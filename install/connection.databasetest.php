<?php
$host = $_POST['host'];
$uid  = $_POST['uid'];
$pwd  = $_POST['pwd'];
$table_prefix = trim($_POST['table_prefix']);

$self = 'install/connection.databasetest.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
include_once("{$base_path}manager/includes/document.parser.class.inc.php");
$modx = new DocumentParser;

require_once("{$base_path}manager/includes/default.config.php");
require_once("{$base_path}install/functions.php");
$language = getOption('install_language');
includeLang($language);

$output = $_lang['status_checking_database'];

$modx->db->hostname = $_SESSION['database_server'];
$modx->db->username = $_SESSION['database_user'];
$modx->db->password = $_SESSION['database_password'];
$modx->db->connect();
if(!$modx->db->isConnected()) $output .= span_fail($_lang['status_failed']);
else
{
	$dbase                      = trim($_POST['dbase'],'`');
	$table_prefix = trim($table_prefix,'_').'_';
	$database_collation         = $_POST['database_collation'];
	$database_connection_method = $_POST['database_connection_method'];
	$database_charset = substr($database_collation,0,strpos($database_collation,'_'));
	
	if(get_magic_quotes_gpc())
	{
		$dbase                      = stripslashes($dbase);
		$table_prefix               = stripslashes($table_prefix);
		$database_collation         = stripslashes($database_collation);
		$database_connection_method = stripslashes($database_connection_method);
		$database_charset           = stripslashes($database_charset);
	}
	$dbase                      = $modx->db->escape($dbase);
	$table_prefix               = $modx->db->escape($table_prefix);
	$database_collation         = $modx->db->escape($database_collation);
	$database_connection_method = $modx->db->escape($database_connection_method);
	$database_charset           = $modx->db->escape($database_charset);

	$pass = false;
	$rs = $modx->db->select_db($dbase);
	if (!$rs)
	{
		$query = "CREATE DATABASE `{$dbase}` CHARACTER SET '{$database_charset}' COLLATE {$database_collation}";
		if(!@ $modx->db->query($query)) $output .= span_fail($query.$_lang['status_failed_could_not_create_database']);
		else
		{
			$output .= span_pass($_lang['status_passed_database_created']);
			$pass = true;
		}
	}
	else {
		$modx->db->dbname       = $dbase;
		$modx->db->table_prefix = $table_prefix;
		$rs = $modx->db->table_exists('[+prefix+]site_content');
		if($rs) $rs = $modx->db->select('COUNT(id)','[+prefix+]site_content');
    	if($rs) {
    		$output .= span_fail($_lang['status_failed_table_prefix_already_in_use']);
    	}
    	else {
    		$output .= span_pass($_lang['status_passed']);
    		$pass = true;
    	}
	}
	if($pass === true)
	{
		$_SESSION['dbase']                      = $dbase;
		$_SESSION['table_prefix']               = $table_prefix;
		$_SESSION['database_collation']         = $database_collation;
		$_SESSION['database_connection_method'] = $database_connection_method;
		$_SESSION['database_charset']           = $database_charset;
	}
}

echo $output;

function span_pass($str) {
	return '<span id="database_pass" style="color:#388000;">' . $str . '</span>';
}

function span_fail($str) {
	return '<span id="database_fail" style="color:#FF0000;">' . $str . '</span>';
}

