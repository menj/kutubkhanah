<?php
/**
 * Kutubkhanah home page in the Private Library design:
 * hero with mission and search, stats strip, recent additions, browse by genre,
 * latest resources and the mission banner.
 * GNU GPL v3 or later.
 */

use SLiMS\Kutubkhanah\Library;
use SLiMS\Kutubkhanah\Settings;

$kk = Settings::all();
$kkStats = Library::stats();
$kkRecent = (int)$kk['recent_count'] > 0 ? Library::books('1=1', [], 'b.input_date DESC, b.biblio_id DESC', (int)$kk['recent_count']) : [];
$kkGenres = Library::genres(16);
$kkAuthors = Library::authorMosaics(3);
$kkNews = Library::news(3);
$e = [Library::class, 'e'];
?>
<header class="kk-header">
    <?php include '_navbar.php'; ?>
</header>

<main id="kk-main" class="kk-home">
    <section class="kk-hero">
        <div class="kk-hero__pattern" aria-hidden="true"></div>
        <div class="container kk-hero__content">
            <p class="kk-eyebrow"><?= __('Official Catalogue'); ?></p>
            <div class="kk-rule" aria-hidden="true"></div>
            <h1 class="kk-hero__title"><?= $e($sysconf['library_name']); ?></h1>
            <?php if ($kk['mission'] !== '') : ?>
                <p class="kk-hero__tagline">&ldquo;<?= $e($kk['mission']); ?>&rdquo;</p>
            <?php endif; ?>
            <form class="kk-hero__search" role="search" action="index.php" method="get">
                <input type="hidden" name="search" value="search">
                <svg class="kk-hero__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <label class="kk-sr" for="kk-hero-q"><?= __('Search the library'); ?></label>
                <select class="kk-hero__by" id="kk-hero-by" aria-label="<?= __('Search by'); ?>">
                    <option value="keywords"><?= __('All'); ?></option>
                    <option value="title"><?= __('Title'); ?></option>
                    <option value="author"><?= __('Author'); ?></option>
                    <option value="subject"><?= __('Subject'); ?></option>
                </select>
                <input id="kk-hero-q" type="search" name="keywords" placeholder="<?= __('Search title, author or subject'); ?>" autocomplete="off">
                <button type="submit" class="kk-btn kk-btn--primary"><?= __('Search'); ?></button>
            </form>
            <details class="kk-adv">
                <summary><?= __('Advanced search'); ?></summary>
                <form class="kk-adv__form" action="index.php" method="get">
                    <input type="hidden" name="p" value="shelf">
                    <label><span><?= __('Subject'); ?></span><select name="genre"><option value=""><?= __('Any'); ?></option>
                        <?php foreach (Library::genres(60) as $g) : ?><option value="<?= (int)$g['topic_id']; ?>"><?= $e($g['topic']); ?></option><?php endforeach; ?></select></label>
                    <label><span><?= __('Format'); ?></span><select name="format"><option value=""><?= __('Any'); ?></option>
                        <?php foreach (Settings::formats() as $f) : ?><option value="<?= $e($f); ?>"><?= $e($f); ?></option><?php endforeach; ?></select></label>
                    <label><span><?= __('Language'); ?></span><select name="lang"><option value=""><?= __('Any'); ?></option>
                        <?php foreach (Library::languages() as $l) : ?><option value="<?= $e($l['language_id']); ?>"><?= $e($l['language_name']); ?></option><?php endforeach; ?></select></label>
                    <button type="submit" class="kk-btn kk-btn--gold kk-btn--sm"><?= __('Show books'); ?></button>
                </form>
            </details>
        </div>
    </section>

    <div class="kk-stats">
        <div class="container kk-stats__inner">
            <div class="kk-stat"><span class="kk-stat__num"><?= number_format($kkStats['books']); ?></span><span class="kk-stat__label"><?= __('Volumes Catalogued'); ?></span></div>
            <div class="kk-stat"><span class="kk-stat__num"><?= number_format($kkStats['authors']); ?></span><span class="kk-stat__label"><?= __('Authors'); ?></span></div>
            <div class="kk-stat"><span class="kk-stat__num"><?= $kkStats['digital'] ? number_format($kkStats['digital']) : '—'; ?></span><span class="kk-stat__label"><?= __('Digital Archives'); ?></span></div>
            <div class="kk-stat"><span class="kk-stat__num"><?= $kkStats['genres'] ? number_format($kkStats['genres']) : '—'; ?></span><span class="kk-stat__label"><?= __('Genres'); ?></span></div>
        </div>
    </div>

    <section class="kk-section kk-section--band">
        <div class="container">
            <span class="kk-label kk-center"><?= __('What the shelves hold'); ?></span>
            <h2 class="kk-section__title kk-center"><?= __('Browse by Genre'); ?></h2>
            <?php if ($kkGenres) : ?>
                <div class="kk-chips">
                    <?php
                    $kkMax = max(1, (int)max(array_column($kkGenres, 'n'))); $kkMin = (int)min(array_column($kkGenres, 'n'));
                    foreach ($kkGenres as $g) :
                        $w = $kkMax === $kkMin ? 2 : 1 + (int)round(3 * ((int)$g['n'] - $kkMin) / ($kkMax - $kkMin));
                    ?>
                        <a class="kk-badge kk-badge--gold kk-chip kk-chip--w<?= $w; ?>" href="index.php?p=shelf&amp;genre=<?= (int)$g['topic_id']; ?>"><?= $e($g['topic']); ?> <span class="kk-chip__n">(<?= (int)$g['n']; ?>)</span></a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="kk-muted kk-center"><?= __('No genres catalogued yet. Add subjects to books in Bibliography to fill this section.'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($kkRecent) : ?>
    <section class="kk-section">
        <div class="container">
            <div class="kk-section__head">
                <div>
                    <span class="kk-label"><?= __('Newly arrived'); ?></span>
                    <h2 class="kk-section__title"><?= __('Recent Additions'); ?></h2>
                </div>
                <a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=shelf"><?= __('Browse Full Catalogue'); ?> &rarr;</a>
            </div>
            <?= Library::grid($kkRecent, 'kk-grid kk-grid--5'); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($kkAuthors) : ?>
    <section class="kk-section kk-section--tight">
        <div class="container">
            <div class="kk-section__head">
                <div>
                    <span class="kk-label"><?= __('The voices behind the books'); ?></span>
                    <h2 class="kk-section__title"><?= __('By Author'); ?></h2>
                </div>
                <a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=shelf"><?= __('View All'); ?> &rarr;</a>
            </div>
            <div class="kk-grid kk-grid--3">
                <?php foreach ($kkAuthors as $a) echo Library::authorMosaic($a); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?= \SLiMS\Kutubkhanah\Blog\Render::homeSection(); ?>

    <?php if ($kkNews) : ?>
    <section class="kk-section">
        <div class="container">
            <div class="kk-section__head">
                <div>
                    <span class="kk-label"><?= __('Library Notes'); ?></span>
                    <h2 class="kk-section__title"><?= __('Latest Resources'); ?></h2>
                </div>
                <a class="kk-btn kk-btn--outline kk-btn--sm" href="index.php?p=news"><?= __('View All'); ?> &rarr;</a>
            </div>
            <div class="kk-grid kk-grid--3">
                <?php foreach ($kkNews as $n) : ?>
                    <article class="kk-resource">
                        <h3 class="kk-resource__title"><a href="index.php?p=<?= rawurlencode($n['content_path']); ?>"><?= $e($n['content_title']); ?></a></h3>
                        <p class="kk-resource__excerpt"><?= $e(mb_strimwidth(trim(strip_tags($n['content_desc'])), 0, 160, '…')); ?></p>
                        <div class="kk-resource__meta"><?= $e($n['published']); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="kk-section kk-section--visit">
        <div class="container">
            <div class="kk-section__head">
                <div>
                    <span class="kk-label"><?= __('Come and read'); ?></span>
                    <h2 class="kk-section__title"><?= __('Visit and Borrow'); ?></h2>
                </div>
            </div>
            <?php if (trim((string)$kk['hours']) !== '' || trim((string)$kk['lending']) !== '') : ?>
            <div class="kk-info">
                <?php if (trim((string)$kk['hours']) !== '') : ?><div class="kk-info__box"><h3 class="kk-info__title"><?= __('Visiting hours'); ?></h3><p><?= nl2br($e($kk['hours'])); ?></p></div><?php endif; ?>
                <?php if (trim((string)$kk['lending']) !== '') : ?><div class="kk-info__box"><h3 class="kk-info__title"><?= __('Lending'); ?></h3><p><?= nl2br($e($kk['lending'])); ?></p></div><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="kk-cta">
                <?php if (!empty($kk['enable_borrow'])) : ?><a class="kk-btn kk-btn--primary" href="index.php?p=my-list"><?= __('Your saved books'); ?></a><?php endif; ?>
                <?php if (!empty($kk['enable_access'])) : ?><a class="kk-btn kk-btn--outline" href="index.php?p=request-access"><?= __('Request access'); ?></a><?php endif; ?>
                <?php if (!empty($kk['enable_suggest'])) : ?><a class="kk-btn kk-btn--outline" href="index.php?p=suggest"><?= __('Suggest a book'); ?></a><?php endif; ?>
                <?php if (!empty($kk['enable_contact'])) : ?><a class="kk-btn kk-btn--ghost" href="index.php?p=contact"><?= __('Contact'); ?></a><?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($kk['mission'] !== '') : ?>
    <section class="kk-mission">
        <div class="container">
            <span class="kk-label"><?= __('Why this collection exists'); ?></span>
            <blockquote class="kk-mission__quote">&ldquo;<?= $e($kk['mission']); ?>&rdquo;</blockquote>
            <p class="kk-mission__by">&mdash; <?= $e($sysconf['library_name']); ?></p>
        </div>
    </section>
    <?php endif; ?>
</main>
