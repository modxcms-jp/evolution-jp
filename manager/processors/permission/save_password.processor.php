<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('save_password')) {
	$e->setError(3);
	$e->dumpError();
}

$password = $_POST['pass1'];

if($password!=$_POST['pass2']) $msg = '<p class="fail">passwords don\'t match!</p>';
elseif(empty($password))       $msg = '<p class="fail">passwords don\'t empty!</p>';
elseif(strlen($password)<6)    $msg = '<p class="fail">Password is too short. Please specify a password of at least 6 characters.</p>';
elseif(32<strlen($password))   $msg = '<p class="fail">Password is too long. Please specify a password of less than 32 characters.</p>';
else
{
	$uid = $modx->getLoginUserID();
	$f['password'] = $modx->manager->genHash($password, $uid);
	$rs = $modx->db->update($f,'[+prefix+]manager_users',"id='{$uid}'");
	if(!$rs) $msg = '<p class="fail">An error occured while attempting to save the new password.</p>';
	else
	{
		$userinfo = $modx->getUserInfo($uid);
		$msg = '<p class="success">' . $_lang["change_password_success"] . '</p>';
    $tmp = array (
			"userid" => $uid,
			"username" => $userinfo['username'],
			"userpassword" => $userinfo['password']
		);
		$modx->invokeEvent("OnManagerChangePassword", $tmp);
	}
}
$_SESSION['onetime_msg'] = $msg;
header("Location: index.php?a=28");
