<?php


//-----------------------------------------------------------------------
//   Utility functions
//
//-----------------------------------------------------------------------


// Pass useThisRule a comma separated list of allowed roles and templates, and it will
// return TRUE or FALSE to indicate whether this rule should be run on this page
function useThisRule($roles = '', $templates = '')
{

    global $mm_current_page;

    $exclude_templates = false;

    // Are they negative roles?
    if (strpos($roles, '!') === 0) {
        $roles = substr($roles, 1);
        $match_role_list = !in_array($mm_current_page['role'], makeArray($roles));
    } else {
        $match_role_list = in_array($mm_current_page['role'], makeArray($roles));
    }

    if (strpos($templates, '!') === 0) {
        $templates = substr($templates, 1);
        $match_template_list = !in_array($mm_current_page['template'], makeArray($templates));
    } else {
        $match_template_list = in_array($mm_current_page['template'], makeArray($templates));
    }

    return ($match_role_list || !makeArray($roles)) && ($match_template_list || !makeArray($templates));
}

// Makes a commas separated list into an array
function makeArray($csv)
{
    // If we've already been supplied an array, just return it
    if (is_array($csv)) {
        return $csv;
    }

    // Else if we have an empty string
    if (trim($csv) == '') {
        return [];
    }

    // Otherwise, turn it into an array
    $return = explode(',', $csv);
    foreach ($return as $i => $v) {
        $return[$i] = trim($v);
    }
    return $return;
}

// Make an output JS safe
function jsSafe($str)
{
    global $modx;

    return htmlentities($str, ENT_QUOTES, $modx->config('modx_charset', 'utf-8'), false);
}

// Does the specified template use the specified TVs?
// $tpl_id = Template ID (int)
// $tvs = TV names - either array or comma separated list
// $types = TV types - e.g. image
function tplUseTvs($tpl_id, $tvs = '', $types = '')
{

    // If it's a blank template, it can't have TVs
    if ($tpl_id == 0) {
        return false;
    }

    // Make the TVs and field types into an array
    $fields = makeArray($tvs);
    $types = makeArray($types);

    // Get the DB table names
    $from = ['[+prefix+]site_tmplvars tvs'];
    $from[] = 'LEFT JOIN [+prefix+]site_tmplvar_templates rel ON rel.tmplvarid = tvs.id';

    $where = [];
    if ($tpl_id) {
        $where[] = sprintf('rel.templateid=%s', $tpl_id);
    }
    if ($fields) {
        $where[] = sprintf('tvs.name IN %s', makeSqlList($fields));
    }
    if ($types) {
        $where[] = sprintf('type IN %s', makeSqlList($types));
    }
    if ($where) {
        $where = implode(' AND ', $where);
    }

    // Do the SQL query
    $result = db()->select('id', $from, $where);

    if (!db()->count($result)) {
        return false;
    }
    return db()->makeArray($result);
}

// Create a MySQL-safe list from an array
function makeSqlList($arr)
{
    $arr = makeArray($arr);
    foreach ($arr as $k => $tv) {
        $arr[$k] = sprintf("'%s'", db()->escape($tv)); // Escape them for MySQL
    }
    return sprintf(' (%s) ', implode(',', $arr));
}

// Generates the code needed to include an external script file.
// $url is the URL of the external script
// $output_type is either js or html - depending on where the output is appearing
function includeJs($url, $output_type = 'js')
{

    if ($output_type === 'js') {
        return sprintf(
            'jQuery("head").append(\' <script src="%s" type="text/javascript"></scr\'+\'ipt> \');' . "\n",
            $url);
    }
    if ($output_type === 'html') {
        return sprintf('<script src="%s" type="text/javascript"></script>' . "\n", $url);
    }
    return '';
}

// Generates the code needed to include an external CSS file.
// $url is any URL
// $output_type is either js or html - depending on where the output is appearing
function includeCss($url, $output_type = 'js')
{
    if ($output_type === 'js') {
        return sprintf(
            '$j("head").append(\' <link href="%s" rel="stylesheet" type="text/css" /> \');' . "\n",
            $url
        );
    }
    if ($output_type === 'html') {
        return sprintf(
            '<link href="%s" rel="stylesheet" type="text/css" />' . "\n",
            $url);
    }
    return '';
}
