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
$tabs = ['library' => __('Library'), 'general' => __('Shelf'), 'display' => __('Display'), 'lookup' => __('Lookup'), 'blog' => __('Journal'), 'ai' => __('AI files'), 'security' => __('Sign-in')];

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
    kk_row(__('Reading lists'), kk_check('enable_lists', __('Show the public Reading lists page'), $s));
    kk_row(__('Search inside books'), kk_check('enable_inside', __('Show the public page that searches the text of digital books'), $s)
        . '<input type="text" name="ocr_lang" value="' . kk_e($s['ocr_lang']) . '" class="kk-input kk-input--short" aria-label="OCR"> ',
        __('Language codes for reading scanned pages (OCR), such as eng, ara, msa or eng+ara. Install the matching tesseract language data on the server.'));
    $pl = '<select name="permalinks" class="kk-input">'
        . '<option value="pretty"' . ($s['permalinks'] !== 'plain' ? ' selected' : '') . '>' . kk_e(__('Readable: /book/dune')) . '</option>'
        . '<option value="plain"' . ($s['permalinks'] === 'plain' ? ' selected' : '') . '>' . kk_e(__('Plain: index.php?p=show_detail&id=12')) . '</option></select>';
    kk_row(__('Web addresses'), $pl, __('Readable addresses help search engines and people. Old links redirect automatically. Needs URL rewriting on the web server (see docs/install.md); choose Plain if it is not available.'));
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

  <!-- AI discovery files -->
  <form class="kk-panel kk-settings notAJAX" id="kk-pane-ai" role="tabpanel" aria-labelledby="kk-tab-ai" data-tab="ai" hidden>
    <?php
    use SLiMS\Kutubkhanah\AiFiles;
    kk_row(__('AI discovery files'), kk_check('ai_enable', __('Publish llms.txt, ai.txt and the other AI discovery files'), $s) . kk_check('ai_log', __('Count visits from AI crawlers'), $s),
        __('These files tell AI assistants who you are and what they may do with your site. They are generated on request from the settings below and your catalogue; nothing is written to disk.'));
    $fl = '';
    foreach (AiFiles::types() as $slug => $t) {
        $key = 'ai_f_' . str_replace('-', '_', $slug);
        $fl .= kk_check($key, $t[0] . ' · ' . $t[1] . ' (' . $t[2] . ')', $s);
        if (!empty($s['ai_enable']) && !empty($s[$key])) $fl = rtrim($fl) . ' <a class="notAJAX" target="_blank" rel="noopener" href="' . kk_e(AiFiles::url($slug)) . '">' . __('View') . '</a>';
    }
    kk_row(__('Files'), $fl);
    kk_row(__('Name'), '<input type="text" name="ai_name" value="' . kk_e($s['ai_name']) . '" class="kk-input" placeholder="' . kk_e((string)($sysconf['library_name'] ?? '')) . '">', __('Leave empty to use the library name.'));
    kk_row(__('Legal name'), '<input type="text" name="ai_legal_name" value="' . kk_e($s['ai_legal_name']) . '" class="kk-input">');
    kk_row(__('Description'), '<input type="text" name="ai_description" value="' . kk_e($s['ai_description']) . '" class="kk-input">', __('One sentence. Leave empty to use the mission statement.'));
    $ot = '<select name="ai_org_type" class="kk-input">';
    foreach (['Library' => __('Library'), 'Organization' => __('Organisation'), 'LocalBusiness' => __('Local business'), 'Person' => __('Person')] as $v => $l) $ot .= '<option value="' . $v . '"' . ($s['ai_org_type'] === $v ? ' selected' : '') . '>' . kk_e($l) . '</option>';
    kk_row(__('Type'), $ot . '</select>');
    kk_row(__('Contact email'), '<input type="email" name="ai_email" value="' . kk_e($s['ai_email']) . '" class="kk-input">', __('Leave empty to use the Notify email.'));
    kk_row(__('City and country'), '<input type="text" name="ai_city" value="' . kk_e($s['ai_city']) . '" class="kk-input kk-input--short" placeholder="' . kk_e(__('City')) . '"> <input type="text" name="ai_country" value="' . kk_e($s['ai_country']) . '" class="kk-input kk-input--short" placeholder="' . kk_e(__('Country')) . '">');
    foreach (['ai_services' => [__('What you offer'), __('One per line.')], 'ai_not_services' => [__('What you do not do'), __('One per line. Stops assistants inventing services, such as public lending.')],
              'ai_alternates' => [__('Other accepted names'), __('One per line.')], 'ai_misspellings' => [__('Common misspellings'), __('One per line.')], 'ai_never' => [__('Names never to use'), __('One per line.')],
              'ai_voice' => [__('Voice'), __('How the library should be described.')], 'ai_about' => [__('About'), __('A short paragraph for assistants to quote.')],
              'ai_faq' => [__('Questions and answers'), __('One per line as: Question | Answer.')], 'ai_dev_notes' => [__('Technical notes'), __('Optional, for developer-ai.txt.')]] as $k => [$l, $h]) {
        kk_row($l, '<textarea name="' . $k . '" class="kk-input kk-textarea" rows="3">' . kk_e($s[$k]) . '</textarea>', $h);
    }
    $enum = function (string $key, array $opts) use ($s): string {
        $o = '<select name="' . $key . '" class="kk-input">';
        foreach ($opts as $v => $l) $o .= '<option value="' . $v . '"' . ($s[$key] === $v ? ' selected' : '') . '>' . kk_e($l) . '</option>';
        return $o . '</select>';
    };
    kk_row(__('AI may use my content'), $enum('ai_usage', ['allow' => __('Yes'), 'allow_with_attribution' => __('Yes, with attribution'), 'restrict' => __('Only describe and link, no copying'), 'prohibit' => __('No, only point people here')]));
    kk_row(__('AI model training'), $enum('ai_training', ['allow' => __('Allowed'), 'restrict' => __('Only with my permission'), 'prohibit' => __('Not allowed')]));
    kk_row(__('AI crawlers'), $enum('ai_crawlers', ['allow_all' => __('Allow all'), 'allow_known' => __('Allow known crawlers, but not those that train models unless allowed above'), 'restrict' => __('Search engines only')]));
    kk_row(__('Preferred citation'), '<input type="text" name="ai_citation" value="' . kk_e($s['ai_citation']) . '" class="kk-input">');
    kk_row(__('Content licence'), '<input type="text" name="ai_licence" value="' . kk_e($s['ai_licence']) . '" class="kk-input">', __('For example "CC BY-NC 4.0". Optional.'));
    $hits = AiFiles::hits();
    if ($hits) {
        $t = '<table class="kk-table"><thead><tr><th>' . __('Crawler') . '</th><th>' . __('Visits (90 days)') . '</th><th>' . __('Last seen') . '</th></tr></thead><tbody>';
        foreach ($hits as $h) $t .= '<tr><td>' . kk_e($h['bot']) . '</td><td>' . (int)$h['hits'] . '</td><td>' . kk_e($h['last']) . '</td></tr>';
        kk_row(__('Who reads them'), $t . '</tbody></table>');
    }
    ?>
    <div class="kk-actions"><button type="submit" class="kk-btn kk-btn--primary"><?= __('Save changes') ?></button><span class="kk-note kk-saved" aria-live="polite"></span></div>
  </form>

  <!-- Sign-in -->
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
