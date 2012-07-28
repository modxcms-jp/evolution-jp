<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings')) {
	$e->setError(3);
	$e->dumpError();
}

$sql = 'TRUNCATE TABLE ' . $modx->getFullTableName('manager_log');
$rs = $modx->db->query($sql);

header("Location: index.php?a=13");
