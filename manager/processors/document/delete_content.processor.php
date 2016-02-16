<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('delete_document'))
{
	$e->setError(3);
	$e->dumpError();
}

// check the document doesn't have any children
$id=intval($_GET['id']);

// check permissions on the document
if($id==$modx->config['site_start'])
	$warning = "Document is 'Site start' and cannot be deleted!";
elseif($id==$modx->config['error_page'])
	$warning = "Document is 'Error page' and cannot be deleted!";
elseif($id==$modx->config['site_unavailable_page'])
	$warning = "Document is used as the 'Site unavailable page' and cannot be deleted!";
elseif(!$modx->checkPermissions($id))
	$warning = $_lang['access_permissions'];
else
	$linked = check_linked($id);

if(isset($linked) && $linked!==false)
	$warning = 'Linked by ' . 'ID:' . join(', ID:', $linked);

if(isset($warning))
{
	$modx->webAlertAndQuit($warning,'javascript:history.back();');
}

$children = array();
getChildren($id);

// invoke OnBeforeDocFormDelete event
$params['id']       = $id;
$params['children'] = $children;
$params['enableProcess'] = true;
$modx->invokeEvent("OnBeforeDocFormDelete",$params);
if( $params['enableProcess'] == false ){
	$modx->webAlertAndQuit("The deletion process was interrupted by plugin.");
}

$field = array();
$field['deleted']   = '1';
$field['deletedby'] = $modx->getLoginUserID();
$field['deletedon'] = time();
if(0 < count($children)) {
	$docs_to_delete   = implode(' ,', $children);
	$rs = $modx->db->update($field,'[+prefix+]site_content',"id IN({$docs_to_delete})");
	if(!$rs) {
		exit("Something went wrong while trying to set the document's children to deleted status...");
	}
}

//ok, 'delete' the document.
$rs = $modx->db->update($field,'[+prefix+]site_content',"id='{$id}'");
if(!$rs) {
	exit('Something went wrong while trying to set the document to deleted status...');
} else {
	// invoke OnDocFormDelete event
	$params['id']       = $id;
	$params['children'] = $children; //array()
	$modx->invokeEvent("OnDocFormDelete",$params);

	// empty cache
	$modx->clearCache();
	$pid = $modx->db->getValue($modx->db->select('parent','[+prefix+]site_content',"id='{$id}'"));
	$page = (isset($_GET['page'])) ? "&page={$_GET['page']}" : '';
	if($pid!=='0') $header="Location: index.php?r=1&a=120&id={$pid}";
	else           $header="Location: index.php?a=2&r=1";
	header($header);
}



function getChildren($parent)
{
	global $modx,$children;

	$rs = $modx->db->select('id', '[+prefix+]site_content', "parent='{$parent}' AND deleted='0'");
	if(!$modx->db->getRecordCount($rs)) return;
	// the document has children documents, we'll need to delete those too
	while($row=$modx->db->getRow($rs))
	{
		if($row['id']==$modx->config['site_start']) {
			exit("The document you are trying to delete is a folder containing document {$row['id']}. This document is registered as the 'Site start' document, and cannot be deleted. Please assign another document as your 'Site start' document and try again.");
		}
		if($row['id']==$modx->config['site_unavailable_page']) {
			exit("The document you are trying to delete is a folder containing document {$row['id']}. This document is registered as the 'Site unavailable page' document, and cannot be deleted. Please assign another document as your 'Site unavailable page' document and try again.");
		}
		$children[] = $row['id'];
		getChildren($row['id']);
	}
}

function check_linked($id)
{
	global $modx;
	
	$rs = $modx->db->select('id','[+prefix+]site_content',"content LIKE '%[~{$id}~]%' AND deleted='0'");
	if(!$modx->db->getRecordCount($rs)) return false;
	while($row = $modx->db->getRow($rs)) {
		$result[] = $row['id'];
	}
	return $result;
}
