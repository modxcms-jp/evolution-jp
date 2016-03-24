<?php

$this->doc= new DocAPI;

class DocAPI {
	
	var $mode;
	
	function __construct()
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
		$f = $modx->db->escape($f);
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
        		    $value = $modx->db->escape($inputFields[$tmplvarname]);
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
	
	function initValue($form_v)
	{
		global $modx;
		
		$fields = 'id,ta,alias,type,contentType,pagetitle,longtitle,description,link_attributes,isfolder,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,richtext,content_dispo,donthit,menutitle,hidemenu,introtext';
		$fields = explode(',',$fields);
		if(isset($form_v['ta'])) $form_v['content'] = $form_v['ta'];
		foreach($fields as $key) {
			if(!isset($form_v[$key])) $form_v[$key] = '';
			$value = trim($form_v[$key]);
			switch($key) {
				case 'id': // auto_increment
				case 'parent':
				case 'template':
				case 'menuindex':
				case 'publishedon':
				case 'publishedby':
				case 'content_dispo':
					if(!preg_match('@^[0-9]+$@',$value))
						$value = 0;
					break;
				case 'published':
				case 'isfolder':
				case 'donthit':
				case 'hidemenu':
				case 'richtext':
					if(!preg_match('@^[01]$@',$value))
						$value = 0;
					break;
				case 'searchable':
				case 'cacheable':
					if(!preg_match('@^[01]$@',$value))
						$value = 1;
					break;
				case 'pub_date':
				case 'unpub_date':
					if($value==='') $value = 0;
					else $value = $modx->toTimeStamp($value);
					break;
				case 'editedon':
					$value = $_SERVER['REQUEST_TIME'];
					break;
				case 'editedby':
					if(empty($value)) $value = $modx->getLoginUserID('mgr');
					break;
				case 'type':
					if($value==='') $value = 'document';
					break;
				case 'contentType':
					if($value==='') $value = 'text/html';
					break;
				case 'longtitle':
				case 'description':
				case 'link_attributes':
				case 'introtext':
				case 'menutitle':
				case 'pagetitle':
				case 'content':
				case 'alias':
					break;
			}
			$form_v[$key] = $value;
		}
		return $form_v;
	}
	
	function setValue($form_v) {
		global $modx,$_lang;
		
		$id = $form_v['id'];
		$mode = $_POST['mode'];
		
		$form_v['alias'] = get_alias($id,$form_v['alias'],$form_v['parent'],$form_v['pagetitle']);
		if($form_v['type']!=='reference' && $form_v['contentType'] !== 'text/html')
			$form_v['richtext'] = 0;
		
		$pos = strrpos($form_v['alias'],'.');
		if($pos!==false && $form_v['contentType'] === 'text/html')
		{
			$ext = substr($form_v['alias'],$pos);
			if    ($ext==='.xml') $form_v['contentType'] = 'text/xml';
			elseif($ext==='.rss') $form_v['contentType'] = 'application/rss+xml';
			elseif($ext==='.css') $form_v['contentType'] = 'text/css';
			elseif($ext==='.js')  $form_v['contentType'] = 'text/javascript';
			elseif($ext==='.txt') $form_v['contentType'] = 'text/plain';
		}
		
		if($form_v['type']==='reference') {
			if(strpos($form_v['content'],"\n")!==false||strpos($form_v['content'],'<')!==false)
				$form_v['content'] = '';
		}
		
		if($form_v['pagetitle']==='') {
			if ($form_v['type'] === 'reference')
				$form_v['pagetitle'] = $_lang['untitled_weblink'];
			else
				$form_v['pagetitle'] = $_lang['untitled_resource'];
		}
		
		if(substr($form_v['alias'],-1)==='/') {
			$form_v['alias'] = trim($form_v['alias'],'/');
			$form_v['isfolder'] = 1;
			$form_v['alias'] = $modx->stripAlias($form_v['alias']);
		}
		
		if(!empty($form_v['pub_date'])) {
			$form_v['pub_date'] = $modx->toTimeStamp($form_v['pub_date']);
			if(empty($form_v['pub_date']))
			{
				$modx->manager->saveFormValues($mode);
				$url = "index.php?a={$mode}";
				if($id) $url.= "&id={$id}";
				$modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'],$url);
			}
			elseif($form_v['pub_date'] < $_SERVER['REQUEST_TIME']) $form_v['published'] = 1;
			elseif($form_v['pub_date'] > $_SERVER['REQUEST_TIME']) $form_v['published'] = 0;
		}
		
		if(!empty($form_v['unpub_date'])) {
			$form_v['unpub_date'] = $modx->toTimeStamp($form_v['unpub_date']);
			if(empty($form_v['unpub_date']))
			{
				$modx->manager->saveFormValues($mode);
				$url = "index.php?a={$mode}";
				if($id) $url.= "&id={$id}";
				$modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'],$url);
			}
			elseif($form_v['unpub_date'] < $_SERVER['REQUEST_TIME']) $form_v['published'] = 0;
		}
		
		if($_POST['mode'] == '27') $actionToTake = 'edit';
		else                       $actionToTake = 'new';
	
		// deny publishing if not permitted
		if ($actionToTake==='new') {
			if (!$modx->hasPermission('publish_document'))
			{
				$form_v['pub_date'] = 0;
				$form_v['unpub_date'] = 0;
				$form_v['published'] = 0;
			}
			$form_v['publishedon'] = ($form_v['published'] ? $_SERVER['REQUEST_TIME'] : 0);
			$form_v['publishedby'] = ($form_v['published'] ? $modx->getLoginUserID() : 0);
			
			$form_v['createdby'] = $modx->getLoginUserID();
			$form_v['createdon'] = $_SERVER['REQUEST_TIME'];
		} else {
		}
		return $form_v;
	}
	
