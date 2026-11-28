<?php
include '../define-path.php';
define('MODX_API_MODE', true);
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_BASE_PATH . 'install/functions.php');

includeLang(post_choicevar('language', get_langs(), getOption('install_language')));
guardInstallerRequest();

// AJAXリクエストで送信された接続情報を使用
// JavaScriptは 'host', 'uid', 'pwd' という名前で送信している
$database_server = post_textvar('host', '');
$database_user = post_textvar('uid', '');
$database_password = post_textvar('pwd', '');

// POSTデータが送信されていない場合はエラー
if (!$database_server && !post_textvar('dbase')) {
    exit(databaseTestResult(false, 'データベース接続情報を入力してください'));
}

// 接続情報をセッションに保存
sessionv('*database_server', $database_server);
sessionv('*database_user', $database_user);
sessionv('*database_password', $database_password);

// 既存の接続をクリア
if (db()->conn) {
    db()->conn->close();
    db()->conn = null;
}
// 空の欄は空のまま使い、保存済みの接続情報に戻さない
db()->hostname = (string) $database_server;
db()->username = (string) $database_user;
db()->password = (string) $database_password;

$connected = db()->connect(
    $database_server,
    $database_user,
    $database_password,
    '',
    2
);
if (!$connected) {
    exit(databaseTestResult(false, lang('status_failed')));
}

$db_name              = trim(post_textvar('dbase', ''), '`');
if ($db_name === '') {
    exit(databaseTestResult(false, lang('status_failed_could_not_create_database')));
}

$table_prefix         = trim(post_textvar('table_prefix', ''));
if ($table_prefix) {
    $table_prefix = rtrim($table_prefix, '_') . '_';
}
$db_collation         = trim(post_textvar('database_collation', ''));
$db_connection_method = trim(post_textvar('database_connection_method', ''));
$underscorePos        = strpos($db_collation, '_');
$db_charset           = $underscorePos !== false ? substr($db_collation, 0, $underscorePos) : $db_collation;

if (!isValidDbIdentifier($db_name) || !isValidCharsetName($db_charset) || !isValidCollationName($db_collation)) {
    exit(
        databaseTestResult(false, lang('status_failed_could_not_create_database'))
    );
}

if (@db()->select_db($db_name)) {
    if (isAlreadyInUse($db_name, $table_prefix)) {
        exit(
            databaseTestResult(false, lang('status_failed_table_prefix_already_in_use'))
        );
    }
    $msg = lang('status_passed');
} else {
    if (!createDB($db_name, $db_charset, $db_collation)) {
        exit(
            databaseTestResult(false, lang('status_failed_could_not_create_database'))
        );
    }
    $msg = lang('status_passed_database_created');
}

sessionv('*dbase', $db_name);
sessionv('*table_prefix', $table_prefix);
sessionv('*database_collation', $db_collation);
sessionv('*database_connection_method', $db_connection_method);
sessionv('*database_charset', $db_charset);

echo databaseTestResult(true, $msg);


function createDB($db_name, $db_charset, $db_collation)
{
    $query = sprintf(
        "CREATE DATABASE `%s` CHARACTER SET '%s' COLLATE %s",
        db()->escape($db_name),
        db()->escape($db_charset),
        db()->escape($db_collation)
    );
    return @db()->conn->query($query);
}

function isValidDbIdentifier($value)
{
    return is_string($value)
        && preg_match('/^[A-Za-z0-9_\\$-]{1,64}$/', $value);
}

function isValidCharsetName($value)
{
    return is_string($value)
        && preg_match('/^[A-Za-z0-9_]{1,64}$/', $value);
}

function isValidCollationName($value)
{
    return is_string($value)
        && preg_match('/^[A-Za-z0-9_]{1,64}$/', $value);
}

function isAlreadyInUse($db_name, $table_prefix)
{
    global $modx;
    $modx->db->dbname       = db()->escape($db_name);
    $modx->db->table_prefix = db()->escape($table_prefix);
    if (!db()->tableExists('[+prefix+]site_content')) {
        return false;
    }
    // テーブルがあっても、どれも空なら失敗したインストールの残りとみなし、やり直しを通す
    // （空のテーブルには、やり直しでそのまま初期データを入れられる）
    return prefixedTablesHaveRows($table_prefix);
}

/**
 * DB のテストの結果。接続のテスト（connection.servertest.php）と同じく、見出しと結果を 1 つの枠に入れる。
 * 画面の JS は、id の database_pass で成否を見る
 */
function databaseTestResult($passed, $message)
{
    return sprintf(
        '<div style="background: %s;padding:8px;border-radius:5px;">%s<span id="%s" style="color:%s;">%s</span></div>',
        $passed ? '#e6ffeb' : '#ffe6eb',
        lang('status_checking_database'),
        $passed ? 'database_pass' : 'database_fail',
        $passed ? '#388000' : '#FF0000',
        $message
    );
}
