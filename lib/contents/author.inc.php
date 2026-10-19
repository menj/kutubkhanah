<?php
/**
 * Kutubkhanah: public author page (index.php?p=author&id=123).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$kkAuthor = \SLiMS\Kutubkhanah\Detail::authorPage((int)($_GET['id'] ?? 0));
if ($kkAuthor) {
    $opac->page_title = htmlspecialchars($kkAuthor['name'], ENT_QUOTES, 'UTF-8');
    echo $kkAuthor['html'];
} else {
    http_response_code(404);
    $opac->page_title = __('Author not found');
    echo '<p class="kk-muted">' . __('There is no author with books in the catalogue at this address.') . '</p>'
        . '<p><a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=shelf">' . __('Browse the catalogue') . '</a></p>';
}
