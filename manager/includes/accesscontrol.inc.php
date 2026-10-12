<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

if (!serverv('HTTP_ACCEPT_LANGUAGE')) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

if (sessionv('mgrValidated') && sessionv('usertype') !== 'manager') {
    @session_destroy();
}

// andrazk 20070416 - if session started before install and was not destroyed yet
if (isset($lastInstallTime) && sessionv('modx.session.created.time') && sessionv('mgrValidated')) {
    if (
        (sessionv('modx.session.created.time', 0) < $lastInstallTime)
        && sessionv('REQUEST_METHOD') !== 'POST'
    ) {
        if (cookiev(session_name())) {
            session_unset();
            @session_destroy();
        }
        header('HTTP/1.0 307 Redirect');
        header('Location: ' . MODX_MANAGER_URL . 'index.php?installGoingOn=2');
    }
}

$theme_path = MODX_MANAGER_PATH . sprintf('media/style/%s/', evo()->config('manager_theme'));
$touch_path = MODX_CACHE_PATH . 'touch.siteCache.idx.php';
if (!sessionv('mgrValidated')) {
    // AJAXシェルからの要求にはログイン要求を示すヘッダーを返し、クライアント側でフルリロードさせる
    if (isEvoPaneRequest()) {
        header('X-Evo-Login: required');
    }
    include __DIR__ . '/accesscontrol-not-mgr.inc.php';
    exit;
}

// log the user action
sessionv('*ip', real_ip());

// CSRF トークンの検証
// - POST はアクションによらず全て検証する（表示系アクションでもフォーム POST が発生するため）。
//   ログインの POST はこの地点（ログイン済みの判定より後）に来ないので、例外は設けない
// - GET・HEAD なども、状態を変える操作（csrfProtectedActions() の一覧）なら検証する。
//   一覧に載せ漏れがあっても POST の検証は残るので、POST の守りは後退しない
if (is_post() || isCsrfProtectedAction(manager()->action)) {
    // デバッグ: トークン情報を確認
    // トークンそのものは秘密値のため、値ではなく有無・件数だけを記録する
    // （ログの閲覧・転送・バックアップが別の信頼境界になりうるため）
    if (evo()->config('debug', 0)) {
        $debugInfo = [
            'action' => manager()->action,
            'method' => serverv('REQUEST_METHOD', ''),
            'csrf_token_in_post' => postv('csrf_token', '') !== '',
            'csrf_token_in_header' => serverv('HTTP_X_CSRF_TOKEN', '') !== '',
            'csrf_token_in_url' => getv('csrf_token', '') !== '',
            'session_token_count' => count((array) sessionv('csrf_tokens', [])),
        ];
        evo()->logEvent(0, 1, 'CSRF Debug: ' . print_r($debugInfo, true), 'CSRF Token Debug');
    }

    checkCsrfToken();
}

if (manager()->action != 1) {
    // DBAPI は値を呼び出し側で安全化する取り決め。数値は整数化し、SQL に入る値は組み立ての直前にまとめてエスケープする
    $fields = [
        'internalKey' => (int) evo()->getLoginUserID(),
        'username'    => (string) sessionv('mgrShortname'),
        'lasthit'     => (int) serverv('REQUEST_TIME', time()),
        'action'      => (int) manager()->action,
        'id'          => preg_match('@^[1-9][0-9]*$@', anyv('id', 0)) ? (int) anyv('id') : 0,
        'ip'          => (string) real_ip()
    ];

    $fields = db()->escape($fields);
    $sql = sprintf(
        "REPLACE INTO %s (%s) VALUES ('%s')",
        evo()->getFullTableName('active_users'),
        implode(',', array_keys($fields)),
        implode("','", array_values($fields))
    );
    if (!db()->query($sql)) {
        echo "error replacing into active users! SQL: " . $sql . "\n" . db()->getLastError();
        exit;
    }
    sessionv('*mgrDocgroups', manager()->getMgrDocgroups(evo()->getLoginUserID()));
}

clearstatcache(true, $touch_path);
if ($touch_path && is_file($touch_path)) {
    unlink($touch_path);
}

function loadLoginTpl($html) {
    if (!empty($html)) {
        return $html;
    }

    $style_path = MODX_MANAGER_PATH . 'media/style/';
    if (empty(evo()->config('manager_login_tpl'))) {
        if (is_file($style_path . evo()->config('manager_theme') . '/login.tpl')) {
            return file_get_contents($style_path . evo()->config('manager_theme') . '/login.tpl');
        }
        return file_get_contents($style_path . 'common/login.tpl');
    }

    $target = evo()->config('manager_login_tpl');
    if (strpos($target, '@') === 0) {
        $result = evo()->atBind($target);
        if($result) {
            return $result;
        }
    }

    $chunk = evo()->getChunk($target);
    if (!empty($chunk)) {
        return $chunk;
    }
    if (is_file(MODX_BASE_PATH . $target)) {
        return file_get_contents(MODX_BASE_PATH . $target);
    }
    return file_get_contents($style_path . 'common/login.tpl');
}

function installGoingOn()
{
    if (checkInstallProc()) {
        return 1;
    }
    if (getv('installGoingOn')) {
        return getv('installGoingOn');
    }
    return false;
}

function checkInstallProc()
{
    $instcheck_path = MODX_CACHE_PATH . 'installProc.inc.php';
    if (!is_file($instcheck_path)) {
        return false;
    }

    global $installStartTime;
    include_once($instcheck_path);
    if (!isset($installStartTime)) {
        return false;
    }

    if ((serverv('REQUEST_TIME', time()) - $installStartTime) > 5 * 60) {
        unlink($instcheck_path);
        return false;
    }

    if (is_post()) {
        return false;
    }

    if (cookiev(session_name())) {
        session_unset();
        @session_destroy();
    }
    return true;
}
