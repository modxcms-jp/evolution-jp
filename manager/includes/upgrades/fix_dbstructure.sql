# For backward compatibilty with early versions
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

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
('101','OnLoadDocumentObject','5',''),
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
('209','OnManagerTreeInit','2',''),
('210','OnManagerTreePrerender','2',''),
('211','OnManagerTreeRender','2',''),
('300','OnMakeUrl','1',''),
('999','OnPageUnauthorized','1',''),
('1000','OnPageNotFound','1','');

# ^ I don't think we need more than 1000 built-in events. Custom events will start at 1001


# 090-091

ALTER TABLE `modx_site_content` ADD COLUMN `publishedon` int(20) NOT NULL DEFAULT '0' COMMENT 'Date the document was published' AFTER `deletedby`;

ALTER TABLE `modx_site_content` ADD COLUMN `publishedby` int(10) NOT NULL DEFAULT '0' COMMENT 'ID of user who published the document' AFTER `publishedon`;

ALTER TABLE `modx_site_plugins` MODIFY COLUMN `properties` text COMMENT 'Default Properties';

ALTER TABLE `modx_site_snippets` MODIFY COLUMN `properties` text COMMENT 'Default Properties';

ALTER TABLE `modx_site_tmplvar_templates`
 DROP INDEX `idx_tmplvarid`,
 DROP INDEX `idx_templateid`,
 ADD PRIMARY KEY (`tmplvarid`, `templateid`);

ALTER TABLE `modx_user_roles` ADD COLUMN `view_unpublished` int(1) NOT NULL DEFAULT '0' AFTER `web_access_permissions`;

#091-092

#092-095

ALTER TABLE `modx_categories` MODIFY COLUMN `category` varchar(45) NOT NULL DEFAULT '';

ALTER TABLE `modx_categories` MODIFY COLUMN `category` varchar(45) NOT NULL DEFAULT '';

ALTER TABLE `modx_event_log` MODIFY COLUMN `source` varchar(50) NOT NULL DEFAULT '';

ALTER TABLE `modx_event_log` MODIFY COLUMN `description` text;

ALTER TABLE `modx_manager_users` MODIFY COLUMN `username` varchar(100) NOT NULL DEFAULT '';

ALTER TABLE `modx_site_content` 
 MODIFY COLUMN `pagetitle` varchar(255) NOT NULL default '',
 MODIFY COLUMN `alias` varchar(255) default '',
 MODIFY COLUMN `introtext` text COMMENT 'Used to provide quick summary of the document',
 MODIFY COLUMN `content` mediumtext,
 MODIFY COLUMN `menutitle` varchar(255) NOT NULL DEFAULT '' COMMENT 'Menu title';

ALTER TABLE `modx_site_content` ADD COLUMN `link_attributes` varchar(255) NOT NULL DEFAULT '' COMMENT 'Link attriubtes' AFTER `alias`;

ALTER TABLE `modx_site_htmlsnippets` MODIFY COLUMN `snippet` mediumtext;

ALTER TABLE `modx_site_modules`
 MODIFY COLUMN `name` varchar(50) NOT NULL DEFAULT '',
 MODIFY COLUMN `disabled` tinyint(4) NOT NULL DEFAULT '0',
 MODIFY COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT 'url to module icon',
 MODIFY COLUMN `resourcefile` varchar(255) NOT NULL DEFAULT '' COMMENT 'a physical link to a resource file',
 MODIFY COLUMN `createdon` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `editedon` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `guid` varchar(32) NOT NULL DEFAULT '' COMMENT 'globally unique identifier',
 MODIFY COLUMN `properties` text,
 MODIFY COLUMN `modulecode` mediumtext COMMENT 'module boot up code';

ALTER TABLE `modx_site_module_access`
 MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `usergroup` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `modx_site_module_depobj`
 MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0',
 MODIFY COLUMN `resource` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `modx_site_plugins`
 MODIFY COLUMN `plugincode` mediumtext,
 MODIFY COLUMN `moduleguid` varchar(32) NOT NULL DEFAULT '' COMMENT 'GUID of module from which to import shared parameters';

ALTER TABLE `modx_site_plugin_events`
 MODIFY COLUMN `evtid` int(10) NOT NULL DEFAULT '0';

ALTER TABLE `modx_site_plugin_events` ADD COLUMN `priority` INT(10) NOT NULL default '0' COMMENT 'determines the run order of the plugin' AFTER `evtid`;

