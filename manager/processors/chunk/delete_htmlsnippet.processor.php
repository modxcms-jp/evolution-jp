<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_snippet')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int)getv('id');

$tmp = ['id' => $id];
evo()->invokeEvent('OnBeforeChunkFormDelete', $tmp);

$rs = db()->delete('[+prefix+]site_htmlsnippets', where('id', '=', $id));
if (!$rs) {
    exit('Something went wrong while trying to delete the htmlsnippet...');
}

$tmp = ['id' => $id];
evo()->invokeEvent('OnChunkFormDelete', $tmp);

evo()->clearCache();

header('Location: index.php?a=76');
