<?php
/**
 * mm_widget_colors
 * @version 1.2 (2026-10-18)
 *
 * Adds a color selection widget to the specified TVs.
 * Uses the browser's native <input type="color">.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_colors/1.1
 *
 * @copyright 2012
 */

function mm_widget_colors($fields, $default = '#ffffff', $roles = '', $templates = '')
{
    global $mm_fields, $mm_current_page;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // Does this page's template use any of these TVs? If not, quit.
    if (!tplUseTvs($mm_current_page['template'], $fields)) {
        return;
    }

    $output = includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/colors/colors.js');

    // Go through each of the fields supplied
    foreach ($fields as $tv) {
        if (!isset($mm_fields[$tv])) {
            continue;
        }
        $output .= sprintf(
            "mmColors.attach(%s, %s);\ndocumentDirty = false;\n",
            js_literal($mm_fields[$tv]['fieldname']),
            js_literal($default)
        );
    }

    event()->output($output . "\n");
}
