<?php
/**
 * Kutubkhanah: sitemap (sitemap.xml / robots.txt when readable addresses are on).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

\SLiMS\Kutubkhanah\Seo::sitemap();
