<?php
/**
 * Kutubkhanah: reading lists (index.php?p=lists, index.php?p=lists&list=slug).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

use SLiMS\Kutubkhanah\Lists;
use SLiMS\Kutubkhanah\Settings;

if (!Settings::get('enable_lists')) {
    http_response_code(404);
    $opac->page_title = __('Not found');
    echo '<p class="kk-muted">' . __('This page is not available.') . '</p>';
} elseif (($kkSlug = trim((string)($_GET['list'] ?? ''))) === '') {
    $opac->page_title = __('Reading lists');
    echo Lists::index();
} elseif ($kkList = Lists::bySlug($kkSlug)) {
    $opac->page_title = htmlspecialchars($kkList['title'], ENT_QUOTES, 'UTF-8');
    echo Lists::page($kkList);
    echo '<p class="kk-series__back"><a href="index.php?p=lists">' . __('All reading lists') . '</a></p>';
    echo \SLiMS\Kutubkhanah\Library::nextSteps('lists');
} else {
    http_response_code(404);
    $opac->page_title = __('List not found');
    echo '<p class="kk-muted">' . __('There is no such reading list.') . '</p><p><a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=lists">' . __('All reading lists') . '</a></p>';
}
