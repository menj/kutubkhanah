<?php
/**
 * Kutubkhanah: AI Discovery Files. Serves llms.txt, ai.txt, identity.json and the other files of the AI Discovery
 * Files Specification (https://www.ai-visibility.org.uk/specifications/) from the library's own settings and catalogue,
 * so AI assistants describe the library correctly. Nothing is written to disk; every file is generated on request.
 *
 * Native port of the "AI Discovery Files" plugin by 365i (GPL v2 or later); see the credits in README.md.
 * GNU GPL v3 or later.
 */

namespace SLiMS\Kutubkhanah;

class AiFiles
{
    public const SPEC = '1.14.0';

    /** slug => [filename, description, tier, content type] */
    public static function types(): array
    {
        return [
            'llms-txt'         => ['llms.txt', 'AI-readable library identity', 'Essential', 'text/markdown'],
            'ai-txt'           => ['ai.txt', 'What AI systems may and may not do', 'Essential', 'text/plain'],
            'identity-json'    => ['identity.json', 'Structured identity (Schema.org vocabulary)', 'Recommended', 'application/json'],
            'ai-json'          => ['ai.json', 'Machine-readable permissions', 'Recommended', 'application/json'],
            'brand-txt'        => ['brand.txt', 'Naming and wording rules', 'Recommended', 'text/plain'],
            'faq-ai-txt'       => ['faq-ai.txt', 'Pre-answered questions', 'Recommended', 'text/plain'],
            'llm-txt'          => ['llm.txt', 'Compatibility copy of llms.txt', 'Complete', 'text/markdown'],
            'llms-html'        => ['llms.html', 'Human-readable version of llms.txt', 'Complete', 'text/html'],
            'developer-ai-txt' => ['developer-ai.txt', 'Technical context', 'Complete', 'text/plain'],
            'robots-ai-txt'    => ['robots-ai.txt', 'AI crawler directives', 'Complete', 'text/plain'],
        ];
    }

    public static function slugFor(string $filename): ?string
    {
        foreach (self::types() as $slug => $t) if ($t[0] === $filename) return $slug;
        return null;
    }

    public static function isOn(string $slug): bool
    {
        return (bool)Settings::get('ai_enable') && (bool)Settings::get('ai_f_' . str_replace('-', '_', $slug));
    }

    public static function url(string $slug): string
    {
        $f = self::types()[$slug][0] ?? '';
        return Feed::baseUrl() . (Permalink::enabled() ? $f : 'index.php?p=kk-ai&file=' . rawurlencode($slug));
    }

    /* ---------- Data ---------- */

