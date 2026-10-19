<?php
/**
 * Kutubkhanah admin bootstrap: session, privileges, request token and asset helpers.
 * Each page defines KK_PAGE_WRITE = true when it needs write access.
 *
 * GNU GPL v3 or later.
 */

define('INDEX_AUTH', '1');
define('DB_ACCESS', 'fa');

require __DIR__ . '/../../../sysconfig.inc.php';
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';

$kk_can_read = utility::havePrivilege('kutubkhanah', 'r');
$kk_can_write = utility::havePrivilege('kutubkhanah', 'w');

// Create or upgrade Kutubkhanah's tables once per session (covers upgrades from earlier versions).
if (($_SESSION['kk_schema'] ?? '') !== \SLiMS\Kutubkhanah\Schema::VERSION) {
    \SLiMS\Kutubkhanah\Schema::ensure($dbs);
    $_SESSION['kk_schema'] = \SLiMS\Kutubkhanah\Schema::VERSION;
}

if (empty($_SESSION['kk_token'])) {
    $_SESSION['kk_token'] = bin2hex(random_bytes(16));
}

const KK_VERSION = '1.7.0';

function kk_e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function kk_deny(): void
{
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

/** Stylesheet link plus the JS config object shared by the admin scripts. */
function kk_head(): string
{
    $config = [
        'api'   => MWB . 'kutubkhanah/api.php',
        'token' => $_SESSION['kk_token'],
        'edit'  => MWB . 'bibliography/index.php?detail=true&itemID=',
        'i18n'  => [
            'insecure'   => __('Camera scanning needs a secure (HTTPS) connection. You can still type or paste ISBNs below.'),
            'denied'     => __('Camera access was blocked. Allow it in your browser settings, or type ISBNs below.'),
            'nocamera'   => __('No camera could be started. You can type or paste ISBNs below.'),
            'invalid'    => __('Not a valid ISBN.'),
            'looking'    => __('Looking up…'),
            'created'    => __('Added'),
            'from'       => __('from'),
            'wishadded'  => __('Added to the wishlist'),
            'wished'     => __('Already on the wishlist'),
            'owned'      => __('Already in the library'),
            'fromwish'   => __('Added (was on your wishlist)'),
            'mergeconfirm' => __('Merge these records? The others are removed and cannot be restored.'),
            'nothingnew' => __('Everything is already indexed.'),
            'indexdone'  => __('%d files indexed'),
            'failed'     => __('failed'),
            'wxrworking' => __('Importing… this can take a few minutes when images are copied.'),
            'wxrdone'    => __('Done: %1$d posts, %2$d pages, %3$d comments, %4$d images and %5$d categories or tags imported; %6$d skipped (already imported, trashed or drafts that were auto-saved).'),
            'incomplete' => __('Saved, needs details'),
            'duplicate'  => __('Already in the library'),
            'copyadded'  => __('Copy added'),
            'copy'       => __('copy'),
            'copies'     => __('copies'),
            'failed'     => __('Could not save. Check your connection and try again.'),
            'scanning'   => __('Point the camera at the barcode on the back of the book.'),
            'working'    => __('Working…'),
            'updated'    => __('Updated.'),
            'notfound'   => __('No source knows this ISBN yet.'),
            'error'      => __('Something went wrong.'),
            'saved'      => __('Saved.'),
            'progress'   => __('Checked %1$d, covers found %2$d, %3$d to go…'),
            'done'       => __('Finished. Covers found this run: %d.'),
            'imported'   => __('Import finished: %1$d added, %2$d already in the library.'),
        ],
    ];
    return '<link rel="stylesheet" href="' . SWB . 'css/kutubkhanah/fonts.css?v=' . KK_VERSION . '">'
        . \SLiMS\Kutubkhanah\Library::privateFontFaces()
        . '<link rel="stylesheet" href="' . SWB . 'css/kutubkhanah/admin.css?v=' . KK_VERSION . '">'
        . '<script>window.kkAdmin = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
}

function kk_script(string $name): string
{
    return '<script src="' . SWB . 'js/kutubkhanah/' . $name . '?v=' . KK_VERSION . '"></script>';
}
