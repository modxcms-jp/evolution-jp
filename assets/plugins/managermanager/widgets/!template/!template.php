<?php
/**
 * mm_widget_template
 * @version 1.0 (2013-01-01)
 *
 * A template for creating new widgets
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://
 *
 * @copyright 2013
 */

function mm_widget_template($fields, $other_param = 'defaultValue', $roles = '', $templates = '')
{
    global $modx, $mm_fields, $mm_current_page;
    $e = &$modx->event;

    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // Your output should be stored in a string, which is outputted at the end
    // It will be inserted as a Javascript block (with jQuery), which is executed on document ready
    // We always put a JS comment, which makes debugging much easier
    $output = "//  -------------- mm_widget_template :: Begin ------------- \n";

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // You might want to check whether the current page's template uses the TVs that have been
    // supplied, to save processing page which don't contain them

    $count = tplUseTvs($mm_current_page['template'], $fields);
    if ($count == false) {
        return;
    }

    // We have functions to include JS or CSS external files you might need
    // The standard ModX API methods don't work here
    $output .= includeJs('assets/plugins/managermanager/widgets/template/javascript.js');
    $output .= includeCss('assets/plugins/managermanager/widgets/template/styles.css');

    // Do something for each of the fields supplied
    foreach ($fields as $targetTv) {
        // If it's a TV, we may need to map the field name, to what it's ID is.
        // This can be obtained from the mm_fields array
        $tv_id = $mm_fields[$targetTv]['fieldname'];
    }

    //JS comment for end of widget
    $output .= "//  -------------- mm_widget_template :: End ------------- \n";

    // Send the output to the browser
    $e->output($output . "\n");
}
