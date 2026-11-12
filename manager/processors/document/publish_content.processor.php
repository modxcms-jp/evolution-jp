<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_document') || !evo()->hasPermission('publish_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = request_intvar('id');

if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

include_once(__DIR__ . '/document_state.functions.php');

// check permissions on the document
$denied = docPublishCheck($id, true);
if ($denied === 'access_permission_denied') {
    include(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?= lang('access_permissions') ?></div>
    <div class="sectionBody">
    <p><?= lang('access_permission_denied') ?></p>
    <?php
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}
if ($denied !== '') {
    alert()->setError(3);
    alert()->dumpError();
}

docPublishExecute($id);

evo()->clearCache();

docPublishFireEvent($id);

$pid = db()->getValue(db()->select('parent', '[+prefix+]site_content', "id='{$id}'"));
include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');
if ($pid !== '0') {
    $header = 'Location: ' . ChildDocumentListState::returnUrl((int) $pid);
} else {
    $header = "Location: index.php?a=2&refresh=tree";
}
header($header);
