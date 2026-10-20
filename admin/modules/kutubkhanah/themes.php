<?php
/**
 * Kutubkhanah: themes (like WordPress's Appearance > Themes).
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Theme;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

// The base system keeps the stored choice; read it fresh so this page shows the saved theme, not a preview.
$active = Theme::active();
$themes = Theme::all();
$types = ['native' => __('Kutubkhanah'), 'wordpress' => __('WordPress'), 'omeka' => __('Omeka Classic')];
$siteBase = rtrim((string)SWB, '/') . '/';

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Themes') ?></h2>
    <p><?= __('A theme controls the layout of the public site. Colours, cover size and corner radius are set under Settings > Display and apply to any Kutubkhanah theme. A child theme names a parent in its theme.json and only holds the files it changes.') ?></p>
  </header>

  <div class="kk-themes">
    <?php foreach ($themes as $slug => $m):
        $is = $slug === $active;
        $shot = $m['screenshot'] !== '' ? $siteBase . 'template/' . rawurlencode($slug) . '/' . $m['screenshot'] : '';
    ?>
      <article class="kk-theme<?= $is ? ' is-active' : '' ?>">
        <div class="kk-theme__shot"><?php if ($shot): ?><img src="<?= kk_e($shot) ?>" alt="" loading="lazy"><?php else: ?><span><?= kk_e(mb_substr($m['name'], 0, 1)) ?></span><?php endif; ?></div>
        <div class="kk-theme__body">
          <h3><?= kk_e($m['name']) ?> <?php if ($is): ?><span class="kk-badge"><?= __('Active') ?></span><?php endif; ?></h3>
          <p class="kk-note"><?= kk_e($types[$m['type']] ?? $m['type']) ?><?= $m['version'] !== '' ? ' · v' . kk_e($m['version']) : '' ?><?= $m['author'] !== '' ? ' · ' . kk_e($m['author']) : '' ?><?= $m['parent'] !== '' ? ' · ' . kk_e(sprintf(__('child of %s'), $m['parent'])) : '' ?></p>
          <?php if ($m['description'] !== ''): ?><p><?= kk_e($m['description']) ?></p><?php endif; ?>
          <div class="kk-actions">
            <?php if (!$is): ?>
              <button type="button" class="kk-btn kk-btn--primary" data-kk-action="theme_activate" data-values='<?= kk_e(json_encode(['slug' => $slug])) ?>' data-reload="<?= kk_e(MWB . 'kutubkhanah/themes.php') ?>"><?= __('Activate') ?></button>
              <a class="kk-btn kk-btn--outline notAJAX" href="<?= kk_e($siteBase . '?kk_theme=' . rawurlencode($slug)) ?>" target="_blank" rel="noopener"><?= __('Live preview') ?></a>
            <?php else: ?>
              <a class="kk-btn kk-btn--outline" href="<?= MWB ?>kutubkhanah/settings.php"><?= __('Customize') ?></a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="kk-panel">
    <h3><?= __('Add a theme') ?></h3>
    <p class="kk-note"><?= __('Upload a theme as a .zip, or copy its folder into template/ on the server. Kutubkhanah themes have a theme.json; WordPress themes have a style.css with a "Theme Name" header; Omeka Classic themes have a theme.ini. See docs/themes.md for what each can do.') ?></p>
    <form class="kk-actions notAJAX" data-kk-zip data-target="themes">
      <input type="file" name="zip" accept=".zip" required>
      <label class="kk-check"><input type="checkbox" name="trust" value="1"> <?= __('I trust this file. Themes and plugins run code on the server.') ?></label>
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Install theme') ?></button>
      <span class="kk-note kk-saved" aria-live="polite"></span>
    </form>
  </div>
</div>
<?= kk_script('admin.js') ?>
