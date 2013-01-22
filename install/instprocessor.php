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
require_once('functions.php');

$installdata  = getOption('installdata');
$templates    = getOption('template');
$tvs          = getOption('tv');
$chunks       = getOption('chunk');
$snippets     = getOption('snippet');
$plugins      = getOption('plugin');
$modules      = getOption('module');

echo "<p>{$_lang['setup_database']}</p>\n";

// get base path and url
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', true);
require_once("{$base_path}manager/includes/initialize.inc.php");
startCMSSession();
$database_type = 'mysql';
include_once("{$base_path}manager/includes/document.parser.class.inc.php");
$modx = new DocumentParser;
$modx->db->connect();

$tbl_site_plugins = getFullTableName('site_plugins');
$tbl_system_settings = getFullTableName('system_settings');
$tbl_site_templates = getFullTableName('site_templates');
$tbl_site_tmplvars = getFullTableName('site_tmplvars');
$tbl_site_tmplvar_templates = getFullTableName('site_tmplvar_templates');
$tbl_site_htmlsnippets = getFullTableName('site_htmlsnippets');
$tbl_site_modules = getFullTableName('site_modules');
$tbl_site_plugin_events = getFullTableName('site_plugin_events');
$tbl_system_eventnames = getFullTableName('system_eventnames');
$tbl_site_snippets = getFullTableName('site_snippets');
$tbl_active_users = getFullTableName('active_users');

// open db connection
$setupPath = realpath(dirname(__FILE__));
include "{$setupPath}/setup.info.php";
include "{$setupPath}/sqlParser.class.php";
$sqlParser = new SqlParser();
$sqlParser->prefix = $table_prefix;
$sqlParser->adminname = $adminname;
$sqlParser->adminpass = $adminpass;
$sqlParser->adminemail = $adminemail;
$sqlParser->connection_charset = 'utf8';
$sqlParser->connection_collation = $database_collation;
$sqlParser->connection_method = $database_connection_method;
$sqlParser->managerlanguage = $managerlanguage;
$sqlParser->manager_theme = $default_config['manager_theme'];
$sqlParser->mode = ($installmode < 1) ? 'new' : 'upd';
$sqlParser->base_path = $base_path;
$sqlParser->ignoreDuplicateErrors = true;

// install/update database
echo '<p>' . $_lang['setup_database_creating_tables'];

$sqlParser->process('both_createtables.sql');
if($installmode==0) $sqlParser->process('new_setvalues.sql');
else                $sqlParser->process('upd_fixtables.sql');
$sqlParser->process('both_fixvalues.sql');

// display database results
if ($sqlParser->installFailed == true)
{
	$errors += 1;
	echo '<span class="notok"><b>' . $_lang['database_alerts'] . '</b></span>';
	echo '</p>';
	echo "<p>" . $_lang['setup_couldnt_install'] . "</p>";
	echo "<p>" . $_lang['installation_error_occured'] . "<br /><br />";
	for ($i = 0; $i < count($sqlParser->mysqlErrors); $i++) {
		echo "<em>" . $sqlParser->mysqlErrors[$i]["error"] . "</em>" . $_lang['during_execution_of_sql'] . "<span class='mono'>" . strip_tags($sqlParser->mysqlErrors[$i]["sql"]) . "</span>.<hr />";
	}
	echo '</p>';
	echo '<p>' . $_lang['some_tables_not_updated'] . '</p>';
	return;
}
else echo '<span class="ok">'.$_lang['ok'].'</span></p>';

echo '<p>' . $_lang['writing_config_file'];
$src = file_get_contents("{$base_path}install/tpl/config.inc.tpl");
$ph['database_type']               = 'mysql';
$ph['database_server']             = $database_server;
$ph['database_user']               = modx_escape($database_user);
$ph['database_password']           = modx_escape($database_password);
$ph['database_connection_method']  = $database_connection_method;
$ph['dbase']                       = $dbase;
$ph['table_prefix']                = $table_prefix;
$ph['lastInstallTime']             = time();
$ph['https_port']                  = '443';

$src = parse($src, $ph);
$config_path = "{$base_path}manager/includes/config.inc.php";
$config_saved = (@ file_put_contents($config_path, $src));

