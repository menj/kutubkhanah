<?php
/**
 * Kutubkhanah blog: categories and tags.
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Terms;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$tax = ($_GET['tax'] ?? 'category') === 'tag' ? 'tag' : 'category';
$rows = $tax === 'category' ? Terms::tree() : Terms::all('tag');
$self = MWB . 'kutubkhanah/blog-terms.php?tax=' . $tax;
$cats = $tax === 'category' ? Terms::tree() : [];

function kk_term_parent_select(array $cats, int $selected, int $self): string
{
    $o = '<option value="0">' . __('— None —') . '</option>';
    foreach ($cats as $c) {
        if ((int)$c['id'] === $self) continue;
        $o .= '<option value="' . (int)$c['id'] . '"' . ((int)$c['id'] === $selected ? ' selected' : '') . '>' . str_repeat('— ', (int)$c['depth']) . kk_e($c['name']) . '</option>';
    }
    return $o;
}

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= $tax === 'tag' ? __('Tags') : __('Categories') ?></h2>
    <p><?= $tax === 'tag' ? __('Tags are loose keywords. Add them while writing a post; tidy them up here.') : __('Categories group posts by subject, and can be nested.') ?></p>
  </header>
  <div class="kk-tabs" role="tablist">
    <a class="kk-tab<?= $tax === 'category' ? ' is-active' : '' ?>" href="<?= MWB ?>kutubkhanah/blog-terms.php?tax=category"><?= __('Categories') ?></a>
    <a class="kk-tab<?= $tax === 'tag' ? ' is-active' : '' ?>" href="<?= MWB ?>kutubkhanah/blog-terms.php?tax=tag"><?= __('Tags') ?></a>
  </div>

  <form class="kk-panel kk-save notAJAX" data-action="term_save" data-id="0" data-taxonomy="<?= $tax ?>" data-reload="<?= kk_e($self) ?>">
    <h3><?= $tax === 'tag' ? __('Add a tag') : __('Add a category') ?></h3>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-t-name"><?= __('Name') ?></label></div><div class="kk-row__field"><input class="kk-input" id="kk-t-name" name="name" required dir="auto"></div></div>
    <?php if ($tax === 'category'): ?>
      <div class="kk-row"><div class="kk-row__label"><label for="kk-t-parent"><?= __('Parent') ?></label></div><div class="kk-row__field"><select class="kk-input" id="kk-t-parent" name="parent"><?= kk_term_parent_select($cats, 0, 0) ?></select></div></div>
    <?php endif; ?>
    <div class="kk-row"><div class="kk-row__label"><label for="kk-t-desc"><?= __('Description') ?></label></div><div class="kk-row__field"><textarea class="kk-input kk-textarea" id="kk-t-desc" name="description" rows="2" dir="auto"></textarea></div></div>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Add') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <div class="kk-panel">
    <?php if (!$rows): ?><p class="kk-note"><?= __('None yet.') ?></p><?php else: ?>
    <ul class="kk-list">
      <?php foreach ($rows as $i => $t): ?>
        <li class="kk-list__row kk-term-row">
          <details class="kk-term-edit">
            <summary><strong dir="auto"><?= str_repeat('— ', (int)($t['depth'] ?? 0)) . kk_e($t['name']) ?></strong>
              <span class="kk-note"><?= kk_e($t['slug']) ?> · <?= kk_e(sprintf(__('%d posts'), (int)$t['n'])) ?></span></summary>
            <form class="kk-save notAJAX" data-action="term_save" data-id="<?= (int)$t['id'] ?>" data-taxonomy="<?= $tax ?>" data-reload="<?= kk_e($self) ?>">
              <div class="kk-row"><div class="kk-row__label"><label for="kk-t<?= $i ?>-name"><?= __('Name') ?></label></div><div class="kk-row__field"><input class="kk-input" id="kk-t<?= $i ?>-name" name="name" value="<?= kk_e($t['name']) ?>" dir="auto"></div></div>
              <div class="kk-row"><div class="kk-row__label"><label for="kk-t<?= $i ?>-slug"><?= __('Slug') ?></label></div><div class="kk-row__field"><input class="kk-input" id="kk-t<?= $i ?>-slug" name="slug" value="<?= kk_e($t['slug']) ?>"></div></div>
              <?php if ($tax === 'category'): ?>
                <div class="kk-row"><div class="kk-row__label"><label for="kk-t<?= $i ?>-parent"><?= __('Parent') ?></label></div><div class="kk-row__field"><select class="kk-input" id="kk-t<?= $i ?>-parent" name="parent"><?= kk_term_parent_select($cats, (int)$t['parent'], (int)$t['id']) ?></select></div></div>
              <?php endif; ?>
              <div class="kk-row"><div class="kk-row__label"><label for="kk-t<?= $i ?>-desc"><?= __('Description') ?></label></div><div class="kk-row__field"><textarea class="kk-input kk-textarea" id="kk-t<?= $i ?>-desc" name="description" rows="2" dir="auto"><?= kk_e($t['description']) ?></textarea></div></div>
              <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save') ?></button>
                <button type="button" class="kk-btn" data-kk-action="term_delete" data-id="<?= (int)$t['id'] ?>" data-confirm="<?= kk_e(__('Delete this? Posts keep their other categories and tags.')) ?>" data-reload="<?= kk_e($self) ?>"><?= __('Delete') ?></button>
                <a class="kk-btn notAJAX" href="<?= kk_e(Terms::url($t)) ?>" target="_blank" rel="noopener"><?= __('View') ?></a>
                <span class="kk-note kk-saved" aria-live="polite"></span></div>
            </form>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?= kk_script('admin.js') ?>
