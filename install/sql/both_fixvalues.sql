# Default Site Settings
INSERT IGNORE INTO `{PREFIX}system_settings` 
(setting_name, setting_value) VALUES 
('settings_version','0'),
('server_offset_time','0'),
('manager_language','{MANAGERLANGUAGE}'),
('modx_charset','UTF-8'),
('site_name','My MODX Site'),
('site_start','1'),
('error_page','1'),
('unauthorized_page','1'),
('site_status','1'),
('old_template',''),
('cache_type','1'),
('use_udperms','0'),
('udperms_allowroot','0'),
('failed_login_attempts','3'),
('blocked_minutes','60'),
('use_captcha','0'),
('emailsender','{ADMINEMAIL}'),
('use_editor','1'),
('use_browser','1'),
('fe_editor_lang','{MANAGERLANGUAGE}'),
('session.cookie.lifetime','604800'),
('manager_theme','RevoStyle'),
('theme_refresher',''),
('site_slogan',''),
('site_url',''),
('base_url','');
('doc_encoding',''),
('xhtml_urls',0),
('site_unavailable_page',''),
('site_unavailable_message','')
('track_visitors',''),
('auto_template_logic',''),
('default_template',''),
('publish_default',''),
('cache_default',''),
('search_default',''),
('auto_menuindex',''),
('custom_contenttype',''),
('docid_incrmnt_method',''),
('server_protocol',''),
('output_filter',''),
('friendly_urls',''),
('friendly_url_prefix',''),
('friendly_url_suffix',''),
('make_folders',''),
('friendly_alias_urls',''),
('use_alias_path',''),
('allow_duplicate_alias',''),
('automatic_alias',''),
('check_files_onlogin',''),
('tree_show_protected',''),
('default_role','');


REPLACE INTO `{PREFIX}user_roles`
(id,name,description,frames,home,view_document,new_document,save_document,publish_document,delete_document,empty_trash,action_ok,logout,help,messages,new_user,edit_user,logs,edit_parser,save_parser,edit_template,settings,credits,new_template,save_template,delete_template,edit_snippet,new_snippet,save_snippet,delete_snippet,edit_chunk,new_chunk,save_chunk,delete_chunk,empty_cache,edit_document,change_password,error_dialog,about,file_manager,save_user,delete_user,save_password,edit_role,save_role,delete_role,new_role,access_permissions,bk_manager,new_plugin,edit_plugin,save_plugin,delete_plugin,new_module,edit_module,save_module,exec_module,delete_module,view_eventlog,delete_eventlog,manage_metatags,edit_doc_metatags,new_web_user,edit_web_user,save_web_user,delete_web_user,web_access_permissions,view_unpublished,import_static,export_static,remove_locks,view_schedule) VALUES 
(1, 'Administrator', 'Site administrators have full access to all functions',1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1);


# 1 - "Parser Service Events", 2 -  "Manager Access Events", 3 - "Web Access Service Events", 4 - "Cache Service Events", 5 - "Template Service Events", 6 - Custom Events

