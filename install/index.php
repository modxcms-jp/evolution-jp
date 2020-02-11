<?php
/**
 * MODX Installer
 */

// set error reporting
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors',1);

header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
define('MODX_BASE_PATH', str_replace('\\','/', dirname(__DIR__)).'/');
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');

include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/version.inc.php');
$cmsName = "MODX";
$cmsVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_SETUP_PATH . 'functions.php');

$rs = install_sessionCheck();
if(!$rs) {
    $ph = includeLang(lang_name());
    $tpl = file_get_contents(MODX_BASE_PATH . 'install/tpl/session_problem.tpl');
    echo $modx->parseText($tpl,$ph);
    exit;
}

$action = isset($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';
$_SESSION['prevAction']    = isset($_SESSION['currentAction']) ? $_SESSION['currentAction'] : '';
$_SESSION['currentAction'] = $action;

if($action==='mode') {
    $_SESSION['is_upgradeable'] = isUpGradeable();
}

if(isset($_SESSION['database_server']))   $modx->db->hostname     = $_SESSION['database_server'];
if(isset($_SESSION['database_user']))     $modx->db->username     = $_SESSION['database_user'];
if(isset($_SESSION['database_password'])) $modx->db->password     = $_SESSION['database_password'];
if(isset($_SESSION['dbase']))             $modx->db->dbname       = $_SESSION['dbase'];
if(isset($_SESSION['database_charset']))  $modx->db->charset      = $_SESSION['database_charset'];
if(isset($_SESSION['table_prefix']))      $modx->db->table_prefix = $_SESSION['table_prefix'];
if(isset($_SESSION['database_server']))   $modx->db->connect();

$_lang = includeLang(lang_name());

$errors= 0;

$ph = ph();
$ph = array_merge($ph,$_lang);
$ph['install_language'] = lang_name();

ob_start();
if (!@include(MODX_SETUP_PATH . "actions/" . $action . ".php")) {
    die ('Invalid install action attempted. [action=' . $action . ']');
}
$ph['content'] = ob_get_contents();
ob_end_clean();

$tpl = file_get_contents(MODX_BASE_PATH . 'install/tpl/template.tpl');
echo $modx->parseText($tpl,$ph);
