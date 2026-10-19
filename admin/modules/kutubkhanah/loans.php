<?php
/**
 * Kutubkhanah: Lending log. What is out, with whom, since when; lend and return books.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\DB;
use SLiMS\Kutubkhanah\Books;
use SLiMS\Kutubkhanah\Loans;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_read) kk_deny();

$id = (int)($_GET['id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$db = DB::getInstance();

$results = [];
if ($q !== '') {
    $st = $db->prepare("SELECT biblio_id, title, isbn_issn, sor FROM biblio
                        WHERE title LIKE ? OR REPLACE(isbn_issn, '-', '') LIKE ? OR sor LIKE ? ORDER BY title LIMIT 30");
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st->execute([$like, '%' . (preg_replace('/[^0-9Xx]/', '', $q) ?: '#') . '%', $like]);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
}
$book = $id ? Books::title($id) : '';

function kk_loan_row(array $l, bool $open, bool $canWrite): string
{
    $over = Loans::isOverdue($l);
    $when = sprintf(__('Lent %s'), kk_e($l['lent_on']));
    if ($open && $l['due_on']) $when .= ' · ' . ($over ? '<strong class="kk-bad">' . sprintf(__('overdue since %s'), kk_e($l['due_on'])) . '</strong>' : sprintf(__('due %s'), kk_e($l['due_on'])));
    if (!$open) $when .= ' · ' . sprintf(__('returned %s'), kk_e($l['returned_on']));
    $thumb = $l['image'] ? '<img src="' . SWB . 'lib/minigalnano/createthumb.php?filename=images/docs/' . rawurlencode($l['image']) . '&width=80" alt="">' : '';
    $actions = '';
    if ($canWrite) {
        $reload = kk_e(MWB . 'kutubkhanah/loans.php');
        if ($open) $actions .= '<button type="button" class="kk-btn kk-btn--primary" data-kk-action="loan_return" data-id="' . (int)$l['id'] . '" data-reload="' . $reload . '">' . __('Returned') . '</button>';
        $actions .= '<button type="button" class="kk-btn" data-kk-action="loan_delete" data-id="' . (int)$l['id'] . '" data-remove data-confirm="' . kk_e(__('Delete this lending record?')) . '">' . __('Delete') . '</button>';
    }
    return '<li class="kk-list__row' . ($over ? ' is-overdue' : '') . '"><span class="kk-thumb">' . $thumb . '</span>'
        . '<span class="kk-list__body"><a href="' . MWB . 'kutubkhanah/book.php?id=' . (int)$l['biblio_id'] . '"><strong>' . kk_e($l['title']) . '</strong></a>'
        . '<span>' . sprintf(__('with %s'), '<strong>' . kk_e($l['borrower']) . '</strong>') . ($l['contact'] ? ' <span class="kk-note">(' . kk_e($l['contact']) . ')</span>' : '') . '</span>'
        . '<span class="kk-note">' . $when . '</span>'
        . ($l['notes'] ? '<span class="kk-note">' . kk_e($l['notes']) . '</span>' : '') . '</span>'
        . '<span class="kk-list__actions">' . $actions . '</span></li>';
}

$open = Loans::open();
$returns = Loans::recentReturns(15);
$borrowers = Loans::borrowers();
$days = (int)Settings::get('loan_days');

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Lending') ?></h2>
    <p><?= __('A simple log of books lent to friends and family: who has what, since when, and what is overdue.') ?></p>
  </header>

  <?php if ($kk_can_write): ?>
  <div class="kk-panel">
    <h3><?= __('Lend a book') ?></h3>
    <?php if (!$book): ?>
      <form class="kk-inline-form submitViaAJAX" method="get" action="<?= MWB ?>kutubkhanah/loans.php">
        <label class="kk-sr" for="kk-loan-q"><?= __('Find a book') ?></label>
        <input class="kk-input" id="kk-loan-q" name="q" value="<?= kk_e($q) ?>" placeholder="<?= __('Title, author or ISBN') ?>">
        <button type="submit" class="kk-btn kk-btn--primary"><?= __('Find') ?></button>
      </form>
      <?php if ($q !== ''): ?>
        <?php if (!$results): ?><p class="kk-note"><?= __('No books match.') ?></p><?php else: ?>
        <ul class="kk-list">
          <?php foreach ($results as $r): $cur = Loans::current((int)$r['biblio_id']); ?>
            <li class="kk-list__row">
              <span class="kk-list__body"><strong><?= kk_e($r['title']) ?></strong>
                <span class="kk-note"><?= kk_e(trim(($r['sor'] ? $r['sor'] . ' · ' : '') . $r['isbn_issn'])) ?></span>
                <?php if ($cur): ?><span class="kk-note"><?= kk_e(sprintf(__('Already out with %s'), $cur['borrower'])) ?></span><?php endif; ?></span>
              <a class="kk-btn" href="<?= MWB ?>kutubkhanah/loans.php?id=<?= (int)$r['biblio_id'] ?>"><?= __('Lend this') ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      <?php endif; ?>
    <?php else: ?>
      <p><strong><?= kk_e($book) ?></strong> · <a href="<?= MWB ?>kutubkhanah/loans.php"><?= __('Choose another book') ?></a></p>
      <form class="kk-save notAJAX" data-action="loan_save" data-id="<?= $id ?>" data-reload="<?= kk_e(MWB . 'kutubkhanah/loans.php') ?>">
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l-borrower"><?= __('Lent to') ?></label></div><div class="kk-row__field">
          <input class="kk-input" id="kk-l-borrower" name="borrower" list="kk-borrowers" required autocomplete="off">
          <datalist id="kk-borrowers"><?php foreach ($borrowers as $b): ?><option value="<?= kk_e($b['borrower']) ?>"><?php endforeach; ?></datalist></div></div>
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l-contact"><?= __('Contact') ?></label></div><div class="kk-row__field">
          <input class="kk-input" id="kk-l-contact" name="contact" placeholder="<?= __('Phone or email (optional)') ?>"></div></div>
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l-lent"><?= __('Lent on') ?></label></div><div class="kk-row__field">
          <input class="kk-input kk-input--mid" type="date" id="kk-l-lent" name="lent_on" value="<?= date('Y-m-d') ?>"></div></div>
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l-due"><?= __('Due back') ?></label></div><div class="kk-row__field">
          <input class="kk-input kk-input--mid" type="date" id="kk-l-due" name="due_on" value="<?= $days > 0 ? date('Y-m-d', strtotime('+' . $days . ' days')) : '' ?>">
          <p class="kk-note"><?= __('Leave empty for no due date.') ?></p></div></div>
        <div class="kk-row"><div class="kk-row__label"><label for="kk-l-notes"><?= __('Notes') ?></label></div><div class="kk-row__field">
          <textarea class="kk-input kk-textarea" id="kk-l-notes" name="notes" rows="2"></textarea></div></div>
        <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Lend') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="kk-panel">
    <h3><?= __('Currently out') ?> <span class="kk-count"><?= count($open) ?></span></h3>
    <?php if (!$open): ?><p class="kk-note"><?= __('Every book is at home.') ?></p><?php else: ?>
      <ul class="kk-list"><?php foreach ($open as $l) echo kk_loan_row($l, true, $kk_can_write); ?></ul>
    <?php endif; ?>
  </div>

  <?php if ($returns): ?>
  <div class="kk-panel">
    <h3><?= __('Recently returned') ?></h3>
    <ul class="kk-list"><?php foreach ($returns as $l) echo kk_loan_row($l, false, $kk_can_write); ?></ul>
  </div>
  <?php endif; ?>
</div>
<?= kk_script('admin.js') ?>
