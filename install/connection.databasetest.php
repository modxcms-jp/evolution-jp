<?php
include '../define-path.php';
define('MODX_API_MODE', true);
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_BASE_PATH . 'install/functions.php');

includeLang(getOption('install_language'));

// AJAXリクエストで送信された接続情報を使用
// JavaScriptは 'host', 'uid', 'pwd' という名前で送信している
$database_server = postv('host');
$database_user = postv('uid');
$database_password = postv('pwd');

// POSTデータが送信されていない場合はエラー
if (!$database_server && !postv('dbase')) {
    exit(lang('status_checking_database') . span_fail('#ffe6eb', 'データベース接続情報を入力してください'));
}

// 接続情報をセッションに保存
sessionv('*database_server', $database_server);
sessionv('*database_user', $database_user);
sessionv('*database_password', $database_password);

// 新しいAPIを使用して接続テスト
$result = DBAPI::testConnection($database_server, $database_user, $database_password, '', 2);

if (!$result->success) {
    exit(lang('status_checking_database') . span_fail('#ffe6eb', $result->getUserMessage()));
}

$db_name              = trim(postv('dbase'), '`');
$table_prefix         = trim(postv('table_prefix'));
if ($table_prefix) {
    $table_prefix = rtrim($table_prefix, '_') . '_';
}
$db_collation         = trim(postv('database_collation'));
$db_connection_method = trim(postv('database_connection_method'));
$underscorePos        = strpos($db_collation, '_');
$db_charset           = $underscorePos !== false ? substr($db_collation, 0, $underscorePos) : $db_collation;

// 接続済みのDBインスタンスを作成（新しいAPIを使用）
$db = DBAPI::forInstaller($database_server, $database_user, $database_password, '', 2);
$connResult = $db->connectWithResult();
if (!$connResult->success) {
    exit(lang('status_checking_database') . span_fail('#ffe6eb', $connResult->getUserMessage()));
}

if ($db->select_db($db->escape($db_name))) {
    if (isAlreadyInUse($db, $db_name, $table_prefix)) {
        exit(
            lang('status_checking_database') . span_fail(
                '#ffe6eb',
                lang('status_failed_table_prefix_already_in_use')
            )
        );
    }
    $msg = lang('status_passed');
} else {
    if (!createDB($db, $db_name, $db_charset, $db_collation)) {
        exit(
            lang('status_checking_database')
            . span_fail(
                '#ffe6eb',
                lang('status_failed_could_not_create_database')
            )
        );
    }
    $msg = lang('status_passed_database_created');
}

sessionv('*dbase', $db_name);
sessionv('*table_prefix', $table_prefix);
sessionv('*database_collation', $db_collation);
sessionv('*database_connection_method', $db_connection_method);
sessionv('*database_charset', $db_charset);

echo lang('status_checking_database') . span_pass('#e6ffeb', $msg);


function createDB(DBAPI $db, $db_name, $db_charset, $db_collation)
{
    $query = sprintf(
        "CREATE DATABASE `%s` CHARACTER SET '%s' COLLATE %s",
        $db->escape($db_name),
        $db->escape($db_charset),
        $db->escape($db_collation)
    );
    return @$db->query($query);
}

function isAlreadyInUse(DBAPI $db, $db_name, $table_prefix)
{
    global $modx;
    $modx->db->dbname       = $db->escape($db_name);
    $modx->db->table_prefix = $db->escape($table_prefix);
    if (!$db->tableExists('[+prefix+]site_content')) {
        return false;
    }
    if (!$db->select('COUNT(id)', '[+prefix+]site_content')) {
        return false;
    }
    return true;
}

function span_pass($bgcolor, $str)
{
    return sprintf('<span id="database_pass" style="background: %s;padding:8px;border-radius:5px;color:#388000;">%s</span>', $bgcolor, $str);
}

function span_fail($bgcolor, $str)
{
    return sprintf('<span id="database_fail" style="background: %s;padding:8px;border-radius:5px;color:#FF0000;">%s</span>', $bgcolor, $str);
}
