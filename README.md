Kutubkhanah 1.11.0
=================
A self-hosted home library CMS. Catalogue your books by scanning ISBN barcodes, publish them on a public library site, lend them
to friends and family, and keep a journal alongside. It replaces Libib, runs on your own server and needs only PHP and one of
MySQL/MariaDB, PostgreSQL or SQLite. Licensed under the GNU GPL v3.

Documents: [install.md](docs/install.md) (install guide), [upgrading.md](docs/upgrading.md) (how to upgrade),
[changelog.md](docs/changelog.md) (what changed), [ssot.md](docs/ssot.md) (single source of truth for developers),
[readme.txt](readme.txt) (plain-text summary).

### New in 1.11.0: reader features
- Saved books, borrow requests, suggest a book, author portraits and bios, visiting hours panel, advanced search panel and a tag cloud.

### New in 1.10.0: themes and plugins
- A theme manager with child themes, and a plugin system with hooks, shortcodes and .zip upload.
- Runs many WordPress and Omeka Classic themes and plugins through compatibility layers. See `docs/themes.md` and `docs/plugins.md` for what is and is not supported.

### New in 1.9.0: AI discovery files
- `llms.txt`, `ai.txt`, `identity.json` and seven more files from the AI Discovery Files Specification, generated from
  your settings and catalogue (Settings > AI files), with a log of which AI crawlers read them.
- All files and folders are now kebab-case (`lib/kutubkhanah/`, `ai-files.php`).

### New in 1.8.0: readable addresses and search-engine basics
- **SEO permalinks.** `/book/dune`, `/author/frank-herbert`, `/journal/my-post`; old `index.php?p=` links redirect (301).
- Canonical links, unique descriptions, JSON-LD (Book, BlogPosting, WebSite, breadcrumbs), `sitemap.xml` and `robots.txt`.

### New in 1.7.0: three databases, reading lists, duplicates, search inside books
- **MySQL/MariaDB, SQLite or PostgreSQL.** Choose in the installer; SQLite needs no database server. The SQL used by
  the code base is translated on the fly (`lib/kutubkhanah/db`). Backups work on all three.
- **Reading lists** (Admin > Kutubkhanah > Reading Lists): ordered lists with notes and a public page each (`index.php?p=lists`).
- **Duplicates & Editions**: find books entered twice and merge them (copies, authors, subjects, files, loans and notes
  move to the record you keep); link different editions of one work so each book page lists the others.
- **Search Inside**: indexes the text of PDF digital copies (pdftotext, plus tesseract OCR for scanned pages) and adds a
  public page (`index.php?p=inside`) that shows the page and a highlighted snippet. Private files are never searched.

### New in 1.5.0: more home-library features
- **Lending** (Admin > Kutubkhanah > Lending): "lent to Ahmad on 3 March, due back 2 April", what is
  out and what is overdue, history per book, and an "On loan" badge on the public book page.
- **Ratings and reviews**: a 1–5 star rating and your own review on Book Details; the review is public
  only when you tick it, and star ratings can be hidden in Settings > Library.
- **Wishlist**: books you want, kept out of the catalogue. Add them on the Wishlist page (an ISBN alone is
  enough) or scan with "Add to: Wishlist". Scanning a wished book into the library marks it as acquired.
  Optional public Wishlist page.
- **Series with volume numbers**: series pages (index.php?p=series) list the volumes you own and the gaps;
  set the number of volumes in Admin > Kutubkhanah > Series. Lookups split "Series ; 3" automatically.
- **Custom fields per format** (Admin > Kutubkhanah > Custom Fields): text, long text, number, date,
  yes/no, choice list or link, for chosen formats, public or private. Starter fields: Signed copy,
  Condition, Provenance, Purchase price (private) and Issue number (periodicals).
- **"New additions" RSS feed** (index.php?p=kk-rss), linked from every page and the footer.
- **Sign-in lockout** (Settings > Sign-in): after 5 wrong passwords (adjustable) sign-in is paused for
  15 minutes for that username and address, on the staff login and the member area.
- **Password reset emails** are now sent from your own server (System > Mail Setting) instead of an
  outside mail service that received your email address and reset link.

### New in 1.4.0: more book databases
- **Five free sources**, asked at the same time when you scan: Open Library, the Library of Congress,
  Google Books, K10plus (about 200 German and international libraries) and any other library catalogue
  with an SRU search (for example a national library). Turn each on or off in Settings > Lookup.
- **Fuller records:** edition, place of publication, pages, language, series, subjects, Dewey and
  Library of Congress classes, LCCN and OCLC numbers, and the original-script title (for example the
  Arabic title of a romanised record), shown on the book page with links to Open Library, the Library
  of Congress and WorldCat.
- "Retry lookup" on older books fills the new details in, without touching anything you edited.

### New in 1.3.0: PHP 8.4
- **PHP 8.4 is now the minimum.** The installer checks for it, and an older PHP gets a clear message
  instead of a broken page. Tested end to end on PHP 8.4 (fresh install, upgrade, catalogue, admin,
  ISBN scanning, Libib import and export).
- Every PHP 8.4 deprecation is fixed, in Kutubkhanah's own code and in the bundled libraries
  (explicit nullable types, CSV escape arguments, LDAP connection strings, `E_STRICT`).
- **Fixed along the way:** EAN and UPC barcode labels (the barcode library used syntax PHP removed in 8.0),
  and MARC import, export and the per-book MARC download, which now work without a system PEAR install.

