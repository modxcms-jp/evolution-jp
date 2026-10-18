<?php
/**
 * mm_hideSections
 * @version 1.1 (2012-11-13)
 *
 * Hides sections.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_hidesections/1.1
 *
 * @copyright 2012
 */

function mm_hideSections($sections, $roles = '', $templates = '')
{
    global $modx;
    $e = &$modx->event;

    // if we've been supplied with a string, convert it into an array
    $sections = makeArray($sections);

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "//  -------------- mm_hideSections :: Begin ------------- \n";

    foreach ($sections as $section) {
        switch ($section) {
            case 'content':
                $output .= '
                MM.hide("#content_header");
                MM.hide("#content_body");
                ';
                break;

            case 'tvs':
                $output .= '
                MM.hide("#tv_header");
                MM.hide("#tv_body");
                ';
                break;

            case 'access': // These have moved to tabs in 1.0.1
                $output .= '
                MM.hide("#sectionAccessHeader");
                MM.hide("#sectionAccessBody");';
                break;
        }
    }

    $output .= "//  -------------- mm_hideSections :: End ------------- \n";

    $e->output($output . "\n");
}
