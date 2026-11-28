<?php
/**
 * TinyMCE 7 の本文でタグを入力するときの補完に使う、チャンク・スニペット・リソース変数・テンプレート変数・設定の名前を JSON で返す
 * （管理画面にログインしている利用者だけ）。
 * 読み取りだけで状態を変えないので、link-list.php と同じく、セッションの確認だけで CSRF トークンは要らない。
 */
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$base_path = str_replace('\\', '/', dirname(__DIR__, 3)) . '/';
require_once $base_path . 'manager/includes/document.parser.class.inc.php';

$modx = new DocumentParser();
$modx->getSettings();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!sessionv('mgrValidated')) {
    http_response_code(403);
    echo '{}';
    exit;
}

require_once __DIR__ . '/src/bootstrap.php';

$language = new \TinyMCE7\Support\Language();

echo json_encode(
    [
        'names' => (new \TinyMCE7\Editor\TagNameRepository())->all(),
        'labels' => ['disabled' => $language->translate('tinymce7_tag_disabled', ' (disabled)')],
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
