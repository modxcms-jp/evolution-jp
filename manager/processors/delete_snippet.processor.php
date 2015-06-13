<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id=intval($_GET['id']);
$tbl_site_snippets = $modx->getFullTableName('site_snippets');

// invoke OnBeforeSnipFormDelete event
$tmp = array('id' => $id);
$modx->invokeEvent('OnBeforeSnipFormDelete',$tmp);

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
  $tmp = array("id"	=> $id);
	$modx->invokeEvent('OnSnipFormDelete',$tmp);

	// empty cache
	$modx->clearCache();

	header('Location: index.php?a=76');
}
