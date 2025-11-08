<?php
/**
 * mm_default
 * @version 1.1 (2012-11-13)
 *
 * Sets a default value for a field when creating a new document.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_default/1.1
 *
 * @copyright 2012
 */

function mm_default($field, $value = '', $roles = '', $templates = '', $eval = false)
{
    global $mm_fields;

    // if we aren't creating a new document or folder, we don't want to do this
    // Which action IDs so we want to do this for?
    // 85 =
    // 4  =
    // 72 = Create new weblink

    $allowed_actions = ['85', '4', '72'];
    if (!in_array(manager()->action, $allowed_actions)) {
        return;
    }

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

// What's the new value, and does it include PHP?
    $new_value = ($eval) ? eval($value) : $value;
    if ($field === 'template' && doc('template') != $new_value) {
        echo '<script>jQuery(function(){documentDirty=false;';
        echo "jQuery('#mutate input[name=\"a\"]').val(4);";
        echo "jQuery('#mutate input[name=\"newtemplate\"]').val(" . $new_value . ");";
        echo "jQuery('#mutate').submit();});</script>";
    }

    $output = "//  -------------- mm_default :: Begin ------------- \n";

    // Work out the correct date time format based on the config setting
    $date_format = evo()->toDateFormat(null, 'formatOnly');

    switch ($field) {
        case 'pub_date':
        case 'unpub_date':
            if ($new_value == '') {
                $new_value = evo()->mb_strftime($date_format . ' %H:%M:%S');
            }
            $output .= sprintf(
                'jQuery("input[name=%s]").val("%s"); ' . "\n",
                $field,
                jsSafe($new_value)
            );
            break;

        case 'published':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=published]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= sprintf('jQuery("input[name=%scheck]").prop("checked", true); ' . "\n", $field);
            } else {
                $output .= 'jQuery("input[name=publishedcheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'hide_menu':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=hidemenu]").val("' . $new_value . '"); ' . "\n";

            if (!$value) {
                $output .= 'jQuery("input[name=hidemenucheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=hidemenucheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'show_in_menu':
            $new_value = ($value) ? '0' : '1'; // Note these are reversed from what you'd think
            $output .= 'jQuery("input[name=hidemenu]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=hidemenucheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=hidemenucheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'searchable':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=searchable]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=searchablecheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=searchablecheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'cacheable':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=cacheable]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=cacheablecheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=cacheablecheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'clear_cache':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=syncsite]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=syncsitecheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=syncsitecheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'container':
        case 'is_folder':
            $new_value = ($value) ? '1' : '0';
            $output .= 'jQuery("input[name=isfolder]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=isfoldercheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=isfoldercheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'is_richtext':
        case 'richtext':
            $new_value = ($value) ? '1' : '0';
            $output .= 'var originalRichtextValue = jQuery("#which_editor:first").val(); ' . "\n";
            $output .= 'jQuery("input[name=richtext]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=richtextcheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= '
                jQuery("input[name=richtextcheck]").removeAttr("checked");
                // Make the RTE displayed match the default value that has been set here
                if (originalRichtextValue&&originalRichtextValue != "none") {
                    jQuery("#which_editor").val("none");
                    changeRTE();
                }
                ';

                $output .= '' . "\n";
            }
            break;

        case 'log':
            $new_value = ($value) ? '0' : '1';    // Note these are reversed from what you'd think
            $output .= 'jQuery("input[name=donthit]").val("' . $new_value . '"); ' . "\n";

            if ($value) {
                $output .= 'jQuery("input[name=donthitcheck]").prop("checked", true); ' . "\n";
            } else {
                $output .= 'jQuery("input[name=donthitcheck]").removeAttr("checked"); ' . "\n";
            }
            break;

        case 'content_type':
            $output .= 'jQuery("select[name=contentType]").val("' . $new_value . '");' . "\n";
            break;

        default:
            $tv = $mm_fields[$field];
            if ($tv['tvtype'] === 'option') {
                $tpl = 'jQuery("%s[name=%s]").val(["%s"])';
            } else {
                $tpl = 'jQuery("%s[name=%s]").val("%s");';
            }
            $output .= sprintf(
                    $tpl,
                    isset($tv['fieldtype']) ? $tv['fieldtype'] : '*',
                    $tv['fieldname'],
                    $new_value
                ) . "\n";
            break;
    }

    $output .= "//  -------------- mm_default :: End ------------- \n";

    event()->output($output . "\n");
}
