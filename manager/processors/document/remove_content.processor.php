<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('empty_trash')) {
    alert()->setError(3);
    alert()->dumpError();
}

// id があればその 1 件、無ければゴミ箱の全件。id が整数として読めないときは、全件の削除に進まないよう止める
if (anyv('id') !== null && anyv('id') !== '') {
    $id = request_intvar('id');
    if (!$id) {
        alert()->setError(2);
        alert()->dumpError();
        exit;
    }
    $ids = [$id];
} else {
    $rs = db()->select('id', '[+prefix+]site_content', 'deleted=1');
    $ids = [];
    if (db()->count($rs) > 0) {
        while ($row = db()->getRow($rs)) {
            $ids[] = (int)$row['id'];
        }
    }
}

// 対象の文書それぞれについて、ドキュメントグループと許可するリソースの範囲を確かめる。
// 1 件でも通らなければ、何も消さずに全体を止める
foreach ($ids as $docid) {
    if (!evo()->checkPermissions($docid) || !manager()->isInAllowedParents($docid)) {
        alert()->setError(3, lang('access_permission_denied'));
        alert()->dumpError();
        exit;
    }
}

// invoke OnBeforeEmptyTrash event
$modx->event->vars['ids'] = &$ids;
evo()->invokeEvent('OnBeforeEmptyTrash', $modx->event->vars);

if (!empty($ids)) {
    $ids_list = implode(',', array_map('intval', $ids));
    
    // remove the document groups link.
    $tbl_document_groups = evo()->getFullTableName('document_groups');
    $tbl_site_content = evo()->getFullTableName('site_content');
    $sql = "DELETE {$tbl_document_groups}
            FROM {$tbl_document_groups}
            INNER JOIN {$tbl_site_content} ON {$tbl_site_content}.id = {$tbl_document_groups}.document
            WHERE {$tbl_site_content}.id IN ({$ids_list}) AND {$tbl_site_content}.deleted=1";
    db()->query($sql);
    
    // remove the TV content values.
    $tbl_site_tmplvar_contentvalues = evo()->getFullTableName('site_tmplvar_contentvalues');
    $sql = "DELETE {$tbl_site_tmplvar_contentvalues}
            FROM {$tbl_site_tmplvar_contentvalues}
            INNER JOIN {$tbl_site_content} ON {$tbl_site_content}.id = {$tbl_site_tmplvar_contentvalues}.contentid
            WHERE {$tbl_site_content}.id IN ({$ids_list}) AND {$tbl_site_content}.deleted=1";
    db()->query($sql);
    
    // permanently delete the document.
    $rs = db()->delete($tbl_site_content, "id IN ({$ids_list}) AND deleted=1");
} else {
    // No documents to delete - treat as successful operation
    $rs = true;
}
if (!$rs) {
    exit("Something went wrong while trying to remove deleted documents!");
} else {
    // invoke OnEmptyTrash event
    evo()->invokeEvent('OnEmptyTrash', $modx->event->vars);
    $modx->event->vars = [];
    // empty cache
    $modx->clearCache(); // first empty the cache
    // finished emptying cache - redirect
    header("Location: index.php?r=1&a=7");
}
