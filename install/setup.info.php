<?php

// setup Template template files - array : name, description, type - 0:file or 1:content, parameters, category
$tplTemplates = array();
$templatePath = MODX_BASE_PATH . 'assets/templates/';
if(!sessionv('is_upgradeable') && is_dir($templatePath) && is_readable($templatePath)) {
	$files = collectTpls($templatePath);
	foreach ($files as $tplfile) {
		$params = parse_docblock($tplfile);
		if(!is_array($params) || !$params) {
            continue;
        }
        if($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplTemplates[] = array(
            'templatename'    => $params['name']
            , 'description'   => $params['description']
            // Don't think this is gonna be used ... but adding it just in case 'type'
            , 'type'          => $params['type']
            , 'tpl_file_path' => $tplfile
            , 'category'      => $params['modx_category']
            , 'locked'        => $params['lock_template']
            , 'installset'    => get_installset($params)
        );
    }
}

// setup Template Variable template files
$tplTVs = array();
$tvPath = MODX_BASE_PATH . 'assets/tvs/';
if(!sessionv('is_upgradeable') && is_dir($tvPath) && is_readable($tvPath)) {
	$files = collectTpls($tvPath);
	foreach ($files as $tplfile) {
		$params = parse_docblock($tplfile);
        if(!is_array($params) || !$params) {
            continue;
        }
        if($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplTVs[] = array(
            'name'             => $params['name']
            , 'caption'        => $params['caption']
            , 'description'    => $params['description']
            , 'input_type'     => $params['input_type']
            , 'elements'       => $params['input_options']
            , 'default_text'   => $params['input_default']
            , 'display'        => $params['output_widget']
            , 'display_params' => $params['output_widget_params']
            , 'tpl_file_path'  => $tplfile /* not currently used */
            , 'category'       => $params['modx_category']
            , 'locked'         => $params['lock_tv']  /* value should be 1 or 0 */
            , 'installset'     => get_installset($params)
            , 'template_assignments' => $params['template_assignments'] //comma-separated list of template names
        );
	}
}

// setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
$tplChunks = array();
$chunkPath    = MODX_BASE_PATH . "assets/chunks/";
if(!sessionv('is_upgradeable') && is_dir($chunkPath) && is_readable($chunkPath)) {
	$files = collectTpls($chunkPath);
	foreach ($files as $tpl_file_path) {
		$params = parse_docblock($tpl_file_path);
        if(!is_array($params) || !$params) {
            continue;
        }
        $tplChunks[] = array(
            'name'            => $params['name']
            , 'description'   => $params['description']
            , 'tpl_file_path' => $tpl_file_path
            , 'category'      => $params['modx_category']
            , 'overwrite'     => array_key_exists('overwrite', $params) ? $params['overwrite'] : 'true'
            , 'installset'    => get_installset($params)
        );
	}
}

// setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
$tplSnippets = array();
$snippetPath  = MODX_BASE_PATH . 'assets/snippets/';
if(is_dir($snippetPath) && is_readable($snippetPath)) {
	$files = collectTpls($snippetPath);
	foreach ($files as $tplfile) {
		$params = parse_docblock($tplfile);
        if(!is_array($params) || !$params) {
            continue;
        }
        if(sessionv('is_upgradeable') && compare_check($params) === 'same') {
            continue;
        }
        if($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplSnippets[] = array(
            'name'            => $params['name']
            , 'description'   => $params['description']
            , 'tpl_file_path' => $tplfile
            , 'properties'    => $params['properties']
            , 'category'      => $params['modx_category']
            , 'installset'    => get_installset($params)
        );
	}
}

// setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
$tplPlugins = array();
$pluginPath   = MODX_BASE_PATH . 'assets/plugins/';
if(is_dir($pluginPath) && is_readable($pluginPath)) {
	$files = collectTpls($pluginPath);
	foreach ($files as $tplfile) {
		if(strpos($tplfile,'/mgr_custom/')!==false) {
            continue;
        } //Ignore
		
		$params = parse_docblock($tplfile);
        if(!is_array($params) || !$params) {
            continue;
        }
        if($_SESSION['is_upgradeable']==1 && compare_check($params) === 'same') {
            continue;
        }
        if($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplPlugins[] = array(
            'name'            => $params['name']
            , 'description'   => $params['description']
            , 'tpl_file_path' => $tplfile
            , 'properties'    => $params['properties']
            , 'events'        => $params['events']
            , 'guid'          => $params['guid']
            , 'category'      => $params['modx_category']
            , 'legacy_names'  => $params['legacy_names']
            , 'disabled'      => isset($params['disabled']) ? $params['disabled'] : '0'
            , 'installset'    => get_installset($params)
        );
	}
}

// setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
$tplModules = array();
$modulePath   = MODX_BASE_PATH . 'assets/modules/';
if(is_dir($modulePath) && is_readable($modulePath)) {
	$files = collectTpls($modulePath);
	foreach ($files as $tplfile) {
		$params = parse_docblock($tplfile);
        if(!is_array($params) || !$params) {
            continue;
        }
        if($_SESSION['is_upgradeable'] && compare_check($params) === 'same') {
            continue;
        }
        if($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplModules[] = array(
            'name'            => $params['name']
            , 'description'   => $params['description']
            , 'tpl_file_path' => $tplfile
            , 'properties'    => $params['properties']
            , 'guid'          => $params['guid']
            , 'shareparams'   => (int)$params['shareparams']
            , 'category'      => $params['modx_category']
            , 'installset'    => get_installset($params)
        );
	}
}

// setup callback function
return 'clean_up';

function get_installset($params) {
    if (array_key_exists('installset', $params)) {
        return preg_split("/\s*,\s*/", $params['installset']);
    }
    return array();
}

function add_version_strings($params) {
    return sprintf(
        '<strong>%s</strong> %s'
        , $params['version']
        , $params['description']
    );
}