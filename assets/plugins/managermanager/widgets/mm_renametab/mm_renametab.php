<?php
/**
 * mm_renameTab
 * @version 1.1 (2012-11-13)
 *
 * Rename a tab.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_renametab/1.1
 *
 * @copyright 2012
 */

function mm_renameTab($tab, $newname, $roles = '', $templates = '')
{
    global $modx;
    $e = &$modx->event;

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = "//  -------------- mm_renameTab :: Begin ------------- \n";

    $headers = [
        'general' => 'tabGeneralHeader',
        'settings' => 'tabSettingsHeader',
        'tv' => 'tabTVsHeader',
        'access' => 'tabAccessHeader',
    ];
    if (isset($headers[$tab])) {
        // WebFXTabPage wraps the heading text in a span (styles and click handling): rename inside it
        $output .= sprintf(
            '(function (h) { if (!h) { return; } const s = h.querySelector(":scope > span"); (s || h).innerHTML = "%s"; })(document.getElementById("%s"));' . "\n",
            jsSafe($newname),
            $headers[$tab]
        );
    }

    $output .= "//  -------------- mm_renameTab :: End ------------- \n";

    $e->output($output . "\n");
}
