<?php
# @Author: Waris Agung Widodo <user>
# @Date:   2018-01-23T11:26:05+07:00
# @Email:  ido.alit@gmail.com
# @Filename: footer.php
# @Last modified by:   user
# @Last modified time: 2018-01-23T11:26:47+07:00
?>


<?php
$kk = \SLiMS\Kutubkhanah\Settings::all();
$kkE = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<footer class="kk-footer">
    <div class="kk-footer__bar" aria-hidden="true"></div>
    <div class="container">
        <div class="kk-footer__grid">
            <div>
                <div class="kk-footer__brand">
                    <img class="kk-footer__mark" src="<?= SWB; ?>images/kutubkhanah/kk-mark-outline.svg" alt="" width="52" height="52">
                    <p class="kk-footer__name"><?= $kkE($sysconf['library_name']); ?></p>
                </div>
                <?php if ($kk['tagline'] !== '') : ?><p class="kk-footer__tagline"><?= $kkE($kk['tagline']); ?></p><?php endif; ?>
                <?php if ($kk['footer_desc'] !== '') : ?><p class="kk-footer__desc"><?= $kkE($kk['footer_desc']); ?></p><?php endif; ?>
                <?php if ($kk['location'] !== '') : ?><p class="kk-footer__desc"><?= $kkE($kk['location']); ?></p><?php endif; ?>
            </div>
            <div>
                <p class="kk-footer__heading"><?= __('Catalogue'); ?></p>
                <ul class="kk-footer__links">
                    <li><a href="index.php?p=shelf"><?= __('Browse All Books'); ?></a></li>
                    <li><a href="index.php?p=shelf&amp;format=Digital+Archive"><?= __('Digital Archives'); ?></a></li>
                    <li><a href="index.php?p=series"><?= __('Series'); ?></a></li>
                    <?php if (!empty($kk['enable_lists'])) : ?><li><a href="index.php?p=lists"><?= __('Reading lists'); ?></a></li><?php endif; ?>
                    <?php if (!empty($kk['enable_inside'])) : ?><li><a href="index.php?p=inside"><?= __('Search inside books'); ?></a></li><?php endif; ?>
                    <?php if (!empty($kk['enable_wishlist'])) : ?><li><a href="index.php?p=wishlist"><?= __('Wishlist'); ?></a></li><?php endif; ?>
                    <li><a href="index.php?p=news"><?= __('Resources & Guides'); ?></a></li>
                    <?php if (!empty($kk['enable_rss'])) : ?><li><a href="index.php?p=kk-rss"><?= __('New additions (RSS)'); ?></a></li><?php endif; ?>
                </ul>
            </div>
            <div>
                <p class="kk-footer__heading"><?= __('Information'); ?></p>
                <ul class="kk-footer__links">
                    <li><a href="index.php?p=libinfo"><?= __('About the Library'); ?></a></li>
                    <li><a href="index.php?p=faq"><?= __('FAQ'); ?></a></li>
                    <li><a href="index.php?p=help"><?= __('Help'); ?></a></li>
                </ul>
            </div>
            <div>
                <p class="kk-footer__heading"><?= __('Access'); ?></p>
                <ul class="kk-footer__links">
                    <?php if (!empty($kk['enable_access'])) : ?><li><a href="index.php?p=request-access"><?= __('Request Access'); ?></a></li><?php endif; ?>
                    <?php if (!empty($kk['enable_contact'])) : ?><li><a href="index.php?p=contact"><?= __('Contact'); ?></a></li><?php endif; ?>
                    <li><a href="index.php?p=member"><?= __('Member Area'); ?></a></li>
                    <li><a href="index.php?p=login"><?= __('Librarian Login'); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="kk-footer__bottom">
            <span>&copy; <?= date('Y'); ?> <?= $kkE($sysconf['library_name']); ?>. <?= __('All rights reserved.'); ?></span>
            <span><?= __('Powered by'); ?> <a href="https://github.com/menj/kutubkhanah" target="_blank" rel="noopener">Kutubkhanah</a></span>
        </div>
    </div>
</footer>

<?php if ($sysconf['chat_system']['enabled'] && $sysconf['chat_system']['opac']) : ?>
    <div id="show-pchat2" class="shadow rounded kk-chat-button">
        <button title="Chat" class="btn btn-primary"><i class="fas fa-comments mr-2"></i><?= __('Chat'); ?></button>
    </div>
<?php endif; ?>

<?php
// Chat Engine
include LIB . "contents/chat.php"; ?>

<!-- // Load modal -->
<?php include "_modal_topic.php"; ?>
<?php include "_modal_advanced.php"; ?>
<?php include "_modal_social_media.php"; ?>

<!-- // Load highlight -->
<script src="<?= JWB; ?>highlight.js"></script>
<?php if(isset($engine) && $searchableInJsArray = $this->generateKeywords($engine->searchable_fields)) : ?>
<script>
  $('.card-body > *').highlight(<?= $searchableInJsArray ?>);
</script>
<?php endif; ?>

<!-- // load our vue app.js -->
<script src="<?php echo assets(v('js/app.js')); ?>"></script>
<script src="<?php echo assets(v('js/app_jquery.js')); ?>"></script>
<?php if (\SLiMS\Kutubkhanah\Settings::get('home_layout') === 'classic') include __DIR__ . "/../assets/js/vegas.js.php"; ?>
<script src="<?= SWB; ?>js/kutubkhanah/theme.js?v=1.10.0"></script>
<?php if ($sysconf['chat_system']['enabled'] && $sysconf['chat_system']['opac']) : ?>
    <script>
        $('#show-pchat').click(() => {
            $('.s-chat').hide()
            $('#show-pchat2').show()
        })
        $('#show-pchat2').click(() => {
            $('.s-chat').show(300, () => {
                $('#show-pchat2').hide()
            })
        })
    </script>
<?php endif; ?>
<?php echo \SLiMS\Kutubkhanah\Theme::footer(); \SLiMS\Kutubkhanah\Hooks::action('kk_footer'); ?>
</body>
</html>
