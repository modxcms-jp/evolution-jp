<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('settings') || (!hasPermission('logs') && !hasPermission('bk_manager'))) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!anyv('t') && !anyv('u')) {
    alert()->setError(10);
    alert()->dumpError();
}

if (anyv('t')) {
    db()->optimize(anyv('t'));
}
if (anyv('u')) {
    db()->truncate(anyv('u'));
}

header('Location: index.php?a=' . (int)anyv('mode') . '&s=4');
