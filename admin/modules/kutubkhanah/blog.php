<?php
/**
 * Kutubkhanah blog: all posts (or pages), with status filters, search and quick actions.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Posts;
use SLiMS\Kutubkhanah\Blog\Render;
use SLiMS\Kutubkhanah\Blog\Terms;
use SLiMS\Kutubkhanah\Blog\Text;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$type = ($_GET['type'] ?? 'post') === 'page' ? 'page' : 'post';
$status = (string)($_GET['status'] ?? '');
if ($status !== '' && !in_array($status, Posts::STATUSES, true)) $status = '';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$r = Posts::query(['type' => $type, 'status' => $status, 'search' => $q, 'page' => $page, 'per_page' => $per]);
$counts = Posts::counts($type);
$labels = Posts::statusLabels();
$self = MWB . 'kutubkhanah/blog.php?type=' . $type;
$noun = $type === 'page' ? __('Pages') : __('Posts');

echo kk_head();
?>
<div class="kk kk-blogadmin">
  <header class="kk-head kk-head--row">
    <div>
      <h2><?= kk_e($noun) ?></h2>
      <p><?= $type === 'page'
          ? __('Standalone pages such as "About the collection", written with the same editor as posts.')
          : kk_e(sprintf(__('Write for the %s: drafts, scheduled posts, categories, tags, images and book cards from your catalogue.'), Render::title())) ?></p>
    </div>
    <a class="kk-btn kk-btn--primary" href="<?= MWB ?>kutubkhanah/post.php?type=<?= $type ?>"><?= $type === 'page' ? __('Add page') : __('Write a post') ?></a>
  </header>

  <div class="kk-filter">
    <a href="<?= kk_e($self) ?>" class="<?= $status === '' ? 'is-active' : '' ?>"><?= __('All') ?> <span class="kk-count"><?= array_sum($counts) - (int)($counts['trash'] ?? 0) ?></span></a>
    <?php foreach ($labels as $k => $l): if (empty($counts[$k])) continue; ?>
      <a href="<?= kk_e($self . '&status=' . $k) ?>" class="<?= $status === $k ? 'is-active' : '' ?>"><?= kk_e($l) ?> <span class="kk-count"><?= (int)$counts[$k] ?></span></a>
    <?php endforeach; ?>
  </div>

  <form class="kk-inline-form submitViaAJAX" method="get" action="<?= MWB ?>kutubkhanah/blog.php">
    <input type="hidden" name="type" value="<?= $type ?>"><?php if ($status): ?><input type="hidden" name="status" value="<?= kk_e($status) ?>"><?php endif; ?>
    <label class="kk-sr" for="kk-blog-q"><?= __('Search') ?></label>
    <input class="kk-input" id="kk-blog-q" name="q" value="<?= kk_e($q) ?>" placeholder="<?= __('Search titles and text') ?>">
    <button class="kk-btn" type="submit"><?= __('Search') ?></button>
  </form>

  <div class="kk-panel">
    <?php if (!$r['rows']): ?>
      <p class="kk-note"><?= $q !== '' ? __('Nothing matches your search.') : ($type === 'page' ? __('No pages yet.') : __('No posts yet. Write the first one!')) ?></p>
    <?php else: ?>
    <ul class="kk-list">
      <?php foreach ($r['rows'] as $p):
          $terms = $type === 'post' ? Terms::forPost((int)$p['id']) : ['category' => [], 'tag' => []];
          $edit = MWB . 'kutubkhanah/post.php?id=' . (int)$p['id'];
          $view = Posts::url($p) . ($p['status'] === 'publish' ? '' : '&preview=' . Render::previewKey((int)$p['id']));
      ?>
        <li class="kk-list__row">
          <span class="kk-list__body">
            <a href="<?= kk_e($edit) ?>"><strong dir="auto"><?= kk_e($p['title'] !== '' ? $p['title'] : __('(no title)')) ?></strong></a>
            <span class="kk-note">
              <span class="kk-status kk-status--<?= kk_e($p['status']) ?>"><?= kk_e($labels[$p['status']] ?? $p['status']) ?></span>
              <?= $p['sticky'] ? '· ' . __('Featured') : '' ?>
              · <?= kk_e($p['status'] === 'future' ? sprintf(__('goes live %s'), $p['published_at']) : ($p['published_at'] ? substr($p['published_at'], 0, 16) : sprintf(__('last edited %s'), substr($p['updated_at'], 0, 16)))) ?>
              <?php if ($terms['category']): ?>· <?= kk_e(implode(', ', array_column($terms['category'], 'name'))) ?><?php endif; ?>
              <?php if ((int)$p['comment_count']): ?>· <?= kk_e(sprintf(__('%d comments'), (int)$p['comment_count'])) ?><?php endif; ?>
              · <?= kk_e(sprintf(__('%d words'), count(preg_split('/\s+/u', Text::plain((string)$p['content']), -1, PREG_SPLIT_NO_EMPTY)))) ?>
            </span>
          </span>
          <span class="kk-list__actions">
            <a class="kk-btn" href="<?= kk_e($edit) ?>"><?= __('Edit') ?></a>
            <a class="kk-btn notAJAX" href="<?= kk_e($view) ?>" target="_blank" rel="noopener"><?= $p['status'] === 'publish' ? __('View') : __('Preview') ?></a>
            <?php if ($p['status'] === 'trash'): ?>
              <button type="button" class="kk-btn" data-kk-action="post_status" data-id="<?= (int)$p['id'] ?>" data-values='{"status":"draft"}' data-reload="<?= kk_e($self . '&status=trash') ?>"><?= __('Restore') ?></button>
              <button type="button" class="kk-btn" data-kk-action="post_delete" data-id="<?= (int)$p['id'] ?>" data-remove data-confirm="<?= kk_e(__('Delete this permanently, with its comments and revisions?')) ?>"><?= __('Delete permanently') ?></button>
            <?php else: ?>
              <button type="button" class="kk-btn" data-kk-action="post_status" data-id="<?= (int)$p['id'] ?>" data-values='{"status":"trash"}' data-remove><?= __('Trash') ?></button>
            <?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php $pages = (int)ceil($r['total'] / $per); if ($pages > 1): ?>
      <div class="kk-pager"><?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= kk_e($self . '&status=' . $status . '&q=' . rawurlencode($q) . '&page=' . $i) ?>"><?= $i ?></a>
      <?php endfor; ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
