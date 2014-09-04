<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

if(isset($_POST['id']) && preg_match('@^[1-9][0-9]*$@',$_POST['id']))
{
	$id = $_POST['id'];
	$mode ='update';
}
else
{
	if(isset($_POST['pagetitle']) && $_POST['pagetitle']!=='')
	{
	    $f['pagetitle'] = $modx->db->escape($_POST['pagetitle']);
	}
	if(isset($_POST['pid']) && preg_match('@^[1-9][0-9]*$@',$_POST['pid']))
	{
		$f['parent'] = $modx->db->escape($_POST['pid']);
	}
	elseif(isset($_POST['parent']) && preg_match('@^[1-9][0-9]*$@',$_POST['parent']))
		$f['parent'] = $modx->db->escape($_POST['parent']);
	
	$f['published'] = '0';
	
	$id = $modx->doc->create($f);
	$mode ='create';
}

$stay = isset($_POST['stay'])&&!empty($_POST['stay']) ? $_POST['stay'] : '0';

$input = fix_tv_nest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
if(isset($input['ta']))
{
	$input['content'] = $input['ta'];
	unset($input['ta']);
}

$input = setPubStatus($input);



if($mode==='update' && $input['published']==='0')
{
	$fields = array();
	$fields['pagetitle'] = $modx->db->escape($input['pagetitle']);
	$rs = $modx->db->update($fields,'[+prefix+]site_content',"id='{$id}'");
}

switch($stay)
{
    case 'publish_draft':
    	$currentdate = time();
    	if($currentdate < $input['pub_date'])
    	{
        	$modx->revision->save($id,$input,'future');
        	$f = array();
        	$f['pub_date'] = $input['pub_date'];
        	$modx->db->update($f,'[+prefix+]site_revision',"id='{$id}'");
        	$modx->setCacheRefreshTime($input['pub_date']);
    	}
    	else
    	{
    		$fields = $modx->db->escape($input);
        	$rs = $modx->doc->update($fields, $id);
        	$modx->revision->delete($id);
    	}
    	break;
    case '2'          :
    case 'save_draft' :
    case 'submit'     :
    case 'remand'     :
    case 'accept'     :
    default:
        $draftStatus = $modx->revision->getRevisionStatus($id);
        if($draftStatus==='nodraft' || $stay==='save_draft') $draftStatus = 'draft';
        $modx->revision->save($id,$input,$draftStatus);
        $f = array();
    	$f['editedby'] = $modx->getLoginUserID();
        if($stay ==='submit')      $f['submittedby'] = $modx->getLoginUserID();
        if(0<$input['pub_date'])   $f['pub_date'] = $input['pub_date'];
        if(0<$input['unpub_date']) $f['unpub_date'] = $input['unpub_date'];
    	$modx->db->update($f,'[+prefix+]site_revision',"id='{$id}'");
    	$modx->setCacheRefreshTime($input['pub_date']);
}

switch($stay)
{
    case '2'             : 
    case 'save_draft'    : $a = "a=27&id={$id}&mode=draft&r=1"; break;
    case 'publish_draft' : $a = "a=127&id={$id}&mode=publish_draft&r=1"; break;
    case 'submit'        : $a = "a=122&id={$id}&mode=publish_draft&r=1"; break;
    case 'remand'        : $a = "a=125&id={$id}&mode=remand&r=1"; break;
    case 'accept'        : $a = "a=125&id={$id}&mode=accept&r=1"; break;
    default              : $a = "a=3&id={$id}&r=1";
}

$header = "Location: index.php?{$a}";
header($header);
exit;



function fix_tv_nest($field,$input)
{
	foreach(explode(',',$field) as $name)
	{
		$tv = ($name !== 'ta') ? $name : 'content';
		$s = "[*{$tv}*]";
		$r = "[ *{$tv}* ]";
		$input[$name] = str_replace($s,$r,$input[$name]);
	}
	return $input;
}

function setPubStatus($f)
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
