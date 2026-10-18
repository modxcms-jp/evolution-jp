<?php
/**
 * ddSetFieldValue
 * @param field {string} - Имя поля, для которого необходимо установить значение.
 * @param value {string} - Значение, которое необходимо установить.
 * @param roles {comma separated string} - Id ролей. По умолчанию: для всех ролей.
 * @param templates {comma separated string} - Id шаблонов. По умолчанию: для всех шаблонов.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @todo Основан на mm_default
 *
 * @version 1.0.3 (2012-11-13)
 *
 * Жёстко выставляет необходимые значения заданному полю
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

    //Подбираем правильный формат даты в соответствии с конфигурацией
    switch ($modx->config['datetime_format']) {
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

    //Смотрим, что за поле надо изменить
    switch ($field) {
        //Дата публикации
        case 'pub_date':
            if ($value == '') {
                $value = date($date_format . ' H:i:s');
            }
            $output .= 'MM.setValue("input[name=pub_date]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Дата отмены публикации
        case 'unpub_date':
            if ($value == '') {
                $value = date($date_format . ' H:i:s');
            }
            $output .= 'MM.setValue("input[name=unpub_date]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак публикации
        case 'published':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=publishedcheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=publishedcheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=published]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак отображения в меню
        case 'show_in_menu':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=hidemenucheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=hidemenucheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=hidemenu]", "' . (($value == '1') ? '0' : '1') . '"); ' . "\n"; // Note these are reversed from what you'd think
            break;

        //Признак скрытия из меню (аналогично show_in_menu, только наоборот)
        case 'hide_menu':
            if ($value == '0') {
                $output .= 'MM.setChecked("input[name=hidemenucheck]", true); ' . "\n";
            } else {
                $value = '1';
                $output .= 'MM.setChecked("input[name=hidemenucheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=hidemenu]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак доступности для поиска
        case 'searchable':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=searchablecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=searchablecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=searchable]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак кэширования
        case 'cacheable':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=cacheablecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=cacheablecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=cacheable]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак очистки кэша
        case 'clear_cache':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=syncsitecheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=syncsitecheck]", false); ' . "\n";
            }

            $output .= 'MM.setValue("input[name=syncsite]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак папки
        case 'is_folder':
            if ($value == '1') {
                $output .= 'MM.setChecked("input[name=isfoldercheck]", true); ' . "\n";
            } else {
                $value = '0';
                $output .= 'MM.setChecked("input[name=isfoldercheck]", false); ' . "\n";
            }
            break;

        //Признак использованшия визуального редактора
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
                                changeRTE();
                            }

                            ';
                $output .= '' . "\n";
            }

            $output .= 'MM.setValue("input[name=richtext]", ' . js_literal($value) . '); ' . "\n";
            break;

        //Признак логирования
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

        //Тип содержимого
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
