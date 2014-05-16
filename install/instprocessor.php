<?php
global $moduleName;
global $moduleVersion;
global $moduleChunks;
global $moduleTemplates;
global $moduleSnippets;
global $modulePlugins;
global $moduleModules;
global $moduleTVs;

global $errors;

// set timout limit
@ set_time_limit(120); // used @ to prevent warning when using safe mode?

$self = 'install/instprocessor.php';
$base_path = str_replace($self, '',str_replace('\\','/', __FILE__));

require_once("{$base_path}manager/includes/default.config.php");

$installdata = $_SESSION['installdata'];unset($_SESSION['installdata']);
$templates   = $_SESSION['template'];   unset($_SESSION['template']);
$tvs         = $_SESSION['tv'];         unset($_SESSION['tv']);
$chunks      = $_SESSION['chunk'];      unset($_SESSION['chunk']);
$snippets    = $_SESSION['snippet'];    unset($_SESSION['snippet']);
$plugins     = $_SESSION['plugin'];     unset($_SESSION['plugin']);
$modules     = $_SESSION['module'];     unset($_SESSION['module']);

$installmode = $_SESSION['installmode'];

echo "<p>{$_lang['setup_database']}</p>\n";
// get base path and url
define('MODX_API_MODE', true);
require_once("{$base_path}manager/includes/initialize.inc.php");
startCMSSession();
$database_type = 'mysql';
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

// install/update database
echo '<p>' . $_lang['setup_database_creating_tables'];
$sqlParser->process('both_createtables.sql');
if($installmode==0) $sqlParser->process('new_setvalues.sql');
else                $sqlParser->process('upd_fixvalues.sql');
$sqlParser->process('both_fixvalues.sql');
// display database results
if ($sqlParser->installFailed == true)
{
	$errors += 1;
	echo '<span class="notok"><b>' . $_lang['database_alerts'] . '</b></span>';
	echo '</p>';
	echo '<p>' . $_lang['setup_couldnt_install'] . '</p>';
	echo '<p>' . $_lang['installation_error_occured'] . '<br /><br />';
	foreach ($sqlParser->mysqlErrors as $err) {
		echo '<em>' . $err['error'] . '</em>' . $_lang['during_execution_of_sql'] . '<span class="mono">' . strip_tags($err['sql']) . '</span>.<hr />';
	}
	echo '</p>';
	echo '<p>' . $_lang['some_tables_not_updated'] . '</p>';
	return;
}
else echo '<span class="ok">'.$_lang['ok'].'</span></p>';

$src = file_get_contents("{$base_path}install/tpl/config.inc.tpl");
$ph['database_type']               = 'mysql';
$ph['database_server']             = $_SESSION['database_server'];
$ph['database_user']               = $modx->db->escape($_SESSION['database_user']);
$ph['database_password']           = $modx->db->escape($_SESSION['database_password']);
$ph['database_connection_method']  = $_SESSION['database_connection_method'];
$ph['dbase']                       = trim($_SESSION['dbase'],'`');
$ph['table_prefix']                = $_SESSION['table_prefix'];
$ph['lastInstallTime']             = time();
$ph['https_port']                  = '443';

$src = parse($src, $ph);
$config_path = "{$base_path}manager/includes/config.inc.php";
$config_saved = (@ file_put_contents($config_path, $src));

// try to chmod the config file go-rwx (for suexeced php)
@chmod($config_path, 0404);

echo '<p>' . $_lang['writing_config_file'];
if ($config_saved === false)
{
	echo '<span class="notok">' . $_lang['failed'] . "</span></p>";
	$errors += 1;
?>
	<p><?php echo $_lang['cant_write_config_file'];?><span class="mono">manager/includes/config.inc.php</span></p>
	<textarea style="width:400px; height:160px;">
	<?php echo $configString; ?>
	</textarea>
	<p><?php echo $_lang['cant_write_config_file_note']?></p>
<?php
}
else
{
	echo '<span class="ok">' . $_lang['ok'] . '</span></p>';
}

