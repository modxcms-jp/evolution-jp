<?php
/**
 * mm_default
 * @version 1.1 (2012-11-13)
 *
 * Sets a default value for a field when creating a new document.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_default/1.1
 *
 * @copyright 2012
 */

function mm_default($field, $value = '', $roles = '', $templates = '', $eval = false)
{
    global $mm_fields;

    // if we aren't creating a new document or folder, we don't want to do this
    // Which action IDs so we want to do this for?
    // 85 =
    // 4  =
    // 72 = Create new weblink

    $allowed_actions = ['85', '4', '72'];
    if (!in_array(manager()->action, $allowed_actions)) {
        return;
    }

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

// What's the new value, and does it include PHP?
    $new_value = ($eval) ? eval($value) : $value;
    if ($field === 'template' && doc('template') != $new_value) {
        // テンプレートを切り替えて読み直す。form.submit() はシェルの差し替えを通って送られる（以前の .submit() と同じ経路）。
        // submit のイベントは起こさないので、送信前の確認（mm_requireFields など）は通らない
        echo '<script>Evo.ready(function () {';
        echo 'documentDirty = false;';
        echo 'const form = document.getElementById("mutate");';
        echo 'if (!form) { return; }';
        echo 'form.querySelectorAll(\'input[name="a"]\').forEach(function (el) { el.value = 4; });';
        echo 'form.querySelectorAll(\'input[name="newtemplate"]\').forEach(function (el) { el.value = ' . (int)$new_value . '; });';
        echo 'form.submit();';
        echo '});</script>';
    }

    $output = "//  -------------- mm_default :: Begin ------------- \n";

    // Work out the correct date time format based on the config setting
    $date_format = evo()->toDateFormat(null, 'formatOnly');

    // 隠しの値とチェックボックスの組を揃える（change は起こさない。画面を開いた直後に未保存の扱いにしないため）
    $pair = function ($hidden, $hiddenValue, $check, $checked) {
        return sprintf(
            'MM.setValue("input[name=%s]", "%s"); MM.setChecked("input[name=%s]", %s); ' . "\n",
            $hidden,
            $hiddenValue,
            $check,
            $checked ? 'true' : 'false'
        );
    };

    switch ($field) {
        case 'pub_date':
        case 'unpub_date':
            if ($new_value == '') {
                $new_value = evo()->mb_strftime($date_format . ' %H:%M:%S');
            }
            $output .= sprintf(
                'MM.setValue("input[name=%s]", "%s"); ' . "\n",
                $field,
                jsSafe($new_value)
            );
            break;

        case 'published':
            $output .= $pair('published', $value ? '1' : '0', 'publishedcheck', (bool)$value);
            break;

        case 'hide_menu':
            $output .= $pair('hidemenu', $value ? '1' : '0', 'hidemenucheck', !$value);
            break;

        case 'show_in_menu':
            // Note these are reversed from what you'd think
            $output .= $pair('hidemenu', $value ? '0' : '1', 'hidemenucheck', (bool)$value);
            break;

        case 'searchable':
            $output .= $pair('searchable', $value ? '1' : '0', 'searchablecheck', (bool)$value);
            break;

        case 'cacheable':
            $output .= $pair('cacheable', $value ? '1' : '0', 'cacheablecheck', (bool)$value);
            break;

        case 'clear_cache':
            $output .= $pair('syncsite', $value ? '1' : '0', 'syncsitecheck', (bool)$value);
            break;

        case 'container':
        case 'is_folder':
            $output .= $pair('isfolder', $value ? '1' : '0', 'isfoldercheck', (bool)$value);
            break;

        case 'is_richtext':
        case 'richtext':
            $output .= 'var originalRichtextValue = (document.getElementById("which_editor") || {}).value; ' . "\n";
            $output .= $pair('richtext', $value ? '1' : '0', 'richtextcheck', (bool)$value);

            if (!$value) {
                $output .= '
                // Make the RTE displayed match the default value that has been set here
                if (originalRichtextValue&&originalRichtextValue != "none") {
                    MM.setValue("#which_editor", "none");
                    changeRTE();
                }
                ' . "\n";
            }
            break;

        case 'log':
            // Note these are reversed from what you'd think
            $output .= $pair('donthit', $value ? '0' : '1', 'donthitcheck', (bool)$value);
            break;

        case 'content_type':
            $output .= 'MM.setValue("select[name=contentType]", ' . js_literal($new_value) . ');' . "\n";
            break;

        default:
            if (!isset($mm_fields[$field])) {
                break;
            }
            $tv = $mm_fields[$field];
            // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
            $selector = js_literal(sprintf(
                '%s[name="%s"]',
                isset($tv['fieldtype']) ? $tv['fieldtype'] : '*',
                str_replace('\\', '', $tv['fieldname'])
            ));
            if ($tv['tvtype'] === 'option') {
                // ラジオは値が一致するものだけにチェックを付ける（以前の .val([v])）
                $output .= sprintf(
                    'MM.all(%1$s).forEach(function (el) { if (el.type === "radio" || el.type === "checkbox") { el.checked = el.value === %2$s; } else { el.value = %2$s; } });',
                    $selector,
                    js_literal($new_value)
                ) . "\n";
            } else {
                $output .= sprintf('MM.setValue(%s, %s);', $selector, js_literal($new_value)) . "\n";
            }
            break;
    }

    $output .= "//  -------------- mm_default :: End ------------- \n";

    event()->output($output . "\n");
}