ALTER TABLE `modx_site_snippets`
 MODIFY COLUMN `snippet` mediumtext,
 MODIFY COLUMN `moduleguid` varchar(32) NOT NULL DEFAULT '' COMMENT 'GUID of module from which to import shared parameters';

ALTER TABLE `modx_site_templates`
 MODIFY COLUMN `icon` varchar(255) NOT NULL default '' COMMENT 'url to icon file',
 MODIFY COLUMN `content` mediumtext;

ALTER TABLE `modx_site_tmplvars`
 MODIFY COLUMN `name` varchar(50) NOT NULL default '',
 MODIFY COLUMN `elements` text,
 MODIFY COLUMN `display` varchar(20) NOT NULL DEFAULT '' COMMENT 'Display Control',
 MODIFY COLUMN `display_params` text COMMENT 'Display Control Properties',
 MODIFY COLUMN `default_text` text;

ALTER TABLE `modx_site_tmplvar_contentvalues`
 MODIFY COLUMN `tmplvarid` int(10) NOT NULL DEFAULT '0' COMMENT 'Template Variable id',
 MODIFY COLUMN `value` text;

ALTER TABLE `modx_site_tmplvar_templates` MODIFY COLUMN `tmplvarid` int(10) NOT NULL DEFAULT '0' COMMENT 'Template Variable id';

ALTER TABLE `modx_site_tmplvar_templates` ADD COLUMN `rank` integer(11) NOT NULL DEFAULT '0' AFTER `templateid`;

ALTER TABLE `modx_system_eventnames`
 MODIFY COLUMN  `name` varchar(50) NOT NULL DEFAULT '',
 MODIFY COLUMN `service` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'System Service number';

ALTER TABLE `modx_system_settings` MODIFY COLUMN `setting_value` text;

ALTER TABLE `modx_user_attributes`
 MODIFY COLUMN `country` varchar(5) NOT NULL DEFAULT '',
 MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo',
 MODIFY COLUMN `comment` varchar(255) NOT NULL DEFAULT '' COMMENT 'short comment';

ALTER TABLE `modx_user_settings` MODIFY COLUMN `setting_value` text;

ALTER TABLE `modx_user_messages` MODIFY COLUMN `message` text;

ALTER TABLE `modx_user_roles` ADD COLUMN `publish_document` int(1) NOT NULL DEFAULT '0' AFTER `save_document`;

ALTER TABLE `modx_web_users`
 MODIFY COLUMN `username` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `cachepwd` varchar(100) NOT NULL DEFAULT '' COMMENT 'Store new unconfirmed password' AFTER `password`;

ALTER TABLE `modx_web_user_attributes`
 MODIFY COLUMN `country` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '',
 MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo';

ALTER TABLE `modx_web_user_settings` MODIFY COLUMN `setting_value` text;

#095-096

ALTER TABLE `modx_user_roles`
 ADD COLUMN `edit_chunk` int(1) NOT NULL DEFAULT '0' AFTER `delete_snippet`,
 ADD COLUMN `new_chunk` int(1) NOT NULL DEFAULT '0' AFTER `edit_chunk`,
 ADD COLUMN `save_chunk` int(1) NOT NULL DEFAULT '0' AFTER `new_chunk`,
 ADD COLUMN `delete_chunk` int(1) NOT NULL DEFAULT '0' AFTER `save_chunk`,
 ADD COLUMN `import_static` int(1) NOT NULL DEFAULT '0' AFTER `view_unpublished`,
 ADD COLUMN `export_static` int(1) NOT NULL DEFAULT '0' AFTER `import_static`;

ALTER TABLE `modx_web_user_attributes`
 MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '',
 MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '';

#096-0961

#0961-0963

ALTER TABLE `modx_user_roles` ADD COLUMN `empty_trash` int(1) NOT NULL DEFAULT '0' AFTER `delete_document`;

#0963-1.0.0

#1.0.3-1.0.4

ALTER TABLE `modx_user_roles` ADD COLUMN `remove_locks` int(1) NOT NULL DEFAULT '0';

#1.0.4-1.0.5

ALTER TABLE `modx_member_groups` ADD UNIQUE INDEX `ix_group_member` (`user_group`,`member`);

ALTER TABLE `modx_user_attributes` MODIFY COLUMN `comment` text;

