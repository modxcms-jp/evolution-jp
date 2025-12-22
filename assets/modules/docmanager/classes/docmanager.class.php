<?php

class DocManager
{
    public $lang = [];
    public $theme = '';
    public $ph = [];
    private $fileRegister = [];

    function __construct()
    {
    }

    function getLang()
    {
        $_lang = [];
        $ph = [];
        $managerLanguage = config('manager_language', 'english');

        $userId = evo()->getLoginUserID();
        if ($userId) {
            $rs = db()->select(
                'setting_name, setting_value',
                '[+prefix+]user_settings',
                "setting_name='manager_language' AND user=" . $userId
            );
            $row = db()->getRow($rs);
            if ($row) {
                $managerLanguage = $row['setting_value'];
            }
        }

        $docmanager_lang_dir = MODX_BASE_PATH . 'assets/modules/docmanager/lang/';
        include MODX_CORE_PATH . 'lang/english.inc.php';
        include $docmanager_lang_dir . 'english.inc.php';
        if ($managerLanguage !== 'english') {
            if (is_file(MODX_CORE_PATH . 'lang/' . $managerLanguage . '.inc.php')) {
                include MODX_CORE_PATH . 'lang/' . $managerLanguage . '.inc.php';
            }
            if (is_file($docmanager_lang_dir . $managerLanguage . '.inc.php')) {
                include $docmanager_lang_dir . $managerLanguage . '.inc.php';
            }
        }
        $this->lang = $_lang;
        foreach ($_lang as $key => $value) {
            $ph['lang.' . $key] = $value;
        }
        return $ph;
    }

    function getTheme()
    {
        $theme = db()->select(
            'setting_value',
            '[+prefix+]system_settings',
            "setting_name='manager_theme'"
        );
        if (db()->getRecordCount($theme)) {
            $theme = db()->getRow($theme);
            if ($theme['setting_value'] != '') {
                $this->theme = '/' . $theme['setting_value'];
            } else {
                $this->theme = '';
            }
            return $this->theme;
        }

        return '';
    }

    function getFileContents($file)
    {
        if (empty($file)) {
            return false;
        }

        $file = MODX_BASE_PATH . 'assets/modules/docmanager/templates/' . $file;
        if (array_key_exists($file, $this->fileRegister)) {
            return $this->fileRegister[$file];
        }

        $contents = file_get_contents($file);
        $this->fileRegister[$file] = $contents;
        return $contents;
    }

    function parseTemplate($tpl, $ph = [])
    {
        global $modx;
        if (isset($this->fileRegister[$tpl])) {
            $tpl = $this->fileRegister[$tpl];
        } else {
            $tpl = $this->getFileContents($tpl);
        }
        if ($tpl) {
            if (strpos($tpl, '</body>') !== false) {
                $datePickerPath = $modx->config('mgr_date_picker_path', 'media/script/air-datepicker/datepicker.inc.php');
                $dp = manager()->loadDatePicker($datePickerPath);
                $tpl = str_replace('</body>', $dp . '</body>', $tpl);
            }
            $ph['settings_version'] = $modx->config('settings_version');
            return preg_replace(
                '/(\[\+.*?\+\])/',
                '',
                $modx->parseText(
                $modx->mergeSettingsContent($tpl),
                $ph
            )
            );
        }

        return '';
    }
}
