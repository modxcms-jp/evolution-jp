<?php

function addColumnIfNotExists($table, $column, $definition) {
    $query = sprintf(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '%s' AND COLUMN_NAME = '%s'",
        $table,
        $column
    );
    $result = db()->query($query);
    $exists = db()->getValue($result);

    if ($exists == 0) {
        $query = sprintf(
            "ALTER TABLE `%s` ADD COLUMN `%s` %s",
            $table,
            $column,
            $definition
        );
        db()->query($query);
    }
}

$table = sessionv('table_prefix') . 'user_roles';

addColumnIfNotExists($table, 'edit_chunk', 'int(1) NOT NULL DEFAULT \'0\' AFTER `delete_snippet`');
addColumnIfNotExists($table, 'new_chunk', 'int(1) NOT NULL DEFAULT \'0\' AFTER `edit_chunk`');
addColumnIfNotExists($table, 'save_chunk', 'int(1) NOT NULL DEFAULT \'0\' AFTER `new_chunk`');
addColumnIfNotExists($table, 'delete_chunk', 'int(1) NOT NULL DEFAULT \'0\' AFTER `save_chunk`');
addColumnIfNotExists($table, 'empty_trash', 'int(1) NOT NULL DEFAULT \'0\' AFTER `delete_document`');
addColumnIfNotExists($table, 'view_unpublished', 'int(1) NOT NULL DEFAULT \'0\' AFTER `web_access_permissions`');
addColumnIfNotExists($table, 'import_static', 'int(1) NOT NULL DEFAULT \'0\' AFTER `view_unpublished`');
addColumnIfNotExists($table, 'export_static', 'int(1) NOT NULL DEFAULT \'0\' AFTER `import_static`');
addColumnIfNotExists($table, 'remove_locks', 'int(1) NOT NULL DEFAULT \'0\' AFTER `export_static`');
addColumnIfNotExists($table, 'view_schedule', 'int(1) NOT NULL DEFAULT \'0\' AFTER `remove_locks`');
addColumnIfNotExists($table, 'publish_document', 'int(1) NOT NULL DEFAULT \'0\' AFTER `save_document`');
addColumnIfNotExists($table, 'move_document', 'int(1) NOT NULL DEFAULT \'0\' AFTER `save_document`');

$table = sessionv('table_prefix') . 'documentgroup_names';
db()->query("ALTER TABLE `$table` CHANGE `name` `name` varchar(191) NOT NULL DEFAULT '' AFTER `id`");

$table = sessionv('table_prefix') . 'membergroup_names';
db()->query("ALTER TABLE `$table` CHANGE `name` `name` varchar(191) NOT NULL DEFAULT '' AFTER `id`");

$table = sessionv('table_prefix') . 'webgroup_names';
db()->query("ALTER TABLE `$table` CHANGE `name` `name` varchar(191) NOT NULL DEFAULT '' AFTER `id`");

$table = sessionv('table_prefix') . 'event_log';
db()->query("ALTER TABLE `$table` MODIFY `source` VARCHAR(255)");

$table = sessionv('table_prefix') . 'site_plugins';
addColumnIfNotExists($table, 'error_reporting', "varchar(8) NOT NULL DEFAULT 'inherit' AFTER `properties`");

$table = sessionv('table_prefix') . 'site_snippets';
addColumnIfNotExists($table, 'error_reporting', "varchar(8) NOT NULL DEFAULT 'inherit' AFTER `properties`");

