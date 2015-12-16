<?php
/**
 * session_keepalive.php
 *
 * This page is requested once in awhile to keep the session alive and kicking.
 */
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', 'true');
$self = 'manager/session_keepalive.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
include_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();

// Keep it alive
header('Content-type: application/json');
if(isset($_GET['tok']) && $_GET['tok'] == md5(session_id()))
{
	$modx->updatePublishStatus();
	$uid = $_SESSION['mgrInternalKey'];
	$timestamp = time();
	$modx->db->update("lasthit={$timestamp}", '[+prefix+]active_users', "internalKey='{$uid}'");
	echo '{"status":"ok"}';
}
else echo '{"status":null}';
