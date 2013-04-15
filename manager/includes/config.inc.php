<?php
/**
 *	MODX Configuration file
 */
$database_type               = 'mysql';
$database_server             = 'localhost';
$database_user               = 'senku';
$database_password           = 'ne3n@ya4';
$database_connection_charset = 'utf8';
$database_connection_method  = 'SET CHARACTER SET';
$dbase                       = '`modx_dev`';
$table_prefix                = 'modx_';

$https_port                  = '443';

$lastInstallTime             = 1366048326;

if(!defined('MGR_DIR')) define('MGR_DIR', 'manager');

setlocale (LC_TIME, 'ja_JP.UTF-8');
if(function_exists('date_default_timezone_set')) date_default_timezone_set('Asia/Tokyo');

include_once(dirname(__FILE__) . '/initialize.inc.php');
