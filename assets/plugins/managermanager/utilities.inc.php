<?php


//-----------------------------------------------------------------------
//   Utility functions
// 
//----------------------------------------------------------------------- 


// Pass useThisRule a comma separated list of allowed roles and templates, and it will
// return TRUE or FALSE to indicate whether this rule should be run on this page
function useThisRule($roles='', $templates='') {

	global $mm_current_page, $modx;
	$e = &$modx->event;
	
	$exclude_roles = false;
	$exclude_templates = false;
	
	// Are they negative roles?
	if (substr($roles, 0, 1) === '!') {
		$roles = substr($roles, 1);
		$exclude_roles = true;
	}
	
	// Are they negative templates?
	if (substr($templates, 0, 1) === '!') {
		$templates = substr($templates, 1);
		$exclude_templates = true;
	}
	
	// Make the lists into arrays
	$roles     = makeArray($roles);
	$templates = makeArray($templates);
	
	// Does the current role match the conditions supplied?
	$match_role_list = ($exclude_roles) ? !in_array($mm_current_page['role'], $roles) : in_array($mm_current_page['role'], $roles);

	// Does the current template match the conditions supplied?
	$match_template_list = ($exclude_templates) ? !in_array($mm_current_page['template'], $templates) : in_array($mm_current_page['template'], $templates);
	
	// If we've matched either list in any way, return true	
	if ( ($match_role_list || count($roles)==0) && ($match_template_list || count($templates)==0) ) {
		return true;
	}

	return false;
}

// Makes a commas separated list into an array
function makeArray($csv) {
	
	// If we've already been supplied an array, just return it
	if (is_array($csv)) {
		return $csv;
	}	
	
	// Else if we have an empty string
	if (trim($csv)=='') {
		return array();
	}
	
	// Otherwise, turn it into an array
	$return = explode(',',$csv);
	array_walk( $return, create_function('$v, $k', 'return trim($v);'));	// Remove any whitespace
	return $return;
}

// Make an output JS safe
function jsSafe($str) {
	global $modx;
	
	return htmlentities($str, ENT_QUOTES, $modx->config['modx_charset'], false);
}

// Does the specified template use the specified TVs?
// $tpl_id = Template ID (int)
// $tvs = TV names - either array or comma separated list
// $types = TV types - e.g. image
function tplUseTvs($tpl_id, $tvs='', $types='') {
	
	// If it's a blank template, it can't have TVs
	if($tpl_id == 0) {
		return false;
	}
	
	global $modx;
	
	// Make the TVs and field types into an array
	$fields = makeArray($tvs); 
	$types  = makeArray($types);
	
	// Get the DB table names
	$from = array('[+prefix+]site_tmplvars tvs');
	$from[] = 'LEFT JOIN [+prefix+]site_tmplvar_templates rel ON rel.tmplvarid = tvs.id';
	
	$where = array();
	if ($tpl_id) {
		$where[] = sprintf('rel.templateid=%s', $tpl_id);
	}
	if ($fields) {
		$where[] = sprintf('tvs.name IN %s', makeSqlList($fields));
	}
	if ($types) {
		$where[] = sprintf('type IN %s', makeSqlList($types));
	}
	if($where) {
		$where = join(' AND ', $where);
	}
	
	// Do the SQL query	
	$result = $modx->db->select('id', $from, $where);
	
	if ( !$modx->db->getRecordCount($result)) {
		return false;	
	}
	return $modx->db->makeArray($result);
}

// Create a MySQL-safe list from an array
function makeSqlList($arr) {
	global $modx;
	$arr = makeArray($arr);
	foreach($arr as $k=>$tv) {
		$arr[$k] = sprintf("'%s'", $modx->db->escape($tv)); // Escape them for MySQL
	}
	return sprintf(' (%s) ', implode(',',$arr));
}

// Generates the code needed to include an external script file. 
// $url is the URL of the external script
// $output_type is either js or html - depending on where the output is appearing
function includeJs($url, $output_type='js') {
	
	if ($output_type === 'js') {
		return 'jQuery("head").append(\' <script src="'.$url.'" type="text/javascript"></scr\'+\'ipt> \'); ' . "\n";
	}
	if ($output_type === 'html') {
		return '<script src="'.$url.'" type="text/javascript"></script>' . "\n";
	}
	return '';
}

// Generates the code needed to include an external CSS file. 
// $url is any URL
// $output_type is either js or html - depending on where the output is appearing
function includeCss($url, $output_type='js') {
	if ($output_type === 'js') {
		return  '$j("head").append(\' <link href="'.$url.'" rel="stylesheet" type="text/css" /> \'); ' . "\n";	
	}
	if ($output_type === 'html') {
		return  '<link href="'.$url.'" rel="stylesheet" type="text/css" />' . "\n";	
	}
    return '';
}
