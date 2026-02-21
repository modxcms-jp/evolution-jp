<?php
if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

function checkSafedUri()
{
    if (strpos(urldecode(request_uri()), "'") === false) {
        return;
    }
    jsAlert('This is illegal login.');
}

function jsAlert($msg)
{
    header('Content-Type: text/html; charset=UTF-8');
    if (postv('ajax') == 1) {
        echo $msg;
    } else {
        echo sprintf(
            "<script>alert('%s');location.href='%s';</script>",
            hsc($msg),
            MODX_MANAGER_URL
        );
    }
}

function validateLoginInput()
{
    $username = postv('username', getv('username'));
    $password = postv('password');

    if (!$username || trim((string)$username) === '') {
        jsAlert('ユーザー名とパスワードを入力してください');
        return false;
    }

    if (!$password) {
        jsAlert('ユーザー名とパスワードを入力してください');
        return false;
    }

    return true;
}

function failedLogin()
{
    //increment the failed login counter
    $failedlogincount = user('failedlogincount') + 1;
    db()->update(
        ['failedlogincount' => $failedlogincount],
        '[+prefix+]user_attributes',
        sprintf("internalKey='%s'", user('internalKey'))
    );
    if (config('failed_login_attempts', 0) <= $failedlogincount) {
        db()->update([
                'blockeduntil' => request_time() + (config('blocked_minutes') * 60)
            ],
            '[+prefix+]user_attributes',
            sprintf("internalKey='%s'", user('internalKey'))
        );
    }
    @session_destroy();
    session_unset();
}

function loginPhpass($givenPassword, $dbasePassword)
{
    return evo()->phpass->CheckPassword($givenPassword, $dbasePassword);
}

function loginV1($givenPassword, $dbasePassword, $internalKey)
{
    global $modx;

    $user_algo = manager()->getV1UserHashAlgorithm($internalKey);

    if (!config('pwd_hash_algo')) {
        $modx->config['pwd_hash_algo'] = 'UNCRYPT';
    }

    if ($user_algo !== $modx->config['pwd_hash_algo']) {
        $modx->config['pwd_hash_algo'] = $user_algo;
    }

    if ($dbasePassword != manager()->genV1Hash($givenPassword, $internalKey)) {
        return false;
    }

    updateNewHash($internalKey, $givenPassword);

    return true;
}

function loginMD5($givenPassword, $dbasePassword, $internalKey)
{
    if ($dbasePassword != md5($givenPassword)) {
        return false;
    }
    updateNewHash($internalKey, $givenPassword);
    return true;
}

function updateNewHash($internalKey, $password)
{
    db()->update([
            'password' => db()->escape(
                evo()->phpass->HashPassword($password)
            )
        ],
        '[+prefix+]manager_users',
        sprintf("id='%s'", $internalKey)
    );
}

function user_config($key, $default = null)
{
    static $conf = null;
    if (isset($conf[$key])) {
        return $conf[$key];
    }
    $rs = db()->select(
        'setting_name, setting_value',
        '[+prefix+]user_settings',
        sprintf(
            "user='%s' AND setting_value!=''",
            user('internalKey')
        )
    );
    while ($row = db()->getRow($rs)) {
        $conf[$row['setting_name']] = $row['setting_value'];
    }
    if (isset($conf[$key])) {
        return $conf[$key];
    }
    return $default;
}

function input($key, $default = null)
{
    static $input = [];

    if (isset($input[$key])) {
        return $input[$key];
    }

    $input = [
        'username'     => postv('username', getv('username')),
        'password'     => postv('password'),
        'captcha_code' => postv('captcha_code', ''),
        'rememberme'   => postv('rememberme', '')
    ];
    if (strpos($input['username'], ':safemode') !== false) {
        $input['username'] = str_replace(':safemode', '', $input['username']);
        $input['safeMode'] = 1;
    } else {
        $input['safeMode'] = 0;
    }
    if (strpos($input['username'], ':roleid=') !== false) {
        [$input['username'], $input['forceRole']] = explode(':roleid=', $input['username'], 2);
        if (!preg_match('@^[0-9]+$@', $input['forceRole'])) {
            $input['forceRole'] = 1;
        }
    } else {
        $input['forceRole'] = false;
    }
    return array_get($input, $key, $default);
}

function user($key, $default = null)
{
    static $user = [];

    if (isset($user[$key])) {
        return $user[$key];
    }

    $user = evo()->getUserFromName(input('username'));
    if (!$user) {
        include_once(MODX_CORE_PATH . 'error.class.inc.php');
        $e = new errorHandler;
        jsAlert($e->errors[900]);
        exit;
    }

    if (($user['role'] == 1 && input('forceRole'))) {
        $user['role'] = input('forceRole');
    }
    if (array_get($user, 'blockeduntil') && array_get($user, 'blockeduntil') < time()) {
        $user['failedlogincount'] = '0';
        $user['blocked'] = '0';
    }

    if (isset($user[$key])) {
        return $user[$key];
    }
    return array_get($user, $key, $default);
}

function OnBeforeManagerLogin()
{
    $info = [
        'username'     => input('username'),
        'userpassword' => input('password'),
        'rememberme'   => input('rememberme')
    ];
    evo()->invokeEvent('OnBeforeManagerLogin', $info);
}

