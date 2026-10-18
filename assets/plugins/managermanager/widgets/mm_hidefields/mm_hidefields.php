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

    // 祖先の tr をすべて隠し、それぞれの次の tr の中に td[colspan="2"] があれば、その td の親の tr（説明の行）も隠す
    $hideRows = 'MM.parents(%s, "tr").forEach(function (tr) { MM.hide(tr); const n = tr.nextElementSibling; if (n && n.matches("tr")) { n.querySelectorAll(\'td[colspan="2"]\').forEach(function (td) { if (td.parentElement.matches("tr")) { MM.hide(td.parentElement); } }); } });';
    // 祖先の tr それぞれの次の tr を隠し、祖先の tr も隠す
    $hideDateRows = 'MM.parents(%s, "tr").forEach(function (tr) { const n = tr.nextElementSibling; if (n && n.matches("tr")) { MM.hide(n); } MM.hide(tr); });';
    // 親が td のときだけ親を隠す
    $hideParentTd = 'MM.all(%s).forEach(function (el) { if (el.parentElement && el.parentElement.matches("td")) { MM.hide(el.parentElement); } });';

    foreach ($fields as $field) {
        switch ($field) {
            // Exceptions
            case 'hidemenu':
            case 'show_in_menu':
                $output .= sprintf($hideParentTd, '"input[name=hidemenucheck]"');
                break;

            case 'menuindex':
                $output .= 'MM.all("input[name=menuindex]").forEach(function (el) { const tr = el.closest("tr.mutate-field-row"); const td = tr && tr.querySelector(":scope > td"); if (td) { MM.hide(td.querySelectorAll(":scope > span.mutate-field-title")); } });' . "\n";
                $output .= sprintf($hideParentTd, '"input[name=menuindex]"');
                break;

            case 'which_editor':
                $output .= 'MM.all("select#which_editor").forEach(function (el) { const p = el.previousElementSibling; if (p && p.matches("span.mutate-field-title")) { MM.hide(p); } MM.hide(el); });';
                break;

            case 'content':
                // For 1.0.1
                $output .= 'MM.all("#ta").forEach(function (el) { const p = el.parentElement; const d = p && p.matches("div") ? p.parentElement : null; if (d && d.matches("div")) { MM.hide(d); const prev = d.previousElementSibling; if (prev && prev.matches("div")) { MM.hide(prev); } } });';
                break;

            case 'pub_date':
                $output .= sprintf($hideDateRows, '"input[name=pub_date]"');
                break;

            case 'unpub_date':
                $output .= sprintf($hideDateRows, '"input[name=unpub_date]"');
                break;

            case 'weblink':
                $output .= sprintf($hideRows, '"input#field_weblink"');
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                // Check the fields exist,  so we're not writing JS for elements that don't exist
                // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
                $output .= sprintf(
                    $hideRows,
                    js_literal(sprintf(
                        '%s[name="%s"]',
                        $mm_fields[$field]['fieldtype'],
                        str_replace('\\', '', $mm_fields[$field]['fieldname'])
                    ))
                );
        }
        $output .= "\n";
    }
    $output .= "//  -------------- mm_hideFields :: End ------------- \n";
    event()->output($output . "\n");
}