$data = [
    'id' => 1,
    'name' => 'Administrator',
    'description' => 'Site administrators have full access to all functions',
    'frames' => 1,
    'home' => 1,
    'view_document' => 1,
    'new_document' => 1,
    'save_document' => 1,
    'move_document' => 1,
    'publish_document' => 1,
    'delete_document' => 1,
    'empty_trash' => 1,
    'action_ok' => 1,
    'logout' => 1,
    'help' => 1,
    'messages' => 1,
    'new_user' => 1,
    'edit_user' => 1,
    'logs' => 1,
    'edit_parser' => 1,
    'save_parser' => 1,
    'edit_template' => 1,
    'settings' => 1,
    'credits' => 1,
    'new_template' => 1,
    'save_template' => 1,
    'delete_template' => 1,
    'edit_snippet' => 1,
    'new_snippet' => 1,
    'save_snippet' => 1,
    'delete_snippet' => 1,
    'edit_chunk' => 1,
    'new_chunk' => 1,
    'save_chunk' => 1,
    'delete_chunk' => 1,
    'empty_cache' => 1,
    'edit_document' => 1,
    'change_password' => 1,
    'error_dialog' => 1,
    'about' => 1,
    'file_manager' => 1,
    'save_user' => 1,
    'delete_user' => 1,
    'save_password' => 1,
    'edit_role' => 1,
    'save_role' => 1,
    'delete_role' => 1,
    'new_role' => 1,
    'access_permissions' => 1,
    'bk_manager' => 1,
    'new_plugin' => 1,
    'edit_plugin' => 1,
    'save_plugin' => 1,
    'delete_plugin' => 1,
    'new_module' => 1,
    'edit_module' => 1,
    'save_module' => 1,
    'exec_module' => 1,
    'delete_module' => 1,
    'view_eventlog' => 1,
    'delete_eventlog' => 1,
    'manage_metatags' => 1,
    'edit_doc_metatags' => 1,
    'new_web_user' => 1,
    'edit_web_user' => 1,
    'save_web_user' => 1,
    'delete_web_user' => 1,
    'web_access_permissions' => 1,
    'view_unpublished' => 1,
    'import_static' => 1,
    'export_static' => 1,
    'remove_locks' => 1,
    'view_schedule' => 1
];

$columns = array_keys($data);
$values = array_values($data);

$columns_str = implode(',', $columns);
$values_str = implode(',', array_map(function($value) {
    return is_string($value) ? "'$value'" : $value;
}, $values));

// SQL文を組み立てる
$query = "REPLACE INTO `{PREFIX}user_roles` ($columns_str) VALUES ($values_str)";
$query = str_replace('{PREFIX}', db()->table_prefix, $query);

db()->query($query);

