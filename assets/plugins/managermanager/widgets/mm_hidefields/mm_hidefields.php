<?php
/**
 * mm_hideFields
 * @version 1.1 (2012-11-13)
 *
 * Hide a field.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_hidefields/1.1
 *
 * @copyright 2012
 */

function mm_hideFields($fields, $roles = '', $templates = '')
{
    global $mm_fields;

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "//  -------------- mm_hideFields :: Begin ------------- \n";

    foreach ($fields as $field) {
        switch ($field) {
            // Exceptions
            case 'hidemenu':
            case 'show_in_menu':
                $output .= 'MM.hide(Evo.fieldRow("hidemenu"));';
                break;

            case 'menuindex':
                // 見出しと入力のセルを隠す（同じ行の「メニューに表示」は残す）
                $output .= 'MM.hide(Evo.fieldPart("menuindex", "title"));' . "\n";
                $output .= 'MM.hide(Evo.fieldPart("menuindex", "input"));';
                break;

            case 'which_editor':
                $output .= 'MM.hide("#which_editor_title"); MM.hide("#which_editor");';
                break;

            case 'content':
                // For 1.0.1
                $output .= 'MM.all("#ta").forEach(function (el) { const p = el.parentElement; const d = p && p.matches("div") ? p.parentElement : null; if (d && d.matches("div")) { MM.hide(d); const prev = d.previousElementSibling; if (prev && prev.matches("div")) { MM.hide(prev); } } });';
                break;

            case 'weblink':
                $output .= 'MM.hideRows(Evo.fieldRow("weblink"));';
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                // Check the fields exist,  so we're not writing JS for elements that don't exist
                // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
                $output .= 'MM.hideRows(MM.fieldRows(' . js_literal(mmFieldSelector($field)) . '));';
        }
        $output .= "\n";
    }
    $output .= "//  -------------- mm_hideFields :: End ------------- \n";
    event()->output($output . "\n");
}
