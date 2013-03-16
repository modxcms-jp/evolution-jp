<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_eventlog')) {
	$e->setError(3);
	$e->dumpError();
}

$id=intval($_GET['id']);

if(isset($_GET['cls']) && $_GET['cls']==1) $where = '';
else                                       $where = "id='{$id}'";

// delete event log
$rs = $modx->db->delete('[+prefix+]event_log',$where);
if(!$rs) {
	echo "Something went wrong while trying to delete the event log...";
	exit;
} else {
	header('Location: index.php?a=114');
}
