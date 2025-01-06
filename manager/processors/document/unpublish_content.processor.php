<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_document') || !evo()->hasPermission('publish_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = $_REQUEST['id'];

// check permissions on the document
if (!$modx->checkPermissions($id)) {
    include(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?= $_lang['access_permissions'] ?></div>
    <div class="sectionBody">
    <p><?= $_lang['access_permission_denied'] ?></p>
    <?php
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}

$doc = $modx->db->getObject('site_content', "id='" . $id . "'");

// update the document
$field['published'] = 0;
if ($doc->pub_date < time()) {
    $field['pub_date'] = 0;
}
$field['publishedby'] = 0;
$field['publishedon'] = 0;
$field['editedon'] = time();
$field['editedby'] = evo()->getLoginUserID();

$rs = db()->update($field, '[+prefix+]site_content', "id='" . $id . "'");
if (!$rs) {
    exit("An error occured while attempting to unpublish the document.");
}

// invoke OnDocUnPublished  event
$tmp = array('docid' => $id, 'type' => 'manual');
evo()->invokeEvent('OnDocUnPublished', $tmp);

$modx->clearCache();

$pid = db()->getValue(db()->select('parent', '[+prefix+]site_content', "id='" . $id . "'"));
$page = (isset($_GET['page'])) ? "&page=" . $_GET['page'] : '';
if ($pid !== '0') {
    $header = "Location: index.php?r=1&a=120&id={$pid}{$page}";
} else {
    $header = "Location: index.php?a=2&r=1";
}

header($header);
