<?php
/**
 * Kutubkhanah: tabbed settings (General, Display, Lookup).
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Settings;

require __DIR__ . '/kk.inc.php';
if (!$kk_can_write) kk_deny();

$s = Settings::all();
$d = Settings::defaults();
$tabs = ['library' => __('Library'), 'general' => __('Shelf'), 'display' => __('Display'), 'lookup' => __('Lookup'), 'blog' => __('Journal'), 'security' => __('Sign-in')];

$languages = [];
$lq = $dbs->query('SELECT language_id, language_name FROM mst_language ORDER BY language_name');
while ($l = $lq->fetch_assoc()) $languages[$l['language_id']] = $l['language_name'];

function kk_check(string $key, string $label, array $s): string
{
    return '<label class="kk-check"><input type="checkbox" name="' . $key . '" value="1"' . (!empty($s[$key]) ? ' checked' : '') . '> ' . kk_e($label) . '</label>';
}

function kk_row(string $label, string $field, string $help = ''): void
{
    echo '<div class="kk-row"><div class="kk-row__label">' . kk_e($label) . '</div><div class="kk-row__field">' . $field
        . ($help ? '<p class="kk-note">' . kk_e($help) . '</p>' : '') . '</div></div>';
}

echo kk_head();
?>
<div class="kk">
  <header class="kk-head">
    <h2><?= __('Kutubkhanah settings') ?></h2>
  </header>

  <div class="kk-tabs" role="tablist" aria-label="<?= __('Settings sections') ?>">
    <?php $first = true; foreach ($tabs as $key => $label): ?>
      <button type="button" role="tab" class="kk-tab<?= $first ? ' is-active' : '' ?>" id="kk-tab-<?= $key ?>"
              aria-controls="kk-pane-<?= $key ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-tab="<?= $key ?>"><?= kk_e($label) ?></button>
    <?php $first = false; endforeach; ?>
  </div>

  <!-- Library: identity and public pages (from the Private Library design) -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-library" role="tabpanel" aria-labelledby="kk-tab-library" data-tab="library">
    <?php
    kk_row(__('Tagline'), '<input type="text" name="tagline" value="' . kk_e($s['tagline']) . '" class="kk-input">',
        __('Shown under the library name, e.g. "Est. 2014 · Private Collection". The name itself is set in System > System Configuration.'));
    kk_row(__('Mission statement'), '<textarea name="mission" class="kk-input kk-textarea" rows="3">' . kk_e($s['mission']) . '</textarea>',
        __('Shown in the home page hero and the mission banner. Leave empty to hide both.'));
    kk_row(__('Footer description'), '<textarea name="footer_desc" class="kk-input kk-textarea" rows="3">' . kk_e($s['footer_desc']) . '</textarea>');
    kk_row(__('Location or hours'), '<input type="text" name="location" value="' . kk_e($s['location']) . '" class="kk-input">');
    $layouts = ['library' => __('Library: hero, stats, recent additions, genres, resources'), 'shelf' => __('Cover shelf (A–Z grid)'), 'classic' => __('Classic home page')];
    $sel = '<select name="home_layout" class="kk-input">';
    foreach ($layouts as $v => $l) $sel .= '<option value="' . $v . '"' . ($s['home_layout'] === $v ? ' selected' : '') . '>' . kk_e($l) . '</option>';
    kk_row(__('Home page'), $sel . '</select>');
    kk_row(__('Recent additions'), '<input type="number" min="0" max="30" name="recent_count" value="' . (int)$s['recent_count'] . '" class="kk-input kk-input--short"> ' . __('books'),
        __('How many of the newest books the home page shows. 0 hides the section.'));
    kk_row(__('Public forms'),
        kk_check('enable_access', __('Request Access page'), $s) . kk_check('enable_contact', __('Contact page'), $s),
        __('Submissions arrive in Kutubkhanah > Inbox.'));
    kk_row(__('Notify email'), '<input type="email" name="notify_email" value="' . kk_e($s['notify_email']) . '" class="kk-input">',
        __('Optional. Also email new submissions here. Needs mail set up in System > Mail Setting.'));
    kk_row(__('Reading status'), kk_check('show_reading', __('Show Unread / Reading / Read and reading progress to visitors'), $s));
    kk_row(__('Ratings'), kk_check('show_ratings', __('Show your star ratings to visitors (reviews are public only when you tick it on the book)'), $s));
    kk_row(__('Wishlist'), kk_check('enable_wishlist', __('Show the public Wishlist page (only wishes marked public)'), $s));
    kk_row(__('RSS feed'), kk_check('enable_rss', __('Publish a "New additions" RSS feed'), $s));
    kk_row(__('Lending period'), '<input type="number" min="0" max="365" name="loan_days" value="' . (int)$s['loan_days'] . '" class="kk-input kk-input--short"> ' . __('days'),
        __('Default due date when you lend a book. 0 means no due date.'));
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Shelf -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-general" role="tabpanel" aria-labelledby="kk-tab-general" data-tab="general" hidden>
    <?php
    kk_row(__('Shelf heading'), '<input type="text" name="shelf_title" value="' . kk_e($s['shelf_title']) . '" class="kk-input">',
        __('Optional heading above the cover shelf.'));
    kk_row(__('Public catalogue'),
        kk_check('show_authors', __('Show authors under titles'), $s)
        . kk_check('group_letters', __('Group books under A–Z headings'), $s)
        . kk_check('show_search', __('Show the search box'), $s)
        . kk_check('show_status', __('Show the reading-status filter'), $s),
        __('Books that still need details are always hidden from the public catalogue.'));
    $opts = '';
    foreach ($languages as $id => $name) $opts .= '<option value="' . kk_e($id) . '"' . ($s['default_language'] === $id ? ' selected' : '') . '>' . kk_e($name) . '</option>';
    kk_row(__('Default language'), '<select name="default_language" class="kk-input">' . $opts . '</select>',
        __('Language given to books added by scanning or import.'));
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Display -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-display" role="tabpanel" aria-labelledby="kk-tab-display" data-tab="display" hidden>
    <?php
    $schemes = ['auto' => __('Automatic (follow the visitor’s device)'), 'light' => __('Always light'), 'dark' => __('Always dark')];
    $sel = '<select name="scheme" class="kk-input">';
    foreach ($schemes as $v => $l) $sel .= '<option value="' . $v . '"' . ($s['scheme'] === $v ? ' selected' : '') . '>' . kk_e($l) . '</option>';
    kk_row(__('Colour scheme'), $sel . '</select>');
    kk_row(__('Cover size'), '<input type="number" min="110" max="260" step="5" name="columns_min" value="' . (int)$s['columns_min'] . '" class="kk-input kk-input--short"> px',
        __('Minimum card width. Columns adjust to the screen.'));
    kk_row(__('Corner radius'), '<input type="number" min="0" max="24" name="radius" value="' . (int)$s['radius'] . '" class="kk-input kk-input--short"> px');
    $names = ['primary' => __('Primary'), 'accent' => __('Accent'), 'bg' => __('Background'), 'surface' => __('Surface'), 'text' => __('Text'), 'muted' => __('Muted text')];
    foreach (['light' => __('Light palette'), 'dark' => __('Dark palette')] as $mode => $title) {
        $html = '<div class="kk-swatches">';
        foreach ($names as $k => $l) {
            $key = $mode . '_' . $k;
            $html .= '<label class="kk-swatch"><span>' . kk_e($l) . '</span><input type="color" name="' . $key . '" value="' . kk_e($s[$key]) . '" data-default="' . kk_e($d[$key]) . '"></label>';
        }
        $html .= '</div><button type="button" class="kk-link kk-reset" data-mode="' . $mode . '">' . __('Reset to default') . '</button>';
        kk_row($title, $html);
    }
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Journal (blog) -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-blog" role="tabpanel" aria-labelledby="kk-tab-blog" data-tab="blog" hidden>
    <?php
    kk_row(__('Journal'), kk_check('blog_enable', __('Publish the journal (blog) on the public site'), $s));
    kk_row(__('Name'), '<input type="text" name="blog_title" value="' . kk_e($s['blog_title']) . '" class="kk-input">', __('Shown in the menu and as the page title, e.g. "Journal", "Blog" or "Catatan".'));
    kk_row(__('Introduction'), '<textarea name="blog_intro" class="kk-input kk-textarea" rows="2">' . kk_e($s['blog_intro']) . '</textarea>');
    kk_row(__('Posts per page'), '<input type="number" min="3" max="50" name="blog_per_page" value="' . (int)$s['blog_per_page'] . '" class="kk-input kk-input--short">');
    kk_row(__('On the home page'), '<input type="number" min="0" max="12" name="blog_home_count" value="' . (int)$s['blog_home_count'] . '" class="kk-input kk-input--short"> ' . __('latest posts'), __('0 hides the section.'));
    kk_row(__('Comments'), kk_check('blog_comments', __('Allow comments on posts'), $s) . kk_check('blog_moderate', __('Hold every comment until I approve it'), $s)
        . kk_check('blog_notify', __('Email me about new comments (sent to the Notify email on the Library tab)'), $s));
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Sign-in security (lockout from OpenBiblio) -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-security" role="tabpanel" aria-labelledby="kk-tab-security" data-tab="security" hidden>
    <?php
    kk_row(__('Lockout'), kk_check('lockout_enable', __('Lock sign-in after repeated wrong passwords'), $s),
        __('Applies to the staff login and the member area. Wrong attempts are counted per username and address; an address that tries many usernames is locked too.'));
    kk_row(__('Attempts allowed'), '<input type="number" min="3" max="20" name="lockout_attempts" value="' . (int)$s['lockout_attempts'] . '" class="kk-input kk-input--short">');
    kk_row(__('Lockout time'), '<input type="number" min="1" max="1440" name="lockout_minutes" value="' . (int)$s['lockout_minutes'] . '" class="kk-input kk-input--short"> ' . __('minutes'));
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Lookup -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-lookup" role="tabpanel" aria-labelledby="kk-tab-lookup" data-tab="lookup" hidden>
    <?php
    $srcHelp = [
        'openlibrary' => __('Free and open (Internet Archive). Descriptions, subjects, Dewey and LC classes, covers.'),
        'loc'         => __('The US national library catalogue. Excellent records for English and many Arabic titles.'),
        'google'      => __('Good descriptions and covers, especially for recent books.'),
        'k10plus'     => __('Union catalogue of about 200 German and international libraries, strong on academic and European titles.'),
        'custom'      => __('Any library catalogue with an SRU search that returns MARCXML, for example a national library.'),
    ];
    $checks = '';
    foreach (\SLiMS\Kutubkhanah\Lookup::sources() as $key => $label) {
        $checks .= kk_check('src_' . $key, $label, $s) . '<p class="kk-note kk-note--tight">' . kk_e($srcHelp[$key]) . '</p>';
    }
    kk_row(__('Book databases'), $checks,
        __('Every ticked source is asked at the same time. The first in this list that knows a detail wins, and later sources only fill what is still missing.'));
    kk_row(__('Custom SRU catalogue'),
        '<input type="url" name="sru_custom_url" placeholder="https://example.org/sru" value="' . kk_e($s['sru_custom_url']) . '" class="kk-input">'
        . '<label class="kk-note" for="kk-sru-index">' . __('ISBN index name') . '</label>'
        . '<input type="text" id="kk-sru-index" name="sru_custom_index" value="' . kk_e($s['sru_custom_index']) . '" class="kk-input kk-input--mid">',
        __('The base address of the catalogue’s SRU service and its ISBN index (often "isbn", "bath.isbn" or "dc.identifier"). Tick "Custom SRU catalogue" above to use it.'));
    kk_row(__('Google Books API key'), '<input type="text" name="google_key" autocomplete="off" value="' . kk_e($s['google_key']) . '" class="kk-input">',
        __('Optional. A free key avoids Google’s anonymous rate limit during large cover runs.'));
    kk_row(__('Covers'), kk_check('download_covers', __('Download a cover when a book is scanned'), $s));
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>
</div>
<?= kk_script('admin.js') ?>
