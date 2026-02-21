<?php
if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);
ini_set('display_errors', '1');

include_once '../../index.php';
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
$modx->getSettings();
include_once(__DIR__ . '/login.processor.functions.php');
$modx->config['login_by'] = 'username,email';

checkSafedUri();

if (!validateLoginInput()) {
    return;
}

$_SESSION['safeMode'] = input('safeMode');
if (!sessionv('SystemAlertMsgQueque')) {
    $_SESSION['SystemAlertMsgQueque'] = [];
}

$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

OnBeforeManagerLogin();

if (isBlockedUser()) {
    @session_destroy();
    session_unset();
    jsAlert(alert()->errors[902]);
}

if (!checkAllowedIp() || !checkAllowedDays()) {
    return;
}

evo()->loadExtension('ManagerAPI');

// invoke OnManagerAuthentication event
if (!OnManagerAuthentication()) {
    if (!validPassword(input('password'), user('password'))) {
        jsAlert(alert()->errors[901]);
        failedLogin();
        return;
    }
    if (!checkCaptcha()) {
        return;
    }
}

managerLogin();
OnManagerLogin();
redirectAfterLogin();
