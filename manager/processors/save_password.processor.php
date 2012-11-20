<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('save_password')) {
	$e->setError(3);
	$e->dumpError();
}

$password = $_POST['pass1'];

if($password!=$_POST['pass2']) $warning = '<p class="fail">passwords don\'t match!</p>';
elseif(empty($password))       $warning = '<p class="fail">passwords don\'t empty!</p>';
elseif(strlen($password)<6)    $warning = '<p class="fail">Password is too short. Please specify a password of at least 6 characters.</p>';
elseif(32<strlen($password))   $warning = '<p class="fail">Password is too long. Please specify a password of less than 32 characters.</p>';

if(isset($warning))
{
	$_SESSION['onetime_msg'] = $warning;
}
else
{
	$tbl_manager_users = $modx->getFullTableName('manager_users');
	$f['password'] = md5($password);
	$uid = $modx->getLoginUserID();
	$rs = $modx->db->update($f,$tbl_manager_users,"id='{$uid}'");
	if(!$rs){
		echo "An error occured while attempting to save the new password.";
		exit;
	}
	if($_SESSION['mgrForgetPassword']) unset($_SESSION['mgrForgetPassword']);
	$_SESSION['onetime_msg'] = '<p class="success">' . $_lang["change_password_success"] . '</p>';
}
header("Location: index.php?a=28");
