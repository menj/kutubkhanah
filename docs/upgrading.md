# Upgrading Kutubkhanah

Kutubkhanah upgrades itself. There is no upgrade wizard: replace the files, then open the admin.

## 1.10.0

New table `kk_option` is created on first admin visit. New folder `plugins/` is where plugins go; stock `*.plugin.php` files there are untouched.
Existing themes keep working; `template/default/theme.json` is new.

## Before you start

1. **Back up the database.** Admin > System > Backup, or your own tool.
   - MySQL/MariaDB: a dump as usual.
   - SQLite: copy `files/db/kutubkhanah-*.sqlite` (and its `-wal` and `-shm` files if present), or use Backup.
   - PostgreSQL: `pg_dump`, or Backup (the Backup file holds data only).
2. **Back up these folders:** `config/`, `files/`, `images/docs/`, `repository/`.
3. Check the PHP version: **8.4 or later** is required.

## Steps

1. Put the site in maintenance if you can (or just do it at a quiet time).
2. Replace the program files with the new release. **Keep** these:
   - `config/database.php`, `config/env.php` and any other file in `config/` that is not a `*.sample.php`
   - `files/` (database for SQLite, backups, reports)
   - `images/docs/` (covers), `repository/` (digital copies)
3. Sign in to the admin and open any Kutubkhanah page. Missing tables and columns are created automatically, once per
   session, by `SLiMS\Kutubkhanah\Schema::ensure()`. Nothing is dropped or rewritten.
4. Clear your browser cache if styles look old (assets are versioned, but a proxy may hold old ones).
5. Check Admin > Kutubkhanah > Settings once; new options default to on.

## Getting the files

```shell
git clone https://github.com/menj/kutubkhanah.git   # first time
git pull origin main                                  # later
```

Pulling keeps `config/` and `files/` because they are ignored by git.

## Notes per version

### To 1.9.0
- New table `kk_ai_hit` (created automatically). New tab Settings > AI files; files are on by default, review the wording first.
- Apache: copy the new rewrite line from `.htaccess` (it also routes `llms.txt`, `ai.txt` and the other files).
  Nginx: the `try_files` rule from 1.8.0 already covers them.
- Files moved to kebab-case (`lib/Kutubkhanah/` is now `lib/kutubkhanah/`). Replace the whole folder when upgrading
  rather than merging; custom code that includes these files by path must use the new names.

### To 1.8.0
- New table `kk_slug` (created automatically). New setting `permalinks` (Settings > Library > Web addresses).
- **Apache**: the bundled `.htaccess` gained a rewrite rule. If you edited `.htaccess` yourself, copy the new block.
  **Nginx**: add `location / { try_files $uri $uri/ /index.php?$query_string; }` (see `install.md`).
  Without URL rewriting, set Web addresses to Plain.
- Old links keep working (301 redirect). Submit `/sitemap.xml` to Google Search Console.

### To 1.7.0
- New tables: `kk_list`, `kk_list_item`, `kk_work_link`, `kk_doc_index`, `kk_doc_text` (created automatically).
- New settings: `enable_lists`, `enable_inside`, `ocr_lang` (Settings > Library).
- **Database choice.** Existing MySQL/MariaDB sites keep working with no change: a missing `driver` key in
  `config/database.php` means MySQL. To start a new site on SQLite or PostgreSQL, use the installer.
  There is no tool to convert an existing site between engines. To move, export the books (Import / Export > CSV)
  and import them into a new install.
- **Search Inside** needs `pdftotext` (poppler-utils) on the server, and `tesseract` plus `pdftoppm` for scanned pages.
  Run Admin > Kutubkhanah > Search Inside > "Index new and changed files" after upgrading.
- If you added your own SQL to a custom module: write portable SQL (no multi-table `UPDATE`/`DELETE`, no
  MySQL-only functions) if you want it to run on SQLite and PostgreSQL. MySQL sites are not affected.

### To 1.6.0
- Blog tables (`kk_post`, `kk_term`, `kk_post_term`, `kk_comment`, `kk_media`, `kk_post_revision`) are created automatically.

### To 1.5.1
- The installer was rewritten. `upgrade/` and the old upgrade wizard are no longer used.
- If `config/database.php` exists but `config/env.php` does not, visitors are sent to `install/index.php?step=ready`.

### To 1.5.0
- New tables: `kk_loan`, `kk_wish`, `kk_series`, `kk_field`, `kk_field_value`, `kk_login_attempt` (automatic).
- Password reset email now uses System > Mail Setting instead of an outside mailer.

### To 1.3.0
- **PHP 8.4 is the minimum.** Upgrade PHP first.

### To 1.2.x and earlier
- Stock pages (About, Licence) are replaced on upgrade.

## If something goes wrong

- Restore the database backup and the files, then try again.
- Set `$env = 'development'` in `config/env.php` to see full error messages; set it back to `production` afterwards.
- Email contact@menj.org or open an issue at https://github.com/menj/kutubkhanah/issues with the message and your PHP and database versions.
