<?php
class REVISION
{
	
	function REVISION()
	{
		if(!defined('MODX_BASE_PATH'))  return false;
	}
	
    function getRevision($id)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "id='{$id}'");
        $total = $modx->db->getRecordcount($rs);
        while($row = $modx->db->getRow($rs))
    	{
    		$content = unserialize($row['content']);
    		$revision = $row['revision'];
    		$status = $row['status'];
    		if($row['revision']==='inherit')
        	{
        		$rev[$revision] = $content;
    		}
    		else $rev[$status] = $content;
        }
        return $rev;
    }

    function getDraft($id)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "id='{$id}' AND revision='0'");
        $row = $modx->db->getRow($rs);
        $data = array();
        if(isset($row['content'])) $data = unserialize($row['content']);
    	$resource = $this->getCurrentResource($id);
    	if(empty($data)) return array();
    	else $data = $data + $resource;
    	foreach($data as $k=>$v)
    	{
    		if(trim($v)==='') unset($data[$k]);
    	}
        return $data;
    }
    
    function getRevisionStatus($id)
    {
    	global $modx;
    	
        $rs = $modx->db->select('*', '[+prefix+]site_revision', "id='{$id}' AND revision='0'");
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
    
    function getCurrentResource($id)
    {
    	global $modx;
        $rs = $modx->getTemplateVars('*', '*', $id);
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
    	
    	$id = $resource['id'];
    	
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
    
    function save($id='',$resource=array(), $status='inherit')
    {
    	global $modx;
    	
    	if(empty($id)) return;
    	
    	$input = $this->convertData($resource);
    	$input['status'] = $status;
/*
        echo '<pre>';
        print_r($input);
        echo '</pre>';exit;
*/
        
    	$rs = $modx->db->select('*','[+prefix+]site_revision', "id='{$id}'", 'revision DESC');
    	$exists_revision = $modx->db->getRow($rs);
    	$total = $modx->db->getRecordCount($rs);
    	$revision_content = serialize($input);
    	$revision_content = $modx->db->escape($revision_content);
    	$file_name = MODX_BASE_PATH . time() . '.txt';
    	file_put_contents($file_name,$revision_content);
    	$checksum = md5($revision_content);
    	if(empty($exists_revision) || $exists_revision['checksum'] !== $checksum)
    	{
    		$f = array();
    		$f['id']         = $id;
    		$f['status']     = $status;
    		$f['content']    = $revision_content;
    		$f['target']     = 'resource';
    		$f['editedon']   = time();
    		$f['editedby']   = $modx->getLoginUserID();
    //		$f['pub_date']   = $pub_date;
    //		$f['unpub_date'] = $unpub_datey;
    		$f['checksum']   = $checksum;

    		switch($status)
    		{
    		    case 'inherit':
    		    	$f['revision'] = $total + 1;
    		    	break;
    		    default:
    		    	$f['revision'] = '0';
    		}
    		
    		if($total==0) $modx->db->insert($f,'[+prefix+]site_revision');
    		else          $modx->db->update($f,'[+prefix+]site_revision',"id='{$id}'");
    	}
    }
    
    function delete($id='', $status='*')
    {
    	global $modx;
    	
    	if(empty($id)) return;
    	
    	if($status==='*') $where = "id='{$id}'";
    	else              $where = "id='{$id}' AND status='{$status}'";
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
