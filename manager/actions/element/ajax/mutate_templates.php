<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (request_intvar('id')) {
    return get_resources_byajax(request_intvar('id'));
}

function get_resources_byajax($id)
{

    $rs = db()->select(
        'pagetitle,id',
        '[+prefix+]site_content',
        "template='" . $id . "'"
    );
    $total = db()->count($rs);
    if (config('limit_by_container') < $total) {
        return sprintf('<p>%s</p>', lang('a16_many_resources'));
    }
    if ($total === 0) {
        return sprintf('<p>%s</p>', lang('a16_no_resource'));
    }

    $items = [];
    while ($ph = db()->getRow($rs)) {
        $ph['id'] = (int)$ph['id'];
        $ph['pagetitle'] = hsc($ph['pagetitle']);
        $items[] = evo()->parseText(
            '<a href="index.php?a=27&id=[+id+]">[+pagetitle+]([+id+])</a>',
            $ph
        );
    }
    return sprintf('<p>%s</p>', implode(', ', $items));
}

