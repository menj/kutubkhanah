<?php
/**
 * Kutubkhanah: Suggest a book page (index.php?p=suggest).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$opac->page_title = __('Suggest a book');
echo \SLiMS\Kutubkhanah\Forms::render('suggest');