	function getNewDocID()
	{
		global $modx;
		
		switch($modx->config['docid_incrmnt_method']) {
			case '1':
				$from = '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id';
				$where = "T1.id IS NULL";
				$rs = $modx->db->select('MIN(T0.id)+1', $from, "T1.id IS NULL");
				$newid = $modx->db->getValue($rs);
				break;
			case '2':
				$rs = $modx->db->select('MAX(id)+1','[+prefix+]site_content');
				$newid = $modx->db->getValue($rs);
				break;
			default:
				$newid = '';
		}
		return $newid;
	}
	
    function fixPubStatus($f) // published, pub_date, unpub_date
    {
    	global $modx;
    
    	$currentdate = time();
    	
        if(isset($f['pub_date']) && !empty($f['pub_date']))
        {
        	$f['pub_date'] = $modx->toTimeStamp($f['pub_date']);
        	
        	if($f['pub_date'] < $currentdate) $f['published'] = 1;
        	else                              $f['published'] = 0;
        }
        else $f['pub_date'] = 0;
        
        if(isset($f['unpub_date']) && !empty($f['unpub_date']))
        {
        	$f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);
        	
        	if($f['unpub_date'] < $currentdate) $f['published'] = 0;
        	else                                $f['published'] = 1;
        }
        else $f['unpub_date'] = 0;
        
        return $f;
    }
    
	function fixTvNest($target,$form_v)
	{
		foreach(explode(',',$target) as $name)
		{
			$tv = ($name === 'ta') ? 'content' : $name;
			$s = "[*{$tv}*]";
			$r = "[ *{$tv}* ]";
			if(strpos($form_v[$name],$s)===false) continue;
			$form_v[$name] = str_replace($s,$r,$form_v[$name]);
		}
        if(isset($form_v['ta']))
        {
        	$form_v['content'] = $form_v['ta'];
        	unset($form_v['ta']);
        }
		return $form_v;
	}
	
    function canSaveDoc()
    {
    	global $modx;
    	
    	return $modx->hasPermission('save_document');
    }
    
    function canPublishDoc()
    {
    	global $modx;
    	if($modx->hasPermission('new_document')) return 1;
    	elseif(!$modx->documentObject['published']) return 1;
    	else return 0;
    }
    
    function canSaveDraft()
    {
    	global $modx;
    	return 1;
    }
    
    function canMoveDoc()
    {
    	global $modx;
    	return $modx->hasPermission('save_document');
    }
    
    function canCopyDoc()
    {
    	global $modx;
    	return ($modx->hasPermission('new_document')&&$modx->hasPermission('save_document'));
    }
    
    function canDeleteDoc()
    {
    	global $modx;
    	return ($modx->hasPermission('save_document')&&$modx->hasPermission('delete_document'));
    }
    
    function canCreateDoc()
    {
    	global $modx;
    	return $modx->hasPermission('new_document');
    }
    
    function canEditDoc()
    {
    	global $modx;
    	return $modx->hasPermission('edit_document');
    }
    
    function existsDoc($id = 0) {
        global $modx;
        $rs = $modx->db->select('id','[+prefix+]site_content', "id='{$id}'");
        if($modx->db->getRecordCount($rs)==0) {
            return false;
        }
        else {
            return true;
        }
    }
}

