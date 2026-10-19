<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2019-01-29 10:43
 * @File name           : _other.php
 */

?>

<div class="result-search kk-page pb-5">
    <section id="section1 container-fluid">
        <header class="c-header">
            <div class="mask"></div>
          <?php
          // ----------------------------------------------------------------------
          // include navbar part
          // ----------------------------------------------------------------------
          include '_navbar.php'; ?>
          <?php if ($_GET['p'] !== 'show_detail') : ?>
          <div class="kk-banner">
        <div class="container">
            <span class="kk-eyebrow"><?= htmlspecialchars($sysconf['library_name'], ENT_QUOTES); ?></span>
            <h1 class="kk-banner__title"><?= $page_title; ?></h1>
        </div>
    </div>
          <?php endif; ?>
        </header>
      <?php
      // ------------------------------------------------------------------------
      // include search form part (Kutubkhanah pages have their own search or none)
      // ------------------------------------------------------------------------
      if (!in_array($_GET['p'] ?? '', ['shelf', 'contact', 'request_access', 'faq', 'author', 'series', 'wishlist', 'blog', 'page'], true)) include '_search-form.php';
      else echo '<div class="kk-banner-end" aria-hidden="true"></div>'; ?>
    </section>


    <section class="container mt-8 kk-page__body">
      <?php
      if ($_GET['p'] !== 'show_detail') {
        if ($_GET['p'] === 'librarian') {
          echo '<div class="flex flex-row flex-wrap">' . $main_content . '</div>';
        } else {
          echo $main_content;
        }
      } else {
        echo $main_content;
      }
      ?>
    </section>
</div>
