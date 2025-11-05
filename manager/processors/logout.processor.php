<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

$internalKey = evo()->getLoginUserID();
$username = sessionv('mgrShortname');

// invoke OnBeforeManagerLogout event
$tmp = [
    'userid' => $internalKey,
    'username' => $username
];
evo()->invokeEvent("OnBeforeManagerLogout", $tmp);

if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        '',
        [
            'expires' => 0,
            'path' => MODX_BASE_URL,
            'domain' => '',
            'secure' => init::is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

// Clear CSRF tokens before destroying session
clearCsrfTokens();

//// now destroy the session
@session_destroy(); // this sometimes generate an error in iis

// invoke OnManagerLogout event
$tmp = [
    'userid' => $internalKey,
    'username' => $username
];
evo()->invokeEvent("OnManagerLogout", $tmp);

// show login screen
header('Location: ' . MODX_SITE_URL . 'manager/');
