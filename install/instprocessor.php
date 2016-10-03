<?php
global $tplChunks;
global $tplTemplates;
global $tplSnippets;
global $tplPlugins;
global $tplModules;
global $tplTVs;

global $errors;

// set timout limit
@ set_time_limit(120); // used @ to prevent warning when using safe mode?

$self = 'install/instprocessor.php';
$base_path = str_replace($self, '',str_replace('\\','/', __FILE__));

require_once("{$base_path}manager/includes/default.config.php");

$installdata = $_SESSION['installdata'];
$formvTemplates   = $_SESSION['template'];
$formvTvs         = $_SESSION['tv'];
$formvChunks      = $_SESSION['chunk'];
$formvSnippets    = $_SESSION['snippet'];
$formvPlugins     = $_SESSION['plugin'];
$formvModules     = $_SESSION['module'];

$installmode = $_SESSION['installmode'];

extract($_lang, EXTR_PREFIX_ALL, 'lang');

echo "<p>{$lang_setup_database}</p>\n";
// get base path and url
define('MODX_API_MODE', true);
$database_type = function_exists('mysqli_connect') ? 'mysqli' : 'mysql';
$modx = include_once("{$base_path}manager/includes/document.parser.class.inc.php");
$modx->db->hostname          = $_SESSION['database_server'];
$modx->db->username          = $_SESSION['database_user'];
$modx->db->password          = $_SESSION['database_password'];
$modx->db->dbname            = $_SESSION['dbase'];
$modx->db->charset           = $_SESSION['database_charset'];
$modx->db->table_prefix      = $_SESSION['table_prefix'];
$modx->db->connect();

$rs = $modx->db->table_exists('[+prefix+]site_revision');
if($rs) {
	$rs = $modx->db->field_exists('elmid','[+prefix+]site_revision');
    if(!$rs) {
    	$sql = 'DROP TABLE ' . $modx->db->table_prefix . 'site_revision';
    	$modx->db->query($sql);
    }
}

// open db connection
$setupPath = realpath(getcwd());
$callBackFnc = include_once("{$setupPath}/setup.info.php");
include_once("{$setupPath}/sqlParser.class.php");
$sqlParser = new SqlParser();
$sqlParser->prefix     = $_SESSION['table_prefix'];
$sqlParser->adminname  = $_SESSION['adminname'];
$sqlParser->adminpass  = $_SESSION['adminpass'];
$sqlParser->adminemail = $_SESSION['adminemail'];
$sqlParser->connection_charset = $_SESSION['database_charset'];
$sqlParser->connection_collation = $_SESSION['database_collation'];
$sqlParser->connection_method = $_SESSION['database_connection_method'];
$sqlParser->managerlanguage = $_SESSION['managerlanguage'];
$sqlParser->manager_theme = $default_config['manager_theme'];
$sqlParser->mode = ($installmode < 1) ? 'new' : 'upd';
$sqlParser->base_path = $base_path;
$sqlParser->showSqlErrors = false;

// install/update database
echo "<p>{$lang_setup_database_creating_tables}";
$sqlParser->intoDB('both_createtables.sql');
if($installmode==0) $sqlParser->intoDB('new_setvalues.sql');

$sqlParser->intoDB('both_fixvalues.sql');
// display database results
if ($sqlParser->installFailed == true)
{
	$errors += 1;
	printf('<span class="notok"><b>%s</b></span></p>', $lang_database_alerts);
	printf('<p>%s</p>',                                $lang_setup_couldnt_install);
	printf('<p>%s<br /><br />',                        $lang_installation_error_occured);
	foreach ($sqlParser->mysqlErrors as $err) {
		printf('<em>%s</em>%s<span class="mono">%s</span>.<hr />', $err['error'], $lang_during_execution_of_sql, strip_tags($err['sql']));
	}
	echo '</p>';
	echo "<p>{$lang_some_tables_not_updated}</p>";
	return;
}
else printf('<span class="ok">%s</span></p>', $lang_ok);

$configString = file_get_contents("{$base_path}install/tpl/config.inc.tpl");
$ph['database_type']               = $database_type;
$ph['database_server']             = $_SESSION['database_server'];
$ph['database_user']               = $modx->db->escape($_SESSION['database_user']);
$ph['database_password']           = $modx->db->escape($_SESSION['database_password']);
$ph['database_connection_charset'] = $_SESSION['database_charset'];
$ph['database_connection_method']  = $_SESSION['database_connection_method'];
$ph['dbase']                       = trim($_SESSION['dbase'],'`');
$ph['table_prefix']                = $_SESSION['table_prefix'];
$ph['lastInstallTime']             = time();
$ph['https_port']                  = '443';

