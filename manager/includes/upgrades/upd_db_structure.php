<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$prefix = db()->config['table_prefix'];

$queries = [
    [
        'table' => "{$prefix}site_content",
        'column' => 'publishedon',
        'query' => "ALTER TABLE `{$prefix}site_content` ADD COLUMN `publishedon` int(20) NOT NULL DEFAULT '0' COMMENT 'Date the document was published' AFTER `deletedby`;"
    ],
    [
        'table' => "{$prefix}site_content",
        'column' => 'publishedby',
        'query' => "ALTER TABLE `{$prefix}site_content` ADD COLUMN `publishedby` int(10) NOT NULL DEFAULT '0' COMMENT 'ID of user who published the document' AFTER `publishedon`;"
    ],
    [
        'table' => "{$prefix}site_content",
        'column' => 'link_attributes',
        'query' => "ALTER TABLE `{$prefix}site_content` ADD COLUMN `link_attributes` varchar(255) NOT NULL DEFAULT '' COMMENT 'Link attriubtes' AFTER `alias`;"
    ],
    [
        'table' => "{$prefix}site_content",
        'column' => 'alias_visible',
        'query' => "ALTER TABLE `{$prefix}site_content` ADD COLUMN `alias_visible` int(2) NOT NULL DEFAULT '1' COMMENT 'Hide document from alias path';"
    ],
    [
        'table' => "{$prefix}site_htmlsnippets",
        'column' => 'pub_date',
        'query' => "ALTER TABLE `{$prefix}site_htmlsnippets` ADD COLUMN `pub_date` int(20) NOT NULL default '0' AFTER `published`;"
    ],
    [
        'table' => "{$prefix}site_htmlsnippets",
        'column' => 'published',
        'query' => "ALTER TABLE `{$prefix}site_htmlsnippets` ADD COLUMN `published` int(1) NOT NULL default '1' AFTER `description`;"
    ],
    [
        'table' => "{$prefix}site_htmlsnippets",
        'column' => 'unpub_date',
        'query' => "ALTER TABLE `{$prefix}site_htmlsnippets` ADD COLUMN `unpub_date` int(20) NOT NULL default '0' AFTER `pub_date`;"
    ],
    [
        'table' => "{$prefix}site_plugin_events",
        'column' => 'priority',
        'query' => "ALTER TABLE `{$prefix}site_plugin_events` ADD COLUMN `priority` int(10) NOT NULL default '0' COMMENT 'determines the run order of the plugin' AFTER `evtid`;"
    ],
    [
        'table' => "{$prefix}site_templates",
        'column' => 'parent',
        'query' => "ALTER TABLE `{$prefix}site_templates` ADD COLUMN `parent` int(10) NOT NULL default '0' AFTER `content`;"
    ],
    [
        'table' => "{$prefix}site_tmplvar_templates",
        'column' => 'rank',
        'query' => "ALTER TABLE `{$prefix}site_tmplvar_templates` ADD COLUMN `rank` integer(11) NOT NULL DEFAULT '0' AFTER `templateid`;"
    ],
    [
        'table' => "{$prefix}user_attributes",
        'column' => 'city',
        'query' => "ALTER TABLE `{$prefix}user_attributes` ADD COLUMN `city` varchar(255) NOT NULL default '' AFTER `street`;"
    ],
    [
        'table' => "{$prefix}user_attributes",
        'column' => 'street',
        'query' => "ALTER TABLE `{$prefix}user_attributes` ADD COLUMN `street` varchar(255) NOT NULL default '' AFTER `country`;"
    ],
    [
        'table' => "{$prefix}web_user_attributes",
        'column' => 'street',
        'query' => "ALTER TABLE `{$prefix}web_user_attributes` ADD COLUMN `street` varchar(255) NOT NULL DEFAULT '' AFTER `country`;"
    ],
    [
        'table' => "{$prefix}web_user_attributes",
        'column' => 'city',
        'query' => "ALTER TABLE `{$prefix}web_user_attributes` ADD COLUMN `city` varchar(255) NOT NULL DEFAULT '' AFTER `street`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'edit_chunk',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `edit_chunk` int(1) NOT NULL DEFAULT '0' AFTER `delete_snippet`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'new_chunk',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `new_chunk` int(1) NOT NULL DEFAULT '0' AFTER `edit_chunk`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'save_chunk',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `save_chunk` int(1) NOT NULL DEFAULT '0' AFTER `new_chunk`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'delete_chunk',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `delete_chunk` int(1) NOT NULL DEFAULT '0' AFTER `save_chunk`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'empty_trash',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `empty_trash` int(1) NOT NULL DEFAULT '0' AFTER `delete_document`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'view_unpublished',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `view_unpublished` int(1) NOT NULL DEFAULT '0' AFTER `web_access_permissions`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'import_static',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `import_static` int(1) NOT NULL DEFAULT '0' AFTER `view_unpublished`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'export_static',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `export_static` int(1) NOT NULL DEFAULT '0' AFTER `import_static`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'remove_locks',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `remove_locks` int(1) NOT NULL DEFAULT '0' AFTER `export_static`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'view_schedule',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `view_schedule` int(1) NOT NULL DEFAULT '0' AFTER `remove_locks`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'publish_document',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `publish_document` int(1) NOT NULL DEFAULT '0' AFTER `save_document`;"
    ],
    [
        'table' => "{$prefix}user_roles",
        'column' => 'move_document',
        'query' => "ALTER TABLE `{$prefix}user_roles` ADD COLUMN `move_document` int(1) NOT NULL DEFAULT '0' AFTER `save_document`;"
    ]
];

