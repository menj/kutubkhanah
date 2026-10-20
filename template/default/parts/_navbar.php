<?php
# @Author: Waris Agung Widodo <user>
# @Date:   2018-01-25T10:25:29+07:00
# @Email:  ido.alit@gmail.com
# @Filename: _navbar.php
# @Last modified by:   user
# @Last modified time: 2018-01-25T10:29:27+07:00

$kk = \SLiMS\Kutubkhanah\Settings::all();
$main_menus = [
  'home' => ['text' => __('Home'), 'url' => 'index.php'],
  'shelf' => ['text' => __('Catalogue'), 'url' => 'index.php?p=shelf'],
  'archives' => ['text' => __('Digital Archives'), 'url' => 'index.php?p=shelf&format=Digital+Archive'],
  'news' => ['text' => __('Resources'), 'url' => 'index.php?p=news'],
];
if (!empty($kk['blog_enable'])) $main_menus = array_slice($main_menus, 0, 3, true) + ['blog' => ['text' => htmlspecialchars($kk['blog_title'] ?: __('Journal'), ENT_QUOTES, 'UTF-8'), 'url' => 'index.php?p=blog']] + array_slice($main_menus, 3, null, true);
$main_menus += [
  'faq' => ['text' => __('FAQ'), 'url' => 'index.php?p=faq'],
];
if (!empty($kk['enable_contact'])) $main_menus['contact'] = ['text' => __('Contact'), 'url' => 'index.php?p=contact'];
if (!empty($kk['enable_access'])) $main_menus['request-access'] = ['text' => __('Request Access'), 'url' => 'index.php?p=request-access'];
?>
<nav class="navbar navbar-expand-lg navbar-light kk-nav" aria-label="<?= __('Main navigation'); ?>">
    <a class="navbar-brand kk-brand" href="index.php">
        <?php
        if(isset($sysconf['logo_image']) && $sysconf['logo_image'] != '' && $imagesDisk->isExists($path = 'default/'.$sysconf['logo_image'])){
            echo '<img class="h-10 w-15" src="'.SWB . 'lib/minigalnano/createthumb.php?filename=images/' . $path.'&width=350">';
        }
        elseif (file_exists(SB . 'images/kutubkhanah/kk-mark.svg')) {
            // KK brand mark: navy arch on light backgrounds, gold outline in dark mode (switched in theme.css).
            echo '<img class="kk-brand-logo kk-brand-logo--light" src="'.SWB.'images/kutubkhanah/kk-mark.svg" alt="" width="44" height="44">';
            echo '<img class="kk-brand-logo kk-brand-logo--dark" src="'.SWB.'images/kutubkhanah/kk-mark-outline.svg" alt="" width="44" height="44">';
        } else {
        ?>
        <span class="kk-brand-logo kk-brand-monogram" aria-hidden="true">KK</span>
        <?php } ?>
        <div class="kk-brand-text">
            <span class="kk-brand-name"><?php echo $sysconf['library_name']; ?></span>
            <?php if ($kk['tagline'] !== '') : ?>
            <span class="kk-brand-sub"><span class="kk-brand-dot" aria-hidden="true"></span><span class="kk-brand-tagline"><?php echo htmlspecialchars($kk['tagline'], ENT_QUOTES); ?></span></span>
            <?php endif; ?>
        </div>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ml-auto">
          <?php
          foreach ($main_menus as $key => $main_menu) {
            $active = '';
            $kkPage = $_GET['p'] ?? '';
            if ($kkPage === 'shelf' && !empty($_GET['format'])) $kkPage = 'archives';
            if ($kkPage === 'show_news') $kkPage = 'news';
            if (isset($_GET['p'])) {
              if ($key === $kkPage) $active = 'active';
            } elseif ($key === 'home' && !isset($_GET['search'])) {
              $active = 'active';
            }
            $aria = $active ? ' aria-current="page"' : '';
            $menu_str = <<<HTML
<li class="nav-item {$active}">
    <a class="nav-link" href="{$main_menu['url']}"{$aria}>{$main_menu['text']}</a>
</li>
HTML;
            echo $menu_str;
          }
          ?>
          <?php if (!empty($kk['enable_borrow'])) : ?>
              <li class="nav-item kk-nav-saved<?= ($_GET['p'] ?? '') === 'my-list' ? ' active' : '' ?>">
                  <a class="nav-link" href="index.php?p=my-list" aria-label="<?= __('Saved books'); ?>">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
                      <span class="kk-nav-saved__n" data-kk-saved-count hidden>0</span>
                  </a>
              </li>
          <?php endif; ?>
          <?php
          $menu_member_active = isset($_GET['p']) && $_GET['p'] === 'member' ? 'active' : '';
          if ($is_login) {
            ?>
              <li class="nav-item <?= $menu_member_active; ?>">
                  <a class="nav-link" href="index.php?p=member&sec=title_basket">
                      <i class="fas fa-shopping-basket"></i>
                    <?php
                    $count_basket = count($_SESSION['m_mark_biblio']);
                    ?>
                      <sup id="count-basket" class="badge badge-danger"><?php echo $count_basket; ?></sup>
                  </a>
              </li>
              <li class="nav-item dropdown <?= $menu_member_active; ?>">
                  <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown"
                     aria-haspopup="true" aria-expanded="false">
                      <img class="w-6 h-6 rounded-full ml-2 mr-2"
                           src="<?php echo $member_image_path; ?>"
                           alt="Avatar of Jonathan Reinink">
                    <?php echo $_SESSION['m_name']; ?>
                  </a>
                  <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item" href="index.php?p=member"><i class="fas fa-user-circle mr-3"></i> <?= __('Profile');?></a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="index.php?p=member&sec=bookmark"><i class="fas fa-bookmark mr-3"></i> <?= __('Bookmark');?></a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="index.php?p=member&logout=1"><i class="fas fa-sign-out-alt mr-3"></i> <?= __('Logout'); ?></a>
                  </div>
              </li>
          <?php } else { ?>
              <li class="nav-item <?= $menu_member_active; ?>">
                  <a class="nav-link" href="index.php?p=member"><?= __('Member Area') ?></a>
              </li>
          <?php } ?>
            <li class="nav-item dropdown">
              <?php
              $langstr = '';
              $current_lang = '';
              $select_lang = isset($_COOKIE['select_lang'])?$_COOKIE['select_lang']:$sysconf['default_lang'];
              // require_once(LANG . 'localisation.php');
              foreach ($available_languages??[] AS $lang_index) {
                $selected = null;
                $lang_code = $lang_index[0];
                $lang_name = $lang_index[1];
                $code_arr = explode('_', $lang_code);
                $code_flag = strtolower($code_arr[1]);
                if ($lang_code == $select_lang) {
                  $current_lang = [
                    'name' => $lang_name,
                    'code' => $code_flag
                  ];
                }
                $langstr .= <<<HTML
    <a class="dropdown-item" href="index.php?select_lang={$lang_code}">
        <span class="flag-icon flag-icon-{$code_flag} mr-2"></span> {$lang_name}
    </a>
HTML;
              }
              ?>
                <a class="nav-link dropdown-toggle cursor-pointer" type="button" id="languageMenuButton"
                   data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="flag-icon flag-icon-<?= $current_lang['code'] ?>"></span>
                </a>
                <div class="dropdown-menu bg-grey-lighter dropdown-menu-lg-right" aria-labelledby="dropdownMenuButton">
                    <h6 class="dropdown-header"><?= __('Select Language'); ?> : </h6>
                  <?= $langstr; ?>
                </div>
            </li>
        </ul>
    </div>
</nav>