REPLACE INTO `{PREFIX}system_eventnames` 
(id,name,service,groupname) VALUES 
('1','OnDocPublished','5',''), 
('2','OnDocUnPublished','5',''),
('3','OnWebPagePrerender','5',''),
('4','OnWebLogin','3',''),
('5','OnBeforeWebLogout','3',''),
('6','OnWebLogout','3',''),
('7','OnWebSaveUser','3',''),
('8','OnWebDeleteUser','3',''),
('9','OnWebChangePassword','3',''),
('10','OnWebCreateGroup','3',''),
('11','OnManagerLogin','2',''),
('12','OnBeforeManagerLogout','2',''),
('13','OnManagerLogout','2',''),
('14','OnManagerSaveUser','2',''),
('15','OnManagerDeleteUser','2',''),
('16','OnManagerChangePassword','2',''),
('17','OnManagerCreateGroup','2',''),
('18','OnBeforeCacheUpdate','4',''),
('19','OnCacheUpdate','4',''),
('20','OnLoadWebPageCache','4',''),
('21','OnBeforeSaveWebPageCache','4',''),
('22','OnChunkFormPrerender','1','Chunks'),
('23','OnChunkFormRender','1','Chunks'),
('24','OnBeforeChunkFormSave','1','Chunks'),
('25','OnChunkFormSave','1','Chunks'),
('26','OnBeforeChunkFormDelete','1','Chunks'),
('27','OnChunkFormDelete','1','Chunks'),
('28','OnDocFormPrerender','1','Documents'),
('29','OnDocFormRender','1','Documents'),
('30','OnBeforeDocFormSave','1','Documents'),
('31','OnDocFormSave','1','Documents'),
('32','OnBeforeDocFormDelete','1','Documents'),
('33','OnDocFormDelete','1','Documents'),
('34','OnPluginFormPrerender','1','Plugins'),
('35','OnPluginFormRender','1','Plugins'),
('36','OnBeforePluginFormSave','1','Plugins'),
('37','OnPluginFormSave','1','Plugins'),
('38','OnBeforePluginFormDelete','1','Plugins'),
('39','OnPluginFormDelete','1','Plugins'),
('40','OnSnipFormPrerender','1','Snippets'),
('41','OnSnipFormRender','1','Snippets'),
('42','OnBeforeSnipFormSave','1','Snippets'),
('43','OnSnipFormSave','1','Snippets'),
('44','OnBeforeSnipFormDelete','1','Snippets'),
('45','OnSnipFormDelete','1','Snippets'),
('46','OnTempFormPrerender','1','Templates'),
('47','OnTempFormRender','1','Templates'),
('48','OnBeforeTempFormSave','1','Templates'),
('49','OnTempFormSave','1','Templates'),
('50','OnBeforeTempFormDelete','1','Templates'),
('51','OnTempFormDelete','1','Templates'),
('52','OnTVFormPrerender','1','Template Variables'),
('53','OnTVFormRender','1','Template Variables'),
('54','OnBeforeTVFormSave','1','Template Variables'),
('55','OnTVFormSave','1','Template Variables'),
('56','OnBeforeTVFormDelete','1','Template Variables'),
('57','OnTVFormDelete','1','Template Variables'),
('58','OnUserFormPrerender','1','Users'),
('59','OnUserFormRender','1','Users'),
('60','OnBeforeUserFormSave','1','Users'),
('61','OnUserFormSave','1','Users'),
('62','OnBeforeUserFormDelete','1','Users'),
('63','OnUserFormDelete','1','Users'),
('64','OnWUsrFormPrerender','1','Web Users'),
('65','OnWUsrFormRender','1','Web Users'),
('66','OnBeforeWUsrFormSave','1','Web Users'),
('67','OnWUsrFormSave','1','Web Users'),
('68','OnBeforeWUsrFormDelete','1','Web Users'),
('69','OnWUsrFormDelete','1','Web Users'),
('70','OnSiteRefresh','1',''),
('71','OnFileManagerUpload','1',''),
('72','OnModFormPrerender','1','Modules'),
('73','OnModFormRender','1','Modules'),
('74','OnBeforeModFormDelete','1','Modules'),
('75','OnModFormDelete','1','Modules'),
('76','OnBeforeModFormSave','1','Modules'),
('77','OnModFormSave','1','Modules'),
('78','OnBeforeWebLogin','3',''),
('79','OnWebAuthentication','3',''),
('80','OnBeforeManagerLogin','2',''),
('81','OnManagerAuthentication','2',''),
('82','OnSiteSettingsRender','1','System Settings'),
('83','OnFriendlyURLSettingsRender','1','System Settings'),
('84','OnUserSettingsRender','1','System Settings'),
('85','OnInterfaceSettingsRender','1','System Settings'),
('86','OnMiscSettingsRender','1','System Settings'),
('87','OnRichTextEditorRegister','1','RichText Editor'),
('88','OnRichTextEditorInit','1','RichText Editor'),
('89','OnManagerPageInit','2',''),
('90','OnWebPageInit','5',''),
('91','OnLoadWebDocument','5',''),
('92','OnParseDocument','5',''),
('93','OnManagerLoginFormRender','2',''),
('94','OnWebPageComplete','5',''),
('95','OnLogPageHit','5',''),
('96','OnBeforeManagerPageInit','2',''),
('97','OnBeforeEmptyTrash','1','Documents'),
('98','OnEmptyTrash','1','Documents'),
('99','OnManagerLoginFormPrerender','2',''),
('100','OnStripAlias','1','Documents'),
('200','OnCreateDocGroup','1','Documents'),
('201','OnManagerWelcomePrerender','2',''),
('202','OnManagerWelcomeHome','2',''),
('203','OnManagerWelcomeRender','2',''),
('204','OnBeforeDocDuplicate','1','Documents'),
('205','OnDocDuplicate','1','Documents'),
('206','OnManagerMainFrameHeaderHTMLBlock','2',''),
('207','OnManagerPreFrameLoader','2',''),
('208','OnManagerFrameLoader','2',''),
('300','OnMakeUrl','1',''),
('999','OnPageUnauthorized','1',''),
('1000','OnPageNotFound','1','');

