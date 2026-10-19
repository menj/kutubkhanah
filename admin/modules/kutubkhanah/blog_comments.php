<?php
/**
 * Kutubkhanah blog: comment moderation (approve, reply, spam, trash).
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Comments;
use SLiMS\Kutubkhanah\Blog\Posts;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$status = in_array($_GET['status'] ?? '', Comments::STATUSES, true) ? $_GET['status'] : 'pending';
$r = Comments::adminList($status);
$counts = Comments::counts();
$self = MWB . 'kutubkhanah/blog_comments.php?status=' . $status;

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Comments') ?></h2>
    <p><?= __('Approve visitors’ comments before they appear (Settings > Blog), reply as the librarian, or mark spam.') ?></p>
  </header>
  <div class="kk-filter">
    <?php foreach (Comments::statusLabels() as $k => $l): ?>
      <a href="<?= kk_e(MWB . 'kutubkhanah/blog_comments.php?status=' . $k) ?>" class="<?= $status === $k ? 'is-active' : '' ?>"><?= kk_e($l) ?> <span class="kk-count"><?= (int)($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <div class="kk-panel">
    <?php if (!$r['rows']): ?><p class="kk-note"><?= __('No comments here.') ?></p><?php else: ?>
    <ul class="kk-list">
      <?php foreach ($r['rows'] as $c): $url = $c['post_slug'] ? Posts::url(['type' => $c['post_type'], 'slug' => $c['post_slug']]) . '#comment-' . (int)$c['id'] : ''; ?>
        <li class="kk-list__row kk-comment-admin">
          <span class="kk-list__body">
            <strong><?= kk_e($c['author']) ?></strong>
            <span class="kk-note"><?= kk_e(trim($c['email'] . ' ' . $c['url'] . ' · ' . $c['created_at'] . ($c['ip'] ? ' · ' . $c['ip'] : ''))) ?></span>
            <span class="kk-note"><?= __('On') ?> <?= $url ? '<a class="notAJAX" href="' . kk_e($url) . '" target="_blank" rel="noopener">' . kk_e($c['post_title']) . '</a>' : kk_e($c['post_title']) ?></span>
            <span class="kk-comment-admin__text" dir="auto"><?= nl2br(kk_e($c['content'])) ?></span>
            <?php if ($status === 'approved' || $status === 'pending'): ?>
            <details class="kk-comment-admin__reply"><summary><?= __('Reply') ?></summary>
              <form class="kk-save notAJAX" data-action="comment_reply" data-id="<?= (int)$c['id'] ?>" data-reload="<?= kk_e($self) ?>">
                <textarea class="kk-input kk-textarea" name="reply" rows="3" dir="auto" aria-label="<?= __('Reply') ?>"></textarea>
                <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= $status === 'pending' ? __('Approve and reply') : __('Reply') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
              </form>
            </details>
            <?php endif; ?>
          </span>
          <span class="kk-list__actions">
            <?php foreach (['approved' => __('Approve'), 'pending' => __('Unapprove'), 'spam' => __('Spam'), 'trash' => __('Trash')] as $k => $l): if ($k === $status) continue; ?>
              <button type="button" class="kk-btn<?= $k === 'approved' ? ' kk-btn--primary' : '' ?>" data-kk-action="comment_status" data-id="<?= (int)$c['id'] ?>" data-values='{"status":"<?= $k ?>"}' data-remove><?= kk_e($l) ?></button>
            <?php endforeach; ?>
            <?php if ($status === 'trash' || $status === 'spam'): ?>
              <button type="button" class="kk-btn" data-kk-action="comment_delete" data-id="<?= (int)$c['id'] ?>" data-remove data-confirm="<?= kk_e(__('Delete this comment permanently?')) ?>"><?= __('Delete permanently') ?></button>
            <?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
