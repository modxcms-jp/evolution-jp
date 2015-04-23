<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!($modx->hasPermission('settings') && ($modx->hasPermission('logs')||$modx->hasPermission('bk_manager')))) {
	$e->setError(3);
	$e->dumpError();
}

if((!isset($_REQUEST['t']) || $_REQUEST['t']=='') && (!isset($_REQUEST['u']) || $_REQUEST['u']==''))
{
		$e->setError(10);
		$e->dumpError();
}

if (isset($_REQUEST['t']))     $sql = sprintf('OPTIMIZE TABLE %s.`%s`', $dbase, $modx->db->escape($_REQUEST['t']));
elseif (isset($_REQUEST['u'])) $sql = sprintf('TRUNCATE TABLE %s.`%s`', $dbase, $modx->db->escape($_REQUEST['u']));

if($sql) $rs = $modx->db->query($sql);

$mode = intval($_REQUEST['mode']);
header("Location: index.php?a={$mode}&s=4");
