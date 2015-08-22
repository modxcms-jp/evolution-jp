<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings')) {
	$e->setError(3);
	$e->dumpError();
}

$rs = $modx->db->truncate('[+prefix+]manager_log');

header('Location: index.php?a=13');
