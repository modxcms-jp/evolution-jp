<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_web_user')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int)(getv('id'));

$tbl_web_users = evo()->getFullTableName('web_users');
$tbl_web_groups = evo()->getFullTableName('web_groups');
$tbl_web_user_attributes = evo()->getFullTableName('web_user_attributes');

// get user name
$rs = db()->select('*', $tbl_web_users, "id='{$id}'", '', '1');
if ($rs) {
    $row = db()->getRow($rs);
    $username = $row['username'];
}


// invoke OnBeforeWUsrFormDelete event
$tmp = ["id" => $id];
evo()->invokeEvent("OnBeforeWUsrFormDelete", $tmp);

// delete the user.
$rs = db()->delete($tbl_web_users, "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the web user...";
    exit;
}
// delete user groups
$rs = db()->delete($tbl_web_groups, "webuser='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the web user's access permissions...";
    exit;
}
// delete the attributes
$rs = db()->delete($tbl_web_user_attributes, "internalKey='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the web user attributes...";
    exit;
}

//Delete user settings
db()->delete('[+prefix+]web_user_settings', "webuser='{$id}'");

// invoke OnWebDeleteUser event
$tmp = [
    "userid" => $id,
    "username" => $username
];
evo()->invokeEvent("OnWebDeleteUser", $tmp);

// invoke OnWUsrFormDelete event
$tmp = ["id" => $id];
evo()->invokeEvent("OnWUsrFormDelete", $tmp);

header("Location: index.php?a=99");