$configString = parse($configString, $ph);
$config_path = "{$base_path}manager/includes/config.inc.php";
$config_saved = @ file_put_contents($config_path, $configString);

// try to chmod the config file go-rwx (for suexeced php)
@chmod($config_path, 0404);

echo "<p>{$lang_writing_config_file}";
if ($config_saved === false)
{
	printf('<span class="notok">%s</span></p>', $lang_failed);
	$errors += 1;
	echo sprintf('<p>%s<br /><span class="mono">manager/includes/config.inc.php</span></p>', $lang_cant_write_config_file);
	echo '<textarea style="width:100%; height:200px;font-size:inherit;font-family:\'Courier New\',\'Courier\', monospace;">';
	echo htmlspecialchars($configString);
	echo '</textarea>';
	echo "<p>{$lang_cant_write_config_file_note}</p>";
}
else
	printf('<span class="ok">%s</span></p>', $lang_ok);

$_SESSION = array();

if ($installmode == 0) // generate new site_id
{
	$uniqid = uniqid('');
	$query = "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','{$uniqid}')";
	$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
	$modx->db->query($query);
}
else  // update site_id if missing
{
	$dbv_site_id = $modx->db->getObject('system_settings', "setting_name='site_id'");
	if ($dbv_site_id)
	{
		if ($dbv_site_id->setting_value == '' || $dbv_site_id->setting_value = 'MzGeQ2faT4Dw06+U49x3')
		{
			$uniqid = uniqid('');
			$query = "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','{$uniqid}')";
			$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
			$modx->db->query($query);
		}
	}
}

include_once('processors/prc_insTemplates.inc'); // Install Templates
include_once('processors/prc_insTVs.inc');       // Install Template Variables
include_once('processors/prc_insChunks.inc');    // Install Chunks
include_once('processors/prc_insModules.inc');   // Install Modules
include_once('processors/prc_insPlugins.inc');   // Install Plugins
include_once('processors/prc_insSnippets.inc');  // Install Snippets

if($installmode ==0 && is_file("{$base_path}install/sql/new_override.sql"))
{
	$sqlParser->intoDB('new_override.sql');
}

// install data
if ($installmode == 0 && $installdata==1)
{
	echo "<p>{$lang_installing_demo_site}";
	$sqlParser->intoDB('new_sample.sql');
	if ($sqlParser->installFailed == true)
	{
		$errors += 1;
		printf('<span class="notok"><b>%s</b></span></p>', $lang_database_alerts);
		echo "<p>{$lang_setup_couldnt_install}</p>";
		echo "<p>{$lang_installation_error_occured}<br /><br />";
		foreach($sqlParser->mysqlErrors as $info)
		{
			printf('<em>%s</em>%s<span class="mono">%s</span>.<hr />',$info['error'],$lang_during_execution_of_sql,strip_tags($info['sql']));
		}
		echo '</p>';
		echo "<p>{$lang_some_tables_not_updated}</p>";
		return;
	}
	else
	{
		printf('<span class="ok">%s</span></p>', $lang_ok);
	}
}

// call back function
if ($callBackFnc != '') $callBackFnc ($sqlParser);

// Setup the MODX API -- needed for the cache processor
// initiate a new document parser

$cache_path = "{$base_path}assets/cache/";

$files = glob("{$cache_path}*.idx.php");
foreach($files as $file)
{
	@unlink($file);
}

// try to chmod the cache go-rwx (for suexeced php)
@chmod("{$cache_path}siteCache.idx.php", 0600);
@chmod("{$cache_path}basicConfig.php", 0600);

$modx->clearCache(); // always empty cache after install

// remove any locks on the manager functions so initial manager login is not blocked
$modx->db->truncate('[+prefix+]active_users');

// andrazk 20070416 - release manager access
if (is_file("{$cache_path}installProc.inc.php"))
{
	@chmod("{$cache_path}installProc.inc.php", 0755);
	unlink("{$cache_path}installProc.inc.php");
}
// setup completed!
echo "<p><b>{$lang_installation_successful}</b></p>";
echo "<p>{$lang_to_log_into_content_manager}</p>";
echo '<p><img src="img/ico_info.png" align="left" style="margin-right:10px;" />';

if($installmode == 0) echo $lang_installation_note;
else                  echo $lang_upgrade_note;

echo '</p>';

function ok($name,$msg) {
	return sprintf('<p>&nbsp;&nbsp;%s: <span class="ok">%s</span></p>', $name, $msg) . "\n";
}

function ng($name,$msg) {
	return sprintf('<p>&nbsp;&nbsp;%s: <span class="notok">%s</span></p>', $name, $msg) . "\n";
}

function showError() {
	global $modx;
	printf('<p>%s</p>', $modx->db->getLastError());
}