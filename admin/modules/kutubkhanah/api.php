<?php
/**
 * Kutubkhanah JSON endpoint: scan, retry, covers, import, settings, export.
 * Requires a logged-in librarian with write access and the per-session token.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Comments as BlogComments;
use SLiMS\Kutubkhanah\Blog\Import as BlogImport;
use SLiMS\Kutubkhanah\Blog\Media;
use SLiMS\Kutubkhanah\Blog\Posts;
use SLiMS\Kutubkhanah\Blog\Render as BlogRender;
use SLiMS\Kutubkhanah\Blog\Terms;
use SLiMS\Kutubkhanah\Books;
use SLiMS\Kutubkhanah\Covers;
use SLiMS\Kutubkhanah\DocSearch;
use SLiMS\Kutubkhanah\Duplicates;
use SLiMS\Kutubkhanah\Lists;
use SLiMS\Kutubkhanah\Fields;
use SLiMS\Kutubkhanah\Importer;
use SLiMS\Kutubkhanah\Messages;
use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Loans;
use SLiMS\Kutubkhanah\Series;
use SLiMS\Kutubkhanah\Settings;
use SLiMS\Kutubkhanah\Theme;
use SLiMS\Kutubkhanah\Plugins;
use SLiMS\Kutubkhanah\Wishlist;

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
            if (($input['mode'] ?? '') === 'wish') kk_json(Wishlist::addFromIsbn((string)($input['isbn'] ?? '')));
            kk_json(Books::createFromIsbn((string)($input['isbn'] ?? ''), !empty($input['add_copy'])));

        case 'wish_save':
            $v = (array)($input['values'] ?? []);
            if ((int)($input['id'] ?? 0) === 0 && trim((string)($v['title'] ?? '')) === '' && trim((string)($v['isbn'] ?? '')) !== '') {
                $r = Wishlist::addFromIsbn((string)$v['isbn']);
                if ($r['status'] === 'owned') kk_json(['message' => __('That book is already in the library.')], 400);
                kk_json(['ok' => true] + $r);
            }
            kk_json(['ok' => true, 'id' => Wishlist::save((int)($input['id'] ?? 0), $v)]);

        case 'wish_delete':
            kk_json(['ok' => Wishlist::delete((int)($input['id'] ?? 0))]);

        case 'wish_acquire':
            kk_json(['ok' => true] + Wishlist::moveToLibrary((int)($input['id'] ?? 0)));

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

        case 'authors_fetch':
            if (!empty($input['reset'])) \SLiMS\Kutubkhanah\Authors::resetTried();
            $pending = \SLiMS\Kutubkhanah\Authors::pending(max(1, min(6, (int)($input['limit'] ?? 4))));
            $found = 0;
            foreach ($pending as $a) { if (\SLiMS\Kutubkhanah\Authors::fetch((int)$a['author_id'])) $found++; }
            kk_json(['processed' => count($pending), 'found' => $found, 'remaining' => \SLiMS\Kutubkhanah\Authors::countPending()]);

        case 'author_review':
            $op = (string)($input['op'] ?? 'save');
            if (!in_array($op, ['save', 'approve', 'reject', 'refetch'], true)) kk_json(['message' => __('Unknown action.')], 400);
            if (!\SLiMS\Kutubkhanah\Authors::review((int)($input['id'] ?? 0), $op, $input)) kk_json(['message' => __('Author not found.')], 404);
            $m = \SLiMS\Kutubkhanah\Authors::meta((int)$input['id']);
            kk_json(['ok' => true, 'ol_name' => (string)($m['ol_name'] ?? ''), 'ol_key' => (string)($m['ol_key'] ?? ''), 'found' => ($m['bio_source'] ?? '') !== '']);

        case 'suggest_to_wishlist':
            $mid = (int)($input['id'] ?? 0);
            $row = \SLiMS\Kutubkhanah\Library::rows("SELECT subject, affiliation, body FROM kk_message WHERE id = ? AND type = 'suggest'", [$mid])[0] ?? null;
            if (!$row) kk_json(['message' => __('Suggestion not found.')], 404);
            try {
                Wishlist::save(0, ['title' => $row['subject'], 'authors' => $row['affiliation'], 'notes' => $row['body'], 'priority' => 2, 'is_public' => 1]);
            } catch (\Throwable $e) { kk_json(['message' => $e->getMessage()], 400); }
            Messages::setStatus($mid, 'approved');
            kk_json(['ok' => true]);

        case 'restore':
            if (empty($_FILES['sql']['tmp_name']) || (int)$_FILES['sql']['error'] !== UPLOAD_ERR_OK) kk_json(['message' => __('No file was uploaded.')], 400);
            if (empty($_POST['confirm'])) kk_json(['message' => __('Tick the box to confirm the restore.')], 400);
            if (!utility::havePrivilege('system', 'w')) kk_json(['message' => __('Only a system administrator can restore a backup.')], 403);
            $dumper = \SLiMS\DB::backup();
            if (!($dumper instanceof \SLiMS\Kutubkhanah\Db\Dump)) kk_json(['message' => __('On MySQL/MariaDB, restore with System > Backup or your own tool.')], 400);
            $dir = rtrim((string)$sysconf['backup_dir'], '/\\') . DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $safety = $dir . 'before-restore-' . date('Ymd-His') . '.sql';
            try {
                $counts = $dumper->restore($_FILES['sql']['tmp_name'], $safety);
            } catch (\Throwable $e) {
                kk_json(['message' => $e->getMessage()], 400);
            }
            kk_json(['ok' => true, 'tables' => count($counts), 'rows' => array_sum($counts), 'safety' => basename($safety)]);

        case 'theme_activate':
            $slug = (string)($input['slug'] ?? '');
            if (!Theme::activate($slug)) kk_json(['message' => __('Theme not found.')], 404);
            kk_json(['ok' => true]);

        case 'plugin_activate':
            [$ok, $msg] = Plugins::activate((string)($input['slug'] ?? ''));
            if (!$ok) kk_json(['message' => $msg], 400);
            kk_json(['ok' => true]);

        case 'plugin_deactivate':
            kk_json(['ok' => Plugins::deactivate((string)($input['slug'] ?? ''))]);

        case 'plugin_delete':
            if (!Plugins::delete((string)($input['slug'] ?? ''))) kk_json(['message' => __('Deactivate a plugin before deleting it.')], 400);
            kk_json(['ok' => true]);

        case 'install_zip':
            if (empty($_FILES['zip']['tmp_name']) || (int)$_FILES['zip']['error'] !== UPLOAD_ERR_OK) kk_json(['message' => __('No file was uploaded.')], 400);
            if (empty($_POST['trust'])) kk_json(['message' => __('Tick the box to confirm you trust this file.')], 400);
            $target = ($_POST['target'] ?? '') === 'themes' ? 'themes' : 'plugins';
            [$ok, $msg] = Plugins::installZip($_FILES['zip']['tmp_name'], $target);
            if (!$ok) kk_json(['message' => $msg], 400);
            kk_json(['ok' => true, 'slug' => $msg]);

        case 'settings':
            $tab = (string)($input['tab'] ?? '');
            if (!isset(Settings::fields()[$tab])) kk_json(['message' => 'Unknown tab'], 400);
            Settings::save(Settings::sanitize($tab, (array)($input['values'] ?? [])));
            kk_json(['ok' => true]);

        case 'book_save':
            $id = (int)($input['id'] ?? 0);
            if (!$id || Books::title($id) === '') kk_json(['message' => __('Book not found.')], 404);
            Books::setMeta($id, Books::sanitizeExtras((array)($input['values'] ?? [])));
            Fields::saveFromInput($id, (array)($input['values'] ?? []));
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

        case 'field_save':
            kk_json(['ok' => true, 'id' => Fields::save((int)($input['id'] ?? 0), (array)($input['values'] ?? []))]);

        case 'field_delete':
            kk_json(['ok' => Fields::delete((int)($input['id'] ?? 0))]);

        /* ---------- Blog ---------- */
        case 'post_save':
            $user = ['id' => (int)($_SESSION['uid'] ?? 0), 'name' => (string)($_SESSION['realname'] ?? '')];
            $pid = Posts::save((int)($input['id'] ?? 0), (array)($input['values'] ?? []), $user, (string)($input['type'] ?? 'post'));
            $p = Posts::get($pid);
            kk_json(['ok' => true, 'id' => $pid, 'status' => $p['status'], 'status_label' => Posts::statusLabels()[$p['status']] ?? $p['status'],
                     'slug' => $p['slug'], 'view' => Posts::url($p) . ($p['status'] === 'publish' ? '' : '&preview=' . BlogRender::previewKey($pid))]);

        case 'post_status':
            kk_json(['ok' => Posts::setStatus((int)($input['id'] ?? 0), (string)($input['status'] ?? ''))]);

        case 'post_delete':
            kk_json(['ok' => Posts::delete((int)($input['id'] ?? 0))]);

        case 'revision_restore':
            $user = ['id' => (int)($_SESSION['uid'] ?? 0), 'name' => (string)($_SESSION['realname'] ?? '')];
            kk_json(['ok' => true, 'id' => Posts::restoreRevision((int)($input['id'] ?? 0), $user)]);

        case 'term_save':
            kk_json(['ok' => true, 'id' => Terms::save((int)($input['id'] ?? 0), (string)($input['taxonomy'] ?? 'category'), (array)($input['values'] ?? []))]);

        case 'term_delete':
            kk_json(['ok' => Terms::delete((int)($input['id'] ?? 0))]);

        case 'comment_status':
            kk_json(['ok' => BlogComments::setStatus((int)($input['id'] ?? 0), (string)($input['status'] ?? ''))]);

        case 'comment_delete':
            kk_json(['ok' => BlogComments::delete((int)($input['id'] ?? 0))]);

        case 'comment_reply':
            $v = (array)($input['values'] ?? []);
            kk_json(['ok' => BlogComments::reply((int)($input['id'] ?? 0), (string)($v['reply'] ?? ''), (string)($_SESSION['realname'] ?? 'Librarian'))]);

        case 'media_upload':
            if (empty($_FILES['file'])) kk_json(['message' => __('No file was uploaded.')], 400);
            kk_json(['ok' => true] + Media::upload($_FILES['file'], (int)($_SESSION['uid'] ?? 0), (string)($_POST['alt'] ?? '')));

        case 'media_list':
            kk_json(Media::list((int)($input['page'] ?? 1), 60));

        case 'media_update':
            kk_json(['ok' => Media::update((int)($input['id'] ?? 0), (array)($input['values'] ?? []))]);

        case 'media_delete':
            kk_json(['ok' => Media::delete((int)($input['id'] ?? 0))]);

        case 'book_search':
            $q = trim((string)($input['q'] ?? ''));
            if ($q === '') kk_json(['rows' => []]);
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $st = \SLiMS\DB::getInstance()->prepare("SELECT b.biblio_id AS id, b.title, b.publish_year AS year, b.image,
                (SELECT GROUP_CONCAT(a.author_name SEPARATOR ', ') FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) AS authors
                FROM biblio b WHERE b.title LIKE ? OR b.sor LIKE ? OR REPLACE(b.isbn_issn, '-', '') LIKE ? ORDER BY b.title LIMIT 20");
            $st->execute([$like, $like, '%' . (preg_replace('/[^0-9Xx]/', '', $q) ?: '#') . '%']);
            $rows = array_map(fn($r) => $r + ['thumb' => $r['image'] ? SWB . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($r['image']) . '&width=80' : ''], $st->fetchAll(\PDO::FETCH_ASSOC));
            kk_json(['rows' => $rows]);

        case 'wxr_import':
            if (empty($_FILES['wxr']['tmp_name']) || (int)$_FILES['wxr']['error'] !== UPLOAD_ERR_OK) kk_json(['message' => __('No file was uploaded.')], 400);
            @set_time_limit(600);
            kk_json(['ok' => true] + BlogImport::run($_FILES['wxr']['tmp_name'], ['id' => (int)($_SESSION['uid'] ?? 0), 'name' => (string)($_SESSION['realname'] ?? '')],
                !empty($_POST['images'])));

        case 'series_save':
            $v = (array)($input['values'] ?? []);
            Series::save((string)($v['name'] ?? ''), (int)($v['total'] ?? 0), (string)($v['description'] ?? ''));
            kk_json(['ok' => true]);

        case 'dup_merge':
            $n = Duplicates::merge((int)($input['keep'] ?? 0), (array)($input['drop'] ?? []));
            kk_json(['ok' => true, 'merged' => $n]);

        case 'work_link':
            Duplicates::linkEditions((array)($input['ids'] ?? []));
            kk_json(['ok' => true]);

        case 'work_unlink':
            Duplicates::unlink((int)($input['id'] ?? 0));
            kk_json(['ok' => true]);

        case 'list_save':
            kk_json(['ok' => true, 'id' => Lists::save((int)($input['id'] ?? 0), (array)($input['values'] ?? []))]);

        case 'list_delete':
            Lists::delete((int)($input['id'] ?? 0));
            kk_json(['ok' => true]);

        case 'list_add':
            Lists::add((int)($input['list'] ?? 0), (int)($input['book'] ?? 0));
            kk_json(['ok' => true]);

        case 'list_remove':
            Lists::remove((int)($input['list'] ?? 0), (int)($input['book'] ?? 0));
            kk_json(['ok' => true]);

        case 'list_move':
            Lists::move((int)($input['list'] ?? 0), (int)($input['book'] ?? 0), (int)($input['dir'] ?? 0));
            kk_json(['ok' => true]);

        case 'list_note':
            Lists::note((int)($input['list'] ?? 0), (int)($input['book'] ?? 0), (string)($input['note'] ?? ''));
            kk_json(['ok' => true]);

        case 'doc_pending':
            kk_json(['ids' => DocSearch::pending(), 'counts' => DocSearch::counts()]);

        case 'doc_index':
            kk_json(['ok' => true] + DocSearch::index((int)($input['id'] ?? 0), empty($input['no_ocr'])));

        case 'doc_forget':
            DocSearch::forget((int)($input['id'] ?? 0));
            kk_json(['ok' => true]);

        case 'loan_save':
            kk_json(['ok' => true, 'id' => Loans::lend((int)($input['id'] ?? 0), (array)($input['values'] ?? []))]);

        case 'loan_return':
            kk_json(['ok' => Loans::giveBack((int)($input['id'] ?? 0), (string)($input['on'] ?? ''))]);

        case 'loan_delete':
            kk_json(['ok' => Loans::delete((int)($input['id'] ?? 0))]);

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
