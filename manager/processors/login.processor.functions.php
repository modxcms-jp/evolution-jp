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
    if (post_boolvar('ajax')) {
        echo $msg;
    } else {
        echo sprintf(
            "<script>alert('%s');location.href='%s';</script>",
            hsc($msg),
            MODX_MANAGER_URL
        );
    }
}

// 送られたユーザー名。POST を優先し、無ければ GET。文字列でなければ空（配列は文字列関数に渡せないため）
function loginUsername()
{
    // POST に username があれば、文字列でなくても GET には替えない（配列なら '' にする）
    return postv('username') === null ? get_textvar('username', '') : post_textvar('username', '');
}

// 送られたパスワード。加工しない。無い・文字列でないときは null
function loginPassword()
{
    return post_textvar('password');
}

function validateLoginInput()
{
    $username = loginUsername();
    $password = loginPassword();

    if (!$username || trim((string)$username) === '') {
        jsAlert('ユーザー名とパスワードを入力してください');
        return false;
    }

    // パスワード再設定メールのリンク（fmpkey付きGET）はパスワードなしで来る。認証はOnManagerAuthenticationに任せる
    if (!$password && !getv('fmpkey')) {
        jsAlert('ユーザー名とパスワードを入力してください');
        return false;
    }

    return true;
}

function failedLogin()
{
    //increment the failed login counter
    $failedlogincount = user('failedlogincount') + 1;
    $fields = ['failedlogincount' => $failedlogincount];

    // 一時的なロックは blockeduntil だけで表す（blocked は管理者によるブロック。Web ユーザーと同じ）
    if (config('failed_login_attempts', 0) <= $failedlogincount) {
        $fields['blockeduntil'] = request_time() + (config('blocked_minutes') * 60);
    }

    db()->update(
        $fields,
        '[+prefix+]user_attributes',
        sprintf("internalKey='%s'", db()->escape(user('internalKey')))
    );

    @session_destroy();
    session_unset();
}

function loginPhpass($givenPassword, $dbasePassword)
{
    return evo()->phpass->CheckPassword($givenPassword, $dbasePassword);
}

