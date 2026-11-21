<?php
/**
 * mm_requireFields
 * @version 1.2 (2013-02-11)
 *
 * Make fields required. Currently works with text fields only.
 * In the future perhaps this could deal with other elements.
 * Originally version by Jelle Jager AKA TobyL - Make fields required
 * Updated by ncrossland to utilise simpler field handline of MM 0.3.5+; bring JS code into line; add indication to required fields
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_requirefields/1.2
 *
 * @copyright 2013
 */

function mm_requireFields($fields, $roles = '', $templates = '')
{
    global $mm_fields, $mm_current_page, $modx;
    $e = &$modx->event;

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

// if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    if (count($fields) == 0) return;

    $selectors = [];
    $fixDateType = false;

    foreach ($fields as $field) {
        //ignore for now
        switch ($field) {
            // fields for which this doesn't make sense - in my opinion anyway :)
            case 'hidemenu':
            case 'which_editor':
            case 'template':
            case 'menuindex':
            case 'show_in_menu':
            case 'parent':
            case 'is_folder':
            case 'is_richtext':
            case 'log':
            case 'searchable':
            case 'cacheable':
            case 'clear_cache':
            case 'content_type':
            case 'content_dispo':
                break;

            // Pub/unpub dates don't have a type attribute on their input tag in 1.0.2, so add this. Won't do any harm to other versions
            case 'pub_date':
            case 'unpub_date':
                $fixDateType = true;
            // no break, because we want to do the things below too.

            // Ones that follow the regular pattern
            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                //if it's tv & it's not used in current template
                if ($mm_fields[$field]['tv'] && tplUseTvs($mm_current_page['template'], $field) === false) {
                    //Go to next field
                    break;
                }

                // What type is this field?
                // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
                $fieldname = str_replace('\\', '', $mm_fields[$field]['fieldname']);

                // What selector should we use for this fieldtype?
                switch ($mm_fields[$field]['fieldtype']) {
                    case 'textarea':
                        $selector = sprintf('textarea[name="%s"]', $fieldname);
                        break;

                    case 'input': // If it's an input, we only want to do something if it's a text field
                        $selector = sprintf('input[type="text"][name="%s"]', $fieldname);
                        break;

                    case 'select':
                        $selector = sprintf('select[name="%s"]', $fieldname);
                        break;

                    default:  // all other input types, do nothing
                        $selector = '';
                        break;
                }

                // If we've found something we want to use
                if (!empty($selector)) {
                    $selectors[] = $selector;
                }
                break;
        }
    }

    $output = "//  -------------- mm_requireFields :: Begin ------------- \n";
    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/mm_requirefields/mm_requirefields.js');
    $output .= 'mmRequire.run(' . js_value([
        'fixDateType' => $fixDateType,
        'selectors' => $selectors,
        'messages' => [
            'required' => lang('required_field'),
            'missing' => lang('required_fields_missing'),
        ],
    ]) . ');' . "\n";
    $output .= "//  -------------- mm_requireFields :: End ------------- \n";

    $e->output($output . "\n");
}