# ^ I don't think we need more than 1000 built-in events. Custom events will start at 1001

CREATE TABLE IF NOT EXISTS `{PREFIX}system_settings_group` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(64) NOT NULL,
 PRIMARY KEY (`id`)
);

INSERT INTO `{PREFIX}system_settings_group` (`id`, `name`) VALUES
 (1, 'settings_site'), (2, 'settings_furls'),(3,'settings_users'),
 (4,'settings_ui'),(5,'settings_misc');

ALTER TABLE `{PREFIX}system_settings` add column `id_group` int NOT NULL default '1';

ALTER TABLE `{PREFIX}system_settings` add column `title` varchar(255) NOT NULL default '';

ALTER TABLE `{PREFIX}system_settings` add column `description` TEXT NOT NULL;

ALTER TABLE `{PREFIX}system_settings` add column `sort` int NOT NULL;

ALTER TABLE `{PREFIX}system_settings` add column `options` TEXT NOT NULL;

UPDATE `{PREFIX}system_settings` set `title`=CONCAT(`setting_name`,'_title'),`description`=CONCAT(`setting_name`,'_message');

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

UPDATE `{PREFIX}system_settings` set `sort`=35, `id_group`=3, `options`='textarea' WHERE `setting_name`='check_files_onlogin';

UPDATE `{PREFIX}system_settings` set `sort`=36, `id_group`=3, `options`='radio||yes=1;no=0||depend||udperms_allowroot,tree_show_protected' WHERE `setting_name`='use_udperms';

UPDATE `{PREFIX}system_settings` set `sort`=37, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='udperms_allowroot';

UPDATE `{PREFIX}system_settings` set `sort`=38, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='tree_show_protected';

UPDATE `{PREFIX}system_settings` set `sort`=39, `id_group`=3, `options`='role_list' WHERE `setting_name`='default_role';

UPDATE `{PREFIX}system_settings` set `sort`=40, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='validate_referer';

UPDATE `{PREFIX}system_settings` set `sort`=41, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='allow_mgr2web';

UPDATE `{PREFIX}system_settings` set `sort`=42, `id_group`=3, `options`='text' WHERE `setting_name`='failed_login_attempts';

UPDATE `{PREFIX}system_settings` set `sort`=43, `id_group`=3, `options`='text' WHERE `setting_name`='blocked_minutes';

UPDATE `{PREFIX}system_settings` set `sort`=44, `id_group`=3, `options`='text' WHERE `setting_name`='auto_sleep_user';

UPDATE `{PREFIX}system_settings` set `sort`=45, `id_group`=3, `options`='radio||a17_error_reporting_opt0=0;a17_error_reporting_opt1=1;a17_error_reporting_opt2=2;a17_error_reporting_opt99=99' WHERE `setting_name`='error_reporting';

