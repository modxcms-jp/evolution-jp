<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}
$tbl_site_content = evo()->getFullTableName('site_content');
$tbl_site_templates = evo()->getFullTableName('site_templates');
$tbl_site_tmplvar_templates = evo()->getFullTableName('site_tmplvar_templates');

// delete the template, but first check it doesn't have any documents using it
// 使用中かの判定は権限で絞らず全件で行い、一覧に出す行だけ開ける文書に絞る
$where = "template='{$id}' and deleted=0";
$limit = db()->count(db()->select('id', $tbl_site_content, $where));
if ($limit > 0) {
    $accessCond = manager()->documentAccessCondition('');
    $rs = db()->select(
        'id, pagetitle',
        $tbl_site_content,
        $accessCond !== '' ? "{$where} and {$accessCond}" : $where
    );
    $shown = db()->count($rs);
    echo "This template is in use. Please set the documents using the template to another template. Documents using this template:<br />";
    while ($row = db()->getRow($rs)) {
        echo (int)$row['id'] . " - " . hsc($row['pagetitle']) . "<br />\n";
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

// invoke OnBeforeTempFormDelete event
$tmp = ['id' => $id];
evo()->invokeEvent('OnBeforeTempFormDelete', $tmp);

//ok, delete the document.
$rs = db()->delete($tbl_site_templates, "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the template...";
    exit;
}

$rs = db()->delete($tbl_site_tmplvar_templates, "templateid='{$id}'");

// invoke OnTempFormDelete event
$tmp = ['id' => $id];
evo()->invokeEvent('OnTempFormDelete', $tmp);

// empty cache
$modx->clearCache();

header('Location: index.php?a=76');
