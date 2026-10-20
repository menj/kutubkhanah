# Installing Kutubkhanah

A short guide, from an empty server to your first book. The installer works like WordPress's: you copy the files, open the
site in a browser and answer a few questions.

## 1. What you need

- A web server (Apache, Nginx, Caddy) running **PHP 8.4 or later** with these extensions: `gd`, `gettext`, `intl`,
  `mbstring`, `xml` (with `dom` and `simplexml`), `zip`, `fileinfo`, `curl` and the driver for your database
  (`mysqli`, `pdo_pgsql` or `pdo_sqlite`). `curl` makes book and cover lookups faster and is used for the WordPress import.
- **No Composer step is needed.** The PHP libraries Kutubkhanah uses (PDF, spreadsheet, barcode, mail, CSRF, UUID) are
  bundled in `lib/`. Run `composer install` only if you want the optional Elasticsearch search engine.
- **One database:**
  - **SQLite**: nothing to install; the easiest choice for one household.
  - **MySQL 5.7 / MariaDB 10.3 or later**, or **PostgreSQL 13 or later**: create an empty database and a user for it first.
- Optional, for searching inside PDFs: `pdftotext` (poppler-utils); for scanned pages also `tesseract` and `pdftoppm`.
- HTTPS is strongly recommended. Camera scanning in the browser only works over HTTPS.

## 2. Get the files

```shell
git clone https://github.com/menj/kutubkhanah.git
```

Or download the repository from GitHub and upload it to your web root (or a sub-folder).

Point the web server at the folder that contains `index.php`.

## 3. Folder permissions

The web server's user must be able to write to:

```
config/   files/   images/   repository/
```

Example (Debian/Ubuntu with Apache): `chown -R www-data:www-data config files images repository`.

## 4. Run the installer

1. Open your site in a browser. You are taken to `install/index.php`.
2. **Welcome**: the page checks PHP and extensions and tells you what is missing.
3. **Database**: choose the type.
   - *MySQL / MariaDB* and *PostgreSQL*: host, database name, username and password. The installer creates the database
     if the user is allowed to.
   - *SQLite*: nothing to type. The database file is created in `files/db/` and protected from web access.
4. If the installer cannot write `config/database.php` it shows the text so you can paste it in yourself.
5. **Your library**: title, administrator username and email. A strong password is generated; **copy it now**.
   You can also load a few sample books, and tick *Discourage search engines* if the site should stay private.
6. **Done**. Sign in at `/admin`.

## 5. After installing

- Remove or rename the `install/` folder.
- Open **Admin > Kutubkhanah > Settings** and set the library name, colours, lookup sources and public pages.
- Add your first book with **Scan ISBN** (camera or typing), or import a Libib CSV under **Import / Export**.
- Set up outgoing email under **System > Mail Setting** if you want password-reset and notification emails.
- Make backups: **System > Backup**, and copy `config/`, `files/`, `images/docs/` and `repository/` regularly.

## Web server notes

**Apache**: the bundled `.htaccess` is enough; enable `mod_rewrite`.

**Nginx**: serve `index.php` through PHP-FPM and deny access to `config/`, `files/db/` and `.htaccess`-protected folders:

```nginx
location ~ ^/(config|files/db)/ { deny all; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.4-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```

## Docker (development)

```shell
cp docker/.env.example .env      # ports and database password
docker compose -f docker/docker-compose.yml --env-file .env up -d --build
```

The site is then at `http://localhost:8881` (or the `HTTP_PORT` you set). The Docker setup uses MariaDB.

## Troubleshooting

- **Blank page or error 500**: set `$env = 'development'` in `config/env.php` to see the message; set it back afterwards.
- **"Cannot write config"**: fix the permissions in step 3, or paste the text the installer shows.
- **Installer appears again**: `config/database.php` exists but `config/env.php` does not; finish the installer.
- **Camera does not start**: use HTTPS, and allow the camera in the browser.
- **Covers do not download**: the server must be allowed to make outgoing HTTPS requests.
- Questions or bugs: contact@menj.org or https://github.com/menj/kutubkhanah/issues
- More: [upgrading.md](upgrading.md) and [ssot.md](ssot.md).
