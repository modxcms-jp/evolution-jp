<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

$warnings = array();
$rs = checkAjaxSearch();
if ($rs) {
    $warnings[] = 'configcheck_danger_ajaxsearch';
}

if (is_writable(MODX_CORE_PATH . 'config.inc.php')) {
    if(!@chmod(MODX_CORE_PATH . 'config.inc.php', 0444)) {
        $warnings[] = 'configcheck_configinc';
    }
}

if (is_file(MODX_BASE_PATH . "assets/templates/manager/login.html")) {
    $warnings[] = 'configcheck_mgr_tpl';
}
if (is_dir('../install/')) {
    $warnings[] = 'configcheck_installer';
}
if (ini_get('register_globals')) {
    $warnings[] = 'configcheck_register_globals';
}
if (!extension_loaded('gd')) {
    $warnings[] = 'configcheck_php_gdzip';
}

if (!evo()->config('_hide_configcheck_validate_referer')) {
    if (sessionv('mgrPermissions.settings') == 1) {
        $rs = db()->select(
            'setting_value',
            '[+prefix+]system_settings',
            "setting_name='validate_referer' AND setting_value=0"
        );
    if (db()->count($rs)) {
            $warnings[] = 'configcheck_validate_referer';
        }
    }
}

$actionphp = MODX_BASE_PATH . 'action.php';
if (is_file($actionphp)) {
    $src = file_get_contents($actionphp);
    if (strpos($src, 'if(strpos($path,MODX_MANAGER_PATH)!==0)') === false) {
        $warnings[] = 'configcheck_del_actionphp';
    }
}

// check for Template Switcher plugin
if (!evo()->config('_hide_configcheck_templateswitcher_present')) {
    if (isset($_SESSION['mgrPermissions']['edit_plugin']) && $_SESSION['mgrPermissions']['edit_plugin'] == '1') {
        $rs = db()->select(
            'name, disabled',
            '[+prefix+]site_plugins',
            array(
                "name IN ('TemplateSwitcher','Template Switcher','templateswitcher','template_switcher','template switcher')",
                "OR plugincode LIKE '%TemplateSwitcher%'"
            )
        );
        $row = db()->getRow($rs);
        if ($row && $row['disabled'] == 0) {
            $warnings[] = 'configcheck_templateswitcher_present';
            $tplName = $row['name'];
            $script = get_src_TemplateSwitcher_js($tplName);
            evo()->regClientScript($script);
        }
    }
}

if (get_sc_value('published', $unauthorized_page) === '0') {
    $warnings[] = 'configcheck_unauthorizedpage_unpublished';
}
if (get_sc_value('published', $error_page) === '0') {
    $warnings[] = 'configcheck_errorpage_unpublished';
}
if (get_sc_value('privateweb', $unauthorized_page) === '1') {
    $warnings[] = 'configcheck_unauthorizedpage_unavailable';
}
if (get_sc_value('privateweb', $error_page) === '1') {
    $warnings[] = 'configcheck_errorpage_unavailable';
}

if (!is_writable(MODX_BASE_PATH . 'assets/cache')) {
    $warnings[] = 'configcheck_cache';
}
if (!is_writable(evo()->config['rb_base_dir'] . 'images')) {
    $warnings[] = 'configcheck_images';
}

if (!is_dir(evo()->config['rb_base_dir'])) {
    $warnings[] = 'configcheck_rb_base_dir';
}
if (!is_dir(evo()->config['filemanager_path'])) {
    $warnings[] = 'configcheck_filemanager_path';
}

if ($_SESSION['mgrRole'] == 1) {
    $warnings[] = 'configcheck_you_are_admin';
}

// clear file info cache
clearstatcache();

if (0 >= count($warnings)) {
    $config_check_results = $_lang['configcheck_ok'];
    return;
}

