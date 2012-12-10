<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_web_user')) {
	$e->setError(3);
	$e->dumpError();
}
$id=intval($_GET['id']);

$tbl_web_users           = $modx->getFullTableName('web_users');
$tbl_web_groups          = $modx->getFullTableName('web_groups');
$tbl_web_user_attributes = $modx->getFullTableName('web_user_attributes');

// get user name
$rs = $modx->db->select('*',$tbl_web_users,"id='{$id}'",'','1');
if($rs) {
	$row = $modx->db->getRow($rs);
	$username = $row['username'];
}


// invoke OnBeforeWUsrFormDelete event
$modx->invokeEvent("OnBeforeWUsrFormDelete",
					array(
						"id"	=> $id
					));

// delete the user.
$rs = $modx->db->delete($tbl_web_users,"id='{$id}'");
if(!$rs) {
	echo "Something went wrong while trying to delete the web user...";
	exit;
}
// delete user groups
$rs = $modx->db->delete($tbl_web_groups,"webuser='{$id}'");
if(!$rs) {
	echo "Something went wrong while trying to delete the web user's access permissions...";
	exit;
}
// delete the attributes
$rs = $modx->db->delete($tbl_web_user_attributes,"internalKey='{$id}'");
if(!$rs) {
	echo "Something went wrong while trying to delete the web user attributes...";
	exit;
} else {
	// invoke OnWebDeleteUser event
	$modx->invokeEvent("OnWebDeleteUser",
						array(
							"userid"		=> $id,
							"username"		=> $username
						));

	// invoke OnWUsrFormDelete event
	$modx->invokeEvent("OnWUsrFormDelete",
						array(
							"id"	=> $id
						));

	header("Location: index.php?a=99");
}
