Kutubkhanah 1.2.0
=================
A home library system built on SLiMS 9.8.0 (Bulian), licensed under the GNU GPL v3.

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
  or the classic SLiMS home: Settings > Shelf).
- **Catalogue** and **Digital Archives** (cover shelf with format, genre and language filters),
  **book pages** with format badges, reading progress, digital-copy links, tags and curator's notes,
  and **author pages**.
- **Contact** and **Request Access** forms with spam protection (session token, honeypot,
  minimum fill time, 3 messages per 10 minutes), stored in the admin **Inbox**.
- **FAQ** page, editable in System > Content.
- Read-only JSON API: `index.php?p=kk_api&r=books|book|stats|authors`.

### What Kutubkhanah adds to SLiMS
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

Books are stored in the standard SLiMS tables (biblio, item, mst_author,
mst_publisher, search_biblio), so all SLiMS features work on them. Kutubkhanah keeps
its extra fields (needs-details flag, reading status, cover state) in `kk_book`.

Code: `admin/modules/kutubkhanah/`, `lib/Kutubkhanah/`, `lib/contents/` (shelf, author,
contact, request_access, kk_api), `css/kutubkhanah/`, `js/kutubkhanah/`, and the `default`
OPAC template (`template/default/`).

### Credits
Kutubkhanah is a modified version of SLiMS by the SLiMS Developer Community
(https://slims.web.id). The public design follows the "Private Library CMS"
WordPress theme. The barcode scanner uses html5-qrcode 2.3.8 (Apache-2.0,
`js/kutubkhanah/vendor/`). The original SLiMS README follows.

----

SLiMS 9 Bulian
===============
SENAYAN Library Management System (SLiMS) version 9 Codename Bulian

SLiMS is free open source software for library resources management
(such as books, journals, digital document and other library materials)
and administration such as collection circulation, collection management,
membership, stock taking and many other else.

SLiMS is licensed under GNU GPL version 3. Please read "GPL-3.0 License.txt"
to learn more about GPL.

### System Requirements
- PHP version >= 8.1;
- MySQL version 5.7 and or MariaDB version 10.3;
- PHP GD enabled
- PHP gettext enabled
- PHP mbstring enabled

### Running Docker Development

Using docker for running requires Docker Desktop service, as it will use Docker to create the database.
Before run docker compose copy .env.example to .env for setting http port and maria db (db) port 
can be change on .env

You can run the test using this command :

```shell
docker compose up -d --build
```

Setting docker default can be change on docker-compose.yml