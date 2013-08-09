# For backward compatibilty with early versions
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

# 090-091

ALTER TABLE `{PREFIX}site_content` ADD COLUMN `publishedon` int(20) NOT NULL DEFAULT '0' COMMENT 'Date the document was published' AFTER `deletedby`;

ALTER TABLE `{PREFIX}site_content` ADD COLUMN `publishedby` int(10) NOT NULL DEFAULT '0' COMMENT 'ID of user who published the document' AFTER `publishedon`;

ALTER TABLE `{PREFIX}site_plugins` MODIFY COLUMN `properties` text COMMENT 'Default Properties';

ALTER TABLE `{PREFIX}site_snippets` MODIFY COLUMN `properties` text COMMENT 'Default Properties';

ALTER TABLE `{PREFIX}site_tmplvar_templates`
 DROP INDEX `idx_tmplvarid`,
 DROP INDEX `idx_templateid`,
 ADD PRIMARY KEY (`tmplvarid`, `templateid`);

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `view_unpublished` int(1) NOT NULL DEFAULT '0' AFTER `web_access_permissions`;

#091-092

#092-095

ALTER TABLE `{PREFIX}categories` MODIFY COLUMN `category` varchar(45) NOT NULL DEFAULT '';

ALTER TABLE `{PREFIX}categories` MODIFY COLUMN `category` varchar(45) NOT NULL DEFAULT '';

ALTER TABLE `{PREFIX}event_log` MODIFY COLUMN `source` varchar(50) NOT NULL DEFAULT '';

ALTER TABLE `{PREFIX}event_log` MODIFY COLUMN `description` text;

ALTER TABLE `{PREFIX}manager_users` MODIFY COLUMN `username` varchar(100) NOT NULL DEFAULT '';

ALTER TABLE `{PREFIX}site_content` 
 MODIFY COLUMN `pagetitle` varchar(255) NOT NULL default '',
 MODIFY COLUMN `alias` varchar(255) default '',
 MODIFY COLUMN `introtext` text COMMENT 'Used to provide quick summary of the document',
 MODIFY COLUMN `content` mediumtext,
 MODIFY COLUMN `menutitle` varchar(255) NOT NULL DEFAULT '' COMMENT 'Menu title';

ALTER TABLE `{PREFIX}site_content` ADD COLUMN `link_attributes` varchar(255) NOT NULL DEFAULT '' COMMENT 'Link attriubtes' AFTER `alias`;

ALTER TABLE `{PREFIX}site_htmlsnippets` MODIFY COLUMN `snippet` mediumtext;

ALTER TABLE `{PREFIX}site_modules`
 MODIFY COLUMN `name` varchar(50) NOT NULL DEFAULT '',
 MODIFY COLUMN `disabled` tinyint(4) NOT NULL DEFAULT '0',
 MODIFY COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT 'url to module icon',
 MODIFY COLUMN `resourcefile` varchar(255) NOT NULL DEFAULT '' COMMENT 'a physical link to a resource file',
 MODIFY COLUMN `createdon` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `editedon` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `guid` varchar(32) NOT NULL DEFAULT '' COMMENT 'globally unique identifier',
 MODIFY COLUMN `properties` text,
 MODIFY COLUMN `modulecode` mediumtext COMMENT 'module boot up code';

ALTER TABLE `{PREFIX}site_module_access`
 MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `usergroup` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `{PREFIX}site_module_depobj`
 MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `resource` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `{PREFIX}site_plugins`
 MODIFY COLUMN `plugincode` mediumtext,
 MODIFY COLUMN `moduleguid` varchar(32) NOT NULL DEFAULT '' COMMENT 'GUID of module from which to import shared parameters';

ALTER TABLE `{PREFIX}site_plugin_events`
 MODIFY COLUMN `evtid` int(10) NOT NULL DEFAULT '0';

