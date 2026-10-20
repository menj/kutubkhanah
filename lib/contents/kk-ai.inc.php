<?php
/**
 * Kutubkhanah: AI Discovery Files (llms.txt, ai.txt, identity.json ...), index.php?p=kk-ai&file=<slug>.
 * GNU GPL v3 or later.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die('can not access this file directly');
}

\SLiMS\Kutubkhanah\AiFiles::serve((string)($_GET['file'] ?? ''));
