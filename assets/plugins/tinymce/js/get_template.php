<?php
// Get Template from resource for TinyMCE
// v0.1
// By Yamamoto
//
// Changelog:
// v0.1: First release
//
// To do:
// * xxx

// Config options
$templates_to_ignore = array();    // Template IDs to ignore from the link list
$include_page_ids = false;
$charset = 'UTF-8';
$sortby = 'menuindex'; // Could be menuindex or menutitle
$limit = 0;

/* That's it to config! */
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', true);
$self = 'assets/plugins/tinymce/js/get_template.php';
$base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
include_once($base_path . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

/* only display if manager user is logged in */
if ($modx->getLoginUserType() !== 'manager') {
    // Make output a real JavaScript file!
    header('Content-type: application/x-javascript');
    header('pragma: no-cache');
    header('expires: 0');

    exit('var mceTemplateList = Array();');
}

$modx->getSettings();

$ids = $modx->config['mce_template_docs'];
$chunks = $modx->config['mce_template_chunks'];

$output = false;

if (isset($_GET['docid']) && preg_match('@^[0-9]+$@', $_GET['docid'])) {
    $doc = $modx->getDocument($_GET['docid']);
    if ($doc) $output = $doc['content'];
} elseif (isset($_GET['chunk']) && preg_match('@^[0-9]+$@', $_GET['chunk'])) {
    $tbl_site_htmlsnippets = evo()->getFullTableName('site_htmlsnippets');
    $cid = $_GET['chunk'];
    $rs = db()->select('snippet', $tbl_site_htmlsnippets, "`id`='{$cid}' AND published=1");
    $content = db()->getValue($rs);
    if ($content) $output = $content;
} else {
    $list = array();
    $tpl = "['[+title+]', '[+site_url+]assets/plugins/tinymce/js/get_template.php?[+target+]', '[+description+]']";
    $ph['site_url'] = MODX_SITE_URL;

    if (isset($ids) && !empty($ids)) {
        $docs = $modx->getDocuments($ids, 1, 0, $fields = 'id,pagetitle,menutitle,description,content');
        foreach ($docs as $i => $a) {
            $ph['title'] = ($docs[$i]['menutitle'] !== '') ? $docs[$i]['menutitle'] : $docs[$i]['pagetitle'];
            $ph['target'] = 'docid=' . $docs[$i]['id'];
            $ph['description'] = $docs[$i]['description'];
            $list[] = $modx->parseText($tpl, $ph);
        }
    }

    if (isset($chunks) && !empty($chunks)) {
        $tbl_site_htmlsnippets = evo()->getFullTableName('site_htmlsnippets');
        if (strpos($chunks, ',') !== false) {
            $chunks = explode(',', $chunks);
            foreach ($chunks as $i => $v) {
                $chunks[$i] = db()->escape(trim($v));
            }
            $chunks = join("','", $chunks);
            $where = "`name` IN ('{$chunks}')";
            $orderby = "FIELD(name, '{$chunks}')";
        } else {
            $where = "`name`='{$chunks}'";
            $orderby = '';
        }

        $rs = db()->select('id,name,description', $tbl_site_htmlsnippets, $where, $orderby);

        while ($row = db()->getRow($rs)) {
            $ph['title'] = $row['name'];
            $ph['target'] = 'chunk=' . $row['id'];
            $ph['description'] = $row['description'];
            $list[] = $modx->parseText($tpl, $ph);
        }
    }

    if (0 < count($list)) $output = 'var tinyMCETemplateList = [' . join(',', $list) . '];';
}

if ($output) {
    header('Content-type: application/x-javascript');
    header('pragma: no-cache');
    header('expires: 0');
    echo $output;
}
