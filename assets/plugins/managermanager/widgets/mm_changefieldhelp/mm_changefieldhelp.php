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
    $fieldtype = $mm_fields[$field]['fieldtype'];
    $fieldname = $mm_fields[$field]['fieldname'];
    // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
    $selector = js_literal(sprintf('%s[name="%s"]', $fieldtype, str_replace('\\', '', $fieldname)));

    //Is this TV?
    if ($mm_fields[$field]['tv']) {
        // 最も近い祖先の td の、直前の td の説明
        $output .= sprintf(
            'MM.all(%s).forEach(function (el) { const td = el.parentElement && el.parentElement.closest("td"); const p = td && td.previousElementSibling; if (p && p.matches("td")) { MM.html(p.querySelectorAll(":scope > span.comment"), "%s"); } });',
            $selector,
            jsSafe($helptext)
        );
        //Or document field
    } else {
        // Give the help button an ID, and modify the alt/title text
        // title・alt はプロパティなので生の文字列を、data-powertip は属性なので hsc() したものを入れる
        $output .= sprintf(
            'MM.all(%s).forEach(function (el) { Array.from(el.parentNode.children).forEach(function (c) { if (c === el || !c.matches("img.tooltip")) { return; } c.id = "%s-help"; c.title = %s; c.alt = %s; c.setAttribute("data-powertip", %s); }); }); ',
            $selector,
            $fieldname,
            js_literal($helptext),
            js_literal($helptext),
            js_literal(hsc($helptext))
        );
    }
    $output .= "//  -------------- mm_changeFieldHelp :: End ------------- \n";

    $e->output($output . "\n");
}
