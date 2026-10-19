<?php
/**
 * Kutubkhanah: Request Access page (index.php?p=request_access).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$opac->page_title = __('Request Access');
echo \SLiMS\Kutubkhanah\Forms::render('access');
