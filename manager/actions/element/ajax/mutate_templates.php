<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (preg_match('@^[1-9][0-9]*$@', anyv('id'))) {
    return get_resources_byajax(anyv('id'));
}

function get_resources_byajax($id) {
    global $_lang;

    $rs = db()->select(
        'pagetitle,id'
        , '[+prefix+]site_content'
        , "template='" . $id . "'"
    );
    $total = db()->count($rs);
    if (evo()->config['limit_by_container'] < $total) {
        return sprintf('<p>%s</p>', $_lang['a16_many_resources']);
    }
    if ($total === 0) {
        return sprintf('<p>%s</p>', $_lang['a16_no_resource']);
    }

    $items = array();
    while ($ph = db()->getRow($rs)) {
        $items[] = evo()->parseText(
            '<a href="index.php?a=27&id=[+id+]">[+pagetitle+]([+id+])</a>'
            , $ph
        );
    }
    return sprintf('<p>%s</p>', implode(', ', $items));
}

