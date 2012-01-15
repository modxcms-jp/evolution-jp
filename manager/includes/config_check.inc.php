<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

if (is_writable("includes/config.inc.php")){
    // Warn if world writable
    if(@fileperms('includes/config.inc.php') & 0x0002) {
      $warnings[] = 'configcheck_configinc';
    }
}

if (file_exists("../install/")) {
    $warnings[] = 'configcheck_installer';
}

if (ini_get('register_globals')==TRUE) {
    $warnings[] = 'configcheck_register_globals';
}

if (!extension_loaded('gd')) {
	$warnings[] = 'configcheck_php_gdzip';
}

if(!isset($modx->config['_hide_configcheck_validate_referer']) || $modx->config['_hide_configcheck_validate_referer'] !== '1')
{
	if(isset($_SESSION['mgrPermissions']['settings']) && $_SESSION['mgrPermissions']['settings'] == '1')
	{
		if ($modx->db->getValue('SELECT COUNT(setting_value) FROM '.$modx->getFullTableName('system_settings').' WHERE setting_name=\'validate_referer\' AND setting_value=\'0\''))
		{
			$warnings[] = 'configcheck_validate_referer';
		}
    }
}

// check for Template Switcher plugin
if(!isset($modx->config['_hide_configcheck_templateswitcher_present']) || $modx->config['_hide_configcheck_templateswitcher_present'] !== '1')
{
    if(isset($_SESSION['mgrPermissions']['edit_plugin']) && $_SESSION['mgrPermissions']['edit_plugin'] == '1')
    {
        $where = "name IN ('TemplateSwitcher','Template Switcher','templateswitcher','template_switcher','template switcher') OR plugincode LIKE '%TemplateSwitcher%'";
        $rs = $modx->db->select('name, disabled',$modx->getFullTableName('site_plugins'),$where);
        $row = $modx->db->getRow($rs);
        if($row && $row['disabled'] == 0) {
            $warnings[] = 'configcheck_templateswitcher_present';
            $tplName = $row['name'];
	$script = <<<JS
<script type="text/javascript">
function deleteTemplateSwitcher(){
    if(confirm('{$_lang["confirm_delete_plugin"]}')) {
	var myAjax = new Ajax('index.php?a=118',
	{
		method: 'post',
        data: 'action=updateplugin&key=_delete_&lang=$tplName'
	});
	myAjax.addEvent('onComplete', function(resp){
            fieldset = $('templateswitcher_present_warning_wrapper').getParent().getParent();
		var sl = new Fx.Slide(fieldset);
		sl.slideOut();
	});
	myAjax.request();
    }
}
function disableTemplateSwitcher(){
    var myAjax = new Ajax('index.php?a=118', {
        method: 'post',
        data: 'action=updateplugin&lang={$tplName}&key=disabled&value=1'
    });
    myAjax.addEvent('onComplete', function(resp){
        fieldset = $('templateswitcher_present_warning_wrapper').getParent().getParent();
        var sl = new Fx.Slide(fieldset);
        sl.slideOut();
    });
    myAjax.request();
}
</script>

JS;
	$modx->regClientScript($script);
        }
    }
}

$tbl_site_content = $modx->getFullTableName('site_content');

if ($modx->db->getValue($modx->db->select('published',$tbl_site_content,"id={$unauthorized_page}")) == 0) {
    $warnings[] = 'configcheck_unauthorizedpage_unpublished';
}

if ($modx->db->getValue($modx->db->select('published',$tbl_site_content,"id={$error_page}")) == 0) {
    $warnings[] = 'configcheck_errorpage_unpublished';
}

if ($modx->db->getValue($modx->db->select('privateweb',$tbl_site_content,"id={$unauthorized_page}")) == 1) {
    $warnings[] = 'configcheck_unauthorizedpage_unavailable';
}

if ($modx->db->getValue($modx->db->select('privateweb',$tbl_site_content,"id={$error_page}")) == 1) {
    $warnings[] = 'configcheck_errorpage_unavailable';
}

	if (!function_exists('checkSiteCache'))
	{
		function checkSiteCache()
		{
			global $modx;
			$checked= true;
			if (file_exists($modx->config['base_path'] . 'assets/cache/siteCache.idx.php'))
			{
				$checked= @include_once ($modx->config['base_path'] . 'assets/cache/siteCache.idx.php');
			}
			return $checked;
		}
	}

