<?php
/**
 * mm_hideTemplates
 * @version 1.1 (2012-11-13)
 *
 * Hide a template within the dropdown list of templates.
 * Based on code submitted by Metaller
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_hidetemplates/1.1
 *
 * @copyright 2012
 */

function mm_hideTemplates($tplIds, $roles = '', $templates = '')
{
    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $tplIds = makeArray($tplIds);
    $output = "//  -------------- mm_hideTemplates :: Begin ------------- \n";
    foreach ($tplIds as $tpl) {
        // 選ばれているテンプレートは残す
        $output .= sprintf(
            '(function (sel, v) { if (!sel || sel.value == v) { return; } Array.from(sel.options).forEach(function (o) { if (o.value == v) { o.remove(); } }); })(document.querySelector("select#field_template"), %s);' . "\n",
            js_literal($tpl)
        );
    }
    $output .= "//  -------------- mm_hideTemplates :: End ------------- \n";

    event()->output($output . "\n");
}
