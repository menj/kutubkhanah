<?php
/**
 * Kutubkhanah: duplicate finder and merge, and editions grouped as one work.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Duplicates;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$dups = Duplicates::groups();
$eds = Duplicates::editionGroups();
$works = Duplicates::works();
$reload = MWB . 'kutubkhanah/cleanup.php';
$label = function (array $b): string {
    return trim(($b['title'] ?? '') . ($b['author'] ? ' — ' . $b['author'] : '') . ($b['year'] ? ' (' . $b['year'] . ')' : ''));
};

echo kk_head();
?>
<div class="kk" data-reload="<?= kk_e($reload) ?>">
  <header class="kk-head">
    <h2><?= __('Duplicates & Editions') ?></h2>
    <p><?= __('A duplicate is the same book entered twice: merge them and keep one record with all copies, authors, subjects, files and notes. Editions are different books of the same work: link them so each shows the others.') ?></p>
  </header>

  <div class="kk-tabs" role="tablist" aria-label="<?= __('Sections') ?>">
    <button type="button" role="tab" class="kk-tab is-active" id="kk-tab-dups" aria-controls="kk-pane-dups" aria-selected="true"><?= __('Duplicates') ?> <span class="kk-count"><?= count($dups) ?></span></button>
    <button type="button" role="tab" class="kk-tab" id="kk-tab-eds" aria-controls="kk-pane-eds" aria-selected="false"><?= __('Possible editions') ?> <span class="kk-count"><?= count($eds) ?></span></button>
    <button type="button" role="tab" class="kk-tab" id="kk-tab-works" aria-controls="kk-pane-works" aria-selected="false"><?= __('Linked works') ?> <span class="kk-count"><?= count($works) ?></span></button>
  </div>

  <div id="kk-pane-dups" role="tabpanel" aria-labelledby="kk-tab-dups">
    <?php if (!$dups): ?><div class="kk-panel"><p class="kk-note"><?= __('No duplicates found. Books match when they share an ISBN, or the same title, author and year.') ?></p></div>
    <?php else: foreach ($dups as $gi => $g): ?>
      <div class="kk-panel kk-merge" data-merge>
        <p class="kk-note"><?= __('Choose the record to keep. The others are merged into it and removed.') ?></p>
        <ul class="kk-list">
          <?php foreach ($g as $k => $b): ?>
            <li>
              <label class="kk-check"><input type="radio" name="keep<?= $gi ?>" value="<?= (int)$b['id'] ?>"<?= $k === 0 ? ' checked' : '' ?>>
                <span class="kk-list__body"><strong dir="auto"><?= kk_e($label($b)) ?></strong>
                  <span class="kk-note">#<?= (int)$b['id'] ?> · <?= $b['isbn'] !== '' && $b['isbn'] !== null ? 'ISBN ' . kk_e($b['isbn']) . ' · ' : '' ?><?= kk_e(sprintf(__('%d copies'), (int)$b['copies'])) ?><?= $b['image'] ? ' · ' . kk_e(__('has cover')) : '' ?>
                  · <a class="notAJAX" href="<?= kk_e(MWB . 'bibliography/index.php?detail=true&itemID=' . (int)$b['id']) ?>" target="_blank" rel="noopener"><?= __('Open') ?></a></span></span></label>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="kk-actions"><button type="button" class="kk-btn kk-btn--primary" data-merge-go><?= __('Merge into the selected record') ?></button>
          <button type="button" class="kk-btn" data-merge-link><?= __('They are different editions: link instead') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
        <input type="hidden" data-ids value="<?= kk_e(implode(',', array_map(fn($b) => (int)$b['id'], $g))) ?>">
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div id="kk-pane-eds" role="tabpanel" aria-labelledby="kk-tab-eds" hidden>
    <?php if (!$eds): ?><div class="kk-panel"><p class="kk-note"><?= __('No unlinked editions found. Books match when they share a title and first author.') ?></p></div>
    <?php else: foreach ($eds as $g): ?>
      <div class="kk-panel" data-merge>
        <ul class="kk-list"><?php foreach ($g as $b): ?>
          <li><span class="kk-list__body"><strong dir="auto"><?= kk_e($label($b)) ?></strong><span class="kk-note">#<?= (int)$b['id'] ?><?= $b['edition'] ? ' · ' . kk_e($b['edition']) : '' ?></span></span></li>
        <?php endforeach; ?></ul>
        <div class="kk-actions"><button type="button" class="kk-btn kk-btn--primary" data-merge-link><?= __('Link as editions of one work') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
        <input type="hidden" data-ids value="<?= kk_e(implode(',', array_map(fn($b) => (int)$b['id'], $g))) ?>">
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div id="kk-pane-works" role="tabpanel" aria-labelledby="kk-tab-works" hidden>
    <?php if (!$works): ?><div class="kk-panel"><p class="kk-note"><?= __('No linked works yet.') ?></p></div>
    <?php else: foreach ($works as $wid => $eds2): ?>
      <div class="kk-panel">
        <ul class="kk-list"><?php foreach ($eds2 as $b): ?>
          <li><span class="kk-list__body"><strong dir="auto"><?= kk_e($b['title']) ?></strong><span class="kk-note"><?= kk_e(trim($b['publish_year'] . ' ' . $b['edition'])) ?></span></span>
            <button type="button" class="kk-btn kk-btn--sm" data-kk-action="work_unlink" data-id="<?= (int)$b['biblio_id'] ?>" data-reload="<?= kk_e($reload) ?>"><?= __('Unlink') ?></button></li>
        <?php endforeach; ?></ul>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
