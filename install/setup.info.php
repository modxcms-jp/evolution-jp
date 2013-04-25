<?php
//:: MODx Installer Setup file 
//:::::::::::::::::::::::::::::::::::::::::
require_once("{$base_path}manager/includes/version.inc.php");

$moduleName = 'MODX';
$moduleVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;

$chunkPath    = "{$base_path}assets/chunks/";
$snippetPath  = "{$base_path}assets/snippets/";
$pluginPath   = "{$base_path}assets/plugins/";
$modulePath   = "{$base_path}assets/modules/";
$templatePath = "{$base_path}assets/templates/";
$tvPath       = "{$base_path}assets/tvs/";

if (is_file("{$base_path}manager/includes/config.inc.php"))
{
	global $dbase,$database_server,$database_user,$database_password,$table_prefix;
	include_once("{$base_path}manager/includes/config.inc.php");
}

$database_server   = $_SESSION['database_server'];
$database_user     = $_SESSION['database_user'];
$database_password = $_SESSION['database_password'];
$dbase             = trim($_SESSION['dbase'],'`');
$table_prefix      = $_SESSION['table_prefix'];

$installmode = $_SESSION['installmode'];

$conn = mysql_connect($database_server, $database_user, $database_password);
mysql_select_db($dbase, $conn);
mysql_query("SET CHARACTER SET 'utf8'", $conn);
if (function_exists('mysql_set_charset'))
{
	mysql_set_charset('utf8');
}
else
{
	mysql_query("SET NAMES 'utf8'");
}

// setup Template template files - array : name, description, type - 0:file or 1:content, parameters, category
$mt = &$moduleTemplates;
if($installmode==0 && is_dir($templatePath) && is_readable($templatePath))
{
	$files = collectTpls($templatePath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && (count($params)>0))
		{
			$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
			
			if($installmode==1 && compare_check($params)=='same') continue;
				
			$mt[] = array
			(
				$params['name'],
				$description,
				// Don't think this is gonna be used ... but adding it just in case 'type'
				$params['type'],
				$tplfile,
				$params['modx_category'],
				$params['lock_template'],
				array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
}

// setup Template Variable template files
$mtv = &$moduleTVs;
if($installmode==0 && is_dir($tvPath) && is_readable($tvPath))
{
	$files = collectTpls($tvPath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && (count($params)>0))
		{
			$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
			if($installmode==1 && compare_check($params)=='same') continue;
            $mtv[] = array(
					$params['name'],
					$params['caption'],
					$description,
					$params['input_type'],
					$params['input_options'],
					$params['input_default'],
					$params['output_widget'],
					$params['output_widget_params'],
					$tplfile, /* not currently used */
					$params['template_assignments'], /* comma-separated list of template names */
					$params['modx_category'],
					$params['lock_tv'],  /* value should be 1 or 0 */
					array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
				);
		}
	}
}

// setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
$mc = &$moduleChunks;
if($installmode==0 && is_dir($chunkPath) && is_readable($chunkPath))
{
	$files = collectTpls($chunkPath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && count($params) > 0)
		{
		
			if($installmode==1 && compare_check($params)=='same') continue;
			
			$mc[] = array(
			    $params['name'],
			    $params['description'],
			    $tplfile,
			    $params['modx_category'],
			    array_key_exists('overwrite', $params) ? $params['overwrite'] : 'true',
			    array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
}

// setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
$ms = &$moduleSnippets;
if(is_dir($snippetPath) && is_readable($snippetPath))
{
	$files = collectTpls($snippetPath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && count($params) > 0)
		{
			$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
			
			if($installmode==1 && compare_check($params)=='same') continue;
			
			$ms[] = array(
			    $params['name'],
			    $description,
			    $tplfile,
			    $params['properties'],
			    $params['modx_category'],
			    array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
}

// setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
$mp = &$modulePlugins;
if(is_dir($pluginPath) && is_readable($pluginPath))
{
	$files = collectTpls($pluginPath);
	foreach ($files as $tplfile)
	{
		if(strpos($tplfile,'/mgr_custom/')!==false) continue; //Ignore
		
		$params = parse_docblock($tplfile);
		if(is_array($params) && 0 < count($params))
		{
		
			if(!empty($params['version'])) $description = "<strong>{$params['version']}</strong> {$params['description']}";
			else                           $description = $params['description'];
			
			if($installmode==1 && compare_check($params)=='same') continue;
		
			$mp[] = array(
				$params['name'],
				$description,
				$tplfile,
				$params['properties'],
				$params['events'],
				$params['guid'],
				$params['modx_category'],
				$params['legacy_names'],
				array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
}

// setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
$mm = &$moduleModules;
if(is_dir($modulePath) && is_readable($modulePath))
{
	$files = collectTpls($modulePath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && count($params) > 0)
		{
			$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
			
			if($installmode==1 && compare_check($params)=='same') continue;
			
			$mm[] = array(
			    $params['name'],
			    $description,
			    $tplfile,
			    $params['properties'],
			    $params['guid'],
			    intval($params['shareparams']),
			    $params['modx_category'],
			    array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
}

// setup callback function
$callBackFnc = 'clean_up';
