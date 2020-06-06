<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_eventlog')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (getv('cls') == 1) {
    $rs = db()->truncate('[+prefix+]event_log');
} elseif ((int)getv('id')) {
    $rs = db()->delete(
        '[+prefix+]event_log'
        , sprintf("id='%d'", (int)getv('id'))
    );
    if ($rs) {
        if (!db()->select('*', '[+prefix+]event_log')) {
            db()->truncate('[+prefix+]event_log');
        }
    }
}

header('Location: index.php?a=114');
