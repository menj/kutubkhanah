<?php
/**
 * Kutubkhanah: Borrow request page (index.php?p=borrow&ids=1,2,3).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$opac->page_title = __('Borrow request');
echo \SLiMS\Kutubkhanah\Forms::render('borrow');
