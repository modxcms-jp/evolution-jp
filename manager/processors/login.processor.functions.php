<?php
if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
	header('HTTP/1.0 404 Not Found');exit;
}
// show javascript alert
function jsAlert($msg){
	global $modx, $modx_manager_charset;
	header('Content-Type: text/html; charset='.$modx_manager_charset);
	if($_POST['ajax']==1) echo $msg;
	else {
		$msg = $modx->db->escape($msg);
		echo "<script>alert('{$msg}');";
		echo "history.go(-1);";
		echo "</script>";
	}
}

function failedLogin($dbv_internalKey,$dbv_failedlogincount) {
	global $modx;
	
	//increment the failed login counter
	$dbv_failedlogincount += 1;
	$f = array('failedlogincount'=>$dbv_failedlogincount);
	$rs = $modx->db->update($f, '[+prefix+]user_attributes', "internalKey='{$dbv_internalKey}'");
	if($modx->config['failed_login_attempts']<=$dbv_failedlogincount) {
		//block user for too many fail attempts
		$blockeduntil = $_SERVER['REQUEST_TIME']+($modx->config['blocked_minutes']*60);
        $rs = $modx->db->update(array('blockeduntil'=>$blockeduntil), '[+prefix+]user_attributes', "internalKey='{$dbv_internalKey}'");
    } else {
		//sleep to help prevent brute force attacks
        $sleep = (int) $dbv_failedlogincount/2;
        if(5<$sleep) $sleep = 5;
        sleep($sleep);
    }
	@session_destroy();
	session_unset();
}

function login($username,$givenPassword,$dbasePassword) {
	global $modx;
	return $modx->phpass->CheckPassword($givenPassword, $dbasePassword);
}

function loginV1($internalKey,$givenPassword,$dbasePassword,$username) {
	global $modx;
	
	$user_algo = $modx->manager->getV1UserHashAlgorithm($internalKey);
	
	if(!isset($modx->config['pwd_hash_algo']) || empty($modx->config['pwd_hash_algo']))
		$modx->config['pwd_hash_algo'] = 'UNCRYPT';
	
	if($user_algo !== $modx->config['pwd_hash_algo']) {
		$bk_pwd_hash_algo = $modx->config['pwd_hash_algo'];
		$modx->config['pwd_hash_algo'] = $user_algo;
	}
	
	if($dbasePassword != $modx->manager->genV1Hash($givenPassword, $internalKey)) {
		return false;
	}
	
	updateNewHash($username,$givenPassword);
	
	return true;
}

function loginMD5($internalKey,$givenPassword,$dbasePassword,$username) {
	if($dbasePassword != md5($givenPassword)) return false;
	updateNewHash($username,$givenPassword);
	return true;
}

function updateNewHash($username,$password) {
	global $modx;
	
	$field = array();
	$field['password'] = $modx->phpass->HashPassword($password);
	$modx->db->update($field, '[+prefix+]manager_users', "username='{$username}'");
}
