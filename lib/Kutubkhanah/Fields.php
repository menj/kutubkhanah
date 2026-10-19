<?php
/**
 * Kutubkhanah: custom fields per format (after OpenBiblio's per-material fields).
 * For example "Signed copy" and "Condition" for books, "Issue number" for periodicals.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Fields
{
    const TYPES = ['text', 'longtext', 'number', 'date', 'bool', 'select', 'url'];

    private static function db(): \PDO
    {
        return \SLiMS\DB::getInstance();
    }

    public static function typeLabels(): array
    {
        return [
            'text' => __('Short text'), 'longtext' => __('Long text'), 'number' => __('Number'), 'date' => __('Date'),
            'bool' => __('Yes / no'), 'select' => __('Choice from a list'), 'url' => __('Link'),
        ];
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM kk_field ORDER BY sort, id')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function formatsOf(array $f): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$f['formats']))));
    }

    /** Fields that apply to a format ('' = every field). */
    public static function forFormat(string $format): array
    {
        return array_values(array_filter(self::all(), function ($f) use ($format) {
            $fs = self::formatsOf($f);
            return !$fs || $format === '' || in_array($format, $fs, true);
        }));
    }

    public static function options(array $f): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$f['options']))));
    }

    public static function values(int $biblioId): array
    {
        $st = self::db()->prepare('SELECT field_id, value FROM kk_field_value WHERE biblio_id = ?');
        $st->execute([$biblioId]);
        return $st->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Clean one value for its field type; '' clears it. */
    public static function clean(array $f, $raw): string
    {
        $v = trim((string)$raw);
        switch ($f['type']) {
            case 'bool':   return $v === '' || $v === '0' ? '' : '1';
            case 'number': return is_numeric($v) ? (string)(0 + $v) : '';
            case 'date':   $d = \DateTime::createFromFormat('!Y-m-d', $v); return $d && $d->format('Y-m-d') === $v ? $v : '';
            case 'select': return in_array($v, self::options($f), true) ? $v : '';
            case 'url':    return ($v !== '' && preg_match('#^https?://#i', $v) && filter_var($v, FILTER_VALIDATE_URL)) ? mb_substr($v, 0, 500) : '';
            case 'longtext': return mb_substr(strip_tags($v), 0, 5000);
            default:       return mb_substr(strip_tags($v), 0, 255);
        }
    }

    /** Save posted "cf_<id>" values for a book. */
    public static function saveFromInput(int $biblioId, array $in): void
    {
        $set = self::db()->prepare('INSERT INTO kk_field_value (biblio_id, field_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $del = self::db()->prepare('DELETE FROM kk_field_value WHERE biblio_id = ? AND field_id = ?');
        foreach (self::all() as $f) {
            $key = 'cf_' . $f['id'];
            if (!array_key_exists($key, $in)) continue;
            $v = self::clean($f, $in[$key]);
            if ($v === '') $del->execute([$biblioId, $f['id']]);
            else $set->execute([$biblioId, $f['id'], $v]);
        }
    }

    /** Human-readable value. */
    public static function display(array $f, string $v): string
    {
        return match ($f['type']) {
            'bool'  => $v === '1' ? __('Yes') : '',
            'date'  => $v !== '' ? date('j F Y', strtotime($v)) : '',
            default => $v,
        };
    }

    /** Public fields with a value, as label => display value (and type for links). */
    public static function publicFor(int $biblioId): array
    {
        $vals = self::values($biblioId);
        $out = [];
        foreach (self::all() as $f) {
            if (empty($f['is_public']) || !isset($vals[$f['id']]) || $vals[$f['id']] === '') continue;
            $out[] = ['label' => $f['label'], 'value' => self::display($f, $vals[$f['id']]), 'type' => $f['type']];
        }
        return $out;
    }

    /** @throws \InvalidArgumentException */
    public static function save(int $id, array $in): int
    {
        $label = mb_substr(trim(strip_tags((string)($in['label'] ?? ''))), 0, 100);
        if ($label === '') throw new \InvalidArgumentException(__('Give the field a name.'));
        $type = in_array($in['type'] ?? '', self::TYPES, true) ? $in['type'] : 'text';
        $options = implode("\n", array_slice(array_values(array_unique(array_filter(array_map(
            fn($o) => mb_substr(trim(strip_tags($o)), 0, 100), preg_split('/\R|,/', (string)($in['options'] ?? ''))
        )))), 0, 50));
        if ($type === 'select' && $options === '') throw new \InvalidArgumentException(__('List the choices, one per line.'));
        $formats = [];
        foreach (Settings::formats() as $fmt) if (!empty($in['fmt_' . md5($fmt)])) $formats[] = $fmt;
        $vals = [$label, $type, $options, implode(',', $formats), empty($in['is_public']) ? 0 : 1, (int)($in['sort'] ?? 0)];
        $db = self::db();
        if ($id > 0) {
            $db->prepare('UPDATE kk_field SET label = ?, type = ?, options = ?, formats = ?, is_public = ?, sort = ? WHERE id = ?')->execute(array_merge($vals, [$id]));
            return $id;
        }
        $db->prepare('INSERT INTO kk_field (label, type, options, formats, is_public, sort) VALUES (?, ?, ?, ?, ?, ?)')->execute($vals);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        self::db()->prepare('DELETE FROM kk_field_value WHERE field_id = ?')->execute([$id]);
        $st = self::db()->prepare('DELETE FROM kk_field WHERE id = ?');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** The input for one field on the Book Details form. */
    public static function input(array $f, string $value): string
    {
        $e = [Library::class, 'e'];
        $name = 'cf_' . (int)$f['id'];
        $id = 'kk-cf-' . (int)$f['id'];
        switch ($f['type']) {
            case 'bool':
                return '<label class="kk-check"><input type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . ($value === '1' ? ' checked' : '') . '> ' . $e(__('Yes')) . '</label>';
            case 'select':
                $o = '<option value="">' . $e(__('— Not set —')) . '</option>';
                foreach (self::options($f) as $opt) $o .= '<option value="' . $e($opt) . '"' . ($opt === $value ? ' selected' : '') . '>' . $e($opt) . '</option>';
                return '<select class="kk-input" id="' . $id . '" name="' . $name . '">' . $o . '</select>';
            case 'longtext':
                return '<textarea class="kk-input kk-textarea" id="' . $id . '" name="' . $name . '" rows="3">' . $e($value) . '</textarea>';
            default:
                $type = ['number' => 'number', 'date' => 'date', 'url' => 'url'][$f['type']] ?? 'text';
                return '<input class="kk-input" type="' . $type . '" id="' . $id . '" name="' . $name . '" value="' . $e($value) . '"' . ($type === 'number' ? ' step="any"' : '') . '>';
        }
    }
}
