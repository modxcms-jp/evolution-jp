<?php
/**
 *	MODX Configuration file
 */
$database_type               = '[+database_type+]';
$database_server             = env('DB_HOST', '[+database_server+]');
$database_user               = env('DB_USERNAME', '[+database_user+]');
$database_password           = env('DB_PASSWORD', '[+database_password+]');
$database_connection_charset = env('DB_CHARSET', '[+database_connection_charset+]');
$dbase                       = env('DB_DATABASE', '[+dbase+]');
$table_prefix                = env('TABLE_PREFIX', '[+table_prefix+]');
$https_port                  = '[+https_port+]';
$filemanager_path            = env('MODX_FILEMANAGER_PATH', '[+filemanager_path+]');
$rb_base_dir                 = env('MODX_RB_BASE_DIR', '[+rb_base_dir+]');

$lastInstallTime             = [+lastInstallTime+];

setlocale (LC_TIME, 'ja_JP.UTF-8');
if(function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Tokyo');
}

if (is_file(__DIR__ . '/extra.config.php')) {
    include __DIR__ . '/extra.config.php';
}

include_once __DIR__ . '/initialize.inc.php';
