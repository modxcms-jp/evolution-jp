<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}

if (preg_match('@^[1-9][0-9]*$@', $_REQUEST['id'])) {
    return get_resources_byajax($_REQUEST['id']);
}

function get_resources_byajax($id) {
    global $modx, $_lang;

    $rs = db()->select('pagetitle,id', $modx->getFullTableName('site_content'), "template='{$id}'");
    $total = db()->getRecordCount($rs);
    if ($modx->config['limit_by_container'] < $total) {
        $result = $_lang['a16_many_resources'];
    } elseif ($total === 0) {
        $result = $_lang['a16_no_resource'];
    } else {
        $tpl = '<a href="index.php?a=27&id=[+id+]">[+pagetitle+]([+id+])</a>';
        $items = array();
        while ($ph = db()->getRow($rs)) {
            $items[] = $modx->parseText($tpl, $ph);
        }
        $result = join(', ', $items);
    }
    return "<p>{$result}</p>";
}

