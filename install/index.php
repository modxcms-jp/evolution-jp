<?php
/**
 * MODx Installer
 */

// set error reporting
error_reporting(E_ALL & ~E_NOTICE);

$self = 'install/index.php';
$base_path = str_replace($self,'',str_replace('\\','/', __FILE__));
$installer_path = "{$base_path}install/";
require_once("{$installer_path}functions.php");

// do a little bit of environment cleanup if possible
if (version_compare(phpversion(), "5.3") < 0) {
    @ ini_set('magic_quotes_runtime', 0);
    @ ini_set('magic_quotes_sybase', 0);
}
header("Content-Type: text/html; charset=utf-8");

$action= isset ($_REQUEST['action']) ? trim(strip_tags($_REQUEST['action'])) : 'mode';

require_once("{$base_path}manager/includes/default.config.php");
require_once("{$base_path}manager/includes/version.inc.php");
if(isset($_REQUEST['install_language']) && !empty($_REQUEST['install_language']))
{
	$default_language = $_REQUEST['install_language'];
}
elseif(isset($_SESSION['install_language']) && !empty($_SESSION['install_language']))
{
	$default_language = $_SESSION['install_language'];
}
else $default_language = 'japanese-utf8';

$default_language = setOption('install_language',$default_language);

includeLang($default_language);

// start session
session_start();
$_SESSION['test'] = 1;
// session loop-back tester
if (!$_SESSION['test'])
{
    $installBaseUrl = (!isset ($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on') ? 'http://' : 'https://';
    $installBaseUrl .= $_SERVER['HTTP_HOST'];
    if ($_SERVER['SERVER_PORT'] != 80)
        $installBaseUrl = str_replace(':' . $_SERVER['SERVER_PORT'], '', $installBaseUrl); // remove port from HTTP_HOST
    $installBaseUrl .= ($_SERVER['SERVER_PORT'] == 80 || isset ($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'on') ? '' : ':' . $_SERVER['SERVER_PORT'];
	$retryURL = $installBaseUrl . $_SERVER['SCRIPT_NAME'] . "?action=language";
	echo '
<html>
<head>
	<title>Install Problem</title>
	<style type="text/css">
		*{margin:0;padding:0}
		body{margin:50px;background:#eee;}
		.install{padding:10px;border:5px solid #f22;background:#f99;margin:0 auto;font:120%/1em serif;text-align:center;}
		p{ margin:20px 0; }
		a{font-size:200%;color:#f22;text-decoration:underline;margin-top:30px;padding:5px;}
	</style>
</head>
<body>
	<div class="install">
		<p>' . $_lang["session_problem"] . '</p>
		<p><a href="' . $retryURL . '">' .$_lang["session_problem_try_again"] . '</a></p>
	</div>
</body>
</html>';
	exit;

}

$installmode        = getOption('installmode');
$database_server    = getOption('database_server');
$database_user      = getOption('database_user');
$database_password  = getOption('database_password');
$database_connection_charset = 'utf8';
$database_collation          = 'utf8_general_ci';
$database_connection_method  = 'SET CHARACTER SET';
$dbase              = getOption('dbase');
$table_prefix       = getOption('table_prefix');
$adminname          = getOption('cmsadmin');
$adminemail         = getOption('cmsadminemail');
$adminpass          = getOption('cmspassword');
$managerlanguage    = getOption('install_language');

$moduleName = "MODX";
$moduleVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

$moduleChunks    = array (); // chunks    - array : name, description, type - 0:file or 1:content, file or content
$moduleTemplates = array (); // templates - array : name, description, type - 0:file or 1:content, file or content
$moduleSnippets  = array (); // snippets  - array : name, description, type - 0:file or 1:content, file or content,properties
$modulePlugins   = array (); // plugins   - array : name, description, type - 0:file or 1:content, file or content,properties, events,guid
$moduleModules   = array (); // modules   - array : name, description, type - 0:file or 1:content, file or content,properties, guid
$moduleTemplates = array (); // templates - array : name, description, type - 0:file or 1:content, file or content,properties
$moduleTVs       = array (); // TVs       - array : name, description, type - 0:file or 1:content, file or content,properties

$errors= 0;

// get post back status
$isPostBack = (count($_POST));

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
