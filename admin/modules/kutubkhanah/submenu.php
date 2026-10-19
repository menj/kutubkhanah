<?php
/**
 * Kutubkhanah module submenu.
 * GNU GPL v3 or later.
 */

do_checkIP('smc');

$menu['kutubkhanah.header-books'] = array('Header', __('KUTUBKHANAH'));
$menu['kutubkhanah.scan']     = array(__('Scan ISBN'), MWB . 'kutubkhanah/index.php', __('Add books by scanning or typing their ISBN'));
$menu['kutubkhanah.review']   = array(__('Review'), MWB . 'kutubkhanah/review.php', __('Books that need details, a cover or an author'));
$menu['kutubkhanah.data']     = array(__('Import / Export'), MWB . 'kutubkhanah/data.php', __('Import from Libib, export to CSV and fetch covers'));
$menu['kutubkhanah.wishlist'] = array(__('Wishlist'), MWB . 'kutubkhanah/wishlist.php', __('Books you want but do not own yet'));
$menu['kutubkhanah.loans']    = array(__('Lending'), MWB . 'kutubkhanah/loans.php', __('Books lent to friends and family, and what is overdue'));
$menu['kutubkhanah.book']     = array(__('Book Details'), MWB . 'kutubkhanah/book.php', __('Format, links, tags, reading progress and curator notes'));
$menu['kutubkhanah.series']   = array(__('Series'), MWB . 'kutubkhanah/series.php', __('Series totals and missing volumes'));
$menu['kutubkhanah.lists']    = array(__('Reading Lists'), MWB . 'kutubkhanah/lists.php', __('Curated, ordered lists of books with public pages'));
$menu['kutubkhanah.cleanup']  = array(__('Duplicates & Editions'), MWB . 'kutubkhanah/cleanup.php', __('Find and merge duplicate books, and group editions of one work'));
$menu['kutubkhanah.inside']   = array(__('Search Inside'), MWB . 'kutubkhanah/inside.php', __('Index digital books so their text can be searched'));
$menu['kutubkhanah.fields']   = array(__('Custom Fields'), MWB . 'kutubkhanah/fields.php', __('Your own details per format, such as signed copy or condition'));
$menu['kutubkhanah.authors']  = array(__('Authors'), MWB . 'kutubkhanah/authors.php', __('Author details shown on public author pages'));
$menu['kutubkhanah.inbox']    = array(__('Inbox'), MWB . 'kutubkhanah/inbox.php', __('Access requests and contact messages'));
$menu['kutubkhanah.header-blog'] = array('Header', __('JOURNAL'));
$menu['kutubkhanah.posts']    = array(__('Posts'), MWB . 'kutubkhanah/blog.php', __('All journal posts: drafts, scheduled and published'));
$menu['kutubkhanah.newpost']  = array(__('Write a Post'), MWB . 'kutubkhanah/post.php', __('Start a new post'));
$menu['kutubkhanah.pages']    = array(__('Pages'), MWB . 'kutubkhanah/blog.php?type=page', __('Standalone pages'));
$menu['kutubkhanah.terms']    = array(__('Categories & Tags'), MWB . 'kutubkhanah/blog_terms.php', __('Organise posts'));
$menu['kutubkhanah.comments'] = array(__('Comments'), MWB . 'kutubkhanah/blog_comments.php', __('Approve, reply to or remove comments'));
$menu['kutubkhanah.media']    = array(__('Media'), MWB . 'kutubkhanah/blog_media.php', __('Images for posts'));
$menu['kutubkhanah.wpimport'] = array(__('Import from WordPress'), MWB . 'kutubkhanah/blog_import.php', __('Bring posts, pages and comments over from WordPress'));
$menu['kutubkhanah.header-settings'] = array('Header', __('SETTINGS'));
$menu['kutubkhanah.settings'] = array(__('Settings'), MWB . 'kutubkhanah/settings.php', __('Shelf page, colours and lookup sources'));