// generate new site_id

if ($installmode == 0)
{
	
	$site_id = uniqid('');
	$query = "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','{$site_id}')";
	$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
	$modx->db->query($query);
	
}
else
{
	// update site_id if missing
	$ds = $modx->db->select('*', '[+prefix+]system_settings', "setting_name='site_id'");
	if ($ds)
	{
		$row = $modx->db->getRow($ds);
		$site_id = $row['setting_value'];
		if ($site_id == '' || $site_id = 'MzGeQ2faT4Dw06+U49x3')
		{
			$site_id = uniqid('');
			$query = "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','{$site_id}')";
			$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
			$modx->db->query($query);
		}
	}
}

// Install Templates
if ($templates!==false && !empty($templates) || $installdata==1)
{
	echo "<h3>" . $_lang['templates'] . ":</h3> ";
	
	foreach ($moduleTemplates as $k=>$moduleTemplate)
	{
		if(!in_array($k, $templates)
			&&
		  (!in_array('sample', $moduleTemplate[6]) || $installdata != 1))
			continue;
		
		$templatename = $modx->db->escape($moduleTemplate[0]);
		$tpl_file_path = $moduleTemplate[3];
		
		if (!is_file($tpl_file_path)) {
			echo ng($templatename,$_lang['unable_install_template'] . " '{$tpl_file_path}' " . $_lang['not_found']);
			continue;
		}
		
		// Strip the first comment up top
		$content = file_get_contents($tpl_file_path);
		$f = array();
		$f['content'] = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', $content, 1);
		$f['description'] = $moduleTemplate[1];
		// Create the category if it does not already exist
		$f['category'] = getCreateDbCategory($moduleTemplate[4]);
		$f['locked'] = $moduleTemplate[5];
		$f = $modx->db->escape($f);
		
		// See if the template already exists
		$rs = $modx->db->select('*', '[+prefix+]site_templates', "templatename='{$templatename}'");
		if (0<$modx->db->getRecordCount($rs))
		{
			if (!@ $modx->db->update($f, '[+prefix+]site_templates', "templatename='{$templatename}'"))
			{
				$errors += 1;
				echo '<p>' . $modx->db->getLastError() . '</p>';
				return;
			}
			else echo ok($templatename,$_lang['upgraded']);
		}
		else
		{
			$f['templatename'] = $templatename;
			if (!@ $modx->db->insert($f, '[+prefix+]site_templates'))
			{
				$errors += 1;
				echo '<p>' . $modx->db->getLastError() . '</p>';
				return;
			}
			else echo ok($templatename,$_lang['installed']);
		}
	}
}

