<?php
/**
 * MODX Installer
 */

// set error reporting
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors',1);

header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
$base_path      = str_replace('\\','/', dirname(getcwd())).'/';

include_once("{$base_path}manager/includes/document.parser.class.inc.php");
$modx = new DocumentParser;

$installer_path = "{$base_path}install/";

require_once("{$base_path}manager/includes/version.inc.php");
$cmsName = "MODX";
$cmsVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

require_once("{$base_path}manager/includes/default.config.php");
require_once("{$installer_path}functions.php");

$lang_name = autoDetectLang();
$rs = install_sessionCheck();
if(!$rs) {
    includeLang($lang_name);
    $ph = $_lang;
    $tpl = file_get_contents("{$base_path}install/tpl/session_problem.tpl");
    echo $modx->parseText($tpl,$ph);
    exit;
}

$action = isset($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';
$_SESSION['prevAction']    = isset($_SESSION['currentAction']) ? $_SESSION['currentAction'] : '';
$_SESSION['currentAction'] = $action;

if($action==='mode') $_SESSION['installmode'] = isUpGrade();

if(isset($_SESSION['database_server']))   $modx->db->hostname     = $_SESSION['database_server'];
if(isset($_SESSION['database_user']))     $modx->db->username     = $_SESSION['database_user'];
if(isset($_SESSION['database_password'])) $modx->db->password     = $_SESSION['database_password'];
if(isset($_SESSION['dbase']))             $modx->db->dbname       = $_SESSION['dbase'];
if(isset($_SESSION['database_charset']))  $modx->db->charset      = $_SESSION['database_charset'];
if(isset($_SESSION['table_prefix']))      $modx->db->table_prefix = $_SESSION['table_prefix'];
if(isset($_SESSION['database_server']))   $modx->db->connect();

if(isset($_POST['install_language']) && !empty($_POST['install_language'])) {
	$lang_name = $_POST['install_language'];
	$_SESSION['install_language'] = $_POST['install_language'];
}
elseif(isset($_SESSION['install_language']) && !empty($_SESSION['install_language']))
	$lang_name = $_SESSION['install_language'];
else {
	$_SESSION['install_language'] = $lang_name;
}

//echo $lang_name;exit;
includeLang($lang_name);

$errors= 0;

$ph = ph();
$ph = array_merge($ph,$_lang);
$ph['install_language'] = $lang_name;

ob_start();
if (!@include("{$installer_path}actions/{$action}.php"))
{
    die ('Invalid install action attempted. [action=' . $action . ']');
}
$ph['content'] = ob_get_contents();
ob_end_clean();

$tpl = file_get_contents("{$base_path}install/tpl/template.tpl");
echo $modx->parseText($tpl,$ph);
