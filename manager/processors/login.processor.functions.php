<?php
if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    header('HTTP/1.0 404 Not Found');exit;
}

function checkSafedUri() {
    if(strpos(urldecode(evo()->server_var('REQUEST_URI')), "'") === false) {
        return;
    }
    jsAlert('This is illegal login.');
}

function jsAlert($msg){
    global $modx, $modx_manager_charset;
    header('Content-Type: text/html; charset='.$modx_manager_charset);
    if($modx->input_post('ajax')==1) {
        echo $msg;
    } else {
        echo sprintf(
            "<script>alert('%s');history.go(-1);</script>"
            , $modx->db->escape($msg)
        );
    }
}

function failedLogin() {
    //increment the failed login counter
    $failedlogincount = user('failedlogincount') + 1;
    db()->update(
        array('failedlogincount'=>$failedlogincount)
        , '[+prefix+]user_attributes'
        , sprintf("internalKey='%s'", user('internalKey'))
    );
    if(config('failed_login_attempts',0)<=$failedlogincount) {
        db()->update(
            array(
                'blockeduntil' => $_SERVER['REQUEST_TIME']+(config('blocked_minutes')*60)
            )
            , '[+prefix+]user_attributes'
            , sprintf("internalKey='%s'", user('internalKey'))
        );
    }
    @session_destroy();
    session_unset();
}

function loginPhpass($givenPassword,$dbasePassword) {
    global $modx;
    return $modx->phpass->CheckPassword($givenPassword, $dbasePassword);
}

function loginV1($givenPassword,$dbasePassword,$internalKey) {
    global $modx;

    $user_algo = $modx->manager->getV1UserHashAlgorithm($internalKey);

    if(!config('pwd_hash_algo'))
        $modx->config['pwd_hash_algo'] = 'UNCRYPT';

    if($user_algo !== $modx->config['pwd_hash_algo']) {
        $modx->config['pwd_hash_algo'] = $user_algo;
    }

    if($dbasePassword != $modx->manager->genV1Hash($givenPassword, $internalKey)) {
        return false;
    }

    updateNewHash($internalKey,$givenPassword);

    return true;
}

function loginMD5($givenPassword,$dbasePassword,$internalKey) {
    if($dbasePassword != md5($givenPassword)) {
        return false;
    }
    updateNewHash($internalKey,$givenPassword);
    return true;
}

function updateNewHash($internalKey,$password) {
    $rs = db()->update(
        array(
            'password' => db()->escape(
                evo()->phpass->HashPassword($password)
            )
        )
        , '[+prefix+]manager_users'
        , sprintf("internalKey='%s'", $internalKey)
    );
}

function user_config($key, $default=null) {
    global $modx;
    static $conf = null;
    if(isset($conf[$key])) {
        return $conf[$key];
    }
    $rs = $modx->db->select(
        'setting_name, setting_value'
        , '[+prefix+]user_settings'
        , sprintf(
            "user='%s' AND setting_value!=''"
            , user('internalKey')
        )
    );
    while ($row = $modx->db->getRow($rs)) {
        $conf[$row['setting_name']] = $row['setting_value'];
    }
    if(isset($conf[$key])) {
        return $conf[$key];
    }
    return $default;
}

function input($key,$default=null) {
    global $modx;
    static $input = array();

    if (isset($input[$key])) {
        return $input[$key];
    }

    $input['password']     = $modx->input_post('password');
    $input['captcha_code'] = $modx->input_post('captcha_code', '');
    $input['rememberme']   = $modx->input_post('rememberme', '');
    $input['username']     = $modx->input_post('username', $modx->input_get('username'));
    if(strpos($input['username'],':safemode')!==false) {
        $input['username'] = str_replace(':safemode', '', $input['username']);
        $input['safeMode'] = 1;
    } else {
        $input['safeMode'] = 0;
    }
    if(strpos($input['username'],':roleid=')!==false) {
        list($input['username'], $input['forceRole']) = explode(':roleid=', $input['username'],2);
        if(!preg_match('@^[0-9]+$@',$input['forceRole'])) {
            $input['forceRole'] = 1;
        }
    } else {
        $input['forceRole'] = false;
    }

    return $modx->array_get($input, $key, $default);
}

