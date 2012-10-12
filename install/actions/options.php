<?php
if(isset($_POST['installmode'])) setOption('installmode',$_POST['installmode']);
$installmode = getOption('installmode');

if(isset($_POST['cmsadmin']))           setOption('cmsadmin',$_POST['cmsadmin']);
if(isset($_POST['cmsadminemail']))      setOption('cmsadminemail',$_POST['cmsadminemail']);
if(isset($_POST['cmspassword']))        setOption('cmspassword',$_POST['cmspassword']);
if(isset($_POST['cmspasswordconfirm'])) setOption('cmspasswordconfirm',$_POST['cmspasswordconfirm']);

?>

<form name="install" id="install_form" action="index.php" method="POST">
<input type="hidden" name="action" value="summary" />

<?php
# load setup information file
include "{$installer_path}setup.info.php";

if($_POST['installmode'] === '0')
{
    echo "<h2>" . $_lang['optional_items'] . "</h2><p>" . $_lang['optional_items_new_note'] . "</p>";
}
else
{
    echo "<h2>" . $_lang['optional_items'] . "</h2><p>" . $_lang['optional_items_upd_note'] . "</p>";
}

$chk = isset ($_POST['installdata']) && $_POST['installdata'] == "1" ? 'checked="checked"' : "";
if($installmode == 0)
{
	echo '<img src="img/sample_site.png" class="options" alt="Sample Data" />';
	echo '<h3>' . $_lang['sample_web_site'] . '</h3>';
	echo '<p><input type="checkbox" name="installdata" id="installdata_field" value="1" ' . $chk  . '/>&nbsp;<label for="installdata_field">' . $_lang['install_overwrite'] . ' <span class="comname">' . $_lang['sample_web_site'] . '</span></label></p><p><em>&nbsp;' . $_lang['sample_web_site_note'] . "</em></p>";
}
echo '<hr />';

// toggle options
echo '<h4>' . $_lang['checkbox_select_options'] . '</h4>
    <p class="actions"><a id="toggle_check_all" href="#">' . $_lang['all'] . '</a> <a id="toggle_check_none" href="#">' . $_lang['none'] . '</a> <a id="toggle_check_toggle" href="#">' . $_lang['toggle'] . '</a></p>
	<br class="clear" />
	<div id="installChoices">';

$options_selected = isset ($_POST['options_selected']);

// display templates
$templates = isset ($_POST['template']) ? $_POST['template'] : array ();
$limit = count($moduleTemplates);
if ($limit > 0) {
    $tplOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleTemplates[$i][6]) && !in_array('sample', $moduleTemplates[$i][6])) ? 'toggle' : 'toggle demo';
        $chk = in_array($i, $templates) || (!$options_selected) ? 'checked="checked"' : "";
        $tplOutput .= "<label><input type=\"checkbox\" name=\"template[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleTemplates[$i][0] . "</span> - " . $moduleTemplates[$i][1] . "</label><hr />\n";
    }
    if($tplOutput !== '') {
        echo "<h3>" . $_lang['templates'] . "</h3><br />";
        echo $tplOutput;
    }
}

// display template variables
$tvs = isset ($_POST['tv']) ? $_POST['tv'] : array ();
$limit = count($moduleTVs);
if ($limit > 0) {
    $tvOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleTVs[$i][12]) && !in_array('sample', $moduleTVs[$i][12])) ? "toggle" : "toggle demo";
        $chk = in_array($i, $tvs) || (!$options_selected) ? 'checked="checked"' : "";
        $tvOutput .= "<label><input type=\"checkbox\" name=\"tv[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleTVs[$i][0] . "</span> - " . $moduleTVs[$i][2] . "</label><hr />\n";
    }
    if($tvOutput != '') {
        echo "<h3>" . $_lang['tvs'] . "</h3><br />\n";
        echo $tvOutput;
    }
}

