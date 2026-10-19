<?php
/**
 * Kutubkhanah: OPAC cover shelf page (index.php?p=shelf).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$opac->page_title = ($_GET['format'] ?? '') === 'Digital Archive' ? __('Digital Archives') : __('Catalogue');
echo \SLiMS\Kutubkhanah\Shelf::render();
