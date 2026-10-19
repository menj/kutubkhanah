<?php
/**
 * Kutubkhanah: Book details editor (Private Library fields: format, volume, tags,
 * digital link, OCLC, Google Books ID, Goodreads ID, reading status and progress,
 * curator's notes). Title, authors, call number, series, edition and language are
 * edited in SLiMS's own Bibliography form.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\DB;
use SLiMS\Kutubkhanah\Books;
use SLiMS\Kutubkhanah\Fields;
use SLiMS\Kutubkhanah\Loans;
use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$id = (int)($_GET['id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$db = DB::getInstance();

$book = null;
if ($id) {
    $st = $db->prepare('SELECT biblio_id, title, isbn_issn, image, sor FROM biblio WHERE biblio_id = ?');
    $st->execute([$id]);
    $book = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$results = [];
if (!$book && $q !== '') {
    $st = $db->prepare("SELECT biblio_id, title, isbn_issn, sor FROM biblio
                        WHERE title LIKE ? OR REPLACE(isbn_issn, '-', '') LIKE ? OR sor LIKE ? ORDER BY title LIMIT 30");
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st->execute([$like, '%' . preg_replace('/[^0-9Xx]/', '', $q) . '%', $like]);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
}

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Book Details') ?></h2>
    <p><?= __('Extra details shown on the public book page. Title, authors, call number, series, edition, language and subjects (genres) are edited in Bibliography.') ?></p>
  </header>

  <?php if (!$book): ?>
    <form class="kk-panel kk-inline-form submitViaAJAX" method="get" action="<?= MWB ?>kutubkhanah/book.php" id="kk-book-find">
      <label class="kk-sr" for="kk-book-q"><?= __('Find a book') ?></label>
      <input class="kk-input" id="kk-book-q" name="q" value="<?= kk_e($q) ?>" placeholder="<?= __('Title, author or ISBN') ?>">
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Find') ?></button>
    </form>
    <?php if ($q !== ''): ?>
      <div class="kk-panel">
        <?php if (!$results): ?>
          <p class="kk-note"><?= __('No books match.') ?></p>
        <?php else: ?>
          <ul class="kk-list">
            <?php foreach ($results as $r): ?>
              <li class="kk-list__row">
                <span class="kk-list__body"><strong><?= kk_e($r['title']) ?></strong>
                  <span class="kk-note"><?= kk_e(trim(($r['sor'] ? $r['sor'] . ' · ' : '') . $r['isbn_issn'])) ?></span></span>
                <a class="kk-btn" href="<?= MWB ?>kutubkhanah/book.php?id=<?= (int)$r['biblio_id'] ?>"><?= __('Edit details') ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php else:
    $m = Books::meta($id);
    $text = function (string $name, string $label, string $help = '', string $type = 'text') use ($m) {
        echo '<div class="kk-row"><div class="kk-row__label"><label for="kk-f-' . $name . '">' . kk_e($label) . '</label></div><div class="kk-row__field">'
            . '<input class="kk-input" type="' . $type . '" id="kk-f-' . $name . '" name="' . $name . '" value="' . kk_e($m[$name]) . '">'
            . ($help ? '<p class="kk-note">' . kk_e($help) . '</p>' : '') . '</div></div>';
    };
  ?>
    <div class="kk-panel kk-book-head">
      <span class="kk-thumb"><?php if ($book['image']): ?><img src="<?= SWB ?>lib/minigalnano/createthumb.php?filename=images/docs/<?= rawurlencode($book['image']) ?>&width=80" alt=""><?php endif; ?></span>
      <span class="kk-list__body"><strong><?= kk_e($book['title']) ?></strong>
        <span class="kk-note"><?= kk_e(trim(($book['sor'] ? $book['sor'] . ' · ' : '') . $book['isbn_issn'])) ?></span></span>
      <span class="kk-list__actions">
        <a class="kk-btn" href="<?= MWB ?>bibliography/index.php?detail=true&itemID=<?= $id ?>"><?= __('Edit in Bibliography') ?></a>
        <a class="kk-btn notAJAX" href="<?= SWB ?>index.php?p=show_detail&id=<?= $id ?>" target="_blank" rel="noopener"><?= __('View public page') ?></a>
      </span>
    </div>

    <form class="kk-panel kk-save notAJAX" data-action="book_save" data-id="<?= $id ?>">
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-format"><?= __('Format') ?></label></div><div class="kk-row__field">
        <select class="kk-input" id="kk-f-format" name="format"><option value=""><?= __('— Not set —') ?></option>
          <?php foreach (Settings::formats() as $f): ?><option value="<?= kk_e($f) ?>"<?= $m['format'] === $f ? ' selected' : '' ?>><?= kk_e($f) ?></option><?php endforeach; ?>
        </select><p class="kk-note"><?= __('"Digital Archive" books appear under Digital Archives on the public site.') ?></p></div></div>
      <?php
      $text('volume', __('Volume in series'), __('The number of this book in its series (set the series name in Bibliography > Series Title). Used for the series page.'));
      $text('tags', __('Tags'), __('Comma separated, e.g. Signed Copy, First Edition, Rare.'));
      $text('digital_link', __('Digital copy link'), __('A full https:// address. Shows a "View digital copy" button.'), 'url');
      $text('oclc', __('OCLC / WorldCat number'));
      $text('google_books_id', __('Google Books ID'));
      $text('goodreads_id', __('Goodreads book ID'));
      ?>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-reading_status"><?= __('Reading status') ?></label></div><div class="kk-row__field">
        <select class="kk-input" id="kk-f-reading_status" name="reading_status"><option value=""><?= __('— Not set —') ?></option>
          <?php foreach (Settings::readingStatuses() as $v => $l): ?><option value="<?= $v ?>"<?= $m['reading_status'] === $v ? ' selected' : '' ?>><?= kk_e($l) ?></option><?php endforeach; ?>
        </select></div></div>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-reading_progress"><?= __('Reading progress') ?></label></div><div class="kk-row__field">
        <input class="kk-input kk-input--short" type="number" min="0" max="100" id="kk-f-reading_progress" name="reading_progress" value="<?= (int)$m['reading_progress'] ?>"> %
        <p class="kk-note"><?= __('Shown as a progress bar while the status is "Reading".') ?></p></div></div>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-rating"><?= __('Your rating') ?></label></div><div class="kk-row__field">
        <select class="kk-input kk-input--mid" id="kk-f-rating" name="rating">
          <?php foreach ([0 => __('— Not rated —'), 5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆'] as $v => $l): ?>
            <option value="<?= $v ?>"<?= (int)$m['rating'] === $v ? ' selected' : '' ?>><?= kk_e($l) ?></option>
          <?php endforeach; ?>
        </select></div></div>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-review"><?= __('Your review') ?></label></div><div class="kk-row__field">
        <textarea class="kk-input kk-textarea" id="kk-f-review" name="review" rows="5" dir="auto"><?= kk_e($m['review']) ?></textarea>
        <label class="kk-check"><input type="checkbox" name="review_public" value="1"<?= !empty($m['review_public']) ? ' checked' : '' ?>> <?= __('Show this review to visitors') ?></label></div></div>
      <?php
      $cfs = Fields::forFormat((string)$m['format']);
      if ($cfs):
          $cvals = Fields::values($id); ?>
        <h3 class="kk-subhead"><?= __('Custom fields') ?></h3>
        <?php foreach ($cfs as $cf): ?>
          <div class="kk-row"><div class="kk-row__label"><label for="kk-cf-<?= (int)$cf['id'] ?>"><?= kk_e($cf['label']) ?></label></div><div class="kk-row__field">
            <?= Fields::input($cf, (string)($cvals[$cf['id']] ?? '')) ?><?php if (empty($cf['is_public'])): ?><p class="kk-note"><?= __('Private: not shown to visitors.') ?></p><?php endif; ?></div></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-f-notes"><?= __("Curator's notes") ?></label></div><div class="kk-row__field">
        <textarea class="kk-input kk-textarea" id="kk-f-notes" name="notes" rows="5"><?= kk_e($m['notes']) ?></textarea>
        <label class="kk-check"><input type="checkbox" name="notes_public" value="1"<?= !empty($m['notes_public']) ? ' checked' : '' ?>> <?= __('Show these notes to visitors') ?></label></div></div>
      <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save details') ?></button>
        <a class="kk-btn" href="<?= MWB ?>kutubkhanah/book.php"><?= __('Find another book') ?></a>
        <span class="kk-note kk-saved" aria-live="polite"></span></div>
    </form>

    <?php $loans = Loans::forBook($id); $cur = Loans::current($id); ?>
    <div class="kk-panel">
      <h3><?= __('Lending') ?></h3>
      <?php if ($cur): ?>
        <p><?= sprintf(__('Out with %s since %s.'), '<strong>' . kk_e($cur['borrower']) . '</strong>', kk_e($cur['lent_on'])) ?>
          <?= $cur['due_on'] ? kk_e(sprintf(__('Due back %s.'), $cur['due_on'])) : '' ?></p>
        <button type="button" class="kk-btn kk-btn--primary" data-kk-action="loan_return" data-id="<?= (int)$cur['id'] ?>" data-reload="<?= kk_e(MWB . 'kutubkhanah/book.php?id=' . $id) ?>"><?= __('Mark as returned') ?></button>
      <?php else: ?>
        <p class="kk-note"><?= __('This book is at home.') ?></p>
        <a class="kk-btn" href="<?= MWB ?>kutubkhanah/loans.php?id=<?= $id ?>"><?= __('Lend this book') ?></a>
      <?php endif; ?>
      <?php if (count($loans) > ($cur ? 1 : 0)): ?>
        <ul class="kk-list kk-list--compact">
          <?php foreach ($loans as $l): if ($cur && (int)$l['id'] === (int)$cur['id']) continue; ?>
            <li class="kk-list__row"><span class="kk-list__body"><?= kk_e(sprintf(__('%1$s: %2$s to %3$s'), $l['borrower'], $l['lent_on'], $l['returned_on'] ?: '…')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?= kk_script('admin.js') ?>
