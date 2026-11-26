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

// CAPTCHA は、ユーザーの読み込み・ブロックの判定・認証より前に確かめる。
// ユーザー名によって応答が変わらないようにするため。通らないうちはユーザーを確かめず、失敗回数にも数えない。
// パスワード再設定のリンクでは求めない（パスワードを送るログインは、fmpkey があっても確かめる）
if (!isPasswordResetLinkRequest() && !checkCaptcha()) {
    return;
}

// ブロック中は処理をここで止める。
// パスワード再設定のリンクの要求（isPasswordResetLinkRequest）だけは OnManagerAuthentication（FMP）に判定を任せ、
// FMP がブロックを解除した場合だけログインさせる（下で DB を確かめる）。
// パスワードを送る要求は、fmpkey があっても普通のログインとして先に止める
// 以前の版の一時ロック（マイグレーションの前）は止めずに認証へ進め、認証が通った後で外す
$legacyLock = isLegacyLoginLockAwaitingMigration();
$isBlocked = isBlockedUser($legacyLock);
if ($isBlocked && !isPasswordResetLinkRequest()) {
    rejectBlockedUser();
    return;
}

if (!checkAllowedIp() || !checkAllowedDays()) {
    return;
}

evo()->loadExtension('ManagerAPI');

// invoke OnManagerAuthentication event
if (!OnManagerAuthentication()) {
    // パスワード再設定のリンクの要求で認証に失敗した場合は、パスワード違いではなくリンクが無効だと伝える。
    // 期限切れのリンクを開いただけでブロックが延びないよう、失敗回数は増やさない。
    // パスワードを送る要求は普通のログインとして、パスワードを確かめて失敗回数に数える
    if (isPasswordResetLinkRequest()) {
        jsAlert(lang('fmp_link_invalid', 'This password reset link is invalid or has expired. Please request a new one.'));
        return;
    }
    if (!validPassword(input('password'), user('password'))) {
        jsAlert(alert()->errors[901]);
        failedLogin();
        return;
    }
}

if ($legacyLock && !$isBlocked) {
    releaseLegacyLoginLock();
}

// ブロック中のユーザーの認証が通った場合は、FMP（または上の以前の版の一時ロックの解除）がブロックを解除したことを DB で確かめる。
// OnManagerAuthentication はどのプラグインの成功でも true になるため、成否だけでは判断しない
if (($isBlocked || $legacyLock) && isStillBlockedInDb()) {
    rejectBlockedUser();
    return;
}

managerLogin();
OnManagerLogin();
redirectAfterLogin();
