<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    alert()->setError(3);
    alert()->dumpError();
}

db()->truncate('[+prefix+]manager_log');

header('Location: index.php?a=13');
