<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

/**
 * CSRF Token Management Functions
 *
 * Evolution CMS の管理画面における CSRF トークン方式の実装
 * セッションベースで複数トークンの同時管理を行い、マルチタブに対応
 */

// トークン管理の定数
define('CSRF_TOKEN_MAX', 10);      // 最大トークン数
define('CSRF_TOKEN_KEEP', 5);      // 上限超過時に保持する数

/**
 * 新しいCSRFトークンを生成してセッションに保存
 *
 * @return string 生成されたトークン
 */
function generateCsrfToken()
{
    $tokens = sessionv('csrf_tokens', []);

    // トークン数の上限チェックと整理
    if (count($tokens) >= CSRF_TOKEN_MAX) {
        // 古いトークンから削除（FIFOキュー）
        $tokens = array_slice($tokens, -CSRF_TOKEN_KEEP, null, true);
    }

    // 新しいトークンを生成
    $token = bin2hex(random_bytes(32));
    $tokens[$token] = true;

    sessionv('*csrf_tokens', $tokens);

    return $token;
}

/**
 * 現在の有効なCSRFトークンを取得（最新のもの）
 * トークンが存在しない場合は新規生成
 *
 * @return string CSRFトークン
 */
function getCurrentCsrfToken()
{
    $tokens = sessionv('csrf_tokens', []);

    if (empty($tokens)) {
        return generateCsrfToken();
    }

    $keys = array_keys($tokens);
    return end($keys);
}

/**
 * リクエストからCSRFトークンを取得
 * POST の値、X-CSRF-Token ヘッダー、URL の csrf_token（フルページ遷移のリンク用）の順に探す
 *
 * @return string トークン（存在しない場合は空文字列）
 */
function getRequestCsrfToken()
{
    return postv('csrf_token') ?: serverv('HTTP_X_CSRF_TOKEN', '') ?: getv('csrf_token', '');
}

/**
 * CSRFトークンを検証
 * マルチタブ対応のため、トークンは削除せず再利用可能
 *
 * @param string|null $token 検証するトークン（nullの場合はリクエストから取得）
 * @return bool 検証成功時はtrue、失敗時はfalse
 */
function validateCsrfToken($token = null)
{
    if ($token === null) {
        $token = getRequestCsrfToken();
    }

    $tokens = sessionv('csrf_tokens', []);

    // トークンが存在するかチェック（削除はしない）
    // 配列などで渡された値はキーとして引けないため、文字列以外は不一致とする
    return is_string($token) && $token !== '' && isset($tokens[$token]);
}

/**
 * CSRF検証失敗時の情報を収集
 *
 * @return array 検証失敗情報の連想配列
 */
function getCsrfValidationFailureInfo()
{
    return [
        'action' => manager()->action ?? 'none',
        'method' => serverv('REQUEST_METHOD'),
        // トークンは秘密値のため、値ではなく有無だけを記録・表示する
        'token' => getRequestCsrfToken() ? 'present' : 'none',
        'valid_tokens_count' => count(sessionv('csrf_tokens', [])),
        // URL にトークンが載ることがあるため、値を伏せる
        'uri' => mask_csrf_token_in_uri(serverv('REQUEST_URI', '')),
        'ip' => serverv('REMOTE_ADDR'),
        'user_agent' => serverv('HTTP_USER_AGENT'),
        'user_id' => evo()->getLoginUserID() ?? 'not logged in',
    ];
}

/**
 * CSRFトークン検証を実行し、失敗時にエラーを出力して終了
 * プロセッサーで使用する標準的なチェック関数
 *
 * @param string|null $token 検証するトークン（nullの場合は自動取得）
 * @return void 検証失敗時はスクリプトを終了
 */
function checkCsrfToken($token = null)
{
    if (validateCsrfToken($token)) {
        return;
    }

    // 検証失敗情報を収集
    $info = getCsrfValidationFailureInfo();

    // ログメッセージの構築
    $logMsg = 'CSRF token validation failed';
    foreach ($info as $key => $value) {
        $logMsg .= " | {$key}: {$value}";
    }

    // Evolution CMSのシステムログに記録（標準のログ機能を使用）
    if (evo() && is_numeric($info['user_id'])) {
        evo()->logEvent(0, 3, $logMsg, 'CSRF Token Validation Failed');
    }

    // ユーザー向けエラーメッセージ
    $errorMsg = 'Invalid CSRF token. Please refresh the page and try again.';

    // デバッグモード時は詳細情報を追加
    if (evo()->config('debug', 0)) {
        $errorMsg .= "\n\nDebug Info:";
        foreach ($info as $key => $value) {
            $errorMsg .= "\n- {$key}: {$value}";
        }
    }

    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=UTF-8');
    exit($errorMsg);
}

/**
 * すべてのCSRFトークンをクリア
 * ログアウト時などに使用
 *
 * @return void
 */
function clearCsrfTokens()
{
    sessionv('*csrf_tokens', null);
}