UPDATE `{PREFIX}system_settings` set `sort`=46, `id_group`=3, `options`='radio||mutate_settings.dynamic.php7=0;error=3;error + warning=2;error + warning + information=1' WHERE `setting_name`='send_errormail';

UPDATE `{PREFIX}system_settings` set `sort`=47, `id_group`=3, `options`='radio||administrators=0;a17_warning_opt2=2;everybody=1' WHERE `setting_name`='warning_visibility';

UPDATE `{PREFIX}system_settings` set `sort`=48, `id_group`=3, `options`='hash_algo' WHERE `setting_name`='pwd_hash_algo';

UPDATE `{PREFIX}system_settings` set `sort`=49, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='use_captcha';

UPDATE `{PREFIX}system_settings` set `sort`=50, `id_group`=3, `options`='text_lang' WHERE `setting_name`='captcha_words';

UPDATE `{PREFIX}system_settings` set `sort`=51, `id_group`=3, `options`='text' WHERE `setting_name`='emailsender';

UPDATE `{PREFIX}system_settings` set `sort`=52, `id_group`=3, `options`='text_lang' WHERE `setting_name`='emailsubject';

UPDATE `{PREFIX}system_settings` set `sort`=53, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='signupemail_message';

UPDATE `{PREFIX}system_settings` set `sort`=54, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='websignupemail_message';

UPDATE `{PREFIX}system_settings` set `sort`=55, `id_group`=3, `options`='textarea_lang' WHERE `setting_name`='webpwdreminder_message';

UPDATE `{PREFIX}system_settings` set `sort`=56, `id_group`=3, `options`='radio||yes=1;no=0' WHERE `setting_name`='enable_bindings';

UPDATE `{PREFIX}system_settings` set `sort`=57, `id_group`=4, `options`='manager_theme' WHERE `setting_name`='manager_theme';

UPDATE `{PREFIX}system_settings` set `sort`=58, `id_group`=4, `options`='textarea' WHERE `setting_name`='manager_inline_style';

UPDATE `{PREFIX}system_settings` set `sort`=59, `id_group`=4, `options`='language' WHERE `setting_name`='manager_language';

UPDATE `{PREFIX}system_settings` set `sort`=60, `id_group`=4, `options`='topmenu' WHERE `setting_name`='topmenu_site';

UPDATE `{PREFIX}system_settings` set `sort`=61, `id_group`=4, `options`='text' WHERE `setting_name`='limit_by_container';

UPDATE `{PREFIX}system_settings` set `sort`=62, `id_group`=4, `options`='radio||open=1;close=0' WHERE `setting_name`='tree_pane_open_default';

UPDATE `{PREFIX}system_settings` set `sort`=62, `id_group`=4, `options`='radio||edit_resource=27;doc_data_title=3;tree_page_click_option_auto=auto' WHERE `setting_name`='tree_page_click';

UPDATE `{PREFIX}system_settings` set `sort`=63, `id_group`=4, `options`='radio||yes_full=2;yes_stay=1;no=0' WHERE `setting_name`='remember_last_tab';

UPDATE `{PREFIX}system_settings` set `sort`=64, `id_group`=4, `options`='select||pagetitle;menutitle;alias;createdon;editedon;publishedon' WHERE `setting_name`='resource_tree_node_name';

UPDATE `{PREFIX}system_settings` set `sort`=65, `id_group`=4, `options`='text' WHERE `setting_name`='top_howmany';

UPDATE `{PREFIX}system_settings` set `sort`=66, `id_group`=4, `options`='radio||yes=1;no=0' WHERE `setting_name`='show_meta';

UPDATE `{PREFIX}system_settings` set `sort`=67, `id_group`=4, `options`='text' WHERE `setting_name`='datepicker_offset';

