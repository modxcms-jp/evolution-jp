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

function mm_widget_tags($fields, $delimiter = ',', $source = '', $display_count = false, $roles = '', $templates = '', $default=''){
    global $modx, $mm_fields, $mm_current_page;
    $e = &$modx->event;

    if ($e->name != 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = '';

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
    $output .= "//  -------------- Tag widget include ------------- \n";
    $output .= includeJs($modx->config['base_url'] . 'assets/plugins/managermanager/widgets/tags/tags.js');
    $output .= includeCss($modx->config['base_url'] . 'assets/plugins/managermanager/widgets/tags/tags.css');

    // Go through each of the fields supplied
    foreach ($fields as $targetTv) {
        $tv_id = $mm_fields[$targetTv]['fieldname'];

        // Make an SQL friendly list of fields to look at:
        //$escaped_sources = array();
        //foreach ($source as $s){
        //	$s=substr($s,2,1);
        //	$escaped_sources[] = "'".$s."'";
        //}

        $sql_sources = implode(',', $source_tvs[0]);

        // Get the list of current values for this TV
        $result = $modx->db->select(
            'value'
            , $modx->getFullTableName('site_tmplvar_contentvalues')
            , sprintf("tmplvarid IN ('%s')", $sql_sources)
        );
        $all_docs = $modx->db->makeArray($result);

        $foundTags = explode(',', $default);
        $foundTags = array();
        foreach ($all_docs as $theDoc) {
            $theTags = explode($delimiter, $theDoc['value']);
            foreach ($theTags as $t) {
                $foundTags[trim($t)]++;
            }
        }

        $default = explode(',', $default);
        foreach ($default as $k) {
            if(!isset($foundTags[$k])) {
                $foundTags[$k] = 0;
            }
        }
        
        // Sort the TV values (case insensitively)
        uksort($foundTags, 'strcasecmp');

        $lis = '';
        foreach ($foundTags as $t => $c) {
            $lis .= sprintf(
                '<li title="Used %s times">%s%s</li>'
                , $c
                , jsSafe($t)
                , $display_count ? sprintf(' (%s)', $c) : ''
            );
        }

        $html_list = sprintf(
            '<ul class="mmTagList" id="%s_tagList">%s</ul>'
            , $tv_id
            , $lis
        );

        // Insert the list of tags after the field
        $output .= sprintf("
        //  -------------- Tag widget for %s (%s) --------------
        jQuery('#%s').after('%s');
        ", $targetTv, $tv_id, $tv_id, $html_list);

        // Initiate the tagCompleter class for this field
        $output .= $modx->parseText(
            'var [+tv_id+]_tags = new TagCompleter("[+tv_id+]", "[+tv_id+]_tagList", "[+delim+]"); '
            , array(
                'tv_id' => $tv_id,
                'delim' => $delimiter
            )
        ) . "\n";
    }
    $e->output($output . "\n");
}
