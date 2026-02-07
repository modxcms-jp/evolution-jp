<?php
/**
 * MODX Installer
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
header("Content-Type: text/html; charset=utf-8");

include ('../define-path.php');

define('MODX_API_MODE', true);
if (!defined('MODX_BASE_PATH')) {
    define('MODX_BASE_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
}
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');

if (is_file(MODX_BASE_PATH . '.env')) {
    require_once MODX_BASE_PATH . 'manager/includes/dotenv-loader.php';
    $dotenv = new Dotenv(MODX_BASE_PATH . '.env');
    $dotenv->load();
}

include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/version.inc.php');
$cmsName = 'MODX';
$cmsVersion = $modx_branch . ' ' . $modx_version;
$moduleRelease = $modx_release_date;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_SETUP_PATH . 'functions.php');

if (!install_sessionCheck()) {
    echo parseText(
        file_get_contents(MODX_SETUP_PATH . 'tpl/session_problem.tpl'),
        includeLang(lang_name())
    );
    exit;
}

sessionv('*prevAction', sessionv('currentAction', ''));
$action = anyv('action', 'mode');
sessionv('*currentAction', $action);

// データベース接続があれば接続して、テーブルの存在で判定
if (sessionv('database_server')) {
    db()->prop('*dbname', sessionv('dbase'));
    db()->prop('*table_prefix', sessionv('table_prefix', 'modx_'));
    db()->prop('*connection_method', sessionv('database_connection_method'));
    db()->prop('*charset', sessionv('database_charset', 'utf8'));
    db()->connect(
        sessionv('database_server'),
        sessionv('database_user'),
        sessionv('database_password')
    );
}

// テーブルがあればアップグレード、なければ新規インストール
if ($action === 'mode') {
    sessionv('*is_upgradeable', isUpGradeable());
} elseif (db()->isConnected() && sessionv('dbase')) {
    // DB接続済み かつ DB名が設定されている場合のみテーブル確認
    sessionv('*is_upgradeable', checkAllTablesExist() ? 1 : 0);
} else {
    // それ以外は新規インストール
    sessionv('*is_upgradeable', 0);
}

$_lang = includeLang(lang_name());

// 新規インストール時: install-config.php による保護
// アップグレード時: Evolution CMS の管理者認証を要求
if (!sessionv('is_upgradeable')) {
    guardInstallerAccess();
} else {
    guardUpgradeAccess();
}

$errors = 0;

$ph = ph();
$ph = array_merge($ph, $_lang);
$ph['install_language'] = lang_name();

ob_start();
if (!@include(sprintf('%sactions/%s.php', MODX_SETUP_PATH, $action))) {
    exit (sprintf(
        'Invalid install action attempted. [action=%s]',
        $action
    ));
}
$ph['content'] = ob_get_contents();
ob_end_clean();

echo evo()->parseText(
    file_get_contents(MODX_SETUP_PATH . 'tpl/template.tpl'),
    $ph
);