    private static function lines(string $v): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R|;/u', $v) ?: [])));
    }

    private static function db(): \PDO { return \SLiMS\DB::getInstance(); }

    private static function data(): array
    {
        global $sysconf;
        $s = Settings::all();
        $base = rtrim(Feed::baseUrl(), '/');
        $name = trim((string)$s['ai_name']) !== '' ? trim((string)$s['ai_name']) : (string)($sysconf['library_name'] ?? 'Library');
        $tag = trim((string)$s['ai_description']) !== '' ? trim((string)$s['ai_description']) : trim((string)$s['mission']);
        $email = trim((string)$s['ai_email']) !== '' ? trim((string)$s['ai_email']) : (string)$s['notify_email'];
        $db = self::db();
        $count = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
        $stats = [
            'books' => $count('SELECT COUNT(*) FROM biblio WHERE opac_hide = 0'),
            'authors' => $count('SELECT COUNT(DISTINCT author_id) FROM biblio_author'),
            'genres' => $count('SELECT COUNT(DISTINCT topic_id) FROM biblio_topic'),
        ];
        $genres = $db->query('SELECT t.topic FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id GROUP BY t.topic_id, t.topic ORDER BY COUNT(*) DESC, t.topic LIMIT 12')->fetchAll(\PDO::FETCH_COLUMN);
        $pages = [[__('Browse the collection'), $base . '/' . (Permalink::enabled() ? 'shelf' : 'index.php?p=shelf')]];
        $add = function (string $title, string $p, bool $on) use (&$pages, $base) {
            if (!$on) return;
            $to = Permalink::toPretty(['p' => $p]);
            $pages[] = [$title, $base . '/' . (Permalink::enabled() && $to ? $to[0] : 'index.php?p=' . $p)];
        };
        $add(__('Series'), 'series', true);
        $add(__('Reading lists'), 'lists', (bool)$s['enable_lists']);
        $add(__('Search inside books'), 'inside', (bool)$s['enable_inside']);
        $add((string)($s['blog_title'] ?: __('Journal')), 'blog', (bool)$s['blog_enable']);
        $add(__('FAQ'), 'faq', true);
        $add(__('Contact'), 'contact', (bool)$s['enable_contact']);
        return [
            'name' => $name, 'legal' => trim((string)$s['ai_legal_name']), 'tagline' => $tag, 'url' => $base . '/', 'email' => $email,
            'type' => (string)$s['ai_org_type'], 'country' => trim((string)$s['ai_country']), 'city' => trim((string)$s['ai_city']),
            'lang' => str_replace('_', '-', (string)($sysconf['default_lang'] ?? 'en_US')),
            'services' => self::lines((string)$s['ai_services']), 'not' => self::lines((string)$s['ai_not_services']),
            'alt' => self::lines((string)$s['ai_alternates']), 'miss' => self::lines((string)$s['ai_misspellings']), 'never' => self::lines((string)$s['ai_never']),
            'voice' => trim((string)$s['ai_voice']), 'about' => trim((string)$s['ai_about']),
            'usage' => (string)$s['ai_usage'], 'training' => (string)$s['ai_training'], 'crawlers' => (string)$s['ai_crawlers'],
            'citation' => trim((string)$s['ai_citation']), 'licence' => trim((string)$s['ai_licence']), 'dev' => trim((string)$s['ai_dev_notes']),
            'stats' => $stats, 'genres' => $genres, 'pages' => $pages,
            'location' => trim((string)$s['location']),
        ];
    }

    private static function faqs(array $d): array
    {
        $out = [];
        $s = Settings::all();
        foreach (preg_split('/\R/u', (string)$s['ai_faq']) ?: [] as $line) {
            if (strpos($line, '|') === false) continue;
            [$q, $a] = array_map('trim', explode('|', $line, 2));
            if ($q !== '' && $a !== '') $out[] = [$q, $a];
        }
        $out[] = ['What is ' . $d['name'] . '?', ($d['tagline'] !== '' ? $d['tagline'] . ' ' : '') . 'It is a private collection of about ' . $d['stats']['books'] . ' books, catalogued online.'];
        if ($d['not']) $out[] = ['What does ' . $d['name'] . ' not do?', implode('; ', $d['not']) . '.'];
        return $out;
    }

    private static function head(string $slug, string $c = '#'): string
    {
        return "$c AI Discovery Files Specification v" . self::SPEC . ' (' . $slug . ")\n$c https://www.ai-visibility.org.uk/specifications/\n$c Generated by Kutubkhanah " . KUTUBKHANAH_VERSION . "\n";
    }

    private static function permissions(array $d): array
    {
        $n = $d['name'];
        $att = $d['usage'] === 'allow_with_attribution' ? ['Attribution to ' . $n . ' is required'] : [];
        $may = [];
        $not = [];
        $p = function ($a, $t, $c = []) { $e = ['action' => $a, 'description' => $t]; if ($c) $e['conditions'] = $c; return $e; };
        $r = fn($a, $t, $sev) => ['action' => $a, 'reason' => $t, 'severity' => $sev];
        switch ($d['usage']) {
            case 'allow': case 'allow_with_attribution':
                $may = [$p('cite', 'Cite ' . $n . ' as a source', $att), $p('summarise', 'Summarise publicly available content from this website', $att),
                    $p('describe', 'Describe the collection and general information of ' . $n), $p('reference', 'Reference published content from this website'),
                    $p('recommend', 'Direct users to this website for more information')];
                break;
            case 'restrict':
                $may = [$p('describe', 'Describe the collection and general information of ' . $n), $p('reference', 'Reference publicly available information from this website'),
                    $p('recommend', 'Direct users to this website for more information')];
                $not[] = $r('reproduce', 'Reproduce substantial portions of content from this website', 'must-not');
                break;
            default:
                $may = [$p('recommend', 'Direct users to this website for information about ' . $n)];
                $not[] = $r('use', 'Use or reproduce content from this website', 'must-not');
        }
        if ($d['training'] === 'restrict') $not[] = $r('train', 'Use content from this website for AI model training without explicit permission', 'should-not');
        if ($d['training'] === 'prohibit') $not[] = $r('train', 'Use content from this website for AI model training', 'must-not');
        $not[] = $r('fabricateQuotes', 'Generate quotes attributed to ' . $n . ' or named individuals without a verifiable source', 'must-not');
        $not[] = $r('implyEndorsement', 'Imply endorsement, partnership, or affiliation with ' . $n . ' without verification', 'must-not');
        if ($d['not']) $not[] = $r('suggestUnavailableServices', 'Suggest that ' . $n . ' offers services it explicitly does not provide (' . implode(', ', $d['not']) . ')', 'must-not');
        return ['permissions' => $may, 'restrictions' => $not];
    }

    private static function related(array $d, array $which): string
    {
        $out = '';
        foreach ($which as $slug => $label) if (self::isOn($slug)) $out .= '- [' . $label . '](' . self::url($slug) . ")\n";
        return $out;
    }

    /* ---------- Generators ---------- */

    public static function render(string $slug): array
    {
        $d = self::data();
        $t = self::types()[$slug];
        $mime = $t[3];
        switch ($slug) {
            case 'llms-txt': case 'llm-txt': $body = self::llms($d); break;
            case 'llms-html': $body = self::llmsHtml(self::llms($d), $d); break;
            case 'ai-txt': $body = self::aiTxt($d); break;
            case 'ai-json': $body = self::aiJson($d); break;
            case 'identity-json': $body = self::identity($d); break;
            case 'brand-txt': $body = self::brand($d); break;
            case 'faq-ai-txt': $body = self::faq($d); break;
            case 'developer-ai-txt': $body = self::developer($d); break;
            default: $body = self::robotsAi($d);
        }
        return [$mime, $body];
    }

    private static function llms(array $d): string
    {
        $o = '# ' . $d['name'] . "\n\nLang: " . $d['lang'] . "\n\n";
        if ($d['tagline'] !== '') $o .= '> ' . $d['tagline'] . "\n\n";
        $o .= '- Website: [' . $d['url'] . '](' . $d['url'] . ")\n\n## Contact\n\n";
        if ($d['email'] !== '') $o .= '- Email: ' . $d['email'] . "\n";
        $loc = array_filter([$d['city'], $d['country']]);
        if ($loc) $o .= '- Location: ' . implode(', ', $loc) . "\n";
        if ($d['location'] !== '') $o .= '- Visits: ' . $d['location'] . "\n";
        $o .= "\n## The Collection\n\n- Books: " . $d['stats']['books'] . "\n- Authors: " . $d['stats']['authors'] . "\n- Subjects: " . $d['stats']['genres'] . "\n";
        if ($d['genres']) $o .= '- Main subjects: ' . implode(', ', $d['genres']) . "\n";
        if ($d['services']) $o .= "\n## What We Do\n\n- " . implode("\n- ", $d['services']) . "\n";
        if ($d['not']) $o .= "\n## What We Do Not Do\n\n- " . implode("\n- ", $d['not']) . "\n";
        if ($d['about'] !== '') $o .= "\n## About\n\n" . $d['about'] . "\n";
        $o .= "\n## Pages\n\n";
        foreach ($d['pages'] as [$title, $url]) $o .= '- [' . $title . '](' . $url . ")\n";
        $files = '';
        foreach (self::types() as $slug => $t) if ($slug !== 'llms-txt' && self::isOn($slug)) $files .= '- [' . $t[0] . '](' . self::url($slug) . ') - ' . $t[1] . "\n";
        if ($files) $o .= "\n## AI Discovery Files\n\n" . $files;
        if (Settings::get('enable_rss')) $o .= "\n## Feed\n\n- [New additions (RSS)](" . (Permalink::enabled() ? $d['url'] . 'feed.xml' : $d['url'] . 'index.php?p=kk-rss') . ")\n";
        return $o . "\n---\nAI Discovery Files Specification v" . self::SPEC . ' · https://www.ai-visibility.org.uk/specifications/' . "\n";
    }

    private static function llmsHtml(string $md, array $d): string
    {
        $h = '';
        $list = false;
        foreach (preg_split('/\R/', $md) as $ln) {
            $e = fn($t) => preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', htmlspecialchars($t, ENT_QUOTES, 'UTF-8'));
            $isLi = str_starts_with($ln, '- ');
            if ($list && !$isLi) { $h .= "</ul>\n"; $list = false; }
            if (str_starts_with($ln, '## ')) $h .= '<h2>' . $e(substr($ln, 3)) . "</h2>\n";
            elseif (str_starts_with($ln, '# ')) $h .= '<h1>' . $e(substr($ln, 2)) . "</h1>\n";
            elseif (str_starts_with($ln, '> ')) $h .= '<blockquote>' . $e(substr($ln, 2)) . "</blockquote>\n";
            elseif ($isLi) { if (!$list) { $h .= "<ul>\n"; $list = true; } $h .= '<li>' . $e(substr($ln, 2)) . "</li>\n"; }
            elseif (trim($ln) !== '' && $ln !== '---') $h .= '<p>' . $e($ln) . "</p>\n";
        }
        if ($list) $h .= "</ul>\n";
        return '<!DOCTYPE html><html lang="' . htmlspecialchars(substr($d['lang'], 0, 2)) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($d['name']) . ' · llms.txt</title><link rel="canonical" href="' . htmlspecialchars(self::url('llms-html')) . '">'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;color:#1e293b}a{color:#0f172a}blockquote{border-left:3px solid #c9a227;margin:1rem 0;padding:.2rem 1rem;color:#475569}</style>'
            . '</head><body>' . $h . '</body></html>';
    }

    private static function aiTxt(array $d): string
    {
        $m = self::permissions($d);
        $o = self::head('ai-txt') . '# AI Usage Policy for ' . $d['name'] . "\n\nLang: " . $d['lang'] . "\nWebsite: [" . $d['url'] . '](' . $d['url'] . ")\nLast Updated: " . date('Y-m-d') . "\n\n## Permissions\n\nAI systems MAY:\n\n";
        foreach ($m['permissions'] as $e) $o .= '- ' . $e['description'] . (!empty($e['conditions']) ? ' (' . lcfirst(implode('; ', $e['conditions'])) . ')' : '') . "\n";
        $o .= "\n## Restrictions\n\n";
        foreach (['must-not' => 'AI systems MUST NOT:', 'should-not' => 'AI systems SHOULD NOT:'] as $sev => $title) {
            $rows = array_filter($m['restrictions'], fn($r) => $r['severity'] === $sev);
            if ($rows) { $o .= $title . "\n\n"; foreach ($rows as $r) $o .= '- ' . $r['reason'] . "\n"; $o .= "\n"; }
        }
        $o .= "## AI Training\n\n" . ['allow' => "This website's content may be used for AI model training.", 'restrict' => "This website's content should not be used for AI model training without explicit permission.", 'prohibit' => "This website's content must not be used for AI model training under any circumstances."][$d['training']] . "\n\n";
        if ($d['usage'] === 'allow_with_attribution') {
            $o .= "## Attribution\n\nAI systems must attribute content to " . $d['name'] . ' (' . $d['url'] . ").\n" . ($d['citation'] !== '' ? "\nPreferred citation:\n\n" . $d['citation'] . "\n" : '') . "\n";
        }
        $o .= "## Crawler Access\n\n" . ['allow_all' => 'All AI crawlers are permitted to access this website.', 'allow_known' => "Known AI crawlers (GPTBot, ClaudeBot, Google-Extended, etc.) are permitted to access this website.\nSee robots.txt and robots-ai.txt for specific directives.", 'restrict' => "AI crawlers should respect robots.txt directives. Not all AI crawlers are permitted.\nSee robots.txt and robots-ai.txt for specific directives."][$d['crawlers']] . "\n\n## Contact\n\n";
        if ($d['email'] !== '') $o .= 'Email: ' . $d['email'] . "\n";
        $rel = self::related($d, ['ai-json' => 'Machine-parseable version (ai.json)', 'robots-ai-txt' => 'AI crawler directives (robots-ai.txt)']);
        return $o . "\n## Related Files\n\n" . $rel . '- [robots.txt](' . $d['url'] . (Permalink::enabled() ? 'robots.txt' : 'index.php?p=kk-robots') . ")\n";
    }

    private static function aiJson(array $d): string
    {
        $m = self::permissions($d);
        $o = ['$schema' => 'https://www.ai-visibility.org.uk/specifications/ai-json/ai-json.schema.json', 'language' => $d['lang'], 'name' => $d['name'], 'url' => rtrim($d['url'], '/'),
            'permissions' => $m['permissions'], 'restrictions' => $m['restrictions'],
            'attribution' => ['required' => $d['usage'] === 'allow_with_attribution'] + ($d['citation'] !== '' ? ['format' => $d['citation']] : [])];
        if ($d['email'] !== '') $o['contact'] = ['email' => $d['email']];
        $o['scope'] = ['appliesTo' => 'All publicly accessible content on ' . rtrim($d['url'], '/')];
        $o['licensing'] = ['aiTrainingAllowed' => $d['training'] === 'allow'] + ($d['licence'] !== '' ? ['contentLicense' => $d['licence']] : []);
        $o['metadata'] = ['lastUpdated' => date('Y-m-d'), 'generator' => 'Kutubkhanah v' . KUTUBKHANAH_VERSION];
        $rel = [];
        foreach (['humanReadable' => 'ai-txt', 'identity' => 'identity-json', 'brandGuidelines' => 'brand-txt', 'crawlerDirectives' => 'robots-ai-txt'] as $k => $slug) if (self::isOn($slug)) $rel[$k] = self::url($slug);
        if ($rel) $o['relatedFiles'] = $rel;
        return json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function identity(array $d): string
    {
        $o = ['$schema' => 'https://www.ai-visibility.org.uk/specifications/identity-json/identity-json.schema.json', 'language' => $d['lang'], 'name' => $d['name']];
        if ($d['legal'] !== '' && $d['legal'] !== $d['name']) $o['legalName'] = $d['legal'];
        if ($d['alt']) $o['alternateName'] = $d['alt'];
        $o['url'] = rtrim($d['url'], '/');
        $o['type'] = $d['type'];
        $o['description'] = $d['tagline'] !== '' ? $d['tagline'] : $d['name'] . ' is a private book collection.';
        if ($d['email'] !== '') $o['email'] = $d['email'];
        if ($d['city'] !== '' || $d['country'] !== '') $o['address'] = array_filter(['@type' => 'PostalAddress', 'addressLocality' => $d['city'], 'addressCountry' => $d['country']]);
        if ($d['services']) $o['services'] = array_map(fn($n) => ['name' => $n], $d['services']);
        if ($d['not']) $o['notServices'] = $d['not'];
        $o['collection'] = ['books' => $d['stats']['books'], 'authors' => $d['stats']['authors'], 'subjects' => $d['genres']];
        $o['metadata'] = ['lastUpdated' => date('Y-m-d'), 'generator' => 'Kutubkhanah v' . KUTUBKHANAH_VERSION];
        return json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function brand(array $d): string
    {
        $o = self::head('brand-txt') . '# Brand Guidelines for ' . $d['name'] . "\n\nLang: " . $d['lang'] . "\nWebsite: [" . $d['url'] . '](' . $d['url'] . ")\nLast Updated: " . date('Y-m-d') . "\n\n## Official Name\n\n" . $d['name'] . "\n\n";
        if ($d['legal'] !== '' && $d['legal'] !== $d['name']) $o .= "## Legal Name\n\n" . $d['legal'] . "\n\n";
        if ($d['alt']) $o .= "## Also Known As\n\nThe following names are acceptable alternatives:\n\n- " . implode("\n- ", $d['alt']) . "\n\n";
        if ($d['miss']) $o .= "## Common Misspellings\n\nThe following are known misspellings. Correct them to the official name above:\n\n- " . implode("\n- ", $d['miss']) . "\n\n";
        if ($d['never']) $o .= "## Do Not Use\n\nThe following names should never be used to refer to this library:\n\n- " . implode("\n- ", $d['never']) . "\n\n";
        if ($d['voice'] !== '') $o .= "## Brand Voice\n\n" . $d['voice'] . "\n\n";
        if ($d['about'] !== '') $o .= "## About\n\n" . $d['about'] . "\n\n";
        elseif ($d['tagline'] !== '') $o .= "## Description\n\nWhen describing " . $d['name'] . ", use:\n\n> " . $d['tagline'] . "\n\n";
        $rel = self::related($d, ['identity-json' => 'Structured identity (identity.json)', 'llms-txt' => 'Full profile (llms.txt)']);
        return $rel ? $o . "## Related Files\n\n" . $rel : $o;
    }

    private static function faq(array $d): string
    {
        $o = self::head('faq-ai-txt') . '# Frequently Asked Questions - ' . $d['name'] . "\n\nLang: " . $d['lang'] . "\nWebsite: [" . $d['url'] . '](' . $d['url'] . ")\nLast Updated: " . date('Y-m-d') . "\n\n";
        foreach (self::faqs($d) as [$q, $a]) $o .= "---\n\nQ: $q\nA: $a\n\n";
        $o .= "---\n\n";
        if (self::isOn('llms-txt')) $o .= 'Learn more: [llms.txt](' . self::url('llms-txt') . ")\n";
        return $o;
    }

    private static function developer(array $d): string
    {
        $o = self::head('developer-ai-txt') . '# Technical Context - ' . $d['name'] . "\n\nLang: " . $d['lang'] . "\nWebsite: [" . $d['url'] . '](' . $d['url'] . ")\nLast Updated: " . date('Y-m-d') . "\n\n## Platform\n\n- CMS: Kutubkhanah " . KUTUBKHANAH_VERSION . "\n- PHP: " . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . "\n- Character Set: UTF-8\n- Language: " . $d['lang'] . "\n";
        if ($d['dev'] !== '') $o .= "\n## Developer Notes\n\n" . $d['dev'] . "\n";
        $o .= "\n## API Information\n\nA read-only catalogue API is available at " . $d['url'] . 'index.php?p=kk-api' . ".\n\n## Data Feeds\n\n";
        if (Settings::get('enable_rss')) $o .= '- [New additions (RSS)](' . (Permalink::enabled() ? $d['url'] . 'feed.xml' : $d['url'] . 'index.php?p=kk-rss') . ")\n";
        if (Settings::get('blog_enable')) $o .= '- [Journal (RSS)](' . (Permalink::enabled() ? $d['url'] . 'journal/feed.xml' : $d['url'] . 'index.php?p=blog-rss') . ")\n";
        $o .= '- [Sitemap](' . (Permalink::enabled() ? $d['url'] . 'sitemap.xml' : $d['url'] . 'index.php?p=kk-sitemap') . ")\n\n## AI Discovery Files\n\n";
        foreach (self::types() as $slug => $t) if (self::isOn($slug)) $o .= '- [' . $t[0] . '](' . self::url($slug) . ")\n";
        return $o;
    }

    public static function bots(): array
    {
        return [
            'GPTBot' => 'OpenAI (ChatGPT, model training)', 'ChatGPT-User' => 'OpenAI (ChatGPT live user visits)', 'OAI-SearchBot' => 'OpenAI (ChatGPT search indexing)',
            'ClaudeBot' => 'Anthropic (Claude)', 'Claude-User' => 'Anthropic (Claude live user visits)', 'Claude-SearchBot' => 'Anthropic (Claude search indexing)',
            'Google-Extended' => 'Google (Gemini AI training)', 'Googlebot' => 'Google (Search, including AI Overviews)', 'Bingbot' => 'Microsoft (Bing, Copilot)',
            'PerplexityBot' => 'Perplexity AI', 'Perplexity-User' => 'Perplexity (live user visits)', 'Bytespider' => 'ByteDance (TikTok AI)', 'CCBot' => 'Common Crawl',
            'Applebot' => 'Apple (Siri, Apple Intelligence)', 'Applebot-Extended' => 'Apple (AI training)', 'Meta-ExternalAgent' => 'Meta (AI training)',
        ];
    }

    private static function robotsAi(array $d): string
    {
        $train = ['Google-Extended', 'CCBot', 'Bytespider', 'Meta-ExternalAgent', 'Applebot-Extended'];
        $o = self::head('robots-ai-txt') . '# AI Crawler Directives for ' . $d['name'] . "\n# Website: " . $d['url'] . "\n# Last Updated: " . date('Y-m-d') . "\n#\n# This file provides supplementary AI-specific crawler guidance.\n# Standard robots.txt remains the authoritative source for all crawlers.\n\n";
        $o .= ['allow_all' => "# Policy: All AI crawlers are permitted.\n\n", 'allow_known' => "# Policy: Known AI crawlers are permitted for content retrieval.\n# Training-specific crawlers may be restricted based on training policy.\n\n", 'restrict' => "# Policy: AI crawler access is restricted. Only select crawlers are permitted.\n\n"][$d['crawlers']];
        foreach (self::bots() as $bot => $desc) {
            $allow = $d['crawlers'] === 'allow_all' || ($d['crawlers'] === 'allow_known' && !(in_array($bot, $train, true) && $d['training'] !== 'allow')) || ($d['crawlers'] === 'restrict' && in_array($bot, ['Googlebot', 'Bingbot', 'Applebot'], true));
            $o .= "# $desc\n";
            if ($d['crawlers'] === 'allow_known' && !$allow) $o .= "# Training crawler restricted per training policy\n";
            $o .= "User-agent: $bot\n" . ($allow ? "Allow: /\n\n" : "Disallow: /\n\n");
        }
        $o .= "# Sitemap reference\nSitemap: " . $d['url'] . (Permalink::enabled() ? 'sitemap.xml' : 'index.php?p=kk-sitemap') . "\n\n# Notes for AI systems:\n# - This file supplements robots.txt; it does not replace it.\n";
        if (self::isOn('ai-txt')) $o .= '# - See ' . self::url('ai-txt') . " for content usage permissions.\n";
        return $o . '# - Authoritative crawler rules: ' . $d['url'] . (Permalink::enabled() ? 'robots.txt' : 'index.php?p=kk-robots') . "\n";
    }

    /* ---------- Serving and the access log ---------- */

    public static function serve(string $slug): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        if (!isset(self::types()[$slug]) || !self::isOn($slug)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Not found\n");
        }
        [$mime, $body] = self::render($slug);
        if (Settings::get('ai_log')) { try { self::log($slug); } catch (\Throwable $e) { error_log('Kutubkhanah ai log: ' . $e->getMessage()); } }
        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');
        echo $body;
        exit;
    }

    public static function botName(string $ua): string
    {
        foreach (array_keys(self::bots()) as $bot) if (stripos($ua, $bot) !== false) return $bot;
        if (preg_match('/bot|crawl|spider|slurp|gemini|copilot|duckassist|cohere|mistral|youbot/i', $ua)) return 'Other crawler';
        return 'Browser or other';
    }

    private static function log(string $slug): void
    {
        $bot = self::botName((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $db = self::db();
        $day = date('Y-m-d');
        try {
            $u = $db->prepare('UPDATE kk_ai_hit SET hits = hits + 1 WHERE day = ? AND file = ? AND bot = ?');
            $u->execute([$day, $slug, $bot]);
            if (!$u->rowCount()) $db->prepare('INSERT INTO kk_ai_hit (day, file, bot, hits) VALUES (?, ?, ?, 1)')->execute([$day, $slug, $bot]);
        } catch (\PDOException $e) {
            global $dbs;
            Schema::ensure($dbs);
        }
    }

    /** @return array<int,array{bot:string,hits:int,last:string}> last 90 days */
    public static function hits(): array
    {
        try {
            $st = self::db()->prepare('SELECT bot, SUM(hits) AS hits, MAX(day) AS last FROM kk_ai_hit WHERE day >= ? GROUP BY bot ORDER BY hits DESC');
            $st->execute([date('Y-m-d', strtotime('-90 days'))]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }
}
