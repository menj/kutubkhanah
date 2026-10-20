<?php
/**
 * Kutubkhanah: plugins (like WordPress's Plugins screen).
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Plugins;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

Plugins::boot();
$all = Plugins::all();
$active = Plugins::active();
$errors = Plugins::errors();
$types = ['native' => __('Kutubkhanah'), 'wordpress' => __('WordPress'), 'omeka' => __('Omeka Classic')];

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Plugins') ?></h2>
    <p><?= __('Plugins add features. Kutubkhanah plugins use its hooks; WordPress and Omeka Classic plugins run on a compatibility layer and work when they stick to hooks, shortcodes, options and admin pages. Plugins that need WordPress posts, users or WooCommerce, or Omeka item types and records, will not work.') ?></p>
  </header>

  <?php if (!$all): ?>
    <div class="kk-panel"><p class="kk-note"><?= __('No plugins installed yet. Upload one below or copy its folder into plugins/ on the server.') ?></p></div>
  <?php endif; ?>

  <ul class="kk-list kk-plugins">
    <?php foreach ($all as $slug => $p): $on = in_array($slug, $active, true); ?>
      <li class="kk-list__row<?= $on ? ' is-active' : '' ?>">
        <span class="kk-list__body">
          <strong><?= kk_e($p['name']) ?></strong>
          <span class="kk-note"><?= kk_e($types[$p['type']] ?? $p['type']) ?><?= $p['version'] !== '' ? ' · v' . kk_e($p['version']) : '' ?><?= $p['author'] !== '' ? ' · ' . kk_e($p['author']) : '' ?></span>
          <?php if ($p['description'] !== ''): ?><br><span class="kk-note"><?= kk_e($p['description']) ?></span><?php endif; ?>
          <?php if (!empty($errors[$slug])): ?><br><span class="kk-note kk-note--error"><?= kk_e(sprintf(__('Could not load: %s'), $errors[$slug])) ?></span><?php endif; ?>
        </span>
        <span class="kk-list__actions">
          <?php if ($on): ?>
            <?php if ($p['type'] === 'omeka'): ?><a class="kk-btn kk-btn--outline kk-btn--sm" href="<?= MWB ?>kutubkhanah/plugin-page.php?plugin=<?= rawurlencode($slug) ?>"><?= __('Configure') ?></a><?php endif; ?>
            <button type="button" class="kk-btn kk-btn--outline kk-btn--sm" data-kk-action="plugin_deactivate" data-values='<?= kk_e(json_encode(['slug' => $slug])) ?>' data-reload="<?= kk_e(MWB . 'kutubkhanah/plugins.php') ?>"><?= __('Deactivate') ?></button>
          <?php else: ?>
            <button type="button" class="kk-btn kk-btn--primary kk-btn--sm" data-kk-action="plugin_activate" data-values='<?= kk_e(json_encode(['slug' => $slug])) ?>' data-reload="<?= kk_e(MWB . 'kutubkhanah/plugins.php') ?>"><?= __('Activate') ?></button>
            <button type="button" class="kk-btn kk-btn--outline kk-btn--sm" data-kk-action="plugin_delete" data-confirm="<?= kk_e(__('Delete this plugin and its files?')) ?>" data-values='<?= kk_e(json_encode(['slug' => $slug])) ?>' data-reload="<?= kk_e(MWB . 'kutubkhanah/plugins.php') ?>"><?= __('Delete') ?></button>
          <?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="kk-panel">
    <h3><?= __('Add a plugin') ?></h3>
    <p class="kk-note"><?= __('Upload a plugin as a .zip, or copy its folder into plugins/ on the server. See docs/plugins.md for how to write one.') ?></p>
    <form class="kk-actions notAJAX" data-kk-zip data-target="plugins">
      <input type="file" name="zip" accept=".zip" required>
      <label class="kk-check"><input type="checkbox" name="trust" value="1"> <?= __('I trust this file. Themes and plugins run code on the server.') ?></label>
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Install plugin') ?></button>
      <span class="kk-note kk-saved" aria-live="polite"></span>
    </form>
  </div>
</div>
<?= kk_script('admin.js') ?>