ALTER TABLE `{PREFIX}site_plugin_events` ADD COLUMN `priority` INT(10) NOT NULL default '0' COMMENT 'determines the run order of the plugin' AFTER `evtid`;

ALTER TABLE `{PREFIX}site_snippets`
 MODIFY COLUMN `snippet` mediumtext,
 MODIFY COLUMN `moduleguid` varchar(32) NOT NULL DEFAULT '' COMMENT 'GUID of module from which to import shared parameters';

ALTER TABLE `{PREFIX}site_templates`
 MODIFY COLUMN `icon` varchar(255) NOT NULL default '' COMMENT 'url to icon file',
 MODIFY COLUMN `content` mediumtext;

ALTER TABLE `{PREFIX}site_tmplvars`
 MODIFY COLUMN `name` varchar(50) NOT NULL default '',
 MODIFY COLUMN `elements` text,
 MODIFY COLUMN `display` varchar(20) NOT NULL DEFAULT '' COMMENT 'Display Control',
 MODIFY COLUMN `display_params` text COMMENT 'Display Control Properties',
 MODIFY COLUMN `default_text` text;

ALTER TABLE `{PREFIX}site_tmplvar_contentvalues`
 MODIFY COLUMN `tmplvarid` int(10) NOT NULL DEFAULT '0' COMMENT 'Template Variable id',
 MODIFY COLUMN `value` text;

ALTER TABLE `{PREFIX}site_tmplvar_templates` MODIFY COLUMN `tmplvarid` int(10) NOT NULL DEFAULT '0' COMMENT 'Template Variable id';

ALTER TABLE `{PREFIX}site_tmplvar_templates` ADD COLUMN `rank` integer(11) NOT NULL DEFAULT '0' AFTER `templateid`;

ALTER TABLE `{PREFIX}system_eventnames`
 MODIFY COLUMN  `name` varchar(50) NOT NULL DEFAULT '',
 MODIFY COLUMN `service` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'System Service number';

ALTER TABLE `{PREFIX}system_settings` MODIFY COLUMN `setting_value` text;

ALTER TABLE `{PREFIX}user_attributes`
 MODIFY COLUMN `country` varchar(5) NOT NULL DEFAULT '',
 MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo',
 MODIFY COLUMN `comment` varchar(255) NOT NULL DEFAULT '' COMMENT 'short comment';

ALTER TABLE `{PREFIX}user_settings` MODIFY COLUMN `setting_value` text;

ALTER TABLE `{PREFIX}user_messages` MODIFY COLUMN `message` text;

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `publish_document` int(1) NOT NULL DEFAULT '0' AFTER `save_document`;

ALTER TABLE `{PREFIX}web_users`
 MODIFY COLUMN `username` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `cachepwd` varchar(100) NOT NULL DEFAULT '' COMMENT 'Store new unconfirmed password' AFTER `password`;

ALTER TABLE `{PREFIX}web_user_attributes`
 MODIFY COLUMN `country` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo';

ALTER TABLE `{PREFIX}web_user_settings` MODIFY COLUMN `setting_value` text;

#095-096

ALTER TABLE `{PREFIX}user_roles`
 ADD COLUMN `edit_chunk` int(1) NOT NULL DEFAULT '0' AFTER `delete_snippet`,
 ADD COLUMN `new_chunk` int(1) NOT NULL DEFAULT '0' AFTER `edit_chunk`,
 ADD COLUMN `save_chunk` int(1) NOT NULL DEFAULT '0' AFTER `new_chunk`,
 ADD COLUMN `delete_chunk` int(1) NOT NULL DEFAULT '0' AFTER `save_chunk`,
 ADD COLUMN `import_static` int(1) NOT NULL DEFAULT '0' AFTER `view_unpublished`,
 ADD COLUMN `export_static` int(1) NOT NULL DEFAULT '0' AFTER `import_static`;

ALTER TABLE `{PREFIX}web_user_attributes`
 MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '';

#096-0961