// Install Template Variables
if ($tvs!==false && !empty($tvs) || $installdata)
{
	echo "<h3>" . $_lang['tvs'] . ":</h3> ";
	foreach ($moduleTVs as $k=>$moduleTV):
		if(!in_array($k, $tvs)) {
			if(!in_array('sample', $moduleTV[12]) || $installdata != 1)
				continue;
		}
		
		$name = $modx->db->escape($moduleTV[0]);
		$f = array();
		$f['type'] = $moduleTV[3];
		$f['caption'] = $moduleTV[1];
		$f['description'] = $moduleTV[2];
		$f['category'] = getCreateDbCategory($moduleTV[10]);
		$f['locked'] = $moduleTV[11];
		$f['elements'] = $moduleTV[4];
		$f['default_text'] = $moduleTV[5];
		$f['display'] = $moduleTV[6];
		$f['display_params'] = $moduleTV[7];
		$f = $modx->db->escape($f);
		
		$rs = $modx->db->select('*', '[+prefix+]site_tmplvars', "name='{$name}'");
		if ($modx->db->getRecordCount($rs))
		{
			$insert = true;
			$row = $modx->db->getRow($rs);
			$id = $row['id'];
			$rs = $modx->db->update($f, '[+prefix+]site_tmplvars', "id='{$id}'");
			if ($rs) {
				$insert = false;
				echo ok($name,$_lang['upgraded']);
			} else {
				echo '<p>' . $modx->db->getLastError() . '</p>';
				return;
			}
		}
		else
		{
			$f['name'] = $name;
			$rs = $modx->db->insert($f, '[+prefix+]site_tmplvars');
			if ($rs) {
				echo ok($name,$_lang['installed']);
			} else {
				echo '<p>' . $modx->db->getLastError() . '</p>';
				return;
			}
		}
		
		// add template assignments
		$assignments = explode(',', $moduleTV[9]);
		if(empty($assignments)) continue;
		
		// remove existing tv -> template assignments
		$description = $f['description'];
		$rs = $modx->db->select('id', '[+prefix+]site_tmplvars', "name='{$name}' AND description='{$description}'");
		if($modx->db->getRecordCount($rs)==0) continue;
		
		$row = $modx->db->getRow($rs);
		$tmplvarid = $row['id'];
		$modx->db->delete('[+prefix+]site_tmplvar_templates', "tmplvarid='{$tmplvarid}'");
		
		// add tv -> template assignments
		foreach ($assignments as $templatename) {
			$templatename = $modx->db->escape($templatename);
			$rs = $modx->db->select('id', '[+prefix+]site_templates', "templatename='{$templatename}'");
			if (0<$modx->db->getRecordCount($rs))
			{
				$row = $modx->db->getRow($ts);
				$templateid = $row['id'];
				$f = array('tmplvarid'=>$tmplvarid, 'templateid'=>$templateid);
				$modx->db->insert($f, '[+prefix+]site_tmplvar_templates');
			}
		}
	endforeach;
}

// Install Chunks
if ($chunks!==false && !empty($chunks) || $installdata)
{
	echo "<h3>" . $_lang['chunks'] . ":</h3> ";
	foreach ($moduleChunks as $k=>$moduleChunk)
	{
		if(!in_array($k, $chunks)) {
			if(!in_array('sample', $moduleChunk[5]) || $installdata != 1)
				continue;
		}
		
		$name      = $modx->db->escape($moduleChunk[0]);
		$desc      = $modx->db->escape($moduleChunk[1]);
		$category  = $modx->db->escape($moduleChunk[3]);
		$overwrite = $modx->db->escape($moduleChunk[4]);
		$tpl_file_path = $moduleChunk[2];
		
		if (!is_file($tpl_file_path))
		{
			echo ng($name,"{$_lang['unable_install_chunk']} '{$tpl_file_path}' {$_lang['not_found']}");
		}
		else
		{
			// Create the category if it does not already exist
			$category_id = getCreateDbCategory($category);
			
			$rs = $modx->db->select('*', '[+prefix+]site_htmlsnippets', "name='{$name}'");
			$count_original_name = $modx->db->getRecordCount($rs);
			if($overwrite == 'false')
			{
				$newname = $name . '-' . str_replace('.', '_', $modx_version);
				$rs = $modx->db->select('*', '[+prefix+]site_htmlsnippets', "name='{$newname}'");
				$count_new_name = $modx->db->getRecordCount($rs);
			}
			$update = $count_original_name > 0 && $overwrite == 'true';
			$snippet = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', file_get_contents($tpl_file_path), 1);
			$snippet = $modx->db->escape($snippet);
			if ($update)
			{
				$f = array('snippet'=>$snippet, 'description'=>$desc, 'category'=>$category_id);
				if (!@ $modx->db->update($f, '[+prefix+]site_htmlsnippets', "name='{$name}'"))
				{
					$errors += 1;
					echo '<p>' . $modx->db->getLastError() . '</p>';
					return;
				}
				echo ok($name,$_lang['upgraded']);
			}
			elseif($count_new_name == 0)
			{
				if($count_original_name > 0 && $overwrite == 'false')
				{
					$name = $newname;
				}
				$f = array('name'=>$name,'description'=>$desc,'snippet'=>$snippet,'category'=>$category_id);
				if (!@ $modx->db->insert($f, '[+prefix+]site_htmlsnippets'))
				{
					$errors += 1;
					echo '<p>' . $modx->db->getLastError() . '</p>';
					return;
				}
				echo ok($name,$_lang['installed']);
			}
		}
	}
}

