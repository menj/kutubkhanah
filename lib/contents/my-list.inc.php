<?php
/**
 * Kutubkhanah: saved books (index.php?p=my-list). The list lives in the visitor's own browser;
 * this page only draws the cards for the ids it is given (fragment=1) and the shell around them.
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Messages;
use SLiMS\Kutubkhanah\Settings;

if (isset($_GET['fragment'])) {
    $ids = Messages::ids((string)($_GET['ids'] ?? ''));
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    echo $ids ? Library::grid(Library::books('b.biblio_id IN (' . implode(',', $ids) . ')', [], 'b.title', 30)) : '';
    exit;
}

$opac->page_title = __('Saved books');
?>
<div class="kk-mylist" id="kk-mylist" data-borrow="<?= Settings::get('enable_borrow') ? '1' : '0' ?>">
    <p class="kk-muted"><?= __('Books you save are kept in this browser only. Nothing is sent to the library until you ask to borrow them.') ?></p>
    <div id="kk-mylist-grid" aria-live="polite"></div>
    <p class="kk-muted" id="kk-mylist-empty" hidden><?= __('You have not saved any books yet. Use the bookmark on a book to save it here.') ?> <a href="index.php?p=shelf"><?= __('Browse the catalogue') ?></a></p>
    <div class="kk-actions" id="kk-mylist-actions" hidden>
        <?php if (Settings::get('enable_borrow')) : ?><a class="kk-btn kk-btn--primary" id="kk-mylist-borrow" href="index.php?p=borrow"><?= __('Ask to borrow these') ?></a><?php endif; ?>
        <button type="button" class="kk-btn kk-btn--outline" id="kk-mylist-clear"><?= __('Clear list') ?></button>
    </div>
</div>