foreach ($queries as $query) {
    if (!db()->fieldExists("`{$query['column']}`", $query['table'])) {
        db()->query($query['query']);
    }
}

// ...existing code...

db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `pagetitle` varchar(255) NOT NULL default '';");
db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `alias` varchar(245) default '';");
db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `introtext` text COMMENT 'Used to provide quick summary of the document';");
db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `content` mediumtext;");
db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `menutitle` varchar(255) NOT NULL DEFAULT '' COMMENT 'Menu title';");
db()->query("ALTER TABLE `{$prefix}site_content` MODIFY COLUMN `template` int(10) NOT NULL default '0';");

// Check if index 'content_ft_idx' exists before dropping
$indexExists = db()->getValue(db()->query("SHOW INDEX FROM `{$prefix}site_content` WHERE Key_name = 'content_ft_idx'"));
if ($indexExists) {
    db()->query("ALTER TABLE `{$prefix}site_content` DROP INDEX `content_ft_idx`;");
}

// Check if index 'typeidx' exists before adding
$indexExists = db()->getValue(db()->query("SHOW INDEX FROM `{$prefix}site_content` WHERE Key_name = 'typeidx'"));
if (!$indexExists) {
    db()->query("ALTER TABLE `{$prefix}site_content` ADD INDEX `typeidx` (`type`);");
}

db()->query("UPDATE `{$prefix}site_content` SET `type`='reference', `contentType`='text/html' WHERE `type` = '' AND `content` REGEXP '^https?://([-\w\.]+)+(:\d+)?/?';");
db()->query("UPDATE `{$prefix}site_content` SET `type`='document', `contentType`='text/xml' WHERE `type` = '' AND `alias` REGEXP '\.(rss|xml)$';");
db()->query("UPDATE `{$prefix}site_content` SET `type`='document', `contentType`='text/javascript' WHERE `type` = '' AND `alias` REGEXP '\.js$';");
db()->query("UPDATE `{$prefix}site_content` SET `type`='document', `contentType`='text/css' WHERE `type` = '' AND `alias` REGEXP '\.css$';");
db()->query("UPDATE `{$prefix}site_content` SET `type`='document', `contentType`='text/html' WHERE `type` = '';");

db()->query("ALTER TABLE `{$prefix}site_htmlsnippets` MODIFY COLUMN `snippet` mediumtext;");

// Check if primary key exists before dropping
$primaryKeyExists = db()->getValue(db()->query("SHOW KEYS FROM `{$prefix}site_plugin_events` WHERE Key_name = 'PRIMARY'"));
if ($primaryKeyExists) {
    db()->query("ALTER TABLE `{$prefix}site_plugin_events` DROP PRIMARY KEY;");
}
db()->query("ALTER TABLE `{$prefix}site_plugin_events` ADD PRIMARY KEY (`pluginid`, `evtid`);");

db()->query("ALTER TABLE `{$prefix}site_templates` MODIFY COLUMN `icon` varchar(255) NOT NULL default '' COMMENT 'url to icon file';");
db()->query("ALTER TABLE `{$prefix}site_templates` MODIFY COLUMN `content` mediumtext;");

