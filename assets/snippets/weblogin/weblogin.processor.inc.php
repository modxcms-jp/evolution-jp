<?php
# WebLogin 1.0
# Created By Raymond Irving 2004
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

defined('IN_PARSER_MODE') or die();

$tbl_web_user_attributes = evo()->getFullTableName('web_user_attributes');
$tbl_web_users = evo()->getFullTableName('web_users');
$tbl_web_user_settings = evo()->getFullTableName('web_user_settings');

# process password activation
if ($isPWDActivate == 1) {
    $uid = db()->escape(anyv('uid'));

    $rs = db()->select('*', $tbl_web_users, "id='{$uid}'");
    $limit = db()->count($rs);
    if ($limit != 1) {
        $output = webLoginAlert("Error while loading user account. Please contact the Site Administrator");
        return;
    }
    $row = db()->getRow($rs);
    $username = $row['username'];
    [$newpwd, $token, $requestedon] = explode('|', $row['cachepwd']);
    $past = time() - $requestedon;
    if (!isset($expireTime)) {
        $expireTime = 60 * 60 * 24;
    }
    if ($token !== anyv('token')) {
        if (!$actInvalidKey)
            $output = webLoginAlert("Invalid password activation key. Your password was NOT activated.");
        else $modx->sendRedirect($actInvalidKey);
        return;
    }

    if ($expireTime < $past) {
        if (!$actExpire)
            $output = webLoginAlert("It was over expiration time");
        else
            $modx->sendRedirect($actExpire);
        return;
    }
    // activate new password
    $f = [];
    $f['password'] = md5($newpwd);
    $f['cachepwd'] = '';
    $rs = db()->update($f, $tbl_web_users, "id='{$uid}'");

    // unblock user by resetting "blockeduntil"
    $rs2 = db()->update("blockeduntil='0'", $tbl_web_user_attributes, "internalKey='{$uid}'");

    // invoke OnWebChangePassword event
    $tmp = [
        'userid' => $uid,
        'username' => $username,
        'userpassword' => $newpwd
    ];
    evo()->invokeEvent('OnWebChangePassword', $tmp);

    if (!$rs || !$rs2) $output = webLoginAlert("Error while activating password.");
    elseif (!$pwdActId) $output = webLoginAlert("Your new password was successfully activated.");
    else {
        // redirect to password activation notification page
        $url = $modx->makeURL($pwdActId, '', "uid={$uid}", 'full');
        $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
    }
    return;
}

# process password reminder
if ($isPWDReminder == 1) {
    $email = postv('txtwebemail');
    if (isset($reminder_message)) {
        if (preg_match('@^[1-9[0-9]*$@', $reminder_message))
            $message = $modx->getField('content', $reminder_message);
        else $message = $modx->getChunk($reminder_message);
    }
    if (!isset($reminder_message) || empty($message))
        $message = $modx->config('webpwdreminder_message');
    if (!isset($reminder_subject)) $reminder_subject = 'New Password Activation for ' . $modx->config('site_name');
    if (!isset($reminder_from)) $reminder_from = $modx->config('emailsender');
    if (!isset($reminder_fromname)) $reminder_fromname = $modx->config('site_name');
    // lookup account
    $sql = "SELECT wu.*, wua.fullname
	FROM {$tbl_web_users} wu
	INNER JOIN {$tbl_web_user_attributes} wua ON wua.internalkey=wu.id
	WHERE wua.email='" . db()->escape($email) . "'";

    $ds = db()->query($sql);
    $limit = db()->count($ds);
    if ($limit == 1) {
        $newpwd = webLoginGeneratePassword(8);
        $token = webLoginGeneratePassword(8); // activation key
        $row = db()->getRow($ds);
        $uid = $row['id'];
        //save new password
        $f = [];
        $requestedon = time();
        $f['cachepwd'] = "{$newpwd}|{$token}|{$requestedon}";
        db()->update($f, $tbl_web_users, "id='{$uid}'");
        // built activation url
        $xhtmlUrlSetting = $modx->config('xhtml_urls', false);
        $modx->config['xhtml_urls'] = false;
        $url = $modx->makeURL($modx->documentIdentifier, '', "webloginmode=actp&uid={$uid}&token={$token}", 'full');
        $modx->config['xhtml_urls'] = $xhtmlUrlSetting;
        // replace placeholders and send email
        $ph = [];
        $ph['uid'] = $uid;
        $ph['username'] = $row['username'];
        $ph['password'] = $newpwd;
        $ph['pwd'] = $newpwd;
        $ph['ufn'] = $row['fullname'];
        $ph['fullname'] = $row['fullname'];
        $ph['sname'] = $modx->config('site_name');
        $ph['semail'] = $reminder_from;
        $ph['url'] = $url;
        $ph['surl'] = $url;
        $message = $modx->parseText($message, $ph);
        $message = $modx->parseDocumentSource($message);
        $config['from'] = $reminder_from;
        $config['fromname'] = $reminder_fromname;
        $config['sendto'] = $email;
        if (!isset($emailsubject)) $emailsubject = 'パスワード再設定';
        $config['subject'] = $emailsubject;
        $sent = $modx->sendmail($config, $message);         //ignore mail errors in this cas
        if (!$sent) // error
        {
            $output = webLoginAlert("Error while sending mail to [+email+]. Please contact the Site Administrator", ['email' => $email]);
            return;
        }

        if (!$pwdReqId) $output = webLoginAlert("Please check your email account ([+email+]) for login instructions.", ['email' => $email]);
        else // redirect to password request notification page
        {
            $url = $modx->makeURL($pwdReqId, '', '', 'full');
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
        }
    } else {
        if (!$actUserNotFound) $output = webLoginAlert("We are sorry! We cannot locate an account using that email.");
        else {
            $url = $modx->makeURL($actUserNotFound, '', '', 'full');
            $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
        }
    }
    return;
}

