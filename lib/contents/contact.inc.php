<?php
/**
 * Kutubkhanah: Contact page (index.php?p=contact).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

$opac->page_title = __('Contact');
echo \SLiMS\Kutubkhanah\Forms::render('contact');
