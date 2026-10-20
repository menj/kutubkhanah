# Kutubkhanah: single source of truth

This file is the reference for how the project is put together. When code and this file disagree, fix whichever is wrong
in the same commit. Keep it short and factual.

## Identity

- Name: **Kutubkhanah** ("KK"). Only this name is public-facing. No third-party version strings in the UI; the projects Kutubkhanah builds on are named only in the credits.
- Purpose: self-hosted home library CMS (catalogue by ISBN scan, public library site, lending, journal) that replaces Libib.
- Creator and maintainer: **MENJ**, contact@menj.org. Repository and site: https://github.com/menj/kutubkhanah/ (branch `main`, always in sync).
- Licence: GNU GPL v3 or later.
- Brand: navy and gold. Fonts in `fonts/kutubkhanah/`.

## Versions and where they live

Change all of these together on a release:

| Place | What |
|---|---|
| `sysconfig.inc.php` | `KUTUBKHANAH_VERSION`, `SENAYAN_VERSION` |
| `lib/kutubkhanah/schema.php` | `Schema::VERSION` (schema check key) |
| `admin/modules/kutubkhanah/kk.inc.php` | `KK_VERSION` |
| `README.md`, `readme.txt` | headline version |
| `docs/changelog.md`, `docs/upgrading.md` | new entry |

Release flow: edit, test, update the files above, commit, push to `origin main`. Never ship zip files.
Commit identity: `menj <remotiveasia@gmail.com>`.

## Requirements

- PHP **8.4+** (gd, gettext, intl, mbstring; mysqli, pdo_pgsql or pdo_sqlite for the chosen database).
- MySQL 5.7 / MariaDB 10.3+, PostgreSQL 13+, or SQLite 3.35+.
- Optional: `pdftotext` (poppler-utils), `pdftoppm`, `tesseract` for Search Inside.

## Databases

- Config: `config/database.php` (`driver` = `mysql` | `pgsql` | `sqlite`; missing means mysql).
- `lib/Connection.php::connect()` builds a plain mysqli/PDO for MySQL, otherwise `Db\Pdo`
  (and `Db\Mysqli` wrapper for the `mysqli` handle `$dbs`).
- Portable layer, `lib/kutubkhanah/db/` (namespace `SLiMS\Kutubkhanah\Db`):
  - `Dialect` translates MySQL-style SQL to SQLite/PostgreSQL (tokenised, string literals untouched).
  - `Ddl` translates CREATE/ALTER TABLE.
  - `Pdo`, `Statement` extend PDO; `Mysqli`, `Result`, `Stmt` imitate the mysqli API.
  - `Dump` is the SQLite/PostgreSQL backup writer (`DB::backup()` picks it).
- **Rules for new code:** write portable SQL. No multi-table `UPDATE`/`DELETE`, no `SUM(boolean)`, every selected column
  in `GROUP BY` or an aggregate, no HAVING on aliases, compare like types, `COUNT`/`CASE` instead of MySQL tricks.
  Read `lastInsertId()` from the same `$db` handle that ran the INSERT (each `DB::getInstance()` call may run a query).
- Test every database-touching change on all three engines before release.

## Layout

Root holds only the entry points (`index.php`, `oai*.php`, `sysconfig.inc.php`), Composer files, `LICENSE`, `README.md` and `readme.txt`.
Docker files are in `docker/`, long-form docs in `docs/`.

| Path | Purpose |
|---|---|
| `docs/` | install.md, upgrading.md, changelog.md, ssot.md |
| `docker/` | Dockerfile, docker-compose.yml, entrypoint.sh, .env.example |
| `lib/kutubkhanah/` | Own classes (Books, Library, Settings, Schema, Loans, Wishlist, Series, Fields, Lists, Duplicates, DocSearch, Covers, Lookup, Importer, Api, ...) |
| `lib/kutubkhanah/blog/` | Journal (blog): Posts, Terms, Comments, Media, Render, Import, Text, Wp |
| `lib/kutubkhanah/db/` | Portable database layer |
| `lib/contents/*.inc.php` | Public pages, routed by `index.php?p=<name>` |
| `admin/modules/kutubkhanah/` | Admin module: pages, `api.php` (JSON actions), `submenu.php`, `kk.inc.php` bootstrap |
| `template/default/` | Public theme (header, footer, detail page) |
| `css/kutubkhanah/`, `js/kutubkhanah/` | Own CSS and JS, kept out of PHP files (theme, admin, shelf, blog-editor, scanner; `vendor/` for third-party) |
| `install/` | WordPress-style installer (`index.php`, `Installer.php`, `css/`, `js/`) |
| `lib/Opac.php` | Public routing; `p` names listed there are indexable by search engines |

Assets rule: JavaScript and CSS live in their own files under `/js/kutubkhanah/` and `/css/kutubkhanah/`.

