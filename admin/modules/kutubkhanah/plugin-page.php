<?php
/**
 * Kutubkhanah: a page a plugin added to the admin (?page=slug), or an Omeka plugin's settings form (?plugin=slug).
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Hooks;
use SLiMS\Kutubkhanah\Plugins;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

Plugins::boot();
echo kk_head();
?>
<div class="kk">
<?php
$page = (string)($_GET['page'] ?? '');
$plugin = (string)($_GET['plugin'] ?? '');
if ($page !== '' && isset(Plugins::pages()[$page])) {
    $pg = Plugins::pages()[$page];
    echo '<header class="kk-head"><h2>' . kk_e($pg['title']) . '</h2></header><div class="kk-panel wrap">';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('kk_wp_handle_settings_post')) kk_wp_handle_settings_post();
    try { ($pg['cb'])(); } catch (\Throwable $e) { echo '<p class="kk-note kk-note--error">' . kk_e($e->getMessage()) . '</p>'; error_log('Kutubkhanah plugin page ' . $page . ': ' . $e->getMessage()); }
    echo '</div>';
} elseif ($plugin !== '' && isset(Plugins::all()[$plugin]) && Plugins::all()[$plugin]['type'] === 'omeka') {
    $info = Plugins::all()[$plugin];
    echo '<header class="kk-head"><h2>' . kk_e(sprintf(__('Configure %s'), $info['name'])) . '</h2></header><div class="kk-panel">';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Hooks::action('omeka:config', ['post' => $_POST]);
        echo '<p class="kk-note">' . __('Saved.') . '</p>';
    }
    echo '<form method="post" class="notAJAX">';
    Hooks::action('omeka:config_form', ['view' => new Kk_Omeka_View()]);
    echo '<div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary">' . __('Save changes') . '</button></div></form></div>';
} else {
    echo '<header class="kk-head"><h2>' . __('Plugin page not found') . '</h2></header>';
}
?>
</div>
<?= kk_script('admin.js') ?>
