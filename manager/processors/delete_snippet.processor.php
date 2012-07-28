<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id=intval($_GET['id']);

// invoke OnBeforeSnipFormDelete event
$modx->invokeEvent("OnBeforeSnipFormDelete",
						array(
							"id"	=> $id
						));

//ok, delete the snippet.
$sql = "DELETE FROM $dbase.`".$table_prefix."site_snippets` WHERE $dbase.`".$table_prefix."site_snippets`.id=".$id.";";
$rs = $modx->db->query($sql);
if(!$rs) {
	echo "Something went wrong while trying to delete the snippet...";
	exit;
} else {
		// invoke OnSnipFormDelete event
		$modx->invokeEvent("OnSnipFormDelete",
								array(
									"id"	=> $id
								));

		// empty cache
		$modx->clearCache(); // first empty the cache
		// finished emptying cache - redirect

	header("Location: index.php?a=76");
}
