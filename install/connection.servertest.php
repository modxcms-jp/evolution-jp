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
    exit(errorDiv('データベースホスト名を入力してください'));
}

// 新しいAPIを使用して接続テスト（簡潔で明確！）
$result = DBAPI::testConnection($host, $username, $password, '', 2);

// エラーハンドリング（詳細なエラー情報が取得できる）
if (!$result->success) {
    // エラータイプに応じたメッセージ
    $message = match($result->errorType) {
        DBConnectionResult::ERROR_TYPE_DNS => sprintf(
            'ホスト "%s" が見つかりません。<br>ホスト名を確認してください。',
            htmlspecialchars($host)
        ),
        DBConnectionResult::ERROR_TYPE_AUTH => sprintf(
            'ユーザー "%s" の認証に失敗しました。<br>ユーザー名とパスワードを確認してください。',
            htmlspecialchars($username)
        ),
        DBConnectionResult::ERROR_TYPE_TIMEOUT => sprintf(
            '接続がタイムアウトしました（2秒）。<br>ホスト "%s" に到達できません。',
            htmlspecialchars($host)
        ),
        default => sprintf(
            '%s<br><details style="margin-top:4px;"><summary>詳細情報</summary><pre>%s (エラーコード: %d)</pre></details>',
            lang('status_failed'),
            htmlspecialchars($result->errorMessage),
            $result->errorCode
        ),
    };

    exit(errorDiv($message));
}

// 成功時の処理
$output = sprintf(
    '<span id="server_pass" style="color:#388000;">%s</span>',
    lang('status_passed_server')
);
sessionv('*database_server', $host);
sessionv('*database_user', $username);
sessionv('*database_password', $password);

echo successDiv(lang('status_connecting') . $output);

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

// Helper functions for cleaner HTML output
function errorDiv(string $message): string
{
    return sprintf(
        '<div style="background: #ffe6eb; padding: 8px; border-radius: 5px;">
            <span id="server_fail" style="color: #FF0000;">%s</span>
        </div>',
        $message
    );
}

function successDiv(string $message): string
{
    return sprintf(
        '<div style="background: #e6ffeb; padding: 8px; border-radius: 5px;">%s</div>',
        $message
    );
}
