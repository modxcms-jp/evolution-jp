<?php
// 128 / 129
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

evo()->loadExtension('REVISION');

$docid = postv('id');
$modx->revision->delete($docid, 'draft');
$modx->revision->delete($docid, 'standby');

$header = "Location: index.php?a=3&id={$docid}&r=1";
header($header);
