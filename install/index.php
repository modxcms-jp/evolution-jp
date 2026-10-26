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

// DocumentParser が mbstring を前提にしているため、読み込む前に確かめる（言語ファイルにも頼らない）
if (!extension_loaded('mbstring')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>MODX Installer</title></head><body>'
        . '<p>PHP の拡張モジュール mbstring が有効ではありません。mbstring は必須です。有効にしてからやり直してください。</p>'
        . '<p>The PHP mbstring extension is not enabled. It is required; enable it and try again.</p>'
        . '</body></html>';
    exit;
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
} elseif (!managerConfigExists()) {
    // config.inc.php が無い場合は、古いセッションのDB情報より新規インストール判定を優先する
    sessionv('*is_upgradeable', 0);
} elseif (db()->isConnected() && sessionv('dbase')) {
    // DB接続済み かつ DB名が設定されている場合のみテーブル確認
    sessionv('*is_upgradeable', checkAllTablesExist() ? 1 : 0);
} else {
    // それ以外は新規インストール
    sessionv('*is_upgradeable', 0);
}

$_lang = includeLang(lang_name());

// 1.0.0J より古い DB は新しいファイルで管理画面にログインできないので、認証より前に止める（DB はまだ変えていない）
if (sessionv('is_upgradeable') && isUnsupportedOldDb()) {
    renderUnsupportedOldDb();
}

guardInstallerRequest();

$errors = 0;

$ph = ph();
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