function isBlockedUser()
{
    if (!user('blocked')) {
        return false;
    }
    if (request_time() < user('blockeduntil', 0)) {
        return true;
    }
    if (config('failed_login_attempts', 0) < user('failedlogincount', 0)) {
        if (request_time() < user('blockeduntil', 0)) {
            return true;
        }
    }
    db()->update(
        [
            'failedlogincount' => 0,
            'blocked' => 0
        ],
        '[+prefix+]user_attributes',
        sprintf("internalKey='%s'", user('internalKey'))
    );
    return false;
}

function checkAllowedIp()
{
    if (!user_config('allowed_ip')) {
        return true;
    }

    $hostname = gethostbyaddr(serverv('REMOTE_ADDR'));
    if ($hostname !== false && $hostname != serverv('REMOTE_ADDR')) {
        if (gethostbyname($hostname) != serverv('REMOTE_ADDR')) {
            jsAlert("Your hostname doesn't point back to your IP!");
            return false;
        }
    }
    $allowed_ip = explode(
        ',',
        str_replace(' ', '', user_config('allowed_ip'))
    );
    if (in_array(serverv('REMOTE_ADDR'), $allowed_ip)) {
        return true;
    }

    jsAlert('You are not allowed to login from this location.');
    return false;
}

function OnManagerAuthentication()
{
    $info = [
        'userid'        => user('internalKey'),
        'username'      => user('username'),
        'userpassword'  => input('password'),
        'savedpassword' => user('password'),
        'rememberme'    => input('rememberme')
    ];
    $rt = evo()->invokeEvent('OnManagerAuthentication', $info);
    if (!$rt || (is_array($rt) && !in_array(true, $rt))) {
        return false;
    }
    return true;
}

function OnManagerLogin()
{
    $info = [
        'userid'       => user('internalKey'),
        'username'     => user('username'),
        'userpassword' => input('password'),
        'rememberme'   => input('rememberme')
    ];
    evo()->invokeEvent('OnManagerLogin', $info);
}

function checkCaptcha()
{
    if (config('use_captcha') != 1) {
        return true;
    }

    if (!sessionv('veriword')) {
        jsAlert('Captcha is not configured properly.');
        return false;
    }

    if (sessionv('veriword') != input('captcha_code')) {
        jsAlert(alert()->errors[905]);
        failedLogin(user('internalKey'), user('failedlogincount'));
        return false;
    }
    return true;
}

function checkAllowedDays()
{
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

function validPassword($inputPassword = '', $savedPassword = '')
{
    evo()->loadExtension('phpass');
    switch (evo()->manager->getHashType($savedPassword)) {
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

function redirectAfterLogin()
{
    if (user_config('manager_login_startup')) {
        $header = 'Location: ' . evo()->makeUrl(user_config('manager_login_startup'));
        if (evo()->input_post('ajax')) {
            exit($header);
        }
        header($header);
        return;
    }

    if (sessionv('save_uri')) {
        $uri = sessionv('save_uri');
        unset($_SESSION['save_uri']);
    } else {
        $uri = MODX_MANAGER_URL;
    }
    $header = 'Location: ' . $uri;
    if (evo()->input_post('ajax') == 1) {
        exit($header);
    }
    header($header);
}

function managerLogin()
{
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
    $_SESSION['mgrLogincount']   = user('logincount');        // login count
    $_SESSION['mgrRole']         = user('role');

    $rs = db()->select(
        '*',
        '[+prefix+]user_roles',
        sprintf("id='%d'", user('role'))
    );
    $row = db()->getRow($rs);

    $_SESSION['mgrPermissions'] = $row;

    if (sessionv('mgrPermissions.messages') == 1) {
        $rs = db()->select('*', '[+prefix+]manager_users');
        $total = db()->count($rs);
        if ($total == 1) {
            $_SESSION['mgrPermissions']['messages'] = '0';
        }
    }
    // successful login so reset fail count and update key values
    db()->update(
        [
            'failedlogincount' => 0,
            'logincount' => user('logincount') + 1,
            'lastlogin' => user('thislogin'),
            'thislogin' => request_time(),
            'sessionid' => session_id()
        ],
        evo()->getFullTableName('user_attributes'),
        'internalKey=' . user('internalKey')
    );

    $_SESSION['mgrLastlogin'] = request_time();
    $_SESSION['mgrDocgroups'] = manager()->getMgrDocgroups(user('internalKey'));

    if (anyv('rememberme')) {
        $_SESSION['modx.mgr.session.cookie.lifetime'] = (int)$modx->config('session.cookie.lifetime', 0);
        setcookie(
            'modx_remember_manager',
            user('username'),
            [
                'expires'  => strtotime('+1 month'),
                'path'     => MODX_BASE_URL,
                'domain' => '',
                'secure'   => init::is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    } else {
        $_SESSION['modx.mgr.session.cookie.lifetime'] = 0;
        setcookie(
            'modx_remember_manager',
            '',
            [
                'expires'  => (request_time() - 3600),
                'path'     => MODX_BASE_URL,
                'domain'   => '',
                'secure'   => init::is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    if (evo()->hasPermission('remove_locks')) {
        manager()->remove_locks();
    }

    include_once(MODX_CORE_PATH . 'log.class.inc.php');
    $log = new logHandler;
    $log->initAndWriteLog(
        'Logged in',
        evo()->getLoginUserID(),
        user('username'),
        '58',
        '-',
        'MODX'
    );
}
