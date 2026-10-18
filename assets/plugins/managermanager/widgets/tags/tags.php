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

    $source_tv_ids = array_map('intval', array_column($source_tvs, 'id'));
    $defaults = explode(',', $default);

    // Go through each of the fields supplied
    foreach ($fields as $targetTv) {
        if (!isset($mm_fields[$targetTv])) {
            continue;
        }
        $foundTags = [];
        if (strpos($default, '@fix') !== 0) {
            // Get the list of current values for this TV
            $result = db()->select(
                'value',
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf(
                    'tmplvarid IN (%s)',
                    implode(',', $source_tv_ids)
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
                    if (!isset($foundTags[$tag])) {
                        $foundTags[$tag] = 0;
                    }
                    $foundTags[$tag]++;
                }
            }
            // Sort the TV values (case insensitively)
            uksort($foundTags, 'strcasecmp');
        }

        foreach ($defaults as $k) {
            $tag = trim($k);
            if ($tag === '' || strpos($tag, '@fix') === 0) {
                continue;
            }
            if (!isset($foundTags[$tag])) {
                $foundTags[$tag] = 0;
            }
        }

        $lis = '';
        foreach ($foundTags as $t => $c) {
            $lis .= sprintf(
                '<li title="Used %s times">%s%s</li>',
                $c,
                hsc($t),
                $display_count ? sprintf(' (%s)', $c) : ''
            );
        }

        $tv_id = $mm_fields[$targetTv]['fieldname'];
        // fieldname の \\[\\] は CSS のエスケープなので、getElementById には外して渡す
        $entry_id = str_replace('\\', '', $tv_id);
        $html_list = sprintf(
            '<ul class="mmTagList" id="%s_tagList">%s</ul>',
            $tv_id,
            $lis
        );

        // Insert the list of tags after the field
        $output .= sprintf("
        //  -------------- Tag widget for %s (%s) --------------
        MM.all([document.getElementById(%s)]).forEach(function (el) { el.insertAdjacentHTML('afterend', %s); });
        ", $targetTv, $tv_id, js_literal($entry_id), js_literal($html_list));

        // Initiate the tagCompleter class for this field
        $output .= sprintf(
                'var %s_tags = new TagCompleter(%s, %s, %s); ',
                preg_replace('/\W/', '_', $tv_id),
                js_literal($entry_id),
                js_literal($tv_id . '_tagList'),
                js_literal($delimiter)
            ) . "\n";
    }
    event()->output($output . "\n");
}
