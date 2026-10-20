# Changelog

All notable changes to Kutubkhanah. Newest first. The version number lives in `sysconfig.inc.php` (see `ssot.md`).

## 1.11.0

- The installer creates the writable folders itself and loosens their permissions where PHP is allowed to; if the web user still cannot write to one, it shows the exact command to run.
- The installer now removes its own folder once the new site answers and an administrator account exists. If the server will not allow the deletion it says so and leaves the folder for you to delete. The Success page also appears again after install (it was being replaced by "Already installed").
- More free sources: HathiTrust and Internet Archive join Open Library, the Library of Congress and Google Books as ISBN lookup sources (Settings > Lookup), adding identifiers, dates and links to free copies. Import / Export > Find free copies searches Project Gutenberg, HathiTrust (full view) and the Internet Archive for books with no digital copy link. VIAF now runs with Wikidata for authors: native-script name, birth and death years, ISNI and an authority-record link, only where empty and only when an ISNI agrees.
- Wikidata (Import / Export > Wikidata): authors are matched by ISNI, then by name or native-script name, and must be a person; books by ISBN, then OCLC number, and a title match must agree on ISBN or OCLC. Fills only empty fields: native-script name, birth and death years, nationality, short description, portrait (Wikimedia Commons) and original title. Matches appear in Review matches, where you can set an ISNI or Wikidata ID, approve or reject.
- Authors in any script: English, Bahasa Melayu, Arabic and Mandarin names are shown as stored (right-to-left aware), with an optional native-script name beside the Latin spelling. Import / Export > Review matches lets you check each Open Library match, set a search name or Open Library ID, approve or reject it, and set a sort name so "Ahmad bin Ali" can file under the part you choose. Rejected matches are never fetched again; names in Arabic or Chinese script are not reordered for the lookup.
- Every page now has a narrative order. Book page: meet the book, take it home (availability, save, ask to borrow), the details, where it fits (author, more by, related). Author page: about, facts, then their books. Series, lists and wishlist end with a "Keep exploring" row.
- The home page now reads as a story: welcome and search, the collection in numbers, what the shelves hold (genres), what just arrived, the voices behind the books (authors), the journal, notes from the library, how to visit and borrow, and why the collection exists.
- Rounder shapes: the default corner radius is now 16 px (range 0 to 32), and a new "Pill shapes" switch (Settings > Display) makes buttons, tags, search boxes and form fields fully rounded. Sites that already saved a radius keep it until changed.
- Author portraits and short biographies from Open Library, saved on this server (Import / Export > Fetch author portraits). Shown on author pages and in the home page author cards. A curator's note still wins over a fetched biography.
- Suggest a book: a public form that lands in Inbox > Suggestions, with one click to add the title to the wishlist.
- Saved books: a bookmark on every book and a Saved books page. The list stays in the visitor's own browser.
- Borrow requests: "Ask to borrow" on a book, or for everything saved. Requests arrive in Inbox > Borrow requests with the book list.
- Tag cloud: genre chips on the home page are sized by how many books they hold.
- Visiting hours and lending note panel on the home page (Settings > Library). Empty hides it.
- Advanced search panel under the home search box: subject, format and language.
- New columns on `kk_author` (`photo`, `bio`, `bio_source`, `photo_tried`) are added automatically. New pages: `/suggest`, `/borrow`, `/my-list`.

## 1.10.0

- Catalogue refresh: a "By Author" section on the home page (three covers, name, book count and a short line per author), a "search by" menu in the home search box, and quieter book cards with shadowed covers.
- Restore screen for SQLite and PostgreSQL backups (Import / Export > Restore a backup). Takes a backup .sql from System > Backup, saves a `before-restore-*.sql` copy of the current data first, replaces the tables in one transaction, and changes nothing if it fails. Needs system administrator rights.
- Admin files `blog_*.php` renamed to kebab-case (`blog-comments.php` and so on).

- Themes: a theme manager (Kutubkhanah > Themes) with live preview, child themes (`theme.json` with `parent`) and upload.
  WordPress themes (`style.css`) and Omeka Classic themes (`theme.ini`) run through compatibility layers; see `themes.md`.
- Plugins: a plugin system (Kutubkhanah > Plugins) with WordPress-style actions, filters and shortcodes, activate/deactivate/delete
  and .zip upload. WordPress plugins and Omeka Classic plugins run on compatibility layers; see `plugins.md`.
- New table `kk_option` (plugin options and transients), created automatically.
- `flash()` can now be echoed safely, for themes written for other systems.

## 1.9.0

- AI Discovery Files built in: `llms.txt`, `ai.txt`, `identity.json`, `ai.json`, `brand.txt`, `faq-ai.txt`, `llm.txt`,
  `llms.html`, `developer-ai.txt` and `robots-ai.txt`, generated on request from Settings > AI files and the catalogue
  (book, author and subject counts, main subjects, pages). Choose which files to publish, what AI may do with the content,
  the training policy and the crawler policy. A small log shows which AI crawlers fetched the files.
- All Kutubkhanah files are now kebab-case: `lib/kutubkhanah/ai-files.php`, `lib/kutubkhanah/db/`, `lib/contents/kk-rss.inc.php`.
  The autoloader finds `AiFiles` as `ai-files.php`. Old page names (`kk_rss`, `kk_api`, `blog_rss`, `request_access`)
  still work.
- New table `kk_ai_hit` (created automatically).

## 1.8.0

