<?php

/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2020-01-12 08:36
 * @File name           : Upgrade.inc.php
 */

namespace Install;


class Upgrade
{

  /**
   * @var SLiMS
   */
  public $slims;

  /**
   * Latest version
   *
   * @var int
   */
  private $version = 39;

  /**
   * @param SLiMS $slims
   * @return Upgrade
   */
  static function init(SLiMS $slims)
  {
    $upgrade = new Upgrade();
    $upgrade->slims = $slims;

    // determine how mane upgrade_role is availabe
    $versions = [];
    foreach (get_class_methods($upgrade) as $method) {
      if (preg_match('/upgrade_role_/', $method)) {
        $versions[] = preg_replace('/[^0-9]/', '', $method);
      }
    }

    // get last version
    sort($versions);
    $upgrade->version = $versions[array_key_last($versions)];
    return $upgrade;
  }

  function hookBeforeUpgrade()
  {
    // store old sql mode
    $old_sql_mode = $this->slims->storeOldSqlMode();
    // update sql_mode to modify database
    $new_sql_mode = str_replace(['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'], '', $old_sql_mode);
    $this->slims->updateSqlMode($new_sql_mode);
  }

  function hookAfterUpgrade($version)
  {
    // cek if table not exist
    $tables = require 'tables.php';
    foreach ($tables as $table) {
      $mtables = $this->slims->getTables();
      if (!in_array($table['table'], $mtables)) {
        // create table
        $msg = $this->slims->createTable($table);
        if ($msg) $error[] = $msg;
      } else {
        // check column
        foreach ($table['column'] as $column) {
          $mColumn = $this->slims->getColumn($table['table']);
          if (!in_array($column['field'], $mColumn)) {
            $msg = $this->slims->addColumn($table['table'], $column);
            if ($msg) $error[] = $msg;
          }
        }
      }
    }

    // make sure use default template
    $this->slims->updateTheme('default', $version);

    // update storeage engine
    $this->slims->updateStorageEngine();

    // rollback sql_mode
    $this->slims->rollbackSqlMode();
  }

  /**
   * Function to execute upgrade role from specific version
   *
   * @param $version
   * @return array
   */
  function from($version)
  {
    if (!isset($_SESSION['upgrade_attempt'])) $_SESSION['upgrade_attempt'] = 1;
    else $_SESSION['upgrade_attempt']++;

    // run before script
    $this->hookBeforeUpgrade();

    $raw_err = [];
    $roles = range($version, $this->version);
    sort($roles);

    foreach ($roles as $role) {
      $method = 'upgrade_role_' . $role;
      if (method_exists($this, $method)) {
        $raw_err[] = $this->$method();
      }
    }

    $err = [];
    foreach ($raw_err as $e) {
      if (is_array($e)) {
        foreach ($e as $_e) {
          $err[] = $_e;
        }
      }
    }

    // run after script
    $this->hookAfterUpgrade($version);
    return $err;
  }

  function getVersion()
  {
    return $this->version;
  }

  /**
   * Upgrade role to v3.3.0
   */
  function upgrade_role_1() {}

  /**
   * Upgrade role to v3.4.0
   */
  function upgrade_role_2() {}

  /**
   * Upgrade role to v3.5.0
   */
  function upgrade_role_3() {}

  /**
   * Upgrade role to v3.6.0
   */
  function upgrade_role_4() {}

  /**
   * Upgrade role to v3.7.0
   */
  function upgrade_role_5() {}

  /**
   * Upgrade role to v3.8.0
   */
  function upgrade_role_6() {}

  /**
   * Upgrade role to v3.9.0
   */
  function upgrade_role_7() {}

  /**
   * Upgrade role to v3.10.0
   */
  function upgrade_role_8() {}

  /**
   * Upgrade role to v3.11.0
   *
   * @return mixed
   */
  function upgrade_role_9()
  {
    $sql['truncate'][] = "TRUNCATE TABLE `setting`;";

    $sql['insert'][] = "INSERT INTO `setting` (`setting_id`, `setting_name`, `setting_value`) VALUES
(1, 'library_name', 's:7:\"Senayan\";'),
(2, 'library_subname', 's:37:\"Open Source Library Management System\";'),
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
(14, 'session_timeout', 's:4:\"7200\";');";

    $sql['alter'][] = "ALTER TABLE `mst_item_status` ADD `no_loan` SMALLINT( 1 ) NOT NULL DEFAULT '0' AFTER `rules`, ADD INDEX ( no_loan );
ALTER TABLE `mst_item_status` ADD `skip_stock_take` SMALLINT( 1 ) NOT NULL DEFAULT '0' AFTER `no_loan`, ADD INDEX ( skip_stock_take );
UPDATE `mst_item_status` SET `no_loan`=1 WHERE `rules` LIKE '%s:1:\"1\";%';
UPDATE `mst_item_status` SET `skip_stock_take`=1 WHERE `rules` LIKE '%s:1:\"2\";%';";

    $sql['alter'][] = "ALTER TABLE `biblio` CHANGE `labels` `labels` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;";

    return $this->slims->query($sql, ['truncate', 'alter', 'insert'], 9);
  }

  /**
   * Upgrade role to v3.12.0
   */
  function upgrade_role_10() {}

  /**
   * Upgrade role to v3.13.0
   *
   * @return mixed
   */
  function upgrade_role_11()
  {
    $sql['insert'][] = "INSERT INTO `setting` (`setting_id`, `setting_name`, `setting_value`) VALUES (NULL, 'circulation_receipt', 'b:1;'),
(NULL, 'barcode_encoding', 's:4:\"128B\";');";

    return $this->slims->query($sql, ['insert'], 11);
  }

  /**
   * Upgrade role to v3.14.0
   */
  function upgrade_role_12() {}

