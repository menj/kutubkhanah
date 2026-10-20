<?php
use SLiMS\Kutubkhanah\Hooks;
Hooks::addAction('kk_footer', function () { echo '<!-- hello-kk active -->'; });
Hooks::addShortcode('hello', function ($atts) { return '<b>Hello ' . htmlspecialchars($atts['name'] ?? 'reader') . '</b>'; });