function user($key, $default=null) {
    global $modx;
    static $user = array();

    if (isset($user[$key])) {
        return $user[$key];
    }

    $user = $modx->getUserFromName(input('username'));
    if(!$user) {
        include_once(MODX_CORE_PATH . 'error.class.inc.php');
        $e = new errorHandler;
        jsAlert($e->errors[900]);
        exit;
    }

    if (($user['role'] == 1 && input('forceRole'))) {
        $user['role'] = input('forceRole');
    }
    if ($modx->array_get($user,'blockeduntil') && $modx->array_get($user,'blockeduntil') < time()) {
        $user['failedlogincount'] = '0';
        $user['blocked']          = '0';
    }

    if (isset($user[$key])) {
        return $user[$key];
    }
    return $modx->array_get($user, $key, $default);
}

function OnBeforeManagerLogin() {
    $info = array(
        'username'     => input('username'),
        'userpassword' => input('password'),
        'rememberme'   => input('rememberme')
    );
    evo()->invokeEvent('OnBeforeManagerLogin', $info);
}

function isBlockedUser() {
    if(!user('blocked')) {
        return false;
    }
    if (evo()->server_var('REQUEST_TIME') < user('blockeduntil',0)) {
        return true;
    }
    if(config('failed_login_attempts',0) < user('failedlogincount',0)) {
        if (evo()->server_var('REQUEST_TIME') < user('blockeduntil',0)) {
            return true;
        }
    }
    evo()->db->update(
        array(
            'failedlogincount' => 0,
            'blocked' => 0
        )
        , '[+prefix+]user_attributes'
        , sprintf("internalKey='%s'", user('internalKey'))
    );
    return false;
}

function checkAllowedIp() {
    if (!user_config('allowed_ip')) {
        return true;
    }

    $hostname = gethostbyaddr(evo()->server_var('REMOTE_ADDR'));
    if ($hostname !== false && $hostname != evo()->server_var('REMOTE_ADDR')) {
        if (gethostbyname($hostname) != evo()->server_var('REMOTE_ADDR')) {
            jsAlert("Your hostname doesn't point back to your IP!");
            return false;
        }
    }
    $allowed_ip = explode(
        ','
        , str_replace(' ', '', user_config('allowed_ip'))
    );
    if (in_array(evo()->server_var('REMOTE_ADDR'), $allowed_ip)) {
        return true;
    }

    jsAlert('You are not allowed to login from this location.');
    return false;
}

function OnManagerAuthentication() {
    $info = array(
        'userid'        => user('internalKey'),
        'username'      => user('username'),
        'userpassword'  => input('password'),
        'savedpassword' => user('password'),
        'rememberme'    => input('rememberme')
    );
    $rt = evo()->invokeEvent('OnManagerAuthentication', $info);
    if (!$rt || (is_array($rt) && !in_array(true,$rt))) {
        return false;
    }
    return true;
}

function OnManagerLogin() {
    $info = array(
        'userid'       => user('internalKey'),
        'username'     => user('username'),
        'userpassword' => input('password'),
        'rememberme'   => input('rememberme')
    );
    evo()->invokeEvent('OnManagerLogin', $info);
}

function checkCaptcha() {
    if(config('use_captcha') != 1) {
        return true;
    }

    if (!evo()->session_var('veriword')) {
        jsAlert('Captcha is not configured properly.');
        return false;
    }

    if (evo()->session_var('veriword') != input('captcha_code')) {
        jsAlert(alert()->errors[905]);
        failedLogin(user('internalKey'), user('failedlogincount'));
        return false;
    }
    return true;
}

function checkAllowedDays() {
    if (!user_config('allowed_days')) {
        return true;
    }

    $date = getdate();
    $day = $date['wday'] + 1;
    if (strpos(user_config('allowed_days'), (string)$day) !== false) {
        return true;
    }
    jsAlert("You are not allowed to login at this time. Please try again later.");
    return false;
}

