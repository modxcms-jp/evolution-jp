<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('access_permissions')) {
	$e->setError(3);
	$e->dumpError();
}

// access group processor.
// figure out what the user wants to do...

// Get table names (alphabetical)
$tbl_document_groups     = $modx->getFullTableName('document_groups');
$tbl_documentgroup_names = $modx->getFullTableName('documentgroup_names');
$tbl_member_groups       = $modx->getFullTableName('member_groups');
$tbl_membergroup_access  = $modx->getFullTableName('membergroup_access');
$tbl_membergroup_names   = $modx->getFullTableName('membergroup_names');

$updategroupaccess = false;
$operation = $_REQUEST['operation'];

switch ($operation)
{
	case "add_user_group" :
		$newgroup = $_REQUEST['newusergroup'];
		if(empty($newgroup)) {
			echo "no group name specified";
			exit;
		} else {
			$f['name'] = $modx->db->escape($newgroup);
			$id = $modx->db->insert_ignore($f,$tbl_membergroup_names);
			if(!$id) {
				echo "Failed to insert new group. Possible duplicate group name?";
				exit;
			}
			// invoke OnManagerCreateGroup event
			$modx->invokeEvent('OnManagerCreateGroup', array(
				'groupid'   => $id,
				'groupname' => $newgroup,
			));
		}
		break;
	case "add_document_group" :
		$newgroup = $_REQUEST['newdocgroup'];
		if(empty($newgroup)) {
			echo "no group name specified";
			exit;
		} else {
			$f['name'] = $modx->db->escape($newgroup);
			$id = $modx->db->insert_ignore($f,$tbl_documentgroup_names);
			if(!$id) {
				echo "Failed to insert new group. Possible duplicate group name?";
				exit;
			}
			
			// invoke OnCreateDocGroup event
			$modx->invokeEvent('OnCreateDocGroup', array(
				'groupid'   => $id,
				'groupname' => $newgroup,
			));
		}
		break;
	case "delete_user_group" :
		$updategroupaccess = true;
		$usergroup = intval($_REQUEST['usergroup']);
		if(empty($usergroup)) {
			echo "No user group name specified for deletion";
			exit;
		} else {
			if(!$modx->db->delete($tbl_membergroup_names,"id='{$usergroup}'")) {
				echo "Unable to delete group. SQL failed.";
				exit;
			}
			if(!$modx->db->delete($tbl_membergroup_access,"membergroup='{$usergroup}'")) {
				echo "Unable to delete group from access table. SQL failed.";
				exit;
			}
			if(!$modx->db->delete($tbl_member_groups,"user_group='{$usergroup}'")) {
				echo "Unable to delete user-group links. SQL failed.";
				exit;
			}
		}
		break;
	case "delete_document_group" :
		$group = intval($_REQUEST['documentgroup']);
		if(empty($group)) {
			echo "No document group name specified for deletion";
			exit;
		} else {
			if(!$modx->db->delete($tbl_documentgroup_names,"id='{$group}'")) {
				echo "Unable to delete group. SQL failed.";
				exit;
			}
			if(!$modx->db->delete($tbl_membergroup_access, "documentgroup='{$group}'")) {
				echo "Unable to delete group from access table. SQL failed.";
				exit;
			}
			if(!$modx->db->delete($tbl_document_groups, "document_group='{$group}'")) {
				echo "Unable to delete document-group links. SQL failed.";
				exit;
			}
		}
		break;
	case "rename_user_group" :
		$newgroupname = $modx->db->escape($_REQUEST['newgroupname']);
		if(empty($newgroupname)) {
			echo "no group name specified";
			exit;
		}
		$groupid = intval($_REQUEST['groupid']);
		if(empty($groupid)) {
			echo "No group id specified";
			exit;
		}
		$f['name'] = $newgroupname;
		if(!$modx->db->update($f,$tbl_membergroup_names,"id='{$groupid}'", '', '1')) {
			echo "Failed to update group name. Possible duplicate group name?";
			exit;
		}
		break;
	case "rename_document_group" :
		$newgroupname = $modx->db->escape($_REQUEST['newgroupname']);
		if(empty($newgroupname)) {
			echo "no group name specified";
			exit;
		}
		$groupid = intval($_REQUEST['groupid']);
		if(empty($groupid)) {
			echo "No group id specified";
			exit;
		}
		$f['name'] = $newgroupname;
		if(!$modx->db->update($f,$tbl_documentgroup_names,"id='{$groupid}'", '', '1')) {
			echo "Failed to update group name. Possible duplicate group name?";
			exit;
		}
		break;
	case "add_document_group_to_user_group" :
		$updategroupaccess = true;
		$usergroup = intval($_REQUEST['usergroup']);
		$docgroup = intval($_REQUEST['docgroup']);
		$where = "membergroup='{$usergroup}' AND documentgroup='{$docgroup}'";
		$limit = $modx->db->getValue($modx->db->select('count(*)',$tbl_membergroup_access,$where));
		if($limit<=0) {
			$f=array();
			$f['membergroup']   = $usergroup;
			$f['documentgroup'] = $docgroup;
			if(!$modx->db->insert_ignore($f,$tbl_membergroup_access)) {
				echo "Failed to link document group to user group";
				exit;
			}
		} else {
			echo "User that coupling already exists";
			exit;
			//alert user that coupling already exists?
		}
		break;
	case "remove_document_group_from_user_group" :
		$updategroupaccess = true;
		$coupling = intval($_REQUEST['coupling']);
		$sql = 'DELETE FROM '.$tbl_membergroup_access.' WHERE id='.$coupling;
		if(!$modx->db->delete($tbl_membergroup_access,"id='{$coupling}'")) {
			echo "Failed to remove document group from user group";
			exit;
		}
		break;
	default :
		echo "No operation set in request.";
		exit;
}

// secure manager documents - flag as private
if($updategroupaccess==true){
	include $base_path."manager/includes/secure_mgr_documents.inc.php";
	secureMgrDocument();

	// Update the private group column
	$sql = 'UPDATE '.$tbl_documentgroup_names.' AS dgn '.
	       'LEFT JOIN '.$tbl_membergroup_access.' AS mga ON mga.documentgroup = dgn.id '.
	       'SET dgn.private_memgroup = (mga.membergroup IS NOT NULL)';
	$rs = $modx->db->query($sql);
}

header("Location: index.php?a=40");
