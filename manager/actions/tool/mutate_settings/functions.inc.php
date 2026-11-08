<?php
/**
 * get_lang_keys
 *
 * @return array of keys from a language file
 */
function get_lang_keys($filename)
{
    // 英語で補うと、すべての言語が英語のキーを持つことになるので、ファイル単体で読む
    return array_keys(evo()->readLexiconFile(sprintf('%slang/%s', MODX_CORE_PATH, $filename)));
}

/**
 * get_langs_by_key
 *
 * @return array of languages that define the key in their file
 */
function get_langs_by_key($key)
{
    global $lang_keys;
    $lang_return = [];
    foreach ($lang_keys as $lang => $keys) {
        if (!in_array($key, $keys)) {
            continue;
        }
        $lang_return[] = $lang;
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
function get_lang_options($key = null, $selected_lang = null)
{
    global $lang_keys;
    $lang_options = [];
    if ($key) {
        $languages = get_langs_by_key($key);
        sort($languages);
        $lang_options[] = sprintf('<option value="">%s</option>', lang('language_title'));
        foreach ($languages as $language_name) {
            $lang_options[] = sprintf(
                '<option value="%s">%s</option>',
                $language_name,
                ucwords(str_replace('_', ' ', $language_name))
            );
        }
        return implode("\n", $lang_options);
    }
    $languages = array_keys($lang_keys);
    sort($languages);
    foreach ($languages as $language_name) {
        $lang_options[] = sprintf(
            '<option value="%s" %s>%s</option>',
            $language_name,
            $language_name == $selected_lang ? ' selected="selected"' : '',
            ucwords(str_replace('_', ' ', $language_name))
        );
    }
    return implode("\n", $lang_options);
}

function form_text($name, $maxlength = '255', $add = '', $readonly = false)
{
    if (!$maxlength) {
        $maxlength = '255';
    }
    return sprintf(
        '<input type="text" name="%s" value="%s" maxlength="%s" %s %s %s />',
        $name,
        hsc(evo()->config($name, '')),
        $maxlength,
        $maxlength <= 10 ? sprintf('style="width:%sem;"', $maxlength) : '',
        $readonly ? 'disabled' : '',
        $add ? $add : ''
    );
}

function form_radio($name, $value, $checked = false, $add = '', $disabled = false)
{
    return sprintf(
        '<input type="radio" name="%s" value="%s" %s %s %s />',
        $name,
        $value,
        $checked ? 'checked="checked"' : '',
        $disabled ? 'disabled' : '',
        $add
    );
}

function wrap_label($str = '', $object = '')
{
    return sprintf(
        '<label>%s %s</label>',
        $object,
        $str
    );
}

function get_role_list()
{
    global $default_role;

    $rs = db()->select(
        'id,name',
        '[+prefix+]user_roles',
        'id!=1',
        'save_role DESC,new_role DESC,id ASC'
    );
    $options = "\n";
    while ($ph = db()->getRow($rs)) {
        $ph['selected'] = $default_role == $ph['id'] ? 'selected' : '';
        $ph['name'] = hsc($ph['name']);
        $options .= evo()->parseText(
            '<option value="[+id+]" [+selected+]>[+name+]</option>',
            $ph
        );
    }
    return $options;
}

function checkConfig($key)
{
    global $settings, $default_config;
    if (strpos($settings[$key], '* ') === 0) {
        $settings[$key] = trim($settings[$key], '* ');
    } else {
        $settings[$key] = $default_config[$key];
    }
}
