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
        // 行の説明
        $output .= sprintf(
            'MM.html(Evo.fieldPart(document.querySelector(%s), "help"), %s);',
            $selector,
            mmHtmlLiteral($helptext)
        );
        //Or document field
    } else {
        // Give the help button an ID, and modify the alt/title text
        // title・alt はプロパティなので生の文字列を、data-powertip は属性なので hsc() したものを入れる
        $setHelp = sprintf(
            'function (c) { c.id = "%s-help"; c.title = %s; c.alt = %s; c.setAttribute("data-powertip", %s); }',
            $fieldname,
            js_literal($helptext),
            js_literal($helptext),
            js_literal(hsc($helptext))
        );
        if ($fieldname === 'hidemenucheck') {
            // 「メニューに表示」は、説明の部分の中のアイコン
            $output .= sprintf(
                'MM.all(Evo.fieldPart("hidemenu", "help")).forEach(function (help) { help.querySelectorAll("img.tooltip").forEach(%s); }); ',
                $setHelp
            );
        } else {
            // 欄の兄弟のアイコン
            $output .= sprintf(
                'MM.all(%s).forEach(function (el) { Array.from(el.parentNode.children).filter(function (c) { return c !== el && c.matches("img.tooltip"); }).forEach(%s); }); ',
                $selector,
                $setHelp
            );
        }
    }
    $output .= "//  -------------- mm_changeFieldHelp :: End ------------- \n";

    $e->output($output . "\n");
}
