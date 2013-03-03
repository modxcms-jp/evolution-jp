<?php
/**
 * MODX Installer
 */

// set error reporting
error_reporting(E_ALL & ~E_NOTICE);

$self = 'install/index.php';
$base_path = str_replace($self,'',str_replace('\\','/', __FILE__));
$installer_path = "{$base_path}install/";
require_once("{$installer_path}functions.php");
install_session_start();

// do a little bit of environment cleanup if possible
if (version_compare(phpversion(), "5.3") < 0) {
    @ ini_set('magic_quotes_runtime', 0);
    @ ini_set('magic_quotes_sybase', 0);
}
header("Content-Type: text/html; charset=utf-8");

$action= isset ($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';

require_once("{$base_path}manager/includes/default.config.php");
require_once("{$base_path}manager/includes/version.inc.php");

$default_language = getOption('install_language');
if(!$default_language) $default_language = autoDetectLang();
if(!isset($_SESSION['install_language']) || empty($_SESSION['install_language']))
	setOption('install_language',$default_language);

includeLang($default_language);

// start session
sessionCheck();

$installmode        = getOption('installmode');
$database_server    = getOption('database_server');
$database_user      = getOption('database_user');
$database_password  = getOption('database_password');
$database_connection_charset = 'utf8';
$database_collation          = 'utf8_general_ci';
$database_connection_method  = 'SET CHARACTER SET';
$dbase              = getOption('dbase');
$table_prefix       = getOption('table_prefix');
$adminname          = getOption('adminname');
$adminemail         = getOption('adminemail');
$adminpass          = getOption('adminpass');
$adminpassconfirm   = getOption('adminpassconfirm');
$install_language   = getOption('install_language');
$managerlanguage    = getOption('install_language');

$moduleName = "MODX";
$moduleVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

// type - 0:file or 1:content
$moduleChunks    = array(); // chunks    - array(name, description, type, file|content)
$moduleTemplates = array(); // templates - array(name, description, type, file|content)
$moduleSnippets  = array(); // snippets  - array(name, description, type, file|content, properties)
$modulePlugins   = array(); // plugins   - array(name, description, type, file|content, properties, events, guid)
$moduleModules   = array(); // modules   - array(name, description, type, file|content, properties, guid)
$moduleTemplates = array(); // templates - array(name, description, type, file|content, properties)
$moduleTVs       = array(); // TVs       - array(name, description, type, file|content, properties)

$errors= 0;

$tpl = file_get_contents('template.tpl');
$ph = ph();

ob_start();
if (!@include ("{$installer_path}actions/{$action}.php"))
{
    die ('Invalid install action attempted. [action=' . $action . ']');
}
$ph['content'] = ob_get_contents();
ob_end_clean();

echo parse($tpl,$ph);