#0961-0963

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `empty_trash` int(1) NOT NULL DEFAULT '0' AFTER `delete_document`;

#0963-1.0.0

#1.0.3-1.0.4

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `remove_locks` int(1) NOT NULL DEFAULT '0';

#1.0.4-1.0.5

ALTER TABLE `{PREFIX}member_groups` ADD UNIQUE INDEX `ix_group_member` (`user_group`,`member`);

ALTER TABLE `{PREFIX}user_attributes` MODIFY COLUMN `comment` text;

ALTER TABLE `{PREFIX}web_groups` ADD UNIQUE INDEX `ix_group_user` (`webgroup`,`webuser`);

ALTER TABLE `{PREFIX}web_user_attributes` MODIFY COLUMN `comment` text;

#1.0.5-1.0.6

ALTER TABLE `{PREFIX}site_content` MODIFY COLUMN `template` int(10) NOT NULL default '0';

ALTER TABLE `{PREFIX}site_content` ADD INDEX `typeidx` (`type`);

ALTER TABLE `{PREFIX}site_htmlsnippets` ADD COLUMN `published` int(1) NOT NULL default '1' AFTER `description`;

ALTER TABLE `{PREFIX}site_htmlsnippets` ADD COLUMN `pub_date` int(20) NOT NULL default '0' AFTER `published`;

ALTER TABLE `{PREFIX}site_htmlsnippets` ADD COLUMN `unpub_date` int(20) NOT NULL default '0' AFTER `pub_date`;

ALTER TABLE `{PREFIX}system_settings` DROP PRIMARY KEY;

ALTER TABLE `{PREFIX}system_settings` DROP INDEX `setting_name`;

ALTER TABLE `{PREFIX}system_settings` ADD PRIMARY KEY (`setting_name`);

ALTER TABLE `{PREFIX}user_settings` DROP PRIMARY KEY;

ALTER TABLE `{PREFIX}user_settings` ADD PRIMARY KEY (`user`, `setting_name`);

ALTER TABLE `{PREFIX}web_user_settings` DROP PRIMARY KEY;

ALTER TABLE `{PREFIX}web_user_settings` ADD PRIMARY KEY (`webuser`, `setting_name`);

ALTER TABLE `{PREFIX}site_plugin_events` DROP PRIMARY KEY;

ALTER TABLE `{PREFIX}site_plugin_events` ADD PRIMARY KEY (`pluginid`, `evtid`);

ALTER TABLE `{PREFIX}active_users` MODIFY COLUMN `ip` varchar(50) NOT NULL DEFAULT '';

ALTER TABLE `{PREFIX}site_tmplvar_contentvalues` ADD FULLTEXT `value_ft_idx` (`value`);

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `view_schedule` int(1) NOT NULL DEFAULT '0' AFTER `remove_locks`;

#1.0.8-1.0.8J-r1

ALTER TABLE `{PREFIX}site_templates` ADD COLUMN `doc_encoding` varchar(20) NOT NULL default '' AFTER `content`;

ALTER TABLE `{PREFIX}site_templates` ADD COLUMN `parent` int(10) NOT NULL default '0' AFTER `content`;

# end related to #MODX-1321

ALTER TABLE `{PREFIX}site_content` DROP INDEX `content_ft_idx`;

UPDATE `{PREFIX}site_plugins` SET disabled='1' WHERE `name`='ダッシュボード・あなたの情報' OR `name`='ダッシュボード・オンライン情報';

#1.0.9-1.0.10J

ALTER TABLE `{PREFIX}user_roles` ADD COLUMN `parent` tinyint(4) NOT NULL default '0' AFTER `description`;

ALTER TABLE `{PREFIX}site_revision` ADD COLUMN `submittedon` int(20) NOT NULL default '0' AFTER `editedby`;

ALTER TABLE `{PREFIX}site_revision` ADD COLUMN `submittedby` int(10) NOT NULL default '0' AFTER `submittedon`;

