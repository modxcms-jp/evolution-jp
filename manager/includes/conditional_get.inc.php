<?php

if (!isset($recent_update) || !$recent_update) {
    return;
}
if (!isset($conditional_get) || !$conditional_get) {
    return;
}
if ($_POST || defined('MODX_API_MODE')) {
    return;
}

if(isset($site_sessionname) && $site_sessionname) {
    session_name($site_sessionname);
    session_cache_limiter('');
    session_start();
    if (isset($_SESSION['mgrValidated'])) {
        return;
    }
}

$etag = sprintf('"%s"', md5($recent_update));
$last_modified = gmdate('D, d M Y H:i:s T', $recent_update);

header('ETag: ' . $etag);
header('Last-Modified: ' . $last_modified);

if (
    filter_input( INPUT_SERVER, 'HTTP_IF_NONE_MATCH') !== $etag
    &&
    filter_input( INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE') !== $last_modified
) {
    return;
}

header('HTTP', true, 304);
header('Content-Length: 0');
exit;
