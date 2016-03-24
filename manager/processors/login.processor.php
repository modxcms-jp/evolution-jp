<?php
if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
	header('HTTP/1.0 404 Not Found');exit;
}

$self = 'manager/processors/login.processor.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE',true);
include_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();
extract($modx->config);

include_once(MODX_CORE_PATH . 'log.class.inc.php');

$decoded_uri = urldecode($_SERVER['REQUEST_URI']);
if(strpos($decoded_uri,"'")!==false) {
	jsAlert("This is illegal login.");
	return;
}

// Initialize System Alert Message Queque
if (!isset($_SESSION['SystemAlertMsgQueque'])) $_SESSION['SystemAlertMsgQueque'] = array();
$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

$formv_password     = $modx->db->escape($_POST['password']);
$formv_captcha_code = (isset($_POST['captcha_code'])) ? $_POST['captcha_code'] : '';
$formv_rememberme   = (isset($_POST['rememberme']))   ? $_POST['rememberme']   : '';
$formv_username = $_POST['username'] ? $_POST['username'] : $_GET['username'];
$formv_username = $modx->db->escape($formv_username);
if(strpos($formv_username,':safemode')!==false)
{
	$_SESSION['safeMode'] = 1;
	$formv_username = str_replace(':safemode','',$formv_username);
}
else $_SESSION['safeMode'] = 0;

if(strpos($formv_username,':roleid=')!==false)
{
	list($formv_username,$forceRole) = explode(':roleid=',$formv_username,2);
	if(!preg_match('@^[0-9]+$@',$forceRole)) $forceRole = 1;
}

// invoke OnBeforeManagerLogin event
$info = array();
$info['username']     = $formv_username;
$info['userpassword'] = $formv_password;
$info['rememberme']   = $formv_rememberme;
$modx->invokeEvent('OnBeforeManagerLogin', $info);

if(isset($modx->config['manager_language'])) $manager_language = $modx->config['manager_language'];
else                                         $manager_language = 'english';

$_lang = array();
include_once(MODX_CORE_PATH . "lang/{$manager_language}.inc.php");

// include_once the error handler
include_once(MODX_CORE_PATH . 'error.class.inc.php');
$e = new errorHandler;

$field = 'mu.*, ua.*';
$from = '[+prefix+]manager_users mu,[+prefix+]user_attributes ua';
$where = "BINARY mu.username='{$formv_username}' and ua.internalKey=mu.id";
$rs = $modx->db->select($field, $from, $where);
$total = $modx->db->getRecordCount($rs);
if($total!=1) {
    jsAlert($e->errors[900]);
    return;
}

$row = $modx->db->getRow($rs);

$dbv_internalKey      = $row['internalKey'];
$dbv_password         = $row['password'];
$dbv_failedlogincount = $row['failedlogincount'];
$dbv_blocked          = $row['blocked'];
$dbv_blockeduntil     = $row['blockeduntil'];
$dbv_role             = ($row['role']==1 && isset($forceRole)) ? $forceRole : $row['role'];
$dbv_logincount       = $row['logincount'];
$dbv_fullname         = $row['fullname'];
$dbv_email            = $row['email'];

// blocked due to number of login errors.
if($_SERVER['REQUEST_TIME']<$dbv_blockeduntil)
{
	if($modx->config['failed_login_attempts']<=$dbv_failedlogincount)
	{
	    $modx->db->update('blocked=1','[+prefix+]user_attributes',"internalKey='{$dbv_internalKey}'");
	    @session_destroy();
	    session_unset();
	    jsAlert($e->errors[902]);
	    return;
	}
}
elseif($dbv_blocked==1)
{
	$dbv_failedlogincount  = '0';
	$dbv_blocked           = '0';
	$dbv_failedlogincount  = '0';
	$f = array();
	$f['failedlogincount'] = '0';
	$f['blocked']          = '0';
	$f['blockedafter']     = '0';
	$f['blockeduntil']     = '0';
    $rs = $modx->db->update($f, '[+prefix+]user_attributes',"internalKey='{$dbv_internalKey}'");
}

