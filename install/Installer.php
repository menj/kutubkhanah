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

    public static function root(): string { return dirname(__DIR__); }

    public static function configExists(): bool { return is_file(self::root() . '/config/database.php'); }

    public static function envExists(): bool { return is_file(self::root() . '/config/env.php'); }

    /** Requirements shown on the welcome screen. @return array<int,array{label:string,ok:bool,note:string}> */
    public static function requirements(): array
    {
        $rows = [];
        $rows[] = ['label' => 'PHP ' . self::MIN_PHP . ' or newer', 'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='), 'note' => 'This server runs PHP ' . PHP_VERSION];
        foreach (['mysqli', 'pdo_mysql', 'gd', 'mbstring', 'curl', 'xml', 'dom', 'json'] as $ext) {
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

    /** Open a connection to the server (and the database when $name is given). @throws \Exception */
    public static function connect(string $host, string $user, string $pass, ?string $name = null, int $port = 3306): \mysqli
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        if (str_contains($host, ':') && !str_contains($host, '[')) {
            [$h, $p] = explode(':', $host, 2);
            if (ctype_digit($p)) { $host = $h; $port = (int)$p; }
        }
        $db = @new \mysqli($host, $user, $pass, $name ?: null, $port);
        if ($db->connect_errno) throw new \Exception($db->connect_error);
        $db->set_charset('utf8mb4');
        return $db;
    }

    /** The contents of config/database.php for these credentials (safely quoted). */
    public static function configSource(string $host, string $name, string $user, string $pass, int $port = 3306): string
    {
        $sample = file_get_contents(self::root() . '/config/database.sample.php');
        $q = fn($v) => var_export((string)$v, true);
        $sample = str_replace(["'_DB_HOST_'", "'_DB_NAME_'", "'_DB_USER_'", "'_DB_PASSWORD_'", "'_DB_PORT_'", "'_STORAGE_ENGINE_'"],
                              [$q($host), $q($name), $q($user), $q($pass), (string)$port, $q('InnoDB')], $sample);
        // The sample wraps values in quotes already: normalise any doubled quoting.
        return $sample;
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
    public static function isInstalled(\mysqli $db): bool
    {
        $r = $db->query("SHOW TABLES LIKE 'biblio'");
        return $r && $r->num_rows > 0;
    }

    public static function dbFromConfig(): \mysqli
    {
        $profile = require self::root() . '/config/database.php';
        $n = $profile['nodes'][$profile['default_profile'] ?? 'SLiMS'] ?? reset($profile['nodes']);
        return self::connect((string)$n['host'], (string)$n['username'], (string)$n['password'], (string)$n['database'], (int)($n['port'] ?: 3306));
    }

    /**
     * Create the tables and the first administrator.
     * @return string[] error messages (empty on success)
     */
    public static function install(\mysqli $db, array $site): array
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

        require_once self::root() . '/lib/Kutubkhanah/Schema.php';
        \SLiMS\Kutubkhanah\Schema::ensure($db);

        if (!empty($site['sample'])) {
            // Build the search index for the sample records.
            $sysconf['index']['type'] = 'index';
            require_once self::root() . '/simbio2/simbio.inc.php';
            require_once self::root() . '/simbio2/simbio_DB/simbio_dbop.inc.php';
            require_once self::root() . '/admin/modules/system/biblio_indexer.inc.php';
            (new \biblio_indexer($db))->createFullIndex();
        }

        $slims->createEnvFile();   // marks the site as installed
        return [];
    }
}