$query = "REPLACE INTO `{PREFIX}system_eventnames` (id,name,service,groupname) VALUES ('1','OnDocPublished','1','Documents'), ('2','OnDocUnPublished','1','Documents'), ('3','OnWebPagePrerender','5',''), ('4','OnWebLogin','3',''), ('5','OnBeforeWebLogout','3',''), ('6','OnWebLogout','3',''), ('7','OnWebSaveUser','3',''), ('8','OnWebDeleteUser','3',''), ('9','OnWebChangePassword','3',''), ('10','OnWebCreateGroup','3',''), ('11','OnManagerLogin','2',''), ('12','OnBeforeManagerLogout','2',''), ('13','OnManagerLogout','2',''), ('14','OnManagerSaveUser','2',''), ('15','OnManagerDeleteUser','2',''), ('16','OnManagerChangePassword','2',''), ('17','OnManagerCreateGroup','2',''), ('18','OnBeforeCacheUpdate','4',''), ('19','OnCacheUpdate','4',''), ('20','OnLoadWebPageCache','4',''), ('21','OnBeforeSaveWebPageCache','4',''), ('22','OnChunkFormPrerender','1','Chunks'), ('23','OnChunkFormRender','1','Chunks'), ('24','OnBeforeChunkFormSave','1','Chunks'), ('25','OnChunkFormSave','1','Chunks'), ('26','OnBeforeChunkFormDelete','1','Chunks'), ('27','OnChunkFormDelete','1','Chunks'), ('28','OnDocFormPrerender','1','Documents'), ('215','OnDocListRender','1','Documents'), ('216','OnDocListPrerender','1','Documents'), ('29','OnDocFormRender','1','Documents'), ('30','OnBeforeDocFormSave','1','Documents'), ('31','OnDocFormSave','1','Documents'), ('32','OnBeforeDocFormDelete','1','Documents'), ('33','OnDocFormDelete','1','Documents'), ('34','OnPluginFormPrerender','1','Plugins'), ('35','OnPluginFormRender','1','Plugins'), ('36','OnBeforePluginFormSave','1','Plugins'), ('37','OnPluginFormSave','1','Plugins'), ('38','OnBeforePluginFormDelete','1','Plugins'), ('39','OnPluginFormDelete','1','Plugins'), ('40','OnSnipFormPrerender','1','Snippets'), ('41','OnSnipFormRender','1','Snippets'), ('42','OnBeforeSnipFormSave','1','Snippets'), ('43','OnSnipFormSave','1','Snippets'), ('44','OnBeforeSnipFormDelete','1','Snippets'), ('45','OnSnipFormDelete','1','Snippets'), ('46','OnTempFormPrerender','1','Templates'), ('47','OnTempFormRender','1','Templates'), ('48','OnBeforeTempFormSave','1','Templates'), ('49','OnTempFormSave','1','Templates'), ('50','OnBeforeTempFormDelete','1','Templates'), ('51','OnTempFormDelete','1','Templates'), ('52','OnTVFormPrerender','1','Template Variables'), ('53','OnTVFormRender','1','Template Variables'), ('54','OnBeforeTVFormSave','1','Template Variables'), ('55','OnTVFormSave','1','Template Variables'), ('56','OnBeforeTVFormDelete','1','Template Variables'), ('57','OnTVFormDelete','1','Template Variables'), ('58','OnUserFormPrerender','1','Users'), ('59','OnUserFormRender','1','Users'), ('60','OnBeforeUserFormSave','1','Users'), ('61','OnUserFormSave','1','Users'), ('62','OnBeforeUserFormDelete','1','Users'), ('63','OnUserFormDelete','1','Users'), ('64','OnWUsrFormPrerender','1','Web Users'), ('65','OnWUsrFormRender','1','Web Users'), ('66','OnBeforeWUsrFormSave','1','Web Users'), ('67','OnWUsrFormSave','1','Web Users'), ('68','OnBeforeWUsrFormDelete','1','Web Users'), ('69','OnWUsrFormDelete','1','Web Users'), ('70','OnSiteRefresh','1',''), ('71','OnFileManagerUpload','2',''), ('72','OnModFormPrerender','1','Modules'), ('73','OnModFormRender','1','Modules'), ('74','OnBeforeModFormDelete','1','Modules'), ('75','OnModFormDelete','1','Modules'), ('76','OnBeforeModFormSave','1','Modules'), ('77','OnModFormSave','1','Modules'), ('78','OnBeforeWebLogin','3',''), ('79','OnWebAuthentication','3',''), ('80','OnBeforeManagerLogin','2',''), ('81','OnManagerAuthentication','2',''), ('82','OnSiteSettingsRender','1','System Settings'), ('83','OnFriendlyURLSettingsRender','1','System Settings'), ('84','OnUserSettingsRender','1','System Settings'), ('85','OnInterfaceSettingsRender','1','System Settings'), ('86','OnMiscSettingsRender','1','System Settings'), ('87','OnRichTextEditorRegister','1','RichText Editor'), ('88','OnRichTextEditorInit','1','RichText Editor'), ('89','OnManagerPageInit','2',''), ('90','OnWebPageInit','5',''), ('104','OnBeforeGetDocID','5',''), ('101','OnLoadDocumentObject','5',''), ('91','OnLoadWebDocument','5',''), ('92','OnParseDocument','5',''), ('93','OnManagerLoginFormRender','2',''), ('94','OnWebPageComplete','5',''), ('95','OnLogPageHit','5',''), ('96','OnBeforeManagerPageInit','2',''), ('97','OnBeforeEmptyTrash','1','Documents'), ('98','OnEmptyTrash','1','Documents'), ('99','OnManagerLoginFormPrerender','2',''), ('100','OnStripAlias','1','Documents'), ('102','OnBeforeDocFormUnDelete','1','Documents'), ('103','OnDocFormUnDelete','1','Documents'), ('200','OnCreateDocGroup','1','Documents'), ('201','OnManagerWelcomePrerender','2',''), ('202','OnManagerWelcomeHome','2',''), ('203','OnManagerWelcomeRender','2',''), ('204','OnBeforeDocDuplicate','1','Documents'), ('205','OnDocDuplicate','1','Documents'), ('206','OnManagerMainFrameHeaderHTMLBlock','2',''), ('207','OnManagerPreFrameLoader','2',''), ('208','OnManagerFrameLoader','2',''), ('209','OnManagerTreeInit','2',''), ('210','OnManagerTreePrerender','2',''), ('211','OnManagerTreeRender','2',''), ('212','OnSystemSettingsRender','1','System Settings'), ('213','OnManagerNodePrerender','2',''), ('214','OnManagerNodeRender','2',''), ('300','OnMakeUrl','1',''), ('301','OnExportPreExec','2',''), ('302','OnExportExec','2',''), ('303','OnGetConfig','1',''), ('304','OnCallChunk','1','Chunks'), ('999','OnPageUnauthorized','1',''), ('1000','OnPageNotFound','1','')";

db()->query(str_replace('{PREFIX}', sessionv('table_prefix'), $query));
