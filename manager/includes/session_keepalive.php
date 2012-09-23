<?php
/**
 * session_keepalive.php
 *
 * This page is requested once in awhile to keep the session alive and kicking.
 */
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);
$core_path = str_replace('\\','/',realpath('../../')) . '/manager/includes/';
include_once("{$core_path}config.inc.php");
startCMSSession();
include_once("{$core_path}document.parser.class.inc.php");
$modx = new DocumentParser;
$modx->db->connect();

// Keep it alive
if(isset($_GET['tok']) && $_GET['tok'] == md5(session_id()))
{
	$uid = $_SESSION['mgrInternalKey'];
	$tbl_active_users = $modx->getFullTableName('active_users');
	$timestamp = time();
	$modx->db->update("lasthit={$timestamp}", $tbl_active_users, "internalKey='{$uid}'");
	echo '{"status":"ok"}';
}
else echo '{"status":null}';
