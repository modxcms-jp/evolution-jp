<?php
/**
 * mm_widget_tags
 * @version 1.1 (2012-11-13)
 *
 * Adds a tag selection widget to the specified TVs.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_tags/1.1
 *
 * @copyright 2012
 */

function mm_widget_tags(
    $fields,
    $delimiter = ',',
    $source = '',
    $display_count = false,
    $roles = '',
    $templates = '',
    $default = '')
{
    global $mm_fields, $mm_current_page;

    if (event()->name != 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // And likewise for the data source (if supplied)
    $source = $source ? makeArray($source) : $fields;

    // Does this page's template use any of these TVs? If not, quit.
    $field_tvs = tplUseTvs($mm_current_page['template'], $fields);
    if ($field_tvs == false) {
        return;
    }

    $source_tvs = tplUseTvs($mm_current_page['template'], $source);
    if ($source_tvs == false) {
        return;
    }

    // Insert some JS and a style sheet into the head
    $output = '';
    $output .= "//  -------------- Tag widget include ------------- \n";
    $output .= includeJs(evo()->config('base_url') . 'assets/plugins/managermanager/widgets/tags/tags.js');
    $output .= includeCss(evo()->config('base_url') . 'assets/plugins/managermanager/widgets/tags/tags.css');

    // Go through each of the fields supplied
    foreach ($fields as $targetTv) {
        $foundTags = [];
        if (strpos($default, '@fix') !== 0) {
            // Get the list of current values for this TV
            $result = db()->select(
                'value',
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf(
                    "tmplvarid IN ('%s')",
                    implode(',', $source_tvs[0])
                )
            );
            $all_docs = db()->makeArray($result);

            foreach ($all_docs as $theDoc) {
                $theTags = explode($delimiter, $theDoc['value']);
                foreach ($theTags as $t) {
                    $tag = trim($t);
                    if ($tag === '') {
                        continue;
                    }
                    $foundTags[$tag] = ($foundTags[$tag] ?? 0) + 1;
                }
            }
            // Sort the TV values (case insensitively)
            uksort($foundTags, 'strcasecmp');
        }

        $default = explode(',', $default);
        foreach ($default as $k) {
            if (strpos($k, '@fix') === 0) {
                continue;
            }
            if (!isset($foundTags[$k])) {
                $foundTags[$k] = 0;
            }
        }

        $lis = '';
        foreach ($foundTags as $t => $c) {
            $lis .= sprintf(
                '<li title="Used %s times">%s%s</li>',
                $c,
                jsSafe($t),
                $display_count ? sprintf(' (%s)', $c) : ''
            );
        }

        if (!isset($mm_fields[$targetTv])) {
            continue;
        }

        $tv_id = $mm_fields[$targetTv]['fieldname'];
        $html_list = sprintf(
            '<ul class="mmTagList" id="%s_tagList">%s</ul>',
            $tv_id,
            $lis
        );

        // Insert the list of tags after the field
        $output .= sprintf("
        //  -------------- Tag widget for %s (%s) --------------
        jQuery('#%s').after('%s');
        ", $targetTv, $tv_id, $tv_id, $html_list);

        // Initiate the tagCompleter class for this field
        $output .= evo()->parseText(
                'var [+tv_id+]_tags = new TagCompleter("[+tv_id+]", "[+tv_id+]_tagList", "[+delim+]"); ',
                [
                    'tv_id' => $tv_id,
                    'delim' => $delimiter
                ]
            ) . "\n";
    }
    event()->output($output . "\n");
}