# process logout
if ($isLogOut == 1) {
    $internalKey = sessionv('webInternalKey');
    $username = sessionv('webShortname');

    // invoke OnBeforeWebLogout event
    $v = [];
    $v['userid'] = $internalKey;
    $v['username'] = $username;
    evo()->invokeEvent('OnBeforeWebLogout', $v);

    // if we were launched from the manager
    // do NOT destroy session
    if (sessionv('mgrValidated') !== null) {
        sessionv('*webShortname', null);
        sessionv('*webFullname', null);
        sessionv('*webEmail', null);
        sessionv('*webValidated', null);
        sessionv('*webInternalKey', null);
        sessionv('*webValid', null);
        sessionv('*webUser', null);
        sessionv('*webFailedlogins', null);
        sessionv('*webLastlogin', null);
        sessionv('*webnrlogins', null);
        sessionv('*webUserGroupNames', null);
        sessionv('*webDocgroups', null);
    } else {
        if (cookiev(session_name()) !== null) {
            setcookie(
                session_name(),
                '',
                [
                    'expires' => 0,
                    'path' => MODX_BASE_URL,
                    'domain' => '',
                    'secure' => init::is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax' // クロスサイト保護
                ]
            );
        }
        session_destroy();
    }

    // invoke OnWebLogout event
    $v = [];
    $v['userid'] = $internalKey;
    $v['username'] = $username;
    evo()->invokeEvent('OnWebLogout', $v);

    // redirect to first authorized logout page
    $modx->config['xhtml_urls'] = '0';
    $url = preserveUrl($loHomeId);
    $modx->sendRedirect($url, 0, 'REDIRECT_REFRESH');
    return;
}

# process login

$username = db()->escape(htmlspecialchars(postv('username'), ENT_QUOTES));
$givenPassword = db()->escape(postv('password'));
$captcha_code = postv('captcha_code', '');
$rememberme = postv('rememberme');

// invoke OnBeforeWebLogin event
$v = [];
$v['username'] = $username;
$v['userpassword'] = $givenPassword;
$v['rememberme'] = $rememberme;
evo()->invokeEvent('OnBeforeWebLogin', $v);

$field = 'web_users.*, user_attributes.*';
$from = "{$tbl_web_users} as web_users, {$tbl_web_user_attributes} as user_attributes";
$where = "BINARY web_users.username='{$username}' and user_attributes.internalKey=web_users.id";
$ds = db()->select($field, $from, $where);
$limit = db()->count($ds);

if ($limit == 0 || $limit > 1) {
    $output = webLoginAlert("Incorrect username or password entered!");
    return;
}

$row = db()->getRow($ds);

