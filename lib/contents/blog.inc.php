<?php
/**
 * Kutubkhanah: the blog (index.php?p=blog, &post=slug, &category=, &tag=, &month=, &s=).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

use SLiMS\Kutubkhanah\Blog\Posts;
use SLiMS\Kutubkhanah\Blog\Render;

if (!Posts::enabled()) {
    http_response_code(404);
    $opac->page_title = __('Page not found');
    echo '<p class="kk-muted">' . __('This page is not available.') . '</p>';
    return;
}

if (isset($_GET['post'])) {
    [$kkTitle, $kkHtml, $kkCode, $GLOBALS['kk_meta']] = Render::single('post', (string)$_GET['post'], (string)($_GET['preview'] ?? ''));
} else {
    [$kkTitle, $kkHtml, $kkCode] = Render::index($_GET);
}
http_response_code($kkCode);
$opac->page_title = htmlspecialchars($kkTitle, ENT_QUOTES, 'UTF-8');
echo $kkHtml;
