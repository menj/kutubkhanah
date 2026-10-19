<?php
/**
 * Kutubkhanah: home page showing the cover shelf (Settings > General > "Show the cover shelf as the home page").
 * GNU GPL v3 or later.
 */
?>
<div class="kk-home">
<section id="section1 container-fluid">
    <header class="c-header">
        <div class="mask"></div>
        <?php include '_navbar.php'; ?>
    </header>
    <?php include '_search-form.php'; ?>
</section>

<!-- Not id="slims-home": the template mounts Vue there, and Vue strips the shelf's <style> and <script> tags. -->
<div id="kk-shelf-home" class="container py-4">
    <?php echo \SLiMS\Kutubkhanah\Shelf::render(); ?>
</div>
</div>
