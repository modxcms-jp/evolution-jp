<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_document') || !evo()->hasPermission('publish_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = intval(anyv('id'));

// check permissions on the document
if (!$modx->checkPermissions($id)) {
    include(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
    <div class="sectionBody">
    <p><?php echo $_lang['access_permission_denied']; ?></p>
    <?php
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}
$doc = $modx->db->getObject('site_content', "id='{$id}'");
if (!evo()->hasPermission('view_unpublished')) {
    if (evo()->getLoginUserID() != $doc->publishedby) {
        alert()->setError(3);
        alert()->dumpError();
    }
}

$now = time();
// update the document
$field['published'] = 1;
if ($now < $doc->pub_date) {
    $field['pub_date'] = 0;
}
if ($doc->unpub_date < $now) {
    $field['unpub_date'] = 0;
}
$field['publishedby'] = evo()->getLoginUserID();
$field['publishedon'] = $now;
$field['editedon'] = $now;
$rs = db()->update($field, '[+prefix+]site_content', "id='{$id}'");
if (!$rs) {
    exit("An error occured while attempting to publish the document.");
}

$modx->clearCache();

// invoke OnDocPublished  event
$tmp = array('docid' => $id, 'type' => 'manual');
evo()->invokeEvent('OnDocPublished', $tmp);

$pid = db()->getValue(db()->select('parent', '[+prefix+]site_content', "id='{$id}'"));
$page = (isset($_GET['page'])) ? "&page={$_GET['page']}" : '';
if ($pid !== '0') {
    $header = "Location: index.php?r=1&a=120&id={$pid}{$page}";
} else {
    $header = "Location: index.php?a=2&r=1";
}
header($header);