// Install Modules
if ($modules!==false && !empty($modules) || $installdata)
{
	echo "<h3>" . $_lang['modules'] . ":</h3> ";
	foreach ($moduleModules as $k=>$moduleModule)
	{
		if(in_array('sample', $moduleModule[7]) && $installdata == 1) $installSample = true;
		if(in_array($k, $modules) || $installSample)
		{
			$name = $modx->db->escape($moduleModule[0]);
			$desc = $modx->db->escape($moduleModule[1]);
			$filecontent = $moduleModule[2];
			$properties = $modx->db->escape($moduleModule[3]);
			$guid = $modx->db->escape($moduleModule[4]);
			$shared = $modx->db->escape($moduleModule[5]);
			$category = $modx->db->escape($moduleModule[6]);
			if (!is_file($filecontent))
			{
				echo ng($name,"{$_lang['unable_install_module']} '{$filecontent}' {$_lang['not_found']}");
			}
			else
			{
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category);
				
				$modulecode = end(preg_split("/(\/\/)?\s*\<\?php/", file_get_contents($filecontent), 2));
				// remove installer docblock
				$modulecode = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', $modulecode, 1);
				$modulecode = $modx->db->escape($modulecode);
				$rs = $modx->db->select('*', '[+prefix+]site_modules', "name='{$name}'");
				if ($modx->db->getRecordCount($rs))
				{
					$row = $modx->db->getRow($rs);
					$props = propUpdate($properties,$row['properties']);
					$f = array('modulecode'=>$modulecode, 'description'=>$desc, 'properties'=>$props, 'enable_sharedparams'=>$shared);
					if (!@ $modx->db->update($f, '[+prefix+]site_modules', "name='{$name}'"))
					{
						echo '<p>' . $modx->db->getLastError() . '</p>';
						return;
					}
					echo ok($name,$_lang['upgraded']);
				}
				else
				{
					$f = array();
					$f['name']        = $name;
					$f['description'] = $desc;
					$f['modulecode']  = $modulecode;
					$f['properties']  = $properties;
					$f['guid']        = $guid;
					$f['enable_sharedparams'] = $shared;
					$f['category']    = $category;
					if (!@ $modx->db->insert($f, '[+prefix+]site_modules'))
					{
						echo '<p>' . $modx->db->getLastError() . '</p>';
						return;
					}
					echo ok($name,$_lang['installed']);
				}
			}
		}
	}
}

