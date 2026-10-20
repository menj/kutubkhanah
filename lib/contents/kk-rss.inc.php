<?php
/**
 * Kutubkhanah: "New additions" RSS feed (index.php?p=kk-rss).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

\SLiMS\Kutubkhanah\Feed::respond();