$config_check_results = "<h3>" . $_lang['configcheck_notok'] . "</h3>";
foreach ($warnings as $warning) {
    switch ($warning) {
        case 'configcheck_you_are_admin':
            $output = $_lang['configcheck_you_are_admin_msg'];
            break;
        case 'configcheck_mgr_tpl':
            $ph['path'] = urlencode(MODX_BASE_PATH);
            $output = evo()->parseText($_lang['configcheck_mgr_tpl_msg'], $ph);
            break;
        case 'configcheck_configinc';
            $output = $_lang['configcheck_configinc_msg'];
            if (!$_SESSION["mgrConfigCheck"]) {
                evo()->logEvent(0, 2, $output, $_lang[$warning]);
            }
            break;
        case 'configcheck_installer':
            $output = $_lang['configcheck_installer_msg'];
            if (!$_SESSION["mgrConfigCheck"]) {
                evo()->logEvent(0, 3, $output, $_lang[$warning]);
            }
            break;
        case 'configcheck_cache':
            $output = $_lang['configcheck_cache_msg'];
            if (!$_SESSION["mgrConfigCheck"]) {
                evo()->logEvent(0, 2, $output, $_lang[$warning]);
            }
            break;
        case 'configcheck_images':
            $output = $_lang['configcheck_images_msg'];
            if (!$_SESSION["mgrConfigCheck"]) {
                evo()->logEvent(0, 2, $output, $_lang[$warning]);
            }
            break;
        case 'configcheck_danger_ajaxsearch':
            $output = $_lang["configcheck_danger_ajaxsearch_msg"];
            if (!isset($_SESSION["mgrConfigCheck"])) {
                evo()->logEvent(0, 3, $output, $_lang['configcheck_danger_ajaxsearch']);
            }
            break;
        case 'configcheck_rb_base_dir':
            $output = '$modx->config[\'rb_base_dir\']';
            break;
        case 'configcheck_filemanager_path':
            $output = '$modx->config[\'filemanager_path\']';
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
        case 'configcheck_del_actionphp':
            $output = $_lang['configcheck_del_actionphp_msg'];
            if (!$_SESSION["mgrConfigCheck"]) {
                evo()->logEvent(0, 3, $output, $_lang[$warning]);
            }
            break;
        case 'configcheck_templateswitcher_present':
            $msg = $_lang['configcheck_templateswitcher_present_msg'];
            if (isset($_SESSION['mgrPermissions']['save_plugin']) && $_SESSION['mgrPermissions']['save_plugin'] == '1') {
                $msg .= '<br />' . $_lang["configcheck_templateswitcher_present_disable"];
            }
            if (isset($_SESSION['mgrPermissions']['delete_plugin']) && $_SESSION['mgrPermissions']['delete_plugin'] == '1') {
                $msg .= '<br />' . $_lang["configcheck_templateswitcher_present_delete"];
            }
            $msg .= '<br />' . sprintf($_lang["configcheck_hide_warning"], 'templateswitcher_present');
            $output = "<span id=\"templateswitcher_present_warning_wrapper\">{$msg}</span>\n";
            break;
        default :
            $output = $_lang['configcheck_default_msg'];
    }
    $config_check_result[] = sprintf(
'<fieldset style="padding:0;">
    <p><strong>%s</strong></p>
    <p style="padding-left:1em">%s%s</p>
</fieldset>
',
        $_lang[$warning],
        $output,
        $_SESSION['mgrRole'] != 1 ? '<br />' . $_lang['configcheck_admin'] : ''
    );
}
$_SESSION["mgrConfigCheck"] = true;
$config_check_results = implode("\n", $config_check_result);



function get_src_TemplateSwitcher_js($tplName) {
    global $_lang;

    $script =
        <<<EOT
<script type="text/javascript">
function deleteTemplateSwitcher(){
    if(confirm('{$_lang["confirm_delete_plugin"]}')) {
	\$j.post('index.php',{'a':'118','action':'updateplugin','key':'_delete_','lang':'{$tplName}'},function(resp)
	{
		var k = '#templateswitcher_present_warning_wrapper';
		\$j('fieldset:has(' + k + ')').fadeOut('slow');
	});
}
function disableTemplateSwitcher(){
    \$j.post('index.php', {'a':'118','action':'updateplugin','lang':'{$tplName}','key':'disabled','value':'1'}, function(resp)
    {
		var k = '#templateswitcher_present_warning_wrapper';
		\$j('fieldset:has(' + k + ')').fadeOut('slow');
    });
}
</script>
EOT;
    return $script;
}

function get_sc_value($field, $id) {
    if (empty($id)) {
        return true;
    }
    return db()->getValue(
        db()->select(
            $field,
            '[+prefix+]site_content',
            sprintf("id='%s'", $id)
        )
    );
}

function checkAjaxSearch() {
    $target_path = MODX_BASE_PATH . 'assets/snippets/ajaxSearch/classes/ajaxSearchConfig.class.inc.php';
    if (!is_file($target_path)) {
        return false;
    }
    if (strpos(file_get_contents($target_path), 'remove any @BINDINGS') !== false) {
        return false;
    }

    return true;
}
