<?php

$cmsadmin           = getOption('cmsadmin');
$cmsadminemail      = getOption('cmsadminemail');
$cmspassword        = getOption('cmspassword');
$cmspasswordconfirm = getOption('cmspasswordconfirm');
$chkagree           = getOption('chkagree');

setOption('cmsadmin',$cmsadmin);
setOption('cmsadminemail',$cmsadminemail);
setOption('cmspassword',$cmspassword);
setOption('cmspasswordconfirm',$cmspasswordconfirm);
setOption('chkagree',$chkagree);

$templates = getOption('template');
$tvs       = getOption('tv');
$chunks    = getOption('chunk');
$modules   = getOption('module');
$plugins   = getOption('plugin');
$snippets  = getOption('snippet');

?>

<form id="install" action="index.php?action=summary" method="POST">

<?php
# load setup information file
include_once("{$installer_path}setup.info.php");

echo '<h2>' . $_lang['optional_items'] . '</h2>';

if($installmode === '0')
{
    echo '<p>' . $_lang['optional_items_new_note'] . "</p>";
}

$chk = isset ($_POST['installdata']) && $_POST['installdata'] == "1" ? 'checked="checked"' : '';
if($installmode == 0)
{
	echo '<img src="img/sample_site.png" class="options" alt="Sample Data" />';
	echo '<h3>' . $_lang['sample_web_site'] . "</h3>\n";
	echo '<p><input type="checkbox" name="installdata" id="installdata_field" value="1" ' . $chk  . '/>&nbsp;<label for="installdata_field">' . $_lang['install_overwrite'] . ' <span class="comname">' . $_lang['sample_web_site'] . '</span></label></p><p><em>&nbsp;' . $_lang['sample_web_site_note'] . "</em></p>";
}
echo '<hr />';

$options_selected = getOption('options_selected');

// display templates
$limit = count($moduleTemplates);
if ($limit > 0) {
    $tplOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleTemplates[$i][6]) && !in_array('sample', $moduleTemplates[$i][6])) ? 'toggle' : 'toggle demo';
        $chk = (is_array($templates) && in_array($i, $templates)) || (!$options_selected) ? 'checked="checked"' : "";
        $tplOutput .= "\n<label><input type=\"checkbox\" name=\"template[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleTemplates[$i][0] . "</span> - " . $moduleTemplates[$i][1] . "</label><hr />\n";
    }
    if($tplOutput !== '') {
        $echo[] = "<h3>" . $_lang['templates'] . "</h3><br />";
        $echo[] = $tplOutput;
    }
}

// display template variables
$limit = count($moduleTVs);
if ($limit > 0) {
    $tvOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleTVs[$i][12]) && !in_array('sample', $moduleTVs[$i][12])) ? "toggle" : "toggle demo";
        $chk = (is_array($tvs) && in_array($i, $tvs)) || (!$options_selected) ? 'checked="checked"' : "";
        $tvOutput .= "\n<label><input type=\"checkbox\" name=\"tv[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleTVs[$i][0] . "</span> - " . $moduleTVs[$i][2] . "</label><hr />\n";
    }
    if($tvOutput != '') {
        $echo[] = "<h3>" . $_lang['tvs'] . "</h3><br />";
        $echo[] = $tvOutput;
    }
}

// display chunks
$limit = count($moduleChunks);
if ($limit > 0) {
    $chunkOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleChunks[$i][5]) && !in_array('sample', $moduleChunks[$i][5])) ? "toggle" : "toggle demo";
        $chk = (is_array($chunks) && in_array($i, $chunks)) || (!$options_selected) ? 'checked="checked"' : "";
        $chunkOutput .= "\n<label><input type=\"checkbox\" name=\"chunk[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleChunks[$i][0] . "</span> - " . $moduleChunks[$i][1] . "</label><hr />\n";
    }
    if($chunkOutput != '') {
        $echo[] = "<h3>" . $_lang['chunks'] . "</h3>";
        $echo[] = $chunkOutput;
    }
}

// display modules
$limit = count($moduleModules);
if ($limit > 0) {
    $moduleOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleModules[$i][7]) && !in_array('sample', $moduleModules[$i][7])) ? "toggle" : "toggle demo";
        $chk = (is_array($modules) && in_array($i, $modules)) || (!$options_selected) ? 'checked="checked"' : "";
        $moduleOutput .= "\n<label><input type=\"checkbox\" name=\"module[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleModules[$i][0] . "</span> - " . $moduleModules[$i][1] . "</label><hr />\n";
    }
    if($moduleOutput != '') {
        $echo[] = "<h3>" . $_lang['modules'] . "</h3>";
        $echo[] = $moduleOutput;
    }
}

