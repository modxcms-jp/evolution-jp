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
if (evo()->hasPermission('settings') && $settings_version != $modx_version) {
    // seems to be a new install - send the user to the configuration page
    echo '<script>document.location.href="index.php?a=17";</script>';
    exit;
}

$uid = evo()->getLoginUserID();

$script = <<<JS
        <script>
        function hideConfigCheckWarning(key){
            \$j.post('index.php', {'a':'118','action':'setsetting','key':'_hide_configcheck_' + key,'value':'1'},function(resp)
            {
                var k = '#' + key + '_warning_wrapper';
                \$j('fieldset:has(' + k + ')').fadeOut('slow');
            });
        }
        </script>

JS;
$modx->regClientScript($script);

// set placeholders
$modx->setPlaceholder('theme', $manager_theme ? $manager_theme : '');
$modx->setPlaceholder('home', $_lang["home"]);
$modx->setPlaceholder('logo_slogan', $_lang["logo_slogan"]);
$modx->setPlaceholder('site_name', $site_name);
$modx->setPlaceholder('welcome_title', $_lang['welcome_title']);
$modx->setPlaceholder('site', $_lang['site']);
$modx->setPlaceholder('info', $_lang['info']);

// setup message info
if (evo()->hasPermission('messages')) {
    $messages = manager()->getMessageCount();
    $_SESSION['nrtotalmessages'] = $messages['total'];
    $_SESSION['nrnewmessages'] = $messages['new'];

    $msg = '<a href="index.php?a=10"><img src="' . $_style['icons_mail_large'] . '" /></a>
    <span class="inbox-title">&nbsp;' . $_lang["inbox"] . (sessionv('nrnewmessages') > 0 ? " (<span class='message-count-new'>" . sessionv('nrnewmessages') . '</span>)' : '') . '</span><br />';
    if (sessionv('nrnewmessages') > 0) {
        $msg .= '<span class="comment">'
            . sprintf(
                $_lang["welcome_messages"],
                sessionv('nrtotalmessages'),
                "<span class='message-count-new'>" . sessionv('nrnewmessages') . "</span>"
            ) . '</span>';
        $mail_icon = $_style['icons_mail_new_large'];
    } else {
        $msg .= '<span class="comment">' . $_lang["messages_no_messages"] . '</span>';
        $mail_icon = $_style['icons_mail_large'];
    }
    $modx->setPlaceholder('MessageInfo', $msg);
    $src = get_icon($_lang['inbox'], 10, $mail_icon, $_lang['inbox']);
    $modx->setPlaceholder('MessageIcon', $src);
}

