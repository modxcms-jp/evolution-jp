<?php

global $tplTemplates, $tplTVs, $tplChunks, $tplModules, $tplPlugins, $tplSnippets;

if(in_array($_SESSION['prevAction'], array('connection', 'mode')) && !isset($_SESSION['installdata'])) {
    $selDefault = 'all';
} else {
    $selDefault = false;
}

if(isset($_POST['chkagree'])) {
    $_SESSION['chkagree'] = $_POST['chkagree'];
}

$installdata   = !isset($_SESSION['installdata']) ? false : $_SESSION['installdata'];
$formTemplates = !isset($_SESSION['template'])    ? false : $_SESSION['template'];
$formTvs       = !isset($_SESSION['tv'])          ? false : $_SESSION['tv'];
$formChunks    = !isset($_SESSION['chunk'])       ? false : $_SESSION['chunk'];
$formModules   = !isset($_SESSION['module'])      ? false : $_SESSION['module'];
$formPlugins   = !isset($_SESSION['plugin'])      ? false : $_SESSION['plugin'];
$formSnippets  = !isset($_SESSION['snippet'])     ? false : $_SESSION['snippet'];

if(isset($_POST['adminname'])) {
    $_SESSION['adminname'] = $_POST['adminname'];
}
if(isset($_POST['adminemail'])) {
    $_SESSION['adminemail'] = $_POST['adminemail'];
}
if(isset($_POST['adminpass'])) {
    $_SESSION['adminpass'] = $_POST['adminpass'];
}
if(isset($_POST['adminpassconfirm'])) {
    $_SESSION['adminpassconfirm'] = $_POST['adminpassconfirm'];
}

$_SESSION['managerlanguage'] = $_SESSION['install_language'];
include_once $installer_path . 'setup.info.php';

$ph['installmode'] = $_SESSION['installmode'];

if ($_SESSION['installmode'] == 0) {
    $ph['install_sample_site'] = block_install_sample_site($installdata, $ph) . "\n";
} else {
    $ph['install_sample_site'] = '';
}
$ph['block_templates'] = block_templates($tplTemplates,$formTemplates,$ph);
$ph['block_tvs']       = block_tvs(      $tplTVs,      $formTvs,      $ph);
$ph['block_chunks']    = block_chunks(   $tplChunks,   $formChunks,   $ph);
$ph['block_modules']   = block_modules(  $tplModules,  $formModules,  $ph);
$ph['block_plugins']   = block_plugins(  $tplPlugins,  $formPlugins,  $ph);
$ph['block_snippets']  = block_snippets( $tplSnippets, $formSnippets, $ph);

$ph['object_list'] = show_object_list($ph) . "\n";

echo  $modx->parseText(
    file_get_contents($base_path . 'install/tpl/options.tpl')
    ,$ph
);



function show_object_list($ph) {
	global $modx;
	
	$objects = join(
        "\n"
        , array($ph['block_templates'],$ph['block_tvs'],$ph['block_chunks'],$ph['block_modules'],$ph['block_plugins'],$ph['block_snippets']));
	if(trim($objects)==='') {
        return $modx->parseText('<strong>[+no_update_options+]</strong>',$ph);
    }

    $tpl = <<< TPL
<p>[+optional_items_upd_note+]</p>
<p class="actions">
    <a id="toggle_check_all" href="javascript:void(0);">[+all+]</a>
    <a id="toggle_check_none" href="javascript:void(0);">[+none+]</a>
    <a id="toggle_check_toggle" href="javascript:void(0);">[+toggle+]</a>
</p>
<div id="installChoices">
[+objects+]
</div>
TPL;
    $ph['objects'] = $objects;
    return $modx->parseText($tpl,$ph);
}

function block_install_sample_site($installdata='', $ph) {
	global $modx;
	
	$tpl = <<< TPL
<img src="img/sample_site.png" class="options" alt="Sample Data" />
<h3>[+sample_web_site+]</h3>
<p>
<input type="checkbox" name="installdata" id="installdata_field" value="1" [+checked+] />&nbsp;
<label for="installdata_field">[+install_overwrite+] <span class="comname">[+sample_web_site+]</span></label>
</p>
<p><em>&nbsp;[+sample_web_site_note+]</em></p>
TPL;
    $ph['checked'] = $installdata==1 ? 'checked' : '';
	return $modx->parseText($tpl,$ph);
}

function block_templates($tplTemplates,$formTemplates,$ph) {
	global $modx,$selDefault;
	
	if(!$tplTemplates) {
        return '';
    }

    $_ = array('<h3>[+templates+]</h3>');
    foreach($tplTemplates as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="template[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formTemplates, $i, $selDefault) ? 'checked':''
            , $v['templatename']
            , $v['description']
        );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function block_tvs($tplTVs,$formTvs,$ph) {
	global $modx,$selDefault;
	
    if (!$tplTVs) {
        return '';
    }

    $_ = array('<h3>[+tvs+]</h3>');
    foreach($tplTVs as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="tv[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formTvs, $i, $selDefault) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function block_chunks($tplChunks,$formChunks,$ph) {
	global $modx,$selDefault;
	
    if (!$tplChunks) {
        return '';
    }

    $_ = array('<h3>[+chunks+]</h3>');
    foreach($tplChunks as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="chunk[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formChunks, $i, $selDefault) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function block_modules($tplModules,$formModules,$ph) {
	global $modx,$selDefault;
	
    if (!$tplModules) {
        return '';
    }

    $_ = array('<h3>[+modules+]</h3>');
    foreach($tplModules as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="module[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formModules, $i, $selDefault) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function block_plugins($tplPlugins, $formPlugins, $ph) {
	global $modx,$selDefault;
	
    if (!$tplPlugins) {
        return '';
    }

    $_ = array('<h3>[+plugins+]</h3>');
    foreach($tplPlugins as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="plugin[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formPlugins, $i, $selDefault) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function block_snippets($tplSnippets,$formSnippets,$ph) {
	global $modx,$selDefault;
	
    if (!$tplSnippets) {
        return '';
    }
    $_ = array('<h3>[+snippets+]</h3>');
    foreach($tplSnippets as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="snippet[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check($formSnippets, $i, $selDefault) ? 'checked':''
            , $v['name']
            , $v['description']
            );
    }
    return $modx->parseText(join("<br />\n", $_), $ph);
}

function is_demo($option) {
    if(!isset($option['installset'])) {
        return false;
    }
    return is_array($option['installset']) && !in_array('sample', $option['installset'], true);
}

function is_check($elements, $num, $selDefault) {
    if($selDefault==='all') {
        return true;
    }
    if(!is_array($elements) || !$elements) {
        return false;
    }
    return in_array($num, $elements);
}