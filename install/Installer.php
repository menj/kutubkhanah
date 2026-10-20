<?php
/**
 * Kutubkhanah: WordPress-style installer helpers (requirements, config writing, installation).
 * GNU GPL v3 or later.
 */

namespace Install;

require_once __DIR__ . '/SLiMS.inc.php';

class Installer
{
    const MIN_PHP = '8.4.0';

    public const DRIVERS = ['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'];

    /** @return string[] driver keys that this PHP can use */
    public static function availableDrivers(): array
    {
        $d = [];
        if (extension_loaded('mysqli')) $d[] = 'mysql';
        if (extension_loaded('pdo_pgsql')) $d[] = 'pgsql';
        if (extension_loaded('pdo_sqlite')) $d[] = 'sqlite';
        return $d;
    }

    public static function root(): string { return dirname(__DIR__); }

    public static function configExists(): bool { return is_file(self::root() . '/config/database.php'); }

    public static function envExists(): bool { return is_file(self::root() . '/config/env.php'); }

    /** Requirements shown on the welcome screen. @return array<int,array{label:string,ok:bool,note:string}> */
    public static function requirements(): array
    {
        $rows = [];
        $rows[] = ['label' => 'PHP ' . self::MIN_PHP . ' or newer', 'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='), 'note' => 'This server runs PHP ' . PHP_VERSION];
        $drivers = self::availableDrivers();
        $rows[] = ['label' => 'A database driver: MySQL, PostgreSQL or SQLite', 'ok' => (bool)$drivers, 'note' => $drivers ? implode(', ', array_map(fn($d) => self::DRIVERS[$d], $drivers)) : 'None found: enable mysqli, pdo_pgsql or pdo_sqlite'];
        foreach (['gd', 'mbstring', 'curl', 'xml', 'dom', 'json'] as $ext) {
            $rows[] = ['label' => 'PHP extension: ' . $ext, 'ok' => extension_loaded($ext), 'note' => extension_loaded($ext) ? 'Loaded' : 'Missing'];
        }
        foreach (['config', 'files', 'images', 'repository'] as $dir) {
            $ok = is_dir(self::root() . '/' . $dir) && is_writable(self::root() . '/' . $dir);
            $rows[] = ['label' => '/' . $dir . ' is writable', 'ok' => $ok, 'note' => $ok ? 'Yes' : 'No: change its permissions'];
        }
        return $rows;
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as $r) {
            // config may be unwritable: the installer then shows the file to paste, as WordPress does.
            if (!$r['ok'] && $r['label'] !== '/config is writable') return false;
        }
        return true;
    }

    /**
     * Open a connection. For MySQL and PostgreSQL the database is created when missing.
     * SQLite: $name is the file path (created with its folder if needed).
     * @throws \Exception
     */
    public static function connect(string $driver, string $host, string $user, string $pass, string $name, int $port = 0, bool $create = false): object
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        if (str_contains($host, ':') && !str_contains($host, '[')) {
            [$h, $p] = explode(':', $host, 2);
            if (ctype_digit($p)) { $host = $h; $port = (int)$p; }
        }
        if ($driver === 'mysql') {
            $port = $port ?: 3306;
            $db = @new \mysqli($host, $user, $pass, '', $port);
            if ($db->connect_errno) throw new \Exception($db->connect_error);
            $db->set_charset('utf8mb4');
            if (!$db->select_db($name)) {
                if (!$create || !$db->query('CREATE DATABASE `' . $db->real_escape_string($name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin')) {
                    throw new \Exception('The database "' . $name . '" does not exist and could not be created (' . $db->error . ')');
                }
                $db->select_db($name);
            }
            return $db;
        }
        self::loadPortable();
        if ($driver === 'sqlite') {
            $path = self::sqlitePath($name);
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new \Exception('Cannot create the folder ' . $dir);
            if (!is_writable($dir)) throw new \Exception('The folder ' . $dir . ' is not writable');
            self::protectDirectory($dir);
            try { $pdo = new \SLiMS\Kutubkhanah\Db\Pdo(['driver' => 'sqlite', 'database' => $path], true); }
            catch (\Throwable $e) { throw new \Exception($e->getMessage()); }
            return new \SLiMS\Kutubkhanah\Db\Mysqli($pdo);
        }
        // PostgreSQL
        $port = $port ?: 5432;
        try {
            $node = ['driver' => 'pgsql', 'host' => $host, 'port' => $port, 'username' => $user, 'password' => $pass, 'database' => $name];
            try {
                $pdo = new \SLiMS\Kutubkhanah\Db\Pdo($node, true);
            } catch (\Throwable $e) {
                if (!$create || !preg_match('/does not exist|3D000/i', $e->getMessage())) throw $e;
                $admin = new \PDO('pgsql:host=' . $host . ';port=' . $port . ';dbname=postgres', $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $admin->exec('CREATE DATABASE "' . str_replace('"', '', $name) . '" ENCODING \'UTF8\' TEMPLATE template0');
                $pdo = new \SLiMS\Kutubkhanah\Db\Pdo($node, true);
            }
        } catch (\Throwable $e) {
            throw new \Exception(preg_replace('/^SQLSTATE\[[^\]]+\]( \[\d+\])?:?\s*/', '', $e->getMessage()));
        }
        return new \SLiMS\Kutubkhanah\Db\Mysqli($pdo);
    }

    public static function loadPortable(): void
    {
        foreach (['dialect', 'ddl', 'statement', 'pdo', 'result', 'stmt', 'mysqli'] as $c) require_once self::root() . '/lib/kutubkhanah/db/' . $c . '.php';
    }

    /** Absolute path for an SQLite file given as typed (relative paths are inside the site folder). */
    public static function sqlitePath(string $name): string
    {
        $name = trim($name);
        if ($name === '' ) $name = 'files/db/kutubkhanah-' . bin2hex(random_bytes(6)) . '.sqlite';
        if ($name[0] === '/' || preg_match('#^[A-Za-z]:[\\/]#', $name)) return $name;
        return self::root() . '/' . ltrim($name, './');
    }

    /** Keep the database file away from browsers (Apache). Other servers must be told in their config. */
    public static function protectDirectory(string $dir): void
    {
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    }

    /** The contents of config/database.php for these credentials (safely quoted). */
    public static function configSource(string $driver, string $host, string $name, string $user, string $pass, int $port = 0): string
    {
        $sample = file_get_contents(self::root() . '/config/database.sample.php');
        $q = fn($v) => var_export((string)$v, true);
        $port = $port ?: ($driver === 'pgsql' ? 5432 : 3306);
        return str_replace(["'_DB_DRIVER_'", "'_DB_HOST_'", "'_DB_NAME_'", "'_DB_USER_'", "'_DB_PASSWORD_'", "'_DB_PORT_'", "'_STORAGE_ENGINE_'"],
                           [$q($driver), $q($host), $q($name), $q($user), $q($pass), (string)$port, $q('InnoDB')], $sample);
    }

    public static function writeConfig(string $source): bool
    {
        $path = self::root() . '/config/database.php';
        if (!is_writable(dirname($path))) return false;
        return file_put_contents($path, $source, LOCK_EX) !== false;
    }

    public static function strongPassword(int $len = 18): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*()-_=+';
        $out = '';
        for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
        return $out;
    }

    /** Has this database got Kutubkhanah tables already? */
    public static function isInstalled(object $db): bool
    {
        $r = $db->query("SHOW TABLES LIKE 'biblio'");
        return $r && $r->num_rows > 0;
    }

    public static function dbFromConfig(): object
    {
        self::loadPortable();
        $profile = require self::root() . '/config/database.php';
        $n = $profile['nodes'][$profile['default_profile'] ?? 'SLiMS'] ?? reset($profile['nodes']);
        return self::connect(\SLiMS\Kutubkhanah\Db\Dialect::normaliseDriver((string)($n['driver'] ?? 'mysql')), (string)($n['host'] ?? ''), (string)($n['username'] ?? ''), (string)($n['password'] ?? ''), (string)($n['database'] ?? ''), (int)($n['port'] ?: 0), false);
    }

    /**
     * Create the tables and the first administrator.
     * @return string[] error messages (empty on success)
     */
    public static function install(object $db, array $site): array
    {
        if (!defined('ACTION')) define('ACTION', 'install');
        if (!defined('INDEX_AUTH')) define('INDEX_AUTH', '1');
        $slims = new SLiMS();
        $slims->setConnection($db);
        if (self::isInstalled($db)) return ['This database already contains Kutubkhanah tables. Empty it first, or point config/database.php at a new database.'];

        require __DIR__ . '/install.sql.php';
        $errors = $slims->query($sql, ['create', 'insert', 'alter', 'update', 'delete', 'truncate', 'drop']);
        $errors = array_merge($errors, $slims->queryTrigger($query_trigger));
        if ($errors) return array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $errors);

        if (!empty($site['sample'])) {
            require __DIR__ . '/install_sample_data.sql.php';
            $errors = $slims->query($sample_sql, ['create', 'insert', 'alter', 'update', 'delete', 'truncate', 'drop']);
            if ($errors) return array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $errors);
        }

        if ($db instanceof \SLiMS\Kutubkhanah\Db\Mysqli) $db->pdo()->resetSequences();   // explicit ids were inserted

        if (!$slims->updateAdmin($site['username'], $site['password'])) return ['Could not create the administrator: ' . $db->error];
        $email = $db->real_escape_string($site['email']);
        $db->query("UPDATE `user` SET email = '$email' WHERE user_id = 1");

        // Site title and visibility.
        $set = function (string $k, $v) use ($db) {
            $k = $db->real_escape_string($k);
            $v = $db->real_escape_string(serialize($v));
            $db->query("INSERT INTO setting (setting_name, setting_value) VALUES ('$k', '$v') ON DUPLICATE KEY UPDATE setting_value = '$v'");
        };
        $set('library_name', $site['title']);
        if (!empty($site['private'])) $set('kk_discourage_search', true);

        require_once self::root() . '/lib/kutubkhanah/schema.php';
        \SLiMS\Kutubkhanah\Schema::ensure($db);

        if (!empty($site['sample'])) {
            // Build the search index for the sample records.
            $sysconf['index']['type'] = 'index';
            require_once self::root() . '/simbio2/simbio.inc.php';
            require_once self::root() . '/simbio2/simbio_DB/simbio_dbop.inc.php';
            require_once self::root() . '/admin/modules/system/biblio_indexer.inc.php';
            (new \biblio_indexer($db))->createFullIndex();
        }

        if ($db instanceof \SLiMS\Kutubkhanah\Db\Mysqli) $db->pdo()->resetSequences();

        $slims->createEnvFile();   // marks the site as installed
        return [];
    }
}
