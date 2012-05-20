<?php
/**
 *	MODx Configuration file
 */
$database_type               = '[+database_type+]';
$database_server             = '[+database_server+]';
$database_user               = '[+database_user+]';
$database_password           = '[+database_password+]';
$database_connection_charset = '[+database_connection_charset+]';
$database_connection_method  = '[+database_connection_method+]';
$dbase                       = '`[+dbase+]`';
$table_prefix                = '[+table_prefix+]';

$https_port                  = '[+https_port+]';

$lastInstallTime             = [+lastInstallTime+];
$site_sessionname            = '[+site_sessionname+]';

if (!defined('MODX_SITE_URL')) include_once(str_replace('\\', '/', dirname(__FILE__)) . '/initialize.inc.php');