// Check if indexes exist before dropping
$indexExists = db()->getValue(db()->query("SHOW INDEX FROM `{$prefix}site_tmplvar_templates` WHERE Key_name = 'idx_tmplvarid'"));
if ($indexExists) {
    db()->query("ALTER TABLE `{$prefix}site_tmplvar_templates` DROP INDEX `idx_tmplvarid`;");
}
$indexExists = db()->getValue(db()->query("SHOW INDEX FROM `{$prefix}site_tmplvar_templates` WHERE Key_name = 'idx_templateid'"));
if ($indexExists) {
    db()->query("ALTER TABLE `{$prefix}site_tmplvar_templates` DROP INDEX `idx_templateid`;");
}

// Check if primary key exists before dropping
$primaryKeyExists = db()->getValue(db()->query("SHOW KEYS FROM `{$prefix}site_tmplvar_templates` WHERE Key_name = 'PRIMARY'"));
if ($primaryKeyExists) {
    db()->query("ALTER TABLE `{$prefix}site_tmplvar_templates` DROP PRIMARY KEY;");
}
db()->query("ALTER TABLE `{$prefix}site_tmplvar_templates` ADD PRIMARY KEY (`tmplvarid`, `templateid`);");

db()->query("ALTER TABLE `{$prefix}site_tmplvar_contentvalues` MODIFY COLUMN `tmplvarid` int(10) NOT NULL DEFAULT '0' COMMENT 'Template Variable id';");
db()->query("ALTER TABLE `{$prefix}site_tmplvar_contentvalues` MODIFY COLUMN `value` mediumtext;");

db()->query("ALTER TABLE `{$prefix}site_tmplvars` MODIFY COLUMN `name` varchar(50) NOT NULL default '';");
db()->query("ALTER TABLE `{$prefix}site_tmplvars` MODIFY COLUMN `elements` text;");
db()->query("ALTER TABLE `{$prefix}site_tmplvars` MODIFY COLUMN `display` varchar(20) NOT NULL DEFAULT '' COMMENT 'Display Control';");
db()->query("ALTER TABLE `{$prefix}site_tmplvars` MODIFY COLUMN `display_params` text COMMENT 'Display Control Properties';");
db()->query("ALTER TABLE `{$prefix}site_tmplvars` MODIFY COLUMN `default_text` text;");

db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `country` varchar(5) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo';");
db()->query("ALTER TABLE `{$prefix}user_attributes` MODIFY COLUMN `comment` text;");

db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `country` varchar(25) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `state` varchar(25) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `zip` varchar(25) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `fax` varchar(100) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'link to photo';");
db()->query("ALTER TABLE `{$prefix}web_user_attributes` MODIFY COLUMN `comment` text;");

db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `edit_chunk` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `new_chunk` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `save_chunk` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `delete_chunk` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `empty_trash` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `view_unpublished` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `import_static` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `export_static` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `remove_locks` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `view_schedule` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `publish_document` int(1) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}user_roles` MODIFY COLUMN `move_document` int(1) NOT NULL DEFAULT '0';");

db()->query("ALTER TABLE `{$prefix}active_users` MODIFY COLUMN `ip` varchar(50) NOT NULL DEFAULT '';");

db()->query("ALTER TABLE `{$prefix}event_log` MODIFY COLUMN `source` varchar(245) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}event_log` MODIFY COLUMN `description` text;");

db()->query("ALTER TABLE `{$prefix}categories` MODIFY COLUMN `category` varchar(45) NOT NULL DEFAULT '';");

db()->query("ALTER TABLE `{$prefix}manager_users` MODIFY COLUMN `username` varchar(100) NOT NULL DEFAULT '';");

db()->query("ALTER TABLE `{$prefix}site_module_access` MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}site_module_access` MODIFY COLUMN `usergroup` int(11) NOT NULL DEFAULT '0';");

db()->query("ALTER TABLE `{$prefix}site_module_depobj` MODIFY COLUMN `module` int(11) NOT NULL DEFAULT '0';");
db()->query("ALTER TABLE `{$prefix}site_module_depobj` MODIFY COLUMN `resource` int(11) NOT NULL DEFAULT '0';");

db()->query("ALTER TABLE `{$prefix}site_modules` MODIFY COLUMN `name` varchar(50) NOT NULL DEFAULT '';");
db()->query("ALTER TABLE `{$prefix}site_modules` MODIFY COLUMN `disabled` tinyint(1) NOT NULL DEFAULT '0';");
?>
