<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

set_manager_style_placeholders();

unset($_SESSION['itemname']); // clear this, because it's only set for logging purposes

$settings_version = db()->getValue(
    'setting_value',
    evo()->getFullTableName('system_settings'),
    'setting_name="settings_version"'
);
// settings_version は「システム設定を保存した版、またはアップデーターで上げた版」。'0' か空は、一度も保存していない新規インストール
if (evo()->hasPermission('settings') && in_array((string) $settings_version, ['', '0'], true)) {
    // seems to be a new install - send the user to the configuration page
    echo '<script>document.location.href="index.php?a=17";</script>';
    exit;
}

$script = <<<JS
        <script>
        function hideConfigCheckWarning(key){
            Evo.post('index.php', {'a':'118','action':'setsetting','key':'_hide_configcheck_' + key,'value':'1'}).then(function(response)
            {
                // 保存できたときだけ（本文が 'true'）、警告の入った fieldset を隠す
                return response.ok ? response.text() : '';
            }).then(function (body) {
                if (body.trim() !== 'true') return;
                var el = document.getElementById(key + '_warning_wrapper');
                var fieldset = el ? el.closest('fieldset') : null;
                if (fieldset) fieldset.style.display = 'none';
            }).catch(function () {});
        }
        </script>

JS;
$modx->regClientScript($script);

// set placeholders
$modx->setPlaceholder('theme', config('manager_theme') ?: '');
$modx->setPlaceholder('home', lang("home"));
$modx->setPlaceholder('logo_slogan', lang("logo_slogan"));
$modx->setPlaceholder('site_name', hsc(config('site_name')));
$modx->setPlaceholder('welcome_title', lang('welcome_title'));
$modx->setPlaceholder('site', lang('site'));
$modx->setPlaceholder('info', lang('info'));

