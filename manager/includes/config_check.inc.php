<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

class ConfigCheck {
    private $warnings = '';
    private $_lang;
    private $config_check_result = [];

    public function __construct($_lang) {
        $this->_lang = $_lang;
    }

    public function run() {
        $warnings = $this->generateWarnings();

        // clear file info cache
        clearstatcache();

        if (!$warnings) {
            return $this->_lang['configcheck_ok'];
        }

        foreach ($warnings as $warning) {
            $this->config_check_result[] = sprintf(
                '<fieldset style="padding:0;">
    <p><strong>%s</strong></p>
    <p style="padding-left:1em">%s%s</p>
</fieldset>
',
                $this->_lang[$warning['title']],
                $warning['message'],
                !manager()->isAdmin() ? '<br />' . $this->_lang['configcheck_admin'] : ''
            );
        }
        $_SESSION['mgrConfigCheck'] = true;
        $this->warnings = "<h3>" . $this->_lang['configcheck_notok'] . "</h3>"
            . implode("\n", $this->config_check_result);
    }

    public function getWarnings() {
        return $this->warnings;
    }

    private function get_src_TemplateSwitcher_js($tplName) {
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

    private function get_sc_value($field, $id) {
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

    private function checkAjaxSearch() {
        $target_path = MODX_BASE_PATH . 'assets/snippets/ajaxSearch/classes/ajaxSearchConfig.class.inc.php';
        if (!is_file($target_path)) {
            return true;
        }
        if (strpos(file_get_contents($target_path), 'remove any @BINDINGS') !== false) {
            return true;
        }

        return false;
    }

    private function checkConfig() {
        if (!is_writable(MODX_CORE_PATH . 'config.inc.php')) {
            return true;
        }
        if (chmod(MODX_CORE_PATH . 'config.inc.php', 0444)) {
            return true;
        }
        return false;
    }

    private function checkActionPhp() {
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

    private function checkTplSwitchPlugin() {
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
                $this->get_src_TemplateSwitcher_js($row['name'])
            );
            return false;
        }
        return true;
    }

    /**
     * DBから設定値を直接取得（キャッシュをバイパス）
     *
     * @param string $key 設定キー
     * @return string|null 設定値（見つからない場合はnull）
     */
    private function getConfigFromDB($key) {
        $result = db()->getValue(
            db()->select('setting_value', db()->getFullTableName('system_settings'), "setting_name = '" . db()->escape($key) . "'")
        );
        return $result !== false ? $result : null;
    }

    private function generateWarnings() {
        $warnings = [];

        if (!$this->checkAjaxSearch()) {
            $output = $this->_lang['configcheck_danger_ajaxsearch_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $this->_lang['configcheck_danger_ajaxsearch_msg'],
                    $this->_lang['configcheck_danger_ajaxsearch']
                );
            }
            $warnings[] = [
                'title' => 'configcheck_danger_ajaxsearch',
                'message' => $output
            ];
        }

        if (!$this->checkConfig()) {
            $output = $this->_lang['configcheck_configinc_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    2,
                    $this->_lang['configcheck_configinc_msg'],
                    $this->_lang['configcheck_configinc']
                );
            }
            $warnings[] = [
                'title' => 'configcheck_configinc',
                'message' => $output
            ];
        }

        if (is_file(MODX_BASE_PATH . "assets/templates/manager/login.html")) {
            $warnings[] = [
                'title' => 'configcheck_mgr_tpl',
                'message' => evo()->parseText(
                    $this->_lang['configcheck_mgr_tpl_msg'],
                    [
                        'path' => urlencode(MODX_BASE_PATH)
                    ]
                )
            ];
        }

