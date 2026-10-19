<?php
/**
 * Kutubkhanah: wishlist. Books you want but don't own yet. Add them here or with the scanner
 * ("Add to: Wishlist"); when one arrives, "I have it now" moves it into the library.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Settings;
use SLiMS\Kutubkhanah\Wishlist;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$rows = Wishlist::open();
$got = Wishlist::acquiredList(10);
$prio = Wishlist::priorities();
$reload = MWB . 'kutubkhanah/wishlist.php';

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Wishlist') ?></h2>
    <p><?= __('Books you would like to own. They stay out of the catalogue. Scan with "Add to: Wishlist" on the Scan ISBN page, or add one here.') ?>
      <?php if (Settings::get('enable_wishlist')): ?><a class="notAJAX" href="<?= SWB ?>index.php?p=wishlist" target="_blank" rel="noopener"><?= __('View the public wishlist') ?></a><?php endif; ?></p>
  </header>

  <form class="kk-panel kk-save notAJAX" data-action="wish_save" data-id="0" data-reload="<?= kk_e($reload) ?>">
    <h3><?= __('Add a book') ?></h3>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-w-isbn">ISBN</label></div><div class="kk-row__field">
      <input class="kk-input kk-input--mid kk-isbn" id="kk-w-isbn" name="isbn" inputmode="numeric" autocomplete="off">
      <p class="kk-note"><?= __('Enter just the ISBN to have the title, author and cover looked up, or fill in the title yourself.') ?></p></div></div>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-w-title"><?= __('Title') ?></label></div><div class="kk-row__field">
      <input class="kk-input" id="kk-w-title" name="title" dir="auto"></div></div>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-w-authors"><?= __('Author') ?></label></div><div class="kk-row__field">
      <input class="kk-input" id="kk-w-authors" name="authors" dir="auto"></div></div>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-w-prio"><?= __('Priority') ?></label></div><div class="kk-row__field">
      <select class="kk-input kk-input--mid" id="kk-w-prio" name="priority"><?php foreach ($prio as $k => $l): ?><option value="<?= $k ?>"<?= $k === 2 ? ' selected' : '' ?>><?= kk_e($l) ?></option><?php endforeach; ?></select></div></div>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-w-notes"><?= __('Notes') ?></label></div><div class="kk-row__field">
      <textarea class="kk-input kk-textarea" id="kk-w-notes" name="notes" rows="2" placeholder="<?= __('Edition wanted, where to buy, price…') ?>"></textarea>
      <label class="kk-check"><input type="checkbox" name="is_public" value="1" checked> <?= __('Show on the public wishlist') ?></label></div></div>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Add to wishlist') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <div class="kk-panel">
    <h3><?= __('Wanted') ?> <span class="kk-count"><?= count($rows) ?></span></h3>
    <?php if (!$rows): ?><p class="kk-note"><?= __('Nothing on the wishlist yet.') ?></p><?php else: ?>
    <ul class="kk-list">
      <?php foreach ($rows as $w): ?>
        <li class="kk-list__row">
          <span class="kk-thumb"><?php if ($w['image']): ?><img src="<?= Library::cover($w['image'], 80) ?>" alt=""><?php endif; ?></span>
          <span class="kk-list__body"><strong dir="auto"><?= kk_e($w['title']) ?></strong>
            <span class="kk-note" dir="auto"><?= kk_e(trim($w['authors'] . ($w['isbn'] ? ' · ISBN ' . $w['isbn'] : ''), ' ·')) ?></span>
            <span class="kk-note"><?= kk_e(($prio[(int)$w['priority']] ?? '') . ' · ' . sprintf(__('added %s'), $w['added']) . ($w['is_public'] ? '' : ' · ' . __('private'))) ?></span>
            <?php if ($w['notes']): ?><span class="kk-note"><?= kk_e($w['notes']) ?></span><?php endif; ?></span>
          <span class="kk-list__actions">
            <button type="button" class="kk-btn kk-btn--primary" data-kk-action="wish_acquire" data-id="<?= (int)$w['id'] ?>" data-reload="<?= kk_e($reload) ?>"><?= __('I have it now') ?></button>
            <button type="button" class="kk-btn" data-kk-action="wish_delete" data-id="<?= (int)$w['id'] ?>" data-remove data-confirm="<?= kk_e(__('Remove this book from the wishlist?')) ?>"><?= __('Remove') ?></button>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <?php if ($got): ?>
  <div class="kk-panel">
    <h3><?= __('Wishes come true') ?></h3>
    <ul class="kk-list kk-list--compact">
      <?php foreach ($got as $w): ?>
        <li class="kk-list__row"><span class="kk-list__body"><a href="<?= MWB ?>kutubkhanah/book.php?id=<?= (int)$w['acquired_biblio_id'] ?>"><?= kk_e($w['book_title']) ?></a></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<?= kk_script('admin.js') ?>
