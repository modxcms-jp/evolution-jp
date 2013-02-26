<?php
/**
 * Based on modx ddTools class
 *
 * @copyright Copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

class DocAPI {
	function create($f = array(), $groups = false)
	{
		global $modx, $_lang;
		
		if(!$f['pagetitle'] && !isset($_lang['untitled_resource']))
		{
			$lang_path = $modx->config['base_path'] . 'manager/includes/lang/';
			$modx->config['manager_language'];
		}
		
		$f['pagetitle'] = (!$f['pagetitle']) ? $_lang['untitled_resource'] : $f['pagetitle'];
		$f['createdon'] = (!$f['createdon']) ? time() : $f['createdon'];
		$f['createdby'] = (!$f['createdby']) ? $modx->getLoginUserID() : $f['createdby'];
		$f['editedon']  = $f['createdon'];
		$f['editedby']  = $f['createdby'];
		$f['published'] = (!$f['published']) ? '1' : $f['published'];
		$f['template']  = (!$f['template']) ? $modx->config['default_template'] : $f['template'];
		if ($groups) $f['privatemgr'] = 1;
		
		$id = $modx->db->insert($f, '[+prefix+]site_content');
		
		if ($groups && $id)
		{
			foreach ($groups as $gr) {
				$modx->db->insert(array('document_group' => $gr, 'document' => $id), '[+prefix+]document_groups');
			}
		}
		return $id;
	}
	
	function update($id = 0, $f = array(), $where = '')
	{
		global $modx;
		
		if(empty($id) || !preg_match('@^[0-9]+$@', $id)) return;
		
		$f['editedon'] = (!$f['editedon']) ? time() : $f['editedon'];
		$f['editedby'] = (!$f['editedby']) ? $modx->getLoginUserID() : $f['editedby'];
		
		$where .= " `id`='{$id}'";
	
		return $modx->db->update($f, '[+prefix+]site_content', $where);
	}
	
	function delete($id = 0, $f = array(), $where = '')
	{
		global $modx;
	}
}
