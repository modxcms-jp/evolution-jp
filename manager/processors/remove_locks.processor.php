<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}

if (!$modx->hasPermission('remove_locks')) {
    $e->setError(3);
    $e->dumpError();
}

// Remove locks
$modx->db->query('TRUNCATE ' . $modx->getFullTableName('active_users'));
header("Location: index.php?a=7");
