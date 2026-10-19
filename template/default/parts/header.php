<!--
# ===============================
# Kutubkhanah OPAC template
# ===============================
# @Author: Waris Agung Widodo
# @Email:  ido.alit@gmail.com
# @Date:   2018-01-23T11:25:57+07:00
# @Last modified by:   Waris Agung Widodo
# @Last modified time: 2019-01-03T11:25:57+07:00
-->
<?php
// clean request uri from xss
$request_uri = urlencode(strip_tags(urldecode($_SERVER['REQUEST_URI'])));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $page_title; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<?php if (!empty($sysconf['kk_discourage_search'])) echo '    <meta name="robots" content="noindex, nofollow">' . "\n"; ?>

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0"/>
    <meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT"/>
    <?php echo $metadata;?>
    <?php if (isset($_GET['p']) && ($_GET['p'] == 'show_detail')): ?>
        <meta name="description" content="<?php echo substr($notes, 0, 152) . '...'; ?>">
        <meta name="keywords" content="<?php echo $subject; ?>">
    <?php else: ?>
        <meta name="description" content="<?php echo $page_title; ?>">
        <meta name="keywords" content="<?php echo $sysconf['library_subname']; ?>">
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1">
    <meta name="generator" content="<?php echo SENAYAN_VERSION ?>">
    <meta name="theme-color" content="<?php echo htmlspecialchars(\SLiMS\Kutubkhanah\Settings::get('light_primary'), ENT_QUOTES); ?>">

    <meta property="og:locale" content="<?php echo str_replace('-', '_', $sysconf['default_lang']); ?>"/>
    <meta property="og:type" content="book"/>
    <meta property="og:title" content="<?php echo $page_title; ?>"/>
    <?php if (isset($_GET['p']) && ($_GET['p'] == 'show_detail')): ?>
        <meta property="og:description" content="<?php echo substr($notes, 0, 152) . '...'; ?>"/>
    <?php else: ?>
        <meta property="og:description" content="<?php echo $sysconf['library_subname']; ?>"/>
    <?php endif; ?>
    <meta property="og:url" content="//<?php echo $_SERVER["SERVER_NAME"] . $request_uri; ?>"/>
    <meta property="og:site_name" content="<?php echo $sysconf['library_name']; ?>"/>
    <?php if (isset($_GET['p']) && ($_GET['p'] == 'show_detail')): ?>
        <meta property="og:image" content="//<?php echo $_SERVER["SERVER_NAME"] . SWB . $image_src ?>"/>
    <?php else: ?>
        <meta property="og:image" content="<?php echo \SLiMS\Kutubkhanah\Library::shareImage(); ?>"/>
        <meta property="og:image:width" content="1200"/><meta property="og:image:height" content="630"/>
    <?php endif; ?>

    <meta name="twitter:card" content="summary">
    <meta name="twitter:url" content="//<?php echo $_SERVER["SERVER_NAME"] . $request_uri; ?>"/>
    <meta name="twitter:title" content="<?php echo $page_title; ?>"/>
    <?php if (isset($_GET['p']) && ($_GET['p'] == 'show_detail')): ?>
        <meta property="twitter:image" content="//<?php echo $_SERVER["SERVER_NAME"] . SWB . $image_src ?>"/>
    <?php else: ?>
        <meta property="twitter:image" content="<?php echo \SLiMS\Kutubkhanah\Library::shareImage(); ?>"/>
    <?php endif; ?>
    <!-- // load bootstrap style -->
    <link rel="stylesheet" href="<?php echo assets('css/bootstrap.min.css'); ?>">
    <!-- // font awesome -->
    <link rel="stylesheet" href="<?php echo assets('plugin/font-awesome/css/fontawesome-all.min.css'); ?>">
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo assets('css/tailwind.min.css'); ?>">
    <!-- Vegas CSS -->
    <link rel="stylesheet" href="<?php echo assets('plugin/vegas/vegas.min.css'); ?>">
    <link href="<?php echo JWB; ?>toastr/toastr.min.css?<?php echo date('this') ?>" rel="stylesheet" type="text/css"/>
    <!-- CKEditor5 CSS -->
    <link rel="stylesheet" href="<?= JWB; ?>ckeditor5/ckeditor5.css">
    <!-- SLiMS CSS -->
    <link rel="stylesheet" href="<?= JWB; ?>colorbox/colorbox.css">
    <link rel="stylesheet" href="<?= JWB; ?>ion.rangeSlider/css/ion.rangeSlider.min.css">
    <!-- // Flag css -->
    <link rel="stylesheet" href="<?php echo assets('css/flag-icon.min.css'); ?>">
    <!-- // my custom style -->
    <link rel="stylesheet" href="<?php echo assets('css/style.css?v=' . date('Ymd-his')); ?>">
    <!-- // Kutubkhanah theme (navy and gold, from the Private Library design); colours from Kutubkhanah > Settings > Display -->
    <link rel="preload" href="<?php echo SWB; ?>fonts/kutubkhanah/EBGaramond-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo SWB; ?>css/kutubkhanah/fonts.css?v=1.5.0">
    <?php echo \SLiMS\Kutubkhanah\Library::privateFontFaces(); ?>
    <link rel="stylesheet" href="<?php echo SWB; ?>css/kutubkhanah/theme.css?v=1.5.0">
    <style><?php echo \SLiMS\Kutubkhanah\Settings::cssVars('body.kk-theme'); ?></style>

    <?php // Kutubkhanah: KK favicon set (images/kutubkhanah/) unless a custom web icon is set in System settings ?>
    <?php echo \SLiMS\Kutubkhanah\Library::iconLinks(); ?>
    <?php if (\SLiMS\Kutubkhanah\Settings::get('enable_rss')): ?><link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars(($sysconf['library_name'] ?? 'Kutubkhanah') . ' · ' . __('New additions'), ENT_QUOTES) ?>" href="index.php?p=kk_rss"><?php endif; ?>

    <!-- // load vue js -->
    <script src="<?php echo assets('js/vue.min.js'); ?>"></script>
    <!-- // load jquery library -->
    <script src="<?php echo assets('js/jquery.min.js'); ?>"></script>
    <script src="<?php echo assets('js/masonry.pkgd.min.js'); ?>"></script>
    <!-- // load bootstrap javascript -->
    <script src="<?php echo assets('js/bootstrap.bundle.min.js'); ?>"></script>
    <!-- // load vegas javascript -->
    <script src="<?php echo assets('plugin/vegas/vegas.min.js'); ?>"></script>
    <script src="<?php echo JWB; ?>toastr/toastr.min.js"></script>
    <!-- // load SLiMS javascript -->
    <script src="<?php echo JWB; ?>colorbox/jquery.colorbox-min.js"></script>
    <script src="<?php echo JWB . v('gui.js'); ?>"></script>
    <script src="<?php echo JWB; ?>fancywebsocket.js"></script>
    <script src="<?php echo JWB; ?>ion.rangeSlider/js/ion.rangeSlider.min.js"></script>
    <?php
    if (isset($js)):
        echo $js;
    endif;
    ?>

</head>
<body class="bg-grey-lightest kk-theme" data-scheme="<?php echo htmlspecialchars(\SLiMS\Kutubkhanah\Settings::get('scheme'), ENT_QUOTES); ?>">
