<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('empty_trash')) {
	$e->setError(3);
	$e->dumpError();
}

if(isset($_REQUEST['id'])&&preg_match('@^[1-9][0-9]*$@',$_REQUEST['id']))
	$ids = array($_REQUEST['id']);
else
{
    $rs = $modx->db->select('id','[+prefix+]site_content','deleted=1');
    $ids = array();
    if($modx->db->getRecordCount($rs)>0)
    {
    	while($row=$modx->db->getRow($rs))
    	{
    		$ids[] = $row['id'];
    	}
    }
}

// invoke OnBeforeEmptyTrash event
$modx->event->vars['ids'] = & $ids;
$modx->invokeEvent('OnBeforeEmptyTrash',$modx->event->vars);

// remove the document groups link.
$tbl_document_groups = $modx->getFullTableName('document_groups');
$tbl_site_content = $modx->getFullTableName('site_content');
$sql = "DELETE {$tbl_document_groups}
		FROM {$tbl_document_groups}
		INNER JOIN {$tbl_site_content} ON {$tbl_site_content}.id = {$tbl_document_groups}.document
		WHERE {$tbl_site_content}.deleted=1";
$modx->db->query($sql);

// remove the TV content values.
$tbl_site_tmplvar_contentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
$sql = "DELETE {$tbl_site_tmplvar_contentvalues}
		FROM {$tbl_site_tmplvar_contentvalues}
		INNER JOIN {$tbl_site_content} ON {$tbl_site_content}.id = {$tbl_site_tmplvar_contentvalues}.contentid
		WHERE {$tbl_site_content}.deleted=1";
$modx->db->query($sql);

//'undelete' the document.
$rs = $modx->db->delete($tbl_site_content,'deleted=1');
if(!$rs) exit("Something went wrong while trying to remove deleted documents!");
else {
	// invoke OnEmptyTrash event
	$modx->invokeEvent('OnEmptyTrash',$modx->event->vars);
	$modx->event->vars = array();
	// empty cache
	$modx->clearCache(); // first empty the cache
	// finished emptying cache - redirect
	header("Location: index.php?r=1&a=7");
}
