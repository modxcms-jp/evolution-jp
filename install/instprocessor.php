<?php
global $cmsName;
global $cmsVersion;
global $tplChunks;
global $tplTemplates;
global $tplSnippets;
global $tplPlugins;
global $tplModules;
global $tplTVs;
global $mysqli;

global $errors;

// set timout limit
@ set_time_limit(120); // used @ to prevent warning when using safe mode?

$self = 'install/instprocessor.php';
$base_path = str_replace($self, '',str_replace('\\','/', __FILE__));

require_once("{$base_path}manager/includes/default.config.php");

$installdata = $_SESSION['installdata'];unset($_SESSION['installdata']);
$formvTemplates   = $_SESSION['template'];   unset($_SESSION['template']);
$formvTvs         = $_SESSION['tv'];         unset($_SESSION['tv']);
$formvChunks      = $_SESSION['chunk'];      unset($_SESSION['chunk']);
$formvSnippets    = $_SESSION['snippet'];    unset($_SESSION['snippet']);
$formvPlugins     = $_SESSION['plugin'];     unset($_SESSION['plugin']);
$formvModules     = $_SESSION['module'];     unset($_SESSION['module']);

$installmode = $_SESSION['installmode'];

extract($_lang, EXTR_PREFIX_ALL, 'lang');

echo "<p>{$lang_setup_database}</p>\n";
// get base path and url
define('MODX_API_MODE', true);
require_once("{$base_path}manager/includes/initialize.inc.php");
startCMSSession();
$database_type = 'mysqli';
include_once("{$base_path}manager/includes/document.parser.class.inc.php");
$modx = new DocumentParser;
$modx->db->hostname = $_SESSION['database_server'];
$modx->db->dbname   = $_SESSION['dbase'];
$modx->db->username = $_SESSION['database_user'];
$modx->db->password = $_SESSION['database_password'];
$modx->db->table_prefix = $_SESSION['table_prefix'];
$modx->db->connect();

// open db connection
$setupPath = realpath(dirname(__FILE__));
include_once("{$setupPath}/setup.info.php");
include_once("{$setupPath}/sqlParser.class.php");
$sqlParser = new SqlParser();
$sqlParser->prefix     = $_SESSION['table_prefix'];
$sqlParser->adminname  = $_SESSION['adminname'];
$sqlParser->adminpass  = $_SESSION['adminpass'];
$sqlParser->adminemail = $_SESSION['adminemail'];
$sqlParser->connection_charset = 'utf8';
$sqlParser->connection_collation = $_SESSION['database_collation'];
$sqlParser->connection_method = $_SESSION['database_connection_method'];
$sqlParser->managerlanguage = $_SESSION['managerlanguage'];
$sqlParser->manager_theme = $default_config['manager_theme'];
$sqlParser->mode = ($installmode < 1) ? 'new' : 'upd';
$sqlParser->base_path = $base_path;
$sqlParser->ignoreDuplicateErrors = true;

$rs = $modx->db->table_exists('[+prefix+]site_revision');
if($rs)
{
	$rs = $modx->db->field_exists('elmid','[+prefix+]site_revision');
    if(!$rs) {
    	$sql = 'DROP TABLE ' . $sqlParser->prefix . 'site_revision';
    	$modx->db->query($sql);
    }
}

// install/update database
echo "<p>{$lang_setup_database_creating_tables}";
$sqlParser->process('both_createtables.sql');
if($installmode==0) $sqlParser->process('new_setvalues.sql');
else                $sqlParser->process('upd_fixvalues.sql');
$sqlParser->process('both_fixvalues.sql');
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
$ph['database_type']               = 'mysqli';
$ph['database_server']             = $_SESSION['database_server'];
$ph['database_user']               = $modx->db->escape($_SESSION['database_user']);
$ph['database_password']           = $modx->db->escape($_SESSION['database_password']);
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
	printf('<p>%s<br /><span class="mono">manager/includes/config.inc.php</span></p>', $lang_cant_write_config_file);
	echo '<textarea style="width:100%; height:200px;font-size:inherit;font-family:\'Courier New\',\'Courier\', monospace;">';
	echo htmlspecialchars($configString);
	echo '</textarea>';
	echo "<p>{$lang_cant_write_config_file_note}</p>";
}
else
	printf('<span class="ok">%s</span></p>', $lang_ok);

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

