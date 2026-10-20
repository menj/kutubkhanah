<?php
/**
 * Kutubkhanah: wishlist (after PMB's suggestions). Books you want but don't own yet, kept out of
 * the catalogue. Scanning a wished ISBN into the library marks the wish as acquired.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Wishlist
{
    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function priorities(): array
    {
        return [1 => __('Must have'), 2 => __('Would like'), 3 => __('Someday')];
    }

    public static function find(string $isbn13): ?array
    {
        if ($isbn13 === '') return null;
        $st = self::db()->prepare('SELECT * FROM kk_wish WHERE isbn = ? AND acquired_biblio_id IS NULL LIMIT 1');
        $st->execute([$isbn13]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Add by ISBN (details looked up) — the scanner's "Wishlist" mode. @throws \InvalidArgumentException */
    public static function addFromIsbn(string $raw): array
    {
        $isbn = Isbn::normalize($raw);
        if ($isbn === '') throw new \InvalidArgumentException(__('That is not a valid ISBN.'));
        if ($owned = Books::findByIsbn($isbn)) {
            return ['status' => 'owned', 'id' => $owned, 'isbn' => $isbn, 'title' => Books::title($owned)];
        }
        if ($w = self::find($isbn)) return ['status' => 'wished', 'wish' => (int)$w['id'], 'isbn' => $isbn, 'title' => $w['title']];
        $d = Lookup::find($isbn);
        $id = self::save(0, [
            'isbn' => $isbn, 'title' => $d['title'] ?: 'ISBN ' . $isbn, 'authors' => implode(', ', $d['authors']),
            'publisher' => $d['publisher'], 'publish_date' => $d['publish_date'], 'cover_url' => $d['cover_url'],
        ]);
        return ['status' => 'wish_added', 'wish' => $id, 'isbn' => $isbn, 'title' => $d['title'] ?: 'ISBN ' . $isbn,
                'authors' => implode(', ', $d['authors']), 'cover' => $d['cover_url']];
    }

    /** @throws \InvalidArgumentException */
    public static function save(int $id, array $in): int
    {
        $t = fn($k, $n) => mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $n);
        $isbn = isset($in['isbn']) && trim((string)$in['isbn']) !== '' ? Isbn::normalize($in['isbn']) : '';
        if (isset($in['isbn']) && trim((string)$in['isbn']) !== '' && $isbn === '') throw new \InvalidArgumentException(__('That is not a valid ISBN.'));
        $title = $t('title', 500);
        if ($title === '') throw new \InvalidArgumentException(__('Give the book a title.'));
        $cover = trim((string)($in['cover_url'] ?? ''));
        $cover = preg_match('#^https://#i', $cover) && filter_var($cover, FILTER_VALIDATE_URL) ? mb_substr($cover, 0, 500) : '';
        $priority = array_key_exists((int)($in['priority'] ?? 2), self::priorities()) ? (int)($in['priority'] ?? 2) : 2;
        $vals = [$isbn, $title, $t('authors', 255), $t('publisher', 100), $t('publish_date', 20), $cover,
                 mb_substr(trim(strip_tags((string)($in['notes'] ?? ''))), 0, 2000), $priority, array_key_exists('is_public', $in) && empty($in['is_public']) ? 0 : 1];
        $db = self::db();
        if ($id > 0) {
            $db->prepare('UPDATE kk_wish SET isbn = ?, title = ?, authors = ?, publisher = ?, publish_date = ?, cover_url = IF(? = \'\', cover_url, ?),
                          notes = ?, priority = ?, is_public = ? WHERE id = ?')
               ->execute([$vals[0], $vals[1], $vals[2], $vals[3], $vals[4], $vals[5], $vals[5], $vals[6], $vals[7], $vals[8], $id]);
            return $id;
        }
        $db->prepare('INSERT INTO kk_wish (isbn, title, authors, publisher, publish_date, cover_url, notes, priority, is_public, added)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(array_merge($vals, [date('Y-m-d')]));
        $newId = (int)$db->lastInsertId();
        self::fetchCover($newId, $cover, $isbn);
        return $newId;
    }

    /** Keep a local copy of the cover (the public site only loads images from this server). */
    public static function fetchCover(int $id, string $url, string $isbn): void
    {
        if (!Settings::get('download_covers')) return;
        $urls = array_filter([$url, $isbn !== '' ? 'https://covers.openlibrary.org/b/isbn/' . $isbn . '-L.jpg?default=false' : '']);
        foreach ($urls as $u) {
            if (($file = Covers::download($u, 'kk-wish-' . $id)) !== '') {
                self::db()->prepare('UPDATE kk_wish SET image = ? WHERE id = ?')->execute([$file, $id]);
                return;
            }
        }
    }

    public static function delete(int $id): bool
    {
        $st = self::db()->prepare('DELETE FROM kk_wish WHERE id = ?');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** Called when a book enters the library: a matching wish is marked as acquired. */
    public static function acquired(string $isbn13, int $biblioId): bool
    {
        if ($isbn13 === '') return false;
        $st = self::db()->prepare('UPDATE kk_wish SET acquired_biblio_id = ? WHERE isbn = ? AND acquired_biblio_id IS NULL');
        $st->execute([$biblioId, $isbn13]);
        return $st->rowCount() > 0;
    }

    /** "I bought it": create the book from the wish (ISBN lookup when there is one). */
    public static function moveToLibrary(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM kk_wish WHERE id = ? AND acquired_biblio_id IS NULL');
        $st->execute([$id]);
        $w = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$w) throw new \InvalidArgumentException(__('That wish was not found.'));
        if ($w['isbn'] !== '') {
            $r = Books::createFromIsbn($w['isbn']);
            return $r;
        }
        $bid = Books::create(['title' => $w['title'], 'authors' => $w['authors'], 'publisher' => $w['publisher'],
            'publish_date' => $w['publish_date'], 'cover_url' => $w['cover_url'], 'copies' => 1, 'source' => 'wishlist']);
        self::db()->prepare('UPDATE kk_wish SET acquired_biblio_id = ? WHERE id = ?')->execute([$bid, $id]);
        if ($w['image'] !== '') {
            self::db()->prepare('UPDATE biblio SET image = ? WHERE biblio_id = ?')->execute([$w['image'], $bid]);
        } elseif (Settings::get('download_covers') && $w['cover_url'] !== '') {
            Covers::fetch($bid);
        }
        Books::reindex($bid);
        return ['status' => 'created', 'id' => $bid, 'title' => $w['title']];
    }

    public static function open(bool $publicOnly = false): array
    {
        return self::db()->query('SELECT * FROM kk_wish WHERE acquired_biblio_id IS NULL' . ($publicOnly ? ' AND is_public = 1' : '')
            . ' ORDER BY priority, added DESC, id DESC')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function acquiredList(int $limit = 10): array
    {
        return self::db()->query('SELECT w.*, b.title AS book_title FROM kk_wish w JOIN biblio b ON b.biblio_id = w.acquired_biblio_id
            ORDER BY w.id DESC LIMIT ' . max(1, min(100, $limit)))->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Public wishlist page. */
    public static function page(): string
    {
        $e = [Library::class, 'e'];
        $rows = self::open(true);
        if (!$rows) return '<p class="kk-muted">' . __('The wishlist is empty at the moment.') . '</p>';
        $prio = self::priorities();
        $out = '<p class="kk-wish__intro">' . __('Books the library would love to have. If you can help, please get in touch.') . '</p><ul class="kk-wish">';
        foreach ($rows as $w) {
            $cover = $w['image'] !== ''
                ? '<img src="' . Library::cover($w['image'], 160) . '" alt="" loading="lazy">'
                : '<span class="kk-wish__nocover" aria-hidden="true"></span>';
            $out .= '<li class="kk-wish__item"><span class="kk-wish__cover">' . $cover . '</span><span class="kk-wish__body">'
                . '<span class="kk-wish__prio kk-wish__prio--' . (int)$w['priority'] . '">' . $e($prio[(int)$w['priority']] ?? '') . '</span>'
                . '<strong class="kk-wish__title" dir="auto">' . $e($w['title']) . '</strong>'
                . ($w['authors'] !== '' ? '<span class="kk-wish__author" dir="auto">' . $e($w['authors']) . '</span>' : '')
                . '<span class="kk-wish__meta">' . $e(trim($w['publisher'] . ($w['publish_date'] !== '' ? ', ' . $w['publish_date'] : ''), ', '))
                . ($w['isbn'] !== '' ? ' <span class="kk-detail-isbn">ISBN ' . $e($w['isbn']) . '</span>' : '') . '</span>'
                . '</span></li>';
        }
        $contact = Settings::get('enable_contact') ? '<p><a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=contact">' . __('Contact the library') . '</a></p>' : '';
        return $out . '</ul>' . $contact;
    }
}
