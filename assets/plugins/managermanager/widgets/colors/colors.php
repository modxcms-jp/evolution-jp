<?php
/**
 * mm_widget_colors
 * @version 1.1 (2012-11-13)
 *
 * Adds a color selection widget to the specified TVs.
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

    $output = '';

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // Does this page's template use any of these TVs? If not, quit.
    if (!tplUseTvs($mm_current_page['template'], $fields)) {
        return;
    }

    // Insert some JS
    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/colors/farbtastic.js');

    // Go through each of the fields supplied
    foreach ($fields as $tv) {
        $output .= parseText('
            // ----------- Color widget for  [+tv_id+]  --------------
            jQuery("#[+tv_id+]").css("background-image","none");
            jQuery("#[+tv_id+]").after(\'<div id="colorpicker[+tv_id+]"></div>\');
            if (jQuery("#[+tv_id+]").val() == ""){
                jQuery("#[+tv_id+]").val("[+default+]");
            }
            jQuery("#colorpicker[+tv_id+]").farbtastic("#[+tv_id+]");
            jQuery("#colorpicker[+tv_id+]").mouseup(function() {
                // mark the document as dirty, or the value wont be saved
                jQuery("#[+tv_id+]").trigger("change");
            });
            documentDirty = false;
            ', [
                'tv_id' => $mm_fields[$tv]['fieldname'],
                'default' => $default
            ]
        );
    }

    event()->output($output . "\n");
}
