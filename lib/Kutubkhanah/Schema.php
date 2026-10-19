<?php
/**
 * Kutubkhanah: one source of truth for Kutubkhanah's tables, module entry,
 * admin access and seed content. Used by fresh installs, upgrades from plain
 * SLiMS or earlier Kutubkhanah versions, and as self-repair.
 *
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class Schema
{
    const VERSION = '1.5.0';

    /** Columns of kk_book, in order. Missing ones are added on upgrade. */
    private static function bookColumns(): array
    {
        return [
            'needs_details'    => "tinyint(1) NOT NULL default '0'",
            'reading_status'   => "varchar(20) NOT NULL default ''",
            'cover_url'        => "varchar(500) NOT NULL default ''",
            'cover_tried'      => 'datetime default NULL',
            'source'           => "varchar(20) NOT NULL default ''",
            'added'            => 'date default NULL',
            // From Private Library CMS
            'format'           => "varchar(40) NOT NULL default ''",
            'volume'           => "varchar(20) NOT NULL default ''",
            'tags'             => "varchar(255) NOT NULL default ''",
            'digital_link'     => "varchar(500) NOT NULL default ''",
            'oclc'             => "varchar(30) NOT NULL default ''",
            'google_books_id'  => "varchar(30) NOT NULL default ''",
            'goodreads_id'     => "varchar(20) NOT NULL default ''",
            'reading_progress' => "tinyint(3) unsigned NOT NULL default '0'",
            'notes'            => 'text',
            'notes_public'     => "tinyint(1) NOT NULL default '0'",
            // 1.4.0: richer lookups
            'lcc'              => "varchar(60) NOT NULL default ''",
            'lccn'             => "varchar(20) NOT NULL default ''",
            'openlibrary_id'   => "varchar(20) NOT NULL default ''",
            'alt_title'        => "varchar(500) NOT NULL default ''",
            'sources'          => "varchar(120) NOT NULL default ''",
            // 1.5.0: ratings and reviews
            'rating'           => "tinyint(1) unsigned NOT NULL default '0'",
            'review'           => 'text',
            'review_public'    => "tinyint(1) NOT NULL default '0'",
        ];
    }

    public static function ensure(\mysqli $db): void
    {
        $cols = '';
        foreach (self::bookColumns() as $name => $def) $cols .= "`$name` $def,\n";
        $db->query("CREATE TABLE IF NOT EXISTS `kk_book` (
            `biblio_id` int(11) NOT NULL,
            $cols
            PRIMARY KEY (`biblio_id`),
            KEY `needs_details` (`needs_details`),
            KEY `reading_status` (`reading_status`),
            KEY `format` (`format`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Upgrade older kk_book tables column by column.
        $have = [];
        if ($q = $db->query('SHOW COLUMNS FROM `kk_book`')) {
            while ($r = $q->fetch_row()) $have[$r[0]] = true;
        }
        foreach (self::bookColumns() as $name => $def) {
            if (!isset($have[$name])) $db->query("ALTER TABLE `kk_book` ADD `$name` $def");
        }

        $db->query("CREATE TABLE IF NOT EXISTS `kk_author` (
            `author_id` int(11) NOT NULL,
            `birth_year` varchar(10) NOT NULL default '',
            `death_year` varchar(10) NOT NULL default '',
            `nationality` varchar(120) NOT NULL default '',
            `discipline` varchar(200) NOT NULL default '',
            `external_url` varchar(500) NOT NULL default '',
            `notes` text,
            `notes_public` tinyint(1) NOT NULL default '0',
            PRIMARY KEY (`author_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `kk_message` (
            `id` int(11) NOT NULL auto_increment,
            `type` varchar(10) NOT NULL default 'contact',
            `name` varchar(200) NOT NULL default '',
            `email` varchar(254) NOT NULL default '',
            `subject` varchar(400) NOT NULL default '',
            `affiliation` varchar(300) NOT NULL default '',
            `body` text,
            `status` varchar(10) NOT NULL default 'new',
            `ip` varchar(45) NOT NULL default '',
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `type_status` (`type`, `status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 1.5.0: login lockout, lending log, wishlist, series, custom fields.
        $db->query("CREATE TABLE IF NOT EXISTS `kk_login_attempt` (
            `id` int(11) NOT NULL auto_increment,
            `scope` varchar(10) NOT NULL default 'admin',
            `username` varchar(100) NOT NULL default '',
            `ip` varchar(45) NOT NULL default '',
            `attempted_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `lookup` (`scope`, `username`, `ip`, `attempted_at`),
            KEY `by_ip` (`scope`, `ip`, `attempted_at`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `kk_loan` (
            `id` int(11) NOT NULL auto_increment,
            `biblio_id` int(11) NOT NULL,
            `borrower` varchar(150) NOT NULL default '',
            `contact` varchar(150) NOT NULL default '',
            `lent_on` date NOT NULL,
            `due_on` date default NULL,
            `returned_on` date default NULL,
            `notes` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `biblio` (`biblio_id`),
            KEY `open` (`returned_on`, `due_on`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `kk_wish` (
            `id` int(11) NOT NULL auto_increment,
            `isbn` varchar(13) NOT NULL default '',
            `title` varchar(500) NOT NULL default '',
            `authors` varchar(255) NOT NULL default '',
            `publisher` varchar(100) NOT NULL default '',
            `publish_date` varchar(20) NOT NULL default '',
            `cover_url` varchar(500) NOT NULL default '',
            `image` varchar(100) NOT NULL default '',
            `notes` text,
            `priority` tinyint(1) NOT NULL default '2',
            `is_public` tinyint(1) NOT NULL default '1',
            `added` date NOT NULL,
            `acquired_biblio_id` int(11) default NULL,
            PRIMARY KEY (`id`),
            KEY `isbn` (`isbn`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `kk_series` (
            `name` varchar(200) NOT NULL,
            `total` smallint(5) unsigned NOT NULL default '0',
            `description` text,
            PRIMARY KEY (`name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $hadFields = ($t = $db->query("SHOW TABLES LIKE 'kk_field'")) && $t->num_rows > 0;
        $db->query("CREATE TABLE IF NOT EXISTS `kk_field` (
            `id` int(11) NOT NULL auto_increment,
            `label` varchar(100) NOT NULL default '',
            `type` varchar(10) NOT NULL default 'text',
            `options` text,
            `formats` varchar(255) NOT NULL default '',
            `is_public` tinyint(1) NOT NULL default '1',
            `sort` smallint(5) NOT NULL default '0',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `kk_field_value` (
            `biblio_id` int(11) NOT NULL,
            `field_id` int(11) NOT NULL,
            `value` text,
            PRIMARY KEY (`biblio_id`, `field_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Starter custom fields, only when the table is new (deleting them later is respected).
        if (!$hadFields) {
            $books = "Hardcover,Paperback";
            $db->query("INSERT INTO kk_field (label, type, options, formats, is_public, sort) VALUES
                ('Signed copy', 'bool', '', '$books', 1, 1),
                ('Condition', 'select', 'Fine\nVery good\nGood\nFair\nPoor', '$books', 1, 2),
                ('Provenance', 'text', '', '$books', 1, 3),
                ('Purchase price', 'text', '', '', 0, 4),
                ('Issue number', 'text', '', 'Periodical', 1, 5)");
        }

        // Admin module entry and access for the administrator group.
        $q = $db->query("SELECT module_id FROM mst_module WHERE module_path = 'kutubkhanah' LIMIT 1");
        $row = $q ? $q->fetch_row() : null;
        if ($row) {
            $moduleId = (int)$row[0];
        } else {
            $db->query("INSERT INTO mst_module (module_name, module_path, module_desc)
                        VALUES ('kutubkhanah', 'kutubkhanah', 'Scan ISBNs, import from Libib, manage the shelf, authors and inbox')");
            $moduleId = (int)$db->insert_id;
        }
        if ($moduleId) {
            $has = $db->query('SELECT 1 FROM group_access WHERE group_id = 1 AND module_id = ' . $moduleId);
            if ($has && !$has->num_rows) {
                $db->query('INSERT INTO group_access (group_id, module_id, r, w) VALUES (1, ' . $moduleId . ', 1, 1)');
            }
        }

        self::rebrandContent($db);

        // FAQ page (editable in System > Content, path "faq").
        $faq = $db->query("SELECT 1 FROM content WHERE content_path = 'faq'");
        if ($faq && !$faq->num_rows) {
            $html = $db->real_escape_string(self::faqHtml());
            $now = date('Y-m-d H:i:s');
            $db->query("INSERT INTO content (content_title, content_desc, content_path, is_news, input_date, last_update, content_ownpage)
                        VALUES ('Frequently Asked Questions', '$html', 'faq', 0, '$now', '$now', '1')");
        }
    }

    /**
     * Replace the stock pages that earlier installs inherited (about_slims, lisensi_slims, modul_tersedia,
     * opensource) with Kutubkhanah's own, and refresh Library Information and Homepage Info if never edited.
     */
    private static function rebrandContent(\mysqli $db): void
    {
        $now = date('Y-m-d H:i:s');
        $pages = [
            'about_slims'   => ['about_kutubkhanah', 'About Kutubkhanah', '<p><strong>Kutubkhanah</strong> is a home library system. It brings together three open-source library systems, SLiMS, PMB and OpenBiblio, and adds ISBN scanning with automatic lookups, Libib import and export, a cover shelf, author pages and a reader-friendly catalogue.</p><p>Source code and updates: <a href="https://github.com/menj/kutubkhanah">github.com/menj/kutubkhanah</a>.</p>'],
            'lisensi_slims' => ['licence', 'Licence', '<p>Kutubkhanah is free software released under the GNU General Public License, version 3 or later. It includes work from SLiMS, PMB and OpenBiblio under their own open-source licences. See the licence files that come with the software.</p>'],
        ];
        foreach ($pages as $old => [$path, $title, $html]) {
            $exists = $db->query("SELECT 1 FROM content WHERE content_path = '$path'");
            if ($exists && $exists->num_rows) {
                $db->query("DELETE FROM content WHERE content_path = '$old'");
                continue;
            }
            $t = $db->real_escape_string($title);
            $h = $db->real_escape_string($html);
            $db->query("UPDATE content SET content_title = '$t', content_desc = '$h', content_path = '$path', last_update = '$now'
                        WHERE content_path = '$old'");
        }
        $db->query("DELETE FROM content WHERE content_path IN ('modul_tersedia', 'opensource')");

        // Stock seed text, only while it has never been edited (original 2009 timestamp).
        foreach (['libinfo' => '<h3>Contact Information</h3><p>Add your address, email and visiting arrangements here (Admin &gt; System &gt; Content &gt; Library Information).</p>', 'headerinfo' => '<p>Welcome to the library catalogue. Search by title, author, subject or ISBN, or browse the shelf.</p>'] as $path => $html) {
            $h = $db->real_escape_string($html);
            $db->query("UPDATE content SET content_desc = '$h', last_update = '$now'
                        WHERE content_path = '$path' AND last_update = '2009-09-13 19:48:16'");
        }
    }

    private static function faqHtml(): string
    {
        $items = [
            ['Is this library open to the public?', 'This is a private collection. You can browse the catalogue online; to visit or borrow, please use the <a href="index.php?p=request_access">Request Access</a> page.'],
            ['How do I ask about a specific book?', 'Use the <a href="index.php?p=contact">Contact</a> form and mention the book\'s title or ISBN.'],
            ['Are digital copies available?', 'Some items have a digital copy. They are marked "Digital Archive" and have a "View digital copy" link on their detail page.'],
            ['What do the reading labels mean?', '"Unread", "Reading" and "Read" are the owner\'s personal notes and do not affect access.'],
            ['Can I suggest a book?', 'Yes. Use the Contact form with the subject "Suggestion" and include the title, author and ISBN if you have it.'],
        ];
        $out = '<div class="kk-faq">';
        foreach ($items as $i => [$q, $a]) {
            $out .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . $q . '</summary><div><p>' . $a . '</p></div></details>';
        }
        // Edited in Admin > System > Content > "Frequently Asked Questions" (not shown to visitors).
        return $out . '</div>';
    }
}
