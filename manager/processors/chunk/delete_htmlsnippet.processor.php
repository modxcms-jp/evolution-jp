<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id=intval($_GET['id']);

// invoke OnBeforeChunkFormDelete event
$tmp = array("id"	=> $id);
$modx->invokeEvent("OnBeforeChunkFormDelete",$tmp);

//ok, delete the chunk.
$rs = $modx->db->delete('[+prefix+]site_htmlsnippets',"id='{$id}'");
if(!$rs) {
	exit('Something went wrong while trying to delete the htmlsnippet...');
} else {
	// invoke OnChunkFormDelete event
    $tmp = array("id"	=> $id);
	$modx->invokeEvent("OnChunkFormDelete",$tmp);

	// empty cache
	$modx->clearCache(); // first empty the cache
	// finished emptying cache - redirect
	header("Location: index.php?a=76");
}
