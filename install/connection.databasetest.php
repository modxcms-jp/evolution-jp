<?php
$self = 'install/connection.databasetest.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
include_once($base_path . "manager/includes/document.parser.class.inc.php");
$modx = new DocumentParser;

require_once($base_path . "manager/includes/default.config.php");
require_once($base_path . "install/functions.php");

includeLang(getOption('install_language'));

$output = $_lang['status_checking_database'];

$modx->db->hostname = $_SESSION['database_server'];
$modx->db->username = $_SESSION['database_user'];
$modx->db->password = $_SESSION['database_password'];
$modx->db->connect();

if(!$modx->db->isConnected()) {
	$bgcolor = '#ffe6eb';
	$output .= span_fail($bgcolor,$_lang['status_failed']);
} else {
	$bgcolor = '#e6ffeb';
	$db_name              = trim($_POST['dbase'],'`');
	$table_prefix         = trim($_POST['table_prefix']);
	if($table_prefix !== '') $table_prefix = rtrim($table_prefix,'_').'_';
	$db_collation         = trim($_POST['database_collation']);
	$db_connection_method = trim($_POST['database_connection_method']);
	$db_charset           = substr($db_collation,0,strpos($db_collation,'_'));
	
	if(get_magic_quotes_gpc()) {
		$db_name              = stripslashes($db_name);
		$table_prefix         = stripslashes($table_prefix);
		$db_collation         = stripslashes($db_collation);
		$db_connection_method = stripslashes($db_connection_method);
		$db_charset           = stripslashes($db_charset);
	}
	$db_name              = $modx->db->escape($db_name);
	$table_prefix         = $modx->db->escape($table_prefix);
	$db_collation         = $modx->db->escape($db_collation);
	$db_connection_method = $modx->db->escape($db_connection_method);
	$db_charset           = $modx->db->escape($db_charset);

	$pass = false;
	
	if ($modx->db->select_db($db_name)) {
		
		$modx->db->dbname       = $db_name;
		$modx->db->table_prefix = $table_prefix;
		
		if($modx->db->table_exists('[+prefix+]site_content') && $modx->db->select('COUNT(id)','[+prefix+]site_content')) {
			$output .= span_fail($bgcolor,$_lang['status_failed_table_prefix_already_in_use']);
		}
    	else {
    		$output .= span_pass($bgcolor,$_lang['status_passed']);
    		$pass = true;
    	}
	}
	else {
		$query = "CREATE DATABASE `{$db_name}` CHARACTER SET '{$db_charset}' COLLATE {$db_collation}";
		if(!@ $modx->db->query($query)) $output .= span_fail($bgcolor,$query.$_lang['status_failed_could_not_create_database']);
		else {
			$output .= span_pass($bgcolor,$_lang['status_passed_database_created']);
			$pass = true;
		}
	}
	if($pass === true) {
		$_SESSION['dbase']                      = $db_name;
		$_SESSION['table_prefix']               = $table_prefix;
		$_SESSION['database_collation']         = $db_collation;
		$_SESSION['database_connection_method'] = $db_connection_method;
		$_SESSION['database_charset']           = $db_charset;
	}
}

echo $output;

function span_pass($bgcolor,$str) {
	return sprintf('<span id="database_pass" style="background: %s;padding:8px;border-radius:5px;color:#388000;">%s</span>',$bgcolor,$str);
}

function span_fail($bgcolor,$str) {
	return sprintf('<span id="database_fail" style="background: %s;padding:8px;border-radius:5px;color:#FF0000;">%s</span>',$bgcolor,$str);
}

