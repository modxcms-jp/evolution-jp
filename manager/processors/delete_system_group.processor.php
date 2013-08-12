<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('delete_plugin')) {
	$e->setError(3);
	$e->dumpError();
}

$id=intval($_GET['id']);

// delete the system field
$rs = $modx->db->delete("[+prefix+]system_settings_group","id='{$id}'");

if(!$rs){
	echo "Something went wrong while trying to delete the plugin...";
	exit;
}else{
    //Переносим параметры из этой вкладке в первую
    $modx->db->update("id_group=1","[+prefix+]system_settings_fields","id_group=$id");
}
// empty cache
header('Location: index.php?a=131');
