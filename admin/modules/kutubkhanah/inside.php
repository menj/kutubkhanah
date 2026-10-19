<?php
/**
 * Kutubkhanah: search inside books. Index the text of PDF digital copies (with OCR for scanned pages).
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\DocSearch;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$tools = DocSearch::tools();
$files = DocSearch::files();
$counts = DocSearch::counts();

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Search Inside') ?></h2>
    <p><?= __('Reads the text of PDFs attached to books so visitors can search inside them. Only files with public access appear in results. Scanned pages are read with OCR when tesseract is installed.') ?>
      <?php if (Settings::get('enable_inside')): ?><a class="notAJAX" href="<?= SWB ?>index.php?p=inside" target="_blank" rel="noopener"><?= __('View the public search') ?></a><?php endif; ?></p>
  </header>

  <div class="kk-panel">
    <h3><?= __('Server tools') ?></h3>
    <ul class="kk-list">
      <li><span class="kk-list__body"><strong>pdftotext</strong> <span class="kk-note"><?= $tools['pdftotext'] ? __('Found') : __('Missing: install poppler-utils') ?></span></span></li>
      <li><span class="kk-list__body"><strong>tesseract + pdftoppm</strong> <span class="kk-note"><?= $tools['tesseract'] && $tools['pdftoppm'] ? __('Found: scanned pages will be read') : __('Missing: scanned pages cannot be read') ?></span></span></li>
    </ul>
  </div>

  <div class="kk-panel" id="kk-inside" data-inside>
    <h3><?= __('Index') ?> <span class="kk-count"><?= (int)$counts['indexed'] ?> / <?= (int)$counts['files'] ?></span></h3>
    <p class="kk-note"><?= kk_e(sprintf(__('%1$d PDF files attached to books, %2$d pages indexed.'), $counts['files'], $counts['pages'])) ?></p>
    <div class="kk-actions">
      <button type="button" class="kk-btn kk-btn--primary" data-inside-run<?= $tools['pdftotext'] ? '' : ' disabled' ?>><?= __('Index new and changed files') ?></button>
      <label class="kk-check"><input type="checkbox" data-inside-noocr> <?= __('Skip OCR (faster)') ?></label>
      <span class="kk-note" data-inside-status aria-live="polite"></span>
    </div>
    <div class="kk-progress" hidden><div class="kk-progress__bar" data-inside-bar></div></div>
  </div>

  <div class="kk-panel">
    <h3><?= __('Digital copies') ?></h3>
    <?php if (!$files): ?><p class="kk-note"><?= __('No PDF files are attached to books yet. Attach them in Bibliography > Attachments.') ?></p><?php else: ?>
    <ul class="kk-list">
      <?php foreach ($files as $f): ?>
        <li>
          <span class="kk-list__body"><strong dir="auto"><?= kk_e($f['title']) ?></strong>
            <span class="kk-note" dir="auto"><?= kk_e($f['file_title'] ?: $f['file_name']) ?> ·
              <?= $f['status'] === null ? kk_e(__('not indexed')) : ($f['status'] === 'done' ? kk_e(sprintf(__('%1$d pages (%2$s)'), (int)$f['pages'], $f['method'])) : kk_e(__('no text found'))) ?></span></span>
          <span class="kk-list__actions">
            <button type="button" class="kk-btn kk-btn--sm" data-kk-action="doc_index" data-id="<?= (int)$f['file_id'] ?>" data-reload="<?= kk_e(MWB . 'kutubkhanah/inside.php') ?>"><?= $f['status'] === null ? __('Index') : __('Re-index') ?></button>
            <?php if ($f['status'] !== null): ?><button type="button" class="kk-btn kk-btn--sm" data-kk-action="doc_forget" data-id="<?= (int)$f['file_id'] ?>" data-reload="<?= kk_e(MWB . 'kutubkhanah/inside.php') ?>"><?= __('Forget') ?></button><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
