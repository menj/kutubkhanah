<?php
/**
 * Kutubkhanah: Import from Libib, export CSV, fetch missing covers.
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Covers;
use SLiMS\Kutubkhanah\Authors;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$pending = Covers::countPending();
echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Import / Export') ?></h2>
  </header>

  <section class="kk-panel">
    <h3><?= __('Import from Libib') ?></h3>
    <p class="kk-note"><?= __('Upload the CSV exported from Libib. Books are matched by ISBN, or by title when there is none, so importing twice never creates duplicates. Fetch covers afterwards.') ?></p>
    <form id="kk-import" class="kk-inline-form notAJAX" enctype="multipart/form-data">
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Import CSV') ?></button>
    </form>
    <p class="kk-note" id="kk-import-status" aria-live="polite"></p>
  </section>

  <section class="kk-panel">
    <h3><?= __('Fetch missing covers') ?></h3>
    <p class="kk-note"><?= sprintf(__('%d books with an ISBN have not been checked for a cover yet. Covers are saved in images/docs, the same place as covers added by hand.'), $pending) ?></p>
    <div class="kk-actions">
      <button type="button" class="kk-btn kk-btn--primary" id="kk-covers"><?= __('Fetch covers') ?></button>
      <label class="kk-check kk-check--inline"><input type="checkbox" id="kk-covers-reset"> <?= __('Also retry books that failed before') ?></label>
    </div>
    <div class="kk-progress" id="kk-covers-progress" hidden><div class="kk-progress__bar"></div></div>
    <p class="kk-note" id="kk-covers-status" aria-live="polite"></p>
  </section>

  <section class="kk-panel">
    <h3><?= __('Fetch author portraits') ?></h3>
    <p class="kk-note"><?= sprintf(__('%d authors have not been looked up yet. Portraits and short biographies come from Open Library and are saved on this server.'), Authors::countPending()) ?></p>
    <div class="kk-actions">
      <button type="button" class="kk-btn kk-btn--primary" id="kk-authors"><?= __('Fetch portraits') ?></button>
      <label class="kk-check kk-check--inline"><input type="checkbox" id="kk-authors-reset"> <?= __('Also retry authors that had no result') ?></label>
    </div>
    <p class="kk-note" id="kk-authors-status" aria-live="polite"></p>

    <h4><?= __('Review matches') ?></h4>
    <p class="kk-note"><?= __('Names are matched by search, so a different spelling or name order can pick the wrong person. Check each match, set a search name or Open Library ID if needed, and add a sort name to file an author under the part you choose.') ?></p>
    <form method="get" class="kk-actions">
      <?php foreach ($_GET as $k => $v) if ($k !== 'aq' && is_string($v)) echo '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES) . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '">'; ?>
      <input type="search" name="aq" value="<?= htmlspecialchars((string)($_GET['aq'] ?? ''), ENT_QUOTES) ?>" placeholder="<?= __('Find an author') ?>" aria-label="<?= __('Find an author') ?>">
      <button class="kk-btn kk-btn--outline kk-btn--sm"><?= __('Search') ?></button>
    </form>
    <?php $kkRev = Authors::reviewList(trim((string)($_GET['aq'] ?? ''))); ?>
    <?php if (!$kkRev) : ?><p class="kk-note"><?= __('Nothing to review.') ?></p><?php else : ?>
    <div class="kk-author-review">
      <?php foreach ($kkRev as $r) : $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
      <div class="kk-author-review__row" data-author="<?= (int)$r['author_id'] ?>">
        <p><strong dir="auto"><?= $h($r['author_name']) ?></strong>
          <span class="kk-muted">&rarr; <span class="kk-ar-match"><?= $r['ol_name'] ? $h($r['ol_name']) . ' (' . $h($r['ol_key']) . ')' : __('no match') ?></span></span>
          <?= $r['reviewed'] === 'a' ? '<span class="kk-badge kk-badge--muted">' . __('Approved') . '</span>' : ($r['reviewed'] === 'r' ? '<span class="kk-badge kk-badge--muted">' . __('Rejected') . '</span>' : '') ?></p>
        <div class="kk-actions">
          <input data-f="sort_name" value="<?= $h($r['sort_name']) ?>" placeholder="<?= __('Sort name') ?>" aria-label="<?= __('Sort name') ?>">
          <input data-f="native_name" dir="auto" value="<?= $h($r['native_name']) ?>" placeholder="<?= __('Native-script name') ?>" aria-label="<?= __('Native-script name') ?>">
          <input data-f="search_name" value="<?= $h($r['search_name']) ?>" placeholder="<?= __('Search name') ?>" aria-label="<?= __('Search name') ?>">
          <input data-f="ol_key" value="<?= $h($r['ol_key']) ?>" placeholder="OL123A" aria-label="Open Library ID" size="10">
          <button type="button" class="kk-btn kk-btn--outline kk-btn--sm" data-op="save"><?= __('Save') ?></button>
          <button type="button" class="kk-btn kk-btn--outline kk-btn--sm" data-op="refetch"><?= __('Look up again') ?></button>
          <button type="button" class="kk-btn kk-btn--primary kk-btn--sm" data-op="approve"><?= __('Approve') ?></button>
          <button type="button" class="kk-btn kk-btn--ghost kk-btn--sm" data-op="reject"><?= __('Reject') ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="kk-panel">
    <h3><?= __('Export') ?></h3>
    <p class="kk-note"><?= __('Download the whole catalogue as a CSV with Libib-compatible columns. It can be re-imported here or elsewhere.') ?></p>
    <a class="kk-btn notAJAX" href="<?= MWB ?>kutubkhanah/api.php?action=export&token=<?= kk_e($_SESSION['kk_token']) ?>"><?= __('Download CSV') ?></a>
  </section>

  <?php if (\SLiMS\DB::backup() instanceof \SLiMS\Kutubkhanah\Db\Dump): ?>
  <section class="kk-panel">
    <h3><?= __('Restore a backup') ?></h3>
    <p class="kk-note"><?= __('Upload a .sql backup made by System > Backup on this kind of database. The catalogue tables in the file replace the current ones. A copy of the current data is saved in the backup folder first, and a failed restore changes nothing.') ?></p>
    <form id="kk-restore" class="kk-inline-form notAJAX" enctype="multipart/form-data">
      <input type="file" name="sql" accept=".sql,text/plain" required>
      <label class="kk-check kk-check--inline"><input type="checkbox" name="confirm" value="1"> <?= __('Replace my current data with this backup') ?></label>
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Restore') ?></button>
    </form>
    <p class="kk-note" id="kk-restore-status" aria-live="polite"></p>
  </section>
  <?php endif; ?>
</div>
<?= kk_script('admin.js') ?>
