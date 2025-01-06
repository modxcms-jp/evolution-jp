<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_user')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int)(getv('id'));

// delete the user, but first check if we are deleting our own record
if ($id == evo()->getLoginUserID()) {
    echo "You can't delete yourself!";
    exit;
}

$tbl_manager_users = evo()->getFullTableName('manager_users');
$tbl_member_groups = evo()->getFullTableName('member_groups');
$tbl_user_settings = evo()->getFullTableName('user_settings');
$tbl_user_attributes = evo()->getFullTableName('user_attributes');

// get user name
$rs = db()->select('username', $tbl_manager_users, "id='{$id}'", '', 1);
if ($rs) {
    $username = db()->getValue($rs);
}

// invoke OnBeforeUserFormDelete event
$tmp = array("id" => $id);
evo()->invokeEvent("OnBeforeUserFormDelete", $tmp);

//ok, delete the user.
db()->delete($tbl_manager_users, "id='{$id}'")
or exit("Something went wrong while trying to delete the user...");
db()->delete($tbl_member_groups, "member='{$id}'")
or exit("Something went wrong while trying to delete the user's access permissions...");

// delete user settings
db()->delete($tbl_user_settings, "user='{$id}'")
or exit("Something went wrong while trying to delete the user's settings...");

// delete the attributes
db()->delete($tbl_user_attributes, "internalKey='{$id}'")
or exit('Something went wrong while trying to delete the user attributes...');

// invoke OnManagerDeleteUser event
$tmp = array(
    "userid" => $id,
    "username" => $username
);
evo()->invokeEvent("OnManagerDeleteUser", $tmp);

// invoke OnUserFormDelete event
$tmp = array('id' => $id);
evo()->invokeEvent("OnUserFormDelete", $tmp);

header('Location: index.php?a=75');
