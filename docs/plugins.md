# Plugins

Plugins live in `plugins/<slug>/`. Manage them under Kutubkhanah > Plugins: activate, deactivate, delete, upload a .zip.
A plugin that throws an error is skipped and the message is shown on the Plugins screen. Plugins run PHP on your server;
install only code you trust.

## Kutubkhanah plugins (native)

`plugin.json`: `{ "name": "…", "version": "1.0.0", "author": "…", "description": "…", "main": "my-plugin.php" }`

```php
use SLiMS\Kutubkhanah\Hooks;
Hooks::addAction('kk_footer', fn() => print '<!-- hi -->');
Hooks::addFilter('the_content', fn($html) => $html);
Hooks::addShortcode('hello', fn($atts) => 'Hello');
```

Hooks fired: `plugins_loaded`, `init`, `kk_init`, `kk_head`, `kk_footer`, `kk_book_page` (id, title), `kk_book_saved` (id),
`activate_<slug>`, `deactivate_<slug>`, and the `the_content` filter on journal posts and pages (shortcodes run there).
`Plugins::addPage($slug, $title, $callback)` adds an admin page. See `plugins/hello-kk/`.

## WordPress plugins

A PHP file with a `Plugin Name:` header. A compatibility layer provides hooks (`add_action`, `add_filter`, `do_action`), shortcodes,
options and transients (stored in `kk_option`), `wp_enqueue_*`, `wp_head`/`wp_footer`, admin menus and the Settings API, nonces,
`dbDelta`, escaping and sanitising helpers and a `$wpdb` on the catalogue database. **Not available:** posts, users, roles, custom post
types, the block editor, WooCommerce and wp-admin screens. A plugin that depends on those will fail to activate or do nothing.

## Omeka Classic plugins

A folder with `plugin.ini` and `<Name>Plugin.php`. Supported hooks: `initialize`, `public_head`, `public_footer`,
`public_items_show`, `after_save_item`, `config_form`/`config`, plus `set_option`/`get_option`, `queue_css_file`/`queue_js_file`.
Item types, collections, elements and Omeka S modules are not supported.
