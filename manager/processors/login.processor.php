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

sessionv('*safeMode', 0);
if (!sessionv('SystemAlertMsgQueque')) {
    $_SESSION['SystemAlertMsgQueque'] = [];
}

$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

OnBeforeManagerLogin();

// ブロック中は処理をここで止める。
// パスワード再設定リンク（fmpkey）だけは OnManagerAuthentication（FMP）に判定を任せ、
// 認証が通ればブロックを解除してログインさせる（下の分岐で、通らなければ止める）
$isBlocked = isBlockedUser();
if ($isBlocked && !getv('fmpkey')) {
    rejectBlockedUser();
    return;
}

if (!checkAllowedIp() || !checkAllowedDays()) {
    return;
}

evo()->loadExtension('ManagerAPI');

// invoke OnManagerAuthentication event
if (!OnManagerAuthentication()) {
    if ($isBlocked) {
        rejectBlockedUser();
        return;
    }
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
