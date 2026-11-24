<?php
// 304 を返すまでの処理を軽く保つため、ほかのファイル（ヘルパーなど）を読み込まずに書く

if (!isset($recent_update) || !$recent_update) {
    return;
}
if (!isset($conditional_get) || !$conditional_get) {
    return;
}
if ($_POST || defined('MODX_API_MODE')) {
    return;
}

if (isset($site_sessionname) && $site_sessionname) {
    session_name($site_sessionname);
    session_cache_limiter('');
    ini_set('session.gc_maxlifetime', (string)(3600 * 24 * 30)); // init::session_set_cookie_params()と同値
    session_start();
    if (isset($_SESSION['mgrValidated']) || isset($_SESSION['webValidated'])) {
        return;
    }
}

$etag = md5($recent_update);
$last_modified = gmdate('D, d M Y H:i:s T', $recent_update);

header(sprintf('ETag: "%s"', $etag));
header('Last-Modified: ' . $last_modified);

// 圧縮するモジュールは ETag に -gzip などを付け足し、弱い ETag の W/ や複数の ETag が届くこともあるので、含むかどうかで比べる
if (
    strpos((string) filter_input(INPUT_SERVER, 'HTTP_IF_NONE_MATCH'), $etag) === false
    &&
    filter_input(INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE') !== $last_modified
) {
    return;
}

header('HTTP', true, 304);
header('Content-Length: 0');
exit;