// setup icons
if (evo()->hasPermission('new_document') || evo()->hasPermission('save_document')) {
    if (!isset($_style['icons_newdoc_large'])) {
        $_style['icons_newdoc_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/newdoc.png';
    }
    $src = get_icon($_lang['add_resource'], 4, $_style['icons_newdoc_large'], $_lang['add_resource']);
    $modx->setPlaceholder('NewDocIcon', $src);
}
if (evo()->hasPermission('view_document')) {
    $src = get_icon(
        $_lang['view_child_resources_in_container'],
        120,
        $_style['icons_resources_large'],
        $_lang['view_child_resources_in_container']
    );
    $modx->setPlaceholder('iconResources', $src);
}
if (evo()->hasPermission('edit_user')) {
    $src = get_icon($_lang['security'], 75, $_style['icons_security_large'], $_lang['user_management_title']);
    $modx->setPlaceholder('SecurityIcon', $src);
}
if (evo()->hasPermission('edit_web_user')) {
    $src = get_icon($_lang['web_users'], 99, $_style['icons_webusers_large'], $_lang['web_user_management_title']);
    $modx->setPlaceholder('WebUserIcon', $src);
}
if (evo()->hasPermission('new_module') || evo()->hasPermission('edit_module')) {
    $src = get_icon($_lang['modules'], 106, $_style['icons_modules_large'], $_lang['manage_modules']);
    $modx->setPlaceholder('ModulesIcon', $src);
}
if (evo()->hasPermission('new_template') || evo()->hasPermission('edit_template') || evo()->hasPermission('new_snippet') || evo()->hasPermission('edit_snippet') || evo()->hasPermission('new_plugin') || evo()->hasPermission('edit_plugin')) {
    if (!isset($_style['icons_elements_large'])) {
        $_style['icons_elements_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/elements.png';
    }
    $src = get_icon($_lang['element_management'], 76, $_style['icons_elements_large'], $_lang['element_management']);
    $modx->setPlaceholder('ResourcesIcon', $src);
}
if (evo()->hasPermission('bk_manager')) {
    $src = get_icon($_lang['backup'], 93, $_style['icons_backup_large'], $_lang['bk_manager']);
    $modx->setPlaceholder('BackupIcon', $src);
}
if (evo()->hasPermission('help')) {
    if (!isset($_style['icons_help_large'])) {
        $_style['icons_help_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/help.png';
    }
    $src = get_icon($_lang['help'], 9, $_style['icons_help_large'], $_lang['help']);
    $modx->setPlaceholder('HelpIcon', $src);
}


if (evo()->hasPermission('file_manager')) {
    if (!isset($_style['icons_files_large'])) {
        $_style['icons_files_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/files.png';
    }
    $src = get_icon($_lang['manage_files'], 31, $_style['icons_files_large'], $_lang['manage_files']);
    $modx->setPlaceholder('FileManagerIcon', $src);
}
if (evo()->hasPermission('new_user') || evo()->hasPermission('edit_user')) {
    $src = get_icon($_lang['security'], 75, $_style['icons_security_large'], $_lang['user_management_title']);
    $modx->setPlaceholder('UserManagerIcon', $src);
}
if (evo()->hasPermission('new_web_user') || evo()->hasPermission('edit_web_user')) {
    $src = get_icon($_lang['web_users'], 99, $_style['icons_webusers_large'], $_lang['web_user_management_title']);
    $modx->setPlaceholder('WebUserManagerIcon', $src);
}
if (evo()->hasPermission('view_eventlog')) {
    if (!isset($_style['icons_log_large'])) {
        $_style['icons_log_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/log.png';
    }
    $src = get_icon($_lang['eventlog'], 114, $_style['icons_log_large'], $_lang['eventlog']);
    $modx->setPlaceholder('EventLogIcon', $src);
}
if (evo()->hasPermission('logs')) {
    if (!isset($_style['icons_sysinfo_large'])) {
        $_style['icons_sysinfo_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/info.png';
    }
    $src = get_icon($_lang['view_sysinfo'], 53, $_style['icons_sysinfo_large'], $_lang['view_sysinfo']);
    $modx->setPlaceholder('SysInfoIcon', $src);
}
if (!isset($_style['icons_search_large'])) {
    $_style['icons_search_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/search.png';
}
$src = get_icon($_lang['search_resource'], 71, $_style['icons_search_large'], $_lang['search_resource']);
$modx->setPlaceholder('SearchIcon', $src);

if (evo()->hasPermission('settings')) {
    if (!isset($_style['icons_settings_large'])) {
        $_style['icons_settings_large'] = MODX_MANAGER_URL . 'media/style/common/images/icons/32x/settings.png';
    }
    $src = get_icon($_lang['edit_settings'], 17, $_style['icons_settings_large'], $_lang['edit_settings']);
    $modx->setPlaceholder('SettingsIcon', $src);
}

// setup modules
$modulemenu = [];
if (evo()->hasPermission('exec_module')) {
    // Each module
    if (!manager()->isAdmin()) {
        // Display only those modules the user can execute
        $tbl_site_modules = evo()->getFullTableName('site_modules');
        $tbl_site_module_access = evo()->getFullTableName('site_module_access');
        $tbl_member_groups = evo()->getFullTableName('member_groups');
        $field = 'sm.id, sm.name, mg.member, sm.editedon';
        $from = "{$tbl_site_modules} AS sm";
        $from .= " LEFT JOIN {$tbl_site_module_access} AS sma ON sma.module = sm.id";
        $from .= " LEFT JOIN {$tbl_member_groups} AS mg ON sma.usergroup = mg.user_group";
        $where = "(mg.member IS NULL OR mg.member={$uid}) AND sm.disabled != 1";
        $rs = db()->select($field, $from, $where, 'sm.editedon DESC');
    } else {
        // Admins get the entire list
        $rs = db()->select('id,name,icon', evo()->getFullTableName('site_modules'), 'disabled != 1', 'editedon DESC');
    }
    while ($content = db()->getRow($rs)) {
        if (empty($content['icon'])) {
            $content['icon'] = $_style['icons_modules'];
        }
        $action = 'index.php?a=112&amp;id=' . $content['id'];
        $modulemenu[] = get_icon($content['name'], $action, $content['icon'], $content['name']);
    }
}
$modules = '';
if (0 < count($modulemenu)) {
    $modules = implode("\n", $modulemenu);
}
$modx->setPlaceholder('Modules', $modules);

// do some config checks
if (config('warning_visibility' == 0 && manager()->isAdmin())
    || (config('warning_visibility') == 2 && evo()->hasPermission('save_role') == 1)
    || config('warning_visibility') == 1
) {
    include_once(MODX_CORE_PATH . 'config_check.inc.php');
    $configCheck = new ConfigCheck($_lang);
    $configCheck->run();
    $config_check_results = $configCheck->getWarnings();

    $modx->setPlaceholder('settings_config', $_lang['warning']);
    $modx->setPlaceholder('configcheck_title', $_lang['configcheck_title']);
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
        $action = 'index.php?a=' . $action;
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
