<?php
$self = 'manager/processors/login.processor.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE',true);
include_once("{$base_path}index.php");
$modx->db->connect();

include_once(MODX_CORE_PATH . 'settings.inc.php');
include_once(MODX_CORE_PATH . 'version.inc.php');
include_once(MODX_CORE_PATH . 'log.class.inc.php');

// Initialize System Alert Message Queque
if (!isset($_SESSION['SystemAlertMsgQueque'])) $_SESSION['SystemAlertMsgQueque'] = array();
$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

// initiate the content manager class
$modx->loadExtension('ManagerAPI');
$modx->getSettings();

$username       = $modx->db->escape($_REQUEST['username']);
$givenPassword  = $modx->db->escape($_REQUEST['password']);
$captcha_code   = (isset($_REQUEST['captcha_code'])) ? $_REQUEST['captcha_code'] : '';
$rememberme     = (isset($_REQUEST['rememberme']))   ? $_REQUEST['rememberme']   : '';
$failed_allowed = $modx->config['failed_login_attempts'];

if(strpos($username,':safemode')!==false)
{
	$_SESSION['safeMode'] = 1;
	$username = str_replace(':safemode','',$username);
}
if(strpos($username,':roleid=')!==false)
{
	list($username,$assignRole) = explode(':roleid=',$username);
	if(!preg_match('@^[0-9]+$@',$assignRole)) $assignRole = 1;
}

$tbl_user_attributes = $modx->getFullTableName('user_attributes');

// invoke OnBeforeManagerLogin event
$modx->invokeEvent("OnBeforeManagerLogin",
                        array(
                            "username"      => $username,
                            "userpassword"  => $givenPassword,
                            "rememberme"    => $rememberme
                        ));

if(!isset($modx->config['manager_language'])) $modx->config['manager_language'] = 'english';
$_lang = array();
include_once(MODX_CORE_PATH . 'lang/'.$modx->config['manager_language'].'.inc.php');

// include_once the error handler
include_once(MODX_CORE_PATH . 'error.class.inc.php');
$e = new errorHandler;

$field = "mu.*, ua.*";
$from = "[+prefix+]manager_users mu,[+prefix+]user_attributes ua";
$where = "BINARY mu.username='{$username}' and ua.internalKey=mu.id";
$rs = $modx->db->select($field,$from,$where);

$total = $modx->db->getRecordCount($rs);
if($total!=1) {
    jsAlert($e->errors[900]);
    return;
}

$row = $modx->db->getRow($rs);

$internalKey            = $row['internalKey'];
$dbasePassword          = $row['password'];
$failedlogins           = $row['failedlogincount'];
$blocked                = $row['blocked'];
$blockeduntildate       = $row['blockeduntil'];
$blockedafterdate       = $row['blockedafter'];
$registeredsessionid    = $row['sessionid'];
$role                   = ($row['role']==1 && isset($assignRole)) ? $assignRole : $row['role'];
$lastlogin              = $row['lastlogin'];
$nrlogins               = $row['logincount'];
$fullname               = $row['fullname'];
$email                  = $row['email'];

// get the user settings from the database
$rs = $modx->db->select('setting_name, setting_value','[+prefix+]user_settings',"user='{$internalKey}' AND setting_value!=''");
while ($row = $modx->db->getRow($rs))
{
    ${$row['setting_name']} = $row['setting_value'];
}
// blocked due to number of login errors.
if($failedlogins>=$failed_allowed && $blockeduntildate>time()) {
    $modx->db->update('blocked=1','[+prefix+]user_attributes',"internalKey='{$internalKey}'");
    @session_destroy();
    session_unset();
    jsAlert($e->errors[902]);
    return;
}

// blocked due to number of login errors, but get to try again
if($failedlogins>=$failed_allowed && $blockeduntildate<time()) {
    $sql = "UPDATE {$tbl_user_attributes} SET failedlogincount='0', blockeduntil='".(time()-1)."' where internalKey='{$internalKey}'";
    $rs = $modx->db->query($sql);
}

