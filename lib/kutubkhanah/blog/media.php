<?php
/**
 * Kutubkhanah blog: media library (images in images/blog/YYYY/MM/, like WordPress's uploads folder).
 * Large photos are scaled down to 2400 px on upload; smaller sizes are made on demand by the
 * SLiMS thumbnailer and cached.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah\Blog;

class Media
{
    const MAX_SIDE = 2400;
    const MAX_BYTES = 15728640; // 15 MB

    private static function db(): \PDO
    {
        return Posts::db();
    }

    public static function dir(): string
    {
        return IMGBS . 'blog' . DS;
    }

    /** @throws \InvalidArgumentException */
    public static function upload(array $file, int $uid, string $alt = ''): array
    {
        if (empty($file['tmp_name']) || (int)($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException(__('The upload did not arrive. Try again with a smaller image.'));
        }
        if ((int)$file['size'] > self::MAX_BYTES) throw new \InvalidArgumentException(__('Images can be up to 15 MB.'));
        return self::store((string)file_get_contents($file['tmp_name']), (string)($file['name'] ?? 'image'), $uid, $alt);
    }

    /** Save image bytes into the library (uploads, WordPress imports). @throws \InvalidArgumentException */
    public static function store(string $bytes, string $name, int $uid, string $alt = '', string $caption = ''): array
    {
        $info = @getimagesizefromstring($bytes);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) throw new \InvalidArgumentException(__('Only JPEG, PNG, GIF and WebP images can be uploaded.'));
        $ext = $types[$info[2]];
        [$w, $h] = $info;

        // Scale big photos down (keeps animated GIFs as they are).
        if (max($w, $h) > self::MAX_SIDE && $ext !== 'gif' && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($bytes);
            if ($src) {
                $k = self::MAX_SIDE / max($w, $h);
                $nw = (int)round($w * $k);
                $nh = (int)round($h * $k);
                $dst = imagecreatetruecolor($nw, $nh);
                if ($ext === 'png' || $ext === 'webp') { imagealphablending($dst, false); imagesavealpha($dst, true); }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                match ($ext) { 'png' => imagepng($dst, null, 7), 'webp' => imagewebp($dst, null, 85), default => imagejpeg($dst, null, 86) };
                $bytes = (string)ob_get_clean();
                [$w, $h] = [$nw, $nh];
            }
        }

        $sub = date('Y') . DS . date('m');
        $dir = self::dir() . $sub . DS;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new \InvalidArgumentException(__('The images/blog folder is not writable.'));
        $base = Text::slug(pathinfo($name, PATHINFO_FILENAME), 80);
        $file = $base . '.' . $ext;
        for ($n = 2; file_exists($dir . $file); $n++) $file = $base . '-' . $n . '.' . $ext;
        if (file_put_contents($dir . $file, $bytes) === false) throw new \InvalidArgumentException(__('The images/blog folder is not writable.'));

        $rel = date('Y') . '/' . date('m') . '/' . $file;
        $db = self::db();
        $db->prepare('INSERT INTO kk_media (file, mime, width, height, alt, caption, title, created_at, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$rel, $info['mime'], $w, $h, mb_substr(trim(strip_tags($alt)), 0, 300), mb_substr(trim(strip_tags($caption)), 0, 2000),
                      mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 200), date('Y-m-d H:i:s'), $uid]);
        $id = (int)$db->lastInsertId();
        return self::describe(self::get($id));
    }

    public static function get(int $id): ?array
    {
        if ($id <= 0) return null;
        $st = self::db()->prepare('SELECT * FROM kk_media WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Full-size URL, or a resized one (width in px) through the cached thumbnailer. */
    public static function url(array $m, int $width = 0): string
    {
        $path = 'images/blog/' . $m['file'];
        if ($width > 0 && $width < (int)$m['width'] && $m['mime'] !== 'image/gif') {
            return SWB . 'lib/minigalnano/createthumb.php?filename=' . rawurlencode($path) . '&width=' . $width;
        }
        return SWB . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public static function describe(?array $m): array
    {
        if (!$m) return [];
        return ['id' => (int)$m['id'], 'url' => self::url($m), 'medium' => self::url($m, 900), 'thumb' => self::url($m, 300),
                'width' => (int)$m['width'], 'height' => (int)$m['height'], 'alt' => $m['alt'], 'caption' => (string)$m['caption'], 'title' => $m['title']];
    }

    public static function list(int $page = 1, int $per = 40): array
    {
        $total = (int)self::db()->query('SELECT COUNT(*) FROM kk_media')->fetchColumn();
        $rows = self::db()->query('SELECT * FROM kk_media ORDER BY id DESC LIMIT ' . ((max(1, $page) - 1) * $per) . ', ' . (int)$per)->fetchAll(\PDO::FETCH_ASSOC);
        return ['rows' => array_map([self::class, 'describe'], $rows), 'total' => $total];
    }

    public static function update(int $id, array $in): bool
    {
        $st = self::db()->prepare('UPDATE kk_media SET alt = ?, caption = ? WHERE id = ?');
        $st->execute([mb_substr(trim(strip_tags((string)($in['alt'] ?? ''))), 0, 300), mb_substr(trim(strip_tags((string)($in['caption'] ?? ''))), 0, 2000), $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        $m = self::get($id);
        if (!$m) return false;
        $path = realpath(self::dir() . str_replace('/', DS, $m['file']));
        $root = realpath(self::dir());
        if ($path && $root && str_starts_with($path, $root . DS)) @unlink($path);
        self::db()->prepare('UPDATE kk_post SET featured_media = 0 WHERE featured_media = ?')->execute([$id]);
        self::db()->prepare('DELETE FROM kk_media WHERE id = ?')->execute([$id]);
        return true;
    }
}