// Install Templates
if ($installmode==0 && ($formvTemplates!==false && !empty($formvTemplates) || $installdata==1))
{
	echo "<h3>{$lang_templates}:</h3>";
	
	foreach ($tplTemplates as $i=>$tplInfo)
	{
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else $installSample = false;
		
		if(!in_array($i, $formvTemplates) && !$installSample) continue;
		
		$templatename  = $tplInfo['templatename'];
		$tpl_file_path = $tplInfo['tpl_file_path'];
		
		if (!is_file($tpl_file_path)) {
			echo ng($templatename, "{$lang_unable_install_template} '{$tpl_file_path}' {$lang_not_found}");
			continue;
		}
		
		$f = array();
		$content = file_get_contents($tpl_file_path);
		$f['content']     = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1);
		$f['description'] = $tplInfo['description'];
		$f['category']    = getCreateDbCategory($tplInfo['category']); // Create the category if it does not already exist
		$f['locked']      = $tplInfo['locked'];
		$f = $modx->db->escape($f);
		
		// See if the template already exists
		$templatename = $modx->db->escape($templatename);
		$dbv_template = $modx->db->getObject('site_templates', "templatename='{$templatename}'");
		if ($dbv_template)
		{
			if (!@ $modx->db->update($f, '[+prefix+]site_templates', "templatename='{$templatename}'"))
			{
				$errors += 1;
				showError();
				return;
			}
			else echo ok($templatename,$lang_upgraded);
		}
		else
		{
			$f['templatename'] = $templatename;
			if (!@ $modx->db->insert($f, '[+prefix+]site_templates'))
			{
				$errors += 1;
				showError();
				return;
			}
			else echo ok($templatename,$lang_installed);
		}
	}
}

// Install Template Variables
if ($installmode==0 && ($formvTvs!==false && !empty($formvTvs) || $installdata==1))
{
	echo "<h3>{$lang_tvs}:</h3> ";
	foreach ($tplTVs as $i=>$tplInfo):
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else $installSample = false;
		
		if(!in_array($i, $formvTvs) && !$installSample) continue;
		
		$name = $modx->db->escape($tplInfo['name']);
		$f = array();
		$f['type']           = $tplInfo['input_type'];
		$f['caption']        = $tplInfo['caption'];
		$f['description']    = $tplInfo['description'];
		$f['category']       = getCreateDbCategory($tplInfo['category']);
		$f['locked']         = $tplInfo['locked'];
		$f['elements']       = $tplInfo['elements'];
		$f['default_text']   = $tplInfo['default_text'];
		$f['display']        = $tplInfo['display'];
		$f['display_params'] = $tplInfo['display_params'];
		$f = $modx->db->escape($f);
		
		$dbv_tmplvar = $modx->db->getObject('site_tmplvars', "name='{$name}'");
		if ($dbv_tmplvar)
		{
			$tmplvarid = $dbv_tmplvar->id;
			$rs = $modx->db->update($f, '[+prefix+]site_tmplvars', "id='{$tmplvarid}'");
			if (!$rs)
			{
				$errors += 1;
				showError();
				return;
			}
			else
			{
				$modx->db->delete('[+prefix+]site_tmplvar_templates', "tmplvarid='{$dbv_tmplvar->id}'");
				echo ok($name,$lang_upgraded);
			}
		}
		else
		{
			$f['name'] = $name;
			$tmplvarid = $modx->db->insert($f, '[+prefix+]site_tmplvars');
			if (!$tmplvarid)
			{
				$errors += 1;
				showError();
				return;
			}
			else echo ok($name,$lang_installed);
		}
		
		// add template assignments
		$templatenames = explode(',', $tplInfo['template_assignments']);
		if(empty($templatenames)) continue;
		
		// add tv -> template assignments
		foreach ($templatenames as $templatename)
		{
			$templatename = $modx->db->escape($templatename);
			$dbv_template = $modx->db->getObject('site_templates', "templatename='{$templatename}'");
			if ($dbv_template)
			{
				$f = array('tmplvarid'=>$tmplvarid, 'templateid'=>$dbv_template->id);
				$modx->db->insert($f, '[+prefix+]site_tmplvar_templates');
			}
		}
	endforeach;
}

