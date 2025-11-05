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
 *
 * @return string トークン（存在しない場合は空文字列）
 */
function getRequestCsrfToken()
{
    return postv('csrf_token') ?: serverv('HTTP_X_CSRF_TOKEN', '');
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
    return !empty($token) && isset($tokens[$token]);
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
        'token' => getRequestCsrfToken() ?: 'none',
        'valid_tokens_count' => count(sessionv('csrf_tokens', [])),
        'uri' => serverv('REQUEST_URI'),
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

    // Evolution CMSのイベントログに記録（標準のログ機能を使用）
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
