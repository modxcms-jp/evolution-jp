<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

$warnings = [];
if (ini_get('magic_quotes_gpc')) {
    $warnings[] = 'magic_quotes_gpc';
}
if (!checkAjaxSearch()) {
    $warnings[] = 'configcheck_danger_ajaxsearch';
}
if (!checkConfig()) {
    $warnings[] = 'configcheck_configinc';
}
if (is_file(MODX_BASE_PATH . "assets/templates/manager/login.html")) {
    $warnings[] = 'configcheck_mgr_tpl';
}
if (is_dir('../install/')) {
    $warnings[] = 'configcheck_installer';
}
if (!extension_loaded('gd')) {
    $warnings[] = 'configcheck_php_gdzip';
}
if (!checkValidateReferer()) {
    $warnings[] = 'configcheck_validate_referer';
}
if (!checkActionPhp()) {
    $warnings[] = 'configcheck_del_actionphp';
}
if (!checkTplSwitchPlugin()) {
    $warnings[] = 'configcheck_templateswitcher_present';
}
if (get_sc_value('published', $unauthorized_page) == 0) {
    $warnings[] = 'configcheck_unauthorizedpage_unpublished';
}
if (get_sc_value('published', $error_page) == 0) {
    $warnings[] = 'configcheck_errorpage_unpublished';
}
if (get_sc_value('privateweb', $unauthorized_page) == 1) {
    $warnings[] = 'configcheck_unauthorizedpage_unavailable';
}
if (get_sc_value('privateweb', $error_page) == 1) {
    $warnings[] = 'configcheck_errorpage_unavailable';
}
if (!is_writable(MODX_CACHE_PATH)) {
    $warnings[] = 'configcheck_cache';
}
if (!is_writable(evo()->config('rb_base_dir') . 'images')) {
    $warnings[] = 'configcheck_images';
}
if (!is_dir(evo()->config('rb_base_dir'))) {
    $warnings[] = 'configcheck_rb_base_dir';
}
if (!is_dir(evo()->config['filemanager_path'])) {
    $warnings[] = 'configcheck_filemanager_path';
}
if (sessionv('mgrRole') == 1) {
    $warnings[] = 'configcheck_you_are_admin';
}

// clear file info cache
clearstatcache();

if (!$warnings) {
    $config_check_results = $_lang['configcheck_ok'];
    return;
}

foreach ($warnings as $warning) {
    switch ($warning) {
        case 'magic_quotes_gpc':
            $output = 'magic_quotes_gpcが有効になっています。無効にしてください。';
            break;
        case 'configcheck_danger_ajaxsearch':
            $output = $_lang['configcheck_danger_ajaxsearch_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $_lang['configcheck_danger_ajaxsearch_msg'],
                    $_lang['configcheck_danger_ajaxsearch']
                );
            }
            break;
        case 'configcheck_configinc';
            $output = $_lang['configcheck_configinc_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    2,
                    $_lang['configcheck_configinc_msg'],
                    $_lang[$warning]
                );
            }
            break;
        case 'configcheck_you_are_admin':
            $output = $_lang['configcheck_you_are_admin_msg'];
            break;
        case 'configcheck_mgr_tpl':
            $output = evo()->parseText(
                $_lang['configcheck_mgr_tpl_msg'],
                [
                    'path' => urlencode(MODX_BASE_PATH)
                ]
            );
            break;
        case 'configcheck_installer':
            $output = $_lang['configcheck_installer_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $_lang['configcheck_installer_msg'],
                    $_lang[$warning]);
            }
            break;
        case 'configcheck_cache':
            $output = $_lang['configcheck_cache_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    2,
                    $_lang['configcheck_cache_msg'],
                    $_lang[$warning]
                );
            }
            break;
        case 'configcheck_images':
            $output = $_lang['configcheck_images_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    2,
                    $_lang['configcheck_images_msg'],
                    $_lang[$warning]
                );
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
            $output = '<span id="validate_referer_warning_wrapper">' . $msg . "</span>\n";
            break;
        case 'configcheck_del_actionphp':
            $output = $_lang['configcheck_del_actionphp_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $_lang['configcheck_del_actionphp_msg'],
                    $_lang[$warning]
                );
            }
            break;
        case 'configcheck_templateswitcher_present':
            $msg = $_lang['configcheck_templateswitcher_present_msg'];
            if (sessionv('mgrPermissions.save_plugin') == 1) {
                $msg .= '<br />' . $_lang["configcheck_templateswitcher_present_disable"];
            }
            if (sessionv('mgrPermissions.delete_plugin') == 1) {
                $msg .= '<br />' . $_lang["configcheck_templateswitcher_present_delete"];
            }
            $msg .= '<br />' . sprintf($_lang["configcheck_hide_warning"], 'templateswitcher_present');
            $output = '<span id="templateswitcher_present_warning_wrapper">' . $msg . "</span>\n";
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
        sessionv('mgrRole') != 1 ? '<br />' . $_lang['configcheck_admin'] : ''
    );
}
$_SESSION['mgrConfigCheck'] = true;
$config_check_results = "<h3>" . $_lang['configcheck_notok'] . "</h3>";
$config_check_results .= implode("\n", $config_check_result);