$internalKey = $row['id'];
$dbasePassword = $row['password'];
$failedlogins = $row['failedlogincount'];
$blocked = $row['blocked'];
$blockeduntildate = $row['blockeduntil'];
$blockedafterdate = $row['blockedafter'];
$registeredsessionid = $row['sessionid'];
$role = $row['role'];
$lastlogin = $row['lastlogin'];
$nrlogins = $row['logincount'];
$fullname = $row['fullname'];
$email = $row['email'];

// load user settings
if ($internalKey) {
    $rs = db()->select('setting_name, setting_value', $tbl_web_user_settings, "webuser='$internalKey'");
    while ($row = db()->getRow($rs, 'both')) {
        $modx->config[$row[0]] = $row[1];
    }
}

$failedLoginLimit = (int)config('failed_login_attempts', 5);
$blockMinutes = (int)config('blocked_minutes', 10);
$allowedIp = config('allowed_ip', '');
$allowedDays = config('allowed_days', '');
$useCaptcha = (int)config('use_captcha', 0);

if ($failedlogins >= $failedLoginLimit && $blockeduntildate > time()) {
    // blocked due to number of login errors.
    session_destroy();
    session_unset();
    $output = webLoginAlert('Due to too many failed logins, you have been blocked.');
    return;
}

if ($failedlogins >= $failedLoginLimit && $blockeduntildate < time()) {
    // blocked due to number of login errors, but get to try again
    $f = [];
    $f['failedlogincount'] = '0';
    $f['blockeduntil'] = time() - 1;
    $ds = db()->update($f, $tbl_web_user_attributes, "internalKey='{$internalKey}'");
}

if ($blocked == '1') // this user has been blocked by an admin, so no way he's loggin in!
{
    session_destroy();
    session_unset();
    $output = webLoginAlert("You are blocked and cannot log in!");
    return;
}

// blockuntil
if ($blockeduntildate > time()) // this user has a block until date
{
    session_destroy();
    session_unset();
    $output = webLoginAlert("You are blocked and cannot log in! Please try again later.");
    return;
}

// blockafter
if ($blockedafterdate > 0 && $blockedafterdate < time()) // this user has a block after date
{
    session_destroy();
    session_unset();
    $output = webLoginAlert('You are blocked and cannot log in! Please try again later.');
    return;
}

// allowed ip
if ($allowedIp !== '') {
    if (strpos($allowedIp, serverv('REMOTE_ADDR', '')) === false) {
        $output = webLoginAlert('You are not allowed to login from this location.');
        return;
    }
}

// allowed days
if ($allowedDays !== '') {
    $date = getdate();
    $day = $date['wday'] + 1;
    if (strpos($allowedDays, (string)$day) === false) {
        $output = webLoginAlert('You are not allowed to login at this time. Please try again later.');
        return;
    }
}

// invoke OnWebAuthentication event
$tmp = [
    "userid" => $internalKey,
    "username" => $username,
    "userpassword" => $givenPassword,
    "savedpassword" => $dbasePassword,
    "rememberme" => $rememberme
];
$rt = evo()->invokeEvent("OnWebAuthentication", $tmp);
// check if plugin authenticated the user
if (!$rt || (is_array($rt) && !in_array(TRUE, $rt))) {
    // check user password - local authentication
    if ($dbasePassword != md5($givenPassword)) {
        $output = webLoginAlert("Incorrect username or password entered!");
        $newloginerror = 1;
    }
}

if ($useCaptcha === 1) {
    if (sessionv('veriword') != $captcha_code) {
        $output = webLoginAlert("The security code you entered didn't validate! Please try to login again!");
        $newloginerror = 1;
    }
}

if (isset($newloginerror) && $newloginerror == 1) {
    $failedlogins += $newloginerror;
    if ($failedlogins >= $failedLoginLimit) //increment the failed login counter, and block!
    {
        $f = [];
        $f['failedlogincount'] = $failedlogins;
        $f['blocked'] = 1;
        $f['blockeduntil'] = time() + ($blockMinutes * 60);
    } else //increment the failed login counter
    {
        $f = [];
        $f['failedlogincount'] = $failedlogins;
    }
    $ds = db()->update($f, $tbl_web_user_attributes, "internalKey='{$internalKey}'");

    session_destroy();
    session_unset();
    return;
}

$currentsessionid = session_id();

