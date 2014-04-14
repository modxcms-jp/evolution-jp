<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

/**
 *	Secure Manager Documents
 *	This script will mark manager documents as private
 *
 *	A document will be marked as private only if a manager user group 
 *	is assigned to the document group that the document belongs to.
 *
 */

function secureMgrDocument($docid='') {
	global $modx;
	
	if($docid>0) $where = "id='{$docid}'";
	else         $where = 'privatemgr=1';
	$modx->db->update(array('privatemgr'=>0), '[+prefix+]site_content', $where);
	
	$field = 'sc.id';
	$from  = '[+prefix+]site_content sc'
			.' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
			.' LEFT JOIN [+prefix+]membergroup_access mga ON mga.documentgroup = dg.document_group';
	if($docid>0) $where = "sc.id='{$docid}' AND mga.id > 0";
	else         $where = 'mga.id > 0';
	$rs = $modx->db->select($field,$from,$where);
	$ids = $modx->db->getColumn('id',$rs);
	if(count($ids)>0) {
		$ids = implode(',', $ids);
		$modx->db->update(array('privatemgr'=>1),'[+prefix+]site_content', "id IN ({$ids})");
	}
}
