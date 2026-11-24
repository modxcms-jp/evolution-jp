<?php

if (!isset($recent_update) || !$recent_update) {
    return;
}
if (!isset($conditional_get) || !$conditional_get) {
    return;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || $_POST || defined('MODX_API_MODE')) {
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

// 弱い比較（RFC 9110 8.8.3.2）で比べる。値は一覧で来ることがあり、
// 圧縮するモジュール（mod_deflate など）は ETag の末尾に -gzip などを付け足す
$if_none_match = (string) filter_input(INPUT_SERVER, 'HTTP_IF_NONE_MATCH');
$etag_matched = trim($if_none_match) === '*' || preg_match(
    '~(?:^|,)\s*(?:W/)?"' . $etag . '(?:-(?:gzip|br|zstd|deflate))?"\s*(?:,|$)~',
    $if_none_match
);

if (!$etag_matched && filter_input(INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE') !== $last_modified) {
    return;
}

header('HTTP', true, 304);
header('Content-Length: 0');
exit;
