<?php
if(($_SESSION['prevAction']==='connection'||$_SESSION['prevAction']==='mode') && !isset($_SESSION['installdata']))
	$selDefault = 'all';
else $selDefault = false;

if(isset($_POST['chkagree'])) $_SESSION['chkagree'] = $_POST['chkagree'];

$installdata   = !isset($_SESSION['installdata']) ? false : $_SESSION['installdata'];
$formTemplates = !isset($_SESSION['template'])    ? false : $_SESSION['template'];
$formTvs       = !isset($_SESSION['tv'])          ? false : $_SESSION['tv'];
$formChunks    = !isset($_SESSION['chunk'])       ? false : $_SESSION['chunk'];
$formModules   = !isset($_SESSION['module'])      ? false : $_SESSION['module'];
$formPlugins   = !isset($_SESSION['plugin'])      ? false : $_SESSION['plugin'];
$formSnippets  = !isset($_SESSION['snippet'])     ? false : $_SESSION['snippet'];

if(isset($_POST['adminname']))         $_SESSION['adminname']         = $_POST['adminname'];
if(isset($_POST['adminemail']))        $_SESSION['adminemail']        = $_POST['adminemail'];
if(isset($_POST['adminpass']))         $_SESSION['adminpass']         = $_POST['adminpass'];
if(isset($_POST['adminpassconfirm']))  $_SESSION['adminpassconfirm']  = $_POST['adminpassconfirm'];

$_SESSION['managerlanguage'] = $_SESSION['install_language'];
include_once("{$installer_path}setup.info.php");

$ph['installmode'] = $_SESSION['installmode'];

$ph['install_sample_site']     = $_SESSION['installmode']==0 ? block_install_sample_site($installdata,$ph) . "\n" : '';
$ph['block_templates'] = block_templates($tplTemplates,$formTemplates,$ph);
$ph['block_tvs']       = block_tvs(      $tplTVs,      $formTvs,      $ph);
$ph['block_chunks']    = block_chunks(   $tplChunks,   $formChunks,   $ph);
$ph['block_modules']   = block_modules(  $tplModules,  $formModules,  $ph);
$ph['block_plugins']   = block_plugins(  $tplPlugins,  $formPlugins,  $ph);
$ph['block_snippets']  = block_snippets( $tplSnippets, $formSnippets, $ph);

$ph['object_list'] = object_list($ph) . "\n";

$tpl = file_get_contents("{$base_path}install/tpl/options.tpl");
echo  $modx->parseText($tpl,$ph);



function object_list($ph) {
	global $modx;
	
	$elms = array($ph['block_templates'],$ph['block_tvs'],$ph['block_chunks'],$ph['block_modules'],$ph['block_plugins'],$ph['block_snippets']);
	$objects = join("\n", $elms);
	if(trim($objects)==='') $tpl = '<strong>[+no_update_options+]</strong>';
	else {
		$ph['objects'] = $objects;
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
    	}
	return $modx->parseText($tpl,$ph);
}

function block_install_sample_site($installdata='', $ph) {
	global $modx;
	
	$ph['checked'] = $installdata==1 ? 'checked' : '';
	$tpl = <<< TPL
<img src="img/sample_site.png" class="options" alt="Sample Data" />
<h3>[+sample_web_site+]</h3>
<p>
<input type="checkbox" name="installdata" id="installdata_field" value="1" [+checked+] />&nbsp;
<label for="installdata_field">[+install_overwrite+] <span class="comname">[+sample_web_site+]</span></label>
</p>
<p><em>&nbsp;[+sample_web_site_note+]</em></p>
TPL;
	return $modx->parseText($tpl,$ph);
}

function block_templates($tplTemplates,$formTemplates,$ph) {
	global $modx,$selDefault;
	
	if(count($tplTemplates) === 0) return '';
	
    $tpl = '<label><input type="checkbox" name="template[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplTemplates as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formTemplates) && in_array($i, $formTemplates)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['templatename'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+templates+]</h3>\n" . join("\n", $_);
    else           $tpl = '';
    return $modx->parseText($tpl,$ph);
}

function block_tvs($tplTVs,$formTvs,$ph) {
	global $modx,$selDefault;
	
    if (count($tplTVs) === 0) return '';
    
    $tpl = '<label><input type="checkbox" name="tv[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplTVs as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formTvs) && in_array($i, $formTvs)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['name'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+tvs+]</h3>\n" . join("\n", $_);
    else $tpl = '';
    return $modx->parseText($tpl,$ph);
}

function block_chunks($tplChunks,$formChunks,$ph) {
	global $modx,$selDefault;
	
    if (count($tplChunks) === 0) return '';
    
	$tpl = '<label><input type="checkbox" name="chunk[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplChunks as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formChunks) && in_array($i, $formChunks)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['name'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+chunks+]</h3>\n" . join("\n", $_);
    else $tpl = '';
    return $modx->parseText($tpl,$ph);
}

function block_modules($tplModules,$formModules,$ph) {
	global $modx,$selDefault;
	
    if (count($tplModules) === 0) return '';
    
	$tpl = '<label><input type="checkbox" name="module[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplModules as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formModules) && in_array($i, $formModules)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['name'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+modules+]</h3>\n" . join("\n", $_);
    else $tpl = '';
    return $modx->parseText($tpl,$ph);
}

function block_plugins($tplPlugins, $formPlugins, $ph) {
	global $modx,$selDefault;
	
    if (count($tplPlugins) === 0) return '';
    
    $tpl = '<label><input type="checkbox" name="plugin[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplPlugins as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formPlugins) && in_array($i, $formPlugins)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['name'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+plugins+]</h3>\n" . join("\n", $_);
    else           $tpl = '';
    return $modx->parseText($tpl,$ph);
}

function block_snippets($tplSnippets,$formSnippets,$ph) {
	global $modx,$selDefault;
	
    if (count($tplSnippets) === 0) return '';
    
    $tpl = '<label><input type="checkbox" name="snippet[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label><br />';
    foreach($tplSnippets as $i=>$v) {
        $class = is_array($v['installset']) && !in_array('sample', $v['installset']) ? 'toggle' : 'toggle demo';
        $checked = ($selDefault==='all' || is_array($formSnippets) && in_array($i, $formSnippets)) ? 'checked':'';
        $_[] = sprintf($tpl, $i, $class, $checked, $v['name'], $v['description']);
    }
    if(!empty($_)) $tpl = "<h3>[+snippets+]</h3>\n" . join("\n", $_);
    else $tpl = '';
    return $modx->parseText($tpl,$ph);
}
