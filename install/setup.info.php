<?php
//:: MODx Installer Setup file 
//:::::::::::::::::::::::::::::::::::::::::
require_once('../manager/includes/version.inc.php');

$moduleName = "MODx";
$moduleVersion = $modx_branch.' '.$modx_version;
$moduleRelease = $modx_release_date;
$moduleSQLBaseFile = "setup.sql";
$moduleSQLDataFile = "setup.data.sql";
$chunkPath = $setupPath .'/assets/chunks';
$snippetPath = $setupPath .'/assets/snippets';
$pluginPath = $setupPath .'/assets/plugins';
$modulePath = $setupPath .'/assets/modules';
$templatePath = $setupPath .'/assets/templates';
$tvPath = $setupPath .'/assets/tvs';

@ $conn = mysql_connect($database_server, $database_user, $database_password);
if (function_exists('mysql_set_charset'))
{
	mysql_set_charset($database_connection_charset);
}
@ mysql_select_db(trim($dbase, '`'), $conn);

// setup Template template files - array : name, description, type - 0:file or 1:content, parameters, category
$mt = &$moduleTemplates;
if(is_dir($templatePath) && is_readable($templatePath)) {
		$d = dir($templatePath);
		while (false !== ($tplfile = $d->read()))
		{
			if(substr($tplfile, -4) != '.tpl') continue;
			$params = parse_docblock($templatePath, $tplfile);
			if(is_array($params) && (count($params)>0))
			{
				$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
				
				if($installMode===1 && compare_check($params)=='same') continue;
					
				$mt[] = array
				(
					$params['name'],
					$description,
					// Don't think this is gonna be used ... but adding it just in case 'type'
					$params['type'],
					"$templatePath/{$params['filename']}",
					$params['modx_category'],
					$params['lock_template'],
					array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
				);
			}
		}
		$d->close();
}

// setup Template Variable template files
$mtv = &$moduleTVs;
if(is_dir($tvPath) && is_readable($tvPath)) {
		$d = dir($tvPath);
    while (false !== ($tplfile = $d->read())) {
			if(substr($tplfile, -4) != '.tpl') continue;
			$params = parse_docblock($tvPath, $tplfile);
        if(is_array($params) && (count($params)>0)) {
				$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
				
				if($installMode===1 && compare_check($params)=='same') continue;
					
            $mtv[] = array(
					$params['name'],
					$params['caption'],
					$description,
					$params['input_type'],
					$params['input_options'],
					$params['input_default'],
					$params['output_widget'],
					$params['output_widget_params'],
					"$templatePath/{$params['filename']}", /* not currently used */
					$params['template_assignments'], /* comma-separated list of template names */
					$params['modx_category'],
                $params['lock_tv'],  /* value should be 1 or 0 */
                array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
				);
			}
		}
		$d->close();
}

// setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
$mc = &$moduleChunks;
if(is_dir($chunkPath) && is_readable($chunkPath)) {
		$d = dir($chunkPath);
		while (false !== ($tplfile = $d->read())) {
			if(substr($tplfile, -4) != '.tpl') {
				continue;
			}
			$params = parse_docblock($chunkPath, $tplfile);
			if(is_array($params) && count($params) > 0) {
			
				if($installMode===1 && compare_check($params)=='same') continue;
				
            $mc[] = array(
                $params['name'],
                $params['description'],
                "$chunkPath/{$params['filename']}",
                $params['modx_category'],
                array_key_exists('overwrite', $params) ? $params['overwrite'] : 'true',
                array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
            );
			}
		}
		$d->close();
}

// setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
$ms = &$moduleSnippets;
if(is_dir($snippetPath) && is_readable($snippetPath)) {
		$d = dir($snippetPath);
		while (false !== ($tplfile = $d->read())) {
			if(substr($tplfile, -4) != '.tpl') {
				continue;
			}
			$params = parse_docblock($snippetPath, $tplfile);
			if(is_array($params) && count($params) > 0) {
				$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
				
				if($installMode===1 && compare_check($params)=='same') continue;
				
            $ms[] = array(
                $params['name'],
                $description,
                "$snippetPath/{$params['filename']}",
                $params['properties'],
                $params['modx_category'],
                array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
            );
			}
		}
		$d->close();
}

// setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
$mp = &$modulePlugins;
if(is_dir($pluginPath) && is_readable($pluginPath))
{
	$d = dir($pluginPath);
	while (false !== ($tplfile = $d->read()))
	{
		if(substr($tplfile, -4) != '.tpl')
		{
			continue;
		}
		$params = parse_docblock($pluginPath, $tplfile);
		if(is_array($params) && 0 < count($params))
		{
		
			if(!empty($params['version'])) $description = "<strong>{$params['version']}</strong> {$params['description']}";
			else                           $description = $params['description'];
			
			if($installMode===1 && compare_check($params)=='same') continue;
		
			$mp[] = array(
				$params['name'],
				$description,
				"$pluginPath/{$params['filename']}",
				$params['properties'],
				$params['events'],
				$params['guid'],
				$params['modx_category'],
				$params['legacy_names'],
				array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
			);
		}
	}
	$d->close();
}

// setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
$mm = &$moduleModules;
if(is_dir($modulePath) && is_readable($modulePath)) {
		$d = dir($modulePath);
		while (false !== ($tplfile = $d->read())) {
			if(substr($tplfile, -4) != '.tpl') {
				continue;
			}
			$params = parse_docblock($modulePath, $tplfile);
			if(is_array($params) && count($params) > 0) {
				$description = empty($params['version']) ? $params['description'] : "<strong>{$params['version']}</strong> {$params['description']}";
				
				if($installMode===1 && compare_check($params)=='same') continue;
				
            $mm[] = array(
                $params['name'],
                $description,
                "$modulePath/{$params['filename']}",
                $params['properties'],
                $params['guid'],
                intval($params['shareparams']),
                $params['modx_category'],
                array_key_exists('installset', $params) ? preg_split("/\s*,\s*/", $params['installset']) : false
            );
			}
		}
		$d->close();
}

// setup callback function
$callBackFnc = "clean_up";
	
function clean_up($sqlParser) {
		$ids = array();
		$mysqlVerOk = -1;

		if(function_exists("mysql_get_server_info")) {
			$mysqlVerOk = (version_compare(mysql_get_server_info(),"4.0.20")>=0);
		}	
		
		// secure web documents - privateweb 
		mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privateweb = 0 WHERE privateweb = 1",$sqlParser->conn);
		$sql =  "SELECT DISTINCT sc.id 
				 FROM `".$sqlParser->prefix."site_content` sc
				 LEFT JOIN `".$sqlParser->prefix."document_groups` dg ON dg.document = sc.id
				 LEFT JOIN `".$sqlParser->prefix."webgroup_access` wga ON wga.documentgroup = dg.document_group
				 WHERE wga.id>0";
		$ds = mysql_query($sql,$sqlParser->conn);
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			while($r = mysql_fetch_assoc($ds)) $ids[]=$r["id"];
			if(count($ids)>0) {
				mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privateweb = 1 WHERE id IN (".implode(", ",$ids).")");	
				unset($ids);
			}
		}
		
		// secure manager documents privatemgr
		mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privatemgr = 0 WHERE privatemgr = 1");
		$sql =  "SELECT DISTINCT sc.id 
				 FROM `".$sqlParser->prefix."site_content` sc
				 LEFT JOIN `".$sqlParser->prefix."document_groups` dg ON dg.document = sc.id
				 LEFT JOIN `".$sqlParser->prefix."membergroup_access` mga ON mga.documentgroup = dg.document_group
				 WHERE mga.id>0";
		$ds = mysql_query($sql);
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			while($r = mysql_fetch_assoc($ds)) $ids[]=$r["id"];
			if(count($ids)>0) {
				mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privatemgr = 1 WHERE id IN (".implode(", ",$ids).")");	
				unset($ids);
			}		
		}

		/**** Add Quick Plugin to Module 
		// get quick edit module id
		$ds = mysql_query("SELECT id FROM `".$sqlParser->prefix."site_modules` WHERE name='QuickEdit'");
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			$row = mysql_fetch_assoc($ds);
			$moduleid=$row["id"];
		}		
		// get plugin id
		$ds = mysql_query("SELECT id FROM `".$sqlParser->prefix."site_plugins` WHERE name='QuickEdit'");
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			$row = mysql_fetch_assoc($ds);
			$pluginid=$row["id"];
		}		
		// setup plugin as module dependency
		$ds = mysql_query("SELECT module FROM `".$sqlParser->prefix."site_module_depobj` WHERE module='$moduleid' AND resource='$pluginid' AND type='30' LIMIT 1"); 
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		elseif (mysql_num_rows($ds)==0){
			mysql_query("INSERT INTO `".$sqlParser->prefix."site_module_depobj` (module, resource, type) VALUES('$moduleid','$pluginid',30)");
		}
		***/
}

