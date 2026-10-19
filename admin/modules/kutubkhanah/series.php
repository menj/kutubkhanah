<?php
/**
 * Kutubkhanah: series. Set how many volumes a series has so the public series page can show the gaps.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Series;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$rows = Series::all();

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Series') ?></h2>
    <p><?= __('Series come from each book’s Series Title (Bibliography) and its volume number (Book Details). Enter the total number of volumes to see which ones are missing.') ?></p>
  </header>
  <?php if (!$rows): ?>
    <div class="kk-panel"><p class="kk-note"><?= __('No books have a Series Title yet.') ?></p></div>
  <?php else: foreach ($rows as $i => $r): $data = Series::books($r['name'], false); ?>
    <form class="kk-panel kk-save notAJAX" data-action="series_save" data-id="0">
      <input type="hidden" name="name" value="<?= kk_e($r['name']) ?>">
      <div class="kk-book-head">
        <span class="kk-list__body"><strong dir="auto"><?= kk_e($r['name']) ?></strong>
          <span class="kk-note"><?= kk_e(sprintf(__('%d in the collection'), (int)$r['owned'])) ?><?= $data['missing'] ? ' · ' . kk_e(sprintf(__('missing: %s'), Series::ranges($data['missing']))) : '' ?></span></span>
        <a class="kk-btn notAJAX" href="<?= kk_e(Series::url($r['name'])) ?>" target="_blank" rel="noopener"><?= __('View public page') ?></a>
      </div>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-s<?= $i ?>-total"><?= __('Volumes in the series') ?></label></div><div class="kk-row__field">
        <input class="kk-input kk-input--short" type="number" min="0" max="500" id="kk-s<?= $i ?>-total" name="total" value="<?= (int)$r['total'] ?>">
        <p class="kk-note"><?= __('0 if unknown or open-ended.') ?></p></div></div>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-s<?= $i ?>-desc"><?= __('Description') ?></label></div><div class="kk-row__field">
        <textarea class="kk-input kk-textarea" id="kk-s<?= $i ?>-desc" name="description" rows="2" dir="auto"><?= kk_e($r['description']) ?></textarea></div></div>
      <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
    </form>
  <?php endforeach; endif; ?>
</div>
<?= kk_script('admin.js') ?>
