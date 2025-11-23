<?php

class CKEditor5
{
    public $params;

    function __construct()
    {
        global $modx;
        $this->params = $modx->event->params;
        $this->params['cke_path'] = MODX_BASE_PATH . 'assets/plugins/ckeditor5-classic/';
        $this->params['cke_url'] = MODX_BASE_URL . 'assets/plugins/ckeditor5-classic/';
    }

    function get_lang($lang)
    {
        switch (strtolower($lang)) {
            case 'russian-utf8' :
                $lc = 'ru';
                break;
            case 'japanese-utf8':
            case 'japanese-euc' :
                $lc = 'ja';
                break;
            default             :
                $lc = 'en';
        }
        return $lc;
    }

    function selected($cond = false)
    {
        if ($cond !== false) return ' selected="selected"';
        else                return '';
    }

    function checked($cond = false)
    {
        if ($cond !== false) return ' checked="checked"';
        else                return '';
    }

    function get_ckeditor_settings()
    {
        global $modx, $_lang, $usersettings, $settings;
        $params = &$this->params;
        $cke_path = $params['cke_path'];
        $ph = array();

        switch ($modx->manager->action) {
            case 11:
                $config = array();
                break;
            case 12:
            case 74:
                $config = $usersettings;
                if ($usersettings['ckeditor5_editor_theme']) {
                    $usersettings['ckeditor5_editor_theme'] = $settings['ckeditor5_editor_theme'];
                }
                break;
            case 17:
            default:
                $config = $settings;
                break;
        }
        $params['theme'] = $config['ckeditor5_editor_theme'] ?? 'default';
        $params['cke_entermode'] = $config['cke_entermode'] ?? 'p';
        $params['css_selectors'] = $config['ckeditor5_css_selectors'] ?? '';
        $params['custom_config'] = $config['ckeditor5_custom_config'] ?? '';

        // language settings
        if (!@include($cke_path . "lang/" . $modx->config['manager_language'] . '.inc.php')) {
            include_once("{$cke_path}lang/english.inc.php");
        }

        $ph += $_lang;

        $theme_options = '';
        switch ($modx->manager->action) {
            case '11';
            case '12';
            case '74';
                $selected = empty($params['theme']) ? '"selected"' : '';
                $theme_options .= '<option value="" ' . $selected . '>' . $_lang['cke_theme_global_settings'] . "</option>\n";
        }
        $themes['simple'] = $_lang['cke_theme_simple'];
        $themes['default'] = $_lang['cke_theme_default'];
        $themes['full'] = $_lang['cke_theme_full'];
        $themes['custom'] = $_lang['cke_theme_custom'];
        foreach ($themes as $key => $value) {
            $selected = $this->selected($key == $params['theme']);
            $key = '"' . $key . '"';
            $theme_options .= "<option value={$key}{$selected}>{$value}</option>\n";
        }
        $ph['display'] = (isset($_SESSION['browser']) && $_SESSION['browser'] === 'modern') ? 'table-row' : 'block';
        $ph['display'] = $modx->config['use_editor'] == 1 ? $ph['display'] : 'none';

        $ph['theme_options'] = $theme_options;

        $ph['entermode_options'] = '<label><input name="cke_entermode" type="radio" value="p" ' . $this->checked($params['cke_entermode'] === 'p') . '/>' . $_lang['cke_entermode_opt1'] . '</label><br />';
        $ph['entermode_options'] .= '<label><input name="cke_entermode" type="radio" value="br" ' . $this->checked($params['cke_entermode'] === 'br') . '/>' . $_lang['cke_entermode_opt2'] . '</label>';
        switch ($modx->manager->action) {
            case '11':
            case '12':
            case '74':
                $ph['entermode_options'] .= '<br />';
                $ph['entermode_options'] .= '<label><input name="cke_entermode" type="radio" value="" ' . $this->checked(empty($params['cke_entermode'])) . '/>' . $_lang['cke_theme_global_settings'] . '</label><br />';
                break;
        }

        $gsettings = file_get_contents("{$cke_path}inc/gsettings.inc.html");

        foreach ($ph as $name => $value) {
            $name = '[+' . $name . '+]';
            $gsettings = str_replace($name, $value, $gsettings);
        }
        return $gsettings;
    }