// display plugins
$limit = count($modulePlugins);
if ($limit > 0) {
    $pluginOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        if(is_array($modulePlugins[$i][8]))
        {
        $class = (is_array($modulePlugins[$i][8]) && !in_array('sample', $modulePlugins[$i][8])) ? "toggle" : "toggle demo";
        }
        else $class = 'toggle';
        $chk = (is_array($plugins) && in_array($i, $plugins)) || (!$options_selected) ? 'checked="checked"' : "";
        $pluginOutput .= "\n<label><input type=\"checkbox\" name=\"plugin[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $modulePlugins[$i][0] . "</span> - " . $modulePlugins[$i][1] . "</label><hr />\n";
    }
    if($pluginOutput != '') {
        $echo[] = "<h3>" . $_lang['plugins'] . "</h3>";
        $echo[] = $pluginOutput;
    }
}

// display snippets
$limit = count($moduleSnippets);
if ($limit > 0) {
    $snippetOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        if(is_array($moduleSnippets[$i][5]))
        {
        $class = (is_array($moduleSnippets[$i][5]) && !in_array('sample', $moduleSnippets[$i][5])) ? "toggle" : "toggle demo";
        }
        else $class = 'toggle';
        $chk = (is_array($snippets) && in_array($i, $snippets)) || (!$options_selected) ? 'checked="checked"' : "";
        $snippetOutput .= "\n<label><input type=\"checkbox\" name=\"snippet[]\" value=\"$i\" class=\"{$class}\" $chk />\n" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleSnippets[$i][0] . "</span> - " . $moduleSnippets[$i][1] . "</label><hr />\n";
    }
    if($snippetOutput != '') {
        $echo[] = "<h3>" . $_lang['snippets'] . "</h3>";
        $echo[] = $snippetOutput;
    }
}
$echo = join("\n",$echo);
$echo = trim($echo);
if($echo)
{
	echo '<p>' . $_lang['optional_items_upd_note'] . "</p>";
	// toggle options
	echo 
'<h4>' . $_lang['checkbox_select_options'] . '</h4>
<p class="actions"><a id="toggle_check_all" href="javascript:void(0);">' . $_lang['all'] . '</a> <a id="toggle_check_none" href="javascript:void(0);">' . $_lang['none'] . '</a> <a id="toggle_check_toggle" href="javascript:void(0);">' . $_lang['toggle'] . '</a></p>
<br class="clear" />
<div id="installChoices">';
	echo $echo . '</div>';
}
else echo '<strong>' . $_lang['no_update_options'] . '</strong>';
?>

    <p class="buttonlinks">
        <a class="prev" href="javascript:void(0);" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a class="next" href="javascript:void(0);" title="<?php echo $_lang['btnnext_value']?>"><span><?php echo $_lang['btnnext_value']?></span></a>
    </p>

</form>
<script type="text/javascript">
	var installmode = <?php echo $installmode; ?>;
	$('a.prev').click(function(){
		var target = (installmode==1) ? 'mode' : 'connection';
		$('#install').attr({action:'index.php?action='+target});
		$('#install').submit();
	});
	$('a.next').click(function(){
		$('#install').submit();
	});
	
	$('#toggle_check_all').click(function(evt){
	    evt.preventDefault();
	    demo = $('#installdata_field').attr('checked');
	    $('input:checkbox.toggle:not(:disabled)').attr('checked', true);
	});
	$('#toggle_check_none').click(function(evt){
	    evt.preventDefault();
	    demo = $('#installdata_field').attr('checked');
	    $('input:checkbox.toggle:not(:disabled)').attr('checked', false);
	});
	$('#toggle_check_toggle').click(function(evt){
	    evt.preventDefault();
	    $('input:checkbox.toggle:not(:disabled)').attr('checked', function(){
	        return !$(this).attr('checked');
	    });
	});
	$('#installdata_field').click(function(evt){
	    handleSampleDataCheckbox();
	});
	
	var handleSampleDataCheckbox = function(){
	    demo = $('#installdata_field').attr('checked');
	    $('input:checkbox.toggle.demo').each(function(ix, el){
	        if(demo) {
	            $(this)
	                .attr('checked', true)
	                .attr('disabled', true)
	            ;
	} else {
	            $(this)
	                .attr('disabled', false)
	            ;
	}
	    });
	}
	
	// handle state of demo content checkbox on page load
	handleSampleDataCheckbox();
</script>