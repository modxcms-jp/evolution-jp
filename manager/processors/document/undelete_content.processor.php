<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = request_intvar('id');

if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

// check permissions on the document
if (!$modx->checkPermissions($id) || !manager()->isInAllowedParents($id)) {
    disp_access_permission_denied();
}

// get the timestamp on which the document was deleted.
$rs = db()->select('deletedon', '[+prefix+]site_content', "id='{$id}' AND deleted=1");
if (db()->count($rs) != 1) {
    exit("Couldn't find document to determine it's date of deletion!");
}
$deltime = db()->getValue($rs);

$children = getChildren($id, $deltime);

// invoke OnBeforeDocFormUnDelete event
$params['id'] = $id;
$params['children'] = $children;
$params['enableProcess'] = true;
evo()->invokeEvent("OnBeforeDocFormUnDelete", $params);
if ($params['enableProcess'] == false) {
    $modx->webAlertAndQuit("The undeletion process was interrupted by plugin.");
}

$field = [];
$field['deleted'] = '0';
$field['deletedby'] = '0';
$field['deletedon'] = '0';

if (0 < count($children)) {
    $docs_to_undelete = implode(' ,', $children);
    $rs = db()->update($field, '[+prefix+]site_content', "id IN({$docs_to_undelete})");
    if (!$rs) {
        exit("Something went wrong while trying to set the document's children to undeleted status...");
    }
}
//'undelete' the document.
$rs = db()->update($field, '[+prefix+]site_content', "id='{$id}'");
if (!$rs) {
    exit("Something went wrong while trying to set the document to undeleted status...");
}

// invoke OnDocFormUnDelete event
$params['id'] = $id;
$params['children'] = $children;
evo()->invokeEvent("OnDocFormUnDelete", $params);

// empty cache
$modx->clearCache();
// finished emptying cache - redirect
$pid = db()->getValue(db()->select('parent', '[+prefix+]site_content', "id='{$id}'"));
include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');
if ($pid !== '0') {
    $header = 'Location: ' . ChildDocumentListState::returnUrl((int) $pid);
} else {
    $header = "Location: index.php?a=2&refresh=tree";
}
header($header);


// 削除時刻が同じ（一緒に削除された）子孫の id を返す
function getChildren($parent, $deltime)
{
    $children = [];
    $rs = db()->select('id', '[+prefix+]site_content',
        "parent={$parent} AND deleted=1 AND deletedon='{$deltime}'");
    while ($row = db()->getRow($rs)) {
        $children[] = $row['id'];
        // 子の子も対象にする
        $children = array_merge($children, getChildren($row['id'], $deltime));
    }
    return $children;
}

function disp_access_permission_denied()
{
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?= lang('access_permissions') ?></div>
    <div class="sectionBody">
    <p><?= lang('access_permission_denied') ?></p>
    <?php
    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}
