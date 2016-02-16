<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_eventlog')) {
	$e->setError(3);
	$e->dumpError();
}

$id=intval($_GET['id']);

// delete event log
if(isset($_GET['cls']) && $_GET['cls']==1)
	$rs = $modx->db->truncate('[+prefix+]event_log');
else {
	$rs = $modx->db->delete('[+prefix+]event_log',"id='{$id}'");
	if($rs) {
		$rs = $modx->db->select('*', '[+prefix+]event_log');
		$total = $modx->db->getRecordCount($rs);
		if(empty($total)) $modx->db->truncate('[+prefix+]event_log');
	}
}

if(!$rs) {
	exit('Something went wrong while trying to delete the event log...');
} else {
	header('Location: index.php?a=114');
}
