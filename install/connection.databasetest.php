<?php
define('MODX_API_MODE', true);
define('MODX_BASE_PATH', str_replace('\\','/', dirname(__DIR__)).'/');
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_BASE_PATH . 'install/functions.php');

includeLang(getOption('install_language'));

$modx->db->hostname = sessionv('database_server');
$modx->db->username = sessionv('database_user');
$modx->db->password = sessionv('database_password');

db()->connect();
if(!db()->isConnected()) {
    exit(
        lang('status_checking_database') . span_fail('#ffe6eb', lang('status_failed'))
    );
}

$db_name              = trim(postv('dbase'),'`');
$table_prefix         = trim(postv('table_prefix'));
if($table_prefix) {
    $table_prefix = rtrim($table_prefix, '_') . '_';
}
$db_collation         = trim(postv('database_collation'));
$db_connection_method = trim(postv('database_connection_method'));
$db_charset           = substr($db_collation,0,strpos($db_collation,'_'));

if (db()->select_db(db()->escape($db_name))) {
    if(isAlreadyInUse($db_name,$table_prefix)) {
        exit(
            lang('status_checking_database') . span_fail(
                '#ffe6eb'
                , lang('status_failed_table_prefix_already_in_use')
            )
        );
    }
    $msg = lang('status_passed');
} else {
    if(!createDB($db_name,$db_charset,$db_collation)) {
        exit(
            lang('status_checking_database') . span_fail(
                '#ffe6eb'
                , $query . lang('status_failed_could_not_create_database')
            )
        );
    }
    $msg = lang('status_passed_database_created');
}

$_SESSION['dbase']                      = $db_name;
$_SESSION['table_prefix']               = $table_prefix;
$_SESSION['database_collation']         = $db_collation;
$_SESSION['database_connection_method'] = $db_connection_method;
$_SESSION['database_charset']           = $db_charset;

echo lang('status_checking_database') . span_pass('#e6ffeb', $msg);



function createDB($db_name,$db_charset,$db_collation) {
    $query = sprintf(
        "CREATE DATABASE `%s` CHARACTER SET '%s' COLLATE %s"
        , db()->escape($db_name)
        , db()->escape($db_charset)
        , db()->escape($db_collation)
    );
    return @ db()->query($query);
}

function isAlreadyInUse($db_name,$table_prefix) {
    global $modx;
    $modx->db->dbname       = db()->escape($db_name);
    $modx->db->table_prefix = db()->escape($table_prefix);
    if(!db()->table_exists('[+prefix+]site_content')) {
        return false;
    }
    if(!db()->select('COUNT(id)', '[+prefix+]site_content')) {
        return false;
    }
    return true;
}

function span_pass($bgcolor,$str) {
	return sprintf('<span id="database_pass" style="background: %s;padding:8px;border-radius:5px;color:#388000;">%s</span>',$bgcolor,$str);
}

function span_fail($bgcolor,$str) {
	return sprintf('<span id="database_fail" style="background: %s;padding:8px;border-radius:5px;color:#FF0000;">%s</span>',$bgcolor,$str);
}

