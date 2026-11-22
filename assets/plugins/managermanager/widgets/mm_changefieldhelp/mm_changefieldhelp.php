<?php
/**
 * mm_changeFieldHelp
 * @version 1.1 (2012-11-13)
 *
 * Change the help text of a field.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_changefieldhelp/1.1
 *
 * @copyright 2012
 */

function mm_changeFieldHelp($field, $helptext = '', $roles = '', $templates = '')
{
    global $mm_fields, $modx;
    $e = &$modx->event;

    if ($helptext == '') {
        return;
    }

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // What type is this field?
    if (!isset($mm_fields[$field])) {
        return;
    }
    $output = "//  -------------- mm_changeFieldHelp :: Begin ------------- \n";
    $fieldname = $mm_fields[$field]['fieldname'];
    $selector = js_literal(mmFieldSelector($field));

    //Is this TV?
    if ($mm_fields[$field]['tv']) {
        // 行の説明。説明が空の TV は説明の部分が無いので、値のセルの末尾に作る
        $output .= sprintf(
            '(function (el) { let help = Evo.fieldPart(el, "help"); if (!help) { const value = Evo.fieldPart(el, "value"); if (!value) { return; } help = document.createElement("div"); help.className = "form-text"; help.setAttribute("data-field-part", "help"); value.appendChild(help); } MM.html(help, %s); })(document.querySelector(%s));',
            mmHtmlLiteral($helptext),
            $selector
        );
        //Or document field
    } else {
        // Give the help button an ID, and modify the alt/title text
        // title・alt はプロパティなので生の文字列を、data-powertip は属性なので hsc() したものを入れる
        if ($fieldname === 'hidemenucheck') {
            // 「メニューに表示」は、説明の部分の中のアイコン
            $icons = 'MM.innerIcons(Evo.fieldPart("hidemenu", "help"))';
        } else {
            // 欄の兄弟のアイコン
            $icons = 'MM.siblingIcons(' . $selector . ')';
        }
        $output .= sprintf(
            'MM.setHelp(%s, %s, %s, %s);',
            $icons,
            js_literal($fieldname . '-help'),
            js_literal($helptext),
            js_literal(hsc($helptext))
        );
    }
    $output .= "//  -------------- mm_changeFieldHelp :: End ------------- \n";

    $e->output($output . "\n");
}
