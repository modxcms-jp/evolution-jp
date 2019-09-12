<?php
if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
	header('HTTP/1.0 404 Not Found');exit;
}
global $_style;

include_once(__DIR__ . '/login.processor.functions.php');

$self = 'manager/processors/login.processor.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE',true);
include_once($base_path.'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

include_once(MODX_CORE_PATH . 'log.class.inc.php');

$decoded_uri = urldecode($_SERVER['REQUEST_URI']);
if(strpos($decoded_uri,"'")!==false) {
	jsAlert('This is illegal login.');
	return;
}

// Initialize System Alert Message Queque
if (!isset($_SESSION['SystemAlertMsgQueque'])) {
    $_SESSION['SystemAlertMsgQueque'] = array();
}
$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

$modx->config['login_by'] = 'username,email';

$formv_password     = $modx->db->escape($_POST['password']);
$formv_captcha_code = $modx->input_post('captcha_code', '');
$formv_rememberme   = $modx->input_post('rememberme', '');
$formv_username = $modx->input_post('username', $modx->input_get('username'));
if(strpos($formv_username,':safemode')!==false) {
	$_SESSION['safeMode'] = 1;
	$formv_username = str_replace(':safemode','',$formv_username);
} else $_SESSION['safeMode'] = 0;

if(strpos($formv_username,':roleid=')!==false) {
	list($formv_username,$forceRole) = explode(':roleid=',$formv_username,2);
	if(!preg_match('@^[0-9]+$@',$forceRole)) $forceRole = 1;
} else {
	$forceRole = false;
}

// invoke OnBeforeManagerLogin event
$info = array();
$info['username']     = $formv_username;
$info['userpassword'] = $formv_password;
$info['rememberme']   = $formv_rememberme;
$modx->invokeEvent('OnBeforeManagerLogin', $info);

if(isset($modx->config['manager_language'])) {
    $manager_language = $modx->config['manager_language'];
} else {
    $manager_language = 'english';
}

$_lang = array();
include_once(MODX_CORE_PATH . "lang/{$manager_language}.inc.php");

// include_once the error handler
include_once(MODX_CORE_PATH . 'error.class.inc.php');
$e = new errorHandler;
$row = $modx->getUserFromName($formv_username);
if(!$row) {
    jsAlert($e->errors[900]);
    return;
}

$dbv_internalKey      = $row['internalKey'];
$dbv_username         = $row['username'];
$dbv_password         = $row['password'];
$dbv_failedlogincount = $row['failedlogincount'];
$dbv_blocked          = $row['blocked'];
$dbv_blockeduntil     = $row['blockeduntil'];
$dbv_role             = ($row['role']==1 && $forceRole) ? $forceRole : $row['role'];
$dbv_logincount       = $row['logincount'];
$dbv_fullname         = $row['fullname'];
$dbv_email            = $row['email'];

// blocked due to number of login errors.
if($_SERVER['REQUEST_TIME']<$dbv_blockeduntil) {
	if($modx->config['failed_login_attempts']<=$dbv_failedlogincount) {
		$modx->db->update(
		    'blocked=1'
            , '[+prefix+]user_attributes'
            , sprintf("internalKey='%s'", $dbv_internalKey)
        );
		@session_destroy();
		session_unset();
		jsAlert($e->errors[902]);
		return;
	}
} elseif($dbv_blocked==1) {
    $rs = $modx->db->update(
        array(
            'failedlogincount' => 0,
            'blocked'          => 0,
            'blockedafter'     => 0,
            'blockeduntil'     => 0
        )
        , '[+prefix+]user_attributes'
        , sprintf("internalKey='%s'", $dbv_internalKey)
    );
    $dbv_failedlogincount  = '0';
    $dbv_blocked           = '0';
}

// get the user settings from the database
$rs = $modx->db->select(
    'setting_name, setting_value'
    , '[+prefix+]user_settings'
    , sprintf(
        "user='%s' AND setting_value!=''"
        , $dbv_internalKey
    )
);
while ($row = $modx->db->getRow($rs)) {
    $user_settings[$row['setting_name']] = $row['setting_value'];
}

// allowed ip
if ($user_settings['allowed_ip']) {
	$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	if($hostname!==false && $hostname != $_SERVER['REMOTE_ADDR']) {
		if(gethostbyname($hostname) != $_SERVER['REMOTE_ADDR']) {
			jsAlert("Your hostname doesn't point back to your IP!");
			return;
		}
	}
	$user_settings['allowed_ip'] = str_replace(' ','',$user_settings['allowed_ip']);
	if(!in_array($_SERVER['REMOTE_ADDR'], explode(',',$user_settings['allowed_ip']))) {
		jsAlert('You are not allowed to login from this location.');
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
$modx->loadExtension('phpass');
// invoke OnManagerAuthentication event
$info = array();
$info['userid']        = $dbv_internalKey;
$info['username']      = $dbv_username;
$info['userpassword']  = $formv_password;
$info['savedpassword'] = $dbv_password;
$info['rememberme']    = $formv_rememberme;

$rt = $modx->invokeEvent('OnManagerAuthentication', $info);

// check if plugin authenticated the user
if (!isset($rt) || !$rt || (is_array($rt) && !in_array(TRUE,$rt))) {
	// check user password - local authentication
	$hashType = $modx->manager->getHashType($dbv_password);
	if($hashType === 'phpass') {
        $matchPassword = login($dbv_username, $formv_password, $dbv_password);
    } elseif($hashType === 'md5') {
        $matchPassword = loginMD5($dbv_internalKey, $formv_password, $dbv_password, $dbv_username);
    } elseif($hashType === 'v1') {
        $matchPassword = loginV1($dbv_internalKey, $formv_password, $dbv_password, $dbv_username);
    } else {
        $matchPassword = false;
    }
	
	if(!$matchPassword) {
		jsAlert($e->errors[901]);
		failedLogin($dbv_internalKey,$dbv_failedlogincount);
		return;
	}

	if($modx->config['use_captcha']==1) {
		if (!isset ($_SESSION['veriword'])) {
			jsAlert('Captcha is not configured properly.');
			return;
		}

		if ($_SESSION['veriword'] != $formv_captcha_code) {
			jsAlert($e->errors[905]);
			failedLogin($dbv_internalKey,$dbv_failedlogincount);
			return;
		}
	}
}

session_regenerate_id(true);

$_SESSION['usertype'] = 'manager'; // user is a backend user

// get permissions
$_SESSION['mgrShortname']    = $dbv_username;
$_SESSION['mgrFullname']     = $dbv_fullname;
$_SESSION['mgrEmail']        = $dbv_email;
$_SESSION['mgrValidated']    = 1;
$_SESSION['mgrInternalKey']  = $dbv_internalKey;
$_SESSION['mgrFailedlogins'] = $dbv_failedlogincount;
$_SESSION['mgrLogincount']   = $dbv_logincount; // login count
$_SESSION['mgrRole']         = $dbv_role;
$rs = $modx->db->select('* ','[+prefix+]user_roles', sprintf("id='%d'", $dbv_role));
$row = $modx->db->getRow($rs);

$_SESSION['mgrPermissions'] = $row;

if($_SESSION['mgrPermissions']['messages']==1) {
	$rs = $modx->db->select('*', '[+prefix+]manager_users');
	$total = $modx->db->getRecordCount($rs);
	if($total==1) {
        $_SESSION['mgrPermissions']['messages'] = '0';
    }
}
// successful login so reset fail count and update key values
if(isset($_SESSION['mgrValidated'])) {
	$now = $_SERVER['REQUEST_TIME'];
	$currentsessionid = session_id();
	$field = sprintf(
	    "failedlogincount=0, logincount=logincount+1, lastlogin=thislogin, thislogin='%s', sessionid='%s'"
        , $now
        , $currentsessionid
    );
    $sql = "update [+prefix+]user_attributes SET " . $field . " where internalKey='" . $dbv_internalKey . "'";
    $rs = $modx->db->query($sql);
	$_SESSION['mgrLastlogin'] = $now;
}

$_SESSION['mgrDocgroups'] = $modx->manager->getMgrDocgroups($dbv_internalKey);

if($formv_rememberme == '1') {
    $_SESSION['modx.mgr.session.cookie.lifetime'] = (int)$modx->config['session.cookie.lifetime'];

    // Set a cookie separate from the session cookie with the username in it.
    // Are we using secure connection? If so, make sure the cookie is secure
    global $https_port;
    $expire = $_SERVER['REQUEST_TIME'] + 60 * 60 * 24 * 365;
    $path = $modx->config['base_url'];
    $secure = (isset($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == $https_port) ? true : false;
    setcookie('modx_remember_manager', $dbv_username, $expire, $path, NULL, $secure, true);
} else {
    $_SESSION['modx.mgr.session.cookie.lifetime']= 0;
	
	// Remove the Remember Me cookie
    $expire = $_SERVER['REQUEST_TIME'] - 3600;
	setcookie ('modx_remember_manager', '', $expire, $modx->config['base_url']);
}

if($modx->hasPermission('remove_locks')) $modx->manager->remove_locks();

$log = new logHandler;
$log->initAndWriteLog("Logged in", $modx->getLoginUserID(), $dbv_username, '58', '-', 'MODX');

// invoke OnManagerLogin event
$info = array();
$info['userid']       = $dbv_internalKey;
$info['username']     = $dbv_username;
$info['userpassword'] = $formv_password;
$info['rememberme']   = $formv_rememberme;
$modx->invokeEvent('OnManagerLogin', $info);

// check if we should redirect user to a web page
if(isset($user_settings['manager_login_startup']) && $user_settings['manager_login_startup']>0) {
    $header = 'Location: '.$modx->makeUrl($id,'','','full');
    if($_POST['ajax']==1) {
        echo $header;
    } else {
        header($header);
    }
} else {
	if(isset($_SESSION['save_uri']) && !empty($_SESSION['save_uri'])) {
		$uri = $_SESSION['save_uri'];
		unset($_SESSION['save_uri']);
	} else {
        $uri = MODX_MANAGER_URL;
    }
    $header = "Location: {$uri}";
    
    if($_POST['ajax']==1) {
        echo $header;
    } else {
        header($header);
    }
}
