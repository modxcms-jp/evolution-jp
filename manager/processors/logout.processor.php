<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

$internalKey = $modx->getLoginUserID();
$username = $_SESSION['mgrShortname'];

// invoke OnBeforeManagerLogout event
$tmp = array(
    'userid' => $internalKey,
    'username' => $username
);
$modx->invokeEvent("OnBeforeManagerLogout", $tmp);

//// Unset all of the session variables.
//$_SESSION = array();
// destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', 0, $modx->config['base_url']);
}
//// now destroy the session
@session_destroy(); // this sometimes generate an error in iis

// invoke OnManagerLogout event
$tmp = array(
    'userid' => $internalKey,
    'username' => $username
);
$modx->invokeEvent("OnManagerLogout", $tmp);

// show login screen
header('Location: ' . MODX_SITE_URL . 'manager/');