if (sessionv('webValidated') === null) {
    $sql = "update {$tbl_web_user_attributes} SET failedlogincount=0, logincount=logincount+1, lastlogin=thislogin, thislogin=" . time() . ", sessionid='$currentsessionid' where internalKey=$internalKey";
    $ds = db()->query($sql);
}

sessionv('*webShortname', $username);
sessionv('*webFullname', $fullname);
sessionv('*webEmail', $email);
sessionv('*webValidated', 1);
sessionv('*webInternalKey', $internalKey);
sessionv('*webValid', base64_encode($givenPassword));
sessionv('*webUser', base64_encode($username));
sessionv('*webFailedlogins', $failedlogins);
sessionv('*webLastlogin', $lastlogin);
sessionv('*webnrlogins', $nrlogins);
sessionv('*webUserGroupNames', ''); // reset user group names

// get user's document groups
$tbl_webgroup_access = evo()->getFullTableName('webgroup_access');

$from = ['[+prefix+]web_groups ug'];
$from[] = 'INNER JOIN [+prefix+]webgroup_access uga ON uga.webgroup=ug.webgroup';
$ds = db()->select('uga.documentgroup', $from, "ug.webuser='{$internalKey}'");
$i = 0;
$dg = [];
while ($row = db()->getRow($ds, 'num')) {
    $i++;
    $dg[$i] = $row[0];
}
sessionv('*webDocgroups', $dg);

$from = ['[+prefix+]webgroup_names wgn'];
$from[] = "INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser={$internalKey}";
$grpNames = $this->db->getColumn('name', $this->db->select('wgn.name', $from));
sessionv('*webUserGroupNames', $grpNames);

if ($rememberme) {
    sessionv('*modx.web.session.cookie.lifetime', (int)config('session.cookie.lifetime', 0));
} else {
    sessionv('*modx.web.session.cookie.lifetime', 0);
}

$log = new logHandler;
$log->initAndWriteLog("Logged in", sessionv('webInternalKey'), sessionv('webShortname'), "58", "-", "WebLogin");

// get login home page
$ok = false;
$loginHome = config('login_home', 0);
if ($loginHome && $id = $loginHome) {
    if ($modx->getPageInfo($id)) $ok = true;
}
if (!$ok) // check if a login home id page was set
{
    foreach ($liHomeId as $id) {
        $id = trim($id);
        if ($modx->getPageInfo($id)) {
            $ok = true;
            break;
        }
    }
}

// update active users list if redirectinq to another page
if ($id != $modx->documentIdentifier) {
    sessionv('*ip', real_ip());
    $itemid = anyv('id');
    $lasthittime = time();
    $a = 998;
    if ($a != 1) {
        // web users are stored with negative id
        $tbl_active_users = evo()->getFullTableName('active_users');
        $sql = sprintf(
            "REPLACE INTO %s (internalKey, username, lasthit, action, id, ip) VALUES (-%d, '%s', %d, '%s', %d, '%s')",
            $tbl_active_users,
            sessionv('webInternalKey'),
            sessionv('webShortname'),
            $lasthittime,
            $a,
            $itemid,
            real_ip()
        );
        if (!$ds = db()->query($sql)) {
            $output = "error replacing into active users! SQL: {$sql}";
            return;
        }
    }
}

// invoke OnWebLogin event
$tmp = [
    "userid" => $internalKey,
    "username" => $username,
    "userpassword" => $givenPassword,
    "rememberme" => postv('rememberme')
];
evo()->invokeEvent("OnWebLogin", $tmp);

// redirect
$refUrl = anyv('refurl');
if (!empty($refUrl)) {
    // last accessed page
    $targetPageId = $refUrl;
    $qPos = strpos($targetPageId, 'q=');
    if ($qPos !== false) {
        $urlPos = $qPos + 2;
        $alias = substr($targetPageId, $urlPos);
        $ampPos = strpos($alias, '&');
        $aliasLength = $ampPos !== false ? $ampPos : strlen($alias);
        $alias = substr($alias, 0, $aliasLength);
        $url = $modx->config('base_url') . $alias;
    } elseif (intval($targetPageId)) {
        $modx->config['xhtml_urls'] = '0';
        $url = preserveUrl($targetPageId);
    } else {
        $url = $refUrl;
    }
    $modx->sendRedirect($url);
} else // login home page
{
    $modx->config['xhtml_urls'] = '0';
    $url = preserveUrl($id);
    $modx->sendRedirect($url);
}
return;