if (!is_writable("../assets/cache/")) {
    $warnings[] = 'configcheck_cache';
}

if (!checkSiteCache()) {
    $warnings[]= 'configcheck_sitecache_integrity';
}

if (!is_writable("../assets/images/")) {
    $warnings[] = 'configcheck_images';
}

// clear file info cache
clearstatcache();

if (0 < count($warnings))
{
	$config_check_results = "<h3>".$_lang['configcheck_notok']."</h3>";
	foreach ($warnings as $warning)
	{
		$title = $_lang[$warning];
		switch ($warning)
		{
			case 'configcheck_configinc';
				$output = $_lang['configcheck_configinc_msg'];
				if(!$_SESSION["mgrConfigCheck"]) $modx->logEvent(0,2,$output,$_lang[$warning]);
				break;
			case 'configcheck_installer':
				$output = $_lang['configcheck_installer_msg'];
				if(!$_SESSION["mgrConfigCheck"]) $modx->logEvent(0,2,$output,$_lang[$warning]);
				break;
			case 'configcheck_cache':
				$output = $_lang['configcheck_cache_msg'];
				if(!$_SESSION["mgrConfigCheck"]) $modx->logEvent(0,2,$output,$_lang[$warning]);
				break;
			case 'configcheck_images':
				$output = $_lang['configcheck_images_msg'];
				if(!$_SESSION["mgrConfigCheck"]) $modx->logEvent(0,2,$output,$_lang[$warning]);
				break;
			case 'configcheck_lang_difference':
				$output = $_lang['configcheck_lang_difference_msg'];
				break;
			case 'configcheck_register_globals':
				$output = $_lang['configcheck_register_globals_msg'];
				break;
			case 'configcheck_php_gdzip':
				$output = $_lang['configcheck_php_gdzip_msg'];
				break;
			case 'configcheck_unauthorizedpage_unpublished':
				$output = $_lang['configcheck_unauthorizedpage_unpublished_msg'];
				break;
			case 'configcheck_errorpage_unpublished':
				$output = $_lang['configcheck_errorpage_unpublished_msg'];
				break;
			case 'configcheck_unauthorizedpage_unavailable':
				$output = $_lang['configcheck_unauthorizedpage_unavailable_msg'];
				break;
			case 'configcheck_errorpage_unavailable':
				$output = $_lang['configcheck_errorpage_unavailable_msg'];
				break;
			case 'configcheck_validate_referer':
				$msg = $_lang['configcheck_validate_referer_msg'];
				$msg .= '<br />' . sprintf($_lang["configcheck_hide_warning"], 'validate_referer');
				$output = "<span id=\"validate_referer_warning_wrapper\">{$msg}</span>\n";
				break;
			case 'configcheck_templateswitcher_present':
				$msg = $_lang['configcheck_templateswitcher_present_msg'];
				if(isset($_SESSION['mgrPermissions']['save_plugin']) && $_SESSION['mgrPermissions']['save_plugin'] == '1')
				{
					$msg .= '<br />' . $_lang["configcheck_templateswitcher_present_disable"];
				}
				if(isset($_SESSION['mgrPermissions']['delete_plugin']) && $_SESSION['mgrPermissions']['delete_plugin'] == '1')
				{
					$msg .= '<br />' . $_lang["configcheck_templateswitcher_present_delete"];
				}
				$msg .= '<br />' . sprintf($_lang["configcheck_hide_warning"], 'templateswitcher_present');
				$output = "<span id=\"templateswitcher_present_warning_wrapper\">{$msg}</span>\n";
				break;
			default :
				$output = $_lang['configcheck_default_msg'];
		}
		
		$admin_warning = $_SESSION['mgrRole']!=1 ? $_lang['configcheck_admin'] : "" ;
		$config_check_result[] = "
<fieldset>
<p><strong>{$_lang['configcheck_warning']}</strong> ' {$title} '</p>
<p style=\"padding-left:1em\"><em>".$_lang['configcheck_what']."</em><br />
".$output." ".$admin_warning."</p>
</fieldset>
";
	}
	$_SESSION["mgrConfigCheck"] = true;
	$config_check_results = join('<br />',$config_check_result);
}
else
{
	$config_check_results = $_lang['configcheck_ok'];
}
