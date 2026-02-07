//<?php
/**
 * WebLogin
 * 
 * ウェブユーザーのログインフォームスニペット
 *
 * @category 	snippet
 * @version 	1.2
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &loginhomeid=Login Home Id;string; &logouthomeid=Logout Home Id;string; &logintext=Login Button Text;string; &logouttext=Logout Button Text;string; &tpl=Template;string;
 * @internal	@modx_category Login
 * @internal    @installset base
 */

/* --------------------------------
 * Manager 内では何もしない
 * -------------------------------- */
if (evo()->isBackend()) {
    return '';
}

$snipPath = base_path() . 'assets/snippets/';

/* --------------------------------
 * 後方互換パラメータ
 * -------------------------------- */
if (isset($loginid)) {
    $loginhomeid = $loginid;
}
if (isset($logoutid)) {
    $logouthomeid = $logoutid;
}
if (isset($template)) {
    $tpl = $template;
}

/* --------------------------------
 * スニペット設定値
 * -------------------------------- */
$defaultLoginHome = config('login_home', evo()->documentIdentifier);

$liHomeId = !empty($loginhomeid)
    ? explode(',', $loginhomeid)
    : [$defaultLoginHome, evo()->documentIdentifier];

$loHomeId   = $logouthomeid ?? evo()->documentIdentifier;
$pwdReqId   = $pwdreqid    ?? 0;
$pwdActId   = $pwdactid    ?? 0;
$loginText  = $logintext   ?? 'Login';
$logoutText = $logouttext  ?? 'Logout';
$tpl        = $tpl         ?? '';

/* --------------------------------
 * システム状態判定
 * -------------------------------- */
$webLoginMode  = anyv('webloginmode', '');
$isLogOut      = ($webLoginMode === 'lo');
$isPWDActivate = ($webLoginMode === 'actp');

$isPostBack =
    is_post() &&
    (postv('cmdweblogin') !== null || postv('cmdweblogin_x') !== null);

$txtPwdRem     = anyv('txtpwdrem', 0);
$isPWDReminder = $isPostBack && $txtPwdRem === '1';

/* --------------------------------
 * Cookie key
 * -------------------------------- */
$site_id   = $site_id ?? '';
$cookieKey = substr(md5($site_id . 'Web-User'), 0, 15);

/* --------------------------------
 * 共通処理
 * -------------------------------- */
require_once $snipPath . 'weblogin/weblogin.common.inc.php';
require_once $snipPath . 'weblogin/crypt.class.inc.php';

/* --------------------------------
 * 状態に応じた処理
 * -------------------------------- */
if ($isPWDActivate || $isPWDReminder || $isLogOut || $isPostBack) {
    require_once MODX_CORE_PATH . 'log.class.inc.php';
    require_once $snipPath . 'weblogin/weblogin.processor.inc.php';
}

/* --------------------------------
 * 表示処理
 * -------------------------------- */
require_once $snipPath . 'weblogin/weblogin.inc.php';

return $output;
