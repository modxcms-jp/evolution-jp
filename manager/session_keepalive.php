<?php
/**
 * session_keepalive.php
 *
 * This page is requested once in awhile to keep the session alive and kicking.
 */
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', 'true');
$self = 'manager/session_keepalive.php';
$base_path = str_replace(array('\\', $self), array('/', ''), __FILE__);
include_once($base_path.'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

// Keep it alive
header('Content-type: application/json');

if($modx->input_get('tok') !== md5(session_id())) {
    exit('{"status":null}');
}

$modx->updatePublishStatus();
db()->update(
    "lasthit=" . request_time()
    , '[+prefix+]active_users'
    , "internalKey='" . $_SESSION['mgrInternalKey'] . "'"
);
echo '{"status":"ok"}';
