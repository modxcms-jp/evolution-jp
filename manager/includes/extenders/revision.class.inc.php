<?php
class REVISION
{
	var $hasDraft;
	
	function REVISION()
	{
		if(!defined('MODX_BASE_PATH'))  return false;
	}
	
    function getRevision($elmid)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "elmid='{$elmid}'");
        $total = $modx->db->getRecordcount($rs);
        while($row = $modx->db->getRow($rs))
    	{
    		$content = unserialize($row['content']);
    		$version = $row['version'];
    		$status = $row['status'];
    		if($row['version']==='inherit')
        	{
        		$rev[$version] = $content;
    		}
    		else $rev[$status] = $content;
        }
        return $rev;
    }
    
    function getRevisionObject($elmid,$elm='resource')
    {
    	global $modx;
    	$rs = $this->_setStatus($elmid,$elm);
    	if(!$rs) return false;
    	
    	$rs = $modx->db->select('*','[+prefix+]site_revision',"elmid='{$elmid}' AND element='{$elm}'");
    	$obj = array();
    	while($row = $modx->db->getRow($rs))
    	{
    		$status = $row['status'];
    		switch($status)
    		{
    			case 'draft'     :
    				foreach($row as $k=>$v)
    			    {
    			    	if($k==='content') continue;
    			    	$obj[$status][$k] = $v;
    				}
    				break;
    		}
    	}
    	return $obj;
    }
    
    function _setStatus($elmid,$elm='resource')
    {
    	global $modx;
    	
    	$rs = $modx->db->select('*','[+prefix+]site_revision',"elmid='{$elmid}' AND element='{$elm}'");
    	if(!$rs) return false;
    	
    	$this->hasDraft     = 0;
    	$this->hasInherit   = 0;
    	$this->hasPending   = 0;
    	$this->hasAutoDraft = 0;
    	$this->hasStandby   = 0;
    	$this->hasPrivate   = 0;
    	while($row = $modx->db->getRow($rs))
    	{
    		switch($row['status'])
    		{
    			case 'draft'     :$this->hasDraft     = 1;break;
    			case 'inherit'   :$this->hasInherit   = 1;break;
    			case 'pending'   :$this->hasPending   = 1;break;
    			case 'auto-draft':$this->hasAutoDraft = 1;break;
    			case 'standby'   :$this->hasStandby   = 1;break;
    			case 'private'   :$this->hasPrivate   = 1;break;
    		}
    	}
    	return true;
    }
    
    function getDraft($elmid)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "elmid='{$elmid}' AND version='0'");
        $row = $modx->db->getRow($rs);
        $data = array();
        if(isset($row['content'])) $data = unserialize($row['content']);
    	$resource = $this->getCurrentResource($elmid);
    	if(empty($data)) return array();
    	else $data = $data + $resource;
    	foreach($data as $k=>$v)
    	{
    		if(is_string($v)&&trim($v)==='')
    			unset($data[$k]);
    	}
        return $data;
    }
    
    function getRevisionStatus($elmid)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "elmid='{$elmid}' AND version='0'");
        $row = $modx->db->getRow($rs);
        if(!isset($row['status']) || empty($row['status']))
    	{
        	$status = 'nodraft';
        }
        else
        {
        	$status = $row['status'];
        }
        return $status;
    }
    
    function getFormFromDraft($id)
    {
    	global $modx;
    	
    	$data = $this->getDraft($id);
    	$resource  = $this->getCurrentResource($id);
    	$data = $data + $resource;
    
    	$tpl = '<input type="hidden" name="[+name+]" value="[+value+]" />';
    	$form = array();
    	foreach($data as $k=>$v)
    	{
    		$ph['name']  = $k;
    		$ph['value'] = htmlentities($v, ENT_QUOTES, 'UTF-8');
    		$form[] = $modx->parsePlaceholder($tpl,$ph);
    	}
    	return join("\n", $form);
    }
    
    function getCurrentResource($docid)
    {
    	global $modx;
    	
        $rs = $modx->getTemplateVars('*', '*', $docid);
        if(empty($rs)) return array();
        foreach($rs as $i=>$v)
    	{
    		if(isset($v['id'])) $name = 'tv' . $v['id'];
    		else                $name = $v['name'];
	    	$doc[$name] = $v['value'];
        }
        
        $doc = $this->convertData($doc);
        
        if(!empty($doc)) return $doc;
    }

    function convertData($resource=array())
    {
    	global $modx;
    	
    	if(isset($resource['ta'])) $resource['content'] = $resource['ta'];
    	
        $input['content']         = $resource['content'];
        $input['pagetitle']       = $resource['pagetitle'];
        $input['longtitle']       = $resource['longtitle'];
        $input['menutitle']       = $resource['menutitle'];
        $input['description']     = $resource['description'];
        $input['introtext']       = $resource['introtext'];
        $input['type']            = $resource['type'];
        $input['alias']           = $resource['alias'];
        $input['link_attributes'] = $resource['link_attributes'];
        $input['isfolder']        = $resource['isfolder'];
        $input['richtext']        = $resource['richtext'];
        $input['parent']          = $resource['parent'];
        $input['template']        = $resource['template'];
        $input['menuindex']       = $resource['menuindex'];
        $input['searchable']      = $resource['searchable'];
        $input['cacheable']       = $resource['cacheable'];
        $input['contentType']     = $resource['contentType'];
        $input['content_dispo']   = $resource['content_dispo'];
        $input['hidemenu']        = $resource['hidemenu'];
		$input['published']       = $resource['published'];
		$input['pub_date']        = $resource['pub_date'];
		$input['unpub_date']      = $resource['unpub_date'];
        foreach($resource as $k=>$v)
        {
        	if(substr($k,0,2)==='tv') $input[$k] = $v;
        }
        return $input;
    }
    
    function save($elmid='',$resource=array(), $status='inherit')
    {
    	global $modx;
    	
    	if(empty($elmid)) return;
    	
    	$input = $this->convertData($resource);
    	$input['status'] = $status;
        
    	$rs = $modx->db->select('*','[+prefix+]site_revision', "elmid='{$elmid}'", 'version DESC');
    	$exists_version = $modx->db->getRow($rs);
    	
    	$revision_content = serialize($input);
    	$revision_content = $modx->db->escape($revision_content);
    	$checksum = md5($revision_content);
    	if(empty($exists_version) || $exists_version['checksum'] !== $checksum)
    	{
    		$f = array();
    		$f['elmid']         = $elmid;
    		$f['status']     = $status;
    		$f['content']    = $revision_content;
    		$f['element']     = 'resource';
    		$f['editedon']   = time();
    		$f['editedby']   = $modx->getLoginUserID();
    //		$f['pub_date']   = $pub_date;
    //		$f['unpub_date'] = $unpub_datey;
    		$f['checksum']   = $checksum;
    		
    		$total = $modx->db->getRecordCount($rs);
    		switch($status)
    		{
    		    case 'inherit':
    		    	$f['version'] = $total + 1;
    		    	break;
    		    default:
    		    	$f['version'] = '0';
    		}
    		
    		if($total==0) $modx->db->insert($f,'[+prefix+]site_revision');
    		else          $modx->db->update($f,'[+prefix+]site_revision',"elmid='{$elmid}'");
    		
    		return $total==0 ? 'new' : 'upd';
    	}
    	return 'nochange';
    }
    
    function delete($elmid='', $status='*')
    {
    	global $modx;
    	
    	if(empty($elmid)) return;
    	
    	if($status==='*') $where = "elmid='{$elmid}'";
    	else              $where = "elmid='{$elmid}' AND status='{$status}'";
    	
    	$rs = $modx->db->delete('[+prefix+]site_revision', $where);
    	return $rs;
    }
    
    function convertTvid2Tvname($input)
    {
    	global $modx;
    	
        $rs = $modx->db->select('id,name','[+prefix+]site_tmplvars');
        while($row = $modx->db->getRow($rs))
        {
        	$tvid = 'tv' . $row['id'];
        	$tvname[$tvid] = $row['name'];
        }
        
        foreach($input as $k=>$v)
        {
        	if(isset($tvname[$k]))
        	{
        		unset($input[$k]);
        		$k = $tvname[$k];
        		$input[$k] = $v;
        	}
        	elseif($k==='ta')
        	{
        		$input['content'] = $v;
        		unset($input['ta']);
        	}
        }
        return $input;
    }
}