// try to chmod the config file go-rwx (for suexeced php)
@chmod($config_path, 0404);

if ($config_saved === false)
{
	echo '<span class="notok">' . $_lang['failed'] . "</span></p>";
	$errors += 1;
?>
	<p><?php echo $_lang['cant_write_config_file']?><span class="mono">manager/includes/config.inc.php</span></p>
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
	$siteid = uniqid('');
	mysql_query("REPLACE INTO {$tbl_system_settings} (setting_name,setting_value) VALUES('site_id','{$siteid}')");
}
else
{
	// update site_id if missing
	$ds = mysql_query("SELECT setting_name,setting_value FROM {$tbl_system_settings} WHERE setting_name='site_id'");
	if ($ds)
	{
		$r = mysql_fetch_assoc($ds);
		$siteid = $r['setting_value'];
		if ($siteid == '' || $siteid = 'MzGeQ2faT4Dw06+U49x3')
		{
			$siteid = uniqid('');
			mysql_query("REPLACE INTO {$tbl_system_settings} (setting_name,setting_value) VALUES('site_id','{$siteid}')");
		}
	}
}

// Install Templates
if ($templates!==false || $installData==1)
{
	echo "<h3>" . $_lang['templates'] . ":</h3> ";
	
	foreach ($moduleTemplates as $k=>$moduleTemplate)
	{
		$installSample = in_array('sample', $moduleTemplate[6]) && $installData == 1;
		if($installSample || in_array($k, $templates))
		{
			$name = modx_escape($moduleTemplate[0]);
			$desc = modx_escape($moduleTemplate[1]);
			$category = modx_escape($moduleTemplate[4]);
			$locked = modx_escape($moduleTemplate[5]);
			$filecontent = $moduleTemplate[3];
			if (!is_file($filecontent))
			{
				echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_template'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
			}
			else
			{
				// Create the category if it does not already exist
				$category_id = getCreateDbCategory($category, $sqlParser);
				
				// Strip the first comment up top
				$content = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', file_get_contents($filecontent), 1);
				$content = modx_escape($content);
				
				// See if the template already exists
				$rs = mysql_query("SELECT * FROM {$tbl_site_templates} WHERE templatename='$name'");
				
				if (mysql_num_rows($rs))
				{
					if (!@ mysql_query("UPDATE {$tbl_site_templates} SET content='$content', description='$desc', category=$category_id, locked='$locked'  WHERE templatename='$name';"))
					{
						$errors += 1;
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
				}
				else
				{
					$rs = mysql_query("SELECT * FROM {$tbl_site_templates}");
					if (!@ mysql_query("INSERT INTO {$tbl_site_templates} (templatename,description,content,category,locked) VALUES('$name','$desc','$content',$category_id,'$locked');"))
					{
						$errors += 1;
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
				}
			}
		}
	}
}

// Install Template Variables
if ($tvs!==false || $installData)
{
	echo "<h3>" . $_lang['tvs'] . ":</h3> ";
	foreach ($moduleTVs as $k=>$moduleTV)
	{
		$installSample = in_array('sample', $moduleTV[12]) && $installData == 1;
		if($installSample || in_array($k, $tvs))
		{
			$name = modx_escape($moduleTV[0]);
			$caption = modx_escape($moduleTV[1]);
			$desc = modx_escape($moduleTV[2]);
			$input_type = modx_escape($moduleTV[3]);
			$input_options = modx_escape($moduleTV[4]);
			$input_default = modx_escape($moduleTV[5]);
			$output_widget = modx_escape($moduleTV[6]);
			$output_widget_params = modx_escape($moduleTV[7]);
			$filecontent = $moduleTV[8];
			$assignments = $moduleTV[9];
			$category = modx_escape($moduleTV[10]);
			$locked = modx_escape($moduleTV[11]);
			
			// Create the category if it does not already exist
			$category = getCreateDbCategory($category, $sqlParser);
			
			$rs = mysql_query("SELECT * FROM {$tbl_site_tmplvars} WHERE name='$name'");
			if (mysql_num_rows($rs))
			{
				$insert = true;
				while($row = mysql_fetch_assoc($rs))
				{
					if (!@ mysql_query("UPDATE {$tbl_site_tmplvars} SET type='$input_type', caption='$caption', description='$desc', category=$category, locked=$locked, elements='$input_options', display='$output_widget', display_params='$output_widget_params', default_text='$input_default' WHERE id={$row['id']};")) {
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					$insert = false;
				}
				echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
			}
			else
			{
				$q = "INSERT INTO {$tbl_site_tmplvars} (type,name,caption,description,category,locked,elements,display,display_params,default_text) VALUES('$input_type','$name','$caption','$desc',$category,$locked,'$input_options','$output_widget','$output_widget_params','$input_default');";
				if (!@ mysql_query($q))
				{
					echo '<p>' . mysql_error() . '</p>';
					return;
				}
				echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
			}
			
			// add template assignments
			$assignments = explode(',', $assignments);
			if (count($assignments) > 0)
			{
				// remove existing tv -> template assignments
				$ds=mysql_query("SELECT id FROM {$tbl_site_tmplvars} WHERE name='$name' AND description='$desc';");
				$row = mysql_fetch_assoc($ds);
				$id = $row["id"];
				mysql_query("DELETE FROM {$tbl_site_tmplvar_templates} WHERE tmplvarid = '{$id}'");
				
				// add tv -> template assignments
				foreach ($assignments as $assignment)
				{
					$templatename = modx_escape($assignment);
					$ts = mysql_query("SELECT id FROM {$tbl_site_templates} WHERE templatename='$templatename';");
					if ($ds && $ts)
					{
						$tRow = mysql_fetch_assoc($ts);
						$templateId = $tRow['id'];
						mysql_query("INSERT INTO {$tbl_site_tmplvar_templates} (tmplvarid, templateid) VALUES($id, $templateId)");
					}
				}
			}
		}
	}
}

// Install Chunks
if ($chunks!==false || $installData)
{
	echo "<h3>" . $_lang['chunks'] . ":</h3> ";
	foreach ($moduleChunks as $k=>$moduleChunk)
	{
		$installSample = in_array('sample', $moduleChunk[5]) && $installData == 1;
		if(in_array($k, $chunks) || $installSample)
		{
			$name      = modx_escape($moduleChunk[0]);
			$desc      = modx_escape($moduleChunk[1]);
			$category  = modx_escape($moduleChunk[3]);
			$overwrite = modx_escape($moduleChunk[4]);
			$filecontent = $moduleChunk[2];
			
			if (!is_file($filecontent))
			{
				echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . "{$_lang['unable_install_chunk']} '{$filecontent}' {$_lang['not_found']}</span></p>";
			}
			else
			{
				// Create the category if it does not already exist
				$category_id = getCreateDbCategory($category, $sqlParser);
				
				$rs = mysql_query("SELECT * FROM {$tbl_site_htmlsnippets} WHERE name='$name'");
				$count_original_name = mysql_num_rows($rs);
				if($overwrite == 'false')
				{
					$newname = $name . '-' . str_replace('.', '_', $modx_version);
					$rs = mysql_query("SELECT * FROM {$tbl_site_htmlsnippets} WHERE name='$newname'");
					$count_new_name = mysql_num_rows($rs);
				}
				$update = $count_original_name > 0 && $overwrite == 'true';
				$snippet = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', file_get_contents($filecontent), 1);
				$snippet = modx_escape($snippet);
				if ($update)
				{
					if (!@ mysql_query("UPDATE {$tbl_site_htmlsnippets} SET snippet='$snippet', description='$desc', category=$category_id WHERE name='$name';"))
					{
						$errors += 1;
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
				}
				elseif($count_new_name == 0)
				{
					if($count_original_name > 0 && $overwrite == 'false')
					{
						$name = $newname;
					}
					if (!@ mysql_query("INSERT INTO {$tbl_site_htmlsnippets} (name,description,snippet,category) VALUES('$name','$desc','$snippet',$category_id);"))
					{
						$errors += 1;
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
				}
			}
		}
	}
}

// Install Modules
if ($modules!==false || $installData)
{
	echo "<h3>" . $_lang['modules'] . ":</h3> ";
	foreach ($moduleModules as $k=>$moduleModule)
	{
		$installSample = in_array('sample', $moduleModule[7]) && $installData == 1;
		if(in_array($k, $modules) || $installSample)
		{
			$name = modx_escape($moduleModule[0]);
			$desc = modx_escape($moduleModule[1]);
			$filecontent = $moduleModule[2];
			$properties = modx_escape($moduleModule[3]);
			$guid = modx_escape($moduleModule[4]);
			$shared = modx_escape($moduleModule[5]);
			$category = modx_escape($moduleModule[6]);
			if (!is_file($filecontent))
			{
				echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . "{$_lang['unable_install_module']} '{$filecontent}' {$_lang['not_found']}</span></p>";
			}
			else
			{
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category, $sqlParser);
				
				$modulecode = end(preg_split("/(\/\/)?\s*\<\?php/", file_get_contents($filecontent), 2));
				// remove installer docblock
				$modulecode = preg_replace("/^.*?\/\*\*.*?\*\/\s+/s", '', $modulecode, 1);
				$modulecode = modx_escape($modulecode);
				$rs = mysql_query("SELECT * FROM {$tbl_site_modules} WHERE name='$name'");
				if (mysql_num_rows($rs))
				{
					$row = mysql_fetch_assoc($rs);
					$props = propUpdate($properties,$row['properties']);
					if (!@ mysql_query("UPDATE {$tbl_site_modules} SET modulecode='$modulecode', description='$desc', properties='$props', enable_sharedparams='$shared' WHERE name='$name';"))
					{
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
				}
				else
				{
					if (!@ mysql_query("INSERT INTO {$tbl_site_modules} (name,description,modulecode,properties,guid,enable_sharedparams,category) VALUES('$name','$desc','$modulecode','$properties','$guid','$shared', $category);"))
					{
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
				}
			}
		}
	}
}

// Install Plugins
if ($plugins!==false || $installData)
{
	echo "<h3>" . $_lang['plugins'] . ":</h3> ";
	foreach ($modulePlugins as $k=>$modulePlugin)
	{
		$installSample = in_array('sample', $modulePlugin[8]) && $installData == 1;
		if(in_array($k, $plugins) || $installSample)
		{
			$name = modx_escape($modulePlugin[0]);
			$desc = modx_escape($modulePlugin[1]);
			$filecontent = $modulePlugin[2];
			$properties = modx_escape($modulePlugin[3]);
			$events = explode(",", $modulePlugin[4]);
			$guid = modx_escape($modulePlugin[5]);
			$category = modx_escape($modulePlugin[6]);
			$leg_names = '';
			if(array_key_exists(7, $modulePlugin)) {
				// parse comma-separated legacy names and prepare them for sql IN clause
				$leg_names = "'" . implode("','", preg_split('/\s*,\s*/', modx_escape($modulePlugin[7]))) . "'";
			}
			if(!is_file($filecontent)) {
				echo "<p>&nbsp;&nbsp;$name: <span class=\"notok\">" . $_lang['unable_install_plugin'] . " '$filecontent' " . $_lang['not_found'] . ".</span></p>";
			} else {
				// disable legacy versions based on legacy_names provided
				if(!empty($leg_names)) {
					$update_query = "UPDATE {$tbl_site_plugins} SET disabled='1' WHERE name IN ($leg_names);";
					$rs = mysql_query($update_query);
				}
				
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category, $sqlParser);
				
				$plugincode = end(preg_split("@(//)?\s*\<\?php@", file_get_contents($filecontent), 2));
				// remove installer docblock
				$plugincode = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
				$plugincode = modx_escape($plugincode);
				$rs = mysql_query("SELECT * FROM {$tbl_site_plugins} WHERE name='$name' AND disabled='0'");
				if(mysql_num_rows($rs)) {
					$insert = true;
					while($row = mysql_fetch_assoc($rs)) {
						$props = propUpdate($properties,$row['properties']);
						if($row['description'] == $desc) {
							$rs = @ mysql_query("UPDATE {$tbl_site_plugins} SET plugincode='$plugincode', description='$desc', properties='$props' WHERE id={$row['id']};");
							if(!$rs) {
								echo '<p>' . mysql_error() . '</p>';
								return;
							}
							$insert = false;
						} else {
							$rs = @ mysql_query("UPDATE {$tbl_site_plugins} SET disabled='1' WHERE id={$row['id']};");
							if(!$rs) {
								echo '<p>'.mysql_error().'</p>';
								return;
							}
						}
					}
					if($insert === true) {
						if($props) $properties = $props;
						$rs = @mysql_query("INSERT INTO {$tbl_site_plugins} (name,description,plugincode,properties,moduleguid,disabled,category) VALUES('$name','$desc','$plugincode','$properties','$guid','0',$category);");
						if(!$rs) {
							echo '<p>'.mysql_error().'</p>';
							return;
						}
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
				} else {
					$rs = @ mysql_query("INSERT INTO {$tbl_site_plugins} (name,description,plugincode,properties,moduleguid,category) VALUES('$name','$desc','$plugincode','$properties','$guid',$category)";
					if(!$rs)) {
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
				}
				// add system events
				if(count($events) > 0) {
				$ds = mysql_query("SELECT id FROM {$tbl_site_plugins} WHERE name='$name' AND description='$desc';");
					if($ds) {
						$row = mysql_fetch_assoc($ds);
						$id = $row["id"];
						// remove existing events
						mysql_query("DELETE FROM {$tbl_site_plugin_events} WHERE pluginid = '{$id}'");
						// add new events
						mysql_query("INSERT INTO {$tbl_site_plugin_events} (pluginid, evtid) SELECT '{$id}' as 'pluginid',se.id as 'evtid' FROM {$tbl_system_eventnames} se WHERE name IN ('" . implode("','", $events) . "')");
					}
				}
			}
		}
	}
}

// Install Snippets
if ($snippets!==false || $installData)
{
	echo "<h3>" . $_lang['snippets'] . ":</h3> ";
	foreach ($moduleSnippets as $k=>$moduleSnippet)
	{
		$installSample = in_array('sample', $moduleSnippet[5]) && $installData == 1;
		if(in_array($k, $snippets) || $installSample)
		{
			$name = modx_escape($moduleSnippet[0]);
			$desc = modx_escape($moduleSnippet[1]);
			$filecontent = $moduleSnippet[2];
			$properties  = modx_escape($moduleSnippet[3]);
			$category    = modx_escape($moduleSnippet[4]);
			if (!is_file($filecontent))
			{
				echo '<p>&nbsp;&nbsp;' . $name . ': <span class="notok">' . $_lang['unable_install_snippet'] . " '$filecontent' " . $_lang['not_found'] . '.</span></p>';
			}
			else
			{
				// Create the category if it does not already exist
				$category = getCreateDbCategory($category, $sqlParser);
				
				$snippet = end(preg_split("@(//)?\s*\<\?php@", file_get_contents($filecontent)));
				// remove installer docblock
				$snippet = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $snippet, 1);
				$snippet = modx_escape($snippet);
				$rs = mysql_query("SELECT * FROM {$tbl_site_snippets} WHERE name='$name'");
				if (mysql_num_rows($rs))
				{
					$row = mysql_fetch_assoc($rs);
					$props = propUpdate($properties,$row['properties']);
					if (!@ mysql_query("UPDATE {$tbl_site_snippets} SET snippet='$snippet', description='$desc', properties='$props' WHERE name='$name';"))
					{
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['upgraded'] . '</span></p>';
				}
				else
				{
					if (!@ mysql_query("INSERT INTO {$tbl_site_snippets} (name,description,snippet,properties,category) VALUES('$name','$desc','$snippet','$properties',$category);"))
					{
						echo '<p>' . mysql_error() . '</p>';
						return;
					}
					echo "<p>&nbsp;&nbsp;$name: <span class=\"ok\">" . $_lang['installed'] . '</span></p>';
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
if ($installData)
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
@chmod("{$cache_path}sitePublishing.idx.php", 0600);

// remove any locks on the manager functions so initial manager login is not blocked
mysql_query("TRUNCATE TABLE {$tbl_active_users}");

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
