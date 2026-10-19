<?php
/**
 * Kutubkhanah blog: the post / page editor (WordPress's classic editor, TinyMCE 4, with
 * "Insert book" and the media library, publish box, categories, tags, featured image and revisions).
 *
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Blog\Media;
use SLiMS\Kutubkhanah\Blog\Posts;
use SLiMS\Kutubkhanah\Blog\Render;
use SLiMS\Kutubkhanah\Blog\Terms;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? Posts::get($id) : null;
if ($id && !$p) kk_deny();
$type = $p['type'] ?? (($_GET['type'] ?? 'post') === 'page' ? 'page' : 'post');
$p ??= ['id' => 0, 'type' => $type, 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'status' => 'draft', 'featured_media' => 0,
        'published_at' => null, 'comment_status' => 1, 'sticky' => 0, 'meta_description' => '', 'menu_order' => 0, 'updated_at' => null];
$terms = $p['id'] ? Terms::forPost((int)$p['id']) : ['category' => [], 'tag' => []];
$checked = array_map('intval', array_column($terms['category'], 'id')) ?: [Terms::defaultCategory()];
$featured = Media::describe(Media::get((int)$p['featured_media']));
$labels = Posts::statusLabels();
$revisions = $p['id'] ? Posts::revisions((int)$p['id']) : [];
$tagNames = array_column(Terms::all('tag', false), 'name');

$cfg = [
    'id' => (int)$p['id'], 'type' => $type,
    'tinymce' => SWB . 'js/kutubkhanah/vendor/tinymce',
    'contentCss' => [SWB . 'css/kutubkhanah/fonts.css?v=' . KK_VERSION, SWB . 'css/kutubkhanah/editor-content.css?v=' . KK_VERSION],
    'list' => MWB . 'kutubkhanah/blog.php?type=' . $type,
    'i18n' => [
        'insertBook' => __('Insert a book from the catalogue'), 'findBook' => __('Title, author or ISBN'), 'chooseBook' => __('Choose a book'),
        'noBooks' => __('No books match.'), 'insertImage' => __('Insert an image'), 'media' => __('Media library'),
        'upload' => __('Upload images'), 'use' => __('Use this image'), 'saving' => __('Saving…'), 'saved' => __('Saved.'),
        'published' => __('Published.'), 'scheduled' => __('Scheduled.'), 'error' => __('Could not save. Check your connection and try again.'),
        'unsaved' => __('You have unsaved changes.'), 'altText' => __('Describe the image for people who cannot see it (alt text)'),
        'restore' => __('Restore this version? The current text is kept as a revision.'), 'loading' => __('Loading…'),
    ],
];

echo kk_head();
echo '<link rel="stylesheet" href="' . SWB . 'css/kutubkhanah/blog-admin.css?v=' . KK_VERSION . '">';
echo '<script>window.kkPost = ' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
?>
<div class="kk kk-editor-page">
  <header class="kk-head kk-head--row">
    <div>
      <h2><?= $p['id'] ? ($type === 'page' ? __('Edit page') : __('Edit post')) : ($type === 'page' ? __('New page') : __('New post')) ?></h2>
      <p><a href="<?= kk_e($cfg['list']) ?>">← <?= $type === 'page' ? __('All pages') : __('All posts') ?></a></p>
    </div>
  </header>

  <form class="kk-editor" id="kk-post-form" autocomplete="off">
    <div class="kk-editor__main">
      <label class="kk-sr" for="kk-post-title"><?= __('Title') ?></label>
      <input class="kk-editor__title" id="kk-post-title" name="title" value="<?= kk_e($p['title']) ?>" placeholder="<?= __('Add title') ?>" dir="auto">
      <p class="kk-editor__permalink"><span><?= __('Permalink') ?>:</span>
        <code><?= kk_e(SWB . 'index.php?' . ($type === 'page' ? 'p=page&slug=' : 'p=blog&post=')) ?></code><input class="kk-input kk-input--slug" name="slug" value="<?= kk_e($p['slug']) ?>" aria-label="<?= __('Slug') ?>" placeholder="<?= __('made from the title') ?>"></p>
      <textarea id="kk-post-content" name="content" rows="24" dir="auto"><?= htmlspecialchars((string)$p['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="kk-note kk-editor__hint"><?= __('Tip: use the book button to add a card for any book in your catalogue, or type [book id="12"]. Headings, quotes and lists also work with Markdown-style shortcuts: "## " for a heading, "> " for a quote, "* " for a list.') ?></p>

      <section class="kk-panel">
        <h3><label for="kk-post-excerpt"><?= __('Excerpt') ?></label></h3>
        <textarea class="kk-input kk-textarea" id="kk-post-excerpt" name="excerpt" rows="3" dir="auto"><?= kk_e($p['excerpt']) ?></textarea>
        <p class="kk-note"><?= __('Optional summary for lists, search engines and social media. Left empty, the first words of the post are used.') ?></p>
        <h3><label for="kk-post-meta"><?= __('Search engine description') ?></label></h3>
        <input class="kk-input" id="kk-post-meta" name="meta_description" maxlength="300" value="<?= kk_e($p['meta_description']) ?>" dir="auto">
      </section>
    </div>

    <aside class="kk-editor__side">
      <section class="kk-panel kk-box">
        <h3><?= __('Publish') ?></h3>
        <p class="kk-box__row"><span><?= __('Status') ?></span> <strong id="kk-post-status-label"><?= kk_e($labels[$p['status']] ?? $p['status']) ?></strong></p>
        <label class="kk-box__label" for="kk-post-status"><?= __('Visibility') ?></label>
        <select class="kk-input" id="kk-post-status" name="status">
          <?php foreach (['draft' => __('Draft'), 'publish' => __('Public'), 'private' => __('Private (only you)')] as $k => $l): ?>
            <option value="<?= $k ?>"<?= ($p['status'] === $k || ($k === 'publish' && $p['status'] === 'future')) ? ' selected' : '' ?>><?= kk_e($l) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="kk-box__label" for="kk-post-date"><?= __('Publish on') ?></label>
        <input class="kk-input" type="datetime-local" id="kk-post-date" name="published_at" value="<?= $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '' ?>">
        <p class="kk-note"><?= __('Leave empty to publish now. A future date schedules the post.') ?></p>
        <div class="kk-box__actions">
          <button type="button" class="kk-btn" data-save="draft"><?= __('Save draft') ?></button>
          <a class="kk-btn notAJAX" id="kk-post-preview" target="_blank" rel="noopener" href="<?= $p['id'] ? kk_e(Posts::url($p) . ($p['status'] === 'publish' ? '' : '&preview=' . Render::previewKey((int)$p['id']))) : '#' ?>"<?= $p['id'] ? '' : ' hidden' ?>><?= $p['status'] === 'publish' ? __('View') : __('Preview') ?></a>
        </div>
        <div class="kk-box__actions kk-box__actions--end">
          <?php if ($p['id'] && $p['status'] !== 'trash'): ?>
            <button type="button" class="kk-link-danger" data-kk-action="post_status" data-id="<?= (int)$p['id'] ?>" data-values='{"status":"trash"}' data-reload="<?= kk_e($cfg['list']) ?>"><?= __('Move to trash') ?></button>
          <?php endif; ?>
          <button type="button" class="kk-btn kk-btn--primary" data-save="publish"><?= in_array($p['status'], ['publish', 'future'], true) ? __('Update') : __('Publish') ?></button>
        </div>
        <p class="kk-note kk-saved" aria-live="polite"><?= isset($_GET['saved']) ? kk_e(in_array($p['status'], ['publish', 'future'], true) ? ($p['status'] === 'future' ? __('Scheduled.') : __('Published.')) : __('Saved.')) : '' ?></p>
      </section>

      <?php if ($type === 'post'): ?>
      <section class="kk-panel kk-box">
        <h3><?= __('Categories') ?></h3>
        <div class="kk-catlist" id="kk-catlist">
          <?php foreach (Terms::tree() as $t): ?>
            <label class="kk-check" style="padding-left:<?= (int)$t['depth'] * 16 ?>px"><input type="checkbox" name="categories[]" value="<?= (int)$t['id'] ?>"<?= in_array((int)$t['id'], $checked, true) ? ' checked' : '' ?>> <?= kk_e($t['name']) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="kk-inline-add"><input class="kk-input" id="kk-newcat" placeholder="<?= __('New category') ?>" aria-label="<?= __('New category') ?>"><button type="button" class="kk-btn" id="kk-addcat"><?= __('Add') ?></button></div>
      </section>

      <section class="kk-panel kk-box">
        <h3><label for="kk-post-tags"><?= __('Tags') ?></label></h3>
        <input class="kk-input" id="kk-post-tags" name="tags" list="kk-taglist" value="<?= kk_e(implode(', ', array_column($terms['tag'], 'name'))) ?>" dir="auto">
        <datalist id="kk-taglist"><?php foreach ($tagNames as $t): ?><option value="<?= kk_e($t) ?>"><?php endforeach; ?></datalist>
        <p class="kk-note"><?= __('Separate tags with commas.') ?></p>
      </section>
      <?php else: ?>
      <section class="kk-panel kk-box">
        <h3><label for="kk-post-order"><?= __('Order') ?></label></h3>
        <input class="kk-input kk-input--short" type="number" id="kk-post-order" name="menu_order" value="<?= (int)$p['menu_order'] ?>">
      </section>
      <?php endif; ?>

      <section class="kk-panel kk-box">
        <h3><?= __('Featured image') ?></h3>
        <input type="hidden" name="featured_media" id="kk-featured-id" value="<?= (int)$p['featured_media'] ?>">
        <div class="kk-featured" id="kk-featured"><?php if ($featured): ?><img src="<?= kk_e($featured['thumb']) ?>" alt=""><?php endif; ?></div>
        <div class="kk-box__actions">
          <button type="button" class="kk-btn" id="kk-featured-set"><?= $featured ? __('Replace image') : __('Set featured image') ?></button>
          <button type="button" class="kk-btn" id="kk-featured-remove"<?= $featured ? '' : ' hidden' ?>><?= __('Remove') ?></button>
        </div>
      </section>

      <section class="kk-panel kk-box">
        <h3><?= __('Discussion') ?></h3>
        <label class="kk-check"><input type="checkbox" name="comment_status" value="1"<?= $p['comment_status'] ? ' checked' : '' ?>> <?= __('Allow comments') ?></label>
        <?php if ($type === 'post'): ?><label class="kk-check"><input type="checkbox" name="sticky" value="1"<?= $p['sticky'] ? ' checked' : '' ?>> <?= __('Feature at the top of the journal') ?></label><?php endif; ?>
      </section>

      <?php if ($revisions): ?>
      <section class="kk-panel kk-box">
        <h3><?= __('Revisions') ?> <span class="kk-count"><?= count($revisions) ?></span></h3>
        <ul class="kk-revisions">
          <?php foreach (array_slice($revisions, 0, 12) as $rv): ?>
            <li><span><?= kk_e(date('j M Y, H:i', strtotime($rv['created_at']))) ?></span>
              <button type="button" class="kk-link-btn" data-kk-action="revision_restore" data-id="<?= (int)$rv['id'] ?>" data-confirm="<?= kk_e($cfg['i18n']['restore']) ?>" data-reload="<?= kk_e(MWB . 'kutubkhanah/post.php?id=' . (int)$p['id']) ?>"><?= __('Restore') ?></button></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
    </aside>
  </form>

  <div class="kk-modal" id="kk-modal" hidden>
    <div class="kk-modal__box" role="dialog" aria-modal="true" aria-labelledby="kk-modal-title">
      <header class="kk-modal__head"><h3 id="kk-modal-title"></h3><button type="button" class="kk-modal__close" data-modal-close aria-label="<?= __('Close') ?>">×</button></header>
      <div class="kk-modal__body" id="kk-modal-body"></div>
    </div>
  </div>
</div>
<script src="<?= SWB ?>js/kutubkhanah/vendor/tinymce/tinymce.min.js?v=4.9.11"></script>
<?= kk_script('admin.js') ?>
<?= kk_script('blog-editor.js') ?>
