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
$menu['kutubkhanah.fields']   = array(__('Custom Fields'), MWB . 'kutubkhanah/fields.php', __('Your own details per format, such as signed copy or condition'));
$menu['kutubkhanah.authors']  = array(__('Authors'), MWB . 'kutubkhanah/authors.php', __('Author details shown on public author pages'));
$menu['kutubkhanah.inbox']    = array(__('Inbox'), MWB . 'kutubkhanah/inbox.php', __('Access requests and contact messages'));
$menu['kutubkhanah.settings'] = array(__('Settings'), MWB . 'kutubkhanah/settings.php', __('Shelf page, colours and lookup sources'));
