<?php
/**
 * Kutubkhanah: blog RSS feed (index.php?p=blog-rss).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

\SLiMS\Kutubkhanah\Blog\Render::rss();
