<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission(EvoElementTypes::get('template')['permissions']['delete'])) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

// 使用中かの判定は権限で絞らず全件で行い、一覧に出す行だけ開ける文書に絞る
$where = sprintf('template=%d AND deleted=0', $id);
$limit = db()->count(db()->select('id', '[+prefix+]site_content', $where));
if ($limit > 0) {
    $accessCond = manager()->documentAccessCondition('');
    $rs = db()->select(
        'id, pagetitle',
        '[+prefix+]site_content',
        $accessCond !== '' ? "{$where} AND {$accessCond}" : $where
    );
    $shown = db()->count($rs);
    echo "This template is in use. Please set the documents using the template to another template. Documents using this template:<br />";
    while ($row = db()->getRow($rs)) {
        echo (int) $row['id'] . ' - ' . hsc($row['pagetitle']) . "<br />\n";
    }
    if ($limit > $shown) {
        echo hsc(sprintf(lang('resources_hidden_by_permission'), $limit - $shown)) . "<br />\n";
    }
    exit;
}

if ($id == config('default_template')) {
    echo "This template is set as the default template. Please choose a different default template in the MODx configuration before deleting this template.<br />";
    exit;
}

include __DIR__ . '/../element/delete_element.processor.php';
