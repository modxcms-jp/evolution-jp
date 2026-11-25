<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

require_once MODX_CORE_PATH . 'special_pages.class.inc.php';
require_once MODX_CORE_PATH . 'retired_files.class.inc.php';

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

    private function pageSettingWarnings() {
        $warnings = [];
        foreach (EvoSpecialPages::all() as $key => [$name, $titleKey]) {
            $id = (int) config($key);
            if (!$id) {
                continue;
            }
            $problem = EvoSpecialPages::problem($id, $doc);
            if ($problem === 'missing' || $problem === 'deleted') {
                $warnings[] = [
                    'title' => "configcheck_{$name}_{$problem}",
                    'message' => sprintf($this->_lang["configcheck_page_{$problem}_msg"], $this->_lang[$titleKey], $id)
                ];
                continue;
            }
            if ($problem === 'unpublished') {
                $warnings[] = [
                    'title' => "configcheck_{$name}_unpublished",
                    'message' => $this->_lang["configcheck_{$name}_unpublished_msg"]
                ];
            }
            if ($key !== 'site_unavailable_page' && $doc['privateweb']) {
                $warnings[] = [
                    'title' => "configcheck_{$name}_unavailable",
                    'message' => $this->_lang["configcheck_{$name}_unavailable_msg"]
                ];
            }
        }
        return $warnings;
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

    /**
     * 有効な TemplateSwitcher のプラグイン（名前が一致するもの）の一覧。警告を出さないときは空
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function findTplSwitchPlugins() {
        if (evo()->config('_hide_configcheck_templateswitcher_present')) {
            return [];
        }
        if (!evo()->hasPermission('edit_plugin')) {
            return [];
        }
        $rs = db()->select(
            'id, name',
            '[+prefix+]site_plugins',
            "name='TemplateSwitcher' AND disabled=0",
            'id ASC'
        );
        $plugins = [];
        while ($row = db()->getRow($rs)) {
            $plugins[] = $row;
        }
        return $plugins;
    }

    private function templateSwitcherWarning(array $plugins) {
        $canDisable = evo()->hasPermission('save_plugin');
        $canDelete = evo()->hasPermission('delete_plugin');
        $msg = $this->_lang['configcheck_templateswitcher_present_msg'];
        foreach ($plugins as $plugin) {
            $id = (int) $plugin['id'];
            // 確認ダイアログは文言を HTML として出すので、名前はここでエスケープする（属性へは更に hsc する）
            $pluginName = hsc($plugin['name']);
            $links = [];
            if ($canDisable) {
                $links[] = sprintf(
                    '<a href="#" data-config-check-plugin="disable" data-plugin-id="%d" data-confirm="%s">%s</a>',
                    $id,
                    hsc(sprintf($this->_lang['configcheck_templateswitcher_confirm_disable'], $pluginName, $id)),
                    $this->_lang['configcheck_templateswitcher_disable_label']
                );
            }
            if ($canDelete) {
                $links[] = sprintf(
                    '<a href="#" data-config-check-plugin="delete" data-plugin-id="%d" data-confirm="%s">%s</a>',
                    $id,
                    hsc(sprintf($this->_lang['configcheck_templateswitcher_confirm_delete'], $pluginName, $id)),
                    $this->_lang['configcheck_templateswitcher_delete_label']
                );
            }
            $msg .= sprintf(
                '<span class="templateswitcher-plugin" id="templateswitcher_plugin_%d" style="display:block">%s (ID: %d)%s</span>',
                $id,
                hsc($plugin['name']),
                $id,
                $links ? ' - ' . implode(' | ', $links) : ''
            );
        }
        if (evo()->hasPermission('settings')) {
            $msg .= sprintf($this->_lang['configcheck_hide_warning'], hsc('templateswitcher_present'));
        }
        return '<span id="templateswitcher_present_warning_wrapper">' . $msg . "</span>\n";
    }

    /**
     * ファイル管理(a=31)の folder に渡す値。$path がファイル管理のルートの中なら、ルートからの相対パスを URL エンコードしたもの。
     * ルートの外にあるとき(filemanager_path がサイトのルートの下の別の場所のとき)や解決できないときは空(ルートを開く)
     */
    private function fileManagerFolderFor($path) {
        $libPath = MODX_MANAGER_PATH . 'media/browser/evo/lib/';
        require_once $libPath . 'PathGuard.php';
        require_once $libPath . 'BrowserScope.php';
        require_once $libPath . 'ManageScope.php';
        $rootPath = ManageScope::rootPath(evo());
        $root = PathGuard::normalize($rootPath);
        $target = PathGuard::normalize($path);
        if ($root === false || $target === false || !(new PathGuard($rootPath))->isWithinBase($path)) {
            return '';
        }
        $root = rtrim($root, '/') . '/';

        return rawurlencode(trim(substr($target . '/', strlen($root)), '/'));
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

    /**
     * config.inc.php で Unicode の接続を指定しているのに、接続の文字コードが Unicode でないときの、DB の名前。それ以外は null。
     * SET CHARACTER SET は接続の文字コードを DB の既定にするので、原因は DB の既定の文字セット
     */
    private function nonUnicodeConnectionDatabase() {
        if (strpos((string) db()->charset, 'utf8') !== 0) {
            return null;
        }
        $charset = (string) db()->getValue(db()->query('SELECT @@character_set_connection'));
        if ($charset === '' || strpos($charset, 'utf8') === 0) {
            return null;
        }
        return db()->dbase;
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
                        'folder' => $this->fileManagerFolderFor(MODX_BASE_PATH . 'assets/templates/manager')
                    ]
                )
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

        // 配布から外したファイルが残っている
        $retiredDirs = EvoRetiredFiles::remainingDirs();
        if ($retiredDirs) {
            $warnings[] = [
                'title' => 'configcheck_retired_files',
                'message' => evo()->parseText(
                    $this->_lang['configcheck_retired_files_msg'],
                    ['folders' => hsc_for_tpl(implode(', ', $retiredDirs))]
                )
            ];
        }

        $tplSwitchPlugins = $this->findTplSwitchPlugins();
        if ($tplSwitchPlugins) {
            $warnings[] = [
                'title' => 'configcheck_templateswitcher_present',
                'message' => $this->templateSwitcherWarning($tplSwitchPlugins)
            ];
        }

        $nonUnicodeDb = $this->nonUnicodeConnectionDatabase();
        if ($nonUnicodeDb !== null) {
            $warnings[] = [
                'title' => 'configcheck_connection_charset',
                'message' => evo()->parseText(
                    $this->_lang['configcheck_connection_charset_msg'],
                    ['dbase' => hsc_for_tpl($nonUnicodeDb)]
                )
            ];
        }

        foreach ($this->pageSettingWarnings() as $warning) {
            $warnings[] = $warning;
        }

        if (!is_writable(MODX_CACHE_PATH)) {
            $warnings[] = [
                'title' => 'configcheck_cache',
                'message' => $this->_lang['configcheck_cache_msg']
            ];
        }

        // rb_base_dir と filemanager_path はDB から直接取得（キャッシュタイミング問題を回避）
        $rbBaseDir = $this->getConfigFromDB('rb_base_dir') ?: evo()->config('rb_base_dir');
        $rbBaseDir = str_replace('[(base_path)]', MODX_BASE_PATH, $rbBaseDir);

        if (!is_writable($rbBaseDir . 'images')) {
            $warnings[] = [
                'title' => 'configcheck_images',
                'message' => $this->_lang['configcheck_images_msg']
            ];
        }

        $hasRbBaseDir = is_string($rbBaseDir) && $rbBaseDir !== '';
        if (!$hasRbBaseDir || !is_dir($rbBaseDir)) {
            $warnings[] = [
                'title' => 'configcheck_rb_base_dir',
                'message' => '$modx->config[\'rb_base_dir\']'
            ];
        }

        $filemanagerPath = $this->getConfigFromDB('filemanager_path') ?: evo()->config('filemanager_path');
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
