<?php
/**
 * mm_moveFieldsToTab
 * @version 1.1 (2012-11-13)
 *
 * Move a field to a different tab.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_movefieldstotab/1.1
 *
 * @copyright 2012
 */

function mm_moveFieldsToTab($fields, $newtab, $roles = '', $templates = '')
{

    global $modx, $mm_fields, $splitter;
    $e = &$modx->event;

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "//  -------------- mm_moveFieldsToTab :: Begin ------------- \n";

    // If it's one of the default tabs, we need to get the capitalisation right
    switch ($newtab) {
        case 'general':
        case 'settings':
        case 'access':
        case 'meta': // version 1.0.0 only, removed in 1.0.1
            $newtab = ucfirst($newtab);
            break;
    }

    if (isset($splitter) && $splitter === 'none') {
        $ruleHtml = '';
    } else {
        $ruleHtml = '<tr style="height: 10px"><td colspan="2"><div class="split"></div></td></tr>';
    }

    // Make sure the new tab exists in the DOM
    $output .= '(function (tab) {
    if (!tab) { return; }
    // 移す先は、タブの直下の最初の表
    const table = tab.querySelector(":scope > table");
    const ruleHtml = ' . js_literal($ruleHtml) . ';
    // 以前の appendTo と同じく、集合の順に表の末尾へ移す（表が無ければ動かさない）。
    // 表に直下の tbody があれば、以前と同じくその中へ入れる
    function moveRows(rows) {
        if (!table) { return []; }
        const body = table.querySelector(":scope > tbody") || table;
        rows.forEach(function (r) { body.appendChild(r); });
        return rows;
    }
    // それぞれの次の兄弟が tr なら、その tr
    function nextRows(rows) {
        return rows.map(function (r) { return r.nextElementSibling; }).filter(function (n) { return n && n.matches("tr"); });
    }
    // それぞれの後ろに区切りの行を足す（表の直下でも <tr> のまま入るよう template で組み立てる）
    function addRule(rows) {
        if (ruleHtml === "") { return; }
        rows.forEach(function (r) {
            const t = document.createElement("template");
            t.innerHTML = ruleHtml;
            r.after(t.content);
        });
    }
    // 行を移し、後ろの説明の行を消し、区切りを足して、見出しの列の幅を外す
    function moveFieldRows(toMove) {
        const helps = [];
        nextRows(toMove).forEach(function (n) { helps.push.apply(helps, n.querySelectorAll(\'td[colspan="2"]\')); });
        MM.parents(helps, "tr").forEach(function (r) { r.remove(); }); // Get rid of line after, if there is one
        const movedTV = moveRows(toMove); // Move the table row
        addRule(movedTV); // Insert a rule after
        movedTV.forEach(function (r) { r.querySelectorAll("td[width]").forEach(function (td) { td.width = ""; }); }); // Remove widths from label column
    }
';

    // Go through each field that has been supplied
    foreach ($fields as $field) {
        switch ($field) {
            case 'content':
                $output .= '    MM.all("#content_body").forEach(function (el) { tab.appendChild(el); });' . "\n";
                $output .= '    MM.hide("#content_header");' . "\n";
                break;

            // We can't move these fields because they belong in a particular place
            case 'which_editor':
                // Do nothing
                break;

            case 'menuindex':
            case 'hidemenu':
            case 'show_in_menu':
                $output .= '    {
        const rows = MM.all(Evo.fieldRow("menuindex"));
        nextRows(rows).forEach(function (r) { r.remove(); });
        addRule(moveRows(rows));
    }
';
                break;

            case 'weblink':
                $output .= '    moveFieldRows(MM.all(Evo.fieldRow("weblink")));' . "\n";
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                $selector = js_literal(mmFieldSelector($field));
                $output .= sprintf('    moveFieldRows(MM.fieldRows(%s));' . "\n", $selector);
                // This prevents an IE6/7 bug where the moved field would not be visible until you switched tabs
                $output .= sprintf(
                    '    MM.all(Evo.fieldPart(document.querySelector(%s), "value")).forEach(function (td) { td.removeAttribute("style"); });' . "\n",
                    $selector
                );
                break;
        }
    }

    $output .= '})(document.getElementById(' . js_literal('tab' . $newtab) . '));' . "\n";

    $output .= "//  -------------- mm_moveFieldsToTab :: End ------------- \n";

    $e->output($output . "\n");
}
