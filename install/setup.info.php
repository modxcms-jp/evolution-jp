<?php
//:: MODx Installer Setup file 
//:::::::::::::::::::::::::::::::::::::::::

$chunkPath    = "{$base_path}assets/chunks/";
$snippetPath  = "{$base_path}assets/snippets/";
$pluginPath   = "{$base_path}assets/plugins/";
$modulePath   = "{$base_path}assets/modules/";
$templatePath = "{$base_path}assets/templates/";
$tvPath       = "{$base_path}assets/tvs/";

global $_lang,$dbase,$database_server,$database_user,$database_password,$table_prefix;
$database_server   = $_SESSION['database_server'];
$database_user     = $_SESSION['database_user'];
$database_password = $_SESSION['database_password'];
$dbase             = trim($_SESSION['dbase'],'`');
$table_prefix      = $_SESSION['table_prefix'];

$installmode = $_SESSION['installmode'];

global $mysqli;
$mysqli = new mysqli($database_server, $database_user, $database_password);
if(!$mysqli) exit($_lang['alert_database_test_connection_failed']);

$mysqli->select_db($dbase);
$mysqli->query("SET CHARACTER SET 'utf8'");
if (function_exists('mysqli_set_charset'))
{
	$mysqli->set_charset('utf8');
}
else
{
	$mysqli->query("SET NAMES 'utf8'");
}

// setup Template template files - array : name, description, type - 0:file or 1:content, parameters, category
if($installmode==0 && is_dir($templatePath) && is_readable($templatePath))
{
	$files = collectTpls($templatePath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && (count($params)>0))
		{
			if($installmode==1 && compare_check($params)==='same') continue;
			elseif(!empty($params['version']))
				$params['description'] = "<strong>{$params['version']}</strong> {$params['description']}";
			$p = array();
			$p['templatename']  = $params['name'];
			$p['description']   = $params['description'];
			$p['type']          = $params['type']; // Don't think this is gonna be used ... but adding it just in case 'type'
			$p['tpl_file_path'] = $tplfile;
			$p['category']      = $params['modx_category'];
			$p['locked']        = $params['lock_template'];
			$p['installset']    = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false;
			$tplTemplates[] = $p;
		}
	}
}

// setup Template Variable template files
if($installmode==0 && is_dir($tvPath) && is_readable($tvPath))
{
	$files = collectTpls($tvPath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && (count($params)>0))
		{
			if($installmode==1 && compare_check($params)=='same') continue;
			if(!empty($params['version'])) $params['description'] = "<strong>{$params['version']}</strong> {$params['description']}";
			$p = array();
			$p['name']                 = $params['name'];
			$p['caption']              = $params['caption'];
			$p['description']          = $params['description'];
			$p['input_type']           = $params['input_type'];
			$p['elements']             = $params['input_options'];
			$p['default_text']         = $params['input_default'];
			$p['display']              = $params['output_widget'];
			$p['display_params']       = $params['output_widget_params'];
			$p['tpl_file_path']        = $tplfile; /* not currently used */
			$p['template_assignments'] = $params['template_assignments']; /* comma-separated list of template names */
			$p['category']             = $params['modx_category'];
			$p['locked']               = $params['lock_tv'];  /* value should be 1 or 0 */
			$p['installset'] = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false;
            $tplTVs[] = $p;
		}
	}
}

// setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
if($installmode==0 && is_dir($chunkPath) && is_readable($chunkPath))
{
	$files = collectTpls($chunkPath);
	foreach ($files as $tpl_file_path)
	{
		$params = parse_docblock($tpl_file_path);
		if(is_array($params) && count($params) > 0)
		{
			if($installmode==1 && compare_check($params)=='same') continue;
			$p = array();
			$p['name']          = $params['name'];
			$p['description']   = $params['description'];
			$p['tpl_file_path'] = $tpl_file_path;
			$p['category']      = $params['modx_category'];
			$p['overwrite']     = array_key_exists('overwrite', $params) ? $params['overwrite'] : 'true';
			$p['installset']    = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false;
			$tplChunks[] = $p;
		}
	}
}

// setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
if(is_dir($snippetPath) && is_readable($snippetPath))
{
	$files = collectTpls($snippetPath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && count($params) > 0)
		{
			if($installmode==1 && compare_check($params)=='same') continue;
			if(!empty($params['version'])) $params['description'] = "<strong>{$params['version']}</strong> {$params['description']}";
			$p = array();
		    $p['name']        = $params['name'];
		    $p['description'] = $params['description'];
		    $p['tpl_file_path']    = $tplfile;
		    $p['properties']  = $params['properties'];
		    $p['category']    = $params['modx_category'];
		    $p['installset']  = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false;
			$tplSnippets[] = $p;
		}
	}
}

// setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
if(is_dir($pluginPath) && is_readable($pluginPath))
{
	$files = collectTpls($pluginPath);
	foreach ($files as $tplfile)
	{
		if(strpos($tplfile,'/mgr_custom/')!==false) continue; //Ignore
		
		$params = parse_docblock($tplfile);
		if(is_array($params) && 0 < count($params))
		{
		
			if(!empty($params['version'])) $params['description'] = "<strong>{$params['version']}</strong> {$params['description']}";
			
			if($installmode==1 && compare_check($params)=='same') continue;
			$p['name']          = $params['name'];
			$p['description']   = $params['description'];
			$p['tpl_file_path'] = $tplfile;
			$p['properties']    = $params['properties'];
			$p['events']        = $params['events'];
			$p['guid']          = $params['guid'];
			$p['category']      = $params['modx_category'];
			$p['legacy_names']  = $params['legacy_names'];
			$p['disabled']      = isset($params['disabled']) ? $params['disabled'] : '0';
			$p['installset']    = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false;
			$tplPlugins[] = $p;
		}
	}
}

// setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
if(is_dir($modulePath) && is_readable($modulePath))
{
	$files = collectTpls($modulePath);
	foreach ($files as $tplfile)
	{
		$params = parse_docblock($tplfile);
		if(is_array($params) && count($params) > 0)
		{
			if(!empty($params['version'])) $params['description'] = "<strong>{$params['version']}</strong> {$params['description']}";
			
			if($installmode==1 && compare_check($params)=='same') continue;
			$p = array();
		    $p['name']          = $params['name'];
		    $p['description']   = $params['description'];
		    $p['tpl_file_path'] = $tplfile;
		    $p['properties']    = $params['properties'];
		    $p['guid']          = $params['guid'];
		    $p['shareparams']   = intval($params['shareparams']);
		    $p['category']      = $params['modx_category'];
		    $p['installset']    = array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : array();
			$tplModules[] = $p;
		}
	}
}

// setup callback function
return 'clean_up';
