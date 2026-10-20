<?php
/**
 * Kutubkhanah: public wishlist (index.php?p=wishlist).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

if (!\SLiMS\Kutubkhanah\Settings::get('enable_wishlist')) {
    http_response_code(404);
    $opac->page_title = __('Page not found');
    echo '<p class="kk-muted">' . __('This page is not available.') . '</p>';
    return;
}
$opac->page_title = __('Wishlist');
echo \SLiMS\Kutubkhanah\Wishlist::page();
echo \SLiMS\Kutubkhanah\Library::nextSteps();