// setup icons
if (manager()->canCreateDocumentUnder(0)) {
    if (!isset($_style['icons_newdoc_large'])) {
        $_style['icons_newdoc_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/newdoc.png';
    }
    $src = get_icon(lang('add_resource'), 4, style('icons_newdoc_large'), lang('add_resource'));
    $modx->setPlaceholder('NewDocIcon', $src);
}
if (evo()->hasPermission('view_document')) {
    $src = get_icon(
        lang('view_child_resources_in_container'),
        120,
        style('icons_resources_large'),
        lang('view_child_resources_in_container')
    );
    $modx->setPlaceholder('iconResources', $src);
}
if (manager()->can('user.edit')) {
    $src = get_icon(lang('security'), 75, style('icons_security_large'), lang('user_management_title'));
    $modx->setPlaceholder('SecurityIcon', $src);
}
if (manager()->can('webuser.edit')) {
    $src = get_icon(lang('web_users'), 99, style('icons_webusers_large'), lang('web_user_management_title'));
    $modx->setPlaceholder('WebUserIcon', $src);
}
if (manager()->canManageElement('module')) {
    $src = get_icon(lang('modules'), 106, style('icons_modules_large'), lang('manage_modules'));
    $modx->setPlaceholder('ModulesIcon', $src);
}
if (manager()->canOpenElementManager()) {
    if (!isset($_style['icons_elements_large'])) {
        $_style['icons_elements_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/elements.png';
    }
    $src = get_icon(lang('element_management'), 76, style('icons_elements_large'), lang('element_management'));
    $modx->setPlaceholder('ResourcesIcon', $src);
}
if (evo()->hasPermission('bk_manager')) {
    $src = get_icon(lang('backup'), 93, style('icons_backup_large'), lang('bk_manager'));
    $modx->setPlaceholder('BackupIcon', $src);
}
if (evo()->hasPermission('help')) {
    if (!isset($_style['icons_help_large'])) {
        $_style['icons_help_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/help.png';
    }
    $src = get_icon(lang('help'), 9, style('icons_help_large'), lang('help'));
    $modx->setPlaceholder('HelpIcon', $src);
}


if (evo()->hasPermission('file_manager')) {
    if (!isset($_style['icons_files_large'])) {
        $_style['icons_files_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/files.png';
    }
    $src = get_icon(lang('manage_files'), 31, style('icons_files_large'), lang('manage_files'));
    $modx->setPlaceholder('FileManagerIcon', $src);
}
if (manager()->can('user.edit')) {
    $src = get_icon(lang('security'), 75, style('icons_security_large'), lang('user_management_title'));
    $modx->setPlaceholder('UserManagerIcon', $src);
}
if (manager()->can('webuser.edit')) {
    $src = get_icon(lang('web_users'), 99, style('icons_webusers_large'), lang('web_user_management_title'));
    $modx->setPlaceholder('WebUserManagerIcon', $src);
}
if (evo()->hasPermission('view_eventlog')) {
    if (!isset($_style['icons_log_large'])) {
        $_style['icons_log_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/log.png';
    }
    $src = get_icon(lang('eventlog'), 114, style('icons_log_large'), lang('eventlog'));
    $modx->setPlaceholder('EventLogIcon', $src);
}
if (evo()->hasPermission('logs')) {
    if (!isset($_style['icons_sysinfo_large'])) {
        $_style['icons_sysinfo_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/info.png';
    }
    $src = get_icon(lang('view_sysinfo'), 53, style('icons_sysinfo_large'), lang('view_sysinfo'));
    $modx->setPlaceholder('SysInfoIcon', $src);
}
if (!isset($_style['icons_search_large'])) {
    $_style['icons_search_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/search.png';
}
$src = get_icon(lang('search_resource'), 71, style('icons_search_large'), lang('search_resource'));
$modx->setPlaceholder('SearchIcon', $src);

if (evo()->hasPermission('settings')) {
    if (!isset($_style['icons_settings_large'])) {
        $_style['icons_settings_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/settings.png';
    }
    $src = get_icon(lang('edit_settings'), 17, style('icons_settings_large'), lang('edit_settings'));
    $modx->setPlaceholder('SettingsIcon', $src);
}

// setup modules
$modulemenu = [];
// 実行してよいモジュールだけ（判定は ManagerAPI::canExecuteModule()。実行の処理と同じ）
foreach (manager()->getExecutableModules() as $content) {
    if (empty($content['icon'])) {
        $content['icon'] = style('icons_modules');
    }
    $action = 'index.php?a=112&amp;id=' . (int)$content['id'];
    $modulemenu[] = get_icon(hsc($content['name']), $action, hsc($content['icon']), hsc($content['name']));
}
$modules = '';
if (0 < count($modulemenu)) {
    $modules = implode("\n", $modulemenu);
}
$modx->setPlaceholder('Modules', $modules);

// do some config checks
if (
    (config('warning_visibility') == 0 && manager()->isAdmin())
    || (config('warning_visibility') == 2 && evo()->hasPermission('save_role') == 1)
    || config('warning_visibility') == 1
) {
    include_once(MODX_CORE_PATH . 'config_check.inc.php');
    $configCheck = new ConfigCheck($_lang);
    $configCheck->run();
    $config_check_results = $configCheck->getWarnings();

    $modx->setPlaceholder('settings_config', lang('warning'));
    $modx->setPlaceholder('configcheck_title', lang('configcheck_title'));
    if ($config_check_results) {
        $modx->setPlaceholder('config_check_results', $config_check_results);
        $modx->setPlaceholder('config_display', 'block');
    } else {
        $modx->setPlaceholder('config_display', 'none');
    }
} else {
    $modx->setPlaceholder('config_display', 'none');
}

// load template file
global $tpl;
// invoke event OnManagerWelcomePrerender
$modx->event->vars = [];
$modx->event->vars['tpl'] = &$tpl;
$evtOut = evo()->invokeEvent('OnManagerWelcomePrerender');
if (is_array($evtOut)) {
    $output = implode('', $evtOut);
    $modx->setPlaceholder('OnManagerWelcomePrerender', $output);
}

// invoke event OnManagerWelcomeHome
$evtOut = evo()->invokeEvent('OnManagerWelcomeHome');
if (is_array($evtOut)) {
    $output = implode('', $evtOut);
    $modx->setPlaceholder('OnManagerWelcomeHome', $output);
}

// invoke event OnManagerWelcomeRender
$evtOut = evo()->invokeEvent('OnManagerWelcomeRender');
$modx->event->vars = [];
if (is_array($evtOut)) {
    $output = implode('', $evtOut);
    $modx->setPlaceholder('OnManagerWelcomeRender', $output);
}

// merge placeholders
$welcome_tpl = $modx->parseDocumentSource(welcomeTpl($tpl));
if ($js = $modx->getRegisteredClientScripts()) {
    $welcome_tpl .= $js;
}
$welcome_tpl = preg_replace('~\[\+(.*?)\+]~', '', $welcome_tpl); //cleanup
echo $welcome_tpl;

function get_icon($title, $action, $icon_path, $alt = '')
{
    if (preg_match('@^[1-9][0-9]*$@', $action)) {
        $action = (int)$action === 2 ? './' : 'index.php?a=' . $action;
    }
    $icon = '<a class="hometblink" href="' . $action . '" alt="' . $alt . '"><img src="' . $icon_path . '" /><br />' . $title . "</a>\n";
    return '<span class="wm_button" style="border:0">' . $icon . '</span>';
}

function welcomeTpl($tpl)
{
    if (!empty($tpl)) {
        return $tpl;
    }

    $style_path = MODX_MANAGER_PATH . 'media/style/';
    if (empty(evo()->config('manager_welcome_tpl'))) {
        if (is_file($style_path . evo()->config('manager_theme') . '/welcome.tpl')) {
            return file_get_contents(
                $style_path . evo()->config('manager_theme') . '/welcome.tpl'
            );
        }
        return file_get_contents($style_path . 'common/welcome.tpl');
    }


    $target = evo()->config('manager_welcome_tpl');
    if (strpos($target, '@') === 0) {
        $result = evo()->atBind($target);
        if ($result) {
            return $result;
        }
    }

    $chunk = evo()->getChunk($target);
    if (!empty($chunk)) {
        return $chunk;
    }
    if (is_file(MODX_BASE_PATH . $target)) {
        $target = MODX_BASE_PATH . $target;
        return file_get_contents(MODX_BASE_PATH . $target);
    }
    return file_get_contents($style_path . 'common/welcome.tpl');
}
