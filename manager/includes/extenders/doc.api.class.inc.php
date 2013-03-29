<?php

class DocAPI {
	function DocAPI()
	{
	}
	
	function create($f = array(), $groups = array())
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
		if(!$f['published'])
		    $f['published'] = '1';
		if($f['published']==1 && !isset($f['publishedon']))
		    $f['publishedon'] = $f['createdon'];
		if(!$f['template'])
		    $f['template'] = $modx->config['default_template'];
		if (!empty($groups))
		    $f['privatemgr'] = 1;
		
		$this->replaceTVs($f);
		
		$id = $modx->db->insert($f, '[+prefix+]site_content');
		
		if (!empty($groups) && $id)
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
		
		if(!$f['template']) $f['template'] = $modx->db->getField('template',$id);
		
		$rs = $this->replaceTVs($f);
		
		$f['editedon'] = (!$f['editedon']) ? time() : $f['editedon'];
		if(!isset($f['editedby']) && isset($_SESSION['mgrInternalKey']))
		{
			$f['editedby'] = $_SESSION['mgrInternalKey'];
		}
		
		$where .= " `id`='{$id}'";
		
		return $modx->db->update($f, '[+prefix+]site_content', $where);
	}
	
	function replaceTVs(&$fields=array())
	{
	    global $modx;
	    
		$rs = $modx->db->select('id,name', '[+prefix+]site_tmplvars');
		while($row = $modx->db->getRow($rs))
		{
			$tvname = $row['name'];
			$alltmplvarids[$tvname] = $row['id'];
		}
		foreach($fields as $name=>$v)
		{
			if(array_key_exists($name, $alltmplvarids))
			{
				$tmplvarids[$name] = $alltmplvarids[$name];
			}
		}
		$result = false;
		if(isset($tmplvarids) && !empty($tmplvarids))
		{
		    foreach($tmplvarids as $tmplvarname=>$tmplvarid)
		    {
        		$template = $fields['template'];
        		$rs = $modx->db->select('*','[+prefix+]site_tmplvar_templates', "tmplvarid='{$tmplvarid}' AND templateid='{$template}'");
        		if($modx->db->getRecordCount($rs)==1)
        		{
        		    $value = $fields[$tmplvarname];
        		    $key = false;
            		$rs = $modx->db->select('*','[+prefix+]site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$id}'");
            		if($modx->db->getRecordCount($rs)==0)
            		{
            		    $key = $modx->db->insert(array('value'=>$value,'tmplvarid'=>$tmplvarid,'contentid'=>$id), '[+prefix+]site_tmplvar_contentvalues');
            		}
            		else
            		{
            		    $key = $modx->db->update(array('value'=>$value), '[+prefix+]site_tmplvar_contentvalues', "tmplvarid='{$tmplvarid}' AND contentid='{$id}'");
            		}
                    if($key) unset($fields[$tmplvarname]);
        		}
		    }
		}
		
		return $result;
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
