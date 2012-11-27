<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id=intval($_GET['id']);
$tbl_site_snippets = $modx->getFullTableName('site_snippets');

// invoke OnBeforeSnipFormDelete event
$modx->invokeEvent('OnBeforeSnipFormDelete',
						array(
							'id' => $id
						));

//ok, delete the snippet.
$rs = $modx->db->delete($tbl_site_snippets,"id='{$id}'");
if(!$rs)
{
	echo "Something went wrong while trying to delete the snippet...";
	exit;
}
else
{
	// invoke OnSnipFormDelete event
	$modx->invokeEvent('OnSnipFormDelete',
							array(
								"id"	=> $id
							));

	// empty cache
	$modx->clearCache();

	header('Location: index.php?a=76');
}