// this user has been blocked by an admin, so no way he's loggin in!
if($blocked=="1") {
    @session_destroy();
    session_unset();
    jsAlert($e->errors[903]);
    return;
}

// blockuntil: this user has a block until date
if($blockeduntildate>time()) {
    @session_destroy();
    session_unset();
    jsAlert("You are blocked and cannot log in! Please try again later.");
    return;
}

// blockafter: this user has a block after date
if($blockedafterdate>0 && $blockedafterdate<time()) {
    @session_destroy();
    session_unset();
    jsAlert("You are blocked and cannot log in! Please try again later.");
    return;
}

// allowed ip
if ($allowed_ip) {
        if(($hostname = gethostbyaddr($_SERVER['REMOTE_ADDR'])) && ($hostname != $_SERVER['REMOTE_ADDR'])) {
          if(gethostbyname($hostname) != $_SERVER['REMOTE_ADDR']) {
            jsAlert("Your hostname doesn't point back to your IP!");
            return;
          }
        }
        if(!in_array($_SERVER['REMOTE_ADDR'], explode(',',str_replace(' ','',$allowed_ip)))) {
          jsAlert("You are not allowed to login from this location.");
          return;
        }
}

// allowed days
if ($allowed_days) {
    $date = getdate();
    $day = $date['wday']+1;
    if (strpos($allowed_days,"$day")===false) {
        jsAlert("You are not allowed to login at this time. Please try again later.");
        return;
    }
}

// invoke OnManagerAuthentication event
$rt = $modx->invokeEvent("OnManagerAuthentication",
                        array(
                            "userid"        => $internalKey,
                            "username"      => $username,
                            "userpassword"  => $givenPassword,
                            "savedpassword" => $dbasePassword,
                            "rememberme"    => $rememberme
                        ));

$decoded_uri = urldecode($_SERVER['REQUEST_URI']);
if(strpos($decoded_uri,"'")!==false) {
	jsAlert("This is illegal login.");
	return;
}

// check if plugin authenticated the user
if (!isset($rt)||!$rt||(is_array($rt) && !in_array(TRUE,$rt)))
{
	// check user password - local authentication
	if(strpos($dbasePassword,'>')!==false):
		if(!isset($modx->config['pwd_hash_algo']) || empty($modx->config['pwd_hash_algo']))
			$modx->config['pwd_hash_algo'] = 'UNCRYPT';
		$user_algo = $modx->manager->getUserHashAlgorithm($internalKey);
		
		if($user_algo !== $modx->config['pwd_hash_algo']):
			$bk_pwd_hash_algo = $modx->config['pwd_hash_algo'];
			$modx->config['pwd_hash_algo'] = $user_algo;
		endif;
		
		if($dbasePassword != $modx->manager->genHash($givenPassword, $internalKey)):
			jsAlert($e->errors[901]);
			$newloginerror = 1;
		elseif(isset($bk_pwd_hash_algo)):
			$modx->config['pwd_hash_algo'] = $bk_pwd_hash_algo;
			$field = array();
			$field['password'] = $modx->manager->genHash($givenPassword, $internalKey);
			$modx->db->update($field, '[+prefix+]manager_users', "username='{$username}'");
		endif;
		
	else:
		if($dbasePassword != md5($givenPassword)):
			jsAlert($e->errors[901]);
			$newloginerror = 1;
		else:
			$field = array();
			$field['password'] = $modx->manager->genHash($givenPassword, $internalKey);
			$modx->db->update($field, '[+prefix+]manager_users', "username='{$username}'");
		endif;
	endif;
}

if($use_captcha==1) {
	if (!isset ($_SESSION['veriword'])) {
		jsAlert('Captcha is not configured properly.');
		return;
	}
	elseif ($_SESSION['veriword'] != $captcha_code) {
        jsAlert($e->errors[905]);
        $newloginerror = 1;
    }
}