        if (is_dir('../install/')) {
            $output = $this->_lang['configcheck_installer_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $this->_lang['configcheck_installer_msg'],
                    $this->_lang['configcheck_installer']
                );
            }
            $warnings[] = [
                'title' => 'configcheck_installer',
                'message' => $output
            ];
        }

        if (!extension_loaded('gd')) {
            $warnings[] = [
                'title' => 'configcheck_php_gdzip',
                'message' => $this->_lang['configcheck_php_gdzip_msg']
            ];
        }

        if (!$this->checkActionPhp()) {
            $output = $this->_lang['configcheck_del_actionphp_msg'];
            if (!sessionv('mgrConfigCheck')) {
                evo()->logEvent(
                    0,
                    3,
                    $this->_lang['configcheck_del_actionphp_msg'],
                    $this->_lang['configcheck_del_actionphp']
                );
            }
            $warnings[] = [
                'title' => 'configcheck_del_actionphp',
                'message' => $output
            ];
        }

        if (!$this->checkTplSwitchPlugin()) {
            $msg = $this->_lang['configcheck_templateswitcher_present_msg'];
            if (sessionv('mgrPermissions.save_plugin') == 1) {
                $msg .= '<br />' . $this->_lang["configcheck_templateswitcher_present_disable"];
            }
            if (sessionv('mgrPermissions.delete_plugin') == 1) {
                $msg .= '<br />' . $this->_lang["configcheck_templateswitcher_present_delete"];
            }
            $msg .= '<br />' . sprintf($this->_lang["configcheck_hide_warning"], 'templateswitcher_present');
            $warnings[] = [
                'title' => 'configcheck_templateswitcher_present',
                'message' => '<span id="templateswitcher_present_warning_wrapper">' . $msg . "</span>\n"
            ];
        }

        if ($this->get_sc_value('published', config('unauthorized_page')) == 0) {
            $warnings[] = [
                'title' => 'configcheck_unauthorizedpage_unpublished',
                'message' => $this->_lang['configcheck_unauthorizedpage_unpublished_msg']
            ];
        }

        if ($this->get_sc_value('published', config('error_page')) == 0) {
            $warnings[] = [
                'title' => 'configcheck_errorpage_unpublished',
                'message' => $this->_lang['configcheck_errorpage_unpublished_msg']
            ];
        }

        if ($this->get_sc_value('privateweb', config('unauthorized_page')) == 1) {
            $warnings[] = [
                'title' => 'configcheck_unauthorizedpage_unavailable',
                'message' => $this->_lang['configcheck_unauthorizedpage_unavailable_msg']
            ];
        }

        if ($this->get_sc_value('privateweb', config('error_page')) == 1) {
            $warnings[] = [
                'title' => 'configcheck_errorpage_unavailable',
                'message' => $this->_lang['configcheck_errorpage_unavailable_msg']
            ];
        }

        if (!is_writable(MODX_CACHE_PATH)) {
            $warnings[] = [
                'title' => 'configcheck_cache',
                'message' => $this->_lang['configcheck_cache_msg']
            ];
        }

        $rbBaseDir = evo()->config('rb_base_dir');
        $rbBaseDir = str_replace('[(base_path)]', MODX_BASE_PATH, $rbBaseDir);

        $hasRbBaseDir = is_string($rbBaseDir) && $rbBaseDir !== '';
        if ($hasRbBaseDir && !is_writable($rbBaseDir . 'images')) {
            $warnings[] = [
                'title' => 'configcheck_images',
                'message' => $this->_lang['configcheck_images_msg']
            ];
        }

        if (!$hasRbBaseDir || !is_dir($rbBaseDir)) {
            $warnings[] = [
                'title' => 'configcheck_rb_base_dir',
                'message' => '$modx->config[\'rb_base_dir\']'
            ];
        }

        $filemanagerPath = evo()->config('filemanager_path');
        $filemanagerPath = str_replace('[(base_path)]', MODX_BASE_PATH, $filemanagerPath);

        $hasFilemanagerPath = is_string($filemanagerPath) && $filemanagerPath !== '';
        if (!$hasFilemanagerPath || !is_dir($filemanagerPath)) {
            $warnings[] = [
                'title' => 'configcheck_filemanager_path',
                'message' => '$modx->config[\'filemanager_path\']'
            ];
        }

        if (sessionv('mgrRole') == 1) {
            $warnings[] = [
                'title' => 'configcheck_you_are_admin',
                'message' => $this->_lang['configcheck_you_are_admin_msg']
            ];
        }

        return $warnings;
    }
}

$configCheck = new ConfigCheck($_lang);
$config_check_results = $configCheck->run();