## File naming
Every Kutubkhanah file and folder is kebab-case: `ai-files.php`, `doc-search.php`, `lib/kutubkhanah/db/`, `kk-rss.inc.php`.
Class `AiFiles` lives in `ai-files.php`; `lib/autoload.php` converts the class name to kebab-case when the exact name is missing.
Page names (`?p=`) are kebab-case too. Database tables and settings keys keep underscores (`kk_ai_hit`, `ai_f_llms_txt`).

## Public routes (`index.php?p=`)

With Settings > Library > Web addresses = Readable (default), `Permalink::boot()` (called from `index.php`) maps paths to `?p=`:
`/book/{slug}`, `/author/{slug}`, `/series[/{slug}]`, `/lists[/{slug}]`, `/shelf`, `/wishlist`, `/search-inside`, `/contact`,
`/request-access`, `/faq`, `/journal[/{post}|/category/x|/tag/x|/archive/YYYY-MM]`, `/page/{slug}`, `/feed.xml`,
`/journal/feed.xml`, `/sitemap.xml`, `/robots.txt`. Old `?p=` GET links 301 to these; an output filter rewrites every
`index.php?p=` link the templates print. Slugs for books, authors and series live in `kk_slug` and never change.
Rule: always apply Google's SEO Starter Guide and structured-data rules to public pages (see `Seo.php` and `header.php`):
unique title and description, canonical, breadcrumbs, JSON-LD, sitemap, robots, descriptive URLs, 301 for moved pages.
The raw forms:

`shelf`, `show_detail`, `author`, `series`, `lists`, `wishlist`, `inside`, `blog`, `page`, `blog-rss`, `kk-rss`, `kk-api`,
`contact`, `request-access`, `faq`, plus the stock catalogue pages.

## Tables (own, all prefixed `kk_`; the catalogue tables are unprefixed)

| Table | Holds |
|---|---|
| `kk_book` | Per-book extras: reading status, rating, review, format, volume, cover state, lookup ids |
| `kk_author` | Author details for public author pages |
| `kk_message` | Inbox (contact and access requests) |
| `kk_login_attempt` | Sign-in lockout |
| `kk_loan` | Lending log |
| `kk_wish` | Wishlist |
| `kk_series` | Series totals and descriptions |
| `kk_field`, `kk_field_value` | Custom fields per format |
| `kk_post`, `kk_term`, `kk_post_term`, `kk_comment`, `kk_media`, `kk_post_revision` | Blog |
| `kk_list`, `kk_list_item` | Reading lists |
| `kk_work_link` | Editions grouped as one work (same `work_id`) |
| `kk_doc_index`, `kk_doc_text` | Search Inside (index state, page text) |

Tables are created and upgraded by `Schema::ensure()`, written in MySQL syntax and translated per engine.
Settings are stored as one serialized array under the name `kutubkhanah` (`Settings::defaults()` is the full list;
`Settings::fields()` maps each admin tab to its fields).

## Admin module

Menu in `submenu.php`: Scan ISBN, Review, Import/Export, Wishlist, Lending, Book Details, Series, Reading Lists,
Duplicates & Editions, Search Inside, Custom Fields, Authors, Inbox; Journal (Posts, Write, Pages, Categories & Tags,
Comments, Media, WordPress import); Settings.

`api.php` actions need a write privilege and the per-session token. Add a new action as a `case` there, and put the logic
in a class under `lib/kutubkhanah/`, not in `api.php`.

## UI rules

- Modernist, minimalist, clean. Colours come from configurable schemes (light, dark, auto) set in Settings > Display.
- Any screen with many options uses tabs (`.kk-tabs`, handled in `admin.js`), not one long form.
- Public pages use `dir="auto"` for text that may be Arabic or other RTL.
- Accessible: labels on inputs, `aria-` on tabs, keyboard arrows between tabs.

## Installer

Steps: welcome, config (database type and details, or an SQLite file), ready, site (title, username, generated password,
email, sample data, discourage search engines), done. SQLite files go in `files/db/` with a deny `.htaccess`.
Unfinished installs (database.php without env.php) redirect to `install/index.php?step=ready`.

## Testing

- Install each engine through the web installer with sample data, crawl up to 1500 pages (no 500s allowed), and run the
  feature checks (API, pages, reading lists, merge, editions, Search Inside, backup).
- Expected non-error: `index.php?p=author` without a name returns 404.

## Known limits

- Backups on PostgreSQL hold data only; there is no restore screen for SQLite/PostgreSQL backups yet.
- No tool converts an existing site between database engines.
- Some inherited modules were written for MySQL and are only verified by the crawl.

## Open items

- Public catalogue redesign (waiting for screenshots).
- Restore screen for SQLite/PostgreSQL backups.

## AI discovery files
`lib/kutubkhanah/ai-files.php`, page `kk-ai`, setting keys `ai_*` (Settings > AI files), table `kk_ai_hit`.
Files follow the AI Discovery Files Specification v1.14.0 (ai-visibility.org.uk). Native port of the "AI Discovery Files"
plugin by 365i (GPL v2 or later); credit in README.
