<?php
/**
 * Kutubkhanah blog: media library (upload, alt text, captions, delete).
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Media;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$page = max(1, (int)($_GET['page'] ?? 1));
$r = Media::list($page, 40);
$self = MWB . 'kutubkhanah/blog-media.php';

echo kk_head();
echo '<link rel="stylesheet" href="' . SWB . 'css/kutubkhanah/blog-admin.css?v=' . KK_VERSION . '">';
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Media') ?></h2>
    <p><?= __('Images for the journal. Large photos are scaled to 2400 pixels; smaller sizes are made automatically. Add alt text so everyone can follow your posts.') ?></p>
  </header>
  <form class="kk-panel kk-inline-form" id="kk-media-upload" data-reload="<?= kk_e($self) ?>">
    <label class="kk-btn kk-btn--primary"><?= __('Upload images') ?><input type="file" name="files" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden></label>
    <span class="kk-note kk-saved" aria-live="polite"></span>
  </form>
  <div class="kk-panel">
    <?php if (!$r['rows']): ?><p class="kk-note"><?= __('No images yet.') ?></p><?php else: ?>
    <div class="kk-media-admin">
      <?php foreach ($r['rows'] as $m): ?>
        <form class="kk-media-card kk-save notAJAX" data-action="media_update" data-id="<?= (int)$m['id'] ?>">
          <a class="notAJAX" href="<?= kk_e($m['url']) ?>" target="_blank" rel="noopener"><img src="<?= kk_e($m['thumb']) ?>" alt="" loading="lazy"></a>
          <span class="kk-note"><?= kk_e($m['title']) ?> · <?= (int)$m['width'] ?>×<?= (int)$m['height'] ?></span>
          <label class="kk-sr" for="kk-m<?= (int)$m['id'] ?>-alt"><?= __('Alt text') ?></label>
          <input class="kk-input" id="kk-m<?= (int)$m['id'] ?>-alt" name="alt" value="<?= kk_e($m['alt']) ?>" placeholder="<?= __('Alt text') ?>" dir="auto">
          <label class="kk-sr" for="kk-m<?= (int)$m['id'] ?>-cap"><?= __('Caption') ?></label>
          <input class="kk-input" id="kk-m<?= (int)$m['id'] ?>-cap" name="caption" value="<?= kk_e($m['caption']) ?>" placeholder="<?= __('Caption') ?>" dir="auto">
          <div class="kk-actions"><button type="submit" class="kk-btn"><?= __('Save') ?></button>
            <button type="button" class="kk-btn" data-kk-action="media_delete" data-id="<?= (int)$m['id'] ?>" data-remove data-confirm="<?= kk_e(__('Delete this image? Posts that show it will lose it.')) ?>"><?= __('Delete') ?></button>
            <span class="kk-note kk-saved" aria-live="polite"></span></div>
        </form>
      <?php endforeach; ?>
    </div>
    <?php $pages = (int)ceil($r['total'] / 40); if ($pages > 1): ?>
      <div class="kk-pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= kk_e($self . '?page=' . $i) ?>"><?= $i ?></a><?php endfor; ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