if($newloginerror) {
	//increment the failed login counter
    $failedlogins += 1;
    $rs = $modx->db->update(array('failedlogincount'=>$failedlogins), '[+prefix+]user_attributes', "internalKey='{$internalKey}'");
    if($failedlogins>=$failed_allowed) {
		//block user for too many fail attempts
		$blockeduntil = time()+($blocked_minutes*60);
        $rs = $modx->db->update(array('blockeduntil'=>$blockeduntil), '[+prefix+]user_attributes', "internalKey='{$internalKey}'");
    } else {
		//sleep to help prevent brute force attacks
        $sleep = (int)$failedlogins/2;
        if($sleep>5) $sleep = 5;
        sleep($sleep);
    }
	@session_destroy();
	session_unset();
    return;
}

$_SESSION['usertype'] = 'manager'; // user is a backend user

// get permissions
$_SESSION['mgrShortname'] = $username;
$_SESSION['mgrFullname'] = $fullname;
$_SESSION['mgrEmail'] = $email;
$_SESSION['mgrValidated'] = 1;
$_SESSION['mgrInternalKey'] = $internalKey;
$_SESSION['mgrFailedlogins'] = $failedlogins;
$_SESSION['mgrLogincount'] = $nrlogins; // login count
$_SESSION['mgrRole'] = $role;
$rs = $modx->db->select('* ','[+prefix+]user_roles',"id='{$role}'");
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
	$now = time();
	$currentsessionid = session_id();
	$field = "failedlogincount=0, logincount=logincount+1, lastlogin=thislogin, thislogin={$now}, sessionid='{$currentsessionid}'";
    $sql = "update {$tbl_user_attributes} SET {$field} where internalKey={$internalKey}";
    $rs = $modx->db->query($sql);
	$_SESSION['mgrLastlogin'] = $now;
}

$_SESSION['mgrDocgroups'] = $modx->manager->getMgrDocgroups($internalKey);

if($rememberme == '1'):
    $_SESSION['modx.mgr.session.cookie.lifetime']= intval($modx->config['session.cookie.lifetime']);
	
	// Set a cookie separate from the session cookie with the username in it.
	// Are we using secure connection? If so, make sure the cookie is secure
	global $https_port;
	
	$secure = (  (isset ($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $_SERVER['SERVER_PORT'] == $https_port);
	if ( version_compare(PHP_VERSION, '5.2', '<') ) {
		setcookie('modx_remember_manager', $_SESSION['mgrShortname'], time()+60*60*24*365, $modx->config['base_url'], '; HttpOnly' , $secure );
	} else {
		setcookie('modx_remember_manager', $_SESSION['mgrShortname'], time()+60*60*24*365, $modx->config['base_url'], NULL, $secure, true);
	}
else:
    $_SESSION['modx.mgr.session.cookie.lifetime']= 0;
	
	// Remove the Remember Me cookie
	setcookie ('modx_remember_manager', "", time() - 3600, $modx->config['base_url']);
endif;

if($modx->hasPermission('remove_locks'))
	$modx->manager->remove_locks();

$log = new logHandler;
$log->initAndWriteLog("Logged in", $modx->getLoginUserID(), $_SESSION['mgrShortname'], "58", "-", "MODX");

// invoke OnManagerLogin event
$modx->invokeEvent("OnManagerLogin",
                        array(
                            "userid"        => $internalKey,
                            "username"      => $username,
                            "userpassword"  => $givenPassword,
                            "rememberme"    => $rememberme
                        ));

if(isset($settings_version) && !empty($settings_version) && $settings_version!=$modx_version)
{
	include_once(MODX_CORE_PATH . 'upgrades.php');
}

// check if we should redirect user to a web page
$id = $modx->db->getValue($modx->db->select('setting_value','[+prefix+]user_settings',"user='{$internalKey}' AND setting_name='manager_login_startup'"));
if(isset($id) && $id>0) {
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
	
    $header = 'Location: ' . $uri;
    
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