  /**
   * Upgrade role to v3.15.0
   *
   * @return mixed
   */
  function upgrade_role_13()
  {
    $sql['drop'][] = "DROP TABLE IF EXISTS `search_biblio`;";

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `search_biblio` (
  `biblio_id` int(11) NOT NULL,
  `title` text COLLATE utf8_unicode_ci,
  `edition` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isbn_issn` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `author` text COLLATE utf8_unicode_ci,
  `topic` text COLLATE utf8_unicode_ci,
  `gmd` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `publisher` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `publish_place` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `language` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `classification` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `spec_detail_info` text COLLATE utf8_unicode_ci,
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
  FULLTEXT `title` (`title`),
  FULLTEXT `author` (`author`),
  FULLTEXT `topic` (`topic`),
  FULLTEXT `location` (`location`),
  FULLTEXT `items` (`items`),
  FULLTEXT `collection_types` (`collection_types`),
  FULLTEXT `labels` (`labels`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='index table for advance searching technique for SLiMS';";

    $sql['alter'][] = "ALTER TABLE `biblio` CHANGE `publish_year` `publish_year` VARCHAR( 20 ) NULL DEFAULT NULL;";

    $sql['alter'][] = "ALTER TABLE `member` ADD `member_mail_address` varchar(255) collate utf8_unicode_ci default NULL AFTER `member_address`;";

    $sql['alter'][] = "ALTER TABLE `mst_author` DROP INDEX `author_name`;";

    $sql['alter'][] = "ALTER TABLE `mst_author` ADD `author_year` VARCHAR( 20 ) NULL DEFAULT NULL AFTER `author_name`;";

    $sql['alter'][] = "ALTER TABLE `mst_author` ADD UNIQUE (`author_name` ,`authority_type`);";

    $sql['alter'][] = "ALTER TABLE `mst_topic` DROP INDEX `topic`;";

    $sql['alter'][] = "ALTER TABLE `mst_topic` ADD UNIQUE (`topic` ,`topic_type`);";

    $sql['alter'][] = "ALTER TABLE `content` DROP INDEX `content_path`;";

    $sql['alter'][] = "ALTER TABLE `content` ADD UNIQUE (`content_path`);";

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `member_custom` (
`member_id` VARCHAR(20) NOT NULL ,
PRIMARY KEY ( `member_id` )
) ENGINE = MYISAM COMMENT = 'one to one relation with real member table';";

    $sql['alter'][] = "ALTER TABLE `content` ADD `content_ownpage` ENUM( '1', '2' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '1';";

    $sql['insert'][] = "INSERT INTO `content` (`content_id`, `content_title`, `content_desc`, `content_path`, `input_date`, `last_update`, `content_ownpage`) VALUES
(null, 'About Kutubkhanah', '<p><strong>Kutubkhanah</strong> is a home library system. It brings together three open-source library systems, SLiMS, PMB and OpenBiblio, and adds ISBN scanning with automatic lookups, Libib import and export, a cover shelf, author pages and a reader-friendly catalogue.</p><p>Source code and updates: <a href=\"https://github.com/menj/kutubkhanah\">github.com/menj/kutubkhanah</a>.</p>', 'about_kutubkhanah', '2026-10-19 00:00:00', '2026-10-19 00:00:00', '1'),
(null, 'Licence', '<p>Kutubkhanah is free software released under the GNU General Public License, version 3 or later. It includes work from SLiMS, PMB and OpenBiblio under their own open-source licences. See the licence files that come with the software.</p>', 'licence', '2026-10-19 00:00:00', '2026-10-19 00:00:00', '1');";

    $sql['insert'][] = "INSERT INTO `mst_item_status` (`item_status_id`, `item_status_name`, `rules`, `input_date`, `last_update`, `no_loan`, `skip_stock_take`) VALUES
('MIS', 'Missing', NULL, DATE(NOW()), DATE(NOW()), '1', '1');";

    return $this->slims->query($sql, ['drop', 'create', 'alter', 'insert'], 13);
  }

  /**
   * Upgrade role to v5.0.0
   *
   * @return mixed
   */
  function upgrade_role_14()
  {
    $sql['alter'][] = "ALTER TABLE  `mst_topic` ADD  `classification` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT  'Classification Code' AFTER  `auth_list` ;";
    $sql['alter'][] = "ALTER TABLE `biblio` ADD `sor` VARCHAR( 200 ) COLLATE utf8_unicode_ci NULL AFTER `title` ;";
    $sql['insert'][] = "INSERT INTO `setting` (`setting_id`, `setting_name`, `setting_value`) VALUES (NULL , 'ignore_holidays_fine_calc', 'b:0;');";

    return $this->slims->query($sql, ['alter', 'insert'], 14);
  }

  /**
   * Upgrade role to v7.0.0
   *
   * @return mixed
   */
  function upgrade_role_15()
  {
    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `biblio_id` int(11) NOT NULL,
  `member_id` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `comment` text COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

    $sql['insert'][] = "INSERT IGNORE INTO `setting` (`setting_id`, `setting_name`, `setting_value`)
VALUES (18, 'barcode_print_settings', 'a:12:{s:19:\"barcode_page_margin\";d:0.200000000000000011102230246251565404236316680908203125;s:21:\"barcode_items_per_row\";i:3;s:20:\"barcode_items_margin\";d:0.1000000000000000055511151231257827021181583404541015625;s:17:\"barcode_box_width\";i:7;s:18:\"barcode_box_height\";i:5;s:27:\"barcode_include_header_text\";i:1;s:17:\"barcode_cut_title\";i:50;s:19:\"barcode_header_text\";s:0:\"\";s:13:\"barcode_fonts\";s:41:\"Arial, Verdana, Helvetica, ''Trebuchet MS''\";s:17:\"barcode_font_size\";i:11;s:13:\"barcode_scale\";i:70;s:19:\"barcode_border_size\";i:1;}'),
(19, 'label_print_settings', 'a:10:{s:11:\"page_margin\";d:0.200000000000000011102230246251565404236316680908203125;s:13:\"items_per_row\";i:3;s:12:\"items_margin\";d:0.05000000000000000277555756156289135105907917022705078125;s:9:\"box_width\";i:8;s:10:\"box_height\";d:3.29999999999999982236431605997495353221893310546875;s:19:\"include_header_text\";i:1;s:11:\"header_text\";s:0:\"\";s:5:\"fonts\";s:41:\"Arial, Verdana, Helvetica, ''Trebuchet MS''\";s:9:\"font_size\";i:11;s:11:\"border_size\";i:1;}'),
(20, 'membercard_print_settings', 'a:1:{s:5:\"print\";a:1:{s:10:\"membercard\";a:61:{s:11:\"card_factor\";s:12:\"37.795275591\";s:21:\"card_include_id_label\";i:1;s:23:\"card_include_name_label\";i:1;s:22:\"card_include_pin_label\";i:1;s:23:\"card_include_inst_label\";i:0;s:24:\"card_include_email_label\";i:0;s:26:\"card_include_address_label\";i:1;s:26:\"card_include_barcode_label\";i:1;s:26:\"card_include_expired_label\";i:1;s:14:\"card_box_width\";d:8.5999999999999996447286321199499070644378662109375;s:15:\"card_box_height\";d:5.4000000000000003552713678800500929355621337890625;s:9:\"card_logo\";s:8:\"logo.png\";s:21:\"card_front_logo_width\";s:0:\"\";s:22:\"card_front_logo_height\";s:0:\"\";s:20:\"card_front_logo_left\";s:0:\"\";s:19:\"card_front_logo_top\";s:0:\"\";s:20:\"card_back_logo_width\";s:0:\"\";s:21:\"card_back_logo_height\";s:0:\"\";s:19:\"card_back_logo_left\";s:0:\"\";s:18:\"card_back_logo_top\";s:0:\"\";s:15:\"card_photo_left\";s:0:\"\";s:14:\"card_photo_top\";s:0:\"\";s:16:\"card_photo_width\";d:1.5;s:17:\"card_photo_height\";d:1.8000000000000000444089209850062616169452667236328125;s:23:\"card_front_header1_text\";s:19:\"Library Member Card\";s:28:\"card_front_header1_font_size\";s:2:\"12\";s:23:\"card_front_header2_text\";s:10:\"My Library\";s:28:\"card_front_header2_font_size\";s:2:\"12\";s:22:\"card_back_header1_text\";s:10:\"My Library\";s:27:\"card_back_header1_font_size\";s:2:\"12\";s:22:\"card_back_header2_text\";s:35:\"My Library Full Address and Website\";s:27:\"card_back_header2_font_size\";s:1:\"5\";s:17:\"card_header_color\";s:7:\"#0066FF\";s:18:\"card_bio_font_size\";s:2:\"11\";s:20:\"card_bio_font_weight\";s:4:\"bold\";s:20:\"card_bio_label_width\";s:3:\"100\";s:9:\"card_city\";s:9:\"City Name\";s:10:\"card_title\";s:15:\"Library Manager\";s:14:\"card_officials\";s:14:\"Librarian Name\";s:17:\"card_officials_id\";s:12:\"Librarian ID\";s:15:\"card_stamp_file\";s:9:\"stamp.png\";s:19:\"card_signature_file\";s:13:\"signature.png\";s:15:\"card_stamp_left\";s:0:\"\";s:14:\"card_stamp_top\";s:0:\"\";s:16:\"card_stamp_width\";s:0:\"\";s:17:\"card_stamp_height\";s:0:\"\";s:13:\"card_exp_left\";s:0:\"\";s:12:\"card_exp_top\";s:0:\"\";s:14:\"card_exp_width\";s:0:\"\";s:15:\"card_exp_height\";s:0:\"\";s:18:\"card_barcode_scale\";i:100;s:17:\"card_barcode_left\";s:0:\"\";s:16:\"card_barcode_top\";s:0:\"\";s:18:\"card_barcode_width\";s:0:\"\";s:19:\"card_barcode_height\";s:0:\"\";s:10:\"card_rules\";s:120:\"<ul><li>This card is published by Library.</li><li>Please return this card to its owner if you found it.</li></ul>\";s:20:\"card_rules_font_size\";s:1:\"8\";s:12:\"card_address\";s:76:\"My Library<br />website: http://slims.web.id, email : librarian@slims.web.id\";s:22:\"card_address_font_size\";s:1:\"7\";s:17:\"card_address_left\";s:0:\"\";s:16:\"card_address_top\";s:0:\"\";}}}');";

    $sql['update'][] = "UPDATE `setting` SET `setting_value`='a:2:{s:5:\"theme\";s:7:\"default\";s:3:\"css\";s:26:\"template/default/style.css\";}'
  WHERE `setting_name`='template';";

    $sql['update'][] = "UPDATE `setting` SET `setting_value`='a:2:{s:5:\"theme\";s:7:\"default\";s:3:\"css\";s:32:\"admin_template/default/style.css\";}'
  WHERE `setting_name`='admin_template';";

    $sql['update'][] = "UPDATE `content` SET `content_desc`='<div class=\"container admin_home\">\r\n<div class=\"row\">\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Bibliography</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon biblioIcon notAJAX\" href=\"index.php?mod=bibliography\"></a></div>\r\n<div class=\"col-sm-8\">The Bibliography module lets you manage your library bibliographical data. It also include collection items management to manage a copies of your library collection so it can be used in library circulation.</div>\r\n</div>\r\n</div>\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Circulation</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon circulationIcon notAJAX\" href=\"index.php?mod=circulation\"></a></div>\r\n<div class=\"col-sm-8\">The Circulation module is used for doing library circulation transaction such as collection loans and return. In this module you can also create loan rules that will be used in loan transaction proccess.</div>\r\n</div>\r\n</div>\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Membership</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon memberIcon notAJAX\" href=\"index.php?mod=membership\"></a></div>\r\n<div class=\"col-sm-8\">The Membership module lets you manage library members such adding, updating and also removing. You can also manage membership type in this module.</div>\r\n</div>\r\n</div>\r\n</div>\r\n<div class=\"row\">\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Stock Take</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon stockTakeIcon notAJAX\" href=\"index.php?mod=stock_take\"></a></div>\r\n<div class=\"col-sm-8\">The Stock Take module is the easy way to do Stock Opname for your library collections. Follow several steps that ease your pain in Stock Opname proccess.</div>\r\n</div>\r\n</div>\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Serial Control</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon serialIcon notAJAX\" href=\"index.php?mod=serial_control\"></a></div>\r\n<div class=\"col-sm-8\">Serial Control module help you manage library''s serial publication subscription. You can track issues for each subscription.</div>\r\n</div>\r\n</div>\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Reporting</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon reportIcon notAJAX\" href=\"index.php?mod=reporting\"></a></div>\r\n<div class=\"col-sm-8\">Reporting lets you view various type of reports regardings membership data, circulation data and bibliographic data. All compiled on-the-fly from current library database.</div>\r\n</div>\r\n</div>\r\n</div>\r\n<div class=\"row\">\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>Master File</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon masterFileIcon notAJAX\" href=\"index.php?mod=master_file\"></a></div>\r\n<div class=\"col-sm-8\">The Master File modules lets you manage referential data that will be used by another modules. It include Authority File management such as Authority, Subject/Topic List, GMD and other data.</div>\r\n</div>\r\n</div>\r\n<div class=\"col-xs-6 col-md-4\">\r\n<h3>System</h3>\r\n<div class=\"row\">\r\n<div class=\"col-sm-2\"><a class=\"icon systemIcon notAJAX\" href=\"index.php?mod=system\"></a></div>\r\n<div class=\"col-sm-8\">The System module is used to configure application globally, manage index, manage librarian, and also backup database.</div>\r\n</div>\r\n</div>\r\n</div>\r\n</div>'
  WHERE `content_path`='adminhome';";

    $sql['alter'][] = "ALTER TABLE `user` ADD `email` VARCHAR( 200 ) NULL DEFAULT NULL AFTER `passwd` ,
ADD `user_type` SMALLINT( 2 ) NULL DEFAULT NULL AFTER `email` ,
ADD `user_image` VARCHAR( 250 ) NULL DEFAULT NULL AFTER `user_type` ,
ADD `social_media` TEXT NULL AFTER `user_image`;";

    return $this->slims->query($sql, ['create', 'alter', 'insert', 'update'], 15);
  }

  /**
   * Upgrade role to v8.0.0
   *
   * @return mixed
   */
  function upgrade_role_16()
  {
    $sql['delete'][] = "DELETE FROM `setting` WHERE `setting`.`setting_name` = 'barcode_encoding';";
    $sql['update'][] = "UPDATE `setting` SET `setting_value` = 'a:2:{s:5:\"theme\";s:7:\"default\";s:3:\"css\";s:26:\"template/default/style.css\";}' WHERE `setting_id` = 3;";

    $sql['alter'][] = "ALTER TABLE `biblio` ADD `content_type_id` INT NULL DEFAULT NULL AFTER `spec_detail_info`,
  ADD `media_type_id` INT NULL DEFAULT NULL AFTER `content_type_id`,
  ADD `carrier_type_id` INT NULL DEFAULT NULL AFTER `media_type_id`,
  ADD INDEX (`content_type_id`, `media_type_id`, `carrier_type_id`) ;";

    $sql['alter'][] = "ALTER TABLE `search_biblio` ADD `content_type` VARCHAR(100) NULL DEFAULT NULL AFTER `image`, ADD `media_type` VARCHAR(100) NULL DEFAULT NULL AFTER `content_type`,
  ADD `carrier_type` VARCHAR(100) NULL DEFAULT NULL AFTER `media_type`, ADD INDEX (`content_type`, `media_type`, `carrier_type`);";

    $sql['alter'][] = "ALTER TABLE `user` CHANGE `passwd` `passwd` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $sql['alter'][] = "ALTER TABLE `member` CHANGE `mpasswd` `mpasswd` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;";

    /*--
    -- Table structure for table `mst_carrier_type`
    --*/

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_carrier_type` (
`id` int(11) NOT NULL,
  `carrier_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=56 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

    /*--
    -- Dumping data for table `mst_carrier_type`
    --*/

    $sql['insert'][] = "INSERT INTO `mst_carrier_type` (`id`, `carrier_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'audio cartridge', 'sg', 'g', now(), now()),
(2, 'audio cylinder', 'se', 'e', now(), now()),
(3, 'audio disc', 'sd', 'd', now(), now()),
(4, 'sound track reel', 'si', 'i', now(), now()),
(5, 'audio roll', 'sq', 'q', now(), now()),
(6, 'audiocassette', 'ss', 's', now(), now()),
(7, 'audiotape reel', 'st', 't', now(), now()),
(8, 'other (audio)', 'sz', 'z', now(), now()),
(9, 'computer card', 'ck', 'k', now(), now()),
(10, 'computer chip cartridge', 'cb', 'b', now(), now()),
(11, 'computer disc', 'cd', 'd', now(), now()),
(12, 'computer disc cartridge', 'ce', 'e', now(), now()),
(13, 'computer tape cartridge', 'ca', 'a', now(), now()),
(14, 'computer tape cassette', 'cf', 'f', now(), now()),
(15, 'computer tape reel', 'ch', 'h', now(), now()),
(16, 'online resource', 'cr', 'r', now(), now()),
(17, 'other (computer)', 'cz', 'z', now(), now()),
(18, 'aperture card', 'ha', 'a', now(), now()),
(19, 'microfiche', 'he', 'e', now(), now()),
(20, 'microfiche cassette', 'hf', 'f', now(), now()),
(21, 'microfilm cartridge', 'hb', 'b', now(), now()),
(22, 'microfilm cassette', 'hc', 'c', now(), now()),
(23, 'microfilm reel', 'hd', 'd', now(), now()),
(24, 'microfilm roll', 'hj', 'j', now(), now()),
(25, 'microfilm slip', 'hh', 'h', now(), now()),
(26, 'microopaque', 'hg', 'g', now(), now()),
(27, 'other (microform)', 'hz', 'z', now(), now()),
(28, 'microscope slide', 'pp', 'p', now(), now()),
(29, 'other (microscope)', 'pz', 'z', now(), now()),
(30, 'film cartridge', 'mc', 'c', now(), now()),
(31, 'film cassette', 'mf', 'f', now(), now()),
(32, 'film reel', 'mr', 'r', now(), now()),
(33, 'film roll', 'mo', 'o', now(), now()),
(34, 'filmslip', 'gd', 'd', now(), now()),
(35, 'filmstrip', 'gf', 'f', now(), now()),
(36, 'filmstrip cartridge', 'gc', 'c', now(), now()),
(37, 'overhead transparency', 'gt', 't', now(), now()),
(38, 'slide', 'gs', 's', now(), now()),
(39, 'other (projected image)', 'mz', 'z', now(), now()),
(40, 'stereograph card', 'eh', 'h', now(), now()),
(41, 'stereograph disc', 'es', 's', now(), now()),
(42, 'other (stereographic)', 'ez', 'z', now(), now()),
(43, 'card', 'no', 'o', now(), now()),
(44, 'flipchart', 'nn', 'n', now(), now()),
(45, 'roll', 'na', 'a', now(), now()),
(46, 'sheet', 'nb', 'b', now(), now()),
(47, 'volume', 'nc', 'c', now(), now()),
(48, 'object', 'nr', 'r', now(), now()),
(49, 'other (unmediated)', 'nz', '', now(), now()),
(50, 'video cartridge', 'vc', '', now(), now()),
(51, 'videocassette', 'vf', '', now(), now()),
(52, 'videodisc', 'vd', '', now(), now()),
(53, 'videotape reel', 'vr', '', now(), now()),
(54, 'other (video)', 'vz', '', now(), now()),
(55, 'unspecified', 'zu', 'u', now(), now());";

    /*--
    -- Table structure for table `mst_content_type`
    --*/

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_content_type` (
`id` int(11) NOT NULL,
  `content_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

    /*--
    -- Dumping data for table `mst_content_type`
    --*/

    $sql['insert'][] = "INSERT INTO `mst_content_type` (`id`, `content_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'cartographic dataset', 'crd', 'e', now(), now()),
(2, 'cartographic image', 'cri', 'e', now(), now()),
(3, 'cartographic moving image', 'crm', 'e', now(), now()),
(4, 'cartographic tactile image', 'crt', 'e', now(), now()),
(5, 'cartographic tactile three-dimensional form', 'crn', 'e', now(), now()),
(6, 'cartographic three-dimensional form', 'crf', 'e', now(), now()),
(7, 'computer dataset', 'cod', 'm', now(), now()),
(8, 'computer program', 'cop', 'm', now(), now()),
(9, 'notated movement', 'ntv', 'a', now(), now()),
(10, 'notated music', 'ntm', 'c', now(), now()),
(11, 'performed music', 'prm', 'j', now(), now()),
(12, 'sounds', 'snd', 'i', now(), now()),
(13, 'spoken word', 'spw', 'i', now(), now()),
(14, 'still image', 'sti', 'k', now(), now()),
(15, 'tactile image', 'tci', 'k', now(), now()),
(16, 'tactile notated music', 'tcm', 'c', now(), now()),
(17, 'tactile notated movement', 'tcn', 'a', now(), now()),
(18, 'tactile text', 'tct', 'a', now(), now()),
(19, 'tactile three-dimensional form', 'tcf', 'r', now(), now()),
(20, 'text', 'txt', 'a', now(), now()),
(21, 'three-dimensional form', 'tdf', 'r', now(), now()),
(22, 'three-dimensional moving image', 'tdm', 'g', now(), now()),
(23, 'two-dimensional moving image', 'tdi', 'g', now(), now()),
(24, 'other', 'xxx', 'o', now(), now()),
(25, 'unspecified', 'zzz', ' ', now(), now());";

    /*--
    -- Table structure for table `mst_media_type`
    --*/

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_media_type` (
`id` int(11) NOT NULL,
  `media_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `code2` char(1) COLLATE utf8_unicode_ci NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

    /*--
    -- Dumping data for table `mst_media_type`
    --*/

    $sql['insert'][] = "INSERT INTO `mst_media_type` (`id`, `media_type`, `code`, `code2`, `input_date`, `last_update`) VALUES
(1, 'audio', 's', 's', now(), now()),
(2, 'computer', 'c', 'c', now(), now()),
(3, 'microform', 'h', 'h', now(), now()),
(4, 'microscopic', 'p', ' ', now(), now()),
(5, 'projected', 'g', 'g', now(), now()),
(6, 'stereographic', 'e', ' ', now(), now()),
(7, 'unmediated', 'n', 't', now(), now()),
(8, 'video', 'v', 'v', now(), now()),
(9, 'other', 'x', 'z', now(), now()),
(10, 'unspecified', 'z', 'z', now(), now());";

    /*--
    -- Table structure for table `mst_relation_term`
    --*/

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_relation_term` (
`ID` int(11) NOT NULL,
  `rt_id` varchar(11) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `rt_desc` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=7 ;";

    /*--
    -- Dumping data for table `mst_relation_term`
    --*/

    $sql['insert'][] = "INSERT INTO `mst_relation_term` (`ID`, `rt_id`, `rt_desc`) VALUES
(1, 'U', 'Use'),
(2, 'UF', 'Use For'),
(3, 'BT', 'Broader Term'),
(4, 'NT', 'Narrower Term'),
(5, 'RT', 'Related Term'),
(6, 'SA', 'See Also');";


    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `biblio_relation` (
  `biblio_id` int(11) NOT NULL DEFAULT '0',
  `rel_biblio_id` int(11) NOT NULL DEFAULT '0',
  `rel_type` int(1) DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

    /*--
    -- Indexes for dumped tables
    --

    --
    -- Indexes for table `biblio_relation`
    --*/
    $sql['alter'][] = "ALTER TABLE `biblio_relation`
 ADD PRIMARY KEY (`biblio_id`,`rel_biblio_id`);";

    /*--
    -- Table structure for table `mst_voc_ctrl`
    --*/

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `mst_voc_ctrl` (
  `topic_id` int(11) NOT NULL,
`vocabolary_id` int(11) NOT NULL,
  `rt_id` varchar(11) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `related_topic_id` varchar(250) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=43 ;";


    $sql['insert'][] = "INSERT IGNORE INTO `setting` (`setting_name`, `setting_value`) VALUES
('shortcuts_1', 'a:6:{i:0;s:42:\"Daftar Bibliografi|/bibliography/index.php\";i:1;s:61:\"Tambah Bibliografi Baru|/bibliography/index.php?action=detail\";i:2;s:51:\"Mulai Transaksi|/circulation/index.php?action=start\";i:3;s:48:\"Pengembalian Kilat|/circulation/quick_return.php\";i:4;s:42:\"Lihat Daftar Anggota|/membership/index.php\";i:5;s:37:\"Shortcut Setting|/system/shortcut.php\";}');";

    /*--
    -- Indexes for table `mst_relation_term`
    --*/
    $sql['alter'][] = "ALTER TABLE `mst_relation_term`
 ADD PRIMARY KEY (`ID`);";

    /*--
    -- Indexes for table `mst_voc_ctrl`
    --*/
    $sql['alter'][] = "ALTER TABLE `mst_voc_ctrl`
 ADD PRIMARY KEY (`vocabolary_id`);";

    /*--
    -- AUTO_INCREMENT for table `mst_relation_term`
    --*/
    $sql['alter'][] = "ALTER TABLE `mst_relation_term`
MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7;";

    /*--
    -- AUTO_INCREMENT for table `mst_voc_ctrl`
    --*/
    $sql['alter'][] = "ALTER TABLE `mst_voc_ctrl`
MODIFY `vocabolary_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1;";

    $sql['alter'][] = "ALTER TABLE `mst_carrier_type`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `media_type` (`carrier_type`), ADD KEY `code` (`code`);";

    $sql['alter'][] = "ALTER TABLE `mst_content_type`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `content_type` (`content_type`), ADD KEY `code` (`code`);";

    $sql['alter'][] = "ALTER TABLE `mst_media_type`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `media_type` (`media_type`), ADD KEY `code` (`code`);";

    $sql['alter'][] = "ALTER TABLE `mst_carrier_type`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=56;";

    $sql['alter'][] = "ALTER TABLE `mst_content_type`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=26;";

    $sql['alter'][] = "ALTER TABLE `mst_media_type`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=11;";

    $sql['alter'][] = "ALTER TABLE `content` ADD `is_news` SMALLINT(1) NULL DEFAULT NULL AFTER `content_path`;";

    return $this->slims->query($sql, ['create', 'alter', 'delete', 'insert', 'update'], 16);
  }

  /**
   * Upgrade role to v8.2.0
   *
   * @return mixed
   */
  function upgrade_role_17()
  {

    $sql['insert'][] = "INSERT IGNORE INTO `setting` (`setting_name`, `setting_value`) VALUES
('enable_visitor_limitation', 's:1:\"0\";'),
('time_visitor_limitation', 's:2:\"60\";');";

    $sql['alter'][] = "ALTER TABLE `mst_voc_ctrl` CHANGE `vocabolary_id` `vocabolary_id` INT(11) NOT NULL AUTO_INCREMENT;";

    return $this->slims->query($sql, ['alter', 'insert'], 17);
  }

  /**
   * Upgrade role to v8.3.0
   *
   * @return mixed
   */
  function upgrade_role_18()
  {
    $sql['alter'][] = "ALTER TABLE  `biblio` ADD  `uid` INT( 11 ) NULL DEFAULT NULL ,
ADD INDEX (  `uid` ) ;";

    $sql['alter'][] = "ALTER TABLE  `item` ADD  `uid` INT( 11 ) NULL DEFAULT NULL ,
ADD INDEX (  `uid` ) ;";

    $sql['create'][] = "CREATE TABLE `mst_servers` (
  `server_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uri` text COLLATE utf8_unicode_ci NOT NULL, 
  `server_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - p2p server; 2 - z3950; 3 - z3950  SRU',
  `input_date` datetime NOT NULL,
  `last_update` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql['alter'][] = "ALTER TABLE `mst_servers` ADD PRIMARY KEY (`server_id`);";

    $sql['alter'][] = "ALTER TABLE `mst_servers` MODIFY `server_id` int(11) NOT NULL AUTO_INCREMENT;";

    $sql['alter'][] = "ALTER TABLE `mst_voc_ctrl` ADD `scope` TEXT NULL DEFAULT NULL; ";

    return $this->slims->query($sql, ['create', 'alter'], 18);
  }

  /**
   * Upgrade role to v8.3.1
   *
   * @return mixed
   */
  function upgrade_role_19()
  {
    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `biblio_log` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;";

    $sql['alter'][] = "ALTER TABLE  `loan` ADD  `input_date` DATETIME NULL DEFAULT NULL ,
ADD  `last_update` DATETIME NULL DEFAULT NULL ,
ADD  `uid` INT( 11 ) NULL DEFAULT NULL ,
ADD INDEX (  `input_date` ,  `last_update` ,  `uid` ) ;";

    return $this->slims->query($sql, ['create', 'alter'], 19);
  }

  /**
   * Upgrade role to v9.0.0
   *
   * @return array
   */
  function upgrade_role_20()
  {
    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `loan_history` (
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

    $sql['insert'][] = "
        INSERT IGNORE INTO `loan_history` 
      (`loan_id`, 
        `item_code`, 
        `biblio_id`,
        `member_id`, 
        `loan_date`, 
        `due_date`, 
        `renewed`, 
        `is_lent`, 
        `is_return`, 
        `return_date`,
        `input_date`,
        `last_update`,
        `title`,
        `call_number`,
        `classification`,
        `gmd_name`,
        `language_name`,
        `location_name`,
        `collection_type_name`,
        `member_name`,
        `member_type_name`
        )
    (SELECT l.loan_id,
        l.item_code,
        b.biblio_id,
        l.member_id,
        l.loan_date,
        l.due_date,
        l.renewed,
        l.is_lent,
        l.is_return,
        l.return_date,
        IF(day(l.input_date) IS NULL,NULL, l.input_date),
        IF(day(l.last_update) IS NULL,NULL, l.last_update),
        b.title,
        IF(i.call_number IS NULL,b.call_number,i.call_number),
        b.classification,
        g.gmd_name,
        ml.language_name,
        mlc.location_name,
        mct.coll_type_name,
        m.member_name,
        mmt.member_type_name 
    FROM loan l LEFT JOIN item i ON i.item_code=l.item_code
    LEFT JOIN biblio b ON b.biblio_id=i.biblio_id
    LEFT JOIN mst_gmd g ON g.gmd_id=b.gmd_id
    LEFT JOIN mst_language ml ON ml.language_id=b.language_id 
    LEFT JOIN mst_location mlc ON mlc.location_id=i.location_id
    LEFT JOIN member m ON m.member_id=l.member_id
    LEFT JOIN mst_coll_type mct ON mct.coll_type_id=i.coll_type_id
    LEFT JOIN mst_member_type mmt ON mmt.member_type_id=m.member_type_id WHERE m.member_id IS NOT NULL AND b.biblio_id IS NOT NULL);";

    $error = $this->slims->query($sql, ['create', 'insert'], 20);

    // create trigger
    $query_trigger[] = "
    DROP TRIGGER IF EXISTS `delete_loan_history`;";
    $query_trigger[] = "
    CREATE TRIGGER `delete_loan_history` AFTER DELETE ON `loan`
     FOR EACH ROW DELETE FROM `loan_history` WHERE loan_id=OLD.loan_id;";

    $query_trigger[] = "
    DROP TRIGGER IF EXISTS `update_loan_history`;";
    $query_trigger[] = "
    CREATE TRIGGER `update_loan_history` AFTER UPDATE ON `loan`
     FOR EACH ROW UPDATE loan_history 
    SET is_lent=NEW.is_lent,
    is_return=NEW.is_return,
    renewed=NEW.renewed,
    return_date=NEW.return_date,
    last_update=NEW.last_update
    WHERE loan_id=NEW.loan_id;";

    $query_trigger[] = "
    DROP TRIGGER IF EXISTS `insert_loan_history`;";

    $query_trigger[] = "
    CREATE TRIGGER `insert_loan_history` AFTER INSERT ON `loan`
     FOR EACH ROW INSERT INTO loan_history
     SET loan_id=NEW.loan_id,
     item_code=NEW.item_code,
     member_id=NEW.member_id,
     loan_date=NEW.loan_date,
     due_date=NEW.due_date,
     renewed=NEW.renewed,
     is_lent=NEW.is_lent,
     is_return=NEW.is_return,
     return_date=NEW.return_date,
     input_date=NEW.input_date,
     last_update=NEW.last_update,
     title=(SELECT b.title FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id WHERE i.item_code=NEW.item_code),
     biblio_id=(SELECT b.biblio_id FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id WHERE i.item_code=NEW.item_code),
     call_number=(SELECT IF(i.call_number IS NULL, b.call_number,i.call_number) FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id WHERE i.item_code=NEW.item_code),
     classification=(SELECT b.classification FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id WHERE i.item_code=NEW.item_code),
     gmd_name=(SELECT g.gmd_name FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id LEFT JOIN mst_gmd g ON g.gmd_id=b.gmd_id WHERE i.item_code=NEW.item_code),
     language_name=(SELECT l.language_name FROM biblio b LEFT JOIN item i ON i.biblio_id=b.biblio_id LEFT JOIN mst_language l ON b.language_id=l.language_id WHERE i.item_code=NEW.item_code),
     location_name=(SELECT ml.location_name FROM item i LEFT JOIN mst_location ml ON i.location_id=ml.location_id WHERE i.item_code=NEW.item_code),
     collection_type_name=(SELECT mct.coll_type_name FROM mst_coll_type mct LEFT JOIN item i ON i.coll_type_id=mct.coll_type_id WHERE i.item_code=NEW.item_code),
     member_name=(SELECT m.member_name FROM member m WHERE m.member_id=NEW.member_id),
     member_type_name=(SELECT mmt.member_type_name FROM mst_member_type mmt LEFT JOIN member m ON m.member_type_id=mmt.member_type_id WHERE m.member_id=NEW.member_id);";

    // $error_trigger = $this->slims->queryTrigger($query_trigger,20);
    $error = array_merge($error, /*$error_trigger*/);

    // fix mst_topic:classification
    $fix_classification = $this->slims->changeColumn('mst_topic', [
      'field' => 'classification',
      'type' => 'varchar(50)',
      'null' => true,
      'default' => null
    ]);

    if ($fix_classification) $error[] = $fix_classification;

    return $error;
  }

  /**
   * Upgrade role to v9.1.0
   */
  function upgrade_role_21() {}

  /**
   * Upgrade role to v9.1.1
   */
  function upgrade_role_22() {}

  /**
   * Upgrade role to v9.2.0
   */
  function upgrade_role_23()
  {
    $sql['alter'][] = "ALTER TABLE `user` ADD `forgot` VARCHAR(80) COLLATE 'utf8_unicode_ci' DEFAULT NULL AFTER `groups`;";

    $sql['alter'][] = "ALTER TABLE `user` ADD `admin_template` text COLLATE 'utf8_unicode_ci' DEFAULT NULL AFTER `forgot`;";

    $sql['alter'][] = "ALTER TABLE `backup_log` CHANGE `backup_time` `backup_time` datetime NULL AFTER `user_id`;";

    $sql['alter'][] = "ALTER TABLE `user` CHANGE `input_date` `input_date` date NULL AFTER `groups`;";

    $sql['alter'][] = "ALTER TABLE `stock_take_item` CHANGE `checked_by` `checked_by` varchar(50) COLLATE 'utf8_unicode_ci' NULL AFTER `status`;";

    $sql['alter'][] = "ALTER TABLE `biblio_author` ADD INDEX `biblio_id` (`biblio_id`), ADD INDEX `author_id` (`author_id`);";

    $sql['alter'][] = "ALTER TABLE `biblio_topic` ADD INDEX `biblio_id` (`biblio_id`), ADD INDEX `topic_id` (`topic_id`);";

    $sql['insert'][] = "INSERT INTO `setting` (`setting_name`, `setting_value`) VALUES ('logo_image', NULL);";

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
      `member_id` int(11) DEFAULT NULL,
      `user_id` int(11) DEFAULT NULL,
      `client_ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      PRIMARY KEY (`filelog_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

    $error = $this->slims->query($sql, ['alter', 'insert', 'create'], 23);

    return $error;
  }

  /**
   * Upgrade role to v9.2.1
   */
  function upgrade_role_24() {}

  /**
   * Upgrade role to v9.2.2
   */
  function upgrade_role_25() {}

  /**
   * Upgrade role to v9.3.0
   */
  function upgrade_role_26()
  {
    $sql['create'][] = "
        CREATE TABLE IF NOT EXISTS `plugins` (
          `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
          `path` text COLLATE utf8mb4_unicode_ci NOT NULL,
          `created_at` datetime NOT NULL,
          `uid` int(11) NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql['alter'][] = "ALTER TABLE `group_access` ADD `menus` json NULL AFTER `module_id`;";

    $sql['alter'][] = "ALTER TABLE `biblio_attachment` ADD `placement` enum('link','popup','embed') COLLATE 'utf8_unicode_ci' NULL AFTER `file_id`;";

    $sql['alter'][] = "ALTER TABLE `system_log` ADD `sub_module` varchar(50) COLLATE 'utf8_unicode_ci' NULL AFTER `log_location`, ADD `action` varchar(50) COLLATE 'utf8_unicode_ci' NULL AFTER `sub_module`;";

    $sql['alter'][] = "ALTER TABLE `files_read` CHANGE `member_id` `member_id` varchar(20) NULL AFTER `date_read`;";

    $sql['alter'][] = "ALTER TABLE `backup_log` CHANGE `backup_file` `backup_file` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;";

    return $this->slims->query($sql, ['create', 'alter'], 26);
  }

  /**
   * Upgrade role to v9.3.1
   */
  function upgrade_role_27() {}

  /**
   * Upgrade role to v9.4.0
   */
  function upgrade_role_28()
  {
    $sql['alter'][] = "ALTER TABLE `plugins`
                                ADD `options` json NULL AFTER `path`,
                                ADD `updated_at` datetime NULL AFTER `created_at`,
                                ADD `deleted_at` datetime NULL AFTER `updated_at`;";
    $sql['alter'][] = "ALTER TABLE `plugins` ADD UNIQUE `id` (`id`);";

    return $this->slims->query($sql, ['alter'], 28);
  }

  /**
   * Upgrade role to v9.4.1
   */
  function upgrade_role_29() {}

  /**
   * Upgrade role to v9.4.2
   */
  function upgrade_role_30() {}

  /**
   * Upgrade role to v9.5.0
   */
  function upgrade_role_31()
  {
    $sql['alter'][] = "ALTER TABLE `files` ADD `file_key` text COLLATE 'utf8_unicode_ci' NULL AFTER `file_desc`;";
    if ($_POST['oldVersion'] ?? 0 > 19) {
      $sql['alter'][] = "ALTER TABLE `biblio` DROP `update_date`;";
    }
    $sql['alter'][] = "ALTER TABLE `mst_topic` CHANGE `classification` `classification` varchar(50) COLLATE 'utf8_unicode_ci' NULL COMMENT 'Classification Code' AFTER `auth_list`;";
    $sql['alter'][] = "ALTER TABLE `content` ADD `is_draft` smallint(1) NULL DEFAULT '0' AFTER `is_news`, ADD `publish_date` date NULL AFTER `is_draft`;";

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `index_words` (
          `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `word` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
          `num_hits` int NOT NULL,
          `doc_hits` int NOT NULL
        ) ENGINE='MyISAM' COLLATE 'utf8mb4_unicode_ci';";

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

    return $this->slims->query($sql, ['create', 'alter'], 31);
  }
  /**
   * Upgrade role to v9.5.1
   */
  function upgrade_role_32() {}

  /**
   * Upgrade role to v9.5.2
   */
  function upgrade_role_33()
  {
    $sql['alter'][] = 'ALTER TABLE `search_biblio` DROP INDEX `title`, ADD FULLTEXT `title` (`title`, `series_title`)';
    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `biblio_mark` (
          `id` varchar(32) NOT NULL,
          `member_id` varchar(20) NOT NULL,
          `biblio_id` int(11) NOT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          UNIQUE KEY `id` (`id`),
          KEY `member_id_idx` (`member_id`),
          KEY `biblio_id_idx` (`biblio_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    return $this->slims->query($sql, ['create', 'alter'], 33);
  }

  /**
   * Upgrade role to v9.6.0
   */
  function upgrade_role_34()
  {
    $sql['alter'][] = "ALTER TABLE `user` ADD `2fa` text COLLATE 'utf8_unicode_ci' NULL AFTER `passwd`;";
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
    $sql['alter'][] = "ALTER TABLE `visitor_count` ADD `room_code` varchar(5) COLLATE 'utf8_unicode_ci' NULL AFTER `institution`;";
    $sql['alter'][] = "ALTER TABLE `visitor_count` ADD INDEX `room_code` (`room_code`);";
    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `cache` (
          `name` varchar(64) NOT NULL,
          `contents` text NOT NULL,
          `created_at` datetime NOT NULL,
          `updated_at` datetime NOT NULL,
          `expired_at` datetime DEFAULT NULL,
          UNIQUE KEY `name` (`name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $sql['update'][] = "UPDATE `mst_item_status` SET `skip_stock_take` = 1 WHERE `item_status_id` IN ('NL','R')";

    return $this->slims->query($sql, ['create', 'alter', 'update'], 34);
  }

  /**
   * Upgrade role to v9.6.1
   */
  function upgrade_role_35() {}

  /**
   * Upgrade role to v9.7.0
   */
  function upgrade_role_36()
  {
    $sql['create'][] = "CREATE TABLE `user_tokens` (
            `id` int NOT NULL AUTO_INCREMENT,
            `selector` varchar(255) NOT NULL,
            `hashed_validator` varchar(255) NOT NULL,
            `user_id` int NOT NULL,
            `expires_at` datetime NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql['create'][] = "CREATE TABLE IF NOT EXISTS `item_custom` (
          `item_id` INT NOT NULL ,
          PRIMARY KEY ( `item_id` )
        ) ENGINE=MyISAM COMMENT = 'one to one relation with real item table';";

    $sql['alter'][] = "ALTER TABLE `biblio` ADD INDEX `publisher_id` (`publisher_id`);";
    $sql['alter'][] = "ALTER TABLE `biblio` CHANGE `source` `source` varchar(10) COLLATE 'utf8mb3_unicode_ci' NULL AFTER `language_id`;";
    $sql['alter'][] = "ALTER TABLE `member` CHANGE `last_login_ip` `last_login_ip` varchar(50) COLLATE 'utf8mb3_unicode_ci' NULL AFTER `last_login`;";
    $sql['alter'][] = "ALTER TABLE `user` CHANGE `last_login_ip` `last_login_ip` varchar(50) COLLATE 'utf8mb3_unicode_ci' NULL AFTER `last_login`;";
    $sql['alter'][] = "ALTER IGNORE TABLE mst_voc_ctrl ADD UNIQUE idx_heading(topic_id, related_topic_id);";
    return $this->slims->query($sql, ['create', 'alter'], 36);
  }

  /**
   * Upgrade role to v9.7.1
   */
  function upgrade_role_37() {}

  /**
   * Upgrade role to v9.7.2
   */
  function upgrade_role_38()
  {
    // Use direct query execution for DROP TRIGGER statements for backward compatibility
    $dbs = $this->slims->getDb();

    $dropTriggers = [
      "DROP TRIGGER IF EXISTS `delete_loan_history`",
      "DROP TRIGGER IF EXISTS `update_loan_history`",
      "DROP TRIGGER IF EXISTS `insert_loan_history`"
    ];

    $error = [];

    // Execute DROP TRIGGER statements directly without prepared statements
    foreach ($dropTriggers as $triggerSql) {
      $result = mysqli_query($dbs, $triggerSql);
      if (!$result) {
        $error[] = "Error dropping trigger: " . mysqli_error($dbs);
      }
    }

    // Update submenu references from MD5 hash to menu keys
    try {
      $updatedCount = $this->updateSubMenu();

      if ($updatedCount > 0) {
        error_log("Successfully updated {$updatedCount} group_access records with new submenu format.");
      } else {
        error_log("No group_access records required submenu format updates.");
      }
    } catch (\Exception $e) {
      $errorMsg = "Error updating submenu format: " . $e->getMessage();
      error_log($errorMsg);
      $error[] = $errorMsg;
    }

    return $error;
  }

  /**
   * Upgrade role to v9.8.0
   */
  function upgrade_role_39()
  {
    // Optimize index
    $sql['alter'][] = "ALTER TABLE `index_words` ADD INDEX `idx_word` (`word`(250));";
    $sql['alter'][] = "ALTER TABLE `index_words` ADD INDEX `idx_word_hits` (`word`(245), `num_hits`);";
    $sql['alter'][] = "ALTER TABLE `index_documents` ADD INDEX `idx_word_location` (`word_id`, `location`, `hit_count`);";
    $sql['alter'][] = "ALTER TABLE `index_documents` ADD INDEX `idx_document_id` (`document_id`);";
    $sql['alter'][] = "ALTER TABLE `index_words` MODIFY COLUMN `word` VARCHAR(255) NOT NULL;";


    // Analyze index
    $sql['analyze'][] = "ANALYZE TABLE `index_words`;";
    $sql['analyze'][] = "ANALYZE TABLE `index_documents`;";

    // Optimize index
    $sql['optimize'][] = "OPTIMIZE TABLE `index_words`;";
    $sql['optimize'][] = "OPTIMIZE TABLE `index_documents`;";

    return $this->slims->query($sql, ['create', 'alter', 'analyze', 'optimize'], 39);
  }

  private function getSubMenuFromAllModules()
  {
    // get modules using mysqli directly
    $dbs = $this->slims->getDb();
    $sql = "SELECT * FROM mst_module";
    $query = mysqli_query($dbs, $sql);

    if (!$query) {
      error_log("Error getting modules: " . mysqli_error($dbs));
      return [];
    }

    $modules = [];
    while ($row = mysqli_fetch_assoc($query)) {
      $modules[] = $row;
    }

    $allMenus = [];
    $modulesPath = __DIR__ . '/../admin/modules';

    foreach ($modules as $module) {
      // Check if module has required fields
      if (!isset($module['module_path'])) {
        continue;
      }

      $moduleDir = $module['module_path'];
      $submenuFile = $modulesPath . '/' . $moduleDir . '/submenu.php';

      if (file_exists($submenuFile)) {
        // Initialize menu array for this module
        $menu = [];

        // Capture output and include the submenu file
        ob_start();
        try {
          // Create necessary variables that might be used in submenu files
          global $sysconf;

          // Mock session for checking user privileges
          if (!isset($_SESSION['uid'])) {
            $_SESSION['uid'] = 1; // Set as admin for upgrade context
          }

          include $submenuFile;

          // Add this module's menu to the collection
          if (!empty($menu)) {
            $allMenus[$moduleDir] = $menu;
          }
        } catch (\Exception $e) {
          // Log error but continue with other modules
          error_log("Error loading submenu for module {$moduleDir}: " . $e->getMessage());
        }

        // Clean output buffer
        ob_end_clean();
      }
    }

    return $allMenus;
  }

  private function updateSubMenu()
  {
    // Get database connection
    $dbs = $this->slims->getDb();

    // Get all submenus from all modules
    $allMenus = $this->getSubMenuFromAllModules();

    // Create mapping from MD5 hash to menu key
    $hashToKeyMapping = [];

    foreach ($allMenus as $moduleDir => $moduleMenus) {
      foreach ($moduleMenus as $menuKey => $menuData) {
        // Skip header items (they don't have URLs)
        if (isset($menuData[0]) && $menuData[0] === 'Header') {
          continue;
        }

        // Get the URL from menu data
        if (isset($menuData[1])) {
          $url = $menuData[1];

          // Calculate MD5 hash of the URL
          $urlHash = md5($url);

          // Map hash to menu key
          $hashToKeyMapping[$urlHash] = $menuKey;
        }
      }
    }

    // Get all group_access records that have menus using mysqli
    $sql = "SELECT * FROM group_access WHERE menus IS NOT NULL AND menus != ''";
    $query = mysqli_query($dbs, $sql);

    if (!$query) {
      error_log("Error getting group_access records: " . mysqli_error($dbs));
      return 0;
    }

    $groupAccesses = [];
    while ($row = mysqli_fetch_assoc($query)) {
      $groupAccesses[] = $row;
    }

    $updateCount = 0;

    foreach ($groupAccesses as $groupAccess) {
      $menusJson = $groupAccess['menus'];
      $menus = json_decode($menusJson, true);

      if (!is_array($menus)) {
        continue;
      }

      $updated = false;
      $newMenus = [];

      foreach ($menus as $menuItem) {
        // Check if this menu item is a hash that we can convert to a key
        if (isset($hashToKeyMapping[$menuItem])) {
          // Replace hash with menu key
          $newMenus[] = $hashToKeyMapping[$menuItem];
          $updated = true;
        } else {
          // Keep the original item (might already be a key or unknown hash)
          $newMenus[] = $menuItem;
        }
      }

      // Update database if any changes were made
      if ($updated) {
        $newMenusJson = json_encode($newMenus);

        // Use mysqli for update
        $escapedMenusJson = mysqli_real_escape_string($dbs, $newMenusJson);
        $groupId = intval($groupAccess['group_id']);
        $moduleId = intval($groupAccess['module_id']);

        $updateSql = "UPDATE group_access SET menus = '{$escapedMenusJson}' WHERE group_id = {$groupId} AND module_id = {$moduleId}";
        $result = mysqli_query($dbs, $updateSql);

        if ($result) {
          $updateCount++;
          error_log("Updated group_access: group_id={$groupAccess['group_id']}, module_id={$groupAccess['module_id']}");
        } else {
          error_log("Failed to update group_access: group_id={$groupAccess['group_id']}, module_id={$groupAccess['module_id']} - " . mysqli_error($dbs));
        }
      }
    }

    error_log("SubMenu update completed. Updated {$updateCount} records.");
    return $updateCount;
  }
}