    function get_ckeditor_script()
    {
        global $modx;
        $params = &$this->params;
        $cke_path = $params['cke_path'];
        $cke_url = $params['cke_url'];

        $params['css_selectors'] = $modx->config['ckeditor5_css_selectors'] ?? '';
        $params['use_browser'] = $modx->config['use_browser'];
        $params['editor_css_path'] = $modx->config['editor_css_path'];

        if ($modx->isBackend() || (isset($_GET['quickmanagertv']) && (int)$_GET['quickmanagertv'] == 1 && isset($_SESSION['mgrValidated']))) {
            $params['theme'] = $modx->config['ckeditor5_editor_theme'] ?? 'default';
            $params['cke_entermode'] = $modx->config['cke_entermode'] ?? 'p';
            $params['language'] = $this->get_lang($modx->config['manager_language']);
            $params['frontend'] = false;
            $params['custom_config'] = $modx->config['ckeditor5_custom_config'] ?? '';
            $params['toolbar_align'] = $modx->config['manager_direction'] === 'rtl' ? 'rtl' : 'ltr';
            $params['webuser'] = null;
        } else {
            $frontend_language = isset($modx->config['fe_editor_lang']) ? $modx->config['fe_editor_lang'] : '';
            $webuser = (isset($modx->config['rb_webuser']) ? $modx->config['rb_webuser'] : null);

            $params['theme'] = $params['webtheme'] ?? 'default';
            $params['webuser'] = $webuser;
            $params['language'] = $this->get_lang($frontend_language);
            $params['frontend'] = true;
            $params['custom_config'] = $params['webCustomConfig'] ?? '';
            $params['toolbar_align'] = $params['webAlign'] ?? 'ltr';
        }

        $str = '';

        $theme = $params['theme'];
        $toolbar_config = '';

        switch ($theme) {
            case 'custom':
                $toolbar_config = $params['custom_config'];
                break;
            case 'simple':
            case 'full':
            case 'default':
            default:
                $set = include($cke_path . 'settings/toolbar.settings.inc.php');
                if (empty($theme)) {
                    $theme = 'default';
                }
                $toolbar_config = $set[$theme] ?? $set['default'];
        }

        // MCPUK browser helpers must be loaded before CKEditor initialization hooks
        $str .= $this->build_ckeditor_callback();
        $str .= $this->build_ckeditor_init($toolbar_config) . "\n";

        return $str;
    }

    function build_ckeditor_init($toolbar_config)
    {
        global $modx;
        $params = $this->params;
        $cke_path = $params['cke_path'];
        $cke_url = $params['cke_url'];

        $ph['cke_url'] = $cke_url;
        $ph['elmList'] = implode(',', $params['elements']);
        $ph['width'] = (!empty($params['width'])) ? $params['width'] : '100%';
        $ph['height'] = (!empty($params['height'])) ? $params['height'] : '300';
        $ph['language'] = (empty($params['language'])) ? 'en' : $params['language'];

        $ph['document_base_url'] = MODX_SITE_URL;
        $ph['base_url'] = MODX_BASE_URL;

        $ph['toolbar_config'] = $toolbar_config;

        $ph['entermode'] = $params['cke_entermode'];

        $content_css = [];
        $content_css[] = "{$cke_url}style/content.css";
        if (preg_match('@^/@', $params['editor_css_path'])) {
            $content_css[] = $params['editor_css_path'];
        } elseif (preg_match('@^https?://@', $params['editor_css_path'])) {
            $content_css[] = $params['editor_css_path'];
        } elseif ($params['editor_css_path'] !== '') {
            $content_css[] = MODX_BASE_URL . $params['editor_css_path'];
        }
        $ph['content_css'] = "'" . implode("','", $content_css) . "'";

        $cke_init = file_get_contents($cke_path . "js/ckeditor_init.inc.js");
        foreach ($ph as $name => $value) {
            $name = '[+' . $name . '+]';
            $cke_init = str_replace($name, $value, $cke_init);
        }
        return $cke_init;
    }

    function build_ckeditor_callback()
    {
        return str_replace(
            ['[+cmsurl+]', '[+base_url+]'],
            [
                MODX_BASE_URL . 'manager/media/browser/mcpuk/browser.php?editor=ckeditor',
                MODX_BASE_URL
            ],
            file_get_contents($this->params['cke_path'] . 'js/modx_fb.js.inc')
        );
    }
}
