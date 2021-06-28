<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('messages')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = $_REQUEST['id'];

// check the user is allowed to delete this message
$tbl_user_messages = evo()->getFullTableName('user_messages');
$rs = db()->select('recipient', $tbl_user_messages, "id={$id}");
if (db()->count($rs) != 1) {
    echo 'Wrong number of messages returned!';
    exit;
}

$row = db()->getRow($rs);
if ($row['recipient'] != evo()->getLoginUserID()) {
    echo 'You are not allowed to delete this message!';
    exit;
}

// delete message
$rs = db()->delete($tbl_user_messages, "id={$id}");
if (!$rs) {
    echo 'Something went wrong while trying to delete the message!';
    exit;
}
header('Location: index.php?a=10');
