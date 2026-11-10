<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (request_intvar('id')) {
    return get_resources_byajax(request_intvar('id'));
}

function get_resources_byajax($id)
{
    global $_lang;

    // 文書グループによる絞り込み（判定は ManagerAPI::documentAccessCondition()）
    $where = "template='" . $id . "'";
    $accessCond = manager()->documentAccessCondition('');
    if ($accessCond !== '') {
        $where .= ' AND ' . $accessCond;
    }
    $rs = db()->select(
        'pagetitle,id',
        '[+prefix+]site_content',
        $where
    );
    $total = db()->count($rs);
    if (evo()->config['limit_by_container'] < $total) {
        return sprintf('<p>%s</p>', $_lang['a16_many_resources']);
    }
    if ($total === 0) {
        return sprintf('<p>%s</p>', $_lang['a16_no_resource']);
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

