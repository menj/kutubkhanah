<?php
/**
 * Kutubkhanah: Authors (Private Library author metadata: years, nationality, discipline,
 * external link, curator's notes). Shown on the public author page, index.php?p=author&id=.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\DB;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$db = DB::getInstance();
$id = (int)($_GET['id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['pg'] ?? 1));
$per = 50;

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Authors') ?></h2>
    <p><?= __('Details shown on each author’s public page. Author names are managed in Bibliography and Master File.') ?></p>
  </header>
<?php
if ($id) {
    $st = $db->prepare("SELECT a.author_id, a.author_name, m.* FROM mst_author a LEFT JOIN kk_author m ON m.author_id = a.author_id WHERE a.author_id = ?");
    $st->execute([$id]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
}
if ($id && !empty($a)):
    $f = fn($k) => kk_e($a[$k] ?? '');
?>
  <form class="kk-panel kk-save notAJAX" data-action="author_save" data-id="<?= $id ?>">
    <h3><?= kk_e($a['author_name']) ?></h3>
    <?php foreach ([
        'birth_year' => [__('Birth year'), ''], 'death_year' => [__('Death year'), __('Leave blank if living.')],
        'nationality' => [__('Nationality'), ''], 'discipline' => [__('Discipline'), __('e.g. History, Poetry, Islamic Studies')],
        'external_url' => [__('External link'), __('Wikipedia, author site, etc. A full https:// address.')],
    ] as $k => [$label, $help]): ?>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-a-<?= $k ?>"><?= kk_e($label) ?></label></div><div class="kk-row__field">
        <input class="kk-input" id="kk-a-<?= $k ?>" name="<?= $k ?>" value="<?= $f($k) ?>"<?= $k === 'external_url' ? ' type="url"' : '' ?>>
        <?= $help ? '<p class="kk-note">' . kk_e($help) . '</p>' : '' ?></div></div>
    <?php endforeach; ?>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-a-notes"><?= __("Curator's notes") ?></label></div><div class="kk-row__field">
      <textarea class="kk-input kk-textarea" id="kk-a-notes" name="notes" rows="5"><?= $f('notes') ?></textarea>
      <label class="kk-check"><input type="checkbox" name="notes_public" value="1"<?= !empty($a['notes_public']) ? ' checked' : '' ?>> <?= __('Show these notes to visitors') ?></label></div></div>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save author') ?></button>
      <a class="kk-btn" href="<?= MWB ?>kutubkhanah/authors.php"><?= __('All authors') ?></a>
      <a class="kk-btn notAJAX" href="<?= SWB ?>index.php?p=author&id=<?= $id ?>" target="_blank" rel="noopener"><?= __('View public page') ?></a>
      <span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>
<?php else:
    $where = $q !== '' ? 'WHERE a.author_name LIKE ?' : '';
    $args = $q !== '' ? ['%' . addcslashes($q, '%_\\') . '%'] : [];
    $count = $db->prepare("SELECT COUNT(*) FROM mst_author a $where");
    $count->execute($args);
    $total = (int)$count->fetchColumn();
    $st = $db->prepare("SELECT a.author_id, a.author_name, COUNT(ba.biblio_id) AS books,
                               (m.author_id IS NOT NULL) AS has_meta
                        FROM mst_author a LEFT JOIN biblio_author ba ON ba.author_id = a.author_id
                        LEFT JOIN kk_author m ON m.author_id = a.author_id
                        $where GROUP BY a.author_id, a.author_name, m.author_id ORDER BY a.author_name
                        LIMIT $per OFFSET " . (($page - 1) * $per));
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $pages = max(1, (int)ceil($total / $per));
?>
  <form class="kk-panel kk-inline-form submitViaAJAX" method="get" action="<?= MWB ?>kutubkhanah/authors.php" id="kk-author-find">
    <label class="kk-sr" for="kk-author-q"><?= __('Find an author') ?></label>
    <input class="kk-input" id="kk-author-q" name="q" value="<?= kk_e($q) ?>" placeholder="<?= __('Author name') ?>">
    <button type="submit" class="kk-btn kk-btn--primary"><?= __('Find') ?></button>
    <span class="kk-note"><?= sprintf(__('%d authors'), $total) ?></span>
  </form>
  <div class="kk-panel">
    <ul class="kk-list">
      <?php foreach ($rows as $r): ?>
        <li class="kk-list__row">
          <span class="kk-list__body"><strong><?= kk_e($r['author_name']) ?></strong>
            <span class="kk-note"><?= sprintf((int)$r['books'] === 1 ? __('%d book') : __('%d books'), (int)$r['books']) ?><?= $r['has_meta'] ? ' · ' . __('details added') : '' ?></span></span>
          <a class="kk-btn" href="<?= MWB ?>kutubkhanah/authors.php?id=<?= (int)$r['author_id'] ?>"><?= __('Edit details') ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1): ?>
      <nav class="kk-pager" aria-label="<?= __('Pages') ?>">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <a href="<?= MWB ?>kutubkhanah/authors.php?pg=<?= $p ?>&q=<?= rawurlencode($q) ?>" class="<?= $p === $page ? 'is-active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?= kk_script('admin.js') ?>