// Install Plugins
if ($plugins!==false && !empty($plugins) || $installdata)
{
	echo "<h3>" . $_lang['plugins'] . ":</h3> ";
	foreach ($modulePlugins as $k=>$modulePlugin)
	{
		if(in_array('sample', $modulePlugin[8]) && $installdata == 1) $installSample = true;
		
		if(in_array($k, $plugins) || $installSample)
		{
			$name = $modx->db->escape($modulePlugin[0]);
			$desc = $modx->db->escape($modulePlugin[1]);
			$filecontent = $modulePlugin[2];
			$properties = $modx->db->escape($modulePlugin[3]);
			$events = explode(',', $modulePlugin[4]);
			$guid = $modx->db->escape($modulePlugin[5]);
			$category = $modx->db->escape($modulePlugin[6]);
			$leg_names = '';
			if(array_key_exists(7, $modulePlugin)) {
				// parse comma-separated legacy names and prepare them for sql IN clause
				$leg_names = implode("','", preg_split('/\s*,\s*/', $modx->db->escape($modulePlugin[7])));
			}
			if(!is_file($filecontent)) {
				echo ng($name, $_lang['unable_install_plugin'] . " '{$filecontent}' " . $_lang['not_found']);
			} else {
				// disable legacy versions based on legacy_names provided
				if(!empty($leg_names)) {
					$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "name IN ('{$leg_names}')");
				}
				
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category);
				
				$plugincode = end(preg_split("@(//)?\s*\<\?php@", file_get_contents($filecontent), 2));
				// remove installer docblock
				$plugincode = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
				$plugincode = $modx->db->escape($plugincode);
				
				$rs = $modx->db->select('*', '[+prefix+]site_plugins', "name='{$name}' AND disabled='0'");
				
				if(0<$modx->db->getRecordCount($rs))
				{
					$insert = true;
					
					while($row = $modx->db->getRow($rs)):
						$props = propUpdate($properties,$row['properties']);
						$id = $row['id'];
						if($row['description'] === $desc)
						{
							$f = array();
							$f['plugincode']  = $plugincode;
							$f['description'] = $desc;
							$f['properties']  = $props;
							$rs = $modx->db->update($f, '[+prefix+]site_plugins', "id='{$id}'");
							if($rs) $insert = false;
						}
						else
						{
							$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "id='{$id}'");
						}
						if(!$rs) {
							echo '<p>' . $modx->db->getLastError() . '</p>';
							return;
						}
					endwhile;
					if($insert === true) {
						if($props) $properties = $props;
						$f = array();
						$f['name']        = $name;
						$f['description'] = $desc;
						$f['plugincode']  = $plugincode;
						$f['properties']  = $properties;
						$f['moduleguid']  = $guid;
						$f['disabled']    = '0';
						$f['category']    = $category;
						$rs = $modx->db->insert($f, '[+prefix+]site_plugins');
						if(!$rs) {
							echo '<p>'.$modx->db->getLastError().'</p>';
							return;
						}
					}
					echo ok($name,$_lang['upgraded']);
				}
				else
				{
					$f = array();
					$f['name']        = $name;
					$f['description'] = $desc;
					$f['plugincode']  = $plugincode;
					$f['properties']  = $properties;
					$f['moduleguid']  = $guid;
					$f['category']    = $category;
					$rs = $modx->db->insert($f, '[+prefix+]site_plugins');
					if(!$rs) {
						echo '<p>' . $modx->db->getLastError() . '</p>';
						return;
					}
					echo ok($name,$_lang['installed']);
				}
				// add system events
				if(count($events) > 0)
				{
					
					$ds = $modx->db->select('id', '[+prefix+]site_plugins', "name='{$name}' AND description='{$desc}'");
					if($ds) {
						$row = $modx->db->getRow($ds);
						$id = $row["id"];
						// remove existing events
						$modx->db->delete('[+prefix+]site_plugin_events', "pluginid = '{$id}'");
						// add new events
						$events = implode("','", $events);
						$selected = "SELECT '{$id}' as 'pluginid',se.id as 'evtid' FROM [+prefix+]system_eventnames se WHERE name IN ('{$events}')";
						$query = "INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid) {$selected}";
						$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
						$modx->db->query($query);
					}
				}
			}
		}
	}
}

