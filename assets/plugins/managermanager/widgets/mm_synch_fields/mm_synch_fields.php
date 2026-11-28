<?php
/**
 * mm_synch_fields
 * @version 1.1 (2012-11-13)
 *
 * Synch two fields in real time.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_synch_fields/1.1
 *
 * @copyright 2012
 */

function mm_synch_fields($fields, $roles = '', $templates = '')
{
    global $modx, $mm_fields;
    $e = &$modx->event;

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // We need at least 2 values
    if (count($fields) < 2) {
        return;
    }

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // 項目ごとに CSS セレクタを集める。fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
    $selectors = [];
    foreach ($fields as $field) {
        if (!isset($mm_fields[$field])) {
            break;
        }

        $fieldtype = $mm_fields[$field]['fieldtype'];
        $fieldname = $mm_fields[$field]['fieldname'];

        // Make sure we're dealing with an input
        if (!in_array($fieldtype, ['input', 'textarea'])) {
            break;
        }

        $selectors[] = sprintf('%s[name="%s"]', $fieldtype, str_replace('\\', '', $fieldname));
    }

    $output = "//  -------------- mm_synch_fields :: Begin ------------- \n";
    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/mm_synch_fields/mm_synch_fields.js');
    $output .= 'mmSynchFields.link(' . js_value($selectors) . ');' . "\n";
    $output .= "//  -------------- mm_synch_fields :: End ------------- \n";

    $e->output($output . "\n");
}
