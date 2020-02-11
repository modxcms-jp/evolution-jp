<?php

global $tplTemplates, $tplTVs, $tplChunks, $tplModules, $tplPlugins, $tplSnippets;

if(isset($_POST['chkagree'])) {
    $_SESSION['chkagree'] = $_POST['chkagree'];
}

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
include_once MODX_SETUP_PATH . 'setup.info.php';

$ph['installmode'] = $_SESSION['installmode'];

if ($_SESSION['installmode'] == 0) {
    $ph['install_sample_site'] = block_install_sample_site($ph) . "\n";
} else {
    $ph['install_sample_site'] = '';
}
$ph['block_templates'] = block_templates($tplTemplates,$ph);
$ph['block_tvs']       = block_tvs(      $tplTVs,            $ph);
$ph['block_chunks']    = block_chunks(   $tplChunks,      $ph);
$ph['block_modules']   = block_modules(  $tplModules,  $ph);
$ph['block_plugins']   = block_plugins(  $tplPlugins,  $ph);
$ph['block_snippets']  = block_snippets( $tplSnippets, $ph);

$ph['object_list'] = show_object_list($ph) . "\n";

echo  evo()->parseText(
    file_get_contents($base_path . 'install/tpl/options.tpl')
    ,$ph
);



function show_object_list($ph) {
	$objects = implode(
        "\n"
        , array($ph['block_templates'],$ph['block_tvs'],$ph['block_chunks'],$ph['block_modules'],$ph['block_plugins'],$ph['block_snippets']));
	if(trim($objects)==='') {
        return evo()->parseText('<strong>[+no_update_options+]</strong>',$ph);
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
    return evo()->parseText($tpl,$ph);
}

function block_install_sample_site($ph) {
	$tpl = <<< TPL
<img src="img/sample_site.png" class="options" alt="Sample Data" />
<h3>[+sample_web_site+]</h3>
<p>
<input type="checkbox" name="installdata" id="installdata_field" value="1" [+checked+] />&nbsp;
<label for="installdata_field">[+install_overwrite+] <span class="comname">[+sample_web_site+]</span></label>
</p>
<p><em>&nbsp;[+sample_web_site_note+]</em></p>
TPL;
    $ph['checked'] = evo()->session('installdata', false)==1 ? 'checked' : '';
	return evo()->parseText($tpl,$ph);
}

function block_templates($tplTemplates,$ph) {
	if(!$tplTemplates) {
        return '';
    }

    $_ = array('<h3>[+templates+]</h3>');
    foreach($tplTemplates as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="template[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('template', false), $i) ? 'checked':''
            , $v['templatename']
            , $v['description']
        );
    }
    return evo()->parseText(join("<br />\n", $_), $ph);
}

function block_tvs($tplTVs,$ph) {
    if (!$tplTVs) {
        return '';
    }

    $_ = array('<h3>[+tvs+]</h3>');
    foreach($tplTVs as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="tv[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('tv', false), $i) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return evo()->parseText(join("<br />\n", $_), $ph);
}

function block_chunks($tplChunks,$ph) {
    if (!$tplChunks) {
        return '';
    }

    $_ = array('<h3>[+chunks+]</h3>');
    foreach($tplChunks as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="chunk[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('chunk', false), $i) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return evo()->parseText(join("<br />\n", $_), $ph);
}

function block_modules($tplModules,$ph) {
    if (!$tplModules) {
        return '';
    }

    $_ = array('<h3>[+modules+]</h3>');
    foreach($tplModules as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="module[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('module', false), $i) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return evo()->parseText(join("<br />\n", $_), $ph);
}

function block_plugins($tplPlugins, $ph) {
    if (!$tplPlugins) {
        return '';
    }

    $_ = array('<h3>[+plugins+]</h3>');
    foreach($tplPlugins as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="plugin[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('plugin', false), $i) ? 'checked':''
            , $v['name']
            , $v['description']
        );
    }
    return evo()->parseText(implode("<br />\n", $_), $ph);
}

function block_snippets($tplSnippets, $ph) {
    if (!$tplSnippets) {
        return '';
    }
    $_ = array('<h3>[+snippets+]</h3>');
    foreach($tplSnippets as $i=>$v) {
        $_[] = sprintf(
            '<label><input type="checkbox" name="snippet[]" value="%s" class="%s" %s /><span class="comname">%s</span> - %s</label>'
            , $i
            , is_demo($v) ? 'toggle' : 'toggle demo'
            , is_check(evo()->session('snippet', false), $i) ? 'checked':''
            , $v['name']
            , $v['description']
            );
    }
    return evo()->parseText(implode("<br />\n", $_), $ph);
}

function is_demo($option) {
    if(!isset($option['installset'])) {
        return false;
    }
    return is_array($option['installset']) && !in_array('sample', $option['installset'], true);
}

function is_check($elements, $num) {
    if(in_array($_SESSION['prevAction'], array('connection', 'mode'))) {
        if(!isset($_SESSION['installdata'])) {
            return true;
        }
    }
    if(!is_array($elements) || !$elements) {
        return false;
    }
    return in_array($num, $elements);
}