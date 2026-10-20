<?php
/**
 * Kutubkhanah: public read-only JSON API (index.php?p=kk-api&r=books|book|stats|authors).
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

\SLiMS\Kutubkhanah\Api::respond($_GET);
