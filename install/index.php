<?php
/**
 * MODX Installer
 */

// set error reporting
error_reporting(E_ALL & ~E_NOTICE);

if (!defined('E_DEPRECATED'))      define('E_DEPRECATED',       8192);
if (!defined('E_USER_DEPRECATED')) define('E_USER_DEPRECATED', 16384);

// do a little bit of environment cleanup if possible
if (version_compare(phpversion(), "5.3") < 0) {
    @ ini_set('magic_quotes_runtime', 0);
    @ ini_set('magic_quotes_sybase', 0);
}

header("Content-Type: text/html; charset=utf-8");

$base_path      = str_replace('\\','/', dirname(getcwd())).'/';
$installer_path = "{$base_path}install/";

require_once("{$base_path}manager/includes/version.inc.php");
$cmsName = "MODX";
$cmsVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

require_once("{$base_path}manager/includes/default.config.php");
require_once("{$installer_path}functions.php");

install_session_start();

$action = isset($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';
$_SESSION['prevAction']    = isset($_SESSION['currentAction']) ? $_SESSION['currentAction'] : '';
$_SESSION['currentAction'] = $action;

if($action==='mode') {
	$installmode = isUpGrade();
	$_SESSION['installmode'] = $installmode;
	if($installmode==1) {
		include("{$base_path}manager/includes/config.inc.php");
		$_SESSION['database_server']            = $database_server;
		$_SESSION['database_user']              = $database_user;
		$_SESSION['database_password']          = $database_password;
		$_SESSION['dbase']                      = trim($dbase,'`');
		$_SESSION['table_prefix']               = $table_prefix;
		$_SESSION['database_collation']         = 'utf8_general_ci';
		$_SESSION['database_connection_method'] = 'SET CHARACTER SET';
	}
}

if(isset($_POST['install_language']) && !empty($_POST['install_language'])) {
	$install_language = $_POST['install_language'];
	$_SESSION['install_language'] = $_POST['install_language'];
}
elseif(isset($_SESSION['install_language']) && !empty($_SESSION['install_language']))
	$install_language = $_SESSION['install_language'];
else {
	$install_language = autoDetectLang();
	$_SESSION['install_language'] = $install_language;
}

//echo $install_language;exit;
includeLang($install_language);

// start session
$_SESSION['test'] = 1;
install_sessionCheck();

$errors= 0;

$ph = ph();
$ph = array_merge($ph,$_lang);
$ph['install_language'] = $install_language;

ob_start();
if (!@include_once ("{$installer_path}actions/{$action}.php"))
{
    die ('Invalid install action attempted. [action=' . $action . ']');
}
$ph['content'] = ob_get_contents();
ob_end_clean();

$tpl = file_get_contents("{$base_path}install/tpl/template.tpl");
echo parse($tpl,$ph);
