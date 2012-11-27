<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('delete_plugin')) {	
	$e->setError(3);
	$e->dumpError();	
}

$id=intval($_GET['id']);
$tbl_site_plugins       = $modx->getFullTableName('site_plugins');
$tbl_site_plugin_events = $modx->getFullTableName('site_plugin_events');

// invoke OnBeforePluginFormDelete event
$modx->invokeEvent("OnBeforePluginFormDelete",
						array(
							"id"	=> $id
						));

// delete the plugin.
$rs = $modx->db->delete($tbl_site_plugins,"id='{$id}'");
if(!$rs) {
	echo "Something went wrong while trying to delete the plugin...";
	exit;
} else {		
	// delete the plugin events.
	$rs = $modx->db->delete($tbl_site_plugin_events,"pluginid='{$id}'");
	if(!$rs) {
		echo "Something went wrong while trying to delete the plugin events...";
		exit;
	} else {		
		// invoke OnPluginFormDelete event
		$modx->invokeEvent("OnPluginFormDelete",
								array(
									"id"	=> $id
								));

		// empty cache
		$modx->clearCache(); // first empty the cache		
		// finished emptying cache - redirect
		header("Location: index.php?a=76");
	}
}
