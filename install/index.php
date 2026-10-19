<?php
/**
 * Kutubkhanah installer, modelled on WordPress: welcome, database details, site details, done.
 * No JavaScript is needed; install.js only adds a password show/hide button.
 *
 * GNU GPL v3 or later.
 */

require __DIR__ . '/Installer.php';

use Install\Installer;

session_start();
if (empty($_SESSION['kk_csrf'])) $_SESSION['kk_csrf'] = bin2hex(random_bytes(24));

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$root = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/') . '/';
$step = $_GET['step'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !hash_equals($_SESSION['kk_csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Session expired. Go back and try again.'); }

$errors = [];
$old = [];

// ---- Where are we? ---------------------------------------------------------------------------
$db = null;
$installed = false;
if (Installer::configExists()) {
    try { $db = Installer::dbFromConfig(); $installed = Installer::isInstalled($db) && Installer::envExists(); }
    catch (Exception $e) { $errors[] = 'config/database.php exists but the database cannot be reached: ' . $e->getMessage() . '. Delete config/database.php to enter the details again.'; $step = 'broken'; }
}
if ($installed) $step = 'already';
elseif ($step === 'broken') { /* keep */ }
elseif (Installer::configExists()) { if (!in_array($step, ['site', 'done'], true)) $step = 'ready'; if ($step === 'ready' && isset($_GET['step']) && $_GET['step'] === 'site') $step = 'site'; }
elseif (!in_array($step, ['config', 'welcome'], true)) $step = 'welcome';

// ---- Actions ---------------------------------------------------------------------------------
if ($post && $step === 'config') {
    $old = ['name' => trim($_POST['dbname'] ?? ''), 'user' => trim($_POST['uname'] ?? ''), 'pass' => (string)($_POST['pwd'] ?? ''), 'host' => trim($_POST['dbhost'] ?? '') ?: 'localhost'];
    if ($old['name'] === '' || !preg_match('/^[\w$\-]{1,64}$/u', $old['name'])) $errors[] = 'Enter a database name using letters, numbers, underscore or hyphen.';
    if ($old['user'] === '') $errors[] = 'Enter the database username.';
    if (!$errors) {
        try {
            $c = Installer::connect($old['host'], $old['user'], $old['pass']);
            if (!$c->select_db($old['name'])) {
                // Same as the old installer: create the database when the user is allowed to.
                if (!$c->query('CREATE DATABASE `' . $c->real_escape_string($old['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin')) {
                    $errors[] = 'Connected to the server, but the database "' . $old['name'] . '" does not exist and could not be created (' . $c->error . ').';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Cannot connect to the database: ' . $e->getMessage() . '. Check the username, password and host.';
        }
    }
    if (!$errors) {
        $port = 3306; $host = $old['host'];
        if (preg_match('/^(.+):(\d+)$/', $host, $m) && !str_contains($host, '[')) { $host = $m[1]; $port = (int)$m[2]; }
        $source = Installer::configSource($host, $old['name'], $old['user'], $old['pass'], $port);
        if (Installer::writeConfig($source)) { header('Location: index.php?step=ready'); exit; }
        $_SESSION['kk_config_source'] = $source;
        $step = 'manual';
    }
}

if ($post && $step === 'manual-check') {
    header('Location: index.php' . (Installer::configExists() ? '?step=ready' : '?step=manual')); exit;
}

if ($post && $step === 'site') {
    $old = [
        'title' => trim($_POST['title'] ?? ''), 'username' => trim($_POST['username'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
        'private' => !empty($_POST['private']), 'sample' => !empty($_POST['sample']),
    ];
    $pass = (string)($_POST['password'] ?? '');
    if ($old['title'] === '') $errors[] = 'Give your library a title.';
    if (!preg_match('/^[A-Za-z0-9_.@\- ]{3,50}$/', $old['username'])) $errors[] = 'The username needs 3 to 50 letters, numbers, spaces or _ . @ -';
    if (strlen($pass) < 8) $errors[] = 'The password needs at least 8 characters.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!$errors && $db) {
        $errors = Installer::install($db, ['title' => $old['title'], 'username' => $old['username'], 'password' => $pass, 'email' => $old['email'], 'private' => $old['private'], 'sample' => $old['sample']]);
        if (!$errors) { $_SESSION['kk_done'] = ['username' => $old['username'], 'email' => $old['email']]; header('Location: index.php?step=done'); exit; }
    }
}

$done = $_SESSION['kk_done'] ?? null;
$flowBack = fn(string $to) => 'index.php?step=' . $to;
$token = $_SESSION['kk_csrf'];
$titles = ['welcome' => 'Welcome', 'config' => 'Database details', 'manual' => 'Create the configuration file', 'ready' => 'Database connected', 'site' => 'Information needed', 'done' => 'Success', 'already' => 'Already installed', 'broken' => 'Configuration problem'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kutubkhanah &rsaquo; <?= $h($titles[$step] ?? 'Setup') ?></title>
    <link rel="icon" href="<?= $h($root) ?>images/kutubkhanah/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/install.css">
</head>
<body>
<main class="kk-install">
    <header class="kk-install__brand">
        <img src="<?= $h($root) ?>images/kutubkhanah/favicon.svg" alt="" width="56" height="56">
        <h1>Kutubkhanah</h1>
    </header>

    <?php if ($errors): ?>
        <div class="kk-notice kk-notice--error" role="alert"><?php foreach ($errors as $e): ?><p><?= $h($e) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

<?php if ($step === 'welcome'): $reqs = Installer::requirements(); $ok = Installer::requirementsMet(); ?>
    <p>Welcome to Kutubkhanah. Before we get started, we need some information about your database. You will need to know the following:</p>
    <ol>
        <li>Database name</li>
        <li>Database username</li>
        <li>Database password</li>
        <li>Database host</li>
    </ol>
    <p>We will use this to create a <code>config/database.php</code> file. If that does not work for any reason, we will show you the text to paste into it by hand. Most of the time this information comes from your web host. If you do not have it, ask them first.</p>
    <details class="kk-checks" <?= $ok ? '' : 'open' ?>>
        <summary><?= $ok ? 'Your server is ready' : 'Your server needs attention' ?></summary>
        <ul>
            <?php foreach ($reqs as $r): ?>
                <li class="<?= $r['ok'] ? 'is-ok' : 'is-bad' ?>"><span><?= $h($r['label']) ?></span><em><?= $h($r['note']) ?></em></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <p class="kk-actions"><?php if ($ok): ?><a class="kk-button" href="index.php?step=config">Let's go!</a><?php else: ?><a class="kk-button kk-button--ghost" href="index.php">Check again</a><?php endif; ?></p>

<?php elseif ($step === 'config'): ?>
    <p>Enter your database connection details below. If you are not sure about these, contact your host.</p>
    <form method="post" action="index.php?step=config" class="kk-form">
        <input type="hidden" name="csrf" value="<?= $h($token) ?>">
        <label>Database Name
            <input name="dbname" type="text" value="<?= $h($old['name'] ?? 'kutubkhanah') ?>" required autocomplete="off">
            <small>The name of the database you want to use with Kutubkhanah. It is created for you if it does not exist and your user may create it.</small></label>
        <label>Username
            <input name="uname" type="text" value="<?= $h($old['user'] ?? '') ?>" required autocomplete="off">
            <small>Your database username.</small></label>
        <label>Password
            <input name="pwd" type="password" value="" autocomplete="new-password">
            <small>Your database password.</small></label>
        <label>Database Host
            <input name="dbhost" type="text" value="<?= $h($old['host'] ?? 'localhost') ?>" required autocomplete="off">
            <small>If <code>localhost</code> does not work, ask your host. A port can be added like <code>127.0.0.1:3307</code>.</small></label>
        <p class="kk-actions"><button class="kk-button" type="submit">Submit</button></p>
    </form>

<?php elseif ($step === 'manual'): ?>
    <p>Kutubkhanah could not write <code>config/database.php</code>. You can create the file yourself: paste the text below into a new file called <code>database.php</code> in the <code>config</code> folder, then continue.</p>
    <textarea class="kk-code" rows="18" readonly onclick="this.select()"><?= $h($_SESSION['kk_config_source'] ?? '') ?></textarea>
    <p class="kk-actions"><a class="kk-button" href="index.php?step=ready">I have created it. Run the installation</a></p>

<?php elseif ($step === 'ready'): ?>
    <p>All right. You have made it through this part of the installation. Kutubkhanah can now talk to your database. If you are ready, time now to&hellip;</p>
    <p class="kk-actions"><a class="kk-button" href="index.php?step=site">Run the installation</a></p>

<?php elseif ($step === 'site'): ?>
    <h2>Information needed</h2>
    <p>Please provide the following information. Do not worry, you can always change these settings later.</p>
    <form method="post" action="index.php?step=site" class="kk-form" id="kk-site-form">
        <input type="hidden" name="csrf" value="<?= $h($token) ?>">
        <label>Library Title
            <input name="title" type="text" value="<?= $h($old['title'] ?? 'My Library') ?>" required></label>
        <label>Username
            <input name="username" type="text" value="<?= $h($old['username'] ?? 'admin') ?>" required autocomplete="username">
            <small>Usernames can have only letters, numbers, spaces, underscores, hyphens, periods and the @ symbol.</small></label>
        <label>Password
            <span class="kk-pass">
                <input name="password" id="kk-password" type="password" value="<?= $h(Installer::strongPassword()) ?>" required autocomplete="new-password" minlength="8">
                <button type="button" class="kk-button kk-button--ghost" id="kk-show" aria-pressed="false">Show</button>
            </span>
            <small><strong>Important:</strong> you will need this password to log in. Please store it in a secure location.</small></label>
        <label>Your Email
            <input name="email" type="email" value="<?= $h($old['email'] ?? '') ?>" required autocomplete="email">
            <small>Double-check your email address before continuing.</small></label>
        <label class="kk-check"><input type="checkbox" name="sample" value="1" <?= !empty($old['sample']) ? 'checked' : '' ?>> Add a few sample books so you can see how it looks</label>
        <label class="kk-check"><input type="checkbox" name="private" value="1" <?= !empty($old['private']) ? 'checked' : '' ?>> Discourage search engines from indexing this site <small>It is up to search engines to honour this request.</small></label>
        <p class="kk-actions"><button class="kk-button" type="submit">Install Kutubkhanah</button></p>
    </form>

<?php elseif ($step === 'done'): unset($_SESSION['kk_config_source']); ?>
    <h2>Success!</h2>
    <p>Kutubkhanah has been installed. Thank you, and enjoy your library.</p>
    <table class="kk-table">
        <tr><th>Username</th><td><?= $h($done['username'] ?? 'the one you chose') ?></td></tr>
        <tr><th>Password</th><td><em>The password you chose.</em></td></tr>
    </table>
    <p class="kk-actions"><a class="kk-button" href="<?= $h($root) ?>admin/">Log In</a></p>
    <p class="kk-small">For safety, delete the <code>install</code> folder from your server once you have logged in.</p>

<?php elseif ($step === 'already'): ?>
    <h2>Already Installed</h2>
    <p>Kutubkhanah is already installed. To reinstall, clear the database tables first and delete <code>config/env.php</code> and <code>config/database.php</code>.</p>
    <p class="kk-actions"><a class="kk-button" href="<?= $h($root) ?>admin/">Log In</a></p>

<?php else: /* broken */ ?>
    <p>Fix the problem above, then reload this page.</p>
<?php endif; ?>
</main>
<script src="js/install.js" defer></script>
</body>
</html>
