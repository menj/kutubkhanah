<?php
/**
 * Kutubkhanah blog: import from WordPress (a WXR export file).
 *
 * GNU GPL v3 or later.
 */

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Import from WordPress') ?></h2>
    <p><?= __('Bring an existing WordPress blog into Kutubkhanah: categories, tags, posts, pages, featured images, images in posts and comments.') ?></p>
  </header>
  <form class="kk-panel" id="kk-wxr-form" enctype="multipart/form-data">
    <ol class="kk-steps">
      <li><?= __('In WordPress, go to Tools > Export, choose "All content" and download the export file.') ?></li>
      <li><?= __('Choose that .xml file below. Running the import again later only adds what is new.') ?></li>
    </ol>
    <div class="kk-inline-form">
      <input class="kk-input" type="file" name="wxr" accept=".xml,application/xml,text/xml" required aria-label="<?= __('WordPress export file') ?>">
      <label class="kk-check kk-check--inline"><input type="checkbox" name="images" value="1" checked> <?= __('Copy images from the old site') ?></label>
      <button type="submit" class="kk-btn kk-btn--primary"><?= __('Import') ?></button>
    </div>
    <p class="kk-note kk-saved" aria-live="polite"></p>
  </form>
</div>
<?= kk_script('admin.js') ?>
