	<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2020-01-11 21:41
 * @File name           : install.sql.php
 */

$sql = [];
$query_trigger = [];

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `backup_log` (
  `backup_log_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL default \'0\',
  `backup_time` datetime NOT NULL,
  `backup_file` text collate utf8_unicode_ci default NULL,
  PRIMARY KEY  (`backup_log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;';

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `biblio` (
  `biblio_id` int(11) NOT NULL auto_increment,
  `gmd_id` int(3) default NULL,
  `title` text collate utf8_unicode_ci NOT NULL,
  `sor` varchar(200) collate utf8_unicode_ci default NULL,
  `edition` varchar(50) collate utf8_unicode_ci default NULL,
  `isbn_issn` varchar(32) collate utf8_unicode_ci default NULL,
  `publisher_id` int(11) default NULL,
  `publish_year` varchar(20) default NULL,
  `collation` varchar(100) collate utf8_unicode_ci default NULL,
  `series_title` varchar(200) collate utf8_unicode_ci default NULL,
  `call_number` varchar(50) collate utf8_unicode_ci default NULL,
  `language_id` char(5) collate utf8_unicode_ci default \'en\',
  `source` varchar(10) collate utf8_unicode_ci default NULL,
  `publish_place_id` int(11) default NULL,
  `classification` varchar(40) collate utf8_unicode_ci default NULL,
  `notes` text collate utf8_unicode_ci,
  `image` varchar(100) collate utf8_unicode_ci default NULL,
  `file_att` varchar(255) collate utf8_unicode_ci default NULL,
  `opac_hide` smallint(1) default \'0\',
  `promoted` smallint(1) default \'0\',
  `labels` text collate utf8_unicode_ci NULL,
  `frequency_id` int(11) NOT NULL default \'0\',
  `spec_detail_info` text collate utf8_unicode_ci,
  `content_type_id` int(11) default NULL,
  `media_type_id` int(11) default NULL,
  `carrier_type_id` int(11) default NULL,
  `input_date` datetime default NULL,
  `last_update` datetime default NULL,
  `uid` int(11) default NULL,
  PRIMARY KEY  (`biblio_id`),
  KEY `references_idx` (`gmd_id`,`publisher_id`,`language_id`,`publish_place_id`),
  KEY `classification` (`classification`),
  KEY `biblio_flag_idx` (`opac_hide`,`promoted`),
  KEY `rda_idx` (`content_type_id`, `media_type_id`, `carrier_type_id`),
  KEY `uid` (`uid`),
  KEY `publisher_id` (`publisher_id`),
  FULLTEXT KEY `title_ft_idx` (`title`,`series_title`),
  FULLTEXT KEY `notes_ft_idx` (`notes`),
  FULLTEXT KEY `labels` (`labels`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;';

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `biblio_attachment` (
  `biblio_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `placement` enum(\'link\',\'popup\',\'embed\') COLLATE utf8_unicode_ci NULL,
  `access_type` enum(\'public\',\'private\') collate utf8_unicode_ci NOT NULL,
  `access_limit` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
  KEY `biblio_id` (`biblio_id`),
  KEY `file_id` (`file_id`),
  KEY `biblio_id_2` (`biblio_id`,`file_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `biblio_author` (
  `biblio_id` int(11) NOT NULL default \'0\',
  `author_id` int(11) NOT NULL default \'0\',
  `level` int(1) NOT NULL default \'1\',
  KEY `biblio_id` (`biblio_id`),
  KEY `author_id` (`author_id`),
  PRIMARY KEY  (`biblio_id`,`author_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `biblio_topic` (
  `biblio_id` int(11) NOT NULL default \'0\',
  `topic_id` int(11) NOT NULL default \'0\',
  `level` int(1) NOT NULL default \'1\',
  KEY `biblio_id` (`biblio_id`),
  KEY `topic_id` (`topic_id`),
  PRIMARY KEY  (`biblio_id`,`topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$sql['create'][] = 'CREATE TABLE IF NOT EXISTS `content` (
  `content_id` int(11) NOT NULL auto_increment,
  `content_title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `content_desc` text collate utf8_unicode_ci NOT NULL,
  `content_path` varchar(20) collate utf8_unicode_ci NOT NULL,
  `is_news` smallint(1) NULL DEFAULT NULL,
  `is_draft` smallint(1) DEFAULT 0,
  `publish_date` date DEFAULT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  `content_ownpage` enum(\'1\',\'2\') COLLATE utf8_unicode_ci NOT NULL DEFAULT \'1\',
  PRIMARY KEY  (`content_id`),
  UNIQUE KEY `content_path` (`content_path`),
  FULLTEXT KEY `content_title` (`content_title`),
  FULLTEXT KEY `content_desc` (`content_desc`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=5;';

$sql['insert'][] = 'INSERT INTO `content` (`content_id`, `content_title`, `content_desc`, `content_path`, `input_date`, `last_update`, `content_ownpage`) VALUES
(1, \'Library Information\', \'<h3>Contact Information</h3><p>Add your address, email and visiting arrangements here (Admin &gt; System &gt; Content &gt; Library Information).</p>\', \'libinfo\', \'2026-10-19 00:00:00\', \'2026-10-19 00:00:00\', \'1\'),
(2, \'Help On Usage\', \'<h3>Searching</h3>\r\n<p>There are 2 methods available on searching library catalog. The first one is <strong>SIMPLE SEARCH</strong>, which is the simplest method on searching catalog. You just enter any keyword, either it containes in titles, author(s) names or subjects. You can supply more than one keywords in Simple Search method and it will expanding your search results.</p>\r\n<p>&nbsp;</p>\r\n<p><strong>ADVANCED SEARCH</strong>, lets you define keywords in more specific fields. If you want your keywords only contained in title field, then type your keyword in Title field and the system will limit its search within <strong>Title</strong> fields only, not in other fields. Location field lets you narrowing search results by specific location, so only collection that exists in selected location get fetched by system.</p>\', \'help\', \'2009-09-13 19:48:16\', \'2009-09-13 19:48:16\', \'1\'),
(3, \'Welcome To Admin Page\', \'<div class="container admin_home">\r\n<div class="row">\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Bibliography</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon biblioIcon notAJAX" href="index.php?mod=bibliography"></a></div>\r\n<div class="col-sm-8">The Bibliography module lets you manage your library bibliographical data. It also include collection items management to manage a copies of your library collection so it can be used in library circulation.</div>\r\n</div>\r\n</div>\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Circulation</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon circulationIcon notAJAX" href="index.php?mod=circulation"></a></div>\r\n<div class="col-sm-8">The Circulation module is used for doing library circulation transaction such as collection loans and return. In this module you can also create loan rules that will be used in loan transaction proccess.</div>\r\n</div>\r\n</div>\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Membership</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon memberIcon notAJAX" href="index.php?mod=membership"></a></div>\r\n<div class="col-sm-8">The Membership module lets you manage library members such adding, updating and also removing. You can also manage membership type in this module.</div>\r\n</div>\r\n</div>\r\n</div>\r\n<div class="row">\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Stock Take</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon stockTakeIcon notAJAX" href="index.php?mod=stock_take"></a></div>\r\n<div class="col-sm-8">The Stock Take module is the easy way to do Stock Opname for your library collections. Follow several steps that ease your pain in Stock Opname proccess.</div>\r\n</div>\r\n</div>\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Serial Control</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon serialIcon notAJAX" href="index.php?mod=serial_control"></a></div>\r\n<div class="col-sm-8">Serial Control module help you manage library\'\'s serial publication subscription. You can track issues for each subscription.</div>\r\n</div>\r\n</div>\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Reporting</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon reportIcon notAJAX" href="index.php?mod=reporting"></a></div>\r\n<div class="col-sm-8">Reporting lets you view various type of reports regardings membership data, circulation data and bibliographic data. All compiled on-the-fly from current library database.</div>\r\n</div>\r\n</div>\r\n</div>\r\n<div class="row">\r\n<div class="col-xs-6 col-md-4">\r\n<h3>Master File</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon masterFileIcon notAJAX" href="index.php?mod=master_file"></a></div>\r\n<div class="col-sm-8">The Master File modules lets you manage referential data that will be used by another modules. It include Authority File management such as Authority, Subject/Topic List, GMD and other data.</div>\r\n</div>\r\n</div>\r\n<div class="col-xs-6 col-md-4">\r\n<h3>System</h3>\r\n<div class="row">\r\n<div class="col-sm-2"><a class="icon systemIcon notAJAX" href="index.php?mod=system"></a></div>\r\n<div class="col-sm-8">The System module is used to configure application globally, manage index, manage librarian, and also backup database.</div>\r\n</div>\r\n</div>\r\n</div>\r\n</div>\', \'adminhome\', \'2009-09-13 19:48:16\', \'2009-09-13 22:02:11\', \'1\'),
(4, \'Homepage Info\', \'<p>Welcome to the library catalogue. Search by title, author, subject or ISBN, or browse the shelf.</p>\', \'headerinfo\', \'2026-10-19 00:00:00\', \'2026-10-19 00:00:00\', \'1\'),
(5, \'About Kutubkhanah\', \'<p><strong>Kutubkhanah</strong> is a home library system. It brings together three open-source library systems, SLiMS, PMB and OpenBiblio, and adds ISBN scanning with automatic lookups, Libib import and export, a cover shelf, author pages and a reader-friendly catalogue.</p><p>Source code and updates: <a href="https://github.com/menj/kutubkhanah">github.com/menj/kutubkhanah</a>.</p>\', \'about_kutubkhanah\', \'2026-10-19 00:00:00\', \'2026-10-19 00:00:00\', \'1\'),
(6, \'Licence\', \'<p>Kutubkhanah is free software released under the GNU General Public License, version 3 or later. It includes work from SLiMS, PMB and OpenBiblio under their own open-source licences. See the licence files that come with the software.</p>\', \'licence\', \'2026-10-19 00:00:00\', \'2026-10-19 00:00:00\', \'1\');
';


$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `files` (
  `file_id` int(11) NOT NULL auto_increment,
  `file_title` text collate utf8_unicode_ci NOT NULL,
  `file_name` text collate utf8_unicode_ci NOT NULL,
  `file_url` text collate utf8_unicode_ci,
  `file_dir` text collate utf8_unicode_ci,
  `mime_type` varchar(100) collate utf8_unicode_ci default NULL,
  `file_desc` text collate utf8_unicode_ci,
  `file_key` text collate utf8_unicode_ci,
  `uploader_id` int(11) NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  PRIMARY KEY  (`file_id`),
  FULLTEXT KEY `file_name` (`file_name`),
  FULLTEXT KEY `file_dir` (`file_dir`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `fines` (
  `fines_id` int(11) NOT NULL auto_increment,
  `fines_date` date NOT NULL,
  `member_id` varchar(20) collate utf8_unicode_ci NOT NULL,
  `debet` int(11) default '0',
  `credit` int(11) default '0',
  `description` varchar(255) collate utf8_unicode_ci default NULL,
  PRIMARY KEY  (`fines_id`),
  KEY `member_id` (`member_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `group_access` (
  `group_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `menus` json NULL,
  `r` int(1) NOT NULL default '0',
  `w` int(1) NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`module_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['insert'][] = "
INSERT INTO `group_access` (`group_id`, `module_id`, `r`, `w`) VALUES
(1, 1, 1, 1),
(1, 2, 1, 1),
(1, 3, 1, 1),
(1, 4, 1, 1),
(1, 5, 1, 1),
(1, 6, 1, 1),
(1, 7, 1, 1),
(1, 8, 1, 1),
(1, 9, 1, 1);";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `holiday` (
  `holiday_id` int(11) NOT NULL auto_increment,
  `holiday_dayname` varchar(20) collate utf8_unicode_ci NOT NULL,
  `holiday_date` date default NULL,
  `description` varchar(100) collate utf8_unicode_ci default NULL,
  PRIMARY KEY  (`holiday_id`),
  UNIQUE KEY `holiday_dayname` (`holiday_dayname`,`holiday_date`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=7 ;";

$sql['insert'][] = "
INSERT INTO `holiday` (`holiday_id`, `holiday_dayname`, `holiday_date`, `description`) VALUES
(1, 'Mon', '2009-06-01', 'Tes Libur'),
(2, 'Tue', '2009-06-02', 'Tes Libur'),
(3, 'Wed', '2009-06-03', 'Tes Libur'),
(4, 'Thu', '2009-06-04', 'Tes Libur'),
(5, 'Fri', '2009-06-05', 'Tes Libur'),
(6, 'Sat', '2009-06-06', 'Tes Libur');";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `item` (
  `item_id` int(11) NOT NULL auto_increment,
  `biblio_id` int(11) default NULL,
  `call_number` varchar(50) collate utf8_unicode_ci default NULL,
  `coll_type_id` int(3) default NULL,
  `item_code` varchar(20) collate utf8_unicode_ci default NULL,
  `inventory_code` varchar(200) collate utf8_unicode_ci default NULL,
  `received_date` date default NULL,
  `supplier_id` varchar(6) collate utf8_unicode_ci default NULL,
  `order_no` varchar(20) collate utf8_unicode_ci default NULL,
  `location_id` varchar(3) collate utf8_unicode_ci default NULL,
  `order_date` date default NULL,
  `item_status_id` char(3) collate utf8_unicode_ci default NULL,
  `site` varchar(50) collate utf8_unicode_ci default NULL,
  `source` int(1) NOT NULL default '0',
  `invoice` varchar(20) collate utf8_unicode_ci default NULL,
  `price` int(11) default NULL,
  `price_currency` varchar(10) collate utf8_unicode_ci default NULL,
  `invoice_date` date default NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime default NULL,
  `uid` int(11) default NULL,
  PRIMARY KEY  (`item_id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `uid` (`uid`),
  KEY `item_references_idx` (`coll_type_id`,`location_id`,`item_status_id`),
  KEY `biblio_id_idx` (`biblio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
  CREATE TABLE IF NOT EXISTS `item_custom` (
    `item_id` INT NOT NULL ,
    PRIMARY KEY ( `item_id` )
  ) ENGINE=MyISAM COMMENT = 'one to one relation with real item table';";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `kardex` (
  `kardex_id` int(11) NOT NULL auto_increment,
  `date_expected` date NOT NULL,
  `date_received` date default NULL,
  `seq_number` varchar(25) collate utf8_unicode_ci default NULL,
  `notes` text collate utf8_unicode_ci,
  `serial_id` int(11) default NULL,
  `input_date` date NOT NULL,
  `last_update` date NOT NULL,
  PRIMARY KEY  (`kardex_id`),
  KEY `fk_serial` (`serial_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `loan` (
  `loan_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `loan_date` date NOT NULL,
  `due_date` date NOT NULL,
  `renewed` int(11) NOT NULL DEFAULT '0',
  `loan_rules_id` int(11) NOT NULL DEFAULT '0',
  `actual` date DEFAULT NULL,
  `is_lent` int(11) NOT NULL DEFAULT '0',
  `is_return` int(11) NOT NULL DEFAULT '0',
  `return_date` date DEFAULT NULL,
  `input_date` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  PRIMARY KEY (`loan_id`),
  KEY `item_code` (`item_code`),
  KEY `member_id` (`member_id`),
  KEY `input_date` (`input_date`,`last_update`,`uid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `member` (
  `member_id` varchar(20) collate utf8_unicode_ci NOT NULL,
  `member_name` varchar(100) collate utf8_unicode_ci NOT NULL,
  `gender` int(1) NOT NULL,
  `birth_date` date default NULL,
  `member_type_id` int(6) default NULL,
  `member_address` varchar(255) collate utf8_unicode_ci default NULL,
  `member_mail_address` varchar(255) collate utf8_unicode_ci default NULL,
  `member_email` varchar(100) collate utf8_unicode_ci default NULL,
  `postal_code` varchar(20) collate utf8_unicode_ci default NULL,
  `inst_name` varchar(100) collate utf8_unicode_ci default NULL,
  `is_new` int(1) default NULL,
  `member_image` varchar(200) collate utf8_unicode_ci default NULL,
  `pin` varchar(50) collate utf8_unicode_ci default NULL,
  `member_phone` varchar(50) collate utf8_unicode_ci default NULL,
  `member_fax` varchar(50) collate utf8_unicode_ci default NULL,
  `member_since_date` date default NULL,
  `register_date` date default NULL,
  `expire_date` date NOT NULL,
  `member_notes` text collate utf8_unicode_ci,
  `is_pending` smallint(1) NOT NULL default '0',
  `mpasswd` VARCHAR(64) NULL,
  `last_login` DATETIME NULL,
  `last_login_ip` VARCHAR(50) NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`member_id`),
  KEY `member_name` (`member_name`),
  KEY `member_type_id` (`member_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_author` (
  `author_id` int(11) NOT NULL auto_increment,
  `author_name` varchar(100) collate utf8_unicode_ci NOT NULL,
  `author_year` varchar(20) collate utf8_unicode_ci default NULL,
  `authority_type` enum('p','o','c') collate utf8_unicode_ci default 'p',
  `auth_list` varchar(20) collate utf8_unicode_ci default NULL,
  `input_date` date NOT NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`author_id`),
  UNIQUE KEY `author_name` (`author_name`, `authority_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_coll_type` (
  `coll_type_id` int(3) NOT NULL auto_increment,
  `coll_type_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`coll_type_id`),
  UNIQUE KEY `coll_type_name` (`coll_type_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=4 ;";

$sql['insert'][] = "
INSERT INTO `mst_coll_type` (`coll_type_id`, `coll_type_name`, `input_date`, `last_update`) VALUES
(1, 'Reference', '2007-11-29', '2007-11-29'),
(2, 'Textbook', '2007-11-29', '2007-11-29'),
(3, 'Fiction', '2007-11-29', '2007-11-29');";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_frequency` (
  `frequency_id` int(11) NOT NULL auto_increment,
  `frequency` varchar(25) collate utf8_unicode_ci NOT NULL,
  `language_prefix` varchar(5) collate utf8_unicode_ci default NULL,
  `time_increment` smallint(6) default NULL,
  `time_unit` enum('day','week','month','year') collate utf8_unicode_ci default 'day',
  `input_date` date NOT NULL,
  `last_update` date NOT NULL,
  PRIMARY KEY  (`frequency_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=9 ;";

$sql['insert'][] = "
INSERT INTO `mst_frequency` (`frequency_id`, `frequency`, `language_prefix`, `time_increment`, `time_unit`, `input_date`, `last_update`) VALUES
(1, 'Weekly', 'en', 1, 'week', '2009-05-23', '2009-05-23'),
(2, 'Bi-weekly', 'en', 2, 'week', '2009-05-23', '2009-05-23'),
(3, 'Fourth-Nightly', 'en', 14, 'day', '2009-05-23', '2009-05-23'),
(4, 'Monthly', 'en', 1, 'month', '2009-05-23', '2009-05-23'),
(5, 'Bi-Monthly', 'en', 2, 'month', '2009-05-23', '2009-05-23'),
(6, 'Quarterly', 'en', 3, 'month', '2009-05-23', '2009-05-23'),
(7, '3 Times a Year', 'en', 4, 'month', '2009-05-23', '2009-05-23'),
(8, 'Annualy', 'en', 1, 'year', '2009-05-23', '2009-05-23');";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_gmd` (
  `gmd_id` int(11) NOT NULL auto_increment,
  `gmd_code` varchar(3) collate utf8_unicode_ci default NULL,
  `gmd_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `icon_image` varchar(100) collate utf8_unicode_ci default NULL,
  `input_date` date NOT NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`gmd_id`),
  UNIQUE KEY `gmd_name` (`gmd_name`),
  UNIQUE KEY `gmd_code` (`gmd_code`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=32 ;";

$sql['insert'][] = "
INSERT INTO `mst_gmd` (`gmd_id`, `gmd_code`, `gmd_name`, `icon_image`, `input_date`, `last_update`) VALUES
(1, 'TE', 'Text', NULL, DATE(NOW()), DATE(NOW())),
(2, 'AR', 'Art Original', NULL, DATE(NOW()), DATE(NOW())),
(3, 'CH', 'Chart', NULL, DATE(NOW()), DATE(NOW())),
(4, 'CO', 'Computer Software', NULL, DATE(NOW()), DATE(NOW())),
(5, 'DI', 'Diorama', NULL, DATE(NOW()), DATE(NOW())),
(6, 'FI', 'Filmstrip', NULL, DATE(NOW()), DATE(NOW())),
(7, 'FL', 'Flash Card', NULL, DATE(NOW()), DATE(NOW())),
(8, 'GA', 'Game', NULL, DATE(NOW()), DATE(NOW())),
(9, 'GL', 'Globe', NULL, DATE(NOW()), DATE(NOW())),
(10, 'KI', 'Kit', NULL, DATE(NOW()), DATE(NOW())),
(11, 'MA', 'Map', NULL, DATE(NOW()), DATE(NOW())),
(12, 'MI', 'Microform', NULL, DATE(NOW()), DATE(NOW())),
(13, 'MN', 'Manuscript', NULL, DATE(NOW()), DATE(NOW())),
(14, 'MO', 'Model', NULL, DATE(NOW()), DATE(NOW())),
(15, 'MP', 'Motion Picture', NULL, DATE(NOW()), DATE(NOW())),
(16, 'MS', 'Microscope Slide', NULL, DATE(NOW()), DATE(NOW())),
(17, 'MU', 'Music', NULL, DATE(NOW()), DATE(NOW())),
(18, 'PI', 'Picture', NULL, DATE(NOW()), DATE(NOW())),
(19, 'RE', 'Realia', NULL, DATE(NOW()), DATE(NOW())),
(20, 'SL', 'Slide', NULL, DATE(NOW()), DATE(NOW())),
(21, 'SO', 'Sound Recording', NULL, DATE(NOW()), DATE(NOW())),
(22, 'TD', 'Technical Drawing', NULL, DATE(NOW()), DATE(NOW())),
(23, 'TR', 'Transparency', NULL, DATE(NOW()), DATE(NOW())),
(24, 'VI', 'Video Recording', NULL, DATE(NOW()), DATE(NOW())),
(25, 'EQ', 'Equipment', NULL, DATE(NOW()), DATE(NOW())),
(26, 'CF', 'Computer File', NULL, DATE(NOW()), DATE(NOW())),
(27, 'CA', 'Cartographic Material', NULL, DATE(NOW()), DATE(NOW())),
(28, 'CD', 'CD-ROM', NULL, DATE(NOW()), DATE(NOW())),
(29, 'MV', 'Multimedia', NULL, DATE(NOW()), DATE(NOW())),
(30, 'ER', 'Electronic Resource', NULL, DATE(NOW()), DATE(NOW())),
(31, 'DVD', 'Digital Versatile Disc', NULL, DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_item_status` (
  `item_status_id` char(3) collate utf8_unicode_ci NOT NULL,
  `item_status_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `rules` varchar(255) collate utf8_unicode_ci default NULL,
  `no_loan` smallint(1) NOT NULL default '0',
  `skip_stock_take` smallint(1) NOT NULL default '0',
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`item_status_id`),
  UNIQUE KEY `item_status_name` (`item_status_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['insert'][] = 'INSERT INTO `mst_item_status` (`item_status_id`, `item_status_name`, `rules`, `input_date`, `last_update`, `no_loan`, `skip_stock_take`) VALUES
(\'R\', \'Repair\', \'a:1:{i:0;s:1:"1";}\', DATE(NOW()), DATE(NOW()), \'1\', \'1\'),
(\'NL\', \'No Loan\', \'a:1:{i:0;s:1:"1";}\', DATE(NOW()), DATE(NOW()), \'1\', \'1\'),
(\'MIS\', \'Missing\', NULL, DATE(NOW()), DATE(NOW()), \'1\', \'1\');';

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_label` (
  `label_id` int(11) NOT NULL auto_increment,
  `label_name` varchar(20) collate utf8_unicode_ci NOT NULL,
  `label_desc` varchar(50) collate utf8_unicode_ci default NULL,
  `label_image` varchar(200) collate utf8_unicode_ci NOT NULL,
  `input_date` date NOT NULL,
  `last_update` date NOT NULL,
  PRIMARY KEY  (`label_id`),
  UNIQUE KEY `label_name` (`label_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC AUTO_INCREMENT=4 ;";

$sql['insert'][] = "
INSERT INTO `mst_label` (`label_id`, `label_name`, `label_desc`, `label_image`, `input_date`, `last_update`) VALUES
(1, 'label-new', 'New Title', 'label-new.png', DATE(NOW()), DATE(NOW())),
(2, 'label-favorite', 'Favorite Title', 'label-favorite.png', DATE(NOW()), DATE(NOW())),
(3, 'label-multimedia', 'Multimedia', 'label-multimedia.png', DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_language` (
  `language_id` char(5) collate utf8_unicode_ci NOT NULL,
  `language_name` varchar(20) collate utf8_unicode_ci NOT NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`language_id`),
  UNIQUE KEY `language_name` (`language_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['insert'][] = "
INSERT INTO `mst_language` (`language_id`, `language_name`, `input_date`, `last_update`) VALUES
('id', 'Indonesia', DATE(NOW()), DATE(NOW())),
('en', 'English', DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_loan_rules` (
  `loan_rules_id` int(11) NOT NULL auto_increment,
  `member_type_id` int(11) NOT NULL default '0',
  `coll_type_id` int(11) default '0',
  `gmd_id` int(11) default '0',
  `loan_limit` int(3) default '0',
  `loan_periode` int(3) default '0',
  `reborrow_limit` int(3) default '0',
  `fine_each_day` int(3) default '0',
  `grace_periode` int(2) default '0',
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`loan_rules_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_location` (
  `location_id` varchar(3) collate utf8_unicode_ci NOT NULL,
  `location_name` varchar(100) collate utf8_unicode_ci default NULL,
  `input_date` date NOT NULL,
  `last_update` date NOT NULL,
  PRIMARY KEY  (`location_id`),
  UNIQUE KEY `location_name` (`location_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['insert'][] = "
INSERT INTO `mst_location` (`location_id`, `location_name`, `input_date`, `last_update`) VALUES
('SL', 'My Library', DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_member_type` (
  `member_type_id` int(11) NOT NULL auto_increment,
  `member_type_name` varchar(50) collate utf8_unicode_ci NOT NULL,
  `loan_limit` int(11) NOT NULL,
  `loan_periode` int(11) NOT NULL,
  `enable_reserve` int(1) NOT NULL default '0',
  `reserve_limit` int(11) NOT NULL default '0',
  `member_periode` int(11) NOT NULL,
  `reborrow_limit` int(11) NOT NULL,
  `fine_each_day` int(11) NOT NULL,
  `grace_periode` int(2) default '0',
  `input_date` date NOT NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`member_type_id`),
  UNIQUE KEY `member_type_name` (`member_type_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2 ;";

$sql['insert'][] = "
INSERT INTO `mst_member_type` (`member_type_id`, `member_type_name`, `loan_limit`, `loan_periode`, `enable_reserve`, `reserve_limit`, `member_periode`, `reborrow_limit`, `fine_each_day`, `grace_periode`, `input_date`, `last_update`) VALUES
(1, 'Standard', 2, 7, 1, 2, 365, 1, 0, 0, DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_module` (
  `module_id` int(3) NOT NULL auto_increment,
  `module_name` varchar(50) collate utf8_unicode_ci NOT NULL,
  `module_path` varchar(200) collate utf8_unicode_ci default NULL,
  `module_desc` varchar(255) collate utf8_unicode_ci default NULL,
  PRIMARY KEY  (`module_id`),
  UNIQUE KEY `module_name` (`module_name`,`module_path`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC AUTO_INCREMENT=10 ;";

$sql['insert'][] = "
INSERT INTO `mst_module` (`module_id`, `module_name`, `module_path`, `module_desc`) VALUES
(1, 'bibliography', 'bibliography', 'Manage your bibliographic/catalog and items/copies database'),
(2, 'circulation', 'circulation', 'Module for doing library items circulation such as loan and return'),
(3, 'membership', 'membership', 'Manage your library membership and membership type'),
(4, 'master_file', 'master_file', 'Manage your referential data that will be used by other modules'),
(5, 'stock_take', 'stock_take', 'Ease your pain in doing library stock opname process'),
(6, 'system', 'system', 'Configure system behaviour, user and backups'),
(7, 'reporting', 'reporting', 'Real time and dynamic report about library collections and circulation'),
(8, 'serial_control', 'serial_control', 'Serial publication management'),
(9, 'kutubkhanah', 'kutubkhanah', 'Scan ISBNs, import from Libib, manage the shelf, authors and inbox');";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_place` (
  `place_id` int(11) NOT NULL auto_increment,
  `place_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`place_id`),
  UNIQUE KEY `place_name` (`place_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_publisher` (
  `publisher_id` int(11) NOT NULL auto_increment,
  `publisher_name` varchar(100) collate utf8_unicode_ci NOT NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`publisher_id`),
  UNIQUE KEY `publisher_name` (`publisher_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_supplier` (
  `supplier_id` int(11) NOT NULL auto_increment,
  `supplier_name` varchar(100) collate utf8_unicode_ci NOT NULL,
  `address` varchar(100) collate utf8_unicode_ci default NULL,
  `postal_code` char(10) collate utf8_unicode_ci default NULL,
  `phone` char(14) collate utf8_unicode_ci default NULL,
  `contact` char(30) collate utf8_unicode_ci default NULL,
  `fax` char(14) collate utf8_unicode_ci default NULL,
  `account` char(12) collate utf8_unicode_ci default NULL,
  `e_mail` char(80) collate utf8_unicode_ci default NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`supplier_id`),
  UNIQUE KEY `supplier_name` (`supplier_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_topic` (
  `topic_id` int(11) NOT NULL auto_increment,
  `topic` varchar(50) collate utf8_unicode_ci NOT NULL,
  `topic_type` enum('t','g','n','tm','gr','oc') collate utf8_unicode_ci NOT NULL,
  `auth_list` varchar(20) collate utf8_unicode_ci default NULL,
  `classification` VARCHAR( 50 ) COLLATE utf8_unicode_ci NOT NULL COMMENT  'Classification Code',
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`topic_id`),
  UNIQUE KEY `topic` (`topic`, `topic_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['insert'][] = "INSERT IGNORE INTO `mst_topic` (`topic_id`, `topic`, `input_date`, `last_update`) VALUES
(1, 'Programming', '2007-11-29', '2007-11-29'),
(2, 'Website', '2007-11-29', '2007-11-29'),
(3, 'Operating System', '2007-11-29', '2007-11-29'),
(4, 'Linux', '2007-11-29', '2007-11-29'),
(5, 'Computer', '2007-11-29', '2007-11-29'),
(6, 'Database', '2007-11-29', '2007-11-29'),
(7, 'RDBMS', '2007-11-29', '2007-11-29'),
(8, 'Open Source', '2007-11-29', '2007-11-29'),
(9, 'Project', '2007-11-29', '2007-11-29'),
(10, 'Design', '2007-11-29', '2007-11-29'),
(11, 'Information', '2007-11-29', '2007-11-29'),
(12, 'Organization', '2007-11-29', '2007-11-29'),
(13, 'Metadata', '2007-11-29', '2007-11-29'),
(14, 'Library', '2007-11-29', '2007-11-29'),
(15, 'Corruption', '2007-11-29', '2007-11-29'),
(16, 'Development', '2007-11-29', '2007-11-29'),
(17, 'Poverty', '2007-11-29', '2007-11-29');";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `reserve` (
  `reserve_id` int(11) NOT NULL auto_increment,
  `member_id` varchar(20) collate utf8_unicode_ci NOT NULL,
  `biblio_id` int(11) NOT NULL,
  `item_code` varchar(20) collate utf8_unicode_ci NOT NULL,
  `reserve_date` datetime NOT NULL,
  PRIMARY KEY  (`reserve_id`),
  KEY `references_idx` (`member_id`,`biblio_id`),
  KEY `item_code_idx` (`item_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `serial` (
  `serial_id` int(11) NOT NULL auto_increment,
  `date_start` date NOT NULL,
  `date_end` date DEFAULT NULL,
  `period` varchar(100) collate utf8_unicode_ci default NULL,
  `notes` text collate utf8_unicode_ci,
  `biblio_id` int(11) default NULL,
  `gmd_id` int(11) default NULL,
  `input_date` date NOT NULL,
  `last_update` date NOT NULL,
  PRIMARY KEY  (`serial_id`),
  KEY `fk_serial_biblio` (`biblio_id`),
  KEY `fk_serial_gmd` (`gmd_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `setting` (
  `setting_id` int(3) NOT NULL auto_increment,
  `setting_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `setting_value` text collate utf8_unicode_ci,
  PRIMARY KEY  (`setting_id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=14 ;";

$sql['insert'][] = "INSERT IGNORE INTO `setting` (`setting_id`, `setting_name`, `setting_value`) VALUES
(1, 'library_name', 's:11:\"Kutubkhanah\";'),
(2, 'library_subname', 's:12:\"Home Library\";'),
(3, 'template', 'a:2:{s:5:\"theme\";s:7:\"default\";s:3:\"css\";s:26:\"template/default/style.css\";}'),
(4, 'admin_template', 'a:2:{s:5:\"theme\";s:7:\"default\";s:3:\"css\";s:32:\"admin_template/default/style.css\";}'),
(5, 'default_lang', 's:5:\"en_US\";'),
(6, 'opac_result_num', 's:2:\"10\";'),
(7, 'enable_promote_titles', 'N;'),
(8, 'quick_return', 'b:1;'),
(9, 'allow_loan_date_change', 'b:0;'),
(10, 'loan_limit_override', 'b:0;'),
(11, 'enable_xml_detail', 'b:1;'),
(12, 'enable_xml_result', 'b:1;'),
(13, 'allow_file_download', 'b:1;'),
(14, 'session_timeout', 's:4:\"7200\";'),
(15, 'circulation_receipt', 'b:0;'),
(16, 'barcode_encoding', 's:7:\"code128\";'),
(17, 'ignore_holidays_fine_calc', 'b:0;'),
(18, 'barcode_print_settings', 'a:12:{s:19:\"barcode_page_margin\";d:0.200000000000000011102230246251565404236316680908203125;s:21:\"barcode_items_per_row\";i:3;s:20:\"barcode_items_margin\";d:0.1000000000000000055511151231257827021181583404541015625;s:17:\"barcode_box_width\";i:7;s:18:\"barcode_box_height\";i:5;s:27:\"barcode_include_header_text\";i:1;s:17:\"barcode_cut_title\";i:50;s:19:\"barcode_header_text\";s:0:\"\";s:13:\"barcode_fonts\";s:41:\"Arial, Verdana, Helvetica, ''Trebuchet MS''\";s:17:\"barcode_font_size\";i:11;s:13:\"barcode_scale\";i:70;s:19:\"barcode_border_size\";i:1;}'),
(19, 'label_print_settings', 'a:10:{s:11:\"page_margin\";d:0.200000000000000011102230246251565404236316680908203125;s:13:\"items_per_row\";i:3;s:12:\"items_margin\";d:0.05000000000000000277555756156289135105907917022705078125;s:9:\"box_width\";i:8;s:10:\"box_height\";d:3.29999999999999982236431605997495353221893310546875;s:19:\"include_header_text\";i:1;s:11:\"header_text\";s:0:\"\";s:5:\"fonts\";s:41:\"Arial, Verdana, Helvetica, ''Trebuchet MS''\";s:9:\"font_size\";i:11;s:11:\"border_size\";i:1;}'),
(20, 'membercard_print_settings', 'a:19:{s:18:\"front_header1_text\";s:19:\"Library Member Card\";s:18:\"front_header2_text\";s:10:\"My Library\";s:17:\"back_header1_text\";s:10:\"My Library\";s:17:\"back_header2_text\";s:35:\"My Library Full Address and Website\";s:9:\"box_width\";s:3:\"8.6\";s:10:\"box_height\";s:3:\"5.4\";s:6:\"factor\";s:12:\"37.795275591\";s:13:\"barcode_scale\";s:3:\"100\";s:5:\"rules\";s:150:\"&lt;ul&gt;&lt;li&gt;This card is published by Library.&lt;/li&gt;&lt;li&gt;Please return this card to its owner if you found it.&lt;/li&gt;&lt;/ul&gt;\";s:7:\"f_color\";s:7:\"#000000\";s:8:\"fr_color\";s:7:\"#e5e5e5\";s:7:\"b_color\";s:7:\"#ffffff\";s:4:\"city\";s:9:\"City Name\";s:5:\"title\";s:15:\"Library Manager\";s:9:\"officials\";s:14:\"Librarian Name\";s:12:\"officials_id\";s:12:\"Librarian ID\";s:15:\"back_side_image\";s:11:\"bg-back.svg\";s:3:\"css\";s:0:\"\";s:8:\"template\";s:7:\"classic\";}')
;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `stock_take` (
  `stock_take_id` int(11) NOT NULL auto_increment,
  `stock_take_name` varchar(200) collate utf8_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime default NULL,
  `init_user` varchar(50) collate utf8_unicode_ci NOT NULL,
  `total_item_stock_taked` int(11) default NULL,
  `total_item_lost` int(11) default NULL,
  `total_item_exists` int(11) default '0',
  `total_item_loan` int(11) default NULL,
  `stock_take_users` mediumtext collate utf8_unicode_ci,
  `is_active` int(1) NOT NULL default '0',
  `report_file` varchar(255) collate utf8_unicode_ci default NULL,
  PRIMARY KEY  (`stock_take_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `stock_take_item` (
  `stock_take_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_code` varchar(20) collate utf8_unicode_ci NOT NULL,
  `title` varchar(255) collate utf8_unicode_ci NOT NULL,
  `gmd_name` varchar(30) collate utf8_unicode_ci default NULL,
  `classification` varchar(30) collate utf8_unicode_ci default NULL,
  `coll_type_name` varchar(30) collate utf8_unicode_ci default NULL,
  `call_number` varchar(50) collate utf8_unicode_ci default NULL,
  `location` varchar(100) collate utf8_unicode_ci default NULL,
  `status` enum('e','m','u','l') collate utf8_unicode_ci NOT NULL default 'm',
  `checked_by` varchar(50) collate utf8_unicode_ci default NULL,
  `last_update` datetime default NULL,
  PRIMARY KEY  (`stock_take_id`,`item_id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `status` (`status`),
  KEY `item_properties_idx` (`gmd_name`,`classification`,`coll_type_name`,`location`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `system_log` (
  `log_id` int(11) NOT NULL auto_increment,
  `log_type` enum('staff','member','system') collate utf8_unicode_ci NOT NULL default 'staff',
  `id` varchar(50) collate utf8_unicode_ci default NULL,
  `log_location` varchar(50) collate utf8_unicode_ci NOT NULL,
  `sub_module` varchar(50) COLLATE 'utf8_unicode_ci' NULL,
  `action` varchar(50) COLLATE 'utf8_unicode_ci' NULL,
  `log_msg` text collate utf8_unicode_ci NOT NULL,
  `log_date` datetime NOT NULL,
  PRIMARY KEY  (`log_id`),
  KEY `log_type` (`log_type`),
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `realname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `passwd` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `2fa` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_type` smallint(2) DEFAULT NULL,
  `user_image` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `social_media` text COLLATE utf8_unicode_ci NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `groups` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `admin_template` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `forgot` VARCHAR(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `input_date` date DEFAULT NULL,
  `last_update` date DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `realname` (`realname`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2;";

$sql['insert'][] = 'INSERT INTO `user` (`user_id`, `username`, `realname`, `passwd`, `last_login`, `last_login_ip`, `groups`, `input_date`, `last_update`) VALUES
(1, \'admin\', \'Administrator\', \'$2y$10$pG0dqMrd2r39zRTSFSyp8.Z7sMy4cY7s/18UDQsV50Vn0TnR6UORm\', null, \'127.0.0.1\', \'a:1:{i:0;s:1:"1";}\', DATE(NOW()), DATE(NOW()));';

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `user_group` (
  `group_id` int(11) NOT NULL auto_increment,
  `group_name` varchar(30) collate utf8_unicode_ci NOT NULL,
  `input_date` date default NULL,
  `last_update` date default NULL,
  PRIMARY KEY  (`group_id`),
  UNIQUE KEY `group_name` (`group_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2 ;";

$sql['insert'][] = "
INSERT INTO `user_group` (`group_id`, `group_name`, `input_date`, `last_update`) VALUES
(1, 'Administrator', DATE(NOW()), DATE(NOW()));";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `visitor_count` (
  `visitor_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `institution` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `room_code` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `checkin_date` datetime NOT NULL,
  PRIMARY KEY (`visitor_id`),
  KEY `member_id` (`member_id`),
  KEY `room_code` (`room_code`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `biblio_custom` (
`biblio_id` INT NOT NULL ,
PRIMARY KEY ( `biblio_id` )
) ENGINE=MyISAM COMMENT = 'one to one relation with real biblio table';";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `search_biblio` (
  `biblio_id` int(11) NOT NULL,
  `title` text COLLATE utf8_unicode_ci,
  `edition` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isbn_issn` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `author` text COLLATE utf8_unicode_ci,
  `topic` text COLLATE utf8_unicode_ci,
  `gmd` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `publisher` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `publish_place` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `language` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `classification` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `spec_detail_info` text COLLATE utf8_unicode_ci,
  `carrier_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `content_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `media_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `location` text COLLATE utf8_unicode_ci,
  `publish_year` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  `series_title` text COLLATE utf8_unicode_ci,
  `items` text COLLATE utf8_unicode_ci,
  `collection_types` text COLLATE utf8_unicode_ci,
  `call_number` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `opac_hide` smallint(1) NOT NULL DEFAULT '0',
  `promoted` smallint(1) NOT NULL DEFAULT '0',
  `labels` text COLLATE utf8_unicode_ci,
  `collation` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `input_date` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  UNIQUE KEY `biblio_id` (`biblio_id`),
  KEY `add_indexes` (`gmd`,`publisher`,`publish_place`,`language`,`classification`,`publish_year`,`call_number`),
  KEY `add_indexes2` (`opac_hide`,`promoted`),
  KEY `rda_indexes` (`carrier_type`,`media_type`,`content_type`),
  FULLTEXT `title` (`title`,`series_title`),
  FULLTEXT `author` (`author`),
  FULLTEXT `topic` (`topic`),
  FULLTEXT `location` (`location`),
  FULLTEXT `items` (`items`),
  FULLTEXT `collection_types` (`collection_types`),
  FULLTEXT `labels` (`labels`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='index table for advance searching technique for SLiMS';";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `member_custom` (
`member_id` VARCHAR(20) NOT NULL ,
PRIMARY KEY ( `member_id` )
) ENGINE=MyISAM COMMENT = 'one to one relation with real member table';";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `biblio_id` int(11) NOT NULL,
  `member_id` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `comment` text COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_carrier_type` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `carrier_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_type` (`carrier_type`),
  KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=56 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_content_type` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_type` (`content_type`),
  KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_media_type` (
`id` int(11) NOT NULL AUTO_INCREMENT,
  `media_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_type` (`media_type`),
  KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_relation_term` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
  `rt_id` varchar(11) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `rt_desc` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=13 ;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_voc_ctrl` (
  `vocabolary_id` int(11) NOT NULL AUTO_INCREMENT,
  `topic_id` int(11) NOT NULL,
  `rt_id` varchar(11) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `related_topic_id` varchar(250) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `scope` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`vocabolary_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `biblio_relation` (
  `biblio_id` int(11) NOT NULL DEFAULT '0',
  `rel_biblio_id` int(11) NOT NULL DEFAULT '0',
  `rel_type` int(1) DEFAULT '1',
  PRIMARY KEY (`biblio_id`,`rel_biblio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['insert'][] = "
INSERT INTO `mst_carrier_type` (`id`, `carrier_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'audio cartridge', 'sg', 'g', NOW(), NOW()),
(2, 'audio cylinder', 'se', 'e', NOW(), NOW()),
(3, 'audio disc', 'sd', 'd', NOW(), NOW()),
(4, 'sound track reel', 'si', 'i', NOW(), NOW()),
(5, 'audio roll', 'sq', 'q', NOW(), NOW()),
(6, 'audiocassette', 'ss', 's', NOW(), NOW()),
(7, 'audiotape reel', 'st', 't', NOW(), NOW()),
(8, 'other (audio)', 'sz', 'z', NOW(), NOW()),
(9, 'computer card', 'ck', 'k', NOW(), NOW()),
(10, 'computer chip cartridge', 'cb', 'b', NOW(), NOW()),
(11, 'computer disc', 'cd', 'd', NOW(), NOW()),
(12, 'computer disc cartridge', 'ce', 'e', NOW(), NOW()),
(13, 'computer tape cartridge', 'ca', 'a', NOW(), NOW()),
(14, 'computer tape cassette', 'cf', 'f', NOW(), NOW()),
(15, 'computer tape reel', 'ch', 'h', NOW(), NOW()),
(16, 'online resource', 'cr', 'r', NOW(), NOW()),
(17, 'other (computer)', 'cz', 'z', NOW(), NOW()),
(18, 'aperture card', 'ha', 'a', NOW(), NOW()),
(19, 'microfiche', 'he', 'e', NOW(), NOW()),
(20, 'microfiche cassette', 'hf', 'f', NOW(), NOW()),
(21, 'microfilm cartridge', 'hb', 'b', NOW(), NOW()),
(22, 'microfilm cassette', 'hc', 'c', NOW(), NOW()),
(23, 'microfilm reel', 'hd', 'd', NOW(), NOW()),
(24, 'microfilm roll', 'hj', 'j', NOW(), NOW()),
(25, 'microfilm slip', 'hh', 'h', NOW(), NOW()),
(26, 'microopaque', 'hg', 'g', NOW(), NOW()),
(27, 'other (microform)', 'hz', 'z', NOW(), NOW()),
(28, 'microscope slide', 'pp', 'p', NOW(), NOW()),
(29, 'other (microscope)', 'pz', 'z', NOW(), NOW()),
(30, 'film cartridge', 'mc', 'c', NOW(), NOW()),
(31, 'film cassette', 'mf', 'f', NOW(), NOW()),
(32, 'film reel', 'mr', 'r', NOW(), NOW()),
(33, 'film roll', 'mo', 'o', NOW(), NOW()),
(34, 'filmslip', 'gd', 'd', NOW(), NOW()),
(35, 'filmstrip', 'gf', 'f', NOW(), NOW()),
(36, 'filmstrip cartridge', 'gc', 'c', NOW(), NOW()),
(37, 'overhead transparency', 'gt', 't', NOW(), NOW()),
(38, 'slide', 'gs', 's', NOW(), NOW()),
(39, 'other (projected image)', 'mz', 'z', NOW(), NOW()),
(40, 'stereograph card', 'eh', 'h', NOW(), NOW()),
(41, 'stereograph disc', 'es', 's', NOW(), NOW()),
(42, 'other (stereographic)', 'ez', 'z', NOW(), NOW()),
(43, 'card', 'no', 'o', NOW(), NOW()),
(44, 'flipchart', 'nn', 'n', NOW(), NOW()),
(45, 'roll', 'na', 'a', NOW(), NOW()),
(46, 'sheet', 'nb', 'b', NOW(), NOW()),
(47, 'volume', 'nc', 'c', NOW(), NOW()),
(48, 'object', 'nr', 'r', NOW(), NOW()),
(49, 'other (unmediated)', 'nz', '', NOW(), NOW()),
(50, 'video cartridge', 'vc', '', NOW(), NOW()),
(51, 'videocassette', 'vf', '', NOW(), NOW()),
(52, 'videodisc', 'vd', '', NOW(), NOW()),
(53, 'videotape reel', 'vr', '', NOW(), NOW()),
(54, 'other (video)', 'vz', '', NOW(), NOW()),
(55, 'unspecified', 'zu', 'u', NOW(), NOW());";

$sql['insert'][] = "
INSERT INTO `mst_content_type` (`id`, `content_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'cartographic dataset', 'crd', 'e', NOW(), NOW()),
(2, 'cartographic image', 'cri', 'e', NOW(), NOW()),
(3, 'cartographic moving image', 'crm', 'e', NOW(), NOW()),
(4, 'cartographic tactile image', 'crt', 'e', NOW(), NOW()),
(5, 'cartographic tactile three-dimensional form', 'crn', 'e', NOW(), NOW()),
(6, 'cartographic three-dimensional form', 'crf', 'e', NOW(), NOW()),
(7, 'computer dataset', 'cod', 'm', NOW(), NOW()),
(8, 'computer program', 'cop', 'm', NOW(), NOW()),
(9, 'notated movement', 'ntv', 'a', NOW(), NOW()),
(10, 'notated music', 'ntm', 'c', NOW(), NOW()),
(11, 'performed music', 'prm', 'j', NOW(), NOW()),
(12, 'sounds', 'snd', 'i', NOW(), NOW()),
(13, 'spoken word', 'spw', 'i', NOW(), NOW()),
(14, 'still image', 'sti', 'k', NOW(), NOW()),
(15, 'tactile image', 'tci', 'k', NOW(), NOW()),
(16, 'tactile notated music', 'tcm', 'c', NOW(), NOW()),
(17, 'tactile notated movement', 'tcn', 'a', NOW(), NOW()),
(18, 'tactile text', 'tct', 'a', NOW(), NOW()),
(19, 'tactile three-dimensional form', 'tcf', 'r', NOW(), NOW()),
(20, 'text', 'txt', 'a', NOW(), NOW()),
(21, 'three-dimensional form', 'tdf', 'r', NOW(), NOW()),
(22, 'three-dimensional moving image', 'tdm', 'g', NOW(), NOW()),
(23, 'two-dimensional moving image', 'tdi', 'g', NOW(), NOW()),
(24, 'other', 'xxx', 'o', NOW(), NOW()),
(25, 'unspecified', 'zzz', ' ', NOW(), NOW());";

$sql['insert'][] = "
INSERT INTO `mst_media_type` (`id`, `media_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'audio', 's', 's', NOW(), NOW()),
(2, 'computer', 'c', 'c', NOW(), NOW()),
(3, 'microform', 'h', 'h', NOW(), NOW()),
(4, 'microscopic', 'p', ' ', NOW(), NOW()),
(5, 'projected', 'g', 'g', NOW(), NOW()),
(6, 'stereographic', 'e', ' ', NOW(), NOW()),
(7, 'unmediated', 'n', 't', NOW(), NOW()),
(8, 'video', 'v', 'v', NOW(), NOW()),
(9, 'other', 'x', 'z', NOW(), NOW()),
(10, 'unspecified', 'z', 'z', NOW(), NOW());";

$sql['insert'][] = "
INSERT INTO `mst_relation_term` (`ID`, `rt_id`, `rt_desc`) VALUES
(1, 'U', 'Use'),
(2, 'UF', 'Use For'),
(3, 'BT', 'Broader Term'),
(4, 'NT', 'Narrower Term'),
(5, 'RT', 'Related Term'),
(6, 'SA', 'See Also');";

$sql['insert'][] = "
INSERT IGNORE INTO `setting` (`setting_name`, `setting_value`) VALUES
('enable_visitor_limitation', 's:1:\"0\";'),
('time_visitor_limitation', 's:2:\"60\";'),
('logo_image', NULL);";

$sql['create'][] = "
CREATE TABLE `mst_servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uri` text COLLATE utf8_unicode_ci NOT NULL,
  `server_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - p2p server; 2 - z3950; 3 - z3950  SRU',
  `input_date` datetime NOT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `biblio_log` (
  `biblio_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `biblio_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `realname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `affectedrow` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rawdata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_information` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`biblio_log_id`),
  KEY `realname` (`realname`),
  KEY `biblio_id` (`biblio_id`),
  KEY `user_id` (`user_id`),
  KEY `ip` (`ip`),
  KEY `action` (`action`),
  KEY `affectedrow` (`affectedrow`),
  KEY `date` (`date`),
  FULLTEXT KEY `title` (`title`),
  FULLTEXT KEY `rawdata` (`rawdata`),
  FULLTEXT KEY `additional_information` (`additional_information`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";


$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `loan_history` (
  `loan_id` int(11) NOT NULL,
  `item_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `biblio_id` int(11) NOT NULL,
  `title` varchar(300) COLLATE utf8_unicode_ci DEFAULT NULL,
  `call_number` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `classification` varchar(40) COLLATE utf8_unicode_ci  DEFAULT NULL,
  `gmd_name` varchar(30) COLLATE utf8_unicode_ci  DEFAULT NULL,
  `language_name` varchar(20) COLLATE utf8_unicode_ci  DEFAULT NULL,
  `location_name` varchar(100) COLLATE utf8_unicode_ci  DEFAULT NULL,
  `collection_type_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_type_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `loan_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `renewed` int(11) NOT NULL DEFAULT '0',
  `is_lent` int(11) NOT NULL DEFAULT '0',
  `is_return` int(11) NOT NULL DEFAULT '0',
  `return_date` date DEFAULT NULL,
  `input_date` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
   PRIMARY KEY (`loan_id`),
   KEY `member_name` (`member_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";


$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `mst_custom_field` (
  `field_id` int(11) NOT NULL AUTO_INCREMENT,
  `primary_table` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dbfield` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `type` enum('text','checklist','numeric','dropdown','longtext','choice','date') COLLATE utf8_unicode_ci NOT NULL,
  `default` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `max` int(11) DEFAULT NULL,
  `data` text COLLATE utf8_unicode_ci,
  `indexed` tinyint(1) DEFAULT NULL,
  `class` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT NULL,
  `width` int(5) DEFAULT '100',
  `note` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`dbfield`),
  UNIQUE KEY `field_id` (`field_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `files_read` (
  `filelog_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `date_read` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `member_id` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`filelog_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

$sql['create'][] = "
CREATE TABLE IF NOT EXISTS `plugins` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE ,
  `options` json NULL,
  `path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  `uid` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `index_words` (
  `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `word` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `num_hits` int NOT NULL,
  `doc_hits` int NOT NULL
) ENGINE=MyISAM COLLATE 'utf8mb4_unicode_ci';";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `index_documents` (
  `document_id` int(11) NOT NULL,
  `word_id` bigint(20) NOT NULL,
  `location` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hit_count` int(11) NOT NULL,
  PRIMARY KEY (`document_id`,`word_id`,`location`),
  KEY `document_id` (`document_id`),
  KEY `word_id` (`word_id`),
  KEY `location` (`location`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `biblio_mark` (
  `id` varchar(32) NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `biblio_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `id` (`id`),
  KEY `member_id_idx` (`member_id`),
  KEY `biblio_id_idx` (`biblio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_visitor_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `unique_code` varchar(5) NOT NULL COMMENT 'Code for identification each room',
  `created_at` datetime DEFAULT NULL,
  `updated_at`datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code_unq` (`unique_code`),
  KEY `unique_code_idx` (`unique_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql['create'][] = "CREATE TABLE IF NOT EXISTS `cache` (
  `name` varchar(64) NOT NULL,
  `contents` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `expired_at` datetime DEFAULT NULL,
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql['create'][] = "CREATE TABLE `user_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `selector` varchar(255) NOT NULL,
  `hashed_validator` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

/* Kutubkhanah tables, FAQ page and module access are created by \SLiMS\Kutubkhanah\Schema::ensure() (see install/api.php). */
