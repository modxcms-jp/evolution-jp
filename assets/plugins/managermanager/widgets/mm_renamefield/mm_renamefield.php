<?php
/**
 * mm_renameField
 * @version 1.1 (2012-11-13)
 *
 * Change the label for an element.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_renamefield/1.1
 *
 * @copyright 2012
 */

function mm_renameField($field, $newlabel, $roles = '', $templates = '', $newhelp = '')
{
    global $mm_fields;

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "// ----------- mm_renameField :: Begin ------------- \n";

    $label = '"' . jsSafe($newlabel) . '"';
    // 自分以外の兄弟の見出し
    $siblingsTitle = 'MM.all(%s).forEach(function (el) { MM.html(Array.from(el.parentNode.children).filter(function (c) { return c !== el && c.matches("span.mutate-field-title"); }), %s); });';
    // 最も近い祖先の td の、直前の td の見出し
    $prevTdTitle = 'MM.all(%s).forEach(function (el) { const td = el.parentElement && el.parentElement.closest("td"); const p = td && td.previousElementSibling; if (p && p.matches("td")) { MM.html(p.querySelectorAll(":scope > span.mutate-field-title"), %s); } });';

    switch ($field) {
        // Exceptions
        case 'keywords':
            $output .= sprintf($siblingsTitle, '"select[name*=keywords]"', $label);
            break;

        case 'metatags':
            $output .= sprintf($siblingsTitle, '"select[name*=metatags]"', $label);
            break;

        case 'hidemenu':
        case 'show_in_menu':
            $output .= sprintf($siblingsTitle, '"input[name=hidemenucheck]"', $label);
            break;

        case 'which_editor':
            $output .= '(function (el) { const p = el && el.previousElementSibling; if (p && p.matches("span.mutate-field-title")) { p.innerHTML = ' . $label . '; } })(document.getElementById("which_editor"));';
            break;

        case 'content':
            $output .= 'MM.html("#content_header", ' . $label . ');';
            break;

        case 'menuindex':
            $output .= 'MM.all("input[name=menuindex]").forEach(function (el) { const tr = el.closest("tr.mutate-field-row"); const td = tr && tr.querySelector(":scope > td"); if (td) { MM.html(td.querySelectorAll(":scope > span.mutate-field-title"), ' . $label . '); } });';
            break;

        case 'weblink':
            $output .= sprintf($prevTdTitle, '"input#field_weblink"', $label);
            break;

        default:
            if (!isset($mm_fields[$field])) {
                break;
            }
            // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
            $output .= sprintf(
                $prevTdTitle,
                js_literal(sprintf(
                    '%s[name="%s"]',
                    $mm_fields[$field]['fieldtype'],
                    str_replace('\\', '', $mm_fields[$field]['fieldname'])
                )),
                $label
            );
            break;
    }

    $output .= "//  -------------- mm_renameField :: End ------------- \n";

    event()->output($output . "\n");

    // If new help has been supplied, do that too
    if ($newhelp != '') {
        mm_changeFieldHelp($field, $newhelp, $roles, $templates);
    }
}
