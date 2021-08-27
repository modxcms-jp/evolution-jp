<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('messages')) {
    alert()->setError(3);
    alert()->dumpError();
}

$rs = db()->select(
    'recipient',
    '[+prefix+]user_messages',
    where('id', '=', anyv('id'))
);
if (db()->count($rs) != 1) {
    exit('Wrong number of messages returned!');
}

$row = db()->getRow($rs);
if ($row['recipient'] != evo()->getLoginUserID()) {
    exit('You are not allowed to delete this message!');
}

if (!db()->delete('[+prefix+]user_messages', where('id', '=', anyv('id')))) {
    exit('Something went wrong while trying to delete the message!');
}
header('Location: index.php?a=10');
