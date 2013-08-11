<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('delete_plugin')) {
	$e->setError(3);
	$e->dumpError();
}

$id=$_GET['id'];

// delete the system field
$rs = $modx->db->delete("[+prefix+]system_settings","setting_name='{$id}'");

if(!$rs){
	echo "Something went wrong while trying to delete the plugin...";
	exit;
}
// empty cache
header('Location: index.php?a=131');
