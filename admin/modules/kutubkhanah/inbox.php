<?php
/**
 * Kutubkhanah: Inbox for the public Request Access and Contact forms.
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Messages;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$type = in_array($_GET['t'] ?? 'access', ['access', 'contact', 'suggest', 'borrow'], true) ? $_GET['t'] : 'access';
$status = in_array($_GET['s'] ?? '', Messages::STATUSES, true) ? $_GET['s'] : '';
$counts = Messages::counts();
$rows = Messages::list($type, $status);
$labels = ['new' => __('Pending'), 'approved' => __('Approved'), 'declined' => __('Declined'), 'closed' => __('Done')];

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Inbox') ?></h2>
    <p><?= __('Messages from the public Request Access, Borrow, Suggest a book and Contact pages. Set "Notify email" in Settings > Library to also get them by email.') ?></p>
  </header>

  <nav class="kk-tabs" aria-label="<?= __('Message type') ?>">
    <?php foreach (['access' => __('Access requests'), 'borrow' => __('Borrow requests'), 'suggest' => __('Suggestions'), 'contact' => __('Contact messages')] as $tk => $tl): ?>
      <a href="<?= MWB ?>kutubkhanah/inbox.php?t=<?= $tk ?>" class="kk-tab<?= $type === $tk ? ' is-active' : '' ?>"<?= $type === $tk ? ' aria-current="page"' : '' ?>><?= kk_e($tl) ?> <span class="kk-count"><?= $counts[$tk] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <div class="kk-filter">
    <a href="<?= MWB ?>kutubkhanah/inbox.php?t=<?= $type ?>" class="<?= $status === '' ? 'is-active' : '' ?>"><?= __('All') ?></a>
    <?php foreach ($labels as $k => $l): ?>
      <a href="<?= MWB ?>kutubkhanah/inbox.php?t=<?= $type ?>&s=<?= $k ?>" class="<?= $status === $k ? 'is-active' : '' ?>"><?= kk_e($l) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="kk-panel"><p class="kk-note"><?= __('No messages here.') ?></p></div>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <article class="kk-panel kk-msg" data-id="<?= (int)$r['id'] ?>">
      <header class="kk-msg__head">
        <div>
          <strong><?= kk_e(in_array($r['type'], ['contact', 'suggest'], true) ? $r['subject'] : $r['name']) ?></strong>
          <span class="kk-note"><?= kk_e($r['type'] !== 'access' ? $r['name'] . ' · ' : '') ?><a class="notAJAX" href="mailto:<?= kk_e($r['email']) ?>"><?= kk_e($r['email']) ?></a> · <?= kk_e($r['created_at']) ?></span>
          <?php if ($r['affiliation'] !== ''): ?><span class="kk-note"><?= $r['type'] === 'suggest' ? __('Author') : __('Affiliation') ?>: <?= kk_e($r['affiliation']) ?></span><?php endif; ?>
        </div>
        <div class="kk-msg__actions">
          <label class="kk-sr" for="kk-st-<?= (int)$r['id'] ?>"><?= __('Status') ?></label>
          <select class="kk-input kk-input--short kk-msg-status" id="kk-st-<?= (int)$r['id'] ?>" data-id="<?= (int)$r['id'] ?>">
            <?php foreach ($labels as $k => $l): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= kk_e($l) ?></option><?php endforeach; ?>
          </select>
          <a class="kk-btn notAJAX" href="mailto:<?= kk_e($r['email']) ?>?subject=<?= rawurlencode('Re: ' . ['contact' => $r['subject'], 'suggest' => 'Your book suggestion: ' . $r['subject'], 'borrow' => 'Your borrow request', 'access' => 'Your access request'][$r['type']]) ?>"><?= __('Reply') ?></a>
          <?php if ($r['type'] === 'suggest' && $r['status'] === 'new'): ?><button type="button" class="kk-btn kk-btn--primary" data-kk-action="suggest_to_wishlist" data-id="<?= (int)$r['id'] ?>" data-values='<?= kk_e(json_encode(['id' => (int)$r['id']])) ?>' data-reload="<?= kk_e(MWB . 'kutubkhanah/inbox.php?t=suggest') ?>"><?= __('Add to wishlist') ?></button><?php endif; ?>
          <button type="button" class="kk-btn kk-msg-delete" data-id="<?= (int)$r['id'] ?>" data-confirm="<?= kk_e(__('Delete this message permanently?')) ?>"><?= __('Delete') ?></button>
        </div>
      </header>
      <p class="kk-msg__body"><?= nl2br(kk_e($r['body'])) ?></p>
    </article>
  <?php endforeach; ?>
</div>
<?= kk_script('admin.js') ?>
