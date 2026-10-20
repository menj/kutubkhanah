# Themes

Themes live in `template/<slug>/`. Choose one under Kutubkhanah > Themes (activate, live preview for staff, upload a .zip).
Colours, cover size and corner radius are set under Settings > Display and apply to any Kutubkhanah theme.

## Kutubkhanah themes (native)

`theme.json`:

```json
{ "name": "Ink", "version": "1.0.0", "author": "You", "description": "…",
  "parent": "default", "styles": ["css/ink.css"], "scripts": [], "screenshot": "screenshot.png" }
```

A **child theme** names a `parent` and holds only the files it changes. Any file not found in the child is read from the
parent, then from `default`. An optional `functions.php` is loaded (parent first) and may use `Hooks`.
`styles` and `scripts` are printed on every public page. See `template/ink/` for a working child theme.

## WordPress themes

Drop in a theme with a `style.css` "Theme Name:" header (and `Template:` for a child theme). Supported: the template hierarchy
(`index`, `home`, `front-page`, `single`, `page`, `archive`, `search`, `404`), `header.php`, `footer.php`, `get_template_part`,
the Loop, common template tags, `wp_nav_menu` (built from the Kutubkhanah menu), `wp_head`, `wp_footer`, `functions.php`.
A book is presented as one "post" (title, author, cover, description). **Not supported:** blocks and theme.json block themes,
the Customizer, widgets and sidebars with stored content, comments, custom queries over real WordPress posts.

## Omeka Classic themes

A folder with `theme.ini`. Supported: `index.php`, `items/browse.php`, `items/show.php`, `common/header.php`, `common/footer.php`
and the usual helpers (`head`, `foot`, `metadata`, `loop`, `item_image`, `link_to_item`, `public_nav_main`). Books appear as items with
Dublin Core metadata. Collections, exhibits, tags and file derivatives are not available. Omeka S themes are not supported.

Themes run PHP on your server. Only install themes you trust.
