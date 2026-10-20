<?php
/**
 * Kutubkhanah: settings stored as one serialized row in SLiMS's `setting` table,
 * so they load into $sysconf['kutubkhanah'] with the rest of the configuration.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

use SLiMS\DB;

class Settings
{
    const NAME = 'kutubkhanah';

    public static function defaults(): array
    {
        return [
            // Library (identity and public pages)
            'tagline'         => 'Est. 2014 · Private Collection',
            'mission'         => 'A personal collection kept for reading, study and sharing.',
            'footer_desc'     => 'A private home library. Browse the catalogue, or get in touch to visit or borrow.',
            'location'        => 'By appointment only.',
            'home_layout'     => 'library',
            'recent_count'    => 10,
            'enable_contact'  => 1,
            'enable_access'   => 1,
            'notify_email'    => '',
            'show_reading'    => 1,
            'show_ratings'    => 1,
            'enable_wishlist' => 1,
            'enable_rss'      => 1,
            'enable_lists'    => 1,
            'enable_inside'   => 1,
            'ocr_lang'        => 'eng',
            'loan_days'       => 30,
            'permalinks'      => 'pretty',
            // AI discovery files
            'ai_enable'       => 1,
            'ai_log'          => 1,
            'ai_f_llms_txt' => 1, 'ai_f_llm_txt' => 1, 'ai_f_llms_html' => 1, 'ai_f_ai_txt' => 1, 'ai_f_ai_json' => 1,
            'ai_f_identity_json' => 1, 'ai_f_brand_txt' => 1, 'ai_f_faq_ai_txt' => 1, 'ai_f_developer_ai_txt' => 1, 'ai_f_robots_ai_txt' => 1,
            'ai_name'         => '',
            'ai_legal_name'   => '',
            'ai_description'  => '',
            'ai_email'        => '',
            'ai_org_type'     => 'Library',
            'ai_country'      => '',
            'ai_city'         => '',
            'ai_services'     => "Browsing a private catalogue of books\nBorrowing by arrangement\nReading lists and recommendations",
            'ai_not_services' => "Public lending\nSelling books",
            'ai_alternates'   => '',
            'ai_misspellings' => '',
            'ai_never'        => '',
            'ai_voice'        => '',
            'ai_about'        => '',
            'ai_faq'          => '',
            'ai_usage'        => 'allow_with_attribution',
            'ai_training'     => 'restrict',
            'ai_crawlers'     => 'allow_known',
            'ai_citation'     => '',
            'ai_licence'      => '',
            'ai_dev_notes'    => '',
            // General (shelf)
            'shelf_title'     => '',
            'show_authors'    => 1,
            'group_letters'   => 1,
            'show_search'     => 1,
            'show_status'     => 1,
            'default_language' => 'en',
            // Display: navy and gold, from the Private Library design
            'scheme'          => 'auto',
            'columns_min'     => 150,
            'radius'          => 4,
            'light_primary'   => '#0f172a',
            'light_accent'    => '#d4af37',
            'light_bg'        => '#f8f7f4',
            'light_surface'   => '#ffffff',
            'light_text'      => '#1a1a2e',
            'light_muted'     => '#64748b',
            'dark_primary'    => '#e8c84a',
            'dark_accent'     => '#d4af37',
            'dark_bg'         => '#0b1120',
            'dark_surface'    => '#131c2e',
            'dark_text'       => '#e5e7eb',
            'dark_muted'      => '#94a3b8',
            // Lookup
            'src_openlibrary' => 1,
            'src_loc'         => 1,
            'src_google'      => 1,
            'src_k10plus'     => 1,
            'src_custom'      => 0,
            'sru_custom_url'  => '',
            'sru_custom_index' => 'isbn',
            'google_key'      => '',
            'download_covers' => 1,
            // Blog
            'blog_enable'      => 1,
            'blog_title'       => 'Journal',
            'blog_intro'       => 'Notes from the library: reading, collecting and the stories behind the books.',
            'blog_per_page'    => 10,
            'blog_home_count'  => 3,
            'blog_comments'    => 1,
            'blog_moderate'    => 1,
            'blog_notify'      => 1,
            // Sign-in
            'lockout_enable'   => 1,
            'lockout_attempts' => 5,
            'lockout_minutes'  => 15,
        ];
    }

    /** Fields per settings tab, with their type. */
    public static function fields(): array
    {
        return [
            'library' => [
                'tagline' => 'text', 'mission' => 'longtext', 'footer_desc' => 'longtext', 'location' => 'text',
                'home_layout' => 'layout', 'recent_count' => 'int:0:30',
                'enable_contact' => 'bool', 'enable_access' => 'bool', 'notify_email' => 'email', 'show_reading' => 'bool',
                'show_ratings' => 'bool', 'enable_wishlist' => 'bool', 'enable_rss' => 'bool', 'enable_lists' => 'bool', 'enable_inside' => 'bool', 'ocr_lang' => 'text', 'loan_days' => 'int:0:365', 'permalinks' => 'permalinks',
            ],
            'ai' => [
                'ai_enable' => 'bool', 'ai_log' => 'bool',
                'ai_f_llms_txt' => 'bool', 'ai_f_llm_txt' => 'bool', 'ai_f_llms_html' => 'bool', 'ai_f_ai_txt' => 'bool', 'ai_f_ai_json' => 'bool',
                'ai_f_identity_json' => 'bool', 'ai_f_brand_txt' => 'bool', 'ai_f_faq_ai_txt' => 'bool', 'ai_f_developer_ai_txt' => 'bool', 'ai_f_robots_ai_txt' => 'bool',
                'ai_name' => 'text', 'ai_legal_name' => 'text', 'ai_description' => 'text', 'ai_email' => 'email', 'ai_org_type' => 'enum:Library|Organization|LocalBusiness|Person',
                'ai_country' => 'text', 'ai_city' => 'text',
                'ai_services' => 'longtext', 'ai_not_services' => 'longtext', 'ai_alternates' => 'longtext', 'ai_misspellings' => 'longtext', 'ai_never' => 'longtext',
                'ai_voice' => 'longtext', 'ai_about' => 'longtext', 'ai_faq' => 'longtext',
                'ai_usage' => 'enum:allow|allow_with_attribution|restrict|prohibit', 'ai_training' => 'enum:allow|restrict|prohibit',
                'ai_crawlers' => 'enum:allow_all|allow_known|restrict', 'ai_citation' => 'text', 'ai_licence' => 'text', 'ai_dev_notes' => 'longtext',
            ],
            'general' => [
                'shelf_title' => 'text', 'show_authors' => 'bool', 'group_letters' => 'bool',
                'show_search' => 'bool', 'show_status' => 'bool', 'default_language' => 'lang',
            ],
            'display' => [
                'scheme' => 'scheme', 'columns_min' => 'int:110:260', 'radius' => 'int:0:24',
                'light_primary' => 'color', 'light_accent' => 'color', 'light_bg' => 'color', 'light_surface' => 'color',
                'light_text' => 'color', 'light_muted' => 'color',
                'dark_primary' => 'color', 'dark_accent' => 'color', 'dark_bg' => 'color', 'dark_surface' => 'color',
                'dark_text' => 'color', 'dark_muted' => 'color',
            ],
            'lookup' => [
                'src_openlibrary' => 'bool', 'src_loc' => 'bool', 'src_google' => 'bool', 'src_k10plus' => 'bool',
                'src_custom' => 'bool', 'sru_custom_url' => 'url', 'sru_custom_index' => 'sruindex',
                'google_key' => 'text', 'download_covers' => 'bool',
            ],
            'blog' => [
                'blog_enable' => 'bool', 'blog_title' => 'text', 'blog_intro' => 'longtext', 'blog_per_page' => 'int:3:50',
                'blog_home_count' => 'int:0:12', 'blog_comments' => 'bool', 'blog_moderate' => 'bool', 'blog_notify' => 'bool',
            ],
            'security' => [
                'lockout_enable' => 'bool', 'lockout_attempts' => 'int:3:20', 'lockout_minutes' => 'int:1:1440',
            ],
        ];
    }

    public static function all(): array
    {
        global $sysconf;
        $saved = $sysconf[self::NAME] ?? [];
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    /** Validate one tab's posted values; fields from other tabs are left untouched. */
    public static function sanitize(string $tab, array $input): array
    {
        $fields = self::fields()[$tab] ?? [];
        $defaults = self::defaults();
        $out = [];
        foreach ($fields as $key => $type) {
            $raw = $input[$key] ?? null;
            if ($type === 'bool') { $out[$key] = empty($raw) ? 0 : 1; continue; }
            if ($raw === null) continue;
            $raw = trim((string)$raw);
            switch (true) {
                case $type === 'text':
                    $out[$key] = mb_substr(strip_tags($raw), 0, 200);
                    break;
                case $type === 'longtext':
                    $out[$key] = mb_substr(strip_tags($raw), 0, 1000);
                    break;
                case $type === 'email':
                    $out[$key] = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
                    break;
                case $type === 'layout':
                    $out[$key] = in_array($raw, ['library', 'shelf', 'classic'], true) ? $raw : 'library';
                    break;
                case $type === 'permalinks':
                    $out[$key] = $raw === 'plain' ? 'plain' : 'pretty';
                    break;
                case $type === 'color':
                    $out[$key] = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw) ? strtolower($raw) : $defaults[$key];
                    break;
                case $type === 'scheme':
                    $out[$key] = in_array($raw, ['auto', 'light', 'dark'], true) ? $raw : 'auto';
                    break;
                case $type === 'url':
                    $out[$key] = ($raw === '' || (preg_match('#^https?://#i', $raw) && filter_var($raw, FILTER_VALIDATE_URL))) ? mb_substr($raw, 0, 300) : '';
                    break;
                case $type === 'sruindex':
                    $out[$key] = preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,39}$/', $raw) ? $raw : 'isbn';
                    break;
                case $type === 'lang':
                    $out[$key] = preg_match('/^[a-z]{2,5}$/', $raw) ? $raw : 'en';
                    break;
                case strpos($type, 'enum:') === 0:
                    $opts = explode('|', substr($type, 5));
                    $out[$key] = in_array($raw, $opts, true) ? $raw : $defaults[$key];
                    break;
                case strpos($type, 'int:') === 0:
                    [, $min, $max] = explode(':', $type);
                    $out[$key] = max((int)$min, min((int)$max, (int)$raw));
                    break;
            }
        }
        return $out;
    }

    public static function save(array $values): void
    {
        global $sysconf;
        $merged = array_merge(self::all(), $values);
        $db = DB::getInstance();
        $value = serialize($merged);
        $find = $db->prepare('SELECT setting_id FROM setting WHERE setting_name = ?');
        $find->execute([self::NAME]);
        if ($find->fetchColumn()) {
            $db->prepare('UPDATE setting SET setting_value = ? WHERE setting_name = ?')->execute([$value, self::NAME]);
        } else {
            $db->prepare('INSERT INTO setting (setting_name, setting_value) VALUES (?, ?)')->execute([self::NAME, $value]);
        }
        $sysconf[self::NAME] = $merged;
    }

    /** Reading statuses: stored value => label (Libib's three states, Private Library's labels). */
    public static function readingStatuses(): array
    {
        return [
            'not_begun'   => __('Unread'),
            'in_progress' => __('Reading'),
            'completed'   => __('Read'),
        ];
    }

    /** Libib's own labels, used for CSV export so the file re-imports into Libib. */
    public static function libibStatuses(): array
    {
        return ['not_begun' => 'Not begun', 'in_progress' => 'In progress', 'completed' => 'Completed'];
    }

    public static function formats(): array
    {
        return ['Hardcover', 'Paperback', 'Digital Archive', 'Periodical', 'Manuscript', 'Map'];
    }

    /** CSS custom properties for the public pages, light/dark/auto. */
    public static function cssVars(string $selector): string
    {
        $s = self::all();
        $set = function (string $mode) use ($s): string {
            $out = '';
            foreach (['primary', 'accent', 'bg', 'surface', 'text', 'muted'] as $k) $out .= "--kk-$k:" . $s[$mode . '_' . $k] . ';';
            return $out;
        };
        // --kk-deep / --kk-gold stay the light palette's primary and accent in both modes:
        // they colour the navy bands (nav top border, hero, banners, footer) and gold rules.
        $shape = sprintf('--kk-radius:%dpx;--kk-card:%dpx;--kk-deep:%s;--kk-gold:%s;',
            (int)$s['radius'], (int)$s['columns_min'], $s['light_primary'], $s['light_accent']);
        return $selector . '{' . $set('light') . $shape . '}'
            . $selector . '[data-scheme="dark"]{' . $set('dark') . '}'
            . '@media (prefers-color-scheme: dark){' . $selector . '[data-scheme="auto"]{' . $set('dark') . '}}';
    }
}
