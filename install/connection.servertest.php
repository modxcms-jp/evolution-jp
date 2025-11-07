<?php
include '../define-path.php';
define('MODX_API_MODE', true);
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');
include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$modx = new DocumentParser;
require_once MODX_BASE_PATH . 'install/functions.php';
$_lang = includeLang(sessionv('install_language', 'english'));

// POSTデータを取得
$host = postv('host');
$username = postv('uid');
$password = postv('pwd');

// POSTデータが送信されていない場合はエラー
if (!$host) {
    exit(sprintf(
        '<div style="background: #ffe6eb;padding:8px;border-radius:5px;"><span style="color:#FF0000;">%s</span></div>',
        'データベースホスト名を入力してください'
    ));
}

// 既存の接続を強制的にクリア（connect() は isConnected() チェックで既存接続があると即座に return true してしまう）
if (db()->conn) {
    db()->conn->close();
    db()->conn = null;
}

// 明示的に接続情報を渡す（タイムアウト2秒）
$connected = db()->connect($host, $username, $password, '', 2);

// connect() の戻り値をチェック（isConnected() ではなく）
if (!$connected) {
    exit(sprintf(
        '<div style="background: #ffe6eb;padding:8px;border-radius:5px;"><span id="server_fail" style="color:#FF0000;">%s (host: %s, user: %s)</span></div>',
        lang('status_failed'),
        htmlspecialchars($host),
        htmlspecialchars($username)
    ));
}
$output = sprintf(
    '<span id="server_pass" style="color:#388000;">%s</span>',
    lang('status_passed_server')
);
sessionv('*database_server', db()->hostname);
sessionv('*database_user', db()->username);
sessionv('*database_password', db()->password);

echo sprintf(
    '<div style="background: #e6ffeb;padding:8px;border-radius:5px;">%s</div>',
    lang('status_connecting') . $output
);

$script = '<script>
(function() {
    const characters = {' . getCollations() . "};
    const sel = document.getElementById('collation');

    for (const [value, name] of Object.entries(characters)) {
        const opt = document.createElement('option');
        opt.value = value;
        opt.text = name;
        opt.selected = (value === 'utf8mb4_general_ci');
        sel.appendChild(opt);
    }
})();
</script>";
echo $script;

function getCollations()
{
    $rs = db()->query("SHOW COLLATION LIKE 'utf8%'");
    $collations = [];
    while ($row = db()->getRow($rs)) {
        if (isSafeCollation($row['Collation'])) {
            $collations[] = sprintf("%s:'%s'", $row['Collation'], $row['Collation']);
        }
        //$row['Charset'];
    }
    return implode(',', $collations);
}

function isSafeCollation($collation)
{
    if (strpos($collation, '_general_c') !== false) {
        return true;
    }

    if (strpos($collation, '_unicode_c') !== false) {
        return true;
    }

    if (strpos($collation, '_bin') !== false) {
        return true;
    }

    return false;
}