// display chunks
$chunks = isset ($_POST['chunk']) ? $_POST['chunk'] : array ();
$limit = count($moduleChunks);
if ($limit > 0) {
    $chunkOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleChunks[$i][5]) && !in_array('sample', $moduleChunks[$i][5])) ? "toggle" : "toggle demo";
        $chk = in_array($i, $chunks) || (!$options_selected) ? 'checked="checked"' : "";
        $chunkOutput .= "<label><input type=\"checkbox\" name=\"chunk[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleChunks[$i][0] . "</span> - " . $moduleChunks[$i][1] . "</label><hr />";
    }
    if($chunkOutput != '') {
        echo "<h3>" . $_lang['chunks'] . "</h3>";
        echo $chunkOutput;
    }
}

// display modules
$modules = isset ($_POST['module']) ? $_POST['module'] : array ();
$limit = count($moduleModules);
if ($limit > 0) {
    $moduleOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        $class = (is_array($moduleModules[$i][7]) && !in_array('sample', $moduleModules[$i][7])) ? "toggle" : "toggle demo";
        $chk = in_array($i, $modules) || (!$options_selected) ? 'checked="checked"' : "";
        $moduleOutput .= "<label><input type=\"checkbox\" name=\"module[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleModules[$i][0] . "</span> - " . $moduleModules[$i][1] . "</label><hr />";
    }
    if($moduleOutput != '') {
        echo "<h3>" . $_lang['modules'] . "</h3>";
        echo $moduleOutput;
    }
}

// display plugins
$plugins = isset ($_POST['plugin']) ? $_POST['plugin'] : array ();
$limit = count($modulePlugins);
if ($limit > 0) {
    $pluginOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        if(is_array($modulePlugins[$i][8]))
        {
        $class = (is_array($modulePlugins[$i][8]) && !in_array('sample', $modulePlugins[$i][8])) ? "toggle" : "toggle demo";
        }
        else $class = 'toggle';
        $chk = in_array($i, $plugins) || (!$options_selected) ? 'checked="checked"' : "";
        $pluginOutput .= "<label><input type=\"checkbox\" name=\"plugin[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $modulePlugins[$i][0] . "</span> - " . $modulePlugins[$i][1] . "</label><hr />";
    }
    if($pluginOutput != '') {
        echo "<h3>" . $_lang['plugins'] . "</h3>";
        echo $pluginOutput;
    }
}

// display snippets
$snippets = isset ($_POST['snippet']) ? $_POST['snippet'] : array ();
$limit = count($moduleSnippets);
if ($limit > 0) {
    $snippetOutput = '';
    for ($i = 0; $i < $limit; $i++) {
        if(is_array($moduleSnippets[$i][5]))
        {
        $class = (is_array($moduleSnippets[$i][5]) && !in_array('sample', $moduleSnippets[$i][5])) ? "toggle" : "toggle demo";
        }
        else $class = 'toggle';
        $chk = in_array($i, $snippets) || (!$options_selected) ? 'checked="checked"' : "";
        $snippetOutput .= "<label><input type=\"checkbox\" name=\"snippet[]\" value=\"$i\" class=\"{$class}\" $chk />" . $_lang['install_update'] . " <span class=\"comname\">" . $moduleSnippets[$i][0] . "</span> - " . $moduleSnippets[$i][1] . "</label><hr />";
    }
    if($snippetOutput != '') {
        echo "<h3>" . $_lang['snippets'] . "</h3>";
        echo $snippetOutput;
    }
}
if(!count($moduleTemplates+$moduleTVs+$moduleChunks+$moduleModules+$modulePlugins+$moduleSnippets))
echo '<strong>' . $_lang['no_update_options'] . '</strong>';
?>
	</div>
    <p class="buttonlinks">
        <a class="prev" href="#" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a class="next" href="#" title="<?php echo $_lang['btnnext_value']?>"><span><?php echo $_lang['btnnext_value']?></span></a>
    </p>

</form>
<script type="text/javascript">
	var installMode = <?php echo $installmode; ?>;
	$('a.prev').click(function(){
		var target = (installMode==1) ? 'mode' : 'connection';
		$('input[name="action"]').val(target);
		$('#install_form').submit();
	});
	$('a.next').click(function(){
		$('#install_form').submit();
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