// get the user settings from the database
$rs = $modx->db->select('setting_name, setting_value','[+prefix+]user_settings',"user='{$dbv_internalKey}' AND setting_value!=''");
while ($row = $modx->db->getRow($rs))
{
    $user_settings{$row['setting_name']} = $row['setting_value'];
}

// allowed ip
if ($user_settings['allowed_ip'])
{
	$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	if($hostname!==false && $hostname != $_SERVER['REMOTE_ADDR'])
	{
		if(gethostbyname($hostname) != $_SERVER['REMOTE_ADDR'])
		{
			jsAlert("Your hostname doesn't point back to your IP!");
			return;
		}
	}
	$user_settings['allowed_ip'] = str_replace(' ','',$user_settings['allowed_ip']);
	if(!in_array($_SERVER['REMOTE_ADDR'], explode(',',$user_settings['allowed_ip'])))
	{
		jsAlert("You are not allowed to login from this location.");
		return;
	}
}

// allowed days
if ($user_settings['allowed_days']) {
    $date = getdate();
    $day = $date['wday']+1;
    if (strpos($user_settings['allowed_days'], (string)$day)===false) {
        jsAlert("You are not allowed to login at this time. Please try again later.");
        return;
    }
}

$modx->loadExtension('ManagerAPI');

// invoke OnManagerAuthentication event
$info = array();
$info['userid']        = $dbv_internalKey;
$info['username']      = $formv_username;
$info['userpassword']  = $formv_password;
$info['savedpassword'] = $dbv_password;
$info['rememberme']    = $formv_rememberme;

$rt = $modx->invokeEvent('OnManagerAuthentication', $info);

// check if plugin authenticated the user
if (!isset($rt) || !$rt || (is_array($rt) && !in_array(TRUE,$rt)))
{
	// check user password - local authentication
	$hashed_password = $modx->manager->genHash($formv_password, $dbv_internalKey);
	if(strpos($dbv_password,'>')!==false)
	{
		if(!isset($modx->config['pwd_hash_algo']) || empty($modx->config['pwd_hash_algo']))
			$modx->config['pwd_hash_algo'] = 'UNCRYPT';
		
		$user_algo = $modx->manager->getUserHashAlgorithm($dbv_internalKey);
		
		if($user_algo !== $modx->config['pwd_hash_algo'])
		{
			$bk_pwd_hash_algo = $modx->config['pwd_hash_algo'];
			$modx->config['pwd_hash_algo'] = $user_algo;
		}
		
		if($dbv_password != $hashed_password)
		{
			jsAlert($e->errors[901]);
			failedLogin($dbv_internalKey,$dbv_failedlogincount);
			return;
		}
		elseif(isset($bk_pwd_hash_algo))
		{
			$modx->config['pwd_hash_algo'] = $bk_pwd_hash_algo;
			$modx->db->update(array('password'=>$hashed_password), '[+prefix+]manager_users', "username='{$formv_username}'");
		}
	}
	else
	{
		if($dbv_password != md5($formv_password))
		{
			jsAlert($e->errors[901]);
			failedLogin($dbv_internalKey,$dbv_failedlogincount);
			return;
		}
		else
		{
			$modx->db->update(array('password'=>$hashed_password), '[+prefix+]manager_users', "username='{$formv_username}'");
		}
	}
}

if($modx->config['use_captcha']==1) {
	if (!isset ($_SESSION['veriword'])) {
		jsAlert('Captcha is not configured properly.');
		return;
	}
	elseif ($_SESSION['veriword'] != $formv_captcha_code) {
        jsAlert($e->errors[905]);
        failedLogin($dbv_internalKey,$dbv_failedlogincount);
        return;
    }
}

$_SESSION['usertype'] = 'manager'; // user is a backend user