UPDATE `{PREFIX}system_settings` set `sort`=68, `id_group`=4, `options`='select||dd-mm-YYYY;mm/dd/YYYY;YYYY/mm/dd' WHERE `setting_name`='datetime_format';

UPDATE `{PREFIX}system_settings` set `sort`=69, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_logs';

UPDATE `{PREFIX}system_settings` set `sort`=70, `id_group`=4, `options`='text' WHERE `setting_name`='mail_check_timeperiod';

UPDATE `{PREFIX}system_settings` set `sort`=71, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_messages';

UPDATE `{PREFIX}system_settings` set `sort`=72, `id_group`=4, `options`='radio||yes=1;no=0' WHERE `setting_name`='pm2email';

UPDATE `{PREFIX}system_settings` set `sort`=73, `id_group`=4, `options`='text' WHERE `setting_name`='number_of_results';

UPDATE `{PREFIX}system_settings` set `sort`=74, `id_group`=4, `options`='radio||yes=1;no=0||depend||which_editor,fe_editor_lang,editor_css_path' WHERE `setting_name`='use_editor';

UPDATE `{PREFIX}system_settings` set `sort`=75, `id_group`=4, `options`='which_editor' WHERE `setting_name`='which_editor';

UPDATE `{PREFIX}system_settings` set `sort`=76, `id_group`=4, `options`='language' WHERE `setting_name`='fe_editor_lang';

UPDATE `{PREFIX}system_settings` set `sort`=77, `id_group`=4, `options`='text' WHERE `setting_name`='editor_css_path';

UPDATE `{PREFIX}system_settings` set `sort`=78, `id_group`=5, `options`='path' WHERE `setting_name`='filemanager_path';

UPDATE `{PREFIX}system_settings` set `sort`=79, `id_group`=5, `options`='text' WHERE `setting_name`='upload_files';

UPDATE `{PREFIX}system_settings` set `sort`=80, `id_group`=5, `options`='text' WHERE `setting_name`='upload_images';

UPDATE `{PREFIX}system_settings` set `sort`=81, `id_group`=5, `options`='text' WHERE `setting_name`='upload_media';

UPDATE `{PREFIX}system_settings` set `sort`=82, `id_group`=5, `options`='text' WHERE `setting_name`='upload_flash';

UPDATE `{PREFIX}system_settings` set `sort`=83, `id_group`=5, `options`='upload_maxsize' WHERE `setting_name`='upload_maxsize';

UPDATE `{PREFIX}system_settings` set `sort`=84, `id_group`=5, `options`='text' WHERE `setting_name`='new_file_permissions';

UPDATE `{PREFIX}system_settings` set `sort`=85, `id_group`=5, `options`='text' WHERE `setting_name`='new_folder_permissions';

UPDATE `{PREFIX}system_settings` set `sort`=86, `id_group`=5, `options`='radio||yes=1;no=0||depend||strip_image_paths,rb_webuser,rb_base_url,rb_base_dir,clean_uploaded_filename,image_limit_width' WHERE `setting_name`='use_browser';

UPDATE `{PREFIX}system_settings` set `sort`=87, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='strip_image_paths';

UPDATE `{PREFIX}system_settings` set `sort`=88, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='rb_webuser';

UPDATE `{PREFIX}system_settings` set `sort`=89, `id_group`=5, `options`='base_dir' WHERE `setting_name`='rb_base_dir';

UPDATE `{PREFIX}system_settings` set `sort`=90, `id_group`=5, `options`='text' WHERE `setting_name`='rb_base_url';

UPDATE `{PREFIX}system_settings` set `sort`=91, `id_group`=5, `options`='radio||yes=1;no=0' WHERE `setting_name`='clean_uploaded_filename';

UPDATE `{PREFIX}system_settings` set `sort`=92, `id_group`=5, `options`='text' WHERE `setting_name`='image_limit_width';

REPLACE INTO `{PREFIX}system_eventnames` (id,name,service,groupname) VALUES ('101','OnSystemSettingsRender','1','System Settings');