CREATE TABLE IF NOT EXISTS `{PREFIX}system_settings_group` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(64) NOT NULL,
 PRIMARY KEY (`id`)
);

INSERT INTO `{PREFIX}system_settings_group` (`id`, `name`) VALUES
 (1, 'settings_site'), (2, 'settings_furls'),(3,'settings_users'),
 (4,'settings_ui'),(5,'settings_misc');

ALTER TABLE `{PREFIX}system_settings` DROP PRIMARY KEY;

ALTER TABLE `{PREFIX}system_settings` add column `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE `{PREFIX}system_settings` add column `id_group` int NOT NULL default '1';

ALTER TABLE `{PREFIX}system_settings` add column `title` varchar(255) NOT NULL default '';

ALTER TABLE `{PREFIX}system_settings` add column `is_show` int NOT NULL default '1';

ALTER TABLE `{PREFIX}system_settings` add column `description` TEXT NOT NULL;

ALTER TABLE `{PREFIX}system_settings` add column `sort` int NOT NULL;

ALTER TABLE `{PREFIX}system_settings` add column `options` TEXT NOT NULL;

UPDATE `{PREFIX}system_settings` set `title`=CONCAT(`setting_name`,'_title'),`description`=CONCAT(`setting_name`,'_message');

UPDATE `{PREFIX}system_settings` set `is_show`=0 WHERE `setting_name`='settings_version';

UPDATE `{PREFIX}system_settings` set `sort`=1, `options`='text' WHERE `setting_name`='site_name';

UPDATE `{PREFIX}system_settings` set `sort`=2, `options`='textarea' WHERE `setting_name`='site_slogan';

UPDATE `{PREFIX}system_settings` set `sort`=3, `options`='text' WHERE `setting_name`='site_url';

UPDATE `{PREFIX}system_settings` set `sort`=4, `options`='text' WHERE `setting_name`='base_url';

UPDATE `{PREFIX}system_settings` set `sort`=5, `options`='select||UTF-8' WHERE `setting_name`='modx_charset';

UPDATE `{PREFIX}system_settings` set `sort`=6, `options`='select||UTF-8;SJIS-win;eucJP-win;Windows-1251;Windows-1252;KOI8-R;ISO-8859-1;ISO-8859-2;ISO-8859-3;ISO-8859-4;ISO-8859-5;ISO-8859-6;ISO-8859-7;ISO-8859-8;ISO-8859-9;ISO-8859-10;Shift_JIS;EUC-JP;BIG-5' WHERE `setting_name`='doc_encoding';

UPDATE `{PREFIX}system_settings` set `sort`=7, `options`='radio||yes=1;no=0' WHERE `setting_name`='xhtml_urls';

UPDATE `{PREFIX}system_settings` set `sort`=8, `options`='text' WHERE `setting_name`='site_start';

UPDATE `{PREFIX}system_settings` set `sort`=9, `options`='text' WHERE `setting_name`='error_page';

UPDATE `{PREFIX}system_settings` set `sort`=10, `options`='text' WHERE `setting_name`='unauthorized_page';

UPDATE `{PREFIX}system_settings` set `sort`=11, `options`='radio||online=1;offline=0' WHERE `setting_name`='site_status';

UPDATE `{PREFIX}system_settings` set `sort`=12, `options`='text' WHERE `setting_name`='site_unavailable_page';

UPDATE `{PREFIX}system_settings` set `sort`=13, `options`='textarea_lang' WHERE `setting_name`='site_unavailable_message';

UPDATE `{PREFIX}system_settings` set `sort`=14, `options`='radio||yes=1;no=0' WHERE `setting_name`='track_visitors';

UPDATE `{PREFIX}system_settings` set `sort`=15, `options`='radio||defaulttemplate_logic_system_message=system;defaulttemplate_logic_parent_message=parent;defaulttemplate_logic_sibling_message=sibling' WHERE `setting_name`='auto_template_logic';

UPDATE `{PREFIX}system_settings` set `sort`=16, `options`='template' WHERE `setting_name`='default_template';

UPDATE `{PREFIX}system_settings` set `sort`=17, `options`='radio||yes=1;no=0' WHERE `setting_name`='publish_default';

UPDATE `{PREFIX}system_settings` set `sort`=18, `options`='radio||mutate_settings.dynamic.php1=1;mutate_settings.dynamic.php2=2;mutate_settings.dynamic.php3=0' WHERE `setting_name`='cache_type';

UPDATE `{PREFIX}system_settings` set `sort`=19, `options`='radio||yes=1;no=0' WHERE `setting_name`='cache_default';

UPDATE `{PREFIX}system_settings` set `sort`=20, `options`='radio||yes=1;no=0' WHERE `setting_name`='search_default';

UPDATE `{PREFIX}system_settings` set `sort`=21, `options`='radio||yes=1;no=0' WHERE `setting_name`='auto_menuindex';

UPDATE `{PREFIX}system_settings` set `sort`=22, `options`='custom_contenttype' WHERE `setting_name`='custom_contenttype';

UPDATE `{PREFIX}system_settings` set `sort`=23, `options`='radio||docid_incrmnt_method_0=0;docid_incrmnt_method_1=1;docid_incrmnt_method_2=2' WHERE `setting_name`='docid_incrmnt_method';

UPDATE `{PREFIX}system_settings` set `sort`=24, `options`='server_offset' WHERE `setting_name`='server_offset_time';

UPDATE `{PREFIX}system_settings` set `sort`=25, `options`='radio||server_protocol_http=http;server_protocol_https=https' WHERE `setting_name`='server_protocol';

UPDATE `{PREFIX}system_settings` set `sort`=26, `options`='radio||Enable=1;Disable=0' WHERE `setting_name`='output_filter';

UPDATE `{PREFIX}system_settings` set `sort`=27, `id_group`=2, `options`='radio||yes=1;no=0||depend||friendly_url_prefix,friendly_url_suffix,make_folders,friendly_alias_urls,use_alias_path,allow_duplicate_alias,automatic_alias' WHERE `setting_name`='friendly_urls';

UPDATE `{PREFIX}system_settings` set `sort`=28, `id_group`=2, `options`='text' WHERE `setting_name`='friendly_url_prefix';

UPDATE `{PREFIX}system_settings` set `sort`=29, `id_group`=2, `options`='text' WHERE `setting_name`='friendly_url_suffix';

UPDATE `{PREFIX}system_settings` set `sort`=30, `id_group`=2, `options`='radio||yes=1;no=0' WHERE `setting_name`='make_folders';

UPDATE `{PREFIX}system_settings` set `sort`=31, `id_group`=2, `options`='radio||yes=1;no=0' WHERE `setting_name`='friendly_alias_urls';

UPDATE `{PREFIX}system_settings` set `sort`=32, `id_group`=2, `options`='radio||yes=1;no=0' WHERE `setting_name`='use_alias_path';

UPDATE `{PREFIX}system_settings` set `sort`=33, `id_group`=2, `options`='radio||yes=1;no=0' WHERE `setting_name`='allow_duplicate_alias';

UPDATE `{PREFIX}system_settings` set `sort`=34, `id_group`=2, `options`='radio||pagetitle=1;numbering in each folder=2;disabled=0' WHERE `setting_name`='automatic_alias';

UPDATE `ss_system_settings` set `sort`=35, `id_group`=3, `options`='textarea' WHERE `setting_name`='check_files_onlogin';

UPDATE `ss_system_settings` set `sort`=36, `id_group`=3, `options`='radio||yes=1;no=0||depend||udperms_allowroot,tree_show_protected' WHERE `setting_name`='use_udperms';

UPDATE `ss_system_settings` set `sort`=37, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='udperms_allowroot';

UPDATE `ss_system_settings` set `sort`=38, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='tree_show_protected';

UPDATE `ss_system_settings` set `sort`=39, `id_group`=3, `options`='role_list' WHERE `setting_name`='default_role';

UPDATE `ss_system_settings` set `sort`=40, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='validate_referer';

UPDATE `ss_system_settings` set `sort`=41, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='allow_mgr2web';

UPDATE `ss_system_settings` set `sort`=42, `id_group`=3, `options`='text' WHERE `setting_name`='failed_login_attempts';

UPDATE `ss_system_settings` set `sort`=43, `id_group`=3, `options`='text' WHERE `setting_name`='blocked_minutes';

UPDATE `ss_system_settings` set `sort`=44, `id_group`=3, `options`='text' WHERE `setting_name`='auto_sleep_user';

UPDATE `ss_system_settings` set `sort`=45, `id_group`=3, `options`='radio||a17_error_reporting_opt0=0;a17_error_reporting_opt1=1;a17_error_reporting_opt2=2;a17_error_reporting_opt99=99' WHERE `setting_name`='error_reporting';

UPDATE `ss_system_settings` set `sort`=46, `id_group`=3, `options`='radio||mutate_settings.dynamic.php7=0;error=3;error + warning=2;error + warning + information=1' WHERE `setting_name`='send_errormail';

UPDATE `ss_system_settings` set `sort`=47, `id_group`=3, `options`='radio||administrators=0;a17_warning_opt2=2;everybody=1' WHERE `setting_name`='warning_visibility';

UPDATE `ss_system_settings` set `sort`=48, `id_group`=3, `options`='hash_algo' WHERE `setting_name`='pwd_hash_algo';

UPDATE `ss_system_settings` set `sort`=49, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='use_captcha';

UPDATE `ss_system_settings` set `sort`=50, `id_group`=3, `options`='text_lang' WHERE `setting_name`='captcha_words';

UPDATE `ss_system_settings` set `sort`=51, `id_group`=3, `options`='text' WHERE `setting_name`='emailsender';

UPDATE `ss_system_settings` set `sort`=52, `id_group`=3, `options`='text_lang' WHERE `setting_name`='emailsubject';

UPDATE `ss_system_settings` set `sort`=53, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='signupemail_message';

UPDATE `ss_system_settings` set `sort`=54, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='websignupemail_message';

UPDATE `ss_system_settings` set `sort`=55, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='webpwdreminder_message';

UPDATE `ss_system_settings` set `sort`=56, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='enable_bindings';

UPDATE `ss_system_settings` set `sort`=57, `id_group`=4, `options`='manager_theme' WHERE `setting_name`='manager_theme';

UPDATE `ss_system_settings` set `sort`=58, `id_group`=4, `options`='textarea' WHERE `setting_name`='manager_inline_style';

UPDATE `ss_system_settings` set `sort`=59, `id_group`=4, `options`='language' WHERE `setting_name`='manager_language';

UPDATE `ss_system_settings` set `sort`=60, `id_group`=4, `options`='topmenu' WHERE `setting_name`='topmenu_site';

UPDATE `ss_system_settings` set `sort`=61, `id_group`=4, `options`='text' WHERE `setting_name`='limit_by_container';

UPDATE `ss_system_settings` set `sort`=62, `id_group`=4, `options`='radio||open=1;close=0' WHERE `setting_name`='tree_pane_open_default';

UPDATE `ss_system_settings` set `sort`=62, `id_group`=4, `options`='radio||edit_resource=27;doc_data_title=3;tree_page_click_option_auto=auto' WHERE `setting_name`='tree_page_click';

UPDATE `ss_system_settings` set `sort`=63, `id_group`=4, `options`='radio||yes_full=2;yes_stay=1;no=0' WHERE `setting_name`='remember_last_tab';

UPDATE `ss_system_settings` set `sort`=64, `id_group`=4, `options`='select||pagetitle;menutitle;alias;createdon;editedon;publishedon' WHERE `setting_name`='resource_tree_node_name';

UPDATE `ss_system_settings` set `sort`=65, `id_group`=4, `options`='text' WHERE `setting_name`='top_howmany';

UPDATE `ss_system_settings` set `sort`=66, `id_group`=4, `options`='radio||yes=1;no=0' WHERE `setting_name`='show_meta';

UPDATE `ss_system_settings` set `sort`=67, `id_group`=4, `options`='text' WHERE `setting_name`='datepicker_offset';

UPDATE `ss_system_settings` set `sort`=68, `id_group`=4, `options`='select||dd-mm-YYYY;mm/dd/YYYY;YYYY/mm/dd' WHERE `setting_name`='datetime_format';

UPDATE `ss_system_settings` set `sort`=69, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_logs';

UPDATE `ss_system_settings` set `sort`=70, `id_group`=4, `options`='text' WHERE `setting_name`='mail_check_timeperiod';

UPDATE `ss_system_settings` set `sort`=71, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_messages';

UPDATE `ss_system_settings` set `sort`=72, `id_group`=4, `options`='radio||yes=1;no=0' WHERE `setting_name`='pm2email';

UPDATE `ss_system_settings` set `sort`=73, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_results';

UPDATE `ss_system_settings` set `sort`=74, `id_group`=4, `options`='radio||yes=1;no=0||depend||which_editor,fe_editor_lang,editor_css_path' WHERE `setting_name`='use_editor';

UPDATE `ss_system_settings` set `sort`=75, `id_group`=4, `options`='which_editor' WHERE `setting_name`='which_editor';

UPDATE `ss_system_settings` set `sort`=76, `id_group`=4, `options`='language' WHERE `setting_name`='fe_editor_lang';

UPDATE `ss_system_settings` set `sort`=77, `id_group`=4, `options`='text' WHERE `setting_name`='editor_css_path';

UPDATE `ss_system_settings` set `sort`=78, `id_group`=5, `options`='path' WHERE `setting_name`='filemanager_path';

UPDATE `ss_system_settings` set `sort`=79, `id_group`=5, `options`='text' WHERE `setting_name`='upload_files';

UPDATE `ss_system_settings` set `sort`=80, `id_group`=5, `options`='text' WHERE `setting_name`='upload_images';

UPDATE `ss_system_settings` set `sort`=81, `id_group`=5, `options`='text' WHERE `setting_name`='upload_media';

UPDATE `ss_system_settings` set `sort`=82, `id_group`=5, `options`='text' WHERE `setting_name`='upload_flash';

UPDATE `ss_system_settings` set `sort`=83, `id_group`=5, `options`='upload_maxsize' WHERE `setting_name`='upload_maxsize';

UPDATE `ss_system_settings` set `sort`=84, `id_group`=5, `options`='text' WHERE `setting_name`='new_file_permissions';

UPDATE `ss_system_settings` set `sort`=85, `id_group`=5, `options`='text' WHERE `setting_name`='new_folder_permissions';

UPDATE `ss_system_settings` set `sort`=86, `id_group`=5, `options`='radio||yes=1;no=0||depend||strip_image_paths,rb_webuser,rb_base_url,clean_uploaded_filename,image_limit_width' WHERE `setting_name`='use_browser';

UPDATE `ss_system_settings` set `sort`=87, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='strip_image_paths';

UPDATE `ss_system_settings` set `sort`=88, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='rb_webuser';

UPDATE `ss_system_settings` set `sort`=89, `id_group`=5, `options`='base_dir' WHERE `setting_name`='rb_base_dir';

UPDATE `ss_system_settings` set `sort`=90, `id_group`=5, `options`='text' WHERE `setting_name`='rb_base_url';

UPDATE `ss_system_settings` set `sort`=91, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='clean_uploaded_filename';

UPDATE `ss_system_settings` set `sort`=92, `id_group`=5, `options`='text' WHERE `setting_name`='image_limit_width';

