<?php
if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}
$mstart = memory_get_usage();
include_once __DIR__ . '/define-path.php';

if (defined('IN_MANAGER_MODE')) {
    return;
}

if (($_GET['get'] ?? '') === 'captcha') {
    include_once MODX_BASE_PATH . 'manager/media/captcha/veriword.php';
    return;
}

// このリクエストで新しく作ったセッションに、MODX が自分で入れる値（evo_sid_hash）のほかに何も書かれていなければ、
// セッションの cookie を送らず、ファイルも消す。cookie とファイルは、両方残すか両方消すかのどちらかにする
// （cookie だけが残ると、初めて来たブラウザの同時のリクエストが、それぞれ別のセッションを作ってしまう）。
// cookie を持ってきた訪問者のセッションは、既存の訪問者のものなので消さない。
// ヘッダーを送ったあとは何もしない（送った時点で中身があり、cookie を出したセッションなので残す）。
// 呼ぶ所: キャッシュを返す経路の session_write_close() の前、ヘッダーを送る直前（header_register_callback）、
// 本文の無い応答（リダイレクト・304）のための shutdown。
if (!function_exists('evo_drop_empty_front_session')) {
    function evo_drop_empty_front_session()
    {
        if (headers_sent() || session_status() !== PHP_SESSION_ACTIVE || isset($_COOKIE[session_name()])) {
            return;
        }
        if (array_diff_key($_SESSION, ['evo_sid_hash' => true])) {
            return;
        }
        session_destroy();
        $prefix = session_name() . '=';
        $cookies = array_filter(headers_list(), static function ($h) {
            return stripos($h, 'Set-Cookie:') === 0;
        });
        header_remove('Set-Cookie');
        foreach ($cookies as $h) {
            if (stripos(ltrim(substr($h, 11)), $prefix) !== 0) {
                header($h, false);
            }
        }
    }
}
$evoDropEmptyFrontSession = static function () {
    if (!defined('MODX_API_MODE') || !MODX_API_MODE) {
        evo_drop_empty_front_session();
    }
};
header_register_callback($evoDropEmptyFrontSession);
// 本文の無い応答は、ヘッダーをリクエストの終わりに送る。メインの処理で登録された shutdown 関数（postProcess など）のあとに回す
register_shutdown_function(static function () use ($evoDropEmptyFrontSession) {
    register_shutdown_function($evoDropEmptyFrontSession);
});

if (is_file('vendor/autoload.php')) {
    require 'vendor/autoload.php';
}

$cache_type = 1;
$cacheRefreshTime = 0;
$site_sessionname = '';
$site_status = '1';
$cache_version = '';
if (is_file(MODX_CACHE_PATH . 'basicConfig.php')) {
    include_once MODX_CACHE_PATH . 'basicConfig.php';
}

// これはページのキャッシュの互換のための判定で、アップデート待ちの判定ではない。
// 未適用のマイグレーションがあっても、アップデートの前に作られたページが返ることがある（判定は下の update_guard.php）
// コードとキャッシュの版が違うとき（ファイルを入れ替えてアップデートする前など）は早い返しをせず、通常の処理に回す
$code_version = (static function () {
    $versionFile = MODX_BASE_PATH . 'manager/includes/version.inc.php';
    if (!is_file($versionFile)) {
        return '';
    }
    include $versionFile;
    return isset($modx_version) && is_string($modx_version) ? $modx_version : '';
})();

if (isset($conditional_get) && $conditional_get == 1) {
    include_once(MODX_BASE_PATH . "manager/includes/conditional_get.inc.php");
}
if (!defined('MODX_API_MODE')
    && $cache_type == 2
    && $site_status != 0
    && $code_version !== ''
    && $cache_version === $code_version
    && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && count($_POST) < 1
    && (time() < $cacheRefreshTime || $cacheRefreshTime == 0)) {
    // conditional_get.inc.php が 304 を返さずに戻ったときは、セッションが始まっている
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($site_sessionname);
        session_cache_limiter('');
        if (isset($site_cookie_params)) {
            // init::session_set_cookie_params() と、secure 以外は同じ値（path は今までどおり php.ini の既定）。secure は接続ごとに決まるので、ここで判定する
            $site_cookie_params['secure'] = strtolower((string)($_SERVER['HTTPS'] ?? '')) === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            ini_set('session.gc_maxlifetime', (string)$site_cookie_params['lifetime']);
            session_set_cookie_params($site_cookie_params);
        } else {
            // $site_cookie_params が無い古い basicConfig.php 用
            ini_set('session.gc_maxlifetime', (string)(3600 * 24 * 30));
        }
        session_start();
    }
    if (!isset($_SESSION['mgrValidated']) && !isset($_SESSION['webValidated'])) {
        $target = MODX_CACHE_PATH . 'pages/' . hash('crc32b', $_SERVER['REQUEST_URI'] ?? '') . '.pageCache.php';
        if (is_file($target)) {
            evo_drop_empty_front_session();
            session_write_close();
            $handle = fopen($target, 'rb');
            $output = fread($handle, filesize($target));
            unset($handle);
            [$head, $output] = explode('<!--__MODxCacheSpliter__-->', $output, 2);
            if (strpos($head, '"text/html";') === false) {
                $type = unserialize($head);
                header('Content-Type:' . $type . '; charset=utf-8');
            } else header('Content-Type:text/html; charset=utf-8');
            $msize = memory_get_peak_usage() - $mstart;
            $units = ['B', 'KB', 'MB'];
            $pos = 0;
            while ($msize >= 1024) {
                $msize /= 1024;
                $pos++;
            }
            $msize = round($msize, 2) . ' ' . $units[$pos];
            $totalTime = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
            $totalTime = sprintf('%2.4f s', $totalTime);
            $incs = get_included_files();
            $r = ['[^q^]' => '0', '[^qt^]' => '0s', '[^p^]' => $totalTime, '[^t^]' => $totalTime, '[^s^]' => 'bypass_cache', '[^m^]' => $msize, '[^f^]' => count($incs)];
            $output = strtr($output, $r);
            if (is_file(MODX_BASE_PATH . 'autoload.php'))
                $loaded_autoload = include MODX_BASE_PATH . 'autoload.php';
            if ($output !== false) {
                echo $output;
                exit;
            }
        }
    }
}
if (!isset($loaded_autoload) && is_file(MODX_BASE_PATH . 'autoload.php')) {
    include_once MODX_BASE_PATH . 'autoload.php';
}

if (is_file(MODX_BASE_PATH . '.env')) {
    require_once MODX_BASE_PATH . 'manager/includes/dotenv-loader.php';
    $dotenv = new Dotenv(MODX_BASE_PATH . '.env');
    $dotenv->load();
}
// initiate a new document parser
include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$evo = new DocumentParser;
$modx =& $evo;

$evo->mstart = $mstart;
$evo->cacheRefreshTime = $cacheRefreshTime;
if (isset($error_reporting)) {
    $evo->error_reporting = $error_reporting;
}

if (evo()->isFrontend()) {
    include_once MODX_MANAGER_PATH . 'includes/update_guard.php';
    evo_guard_update_pending($modx);
}

require_once MODX_BASE_PATH . 'manager/includes/frontend_error_log.inc.php';

// execute the parser if index.php was not included
if (defined('IN_PARSER_MODE') && IN_PARSER_MODE === 'true') {
    try {
        $result = $evo->executeParser();
        echo $result;
    } catch (Throwable $exception) {
        frontend_log_uncaught_throwable($exception);
        frontend_render_uncaught_error();
    }
}
