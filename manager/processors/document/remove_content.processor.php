<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('empty_trash')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (isset($_REQUEST['id']) && preg_match('@^[1-9][0-9]*$@', $_REQUEST['id'])) {
    $ids = [(int)$_REQUEST['id']];
} else {
    $rs = db()->select('id', '[+prefix+]site_content', 'deleted=1');
    $ids = [];
    if (db()->count($rs) > 0) {
        while ($row = db()->getRow($rs)) {
            $ids[] = (int)$row['id'];
        }
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
    
    //'undelete' the document.
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
