<?php
/**
 * Based on modx ddTools class
 *
 * @copyright Copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

class DocAPI {
	function DocAPI()
	{
	}
	
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
		if($f['published']==1 && !isset($f['publishedon'])) $f['publishedon'] = $f['createdon'];
		$f['template']  = (!$f['template']) ? $modx->config['default_template'] : $f['template'];
		if ($groups) $f['privatemgr'] = 1;
		
		$id = $modx->db->insert($f, '[+prefix+]site_content');
		
		if ($groups && $id)
		{
			foreach ($groups as $group) {
				$modx->db->insert(array('document_group' => $group, 'document' => $id), '[+prefix+]document_groups');
			}
		}
		return $id;
	}
	
	function update($f = array(), $id = 0, $where = '')
	{
		global $modx;
		
		if(!preg_match('@^[0-9]+$@', $id)) return;
		if(empty($id))
		{
			if(isset($modx->documentIdentifier)) $id = $modx->documentIdentifier;
			else return;
		}
		
		if(is_string($f) && strpos($f,'=')!==false)
		{
			list($k,$v) = explode('=',$f,2);
			$k   = trim($k);
			$v = trim($v);
			$f = array();
			$f[$k] = $v;
		}
		
		$rs = $modx->db->select('id,name', '[+prefix+]site_tmplvars');
		while($row = $modx->db->getRow($rs))
		{
			$tvid   = $row['id'];
			$tvname = $row['name'];
			$alltvs[$tvname] = $tvid;
		}
		$tv = array();
		foreach($f as $k=>$v)
		{
			if(array_key_exists($k, $alltvs))
			{
				$tvid = $alltvs[$k];
				$modx->db->update(array('value'=>$v), '[+prefix+]site_tmplvar_contentvalues', "tmplvarid='{$tvid}'");
				unset($f[$k]);
			}
		}
		
		$f['editedon'] = (!$f['editedon']) ? time() : $f['editedon'];
		if(!isset($f['editedby']) && isset($_SESSION['mgrInternalKey']))
		{
			$f['editedby'] = $_SESSION['mgrInternalKey'];
		}
		
		$where .= " `id`='{$id}'";
	
		return $modx->db->update($f, '[+prefix+]site_content', $where);
	}
	
	function delete($id = 0, $where = '')
	{
		global $modx;
		
		if(!preg_match('@^[0-9]+$@', $id)) return;
		if(empty($id))
		{
			if(isset($modx->documentIdentifier)) $id = $modx->documentIdentifier;
			else return;
		}
		
		$f['deleted']     = '1';
		$f['published']   = '0';
		$f['publishedon'] = '';
		$f['pub_date']    = '';
		$f['unpub_date']  = '';
		
		$modx->db->update($f, '[+prefix+]site_content', "id='{$id}'");
	}
}
