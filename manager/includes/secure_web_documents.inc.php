<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

/**
 *	Secure Web Documents
 *	This script will mark web documents as private
 *
 *	A document will be marked as private only if a web user group 
 *	is assigned to the document group that the document belongs to.
 *
 */

function secureWebDocument($docid='') {
	global $modx;
	
	if($docid>0) $where = "id='{$docid}'";
	else         $where = 'privateweb=1';
	$modx->db->update(array('privateweb'=>0), '[+prefix+]site_content', $where);
	
	$field = 'DISTINCT sc.id';
	$from  = '[+prefix+]site_content sc'
			.' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
			.' LEFT JOIN [+prefix+]webgroup_access wga ON wga.documentgroup = dg.document_group';
	if($docid>0) $where = "sc.id='{$docid}' AND wga.id > 0";
	else         $where = 'wga.id > 0';
	$rs = $modx->db->select($field,$from,$where);
	$ids = $modx->db->getColumn('id',$rs);
	if(count($ids)>0) {
		$ids = implode(',', $ids);
		$modx->db->update(array('privateweb'=>1),'[+prefix+]site_content', "id IN ({$ids})");
	}
}
