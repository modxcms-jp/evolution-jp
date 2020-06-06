<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!$modx->hasPermission('delete_user')) {
    $e->setError(3);
    $e->dumpError();
}
$id = intval($_GET['id']);

// delete the user, but first check if we are deleting our own record
if ($id == $modx->getLoginUserID()) {
    echo "You can't delete yourself!";
    exit;
}

$tbl_manager_users = $modx->getFullTableName('manager_users');
$tbl_member_groups = $modx->getFullTableName('member_groups');
$tbl_user_settings = $modx->getFullTableName('user_settings');
$tbl_user_attributes = $modx->getFullTableName('user_attributes');

// get user name
$rs = $modx->db->select('username', $tbl_manager_users, "id='{$id}'", '', 1);
if ($rs) {
    $username = $modx->db->getValue($rs);
}

// invoke OnBeforeUserFormDelete event
$tmp = array("id" => $id);
$modx->invokeEvent("OnBeforeUserFormDelete", $tmp);

//ok, delete the user.
$modx->db->delete($tbl_manager_users, "id='{$id}'")
or exit("Something went wrong while trying to delete the user...");
$modx->db->delete($tbl_member_groups, "member='{$id}'")
or exit("Something went wrong while trying to delete the user's access permissions...");

// delete user settings
$modx->db->delete($tbl_user_settings, "user='{$id}'")
or exit("Something went wrong while trying to delete the user's settings...");

// delete the attributes
$modx->db->delete($tbl_user_attributes, "internalKey='{$id}'")
or exit('Something went wrong while trying to delete the user attributes...');

// invoke OnManagerDeleteUser event
$tmp = array(
    "userid" => $id,
    "username" => $username
);
$modx->invokeEvent("OnManagerDeleteUser", $tmp);

// invoke OnUserFormDelete event
$tmp = array('id' => $id);
$modx->invokeEvent("OnUserFormDelete", $tmp);

header('Location: index.php?a=75');