function validPassword($inputPassword='',$savedPassword='') {
    evo()->loadExtension('phpass');
    switch(evo()->manager->getHashType($savedPassword)) {
        case 'phpass':
            return loginPhpass($inputPassword, $savedPassword);
        case 'md5':
            return loginMD5($inputPassword, $savedPassword, user('internalKey'));
        case 'v1':
            return loginV1($inputPassword, $savedPassword, user('internalKey'));
        default:
            return false;
    }
}

function redirectAfterLogin() {
// check if we should redirect user to a web page
    if(user_config('manager_login_startup',0)) {
        $header = 'Location: '.evo()->makeUrl(user_config('manager_login_startup',0));
        if(evo()->input_post('ajax')) {
            exit($header);
        }
        header($header);
        return;
    }

    if(evo()->session_var('save_uri')) {
        $uri = evo()->session_var('save_uri');
        unset($_SESSION['save_uri']);
    } else {
        $uri = MODX_MANAGER_URL;
    }
    $header = 'Location: ' . $uri;
    if(evo()->input_post('ajax')==1) {
        exit($header);
    }
    header($header);
}

function managerLogin() {
    global $modx;

    session_regenerate_id(true);
    
    $_SESSION['usertype'] = 'manager'; // user is a backend user

    // get permissions
    $_SESSION['mgrShortname']    = user('username');
    $_SESSION['mgrFullname']     = user('fullname');
    $_SESSION['mgrEmail']        = user('email');
    $_SESSION['mgrValidated']    = 1;
    $_SESSION['mgrInternalKey']  = user('internalKey');
    $_SESSION['mgrFailedlogins'] = user('failedlogincount');
    $_SESSION['mgrLogincount']   = user('logincount'); // login count
    $_SESSION['mgrRole']         = user('role');
    $rs = $modx->db->select(
        '*'
        , '[+prefix+]user_roles'
        , sprintf("id='%d'", user('role'))
    );
    $row = $modx->db->getRow($rs);

    $_SESSION['mgrPermissions'] = $row;

    if($modx->session_var('mgrPermissions.messages')==1) {
        $rs = $modx->db->select('*', '[+prefix+]manager_users');
        $total = $modx->db->getRecordCount($rs);
        if($total==1) {
            $_SESSION['mgrPermissions']['messages'] = '0';
        }
    }
    // successful login so reset fail count and update key values
    $modx->db->update(
        array(
            'failedlogincount'=>0,
            'logincount' => user('logincount')+1,
            'lastlogin' => user('thislogin'),
            'thislogin' => $modx->server_var('REQUEST_TIME'),
            'sessionid' => session_id()
        )
        , $modx->getFullTableName('user_attributes')
        , 'internalKey=' . user('internalKey')
    );

    $_SESSION['mgrLastlogin'] = $modx->server_var('REQUEST_TIME');
    $_SESSION['mgrDocgroups'] = $modx->manager->getMgrDocgroups(user('internalKey'));

    if($modx->input_any('rememberme')) {
        $_SESSION['modx.mgr.session.cookie.lifetime'] = (int)$modx->conf_var('session.cookie.lifetime',0);
        global $https_port;
        setcookie(
            'modx_remember_manager'
            , user('username')
            , $modx->server_var('REQUEST_TIME') + strtotime('+1 year')
            , MODX_BASE_URL
            , NULL
            , ($modx->server_var('HTTPS') || $modx->server_var('SERVER_PORT') == $https_port) ? true : false
            , true
        );
    } else {
        $_SESSION['modx.mgr.session.cookie.lifetime']= 0;
        setcookie (
            'modx_remember_manager'
            , ''
            , ($modx->server_var('REQUEST_TIME') - 3600)
            , MODX_BASE_URL
        );
    }

    if($modx->hasPermission('remove_locks')) {
        $modx->manager->remove_locks();
    }

    include_once(MODX_CORE_PATH . 'log.class.inc.php');
    $log = new logHandler;
    $log->initAndWriteLog(
        'Logged in'
        , $modx->getLoginUserID()
        , user('username')
        , '58'
        , '-'
        , 'MODX'
    );
}