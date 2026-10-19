<?php
/**
 * Kutubkhanah: Review queue (needs details, no cover, no author, no ISBN).
 * GNU GPL v3 or later.
 */

use SLiMS\DB;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_read) kk_deny();

$filters = [
    'needs'    => [__('Needs details'), 'k.needs_details = 1'],
    'nocover'  => [__('No cover'), "(b.image IS NULL OR b.image = '')"],
    'noauthor' => [__('No author'), 'NOT EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.biblio_id = b.biblio_id)'],
    'noisbn'   => [__('No ISBN'), "(b.isbn_issn IS NULL OR b.isbn_issn = '')"],
];
$current = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'needs';
$db = DB::getInstance();
$base = 'FROM biblio b LEFT JOIN kk_book k ON k.biblio_id = b.biblio_id WHERE ';

$counts = [];
foreach ($filters as $key => [, $where]) {
    $counts[$key] = (int)$db->query('SELECT COUNT(*) ' . $base . $where)->fetchColumn();
}

$page = max(1, (int)($_GET['pg'] ?? 1));
$per = 50;
$rows = $db->query(
    'SELECT b.biblio_id, b.title, b.isbn_issn, b.image, b.sor ' . $base . $filters[$current][1]
    . ' ORDER BY b.title LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)
)->fetchAll(PDO::FETCH_ASSOC);
$pages = max(1, (int)ceil($counts[$current] / $per));

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Review') ?></h2>
    <p><?= __('Books that still need attention. "Retry lookup" fills in blanks from Open Library and Google Books without overwriting your edits.') ?></p>
  </header>

  <nav class="kk-tabs" aria-label="<?= __('Filters') ?>">
    <?php foreach ($filters as $key => [$label]): ?>
      <a href="<?= MWB ?>kutubkhanah/review.php?f=<?= $key ?>" class="kk-tab<?= $key === $current ? ' is-active' : '' ?>"<?= $key === $current ? ' aria-current="page"' : '' ?>>
        <?= kk_e($label) ?> <span class="kk-count"><?= $counts[$key] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="kk-panel">
    <?php if (!$rows): ?>
      <p class="kk-note"><?= __('Nothing here. Every book is complete for this check.') ?></p>
    <?php else: ?>
      <ul class="kk-list">
        <?php foreach ($rows as $r): ?>
          <li class="kk-list__row">
            <span class="kk-thumb">
              <?php if ($r['image']): ?>
                <img src="<?= SWB ?>lib/minigalnano/createthumb.php?filename=images/docs/<?= rawurlencode($r['image']) ?>&width=80" alt="" loading="lazy">
              <?php endif; ?>
            </span>
            <span class="kk-list__body">
              <strong><?= kk_e($r['title']) ?></strong>
              <span class="kk-note"><?= kk_e(trim(($r['sor'] ? $r['sor'] . ' · ' : '') . ($r['isbn_issn'] ?: __('No ISBN')))) ?></span>
            </span>
            <span class="kk-list__actions">
              <?php if ($kk_can_write && $r['isbn_issn']): ?>
                <button type="button" class="kk-btn kk-retry" data-id="<?= (int)$r['biblio_id'] ?>"><?= __('Retry lookup') ?></button>
              <?php endif; ?>
              <a class="kk-btn" href="<?= MWB ?>bibliography/index.php?detail=true&itemID=<?= (int)$r['biblio_id'] ?>"><?= __('Edit') ?></a>
              <a class="kk-btn" href="<?= MWB ?>kutubkhanah/book.php?id=<?= (int)$r['biblio_id'] ?>"><?= __('Details') ?></a>
              <span class="kk-note kk-retry-status" aria-live="polite"></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($pages > 1): ?>
        <nav class="kk-pager" aria-label="<?= __('Pages') ?>">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="<?= MWB ?>kutubkhanah/review.php?f=<?= $current ?>&pg=<?= $p ?>" class="<?= $p === $page ? 'is-active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
