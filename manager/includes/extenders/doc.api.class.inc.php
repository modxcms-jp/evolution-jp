<?php

class DocAPI {
	function DocAPI()
	{
	}
	
	function create($f = array(), $groups = array())
	{
		global $modx, $_lang;
		$f = $this->correctResourceFields($f);
		
		$f['pagetitle'] = (!$f['pagetitle']) ? $_lang['untitled_resource'] : $f['pagetitle'];
		$f['createdon'] = (!$f['createdon']) ? time() : $f['createdon'];
		$f['createdby'] = (!$f['createdby']) ? $modx->getLoginUserID() : $f['createdby'];
		$f['editedon']  = $f['createdon'];
		$f['editedby']  = $f['createdby'];
		if(isset($f['published']) && $f['published']==1 && !isset($f['publishedon']))
		    $f['publishedon'] = $f['createdon'];
		if(!$f['template'])
		    $f['template'] = $modx->config['default_template'];
		if (!empty($groups))
		    $f['privatemgr'] = 1;
		
//		$f = $this->setPubStatus($f);
		
		switch($modx->config['docid_incrmnt_method'])
		{
			case '1':
				$from = '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id';
				$where = "T1.id IS NULL";
				$rs = $modx->db->select('MIN(T0.id)+1', $from, "T1.id IS NULL");
				$docid = $modx->db->getValue($rs);
				break;
			case '2':
				$rs = $modx->db->select('MAX(id)+1','[+prefix+]site_content');
				$docid = $modx->db->getValue($rs);
				break;
			default:
				$docid = '';
		}

		if(!empty($docid)) $f['id'] = $docid;
		
		$id = $modx->db->insert($f, '[+prefix+]site_content');
		$this->replaceTVs($f,$id);
		if(isset($f['parent']) && preg_match('@^[1-9][0-9]*$@',$f['parent']))
		{
			$parent = $f['parent'];
			$modx->db->update(array('isfolder'=>'1'), '[+prefix+]site_content', "id='{$parent}'");
		}
		
		if (!empty($groups) && $id)
		{
			foreach ($groups as $group) {
				$modx->db->insert(array('document_group' => $group, 'document' => $id), '[+prefix+]document_groups');
			}
		}
		if($id!==false) $modx->clearCache();
		return $id;
	}
	
	function update($f = array(), $id = 0, $where = '')
	{
		global $modx;
		if(!preg_match('@^[0-9]+$@', $id)) return false;
		if(empty($id))
		{
			if(isset($modx->documentIdentifier)) $id = $modx->documentIdentifier;
			else return;
		}
		
		if(is_string($f) && strpos($f,'=')!==false)
		{
			list($k,$v) = explode('=',$f,2);
			$k = trim($k);
			$v = trim($v);
			$f = array();
			$f[$k] = $v;
		}
		
		if(!$f['template']) $f['template'] = $modx->getField('template',$id);
		
		$rs = $this->replaceTVs($f,$id);
		
//		$f = $this->setPubStatus($f);
		
		$f['editedon'] = (!$f['editedon']) ? time() : $f['editedon'];
		if(!isset($f['editedby']) && isset($_SESSION['mgrInternalKey']))
		{
			$f['editedby'] = $_SESSION['mgrInternalKey'];
		}
		
		$where .= " `id`='{$id}'";

		$f = $this->correctResourceFields($f);
		$rs = $modx->db->update($f, '[+prefix+]site_content', $where);
		if($rs!==false) $modx->clearCache();
		return $rs;
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
	
	function setPubStatus($f)
	{
		global $modx;

		$currentdate = time();
		
        if(!isset($f['pub_date']) || empty($f['pub_date'])) $f['pub_date'] = 0;
        else
        {
        	$f['pub_date'] = $modx->toTimeStamp($f['pub_date']);
        	if($f['pub_date'] < $currentdate) $f['published'] = 1;
        	elseif($f['pub_date'] > $currentdate) $f['published'] = 0;
        }
        
        if(empty($f['unpub_date'])) $f['unpub_date'] = 0;
        else
        {
        	$f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);
        	if($f['unpub_date'] < $currentdate) $f['published'] = 0;
        }
        return $f;
	}
	
	function correctResourceFields($f)
	{
		$fnames = $this->getResourceNames();
		$rfields = array();
		foreach($f as $k=>$v)
		{
			if(isset($fnames[$k])) $rfields[$k] = $v;
		}
		return $rfields;
	}

	function getResourceNames()
	{
		$fname = array();
		
        $fname['content']     = '1';
        $fname['pagetitle']   = '1';
        $fname['longtitle']   = '1';
        $fname['menutitle']   = '1';
        $fname['description'] = '1';
        $fname['introtext']   = '1';
        
        $fname['template']  = '1';
        $fname['parent']    = '1';
        $fname['alias']     = '1';
        $fname['isfolder']  = '1';
        $fname['hidemenu']  = '1';
        $fname['menuindex'] = '1';
        
        $fname['createdon']   = '1';
        $fname['editedon']    = '1';
        $fname['publishedon'] = '1';
        $fname['deletedon']   = '1';
        $fname['pub_date']    = '1';
        $fname['unpub_date']  = '1';
        
        $fname['published'] = '1';
        $fname['deleted']   = '1';
        
        $fname['createdby']   = '1';
        $fname['editedby']    = '1';
        $fname['deletedby']   = '1';
        $fname['publishedby'] = '1';
        
        $fname['type']          = '1';
        $fname['contentType']   = '1';
        $fname['content_dispo'] = '1';
        
        $fname['link_attributes'] = '1';
        $fname['searchable']      = '1';
        $fname['cacheable']       = '1';
        $fname['donthit']         = '1';
        
        $fname['richtext'] = '1';
        
        $fname['privateweb']  = '1';
        $fname['privatemgr']  = '1';
        $fname['haskeywords'] = '1';
        $fname['hasmetatags'] = '1';
        
		return $fname;
	}
	function replaceTVs(&$inputFields=array(), $id)
	{
	    global $modx;
		$rs = $modx->db->select('id,name', '[+prefix+]site_tmplvars');
		while($row = $modx->db->getRow($rs))
		{
			$tvname = $row['name'];
			$tvid   = $row['id'];
			$alltmplvarids[$tvname]    = $tvid;
			$alltmplvarids['tv'.$tvid] = $tvid;
		}
		foreach($inputFields as $name=>$v)
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
        		$template = $inputFields['template'];
        		$rs = $modx->db->select('*','[+prefix+]site_tmplvar_templates', "tmplvarid='{$tmplvarid}' AND templateid='{$template}'");
        		if($modx->db->getRecordCount($rs)==1)
        		{
        		    $value = $inputFields[$tmplvarname];
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
                    if($key) unset($inputFields[$tmplvarname]);
        		}
		    }
		}
		
		return $result;
	}
	function test()
	{
		return 'sdfsdfsdff';
	}
}
