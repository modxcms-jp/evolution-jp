<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_snippet')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int) getv('id');
$tbl_site_snippets = evo()->getFullTableName('site_snippets');

// invoke OnBeforeSnipFormDelete event
$tmp = array('id' => $id);
evo()->invokeEvent('OnBeforeSnipFormDelete', $tmp);

//ok, delete the snippet.
$rs = db()->delete($tbl_site_snippets, "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the snippet...";
    exit;
}

// invoke OnSnipFormDelete event
$tmp = array("id" => $id);
evo()->invokeEvent('OnSnipFormDelete', $tmp);

// empty cache
$modx->clearCache();

header('Location: index.php?a=76');