// Install Chunks
if ($formvChunks!==false && !empty($formvChunks) || $installdata)
{
	echo "<h3>{$lang_chunks}:</h3>";
	foreach ($tplChunks as $i=>$tplInfo)
	{
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else $installSample = false;
		
		if(!in_array($i, $formvChunks) && !$installSample) continue;
		
		$overwrite = $tplInfo['overwrite'];
		
		$name = $modx->db->escape($tplInfo['name']);
		$dbv_chunk = $modx->db->getObject('site_htmlsnippets', "name='{$name}'");
		if($dbv_chunk) $update = true;
		else           $update = false;
		
		$tpl_file_path = $tplInfo['tpl_file_path'];
		
		if (!is_file($tpl_file_path))
		{
			echo ng($name,"{$lang_unable_install_chunk} '{$tpl_file_path}' {$lang_not_found}");
			continue;
		}
		$snippet = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', file_get_contents($tpl_file_path), 1);
		
		$f = array();
		$f['description'] = $tplInfo['description'];
		$f['snippet']     = $snippet;
		$f['category']    = getCreateDbCategory($tplInfo['category']);
		$f = $modx->db->escape($f);
		
		if ($update)
		{
			if($overwrite == 'false')
			{
				$rs =true;
				$i = 0;
				while($rs === true)
				{
					$newname = $tplInfo['name'] . '-' . str_replace('.', '_', $modx_version);
					if(0<$i) $newname . "({$i})";
					$newname = $modx->db->escape($newname);
					$rs = $modx->db->getObject('site_htmlsnippets', "name='{$newname}'");
					$name = $newname;
					$i++;
				}
			}
			if (!@ $modx->db->update($f, '[+prefix+]site_htmlsnippets', "name='{$name}'"))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_upgraded);
		}
		else
		{
			$f['name'] = $name;
			if (!@ $modx->db->insert($f, '[+prefix+]site_htmlsnippets'))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_installed);
		}
	}
}

// Install Modules
if ($formvModules!==false && !empty($formvModules) || $installdata)
{
	echo "<h3>{$lang_modules}:</h3>";
	foreach ($tplModules as $i=>$tplInfo)
	{
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else $installSample = false;
		
		if(!in_array($i, $formvModules) && !$installSample) continue;
		
		$name = $tplInfo['name'];
		$tpl_file_path = $tplInfo['tpl_file_path'];
		if (!is_file($tpl_file_path))
		{
			echo ng($name,"{$lang_unable_install_module} '{$tpl_file_path}' {$lang_not_found}");
			continue;
		}
		
		$f = array();
		$f['description'] = $tplInfo['description'];
		$modulecode = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path), 2));
		$f['modulecode']  = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $modulecode, 1);
		$f['properties']  = $tplInfo['properties'];
		$f['enable_sharedparams'] = $tplInfo['shareparams'];
		$f = $modx->db->escape($f);
		
		$name = $modx->db->escape($name);
		$dbv_module = $modx->db->getObject('site_modules', "name='{$name}'");
		if ($dbv_module)
		{
			$props = propUpdate($properties,$dbv_module->properties);
			if (!@ $modx->db->update($f, '[+prefix+]site_modules', "name='{$name}'"))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_upgraded);
		}
		else
		{
			$f['name']     = $name;
			$f['guid']     = $modx->db->escape($tplInfo['guid']);
			$f['category'] = getCreateDbCategory($tplInfo['category']);
			if (!@ $modx->db->insert($f, '[+prefix+]site_modules'))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_installed);
		}
	}
}

