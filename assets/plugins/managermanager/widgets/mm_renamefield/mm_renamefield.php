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

    $label = mmHtmlLiteral($newlabel);
    // 行の見出し（%s は行の名前か、欄の要素を返す式）
    $rowTitle = 'MM.html(Evo.fieldPart(%s, "title"), %s);';

    switch ($field) {
        // Exceptions
        case 'hidemenu':
        case 'show_in_menu':
        case 'hide_menu':
            $output .= sprintf($rowTitle, '"hidemenu"', $label);
            break;

        case 'which_editor':
            $output .= '(function (el) { const p = el && el.previousElementSibling; if (p && p.matches("span.mutate-field-title")) { p.innerHTML = ' . $label . '; } })(document.getElementById("which_editor"));';
            break;

        case 'content':
            mm_renameSection('content', $newlabel, $roles, $templates);
            break;

        case 'menuindex':
            $output .= sprintf($rowTitle, '"menuindex"', $label);
            break;

        case 'weblink':
            $output .= sprintf($rowTitle, '"weblink"', $label);
            break;

        default:
            if (!isset($mm_fields[$field])) {
                break;
            }
            // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
            $output .= sprintf(
                $rowTitle,
                'document.querySelector(' . js_literal(mmFieldSelector($field)) . ')',
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
