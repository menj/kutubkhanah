<?php
/**
 * Kutubkhanah JSON endpoint: scan, retry, covers, import, settings, export.
 * Requires a logged-in librarian with write access and the per-session token.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Books;
use SLiMS\Kutubkhanah\Covers;
use SLiMS\Kutubkhanah\Importer;
use SLiMS\Kutubkhanah\Messages;
use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';

function kk_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$kk_can_write) kk_json(['message' => __('You are not authorized to do that.')], 403);

$action = $_GET['action'] ?? '';

// Export is a plain GET download link carrying the token.
if ($action === 'export') {
    if (!hash_equals($_SESSION['kk_token'], (string)($_GET['token'] ?? ''))) kk_json(['message' => 'Bad token'], 403);
    Importer::export();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') kk_json(['message' => 'POST required'], 405);

$token = $_SERVER['HTTP_X_KK_TOKEN'] ?? ($_POST['kk_token'] ?? '');
if (!hash_equals($_SESSION['kk_token'], (string)$token)) kk_json(['message' => __('Your session has expired. Reload the page.')], 403);

$input = $_POST;
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

// Release the session lock so long-running lookups don't block other admin pages.
session_write_close();

try {
    switch ($action) {
        case 'scan':
            kk_json(Books::createFromIsbn((string)($input['isbn'] ?? ''), !empty($input['add_copy'])));

        case 'retry':
            kk_json(Books::retry((int)($input['id'] ?? 0)));

        case 'covers':
            if (!empty($input['reset'])) Covers::resetTried();
            $ids = Covers::pending(max(1, min(8, (int)($input['limit'] ?? 4))));
            $found = 0;
            foreach ($ids as $id) {
                if (Covers::fetch((int)$id)) $found++;
                Books::reindex((int)$id);
            }
            kk_json(['processed' => count($ids), 'found' => $found, 'remaining' => Covers::countPending()]);

        case 'import':
            if (empty($_FILES['csv']['tmp_name']) || (int)$_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
                kk_json(['message' => __('No file was uploaded.')], 400);
            }
            kk_json(Importer::importFile($_FILES['csv']['tmp_name']));

        case 'settings':
            $tab = (string)($input['tab'] ?? '');
            if (!isset(Settings::fields()[$tab])) kk_json(['message' => 'Unknown tab'], 400);
            Settings::save(Settings::sanitize($tab, (array)($input['values'] ?? [])));
            kk_json(['ok' => true]);

        case 'book_save':
            $id = (int)($input['id'] ?? 0);
            if (!$id || Books::title($id) === '') kk_json(['message' => __('Book not found.')], 404);
            Books::setMeta($id, Books::sanitizeExtras((array)($input['values'] ?? [])));
            Books::reindex($id);
            kk_json(['ok' => true]);

        case 'author_save':
            $id = (int)($input['id'] ?? 0);
            $v = (array)($input['values'] ?? []);
            $t = fn($k, $n) => mb_substr(trim(strip_tags((string)($v[$k] ?? ''))), 0, $n);
            $url = trim((string)($v['external_url'] ?? ''));
            $url = ($url !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) ? mb_substr($url, 0, 500) : '';
            $exists = \SLiMS\DB::getInstance()->prepare('SELECT 1 FROM mst_author WHERE author_id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) kk_json(['message' => __('Author not found.')], 404);
            \SLiMS\DB::getInstance()->prepare(
                'INSERT INTO kk_author (author_id, birth_year, death_year, nationality, discipline, external_url, notes, notes_public)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE birth_year = VALUES(birth_year), death_year = VALUES(death_year), nationality = VALUES(nationality),
                   discipline = VALUES(discipline), external_url = VALUES(external_url), notes = VALUES(notes), notes_public = VALUES(notes_public)'
            )->execute([$id, $t('birth_year', 10), $t('death_year', 10), $t('nationality', 120), $t('discipline', 200), $url,
                        mb_substr(trim(strip_tags((string)($v['notes'] ?? ''))), 0, 5000), empty($v['notes_public']) ? 0 : 1]);
            kk_json(['ok' => true]);

        case 'message_status':
            kk_json(['ok' => Messages::setStatus((int)($input['id'] ?? 0), (string)($input['status'] ?? ''))]);

        case 'message_delete':
            kk_json(['ok' => Messages::delete((int)($input['id'] ?? 0))]);

        default:
            kk_json(['message' => 'Unknown action'], 400);
    }
} catch (\InvalidArgumentException $e) {
    kk_json(['message' => $e->getMessage()], 400);
} catch (\Throwable $e) {
    error_log('Kutubkhanah: ' . $e->getMessage());
    kk_json(['message' => __('Something went wrong.')], 500);
}
