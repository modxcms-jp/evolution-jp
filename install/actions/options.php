<?php
if(isset($_POST['chkagree'])) $_SESSION['chkagree'] = $_POST['chkagree'];

$installdata = !isset($_SESSION['installdata']) ? false : $_SESSION['installdata'];
$templates   = !isset($_SESSION['template'])    ? false : $_SESSION['template'];
$tvs         = !isset($_SESSION['tv'])          ? false : $_SESSION['tv'];
$chunks      = !isset($_SESSION['chunk'])       ? false : $_SESSION['chunk'];
$modules     = !isset($_SESSION['module'])      ? false : $_SESSION['module'];
$plugins     = !isset($_SESSION['plugin'])      ? false : $_SESSION['plugin'];
$snippets    = !isset($_SESSION['snippet'])     ? false : $_SESSION['snippet'];

$options_selected = !isset($_SESSION['options_selected']) ? false : $_SESSION['options_selected'];

if(isset($_POST['adminname']))         $_SESSION['adminname']         = $_POST['adminname'];
if(isset($_POST['adminemail']))        $_SESSION['adminemail']        = $_POST['adminemail'];
if(isset($_POST['adminpass']))         $_SESSION['adminpass']         = $_POST['adminpass'];
if(isset($_POST['adminpassconfirm']))  $_SESSION['adminpassconfirm']  = $_POST['adminpassconfirm'];

if(isset($_POST['installmode'])) $_SESSION['installmode'] = $_POST['installmode'];
$installmode = $_SESSION['installmode'];

$_SESSION['managerlanguage'] = $_SESSION['install_language'];
include_once("{$installer_path}setup.info.php");

$ph['installmode'] = $installmode;

$ph['optional_items_new_note'] = block_optional_items_new_note($installmode,$ph) . "\n";
$ph['install_sample_site'] = block_install_sample_site($installmode,$installdata,$ph) . "\n";
$ph['block_templates'] = block_templates($moduleTemplates,$templates,$options_selected,$ph) . "\n";
$ph['block_tvs']       = block_tvs(      $moduleTVs,      $tvs,      $options_selected,$ph) . "\n";
$ph['block_chunks']    = block_chunks(   $moduleChunks,   $chunks,   $options_selected,$ph) . "\n";
$ph['block_modules']   = block_modules(  $moduleModules,  $modules,  $options_selected,$ph) . "\n";
$ph['block_plugins']   = block_plugins(  $modulePlugins,  $plugins,  $options_selected,$ph) . "\n";
$ph['block_snippets']  = block_snippets( $moduleSnippets, $snippets, $options_selected,$ph) . "\n";

$ph['object_list'] = object_list($ph) . "\n";

$tpl = file_get_contents("{$base_path}install/tpl/options.tpl");
echo  parse($tpl,$ph);



function object_list($ph) {
	$objects = $ph['block_templates'].$ph['block_tvs'].$ph['block_chunks'].$ph['block_modules'].$ph['block_plugins'].$ph['block_snippets'];
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
	return parse($tpl,$ph);
}

function block_optional_items_new_note($installmode,$ph) {
	if($installmode === '0') $tpl = '<p>[+optional_items_new_note+]</p>';
	else $tpl = '';
	return parse($tpl,$ph);
}

function block_install_sample_site($installmode,$installdata='', $ph) {
	if($installmode != 0) return '';
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
	return parse($tpl,$ph);
}

