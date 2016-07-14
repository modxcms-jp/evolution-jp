<?php
$host = $_POST['host'];
$uid  = $_POST['uid'];
$pwd  = $_POST['pwd'];

$self = 'install/connection.databasetest.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
require_once("{$base_path}manager/includes/default.config.php");
require_once("{$base_path}install/functions.php");
install_session_start();
$language = getOption('install_language');
includeLang($language);

$output = $_lang['status_checking_database'];

global $db;
$db = @sql_connect($host, $uid, $pwd);
if(!$db) $output .= span_fail($_lang['status_failed']);
else
{
	$dbase                      = trim($_POST['dbase'],'`');
	$table_prefix               = $_POST['table_prefix'];
	$database_collation         = getOption('database_collation');
	$database_connection_method = $_POST['database_connection_method'];
	
	if(get_magic_quotes_gpc())
	{
		$dbase                      = stripslashes($dbase);
		$table_prefix               = stripslashes($table_prefix);
		$database_collation         = stripslashes($database_collation);
		$database_connection_method = stripslashes($database_connection_method);
	}
	$dbase                      = sql_real_escape_string($dbase);
	$table_prefix               = sql_real_escape_string($table_prefix);
	$database_collation         = sql_real_escape_string($database_collation);
	$database_connection_method = sql_real_escape_string($database_connection_method);
	$tbl_site_content = "`{$dbase}`.`{$table_prefix}site_content`";
	
	$pass = false;
	if (!@ sql_select_db($dbase,$db))
	{
		sql_set_charset('utf8');
		$query = "CREATE DATABASE `{$dbase}` CHARACTER SET 'utf8' COLLATE {$database_collation}";
		
		if(!@ sql_query($query)) $output .= span_fail($query.$_lang['status_failed_could_not_create_database']);
		else
		{
			$output .= span_pass($_lang['status_passed_database_created']);
			$pass = true;
		}
	}
	elseif(@ sql_query("SELECT COUNT(id) FROM {$tbl_site_content}"))
		$output .= span_fail($_lang['status_failed_table_prefix_already_in_use']);
	else
	{
		$output .= span_pass($_lang['status_passed']);
		$pass = true;
	}
	if($pass === true)
	{
		$_SESSION['dbase']                      = $dbase;
		$_SESSION['table_prefix']               = $table_prefix;
		$_SESSION['database_collation']         = $database_collation;
		$_SESSION['database_connection_method'] = $database_connection_method;
	}
}

echo $output;

function span_pass($str) {
	return '<span id="database_pass" style="color:#388000;">' . $str . '</span>';
}

function span_fail($str) {
	return '<span id="database_fail" style="color:#FF0000;">' . $str . '</span>';
}

