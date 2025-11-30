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

    // Make sure the new tab exists in the DOM
    $output .= "if ( \$j('#tab" . $newtab . "').length > 0) { \n";
    if (isset($splitter) && $splitter === 'none') {
        $output .= "var ruleHtml = ''; ";
    } else {
        $output .= 'var ruleHtml = \'<tr style="height: 10px"><td colspan="2"><div class="split"></div></td></tr>\'; ';
    }

    // Try and identify any URL type TVs
    $output .= '$j("select[id$=_prefix]").each( function() { $j(this).parents("tr:first").addClass("urltv"); }  ); ';

    // Go through each field that has been supplied
    foreach ($fields as $field) {
        switch ($field) {
            case 'content':
                $output .= '$j("#content_body").appendTo("#tab' . $newtab . '");' . "\n";
                $output .= '$j("#content_header").hide();' . "\n";
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
                $output .= '$j("input[name=menuindex]").closest("table").closest("tr").next("tr").remove(); ' . "\n";
                $output .= 'var helpline = $j("input[name=menuindex]").closest("table").closest("tr").appendTo("#tab' . $newtab . '>table:first"); ' . "\n";
                $output .= 'helpline.after(ruleHtml); ' . "\n";
                break;
            case 'pub_date':
                $output .= 'var helpline = $j("input[name=pub_date]").parents("tr").next("tr").appendTo("#tab' . $newtab . '>table:first"); ' . "\n";
                $output .= '$j(helpline).before($j("input[name=pub_date]").parents("tr")); ' . "\n";
                $output .= 'helpline.after(ruleHtml); ' . "\n";
                break;

            case 'unpub_date':
                $output .= 'var helpline = $j("input[name=unpub_date]").parents("tr").next("tr").appendTo("#tab' . $newtab . '>table:first"); ' . "\n";
                $output .= '$j(helpline).before($j("input[name=unpub_date]").parents("tr")); ' . "\n";
                $output .= 'helpline.after(ruleHtml); ' . "\n";
                break;

            case 'weblink':
                $output .= sprintf('
                var toMove = $j("input#field_weblink").parents("tr:not(.urltv)"); // Identify the table row to move
                toMove.next("tr").find("td[colspan=2]").parents("tr").remove(); // Get rid of line after, if there is one
                var movedTV = toMove.appendTo("#tab%s>table:first"); // Move the table row
                movedTV.after(ruleHtml); // Insert a rule after
                movedTV.find("td[width]").prop("width","");  // Remove widths from label column
                ',
                    $newtab
                );
                break;

            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                $output .= sprintf('
                var toMove = $j(\'%s[name="%s"]\').parents("tr:not(.urltv)"); // Identify the table row to move
                toMove.next("tr").find("td[colspan=2]").parents("tr").remove(); // Get rid of line after, if there is one
                var movedTV = toMove.appendTo("#tab%s>table:first"); // Move the table row
                movedTV.after(ruleHtml); // Insert a rule after
                movedTV.find("td[width]").prop("width","");  // Remove widths from label column
                ',
                    $mm_fields[$field]['fieldtype'],
                    $mm_fields[$field]['fieldname'],
                    $newtab
                );
                break;
        }
    }

    $output .= "}";

    $output .= "//  -------------- mm_moveFieldsToTab :: End ------------- \n";

    $e->output($output . "\n");
}