// Install Plugins
if ($formvPlugins!==false && !empty($formvPlugins) || $installdata)
{
	echo "<h3>{$lang_plugins}:</h3>";
	
	foreach ($tplPlugins as $i=>$tplInfo):
		
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else $installSample = false;
		
		if(!in_array($i, $formvPlugins) && !$installSample) continue;
		
		$name        = $tplInfo['name'];
		$tpl_file_path = $tplInfo['tpl_file_path'];
		if(!is_file($tpl_file_path))
		{
			echo ng($name, $lang_unable_install_plugin . " '{$tpl_file_path}' " . $lang_not_found);
			continue;
		}
		
		// parse comma-separated legacy names and prepare them for sql IN clause
		if(array_key_exists('legacy_names', $tplInfo))
		{
			$_ = array();
			$array_legacy_names = explode(',', $tplInfo['legacy_names']);
			while($v = array_shift($array_legacy_names))
			{
				$_[] = trim($v);
			}
			$legacy_names = join(',', $_);
			// disable legacy versions based on legacy_names provided
			if(!empty($legacy_names))
			{
				$legacy_names = $modx->db->escape($legacy_names);
				$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "name IN ('{$legacy_names}')");
			}
		}
		
		$f = array();
		$f['name']        = $name;
		$f['description'] = $tplInfo['description'];
		$plugincode = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path), 2));
		$f['plugincode']  = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
		$f['properties']  = propUpdate($tplInfo['properties'],$dbv_plugin->properties);
		$f['disabled']    = '0';
		$f['moduleguid']  = $modx->db->escape($tplInfo['guid']);
		$f = $modx->db->escape($f);
		
		$pluginId = false;
		
		$name = $modx->db->escape($name);
		$dbv_plugin = $modx->db->getObject('site_plugins', "name='{$name}' AND disabled='0'");
		if($dbv_plugin!==false && $dbv_plugin->description !== $tplInfo['description'])
		{
			$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "id='{$dbv_plugin->id}'");
			if($rs)
			{
				$f['category']  = $modx->db->escape($dbv_plugin->category);
				$pluginId = $modx->db->insert($f, '[+prefix+]site_plugins');
			}
			if(!$rs || !$pluginId)
			{
				$errors += 1;
				showError();
				return;
			}
			else echo ok($name,$lang_upgraded);
		}
		else
		{
			$f['category']    = getCreateDbCategory($tplInfo['category']);
			$pluginId = $modx->db->insert($f, '[+prefix+]site_plugins');
			if(!$pluginId) {
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_installed);
		}
		
		// add system events
		$events = explode(',', $tplInfo['events']);
		if($pluginId && count($events) > 0)
		{
			// remove existing events
			$modx->db->delete('[+prefix+]site_plugin_events', "pluginid='{$pluginId}'");
			
			// add new events
			$events = implode("','", $events);
			$selected = "SELECT '{$pluginId}' as 'pluginid',se.id as 'evtid' FROM [+prefix+]system_eventnames se WHERE name IN ('{$events}')";
			$query = "INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid) {$selected}";
			$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
			$modx->db->query($query);
		}
	endforeach;
}

// Install Snippets
if ($formvSnippets!==false && !empty($formvSnippets) || $installdata)
{
	echo "<h3>{$lang_snippets}:</h3>";
	foreach ($tplSnippets as $k=>$tplInfo)
	{
		if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
			$installSample = true;
		else
			$installSample = false;
		
		if(!in_array($k, $formvSnippets) && !$installSample) continue;
		
		$name = $modx->db->escape($tplInfo['name']);
		$tpl_file_path = $tplInfo['tpl_file_path'];
		if (!is_file($tpl_file_path))
		{
			echo ng($name, "{$lang_unable_install_snippet} '{$tpl_file_path}' {$lang_not_found}");
			continue;
		}
		
		$f = array();
		$snippet = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path)));
		$f['snippet']     = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $snippet, 1);
		$f['description'] = $tplInfo['description'];
		$f['properties']  = $tplInfo['properties'];
		$f = $modx->db->escape($f);
		
		$dbv_snippet = $modx->db->getObject('site_snippets', "name='{$name}'");
		if ($dbv_snippet)
		{
			$props = propUpdate($properties,$dbv_snippet->properties);
			if (!@ $modx->db->update($f, '[+prefix+]site_snippets', "name='{$name}'"))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_upgraded);
		}
		else
		{
			$f['name']     = $name;
			$f['category'] = getCreateDbCategory($tplInfo['category']);
			if (!@ $modx->db->insert($f, '[+prefix+]site_snippets'))
			{
				$errors += 1;
				showError();
				return;
			}
			echo ok($name,$lang_installed);
		}
	}
}

if($installmode ==0 && is_file("{$base_path}install/sql/new_override.sql"))
{
	$sqlParser->process('new_override.sql');
}

// install data
if ($installmode == 0 && $installdata)
{
	echo "<p>{$lang_installing_demo_site}";
	$sqlParser->process('new_sample.sql');
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