/**
 * CSRFトークン用のhidden inputフィールドを生成
 *
 * @param string|null $token 使用するトークン（nullの場合は現在のトークンを使用）
 * @return string HTMLのinputタグ
 */
function csrfTokenField($token = null)
{
    if ($token === null) {
        $token = getCurrentCsrfToken();
    }
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * URLにCSRFトークンを付ける
 * 非推奨。状態を変える操作（csrfProtectedActions()）は GET では受け付けない（405）ため、
 * リンク・ボタンには data-evo-post 属性を付け、JS からは EvoCsrf.post() で送る。外部の拡張のために残している
 *
 * @param string $url 付け先のURL
 * @return string csrf_token を付けたURL
 */
function csrfUrl($url)
{
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . 'csrf_token=' . rawurlencode(getCurrentCsrfToken());
}

/**
 * 状態（DB・ファイル・セッション）を変える操作の一覧
 * 値が true のアクションは常に対象。配列のときは、そのパラメータが列挙した値のときだけ対象（表示と更新を兼ねる画面のサブ操作）
 * ここに載せた操作は POST でしか受け付けない（GET・HEAD などは accesscontrol.inc.php で 405）。
 * POST はこの一覧によらずトークンを検証する
 *
 * @return array<int, true|array<string, string[]>>
 */
function csrfProtectedActions()
{
    // モジュールの実行（a=112）は、更新するかどうかがモジュールの実装次第で、
    // モジュール内のリンクがすべて影響を受けるため対象にしない。
    // ツリーの開閉状態やメッセージの既読など、表示のためのセッション・軽微な状態の書き換えも対象にしない
    return [
        // リソース
        6   => true, // 削除
        52  => true, // 移動
        61  => true, // 公開
        62  => true, // 非公開
        63  => true, // 復元
        64  => true, // ごみ箱から完全に削除
        94  => true, // 複製
        129 => true, // 下書きの公開
        134 => true, // 下書きの公開予約の取り消し
        // 要素
        21  => true, // テンプレートの削除
        96  => true, // テンプレートの複製
        25  => true, // スニペットの削除
        98  => true, // スニペットの複製
        80  => true, // チャンクの削除
        97  => true, // チャンクの複製
        104 => true, // プラグインの削除
        105 => true, // プラグインの複製
        110 => true, // モジュールの削除
        111 => true, // モジュールの複製
        303 => true, // テンプレート変数の削除
        304 => true, // テンプレート変数の複製
        501 => true, // カテゴリの削除
        113 => ['op' => ['add', 'del']], // モジュールのリソース設定
        // ユーザー・権限
        33  => true, // ユーザーの削除
        90  => true, // Web ユーザーの削除
        37  => true, // ロールの削除
        41  => true, // アクセス権（ユーザーグループ・リソースグループ）の操作
        92  => true, // Web アクセス権の操作
        8   => true, // ログアウト（セッションを破棄する）
        // メッセージ
        65  => true, // 削除
        66  => true, // 送信
        // 管理・ツール
        26  => true, // サイトのリフレッシュ（公開状態の更新とキャッシュの削除）
        54  => true, // テーブルの最適化・ログの truncate
        55  => true, // 管理画面のログを空にする
        67  => true, // ロックの解除
        307 => true, // スナップショットの作成
        31  => ['mode' => ['delete', 'deletefolder', 'newfolder', 'newfile', 'unzip']], // ファイルの操作
        // 保存系（POST で呼ぶもの。GET で到達しても実行させない）
        5   => true, // リソース
        128 => true, // 下書きの保存
        130 => true, // 下書きの削除
        20  => true, // テンプレート
        24  => true, // スニペット
        79  => true, // チャンク
        103 => true, // プラグイン
        109 => true, // モジュール
        302 => true, // テンプレート変数
        32  => true, // ユーザー
        89  => true, // Web ユーザー
        36  => true, // ロール
        34  => true, // パスワード
        30  => true, // システム設定
        305 => true, // バックアップからの復元
    ];
}

/**
 * 現在のリクエストが、CSRFトークンを要求する状態変更の操作かを判定する
 *
 * @param int|string $action アクション番号
 * @return bool
 */
function isCsrfProtectedAction($action)
{
    $list = csrfProtectedActions();
    $action = (int) $action;
    if (!isset($list[$action])) {
        return false;
    }
    if ($list[$action] === true) {
        return true;
    }
    foreach ($list[$action] as $param => $values) {
        $value = anyv($param);
        if (is_string($value) && in_array($value, $values, true)) {
            return true;
        }
    }
    return false;
}

/**
 * CSRFトークンのメタタグを生成（Ajax用）
 *
 * @param string|null $token 使用するトークン（nullの場合は現在のトークンを使用）
 * @return string HTMLのmetaタグ
 */
function csrfTokenMeta($token = null)
{
    if ($token === null) {
        $token = getCurrentCsrfToken();
    }
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
