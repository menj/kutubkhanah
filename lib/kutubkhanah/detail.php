<?php
/**
 * Kutubkhanah: extras on the OPAC book detail page and the public author page
 * (from the Private Library design: format and reading badges, reading progress,
 * Digital copy / Google Books / Goodreads / WorldCat links, tags, curator's notes,
 * author details and "More by this author").
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Detail
{
    private static function one(string $sql, array $args): ?array
    {
        $st = DB::getInstance()->prepare($sql);
        if (!$st || !$st->execute($args)) return null;
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private static function all(string $sql, array $args): array
    {
        $st = DB::getInstance()->prepare($sql);
        if (!$st || !$st->execute($args)) return [];
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Authors of a book with their Kutubkhanah metadata. */
    private static function authors(int $id): array
    {
        return self::all(
            'SELECT a.author_id, a.author_name, COALESCE(m.nationality, \'\') AS nationality,
                    COALESCE(m.birth_year, \'\') AS birth_year, COALESCE(m.death_year, \'\') AS death_year,
                    COALESCE(m.discipline, \'\') AS discipline
             FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id
             LEFT JOIN kk_author m ON m.author_id = a.author_id
             WHERE ba.biblio_id = ? ORDER BY ba.level, a.author_name',
            [$id]
        );
    }

    public static function years(array $a): string
    {
        if ($a['birth_year'] === '' && $a['death_year'] === '') return '';
        return $a['birth_year'] . '–' . $a['death_year'];
    }

    public static function extras(int $id): string
    {
        $e = [Library::class, 'e'];
        $m = Books::meta($id);
        $out = '<div class="kk-detail-extra">';

        if (($m['alt_title'] ?? '') !== '') {
            $out .= '<p class="kk-alt-title" dir="auto" lang="' . (preg_match('/\p{Arabic}/u', $m['alt_title']) ? 'ar' : '') . '">' . $e($m['alt_title']) . '</p>';
        }
        if (($m['lcc'] ?? '') !== '') {
            $out .= '<p class="kk-detail-lcc"><span>' . __('LC class') . '</span> <span class="kk-detail-call">' . $e($m['lcc']) . '</span></p>';
        }

        $badges = '';
        if ($m['format'] !== '') $badges .= '<span class="kk-badge kk-badge--navy">' . $e($m['format']) . '</span>';
        $badges .= Library::readingBadge((string)$m['reading_status']);
        if ($loan = Loans::current($id)) {
            $badges .= '<span class="kk-badge kk-badge--loan">' . $e($loan['due_on'] ? sprintf(__('On loan · due back %s'), date('j M Y', strtotime($loan['due_on']))) : __('On loan')) . '</span>';
        }
        if ($m['volume'] !== '') $badges .= '<span class="kk-badge kk-badge--muted">' . $e(__('Vol.') . ' ' . $m['volume']) . '</span>';
        if ($badges) $out .= '<div class="kk-detail-extra__badges">' . $badges . '</div>';

        $progress = (int)$m['reading_progress'];
        if ($m['reading_status'] === 'in_progress' && $progress > 0 && Settings::get('show_reading')) {
            $out .= '<div class="kk-progress"><p class="kk-progress__label">' . $e(sprintf(__('Reading progress — %d%%'), $progress)) . '</p>'
                . '<progress class="kk-progress__meter" max="100" value="' . $progress . '">' . $progress . '%</progress></div>';
        }

        $links = '';
        if ($m['digital_link'] !== '') $links .= '<a class="kk-btn kk-btn--gold kk-btn--sm" href="' . $e($m['digital_link']) . '" target="_blank" rel="noopener noreferrer">' . __('View digital copy') . '</a>';
        if ($m['google_books_id'] !== '') $links .= '<a class="kk-btn kk-btn--outline kk-btn--sm" href="https://books.google.com/books?id=' . rawurlencode($m['google_books_id']) . '" target="_blank" rel="noopener noreferrer">Google Books</a>';
        if ($m['goodreads_id'] !== '') $links .= '<a class="kk-btn kk-btn--outline kk-btn--sm" href="https://www.goodreads.com/book/show/' . rawurlencode($m['goodreads_id']) . '" target="_blank" rel="noopener noreferrer">Goodreads</a>';
        if (($m['openlibrary_id'] ?? '') !== '') $links .= '<a class="kk-btn kk-btn--outline kk-btn--sm" href="https://openlibrary.org/books/' . rawurlencode($m['openlibrary_id']) . '" target="_blank" rel="noopener noreferrer">Open Library</a>';
        if (($m['lccn'] ?? '') !== '') $links .= '<a class="kk-btn kk-btn--ghost kk-btn--sm" href="https://lccn.loc.gov/' . rawurlencode($m['lccn']) . '" target="_blank" rel="noopener noreferrer">' . __('Library of Congress') . '</a>';
        if ($m['oclc'] !== '') $links .= '<a class="kk-btn kk-btn--ghost kk-btn--sm" href="https://www.worldcat.org/oclc/' . rawurlencode($m['oclc']) . '" target="_blank" rel="noopener noreferrer">WorldCat</a>';
        if (Settings::get('enable_borrow')) {
            $links = Library::saveButton($id, true) . '<a class="kk-btn kk-btn--primary kk-btn--sm" href="index.php?p=borrow&amp;ids=' . $id . '">' . __('Ask to borrow') . '</a>' . $links;
        }
        if ($links) $out .= '<div class="kk-links">' . $links . '</div>';

        if ($m['tags'] !== '') {
            $out .= '<div class="kk-tags">';
            foreach (array_filter(array_map('trim', explode(',', $m['tags']))) as $t) {
                $out .= '<span class="kk-badge kk-badge--muted">#' . $e($t) . '</span>';
            }
            $out .= '</div>';
        }

        if ($cf = Fields::publicFor($id)) {
            $out .= '<dl class="kk-fields">';
            foreach ($cf as $row) {
                $val = $row['type'] === 'url'
                    ? '<a href="' . $e($row['value']) . '" target="_blank" rel="noopener noreferrer">' . $e(parse_url($row['value'], PHP_URL_HOST) ?: $row['value']) . '</a>'
                    : nl2br($e($row['value']));
                $out .= '<div class="kk-fields__row"><dt>' . $e($row['label']) . '</dt><dd dir="auto">' . $val . '</dd></div>';
            }
            $out .= '</dl>';
        }

        $stars = Library::stars((int)($m['rating'] ?? 0));
        $review = !empty($m['review_public']) ? trim((string)($m['review'] ?? '')) : '';
        if ($stars || $review !== '') {
            $out .= '<div class="kk-review"><p class="kk-review__label">' . __("Owner's review") . ' ' . $stars . '</p>'
                . ($review !== '' ? '<div class="kk-review__body" dir="auto">' . nl2br($e($review)) . '</div>' : '') . '</div>';
        }

        if (trim((string)$m['notes']) !== '' && !empty($m['notes_public'])) {
            $out .= '<div class="kk-notes"><p class="kk-notes__label">' . __("Curator's Notes") . '</p><p class="kk-notes__body">' . $e($m['notes']) . '</p></div>';
        }

        $authors = self::authors($id);
        foreach ($authors as $a) {
            $bits = array_filter([$a['nationality'], self::years($a), $a['discipline']]);
            $out .= '<a class="kk-author-box" href="' . SWB . 'index.php?p=author&amp;id=' . (int)$a['author_id'] . '">'
                . '<strong>' . $e($a['author_name']) . '</strong>'
                . '<p>' . ($bits ? $e(implode(' · ', $bits)) . ' · ' : '') . __('About the author and their books') . ' &rarr;</p></a>';
        }

        if ($authors) {
            $more = Library::books(
                'b.biblio_id <> ? AND EXISTS (SELECT 1 FROM biblio_author x WHERE x.biblio_id = b.biblio_id AND x.author_id = ?)',
                [$id, (int)$authors[0]['author_id']],
                'b.title',
                4
            );
            if ($more) {
                $out .= '<div class="kk-more"><h3>' . $e(sprintf(__('More by %s'), $authors[0]['author_name'])) . '</h3>'
                    . Library::grid($more) . '</div>';
            }
        }
        return $out . '</div>';
    }

    /** Public author page body; null when the author does not exist or has no visible books. */
    public static function authorPage(int $authorId): ?array
    {
        $a = self::one(
            'SELECT a.author_id, a.author_name, COALESCE(m.birth_year, \'\') AS birth_year, COALESCE(m.death_year, \'\') AS death_year,
                    COALESCE(m.nationality, \'\') AS nationality, COALESCE(m.discipline, \'\') AS discipline,
                    COALESCE(m.external_url, \'\') AS external_url, COALESCE(m.notes, \'\') AS notes, COALESCE(m.notes_public, 0) AS notes_public,
                    COALESCE(m.photo, \'\') AS photo, COALESCE(m.bio, \'\') AS bio, COALESCE(m.bio_source, \'\') AS bio_source
             FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id WHERE a.author_id = ?',
            [$authorId]
        );
        if (!$a) return null;
        $books = Library::books(
            'EXISTS (SELECT 1 FROM biblio_author x WHERE x.biblio_id = b.biblio_id AND x.author_id = ?)', [$authorId], 'b.publish_year, b.title', 200
        );
        if (!$books) return null;

        $e = [Library::class, 'e'];
        $rows = [
            __('Born – died') => self::years($a),
            __('Nationality') => $a['nationality'],
            __('Discipline') => $a['discipline'],
        ];
        $html = '<div class="kk-author">';
        if ($a['photo'] !== '') $html .= '<img class="kk-author__photo" src="' . Authors::photoUrl($a['photo'], 300) . '" alt="' . $e($a['author_name']) . '" width="150" loading="lazy">';
        $table = '';
        foreach ($rows as $label => $value) {
            if ($value !== '') $table .= '<tr><th>' . $e($label) . '</th><td>' . $e($value) . '</td></tr>';
        }
        if ($a['external_url'] !== '') {
            $table .= '<tr><th>' . __('More about') . '</th><td><a href="' . $e($a['external_url']) . '" target="_blank" rel="noopener noreferrer">'
                . $e(parse_url($a['external_url'], PHP_URL_HOST) ?: $a['external_url']) . '</a></td></tr>';
        }
        if ($table) $html .= '<table class="kk-meta-table"><tbody>' . $table . '</tbody></table>';
        if ($a['notes'] !== '' && $a['notes_public']) {
            $html .= '<div class="kk-notes"><p class="kk-notes__label">' . __("Curator's Notes") . '</p><p class="kk-notes__body">' . $e($a['notes']) . '</p></div>';
        }
        if ($a['bio'] !== '') {
            $html .= '<div class="kk-notes"><p class="kk-notes__label">' . __('About the author') . '</p><p class="kk-notes__body">' . $e($a['bio']) . '</p>'
                . ($a['bio_source'] !== '' ? '<p class="kk-muted kk-author__src">' . $e(sprintf(__('Source: %s'), $a['bio_source'])) . '</p>' : '') . '</div>';
        }
        $html .= '</div><h3>' . $e(sprintf(count($books) === 1 ? __('%d book in the collection') : __('%d books in the collection'), count($books))) . '</h3>'
            . Library::grid($books);
        return ['name' => $a['author_name'], 'html' => $html];
    }
}
