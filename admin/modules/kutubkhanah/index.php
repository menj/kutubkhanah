<?php
/**
 * Kutubkhanah: Scan ISBN.
 * GNU GPL v3 or later.
 */

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Scan ISBN') ?></h2>
    <p><?= __('Scan the barcode with your camera or type the ISBN. Every ISBN is saved, even when no source recognises the book. Unrecognised ones wait under Review.') ?></p>
  </header>

  <div class="kk-scan">
    <section class="kk-panel" aria-label="<?= __('Scanner') ?>">
      <div id="kk-reader" class="kk-reader"></div>
      <p id="kk-camera-msg" class="kk-note" aria-live="polite"></p>
      <div class="kk-actions">
        <button type="button" class="kk-btn kk-btn--primary" id="kk-start"><?= __('Start camera') ?></button>
        <button type="button" class="kk-btn" id="kk-stop" hidden><?= __('Stop camera') ?></button>
      </div>
      <label class="kk-check"><input type="checkbox" id="kk-continuous" checked> <?= __('Keep scanning after each book') ?></label>
      <label class="kk-check"><input type="checkbox" id="kk-copy"> <?= __('Add a copy if the book is already in the library') ?></label>
      <div class="kk-mode" role="radiogroup" aria-label="<?= __('Add scanned books to') ?>">
        <span class="kk-note"><?= __('Add to') ?></span>
        <label class="kk-check kk-check--inline"><input type="radio" name="kk-mode" value="library" checked> <?= __('Library') ?></label>
        <label class="kk-check kk-check--inline"><input type="radio" name="kk-mode" value="wish"> <?= __('Wishlist') ?></label>
      </div>
      <form id="kk-manual" class="kk-manual notAJAX" autocomplete="off">
        <label class="kk-sr" for="kk-isbn"><?= __('ISBN') ?></label>
        <input type="text" id="kk-isbn" inputmode="numeric" placeholder="<?= __('Type or paste an ISBN') ?>">
        <button type="submit" class="kk-btn"><?= __('Add') ?></button>
      </form>
    </section>

    <section class="kk-panel" aria-label="<?= __('This session') ?>">
      <h3><?= __('This session') ?> <span class="kk-count" id="kk-count">0</span></h3>
      <ul class="kk-results" id="kk-results" aria-live="polite"></ul>
      <p class="kk-note" id="kk-empty"><?= __('Scanned books will appear here.') ?></p>
    </section>
  </div>
</div>
<?= kk_script('vendor/html5-qrcode.min.js') ?>
<?= kk_script('scanner.js') ?>
