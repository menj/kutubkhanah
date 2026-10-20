<?php
/**
 * Kutubkhanah: series pages (index.php?p=series, index.php?p=series&name=…).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

use SLiMS\Kutubkhanah\Series;

$kkName = trim((string)($_GET['name'] ?? ''));
if ($kkName === '') {
    $opac->page_title = __('Series');
    echo Series::index();
} elseif ($kkHtml = Series::page($kkName)) {
    $opac->page_title = htmlspecialchars($kkName, ENT_QUOTES, 'UTF-8');
    echo $kkHtml;
    echo '<p class="kk-series__back"><a href="index.php?p=series">' . __('All series') . '</a></p>';
    echo \SLiMS\Kutubkhanah\Library::nextSteps('series');
} else {
    http_response_code(404);
    $opac->page_title = __('Series not found');
    echo '<p class="kk-muted">' . __('There are no books in this series.') . '</p><p><a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=series">' . __('All series') . '</a></p>';
}