// get permissions
$_SESSION['mgrShortname']    = $formv_username;
$_SESSION['mgrFullname']     = $dbv_fullname;
$_SESSION['mgrEmail']        = $dbv_email;
$_SESSION['mgrValidated']    = 1;
$_SESSION['mgrInternalKey']  = $dbv_internalKey;
$_SESSION['mgrFailedlogins'] = $dbv_failedlogincount;
$_SESSION['mgrLogincount']   = $dbv_logincount; // login count
$_SESSION['mgrRole']         = $dbv_role;
$rs = $modx->db->select('* ','[+prefix+]user_roles',"id='{$dbv_role}'");
$row = $modx->db->getRow($rs);

$_SESSION['mgrPermissions'] = $row;

if($_SESSION['mgrPermissions']['messages']==1) {
	$rs = $modx->db->select('*', '[+prefix+]manager_users');
	$total = $modx->db->getRecordCount($rs);
	if($total==1) $_SESSION['mgrPermissions']['messages']='0';
}
// successful login so reset fail count and update key values
if(isset($_SESSION['mgrValidated']))
{
	$now = $_SERVER['REQUEST_TIME'];
	$currentsessionid = session_id();
	$field = "failedlogincount=0, logincount=logincount+1, lastlogin=thislogin, thislogin='{$now}', sessionid='{$currentsessionid}'";
	$tbl_user_attributes = $modx->getFullTableName('user_attributes');
    $sql = "update {$tbl_user_attributes} SET {$field} where internalKey='{$dbv_internalKey}'";
    $rs = $modx->db->query($sql);
	$_SESSION['mgrLastlogin'] = $now;
}

$_SESSION['mgrDocgroups'] = $modx->manager->getMgrDocgroups($dbv_internalKey);

if($formv_rememberme == '1'):
    $_SESSION['modx.mgr.session.cookie.lifetime']= intval($modx->config['session.cookie.lifetime']);
	
	// Set a cookie separate from the session cookie with the username in it.
	// Are we using secure connection? If so, make sure the cookie is secure
	global $https_port;
	$expire = $_SERVER['REQUEST_TIME']+60*60*24*365;
	$path = $modx->config['base_url'];
	$secure = (isset($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == $https_port) ? true : false;
	if ( version_compare(PHP_VERSION, '5.2', '<') ) {
		setcookie('modx_remember_manager', $formv_username, $expire, $path, '; HttpOnly' , $secure );
	} else {
		setcookie('modx_remember_manager', $formv_username, $expire, $path, NULL,          $secure, true);
	}
else:
    $_SESSION['modx.mgr.session.cookie.lifetime']= 0;
	
	// Remove the Remember Me cookie
    $expire = $_SERVER['REQUEST_TIME'] - 3600;
	setcookie ('modx_remember_manager', '', $expire, $modx->config['base_url']);
endif;

if($modx->hasPermission('remove_locks')) $modx->manager->remove_locks();

$log = new logHandler;
$log->initAndWriteLog("Logged in", $modx->getLoginUserID(), $formv_username, '58', '-', 'MODX');

// invoke OnManagerLogin event
$info = array();
$info['userid']       = $dbv_internalKey;
$info['username']     = $formv_username;
$info['userpassword'] = $formv_password;
$info['rememberme']   = $formv_rememberme;
$modx->invokeEvent('OnManagerLogin', $info);

include_once(MODX_CORE_PATH . 'version.inc.php');
if(isset($settings_version) && !empty($settings_version) && $settings_version!=$modx_version)
{
	include_once(MODX_CORE_PATH . 'upgrades/upgrades.php');
}

// check if we should redirect user to a web page
if(isset($user_settings['manager_login_startup']) && $user_settings['manager_login_startup']>0) {
    $header = 'Location: '.$modx->makeUrl($id,'','','full');
    if($_POST['ajax']==1) echo $header;
    else header($header);
}
else
{
	if(isset($_SESSION['save_uri']) && !empty($_SESSION['save_uri']))
	{
		$uri = $_SESSION['save_uri'];
		unset($_SESSION['save_uri']);
	}
	else $uri = MODX_MANAGER_URL;
	
    $header = "Location: {$uri}";
    
    if($_POST['ajax']==1) echo $header;
    else header($header);
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

function failedLogin($dbv_internalKey,$dbv_failedlogincount)
{
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