ALTER TABLE `modx_web_groups` ADD UNIQUE INDEX `ix_group_user` (`webgroup`,`webuser`);

ALTER TABLE `modx_web_user_attributes` MODIFY COLUMN `comment` text;

# Set the private manager group flag

UPDATE modx_documentgroup_names AS dgn
  LEFT JOIN modx_membergroup_access AS mga ON mga.documentgroup = dgn.id
  LEFT JOIN modx_webgroup_access AS wga ON wga.documentgroup = dgn.id
  SET dgn.private_memgroup = (mga.membergroup IS NOT NULL),
      dgn.private_webgroup = (wga.webgroup IS NOT NULL);

UPDATE `modx_site_content` SET `type`='reference', `contentType`='text/html' WHERE `type`='' AND `content` REGEXP '^https?://([-\w\.]+)+(:\d+)?/?';

UPDATE `modx_site_content` SET `type`='document', `contentType`='text/xml' WHERE `type`='' AND `alias` REGEXP '\.(rss|xml)$';

UPDATE `modx_site_content` SET `type`='document', `contentType`='text/javascript' WHERE `type`='' AND `alias` REGEXP '\.js$';

UPDATE `modx_site_content` SET `type`='document', `contentType`='text/css' WHERE `type`='' AND `alias` REGEXP '\.css$';

UPDATE `modx_site_content` SET `type`='document', `contentType`='text/html' WHERE `type`='';

#1.0.5-1.0.6

ALTER TABLE `modx_site_content` MODIFY COLUMN `template` int(10) NOT NULL default '0';

ALTER TABLE `modx_site_content` ADD INDEX `typeidx` (`type`);

ALTER TABLE `modx_site_htmlsnippets` ADD COLUMN `published` int(1) NOT NULL default '1' AFTER `description`;

ALTER TABLE `modx_site_htmlsnippets` ADD COLUMN `pub_date` int(20) NOT NULL default '0' AFTER `published`;

ALTER TABLE `modx_site_htmlsnippets` ADD COLUMN `unpub_date` int(20) NOT NULL default '0' AFTER `pub_date`;

ALTER TABLE `modx_system_settings` DROP PRIMARY KEY;

ALTER TABLE `modx_system_settings` DROP INDEX `setting_name`;

ALTER TABLE `modx_system_settings` ADD PRIMARY KEY (`setting_name`);

ALTER TABLE `modx_user_settings` DROP PRIMARY KEY;

ALTER TABLE `modx_user_settings` ADD PRIMARY KEY (`user`, `setting_name`);

ALTER TABLE `modx_web_user_settings` DROP PRIMARY KEY;

ALTER TABLE `modx_web_user_settings` ADD PRIMARY KEY (`webuser`, `setting_name`);

ALTER TABLE `modx_site_plugin_events` DROP PRIMARY KEY;

ALTER TABLE `modx_site_plugin_events` ADD PRIMARY KEY (`pluginid`, `evtid`);

ALTER TABLE `modx_active_users` MODIFY COLUMN `ip` varchar(50) NOT NULL DEFAULT '';

ALTER TABLE `modx_site_tmplvar_contentvalues` ADD FULLTEXT `value_ft_idx` (`value`);

ALTER TABLE `modx_user_roles` ADD COLUMN `view_schedule` int(1) NOT NULL DEFAULT '0' AFTER `remove_locks`;

#1.0.8-1.0.8J-r1

ALTER TABLE `modx_site_templates` ADD COLUMN `parent` int(10) NOT NULL default '0' AFTER `content`;

# end related to #MODX-1321

ALTER TABLE `modx_site_content` DROP INDEX `content_ft_idx`;

UPDATE `modx_site_plugins` SET disabled='1' WHERE `name`='ダッシュボード・あなたの情報' OR `name`='ダッシュボード・オンライン情報';

#1.0.10-1.0.12J

ALTER TABLE `modx_user_attributes`
 ADD COLUMN `street` varchar(255) NOT NULL default '' AFTER `country`,
 ADD COLUMN `city` varchar(255) NOT NULL default '' AFTER `street`;

ALTER TABLE `modx_web_user_attributes`
 ADD COLUMN `street` varchar(255) NOT NULL default '' AFTER `country`,
 ADD COLUMN `city` varchar(255) NOT NULL default '' AFTER `street`;



