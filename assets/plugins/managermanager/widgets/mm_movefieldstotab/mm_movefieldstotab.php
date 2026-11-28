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

    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/mm_movefieldstotab/mm_movefieldstotab.js');
    $output .= '(function (mover) {
    if (!mover) { return; }
';

    // Go through each field that has been supplied
    foreach ($fields as $field) {
        switch ($field) {
            case 'content':
                $output .= '    mover.content();' . "\n";
                break;

            // We can't move these fields because they belong in a particular place
            case 'which_editor':
                // Do nothing
                break;

            case 'menuindex':
            case 'hidemenu':
            case 'show_in_menu':
                $output .= '    mover.menuindex();' . "\n";
                break;

            case 'weblink':
                $output .= '    mover.weblink();' . "\n";
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                $output .= sprintf('    mover.field(%s);' . "\n", js_literal(mmFieldSelector($field)));
                break;
        }
    }

    $output .= '})(mmMoveFields.forTab(document.getElementById(' . js_literal('tab' . $newtab) . '), ' . js_literal($ruleHtml) . '));' . "\n";

    $output .= "//  -------------- mm_moveFieldsToTab :: End ------------- \n";

    $e->output($output . "\n");
}