### New in 1.2.2
- One name everywhere: the version reads "Kutubkhanah 1.2.2" in the admin, installer, footer and
  error pages. Outside release checks, donation and registration links are removed, and the stock
  content pages are replaced with "About Kutubkhanah" and "Licence" (also on upgrade).

### New in 1.2.1: full font stack
- Sabon Next LT (text and headings), Kagnue (display titles), Kagnue Outline (catalogue A–Z letters)
  and Ferments (tagline and quotes) are now active, alongside EB Garamond, Amiri, Noto Naskh Arabic,
  KFGQPC HAFS and Special Elite. The licensed files live in `fonts/kutubkhanah/private/` and ship in the
  deployment package, not in this public repository; without them the site falls back to EB Garamond.

### New in 1.2.0: KK brand and font stack
- **KK brand mark**: a navy arch (library window / bookplate) with gold frame, an eight-pointed
  star and the KK monogram in EB Garamond. Used as the favicon (ICO, SVG, PNG, touch icon,
  web manifest), the site logo (gold outline version in dark mode), footer mark, social sharing
  image and installer logo. Files: `images/kutubkhanah/` (SVG sources included).
- **Font stack** (`css/kutubkhanah/fonts.css`, files and licences in `fonts/kutubkhanah/`):
  EB Garamond for headings and text, Amiri and Noto Naskh Arabic for Arabic, KFGQPC HAFS
  Uthmanic Script for Quranic text (`.kk-quran`), Special Elite for call numbers and ISBNs.
  Optional licensed display fonts (Kagnue, Ferments) load from `fonts/kutubkhanah/private/`
  if you supply them; Sabon Next LT is used only if installed on the visitor's device.

### New in 1.1.0: Private Library design
- **Public site restyled** in the Private Library "navy and gold" design: white navigation
  bar with navy rule, navy banners, serif headings, gold accents. Light, dark and automatic
  schemes, all colours editable in Settings > Display.
- **Home page** with hero search, collection stats and recent additions (or the cover shelf,
  or the classic home: Settings > Shelf).
- **Catalogue** and **Digital Archives** (cover shelf with format, genre and language filters),
  **book pages** with format badges, reading progress, digital-copy links, tags and curator's notes,
  and **author pages**.
- **Contact** and **Request Access** forms with spam protection (session token, honeypot,
  minimum fill time, 3 messages per 10 minutes), stored in the admin **Inbox**.
- **FAQ** page, editable in System > Content.
- Read-only JSON API: `index.php?p=kk-api&r=books|book|stats|authors`.

### What Kutubkhanah adds
- **Scan ISBN** (Admin > Kutubkhanah > Scan ISBN): scan barcodes with a phone camera
  (needs HTTPS) or type them. Metadata and covers come from Open Library and Google Books.
  Every ISBN is saved even when no source knows the book; those are hidden from the
  public catalogue and listed under Review.
- **Review**: books that need details, a cover, an author or an ISBN, with "Retry lookup".
- **Import / Export**: import a Libib CSV export (safe to re-run, no duplicates),
  fetch missing covers in batches, and export a Libib-compatible CSV.
- **Settings**: tabbed (Library, Shelf, Display, Lookup), with light, dark and automatic
  colour schemes and configurable colours.
- **Book Details**, **Authors** and **Inbox** admin pages.
- **Shelf** (OPAC `index.php?p=shelf`, optionally the home page): an A–Z cover grid
  with search and a reading-status filter.

Books are stored in the standard catalogue tables (biblio, item, mst_author,
mst_publisher, search_biblio), so all cataloguing, circulation and reporting features work on them. Kutubkhanah keeps
its extra fields (needs-details flag, reading status, cover state) in `kk_book`.

Code: `admin/modules/kutubkhanah/`, `lib/kutubkhanah/`, `lib/contents/` (shelf, author,
contact, request-access, kk-api), `css/kutubkhanah/`, `js/kutubkhanah/`, and the `default`
OPAC template (`template/default/`).

### Creator
Kutubkhanah is created and maintained by **MENJ** · contact@menj.org · https://github.com/menj/kutubkhanah/

### Credits
Kutubkhanah brings together three open-source library systems and keeps their licences:
- **SLiMS** (Senayan Library Management System) by the SLiMS Developer Community, GNU GPL v3:
  https://slims.web.id
- **PMB** by PMB Services, CeCILL v2 (GPL-compatible): https://www.sigb.net
- **OpenBiblio** by the OpenBiblio developers, GNU GPL v2 or later: https://sourceforge.net/projects/obiblio/

The AI discovery files are a native port of the "AI Discovery Files" plugin by 365i (GPL v2 or later),
which implements the specification at https://www.ai-visibility.org.uk/specifications/.
The public design follows the "Private Library CMS" WordPress theme. The barcode scanner uses
html5-qrcode 2.3.8 (Apache-2.0, `js/kutubkhanah/vendor/`). Font licences: `fonts/kutubkhanah/README.md`.

### System requirements
- PHP 8.4 or later (tested on 8.4) with GD, gettext, intl and mbstring
- One database: MySQL 5.7 / MariaDB 10.3 or later (mysqli), PostgreSQL 13 or later (pdo_pgsql), or SQLite 3.35 or later (pdo_sqlite, no server needed)

### Docker (development)
Copy `.env.example` to `.env` (HTTP and database ports), then run:

```shell
docker compose up -d --build
```
