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
    // 以前の .before(集合) と同じく、最後の行の前には元の行を、ほかの行の前には複製を入れる
    function putBefore(targets, rows) {
        if (!targets.length) { return; }
        const frag = document.createDocumentFragment();
        rows.forEach(function (r) { frag.appendChild(r); });
        targets.forEach(function (t, i) {
            t.before(i === targets.length - 1 ? frag : frag.cloneNode(true));
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

    // Try and identify any URL type TVs
    MM.all(\'select[id$="_prefix"]\').forEach(function (el) {
        const tr = el.parentElement && el.parentElement.closest("tr");
        if (tr) { tr.classList.add("urltv"); }
    });
';

    // Go through each field that has been supplied
    foreach ($fields as $field) {
        switch ($field) {
            case 'content':
                $output .= '    MM.all("#content_body").forEach(function (el) { tab.appendChild(el); });' . "\n";
                $output .= '    MM.hide("#content_header");' . "\n";
                break;

            // We can't move these fields because they belong in a particular place
            case 'keywords':
            case 'metatags':
            case 'which_editor':
                // Do nothing
                break;

            case 'menuindex':
            case 'hidemenu':
            case 'show_in_menu':
                $output .= '    {
        const rows = MM.all("input[name=menuindex]").map(function (el) { const t = el.closest("table"); return t && t.closest("tr"); }).filter(function (r, i, a) { return r && a.indexOf(r) === i; });
        nextRows(rows).forEach(function (r) { r.remove(); });
        addRule(moveRows(rows));
    }
';
                break;

            case 'pub_date':
            case 'unpub_date':
                $selector = js_literal('input[name=' . $field . ']');
                $output .= '    {
        const helpline = moveRows(nextRows(MM.parents(' . $selector . ', "tr")));
        putBefore(helpline, MM.parents(' . $selector . ', "tr"));
        addRule(helpline);
    }
';
                break;

            case 'weblink':
                $output .= '    moveFieldRows(MM.parents("input#field_weblink", "tr:not(.urltv)"));' . "\n";
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
                $fieldname = str_replace('\\', '', $mm_fields[$field]['fieldname']);
                $output .= sprintf(
                    '    moveFieldRows(MM.parents(%s, "tr:not(.urltv)"));' . "\n",
                    js_literal(sprintf('%s[name="%s"]', $mm_fields[$field]['fieldtype'], $fieldname))
                );
                // This prevents an IE6/7 bug where the moved field would not be visible until you switched tabs
                $output .= sprintf(
                    '    MM.parents(document.querySelector(%s), "td").forEach(function (td) { td.removeAttribute("style"); });' . "\n",
                    js_literal(sprintf('[name^="%s"]', $fieldname))
                );
                break;
        }
    }

    $output .= '})(document.getElementById(' . js_literal('tab' . $newtab) . '));' . "\n";

    $output .= "//  -------------- mm_moveFieldsToTab :: End ------------- \n";

    $e->output($output . "\n");
}
