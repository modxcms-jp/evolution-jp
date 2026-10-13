<?php
// 128 / 129
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$docid = post_intvar('id');
if (!$docid) {
    alert()->setError(2);
    alert()->dumpError();
}
if (!evo()->checkPermissions($docid)) {
    alert()->setError(3);
    alert()->dumpError();
}

evo()->loadExtension('REVISION');

$modx->revision->delete($docid, 'draft');
$modx->revision->delete($docid, 'standby');

$header = "Location: index.php?a=3&id={$docid}&r=1";
header($header);
