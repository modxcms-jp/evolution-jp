<?php
/**
 * MODX Installer
 */

if (!defined('E_DEPRECATED')) define('E_DEPRECATED', 8192);

// set error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$self = 'install/index.php';
$base_path = str_replace($self,'',str_replace('\\','/', __FILE__));
require_once("{$base_path}manager/includes/version.inc.php");
$moduleName = "MODX";
$moduleVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

require_once("{$base_path}manager/includes/default.config.php");
$installer_path = "{$base_path}install/";
require_once("{$installer_path}functions.php");
install_session_start();
//session_destroy();

// do a little bit of environment cleanup if possible
if (version_compare(phpversion(), "5.3") < 0) {
    @ ini_set('magic_quotes_runtime', 0);
    @ ini_set('magic_quotes_sybase', 0);
}
header("Content-Type: text/html; charset=utf-8");

$action= isset ($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';
if($action==='mode') $installmode = get_installmode();

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
if (!@include ("{$installer_path}actions/{$action}.php"))
{
    die ('Invalid install action attempted. [action=' . $action . ']');
}
$ph['content'] = ob_get_contents();
ob_end_clean();

$tpl = file_get_contents("{$base_path}install/tpl/template.tpl");
echo parse($tpl,$ph);