// Install Snippets
if ($snippets!==false && !empty($snippets) || $installdata)
{
	echo "<h3>" . $_lang['snippets'] . ":</h3> ";
	foreach ($moduleSnippets as $k=>$moduleSnippet)
	{
		if(in_array('sample', $moduleSnippet[5]) && $installdata == 1) $installSample = true;
		if(in_array($k, $snippets) || $installSample)
		{
			$name = $modx->db->escape($moduleSnippet[0]);
			$desc = $modx->db->escape($moduleSnippet[1]);
			$filecontent = $moduleSnippet[2];
			$properties  = $modx->db->escape($moduleSnippet[3]);
			$category    = $modx->db->escape($moduleSnippet[4]);
			if (!is_file($filecontent))
			{
				echo ng($name, $_lang['unable_install_snippet'] . " '{$filecontent}' " . $_lang['not_found']);
			}
			else
			{
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category);
				
				$snippet = end(preg_split("@(//)?\s*\<\?php@", file_get_contents($filecontent)));
				// remove installer docblock
				$snippet = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $snippet, 1);
				$snippet = $modx->db->escape($snippet);
				$rs = $modx->db->select('*', '[+prefix+]site_snippets', "name='{$name}'");
				if ($modx->db->getRecordCount($rs))
				{
					$row = $modx->db->getRow($rs);
					$props = propUpdate($properties,$row['properties']);
					$f = array('snippet'=>$snippet,'description'=>$desc,'properties'=>$props);
					if (!@ $modx->db->update($f, '[+prefix+]site_snippets', "name='{$name}'"))
					{
						echo '<p>' . $modx->db->getLastError() . '</p>';
						return;
					}
					echo ok($name,$_lang['upgraded']);
				}
				else
				{
					$f = array();
					$f['name']        = $name;
					$f['description'] = $desc;
					$f['snippet']     = $snippet;
					$f['properties']  = $properties;
					$f['category']    = $category;
					if (!@ $modx->db->insert($f, '[+prefix+]site_snippets'))
					{
						echo '<p>' . $modx->db->getLastError() . '</p>';
						return;
					}
					echo ok($name,$_lang['installed']);
				}
			}
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
	echo '<p>' . $_lang['installing_demo_site'];
	$sqlParser->process('new_sample.sql');
	if ($sqlParser->installFailed == true)
	{
		$errors += 1;
		echo '<span class="notok"><b>' . $_lang['database_alerts'] . '</b></span></p>';
		echo '<p>' . $_lang['setup_couldnt_install'] . '</p>';
		echo '<p>' . $_lang['installation_error_occured'] . '<br /><br />';
		for ($i = 0; $i < count($sqlParser->mysqlErrors); $i++)
		{
			echo '<em>' . $sqlParser->mysqlErrors[$i]["error"] . '</em>' . $_lang['during_execution_of_sql'] . '<span class="mono">' . strip_tags($sqlParser->mysqlErrors[$i]["sql"]) . '</span>.<hr />';
		}
		echo '</p>';
		echo '<p>' . $_lang['some_tables_not_updated'] . '</p>';
		return;
	}
	else
	{
		echo '<span class="ok">'.$_lang['ok'].'</span></p>';
	}
}

// call back function
if ($callBackFnc != '') $callBackFnc ($sqlParser);

// Setup the MODx API -- needed for the cache processor
// initiate a new document parser
include_once("{$base_path}index.php");

$modx->clearCache(); // always empty cache after install
$cache_path = "{$base_path}assets/cache/";

$files = glob("{$cache_path}*.idx.php");
foreach($files as $file)
{
	@unlink($file);
}

// try to chmod the cache go-rwx (for suexeced php)
@chmod("{$cache_path}siteCache.idx.php", 0600);
@chmod("{$cache_path}basicConfig.idx.php", 0600);

// remove any locks on the manager functions so initial manager login is not blocked
$modx->db->truncate('[+prefix+]active_users');

// andrazk 20070416 - release manager access
if (is_file("{$cache_path}installProc.inc.php"))
{
	@chmod("{$cache_path}installProc.inc.php", 0755);
	unlink("{$cache_path}installProc.inc.php");
}
// setup completed!
echo "<p><b>" . $_lang['installation_successful'] . "</b></p>";
echo '<p>' . $_lang['to_log_into_content_manager'] . '</p>';
echo '<p><img src="img/ico_info.png" align="left" style="margin-right:10px;" />';

if($installmode == 0) echo $_lang['installation_note'];
else                  echo $_lang['upgrade_note'];

echo '</p>';

function ok($name,$msg) {
	return "<p>&nbsp;&nbsp;{$name}: " . '<span class="ok">' . $msg . "</span></p>\n";
}

function ng($name,$msg) {
	return "<p>&nbsp;&nbsp;{$name}: " . '<span class="notok">' . $msg . "</span></p>\n";
}