function block_templates($moduleTemplates,$templates,$options_selected,$ph) {
	if(count($moduleTemplates) === 0) return '';
	else {
        $tpl = '<label><input type="checkbox" name="template[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+template0+]</span> - [+template1+]</label><br />';
        $ph2 = $ph;
        foreach($moduleTemplates as $i=>$template) {
        	$ph2['i'] = $i;
        	$ph2['template0'] = $template[0];
        	$ph2['template1'] = $template[1];
            $ph2['class'] = is_array($template[6]) && !in_array('sample', $template[6]) ? 'toggle' : 'toggle demo';
            if((is_array($templates) && in_array($i, $templates)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                                         $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+templates+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}

function block_tvs($moduleTVs,$tvs,$options_selected,$ph) {
    if (count($moduleTVs) === 0) return '';
	else {
		$tpl = '<label><input type="checkbox" name="tv[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+tv0+]</span> - [+tv2+]</label><br />';
		$ph2 = $ph;
        foreach($moduleTVs as $i=>$tv) {
        	$ph2['i'] = $i;
        	$ph2['tv0'] = $tv[0];
        	$ph2['tv2'] = $tv[2];
            $ph2['class'] = is_array($tv[12]) && !in_array('sample', $tv[12]) ? 'toggle' : 'toggle demo';
            if((is_array($tvs) && in_array($i, $tvs)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                             $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+tvs+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}

function block_chunks($moduleChunks,$chunks,$options_selected,$ph) {
    if (count($moduleChunks) === 0) return '';
	else {
		$tpl = '<label><input type="checkbox" name="chunk[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+chunk0+]</span> - [+chunk1+]</label><br />';
		$ph2 = $ph;
        foreach($moduleChunks as $i=>$chunk) {
        	$ph2['i'] = $i;
        	$ph2['chunk0'] = $chunk[0];
        	$ph2['chunk1'] = $chunk[1];
            $ph2['class'] = is_array($chunk[5]) && !in_array('sample', $chunk[5]) ? 'toggle' : 'toggle demo';
            if((is_array($chunks) && in_array($i, $chunks)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                                   $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+chunks+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}

function block_modules($moduleModules,$modules,$options_selected,$ph) {
    if (count($moduleModules) === 0) return '';
	else {
		$tpl = '<label><input type="checkbox" name="module[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+module0+]</span> - [+module1+]</label><br />';
		$ph2 = $ph;
        foreach($moduleModules as $i=>$module) {
        	$ph2['i'] = $i;
        	$ph2['module0'] = $module[0];
        	$ph2['module1'] = $module[1];
            $ph2['class'] = is_array($module[7]) && !in_array('sample', $module[7]) ? 'toggle' : 'toggle demo';
            if((is_array($modules) && in_array($i, $modules)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                             $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+modules+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}

function block_plugins($modulePlugins,$plugins,$options_selected,$ph) {
    if (count($modulePlugins) === 0) return '';
	else {
		$tpl = '<label><input type="checkbox" name="plugin[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+plugin0+]</span> - [+plugin1+]</label><br />';
		$ph2 = $ph;
        foreach($modulePlugins as $i=>$plugin) {
        	$ph2['i'] = $i;
        	$ph2['plugin0'] = $plugin[0];
        	$ph2['plugin1'] = $plugin[1];
            $ph2['class'] = is_array($plugin[8]) && !in_array('sample', $plugin[8]) ? 'toggle' : 'toggle demo';
            if((is_array($plugins) && in_array($i, $plugins)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                             $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+plugins+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}

function block_snippets($moduleSnippets,$snippets,$options_selected,$ph) {
    if (count($moduleSnippets) === 0) return '';
	else {
		$tpl = '<label><input type="checkbox" name="snippet[]" value="[+i+]" class="[+class+]" [+checked+] />[+install_update+]<span class="comname">[+snippet0+]</span> - [+snippet1+]</label><br />';
		$ph2 = $ph;
        foreach($moduleSnippets as $i=>$snippet) {
        	$ph2['i'] = $i;
        	$ph2['snippet0'] = $snippet[0];
        	$ph2['snippet1'] = $snippet[1];
            $ph2['class'] = is_array($snippet[5]) && !in_array('sample', $snippet[5]) ? 'toggle' : 'toggle demo';
            if((is_array($snippets) && in_array($i, $snippets)) || !$options_selected) $ph2['checked'] = 'checked';
            else                                                             $ph2['checked'] =  '';
            $_[] = parse($tpl,$ph2);
        }
        if(!empty($_)) {
            $tpl = "<h3>[+snippets+]</h3>\n" . join("\n", $_);
        }
        else $tpl = '';
        return parse($tpl,$ph);
    }
}
