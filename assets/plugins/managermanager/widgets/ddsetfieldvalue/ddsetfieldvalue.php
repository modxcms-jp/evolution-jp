<?php
/**
 * ddSetFieldValue
 * @param field {string} - 値を設定する欄の名前
 * @param value {string} - 設定する値
 * @param roles {comma separated string} - 適用するロールの ID。省略時はすべて
 * @param templates {comma separated string} - 適用するテンプレートの ID。省略時はすべて
 *
 * @uses ManagerManager plugin 0.4.
 *
 *
 * @version 1.0.3 (2012-11-13)
 *
 * 指定した欄に値を固定で設定する
 *
 * @link http://code.divandesign.biz/modx/mm_ddsetfieldvalue/1.0.3
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.biz
 */

function mm_ddSetFieldValue($field, $value = '', $roles = '', $templates = '')
{
    global $modx, $content, $mm_fields, $mm_current_page;
    $e = &$modx->event;

    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = " // ----------- mm_ddSetFieldValue :: Begin -------------- \n";

    switch (config('datetime_format')) {
        case 'dd-mm-YYYY':
            $date_format = 'd-m-Y';
            break;
        case 'mm/dd/YYYY':
            $date_format = 'm/d/Y';
            break;
        case 'YYYY/mm/dd':
            $date_format = 'Y/m/d';
            break;
    }

    switch ($field) {
        case 'pub_date':
            if ($value == '') {
                $value = date($date_format . ' H:i:s');
            }
            $output .= 'MM.setValue("input[name=pub_date]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'unpub_date':
            if ($value == '') {
                $value = date($date_format . ' H:i:s');
            }
            $output .= 'MM.setValue("input[name=unpub_date]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'published':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=publishedcheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=publishedcheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=published]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'show_in_menu':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=hidemenucheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=hidemenucheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=hidemenu]", "' . (($value == '1') ? '0' : '1') . '"); ' . "\n"; // Note these are reversed from what you'd think
            break;

        case 'hide_menu':
            if ($value == '0') {
                $output .= 'MM.setChecked("input[name=hidemenucheck]", true); ' . "\n";
            } else {
                $value = '1';
                $output .= 'MM.setChecked("input[name=hidemenucheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=hidemenu]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'searchable':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=searchablecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=searchablecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=searchable]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'cacheable':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=cacheablecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=cacheablecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=cacheable]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'clear_cache':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=syncsitecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=syncsitecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=syncsite]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'is_folder':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=isfoldercheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=isfoldercheck]", false); ' . "\n";
            }
            break;

        case 'is_richtext':
            $output .= 'var originalRichtextValue = (document.getElementById("which_editor") || {}).value; ' . "\n";

            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=richtextcheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= '
                            MM.setChecked("input[name=richtextcheck]", false);
                            // Make the RTE displayed match the default value that has been set here
                            if (originalRichtextValue != "none"){
                                MM.setValue("#which_editor", "none");
                                MM.fire("#which_editor", "change");
                            }

                            ';
                $output .= '' . "\n";
            }

            $output .= 'MM.setValue("input[name=richtext]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'log':
            //Note these are reversed from what you'd think
            $value = ($value) ? '0' : '1';

            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=donthitcheck]", true); ' . "\n";
            } else {
                $output .= 'MM.setChecked("input[name=donthitcheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=donthit]", ' . js_literal($value) . '); ' . "\n";
            break;

        case 'content_type':
            $output .= 'MM.setValue("select[name=contentType]", ' . js_literal($value) . ');' . "\n";
            break;

        //TV
        default:
            // Which template is this page using?

            $tvsMas = tplUseTvs($mm_current_page['template'], $field);

            if ($tvsMas) {
                $output .= 'MM.setValue("#tv' . (int)$tvsMas[0]['id'] . '", ' . js_literal($value) . ');' . "\n";
            }
            break;
    }

    $output .= "\n// ---------------- mm_ddSetFieldValue :: End -------------";

    $e->output($output . "\n");
}
