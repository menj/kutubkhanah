<?php
/**
 * Kutubkhanah: reading lists. Curated, ordered lists with a public page each.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Lists;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$lists = Lists::all();
$active = (int)($_GET['tab'] ?? 0);
if (!in_array($active, array_map(fn($l) => (int)$l['id'], $lists), true)) $active = $lists ? (int)$lists[0]['id'] : 0;
$reload = MWB . 'kutubkhanah/lists.php';

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Reading Lists') ?></h2>
    <p><?= __('Ordered lists of books, such as “Start here” or “Summer reading”. Each public list gets its own page.') ?>
      <?php if (Settings::get('enable_lists')): ?><a class="notAJAX" href="<?= SWB ?>index.php?p=lists" target="_blank" rel="noopener"><?= __('View the public lists') ?></a><?php endif; ?></p>
  </header>

  <form class="kk-panel kk-save notAJAX" data-action="list_save" data-id="0" data-reload="<?= kk_e($reload) ?>">
    <h3><?= __('New list') ?></h3>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-l-title"><?= __('Title') ?></label></div><div class="kk-row__field">
      <input class="kk-input" id="kk-l-title" name="title" dir="auto" required></div></div>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-l-desc"><?= __('Description') ?></label></div><div class="kk-row__field">
      <textarea class="kk-input kk-textarea" id="kk-l-desc" name="description" rows="2" dir="auto"></textarea>
      <label class="kk-check"><input type="checkbox" name="is_public" value="1" checked> <?= __('Public') ?></label></div></div>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Create list') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <?php if (!$lists): ?>
    <div class="kk-panel"><p class="kk-note"><?= __('No lists yet.') ?></p></div>
  <?php else: ?>
  <div class="kk-tabs" role="tablist" aria-label="<?= __('Reading lists') ?>">
    <?php foreach ($lists as $i => $l): $on = (int)$l['id'] === $active; ?>
      <button type="button" role="tab" class="kk-tab<?= $on ? ' is-active' : '' ?>" id="kk-tab-l<?= (int)$l['id'] ?>" aria-controls="kk-pane-l<?= (int)$l['id'] ?>"
              aria-selected="<?= $on ? 'true' : 'false' ?>" dir="auto"><?= kk_e($l['title']) ?> <span class="kk-count"><?= (int)$l['n'] ?></span></button>
    <?php endforeach; ?>
  </div>
  <?php foreach ($lists as $i => $l): $items = Lists::items((int)$l['id']); $lid = (int)$l['id']; ?>
    <div class="kk-panel" id="kk-pane-l<?= $lid ?>" role="tabpanel" aria-labelledby="kk-tab-l<?= $lid ?>"<?= $lid === $active ? '' : ' hidden' ?>>
      <form class="kk-save notAJAX" data-action="list_save" data-id="<?= $lid ?>">
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l<?= $lid ?>-title"><?= __('Title') ?></label></div><div class="kk-row__field">
          <input class="kk-input" id="kk-l<?= $lid ?>-title" name="title" value="<?= kk_e($l['title']) ?>" dir="auto" required></div></div>
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l<?= $lid ?>-desc"><?= __('Description') ?></label></div><div class="kk-row__field">
          <textarea class="kk-input kk-textarea" id="kk-l<?= $lid ?>-desc" name="description" rows="2" dir="auto"><?= kk_e($l['description']) ?></textarea>
          <label class="kk-check"><input type="checkbox" name="is_public" value="1"<?= $l['is_public'] ? ' checked' : '' ?>> <?= __('Public') ?></label>
          <?php if ($l['is_public']): ?><a class="kk-note notAJAX" href="<?= SWB . str_replace('&amp;', '&', Lists::url($l['slug'])) ?>" target="_blank" rel="noopener"><?= __('Public page') ?></a><?php endif; ?></div></div>
        <div class="kk-actions">
          <button type="submit" class="kk-btn kk-btn--primary"><?= __('Save') ?></button><span class="kk-note kk-saved" aria-live="polite"></span>
          <button type="button" class="kk-btn kk-btn--danger" data-kk-action="list_delete" data-id="<?= $lid ?>" data-confirm="<?= kk_e(__('Delete this list? The books stay in the library.')) ?>" data-reload="<?= kk_e($reload) ?>"><?= __('Delete list') ?></button>
        </div>
      </form>

      <h3><?= __('Books') ?></h3>
      <ol class="kk-list kk-list--ordered" data-list="<?= $lid ?>">
        <?php foreach ($items as $it): ?>
          <li data-book="<?= (int)$it['biblio_id'] ?>">
            <span class="kk-list__body"><strong dir="auto"><?= kk_e($it['title']) ?></strong>
              <input class="kk-input kk-list__note" data-list-note value="<?= kk_e($it['note']) ?>" maxlength="500" placeholder="<?= kk_e(__('A note shown under the book (optional)')) ?>" dir="auto" aria-label="<?= kk_e(__('Note')) ?>"></span>
            <span class="kk-list__actions">
              <button type="button" class="kk-btn kk-btn--sm" data-list-move="-1" aria-label="<?= kk_e(__('Move up')) ?>">↑</button>
              <button type="button" class="kk-btn kk-btn--sm" data-list-move="1" aria-label="<?= kk_e(__('Move down')) ?>">↓</button>
              <button type="button" class="kk-btn kk-btn--sm kk-btn--danger" data-list-remove><?= __('Remove') ?></button>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="kk-picker" data-picker="list_add" data-list="<?= $lid ?>">
        <label class="kk-note" for="kk-p<?= $lid ?>"><?= __('Add a book') ?></label>
        <input class="kk-input" id="kk-p<?= $lid ?>" type="search" autocomplete="off" placeholder="<?= kk_e(__('Title, author or ISBN')) ?>">
        <ul class="kk-picker__results" hidden></ul>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
<?= kk_script('admin.js') ?>