function loginV1($givenPassword, $dbasePassword, $internalKey)
{
    $user_algo = manager()->getV1UserHashAlgorithm($internalKey);

    if (!config('pwd_hash_algo')) {
        evo()->config['pwd_hash_algo'] = 'UNCRYPT';
    }

    if ($user_algo !== evo()->config['pwd_hash_algo']) {
        evo()->config['pwd_hash_algo'] = $user_algo;
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
        'username'     => loginUsername(),
        'password'     => loginPassword(),
        'captcha_code' => post_strvar('captcha_code'),
        'rememberme'   => post_strvar('rememberme')
    ];
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
    // 期限の過ぎた一時ロックの後は、失敗回数を数え直す（ブロック中かどうかは isBlockedUser() が DB の値で決める）
    if (isExpiredTemporaryLock($user)) {
        $user['failedlogincount'] = '0';
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

function rejectBlockedUser()
{
    @session_destroy();
    session_unset();
    jsAlert(alert()->errors[902]);
}

// ブロック中かを、画面と同じ isUserBlocked() で決める。
// 期限の過ぎた一時ロックは、ここで外してから判定する（失敗回数による一時ロックの動きは変えない）
// 外すのは失敗回数と blockeduntil だけ。blocked は管理者が付けたブロックなので触らない
// $legacyLock が真（以前の版の一時ロックで、マイグレーションの前）なら、ここでは止めない。認証の後で releaseLegacyLoginLock() が外す
function isBlockedUser($legacyLock = false)
{
    $row = userBlockStateInDb();
    if (!$row) {
        // 属性の行が無いユーザーは、ブロックの記録も無い
        return false;
    }

    if (isExpiredTemporaryLock($row)) {
        $fields = [
            'failedlogincount' => 0,
            'blockeduntil' => 0
        ];
        db()->update(
            $fields,
            '[+prefix+]user_attributes',
            sprintf("internalKey='%d'", user('internalKey'))
        );
        $row = array_merge($row, $fields);
    }

    if ($legacyLock) {
        $row['blocked'] = 0;
    }

    return isUserBlocked($row);
}

// 失敗回数による一時ロックで、期限が過ぎたもの。
// blocked=1 の行は含めない。外しても拒否されるうえ、以前の版の一時ロック（blocked も 1）の形を崩すと、
// マイグレーションと releaseLegacyLoginLock() が拾えなくなるため
function isExpiredTemporaryLock($row)
{
    return isTemporaryLoginLock($row)
        && (int) array_get($row, 'blockeduntil', 0) <= request_time()
        && (int) array_get($row, 'blocked', 0) !== 1;
}

// 以前の版の一時ロック（blocked も 1）の行を外すマイグレーション
function legacyLoginLockMigrationId()
{
    return '20261107_180000_unblock_legacy_temporary_login_locks';
}

// ログインする本人の行が、以前の版の一時ロックで期限が過ぎたもので、マイグレーションがまだ外していないか。
// 対象はマイグレーションが外す行（legacyTemporaryLoginLockSqlCondition()）のうち、本人の、期限が過ぎた行だけ
function isLegacyLoginLockAwaitingMigration()
{
    if (!user('internalKey')) {
        return false;
    }
    $matched = (bool) db()->getValue(
        db()->select('COUNT(*)', '[+prefix+]user_attributes', legacyLoginLockWhere())
    );
    return $matched && !legacyLoginLockMigrationApplied();
}

// マイグレーションが記録済みか。記録を読めないときは記録済みとみなす（外さない側に倒す）。
// マイグレーションのファイルが無い（baseline に取り込んで消した など）ときも、記録済みとみなす。
// 記録の表が無い DB（マイグレーションの仕組みより前の版）は、未適用として扱う
function legacyLoginLockMigrationApplied()
{
    try {
        require_once MODX_CORE_PATH . 'migrator.class.inc.php';
        $migrator = new EvoMigrator();
        if (!in_array(legacyLoginLockMigrationId(), $migrator->all(), true)) {
            return true;
        }
        return isset($migrator->applied()[legacyLoginLockMigrationId()]);
    } catch (Throwable $e) {
        return true;
    }
}

function legacyLoginLockWhere()
{
    return sprintf(
        'internalKey=%d AND %s AND blockeduntil<=%d',
        user('internalKey'),
        legacyTemporaryLoginLockSqlCondition(config('failed_login_attempts', '')),
        request_time()
    );
}

// 認証が通った後で、以前の版の一時ロックを外す（マイグレーションと同じ変更に、期限切れの一時ロックの解除を合わせる）
function releaseLegacyLoginLock()
{
    db()->update(
        [
            'blocked' => 0,
            'failedlogincount' => 0,
            'blockeduntil' => 0
        ],
        '[+prefix+]user_attributes',
        legacyLoginLockWhere()
    );
    if (db()->getAffectedRows()) {
        evo()->logEvent(0, 1, 'Unblocked a user locked by failed logins in the old way before the migration', 'Login', [
            'internalKey' => (int) user('internalKey'),
            'migration' => legacyLoginLockMigrationId(),
        ]);
    }
}

// DB の最新の状態で、まだブロックされているかを確かめる（user() はキャッシュ済みの値を返すため使わない）
function isStillBlockedInDb()
{
    $row = userBlockStateInDb();
    return !$row || isUserBlocked($row);
}

// ブロックの判定に使う列を、DB から読み直す
function userBlockStateInDb()
{
    return db()->getRow(
        db()->select(
            'blocked, blockeduntil, blockedafter, failedlogincount',
            '[+prefix+]user_attributes',
            sprintf("internalKey='%d'", user('internalKey'))
        )
    );
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
    // fmpkey付きリクエストでは password が無い（null）ため文字列に揃える
    $inputPassword = (string)$inputPassword;
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
    $header = 'Location: ' . loginRedirectUri();
    if (post_boolvar('ajax')) {
        exit($header);
    }
    header($header);
}

/**
 * ログイン後の行き先。ログイン前に開こうとした画面を、ユーザー設定の初期ページより優先する
 * - 管理画面: ログインフォームが持つ画面のクエリ（return。accesscontrol-not-mgr.inc.php が入れる）
 * - 表側の下書きプレビュー: 管理画面のトップへ移ってからログインするので、URL はセッション（save_uri）に覚えてある
 */
function loginRedirectUri()
{
    $returnQuery = loginReturnQuery(post_strvar('return'));
    if ($returnQuery !== '') {
        sessionv('*save_uri', null);
        return MODX_MANAGER_URL . 'index.php?' . $returnQuery;
    }

    $savedUri = (string) sessionv('save_uri', '');
    sessionv('*save_uri', null);
    if (isSameSiteUri($savedUri)) {
        return $savedUri;
    }
    if (user_config('manager_login_startup')) {
        return evo()->makeUrl(user_config('manager_login_startup'));
    }
    return MODX_MANAGER_URL;
}

/**
 * ログインフォームから来た画面のクエリを、組み立て直して返す。画面の番号 a が無ければ空。
 * 行き先は管理画面の index.php に固定して付けるので、クエリをどう書き換えられても外部へは出ない
 */
function loginReturnQuery($query)
{
    if (!is_string($query) || $query === '') {
        return '';
    }
    parse_str($query, $params);
    if (empty($params['a'])) {
        return '';
    }
    return http_build_query($params);
}

/**
 * サイトの中を指す URL か。サイトの URL で始まるものと、/ で始まるパスだけを受ける
 * （//example.com や /\example.com は、ブラウザが外部のサイトとして解釈しうるので受けない）
 */
function isSameSiteUri($uri)
{
    if ($uri === '' || strpbrk($uri, "\r\n") !== false) {
        return false;
    }
    if (strpos($uri, MODX_SITE_URL) === 0) {
        return true;
    }
    return (bool) preg_match('@^/(?![/\\\\])@', $uri);
}

function managerLogin()
{
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

    $_SESSION['mgrPermissions'] = manager()->getMgrPermissions(user('role'));
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
    // 表側のセッションの古い作成時刻を引き継がず、インストール前のセッション判定に切られないようにする
    $_SESSION['modx.session.created.time'] = request_time();
    $_SESSION['mgrDocgroups'] = manager()->getMgrDocgroups(user('internalKey'));

    if (request_strvar('rememberme')) {
        $_SESSION['modx.mgr.session.cookie.lifetime'] = (int)evo()->config('session.cookie.lifetime', 0);
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
