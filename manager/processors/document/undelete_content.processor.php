<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_document'))
{
	$e->setError(3);
	$e->dumpError();
}

$id = intval($_REQUEST['id']);

// check permissions on the document
if(!$modx->checkPermissions($id)) disp_access_permission_denied();

// get the timestamp on which the document was deleted.
$rs = $modx->db->select('deletedon', '[+prefix+]site_content', "id='{$id}' AND deleted=1");
if($modx->db->getRecordCount($rs)!=1)
	exit("Couldn't find document to determine it's date of deletion!");
else
	$deltime = $modx->db->getValue($rs);

$children = array();
getChildren($id);

// invoke OnBeforeDocFormUnDelete event
$params['id']       = $id;
$params['children'] = $children;
$params['enableProcess'] = true;
$modx->invokeEvent("OnBeforeDocFormUnDelete",$params);
if( $params['enableProcess'] == false ){
	$modx->webAlertAndQuit("The undeletion process was interrupted by plugin.");
}

$field = array();
$field['deleted']   = '0';
$field['deletedby'] = '0';
$field['deletedon'] = '0';

if(0 < count($children))
{
	$docs_to_undelete = implode(' ,', $children);
	$rs = $modx->db->update($field,'[+prefix+]site_content',"id IN({$docs_to_undelete})");
	if(!$rs) exit("Something went wrong while trying to set the document's children to undeleted status...");
}
//'undelete' the document.
$rs = $modx->db->update($field,'[+prefix+]site_content',"id='{$id}'");
if(!$rs) exit("Something went wrong while trying to set the document to undeleted status...");
else {
	// invoke OnDocFormUnDelete event
	$params['id']       = $id;
	$params['children'] = $children;
	$modx->invokeEvent("OnDocFormUnDelete",$params);

	// empty cache
	$modx->clearCache();
	// finished emptying cache - redirect
	$pid = $modx->db->getValue($modx->db->select('parent','[+prefix+]site_content',"id='{$id}'"));
	$page = (isset($_GET['page'])) ? "&page={$_GET['page']}" : '';
	if($pid!=='0') $header="Location: index.php?r=1&a=120&id={$pid}{$page}";
	else           $header="Location: index.php?a=2&r=1";
	header($header);
}



function getChildren($parent)
{
	global $children;
	global $deltime,$modx;
	
	$rs = $modx->db->select('id','[+prefix+]site_content',"parent={$parent} AND deleted=1 AND deletedon='{$deltime}'");
	if($modx->db->getRecordCount($rs)>0)
	{
		// the document has children documents, we'll need to delete those too
		while($row=$modx->db->getRow($rs))
		{
			$children[] = $row['id'];
			getChildren($row['id']);
		}
	}
}

function disp_access_permission_denied()
{
	global $_lang;
	include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
	?><div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
	<div class="sectionBody">
	<p><?php echo $_lang['access_permission_denied']; ?></p>
	<?php
	include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
	exit;
}
