<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!$modx->hasPermission('settings')) {
    $e->setError(3);
    $e->dumpError();
}

$rs = $modx->db->truncate('[+prefix+]manager_log');

header('Location: index.php?a=13');
