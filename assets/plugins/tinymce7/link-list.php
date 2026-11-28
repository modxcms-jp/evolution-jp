<?php
/**
 * TinyMCE 7 の link_list 用に、サイト内の文書の木を JSON で返す（管理画面にログインしている利用者だけ）。
 * ?id= を付けると、その文書の公開側の URL を {"url": "..."} で返す（本文の [~id~] のリンクを開くとき。js/link-open.js）。
 * 一覧に出ない文書（無い・削除済み・権限なし）は 404 と {}。
 * 読み取りだけで状態を変えないので、ファイルピッカー（manager/media/browser/evo/api.php）の list と同じく、
 * セッションの確認だけで CSRF トークンは要らない。
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
    echo '[]';
    exit;
}

require_once __DIR__ . '/src/bootstrap.php';

$language = new \TinyMCE7\Support\Language();
$repository = new \TinyMCE7\Editor\LinkListRepository(
    $language->translate('tinymce7_link_list_truncated', '(Documents deeper than this are omitted. Write [~ID~] in the URL field.)')
);

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

if (getv('id') !== null) {
    $url = $repository->url(get_intvar('id'));
    if ($url === null) {
        http_response_code(404);
        echo '{}';
        exit;
    }
    echo json_encode(['url' => $url], $flags);
    exit;
}

echo json_encode(
    $repository->tree(),
    $flags
);
