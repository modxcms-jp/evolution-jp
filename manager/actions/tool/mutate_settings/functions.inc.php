<?php
/**
 * get_lang_keys
 *
 * @return array of keys from a language file
 */
function get_lang_keys($filename) {
    global $_lang;
    $path = sprintf('%slang/%s', MODX_CORE_PATH, $filename);
    if (is_file($path) && is_readable($path)) {
        include($path);
        return array_keys($_lang);
    }
    return array();
}

/**
 * get_langs_by_key
 *
 * @return array of languages that define the key in their file
 */
function get_langs_by_key($key) {
    global $lang_keys;
    $lang_return = array();
    foreach ($lang_keys as $lang => $keys) {
        if (in_array($key, $keys)) {
            $lang_return[] = $lang;
        }
    }
    return $lang_return;
}

/**
 * get_lang_options
 *
 * returns html option list of languages
 *
 * @param string $key specify language key to return options of langauges that override it, default return all languages
 * @param string $selected_lang specify language to select in option list, default none
 * @return html|string
 */
function get_lang_options($key = null, $selected_lang = null) {
    global $lang_keys;
    $lang_options = array();
    if ($key) {
        $languages = get_langs_by_key($key);
        sort($languages);
        $lang_options[] = sprintf('<option value="">%s</option>', lang('language_title'));
        foreach ($languages as $language_name) {
            $lang_options[] = sprintf(
                '<option value="%s">%s</option>'
                , $language_name
                , ucwords(str_replace('_', ' ', $language_name))
            );
        }
        return implode("\n", $lang_options);
    }
    $languages = array_keys($lang_keys);
    sort($languages);
    foreach ($languages as $language_name) {
        $uclanguage_name = ucwords(str_replace("_", " ", $language_name));
        $sel = $language_name == $selected_lang ? ' selected="selected"' : '';
        $lang_options .= '<option value="' . $language_name . '" ' . $sel . '>' . $uclanguage_name . '</option>';
    }
    return $lang_options;
}

function form_text($name, $maxlength = '255', $add = '', $readonly = false) {
    global $settings;

    $value = isset($settings[$name]) ? $settings[$name] : '';

    if ($readonly) {
        $readonly = ' disabled';
    }
    if ($add) {
        $add = ' ' . $add;
    }
    if (empty($maxlength)) {
        $maxlength = '255';
    }
    if ($maxlength <= 10) {
        $maxlength = 'maxlength="' . $maxlength . '" style="width:' . $maxlength . 'em;"';
    } else {
        $maxlength = 'maxlength="' . $maxlength . '"';
    }
    return '<input type="text" ' . $maxlength . ' name="' . $name . '" value="' . $value . '"' . $readonly . $add . ' />';
}

function form_radio($name, $value, $checked = false, $add = '', $disabled = false) {
    if ($checked) {
        $checked = ' checked="checked"';
    }
    if ($disabled) {
        $disabled = ' disabled';
    }
    if ($add) {
        $add = ' ' . $add;
    }
    return '<input type="radio" name="' . $name . '" value="' . $value . '"' . $checked . $disabled . $add . ' />';
}

function wrap_label($str = '', $object) {
    return "<label>{$object}\n{$str}</label>";
}

function get_role_list() {
    global $modx, $default_role;

    $rs = db()->select('id,name', '[+prefix+]user_roles', 'id!=1', 'save_role DESC,new_role DESC,id ASC');
    $tpl = '<option value="[+id+]" [+selected+]>[+name+]</option>';
    $options = "\n";
    while ($ph = $modx->db->getRow($rs)) {
        $ph['selected'] = ($default_role == $ph['id']) ? ' selected' : '';
        $options .= $modx->parseText($tpl, $ph);
    }
    return $options;
}

function checkConfig($key) {
    global $settings, $default_config;
    if (substr($settings[$key], 0, 2) === '* ') {
        $settings[$key] = trim($settings[$key], '* ');
    } else {
        $settings[$key] = $default_config[$key];
    }
}

function settings() {
    global $default_config;
    $settings = array();
    $rs = db()->select('setting_name, setting_value', '[+prefix+]system_settings');
    while ($row = db()->getRow($rs)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    if (!isset($default_config) || !is_array($default_config)) {
        $default_config = include(MODX_CORE_PATH . 'default.config.php');
    }
    $settings = array_merge($default_config, $settings);

    if (manager()->hasFormValues()) {
        $_POST = manager()->loadFormValues();
    }
    if (setlocale(LC_CTYPE, 0) === 'Japanese_Japan.932') {
        $settings['filemanager_path'] = mb_convert_encoding(
            $settings['filemanager_path']
            , 'utf-8'
            , 'sjis-win'
        );
        $settings['rb_base_dir'] = mb_convert_encoding(
            $settings['rb_base_dir']
            , 'utf-8'
            , 'sjis-win'
        );
    }
    $settings['filemanager_path'] = preg_replace(
        '@^' . MODX_BASE_PATH . '@'
        , '[(base_path)]'
        , $settings['filemanager_path']
    );
    $settings['rb_base_dir'] = preg_replace(
        '@^' . MODX_BASE_PATH . '@'
        , '[(base_path)]'
        , $settings['rb_base_dir']
    );
    if ($_POST) {
        $settings = array_merge($settings, $_POST);
    }

    if (strpos($settings['site_url'], '[(site_url)]') !== false) {
        $settings['site_url'] = str_replace(
            '[(site_url)]'
            , MODX_SITE_URL
            , $settings['site_url']
        );
    }
    if (strpos($settings['base_url'], '[(base_url)]') !== false) {
        $settings['base_url'] = str_replace(
            '[(base_url)]'
            , MODX_BASE_URL
            , $settings['base_url']
        );
    }
    return $settings;
}