<?php
/**
 * Kutubkhanah: static pages written in the blog editor (index.php?p=page&slug=…).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

[$kkTitle, $kkHtml, $kkCode, $GLOBALS['kk_meta']] = \SLiMS\Kutubkhanah\Blog\Render::single('page', (string)($_GET['slug'] ?? ''), (string)($_GET['preview'] ?? ''));
http_response_code($kkCode);
$opac->page_title = htmlspecialchars($kkTitle, ENT_QUOTES, 'UTF-8');
echo $kkHtml;
