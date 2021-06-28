<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('remove_locks')) {
    alert()->setError(3);
    alert()->dumpError();
}

// Remove locks
db()->query('TRUNCATE ' . evo()->getFullTableName('active_users'));
header("Location: index.php?a=7");
