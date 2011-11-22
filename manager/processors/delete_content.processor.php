<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

if(!$modx->hasPermission('delete_document'))
{
	$e->setError(3);
	$e->dumpError();
}

$tbl_site_content = $modx->getFullTableName('site_content');

// check the document doesn't have any children
$id=intval($_GET['id']);
$deltime = time();
$children = array();

// check permissions on the document
include_once "./processors/user_documents_permissions.class.php";
$udperms = new udperms();
$udperms->user = $modx->getLoginUserID();
$udperms->document = $id;
$udperms->role = $_SESSION['mgrRole'];

if(!$udperms->checkPermissions())
{
	include "header.inc.php";
	?><div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
	<div class="sectionBody">
	<p><?php echo $_lang['access_permission_denied']; ?></p>
	<?php
	include("footer.inc.php");
	exit;
}

getChildren($id);

// invoke OnBeforeDocFormDelete event
$params['id']       = $id;
$params['children'] = $children;
$modx->invokeEvent("OnBeforeDocFormDelete",$params);

if(count($children)>0)
{
	$docs_to_delete = implode(' ,', $children);
	$deletedby = $modx->getLoginUserID();
	$sql = "UPDATE {$tbl_site_content} SET deleted=1, deletedby='{$deletedby}', deletedon='{$deltime}' WHERE id IN({$docs_to_delete})";
	$rs = @mysql_query($sql);
	if(!$rs)
	{
		echo "Something went wrong while trying to set the document's children to deleted status...";
		exit;
	}
}

if($site_start==$id)
{
	echo "Document is 'Site start' and cannot be deleted!";
	exit;
}

if($site_unavailable_page==$id)
{
	echo "Document is used as the 'Site unavailable page' and cannot be deleted!";
	exit;
}

//ok, 'delete' the document.
$sql = "UPDATE {$tbl_site_content} SET deleted=1, deletedby=".$modx->getLoginUserID().", deletedon=$deltime WHERE id={$id}";
$rs = mysql_query($sql);
if(!$rs)
{
	echo "Something went wrong while trying to set the document to deleted status...";
	exit;
}
else
{
	// invoke OnDocFormDelete event
	$params['id']       = $id;
	$params['children'] = $children;
	$modx->invokeEvent("OnDocFormDelete",$params);

	// empty cache
	$modx->clearCache();
	$header="Location: index.php?r=1&a=7";
	header($header);
}

function getChildren($parent)
{
	global $modx,$children;

	$db->debug = true;
	
	$tbl_site_content = $modx->getFullTableName('site_content');

	$sql = "SELECT id FROM {$tbl_site_content} WHERE {$tbl_site_content}.parent='{$parent}' AND deleted='0'";
	$rs = mysql_query($sql);
	$limit = mysql_num_rows($rs);
	if($limit>0)
	{
		// the document has children documents, we'll need to delete those too
		for($i=0;$i<$limit;$i++)
		{
			$row=mysql_fetch_assoc($rs);
			if($row['id']==$modx->config['site_start'])
			{
				echo "The document you are trying to delete is a folder containing document {$row['id']}. This document is registered as the 'Site start' document, and cannot be deleted. Please assign another document as your 'Site start' document and try again.";
				exit;
			}
			if($row['id']==$modx->config['site_unavailable_page'])
			{
				echo "The document you are trying to delete is a folder containing document {$row['id']}. This document is registered as the 'Site unavailable page' document, and cannot be deleted. Please assign another document as your 'Site unavailable page' document and try again.";
				exit;
			}
			$children[] = $row['id'];
			getChildren($row['id']);
		}
	}
}