function parse_docblock($element_dir, $filename)
{
	$params = array();
	$fullpath = $element_dir . '/' . $filename;
	if(is_readable($fullpath))
	{
		$tpl = @fopen($fullpath, 'r');
		if($tpl)
		{
			$params['filename'] = $filename;
			$docblock_start_found = false;
			$name_found = false;
			$description_found = false;
			$docblock_end_found = false;
			
			while(!feof($tpl))
			{
				$line = fgets($tpl);
				if(!$docblock_start_found)
				{
					// find docblock start
					if(strpos($line, '/**') !== false)
					{
						$docblock_start_found = true;
					}
					continue;
				}
				elseif(!$name_found)
				{
					// find name
					$ma = null;
					if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
					{
						$params['name'] = trim($ma[1]);
						$name_found = !empty($params['name']);
					}
					continue;
				}
				elseif(!$description_found)
				{
					// find description
					$ma = null;
					if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
					{
						$params['description'] = trim($ma[1]);
						$description_found = !empty($params['description']);
					}
					continue;
				}
				else
				{
					$ma = null;
					if(preg_match("/^\s+\*\s+\@([^\s]+)\s+(.+)/", $line, $ma))
					{
						$param = trim($ma[1]);
						$val = trim($ma[2]);
						if(!empty($param) && !empty($val))
						{
							if($param == 'internal')
							{
								$ma = null;
								if(preg_match("/\@([^\s]+)\s+(.+)/", $val, $ma))
								{
									$param = trim($ma[1]);
									$val = trim($ma[2]);
								}
								//if($val !== '0' && (empty($param) || empty($val))) {
								if(empty($param))
								{
									continue;
								}
							}
							$params[$param] = $val;
						}
					}
					elseif(preg_match("/^\s*\*\/\s*$/", $line))
					{
						$docblock_end_found = true;
						break;
					}
				}
			}
			@fclose($tpl);
		}
	}
	return $params;
}

if(!function_exists('modx_escape'))
{
	function modx_escape($s)
	{
		global $database_connection_charset;
		if (function_exists('mysql_set_charset'))
		{
			$s = mysql_real_escape_string($s);
		}
		elseif ($database_connection_charset=='utf8')
		{
			$s = mb_convert_encoding($s, 'eucjp-win', 'utf-8');
			$s = mysql_real_escape_string($s);
			$s = mb_convert_encoding($s, 'utf-8', 'eucjp-win');
		}
		else
		{
			$s = mysql_escape_string($s);
		}
		return $s;
	}
}

function compare_check($params)
{
	global $table_prefix;
	
	$name_field  = 'name';
	$name        = $params['name'];
	$mode        = 'version_compare';
	if($params['version'])
	{
		$new_version = $params['version'];
	}
	//print_r($params);
	switch($params['category'])
	{
		case 'template':
			$table = $table_prefix . 'site_templates';
			$name_field = 'templatename';
			$mode       = 'desc_compare';
			break;
		case 'tv':
			$table = $table_prefix . 'site_tmplvars';
			$mode  = 'desc_compare';
			break;
		case 'chunk':
			$table = $table_prefix . 'site_htmlsnippets';
			$mode  = 'desc_compare';
			break;
		case 'snippet':
			$table = $table_prefix . 'site_snippets';
			break;
		case 'plugin':
			$table = $table_prefix . 'site_plugins';
			break;
		case 'module':
			$table = $table_prefix . 'site_modules';
			break;
	}
	$sql = "SELECT * FROM `{$table}` WHERE `{$name_field}`='{$name}'";
	$rs = mysql_query($sql);
	if(!$rs) echo "An error occurred while executing a query: ".mysql_error();
	else     $row = mysql_fetch_assoc($rs);
	$count = mysql_num_rows($rs);
	
	if($count===1)
	{
		$new_desc    = $params['description'];
		$old_desc    = modx_escape($row['description']);
		$old_version = substr($old_desc,0,strpos($old_desc,'</strong>'));
		$old_version = strip_tags($old_version);
/* debug
echo '<br /><b>' . $name . '</b><br />';
echo 'new-' . $new_desc . '<br />';
echo 'old-' . $old_desc . '<br />';
echo 'new-' . $new_version . '<br />';
echo 'old-' . $old_version . '<br />';
*/
		if($mode == 'version_compare' && $old_version === $new_version)
		{
			                            $result = 'same';
		}
		elseif($old_desc === $new_desc) $result = 'same';
		else                            $result = 'diff';
	}
	elseif($count < 1)                  $result = 'no exists';
	
	return $result;
}