function get_src_TemplateSwitcher_js($tplName)
{
    global $_lang;

    $script =
        ">
function deleteTemplateSwitcher(){
    if(confirm('" . $_lang["confirm_delete_plugin"] . "')) {
	\$j.post('index.php',{'a':'118','action':'updateplugin','key':'_delete_','lang':'" . $tplName . "'},function(resp)
	{
		var k = '#templateswitcher_present_warning_wrapper';
		\$j('fieldset:has(' + k + ')').fadeOut('slow');
	});
}
function disableTemplateSwitcher(){
    \$j.post('index.php', {'a':'118','action':'updateplugin','lang':'" . $tplName . "','key':'disabled','value':'1'}, function(resp)
    {
		var k = '#templateswitcher_present_warning_wrapper';
		\$j('fieldset:has(' + k + ')').fadeOut('slow');
    });
}
</script>";
    return $script;
}

function get_sc_value($field, $id)
{
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

function checkAjaxSearch()
{
    $target_path = MODX_BASE_PATH . 'assets/snippets/ajaxSearch/classes/ajaxSearchConfig.class.inc.php';
    if (!is_file($target_path)) {
        return true;
    }
    if (strpos(file_get_contents($target_path), 'remove any @BINDINGS') !== false) {
        return true;
    }

    return false;
}

function checkConfig()
{
    if (!is_writable(MODX_CORE_PATH . 'config.inc.php')) {
        return true;
    }
    if (chmod(MODX_CORE_PATH . 'config.inc.php', 0444)) {
        return true;
    }
    return false;
}

function checkValidateReferer()
{
    if (evo()->config('_hide_configcheck_validate_referer')) {
        return true;
    }
    if (sessionv('mgrPermissions.settings') != 1) {
        return true;
    }
    $rs = db()->select(
        'setting_value',
        '[+prefix+]system_settings',
        "setting_name='validate_referer' AND setting_value=0"
    );
    if (!db()->count($rs)) {
        return true;
    }
    return false;
}

function checkActionPhp()
{
    $actionphp = MODX_BASE_PATH . 'action.php';
    if (!is_file($actionphp)) {
        return true;
    }
    $src = file_get_contents($actionphp);
    if (strpos($src, 'if(strpos($path,MODX_MANAGER_PATH)!==0)') !== false) {
        return true;
    }
    return false;
}

// check for Template Switcher plugin
function checkTplSwitchPlugin()
{
    if (evo()->config('_hide_configcheck_templateswitcher_present')) {
        return true;
    }
    if (!sessionv('mgrPermissions.edit_plugin')) {
        return true;
    }
    if (sessionv('mgrPermissions.edit_plugin') != 1) {
        return true;
    }
    $rs = db()->select(
        'name, disabled',
        '[+prefix+]site_plugins',
        [
            "name IN ('TemplateSwitcher','Template Switcher','templateswitcher','template_switcher','template switcher')",
            "OR plugincode LIKE '%TemplateSwitcher%'"
        ]
    );
    while ($row = db()->getRow($rs)) {
        if ($row['disabled'] != 0) {
            continue;
        }
        evo()->regClientScript(
            get_src_TemplateSwitcher_js($row['name'])
        );
        return false;
    }
    return true;
}
