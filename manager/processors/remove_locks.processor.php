<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('remove_locks')) {
	$e->setError(3);
	$e->dumpError();
}

// Remove locks
$sql = 'TRUNCATE ' . $modx->getFullTableName('active_users');
$rs = $modx->db->query($sql);
if(!$rs) {
	echo "Something went wrong while trying to remove the locks!";
	exit;
}
	header("Location: index.php?a=7");
