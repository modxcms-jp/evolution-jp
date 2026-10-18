<?php
/**
 * mm_hideTabs
 * @version 1.1 (2012-11-13)
 *
 * Hide a tab.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_hidetabs/1.1
 *
 * @copyright 2012
 */

function mm_hideTabs($tabs, $roles = '', $templates = '')
{
    global $modx;
    $e = &$modx->event;

    // if we've been supplied with a string, convert it into an array
    $tabs = makeArray($tabs);

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "//  -------------- mm_hideTabs :: Begin ------------- \n";

    foreach ($tabs as $tab) {
        switch ($tab) {
            case 'general':
                $output .= 'MM.hide("#tabGeneralHeader");';
                $output .= 'MM.hide("#tabGeneral");';
                break;
            case 'settings':
                $output .= 'MM.hide("#tabSettingsHeader");';
                $output .= 'MM.hide("#tabSettings");';
                break;
            case 'tv':
                $output .= 'MM.hide("#tabTVsHeader");';
                $output .= 'MM.hide("#tabTVs");';
                break;
            case 'access':
                $output .= 'MM.hide("#tabAccessHeader");';
                $output .= 'MM.hide("#tabAccess");';
                break;
        }
    }

    $output .= "//  -------------- mm_hideTabs :: End ------------- \n";

    $e->output($output . "\n");
}
