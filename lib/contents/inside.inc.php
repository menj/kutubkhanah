<?php
/**
 * Kutubkhanah: search inside digital books (index.php?p=inside&q=…).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

use SLiMS\Kutubkhanah\DocSearch;
use SLiMS\Kutubkhanah\Settings;

$opac->page_title = __('Search inside books');
if (!Settings::get('enable_inside')) {
    http_response_code(404);
    echo '<p class="kk-muted">' . __('This page is not available.') . '</p>';
} else {
    echo DocSearch::page(trim((string)($_GET['q'] ?? '')));
}