- Readable web addresses: `/book/dune`, `/author/frank-herbert`, `/series/the-expanse`, `/lists/favourites`, `/journal/my-post`,
  `/page/about`, `/shelf`, `/faq` and so on. Slugs are made once and never change; merged duplicates redirect to the kept book.
  Old `index.php?p=` links redirect (301) to the new addresses, so nothing breaks. Settings > Library > Web addresses
  switches back to the plain form.
- Search-engine basics, following Google's SEO Starter Guide and structured-data documentation: a canonical link on every page,
  a unique meta description per page, `<base href>`, JSON-LD (Book with author, ISBN, publisher and image; BlogPosting;
  WebSite with search box; BreadcrumbList), a generated `sitemap.xml` and a `robots.txt` that also honours
  "discourage search engines".
- Fix: the home page failed on PostgreSQL ("argument of AND must be type boolean").
- New table `kk_slug` (created automatically).

## 1.7.0

- Reading lists: ordered lists of books with notes, a public page each (index.php?p=lists) and links from book pages.
- Duplicates & Editions: finds books entered twice (same ISBN, or same title, author and year) and merges them,
  moving copies, authors, subjects, files, loans and notes into the record you keep. Editions of one work can be
  linked, and each edition's page then lists the others.
- Search Inside: indexes the text of PDF digital copies (pdftotext, with tesseract OCR for scanned pages) and adds a
  public "search inside books" page showing the page number and a highlighted snippet. Private files are never searched.
- Settings: switches for Reading lists and Search inside books, and the OCR language.
- Runs on MySQL/MariaDB, SQLite or PostgreSQL. Pick the database type in the installer; SQLite needs no server.
- A built-in translation layer (lib/kutubkhanah/db) converts the MySQL-style SQL used across the code base
  for SQLite and PostgreSQL, so existing modules keep working. Verified by installing and crawling
  1,500 pages and exercising the library features on all three.
- Backups work on all three: MySQL as before; SQLite and PostgreSQL write a portable .sql file (SQLite includes the
  table definitions; PostgreSQL holds the data, to load into a fresh install of the same version).

## 1.6.0

- Blog built into Kutubkhanah (modelled on WordPress): posts and pages, categories, tags, featured image,
  scheduled and draft posts, sticky posts, comments with moderation, media library, RSS feed,
  WordPress export import, rich-text editor, shortcodes that embed books from your library.
- Public blog at index.php?p=blog in the Kutubkhanah theme; settings in Settings > Blog.

## 1.5.1

- WordPress-style installer: welcome, database details (writes config/database.php, or shows the text to paste),
  then library title, username, generated password and email, with optional sample books and a
  "discourage search engines" switch. No JavaScript framework; the old upgrade wizard is gone
  (Kutubkhanah upgrades itself when files are replaced). Unfinished installs redirect to the installer.

## 1.5.0

- Lending log (kk_loan): lend, return, overdue list, per-book history, public "On loan" badge.
- Ratings (1-5) and reviews with a public switch; stars on book pages and cards.
- Wishlist (kk_wish): admin page, scanner Wishlist mode, ISBN lookup, local covers, acquired on scan,
  optional public page.
- Series pages with volume numbers and missing volumes (kk_series); lookups split "Series ; 3".
- Custom fields per format (kk_field, kk_field_value) with seven field types.
- "New additions" RSS 2.0 feed.
- Sign-in lockout for staff and members (kk_login_attempt), Settings > Sign-in.
- Password reset mail sent locally instead of through an outside mailer.
- Footer credit reads "Powered by Kutubkhanah".

## 1.4.0

- Lookups from Open Library (edition + work), Library of Congress (SRU), Google Books, K10plus (SRU)
  and a custom SRU catalogue, fetched in parallel and merged by trust order.
- New stored details: edition, place, collation, language (added when new), series, subjects,
  Dewey, LC class, LCCN, OCLC, Open Library ID, Google Books ID, original-script title, sources used.
- Settings > Lookup: per-source switches and custom SRU address and ISBN index.
- Book page: original-script title, LC class, Open Library and Library of Congress links.

## 1.3.0

- PHP 8.4 is the minimum version (installer check, runtime check, Composer and Docker).
- All PHP 8.4 deprecations fixed in Kutubkhanah and the bundled libraries.
- EAN-8, EAN-13, UPC-A, Code 25 and Identcode barcodes work again (removed {} string offsets).
- MARC import, export and per-book MARC download work without system PEAR (PEAR_Exception bundled, BSD licence).
- Installer messages and the upgrade option ("Kutubkhanah 1.0 to 1.2.x") updated.

## 1.2.2

- All outside version branding removed: the version reads "Kutubkhanah 1.2.2" everywhere
  (admin, installer, page footer, error pages, generator tag).
- Credits page names the open-source projects Kutubkhanah builds on.
- Stock pages (about, licence, module list, open-source model) are replaced
  with "About Kutubkhanah" and "Licence" on new installs and on upgrade.
- The outside release check and community registration links are gone from the admin home.

## 1.2.1

- Full font stack: Sabon Next LT, Kagnue, Kagnue Outline and Ferments active alongside
  EB Garamond, Amiri, Noto Naskh Arabic, KFGQPC HAFS and Special Elite.

## 1.2.0

- KK brand mark, favicon set, logo, social image and installer branding.

## 1.1.0

- Private Library "navy and gold" public design, home page, catalogue and digital archives,
  book and author pages, contact and request-access forms, FAQ, read-only JSON API.

## 1.0.0

- ISBN scanning with Open Library and Google Books lookups, review queue, Libib import and
  export, cover fetching, tabbed settings and the A-Z cover shelf.
