=== Kutubkhanah ===
Version: 1.11.0
Requires PHP: 8.4 or later
Databases: MySQL 5.7 / MariaDB 10.3 or later, PostgreSQL 13 or later, or SQLite 3.35 or later
License: GNU GPL v3 or later
Repository: https://github.com/menj/kutubkhanah

Self-hosted home library CMS. Catalogue your books by scanning ISBN barcodes, publish them on a public library site, lend them to
friends and family, and keep a journal alongside. It replaces Libib and runs on your own server.

== Description ==

* Add books by scanning ISBN barcodes with a phone camera, or by typing them. Details and covers are looked up from
  Open Library, the Library of Congress, Google Books, K10plus and one custom catalogue.
* A public site in navy and gold: cover shelf, book and author pages, series pages, reading lists, wishlist,
  search inside digital books, a journal (blog), contact and request-access forms, a JSON API and an RSS feed.
* Lending log for books lent to friends and family, ratings and reviews, custom fields per format,
  duplicate finder and merge, editions grouped as one work.
* Full cataloguing, circulation, membership and reporting underneath.
* Settings are organised in tabs, with configurable light and dark colour schemes.

== Installation ==

1. Upload the files to your web server (an empty folder or a sub-folder).
2. Make these writable by the web server: config/, files/, images/ and repository/.
3. Open the site in a browser. You are taken to install/index.php.
4. Choose the database (MySQL/MariaDB, PostgreSQL or SQLite), enter its details, then set the library title,
   administrator username and email. A strong password is generated for you; copy it.
5. Sign in at /admin. The installer deletes its own install/ folder once the new site answers; if it cannot, remove the folder by hand.

== Upgrading ==

Back up, replace the files (keep config/ and files/), and open any admin page. See docs/upgrading.md.

== Documents ==

README.md          Overview and feature list
docs/install.md    Step-by-step install guide
docs/upgrading.md  How to upgrade, and notes per version
docs/changelog.md  What changed in each version
docs/ssot.md       Single source of truth for developers: layout, tables, routes, conventions

== Creator ==

MENJ <contact@menj.org>
https://github.com/menj/kutubkhanah/

== Credits ==

AI discovery files: native port of the AI Discovery Files plugin by 365i (GPL v2 or later).
SLiMS (GPL v3), PMB (CeCILL v2), OpenBiblio (GPL v2 or later). Fonts and bundled libraries keep their own licences;
see fonts/kutubkhanah/README.md and js/kutubkhanah/vendor/.
