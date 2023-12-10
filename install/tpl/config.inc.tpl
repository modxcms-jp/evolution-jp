<?php
/**
 *	MODX Configuration file
 */
$database_type               = '[+database_type+]';
$database_server             = '[+database_server+]';
$database_user               = '[+database_user+]';
$database_password           = '[+database_password+]';
$database_connection_charset = '[+database_connection_charset+]';
$database_connection_method  = '[+database_connection_method+]';
$dbase                       = '[+dbase+]';
$table_prefix                = '[+table_prefix+]';

$https_port                  = '[+https_port+]';

$lastInstallTime             = [+lastInstallTime+];

setlocale (LC_TIME, 'ja_JP.UTF-8');
if(function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Tokyo');
}

if (is_file(__DIR__ . '/extra.config.php')) {
    include __DIR__ . '/extra.config.php';
}

include_once __DIR__ . '/initialize.inc.php';
