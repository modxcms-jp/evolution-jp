<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

global $form_v;
$form_v = fix_tv_nest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
$form_v = initValue($form_v);
$form_v = setValue($form_v);

// preprocess POST values
$id = $form_v['id'];
if(!preg_match('@^[0-9]*$@',$id)) {
	$e->setError(2);
	$e->dumpError();
}

if($_POST['mode'] == '27') $actionToTake = 'edit';
else                       $actionToTake = 'new';

if($actionToTake==='edit' && empty($id)) {
	$e->setError(2);
	$e->dumpError();
}

$docgroups = isset($_POST['docgroups']) ? $_POST['docgroups'] : array();
$document_groups = (isset($_POST['chkalldocs']) && $_POST['chkalldocs'] === 'on') ? array() : $docgroups;

checkDocPermission($id,$document_groups);

$modx->manager->saveFormValues();

switch ($actionToTake) {
	case 'new' :
		$return_url = 'index.php?a=' . $_GET['a'];
		
		// invoke OnBeforeDocFormSave event
		$modx->invokeEvent('OnBeforeDocFormSave', array('mode'=>'new'));

		$temp_id = $modx->manager->getNewDocID();
		$values = getInputValues($temp_id,'new');
		$values = $modx->db->escape($values);
		$newid = $modx->db->insert($values,'[+prefix+]site_content');
		if(!$newid) {
			$msg = 'An error occured while attempting to save the new document: ' . $modx->db->getLastError();
			$modx->webAlertAndQuit($msg, $return_url);
		}
		
		if(!empty($form_v['template'])) {
			$tmplvars = get_tmplvars($newid);
			insert_tmplvars($newid,$tmplvars);
		}

		setDocPermissionsNew($document_groups,$newid);

		updateParentStatus();
		if(isset($modx->config['show_meta'])) saveMETAKeywords($newid);

		// invoke OnDocFormSave event
		$modx->invokeEvent('OnDocFormSave', array('mode'=>'new','id'=>$newid));

		if($modx->config['use_udperms']==='1') {
			$modx->manager->setWebDocsAsPrivate($newid);
			$modx->manager->setMgrDocsAsPrivate($newid);
		}
		
		if($form_v['syncsite'] == 1) $modx->clearCache();
		goNextAction($newid);
		break;
	case 'edit' :
		$return_url = "index.php?a=27&id={$id}";
		$db_v = getExistsValues($id, $return_url);
		
		checkStartDoc($id,$return_url);
		$form_v['parent']   = checkParentID($id,$return_url);
		
		$form_v['isfolder'] = checkFolderStatus($id);

		// set publishedon and publishedby
		$form_v['published']   = checkPublished($db_v);
		$form_v['pub_date']    = checkPub_date($db_v);
		$form_v['unpub_date']  = checkUnpub_date($db_v);
		$form_v['publishedon'] = checkPublishedon($db_v);
		$form_v['publishedby'] = checkPublishedby($db_v);
		
		// invoke OnBeforeDocFormSave event
		$modx->invokeEvent('OnBeforeDocFormSave', array('mode'=>'upd','id'=>$id));
		
		$values = getInputValues($id,'edit');
		$values = $modx->db->escape($values);
		$rs = $modx->db->update($values,'[+prefix+]site_content',"id='{$id}'");
		if (!$rs) {
			$msg = "An error occured while attempting to save the edited document. The generated SQL is: <i> {$sql} </i>.";
			$modx->webAlertAndQuit($msg, $return_url);
		}
		
		if(!empty($form_v['template'])) {
			$tmplvars = get_tmplvars($id);
			update_tmplvars($id,$tmplvars);
		}
		
		setDocPermissionsEdit($document_groups,$id);

		updateParentStatus();
		
		// finished moving the document, now check to see if the old_parent should no longer be a folder
		if($db_v['parent']!=='0') folder2doc($db_v['parent']);

		if(isset($modx->config['show_meta'])) saveMETAKeywords($id);

		// invoke OnDocFormSave event
		$modx->invokeEvent('OnDocFormSave', array('mode'=>'upd','id'=>$id));

		if($modx->config['use_udperms']==='1') {
			$modx->manager->setWebDocsAsPrivate($id);
			$modx->manager->setMgrDocsAsPrivate($id);
		}
		
		if ($form_v['syncsite'] === '1') {
			if($form_v['published']===$db_v['published']&&$form_v['alias']===$db_v['alias']&&$form_v['parent']===$db_v['parent'])
				$clearcache['target'] = 'pagecache';
			else
				$clearcache['target'] = 'pagecache,sitecache';
			$modx->clearCache($clearcache);
		}
		goNextAction($id);
	default :
		header("Location: index.php?a=7");
		exit;
}

// -- Save META Keywords --
function saveMETAKeywords($id) {
	global $modx;
	$keywords = $_POST['keywords'];
	$metatags = $_POST['metatags'];
	
	if(!$keywords&&!$metatags) return;
	if(!isset($modx->config['show_meta']) || $modx->config['show_meta']==0)
		return;
	if (!$modx->hasPermission('edit_doc_metatags'))
		return;
	
	// keywords - remove old keywords first
	$modx->db->delete('[+prefix+]keyword_xref', "content_id='{$id}'");
	foreach($keywords as $keyword) {
		$flds = array (
			'content_id' => $id,
			'keyword_id' => $keyword
		);
		$flds = $modx->db->escape($flds);
		$modx->db->insert($flds, '[+prefix+]keyword_xref');
	}
	// meta tags - remove old tags first
	$modx->db->delete('[+prefix+]site_content_metatags', "content_id='{$id}'");
	foreach($metatag as $metatag) {
		$flds = array (
			'content_id' => $id,
			'metatag_id' => $metatag
		);
		$flds = $modx->db->escape($flds);
		$modx->db->insert($flds, '[+prefix+]site_content_metatags');
	}
	$flds = array (
		'haskeywords' => (count($keywords) ? 1 : 0),
		'hasmetatags' => (count($metatags) ? 1 : 0)
	);
	$flds = $modx->db->escape($flds);
	$modx->db->update($flds, '[+prefix+]site_content', "id='{$id}'");
}

function get_tmplvars($id)
{
	global $modx;

	$template = $form_v['template'];
	
	if(empty($template)) return array();
	
	// get document groups for current user
	if ($_SESSION['mgrDocgroups'])
	{
		$docgrp = implode(',', $_SESSION['mgrDocgroups']);
	}
	
	$field = "DISTINCT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value";
	$from = "[+prefix+]site_tmplvars AS tv ";
	$from .= "INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid = tv.id ";
	$from .= 'LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc ON tvc.tmplvarid=tv.id ';
	if($id) $from .= " AND tvc.contentid = '{$id}' ";
	$from .= "LEFT JOIN [+prefix+]site_tmplvar_access tva ON tva.tmplvarid=tv.id  ";
	$tva_docgrp = ($docgrp) ? "OR tva.documentgroup IN ({$docgrp})" : '';
	$where = "tvtpl.templateid = '{$template}' AND (1='{$_SESSION['mgrRole']}' OR ISNULL(tva.documentgroup) {$tva_docgrp})";
	$orderby = 'tv.rank';
	$rs = $modx->db->select($field,$from,$where,$orderby);
	
	$tmplvars = array ();
	while ($row = $modx->db->getRow($rs)):
		$tmplvar = '';
		$tvid = "tv{$row['id']}";
		
		if(!isset($_POST[$tvid]) && $row['type']!=='checkbox' && $row['type']!=='listbox-multiple')
			continue;
		
		if($row['type']==='url') {
			if($_POST["{$tvid}_prefix"] !== '--') {
				$tmplvar = str_replace(array ('feed://','ftp://','http://','https://','mailto:'), '', $tmplvar);
				$tmplvar = $_POST["{$tvid}_prefix"] . $tmplvar;
			}
			else $tmplvar = $_POST[$tvid];
		}
		elseif($row['type']==='file')    $tmplvar = $_POST[$tvid];
		else {
			if(is_array($_POST[$tvid])) {
				// handles checkboxes & multiple selects elements
				$feature_insert = array ();
				$lst = $_POST[$tvid];
				foreach($lst as $v) {
					$feature_insert[count($feature_insert)] = $v;
				}
				$tmplvar = implode('||', $feature_insert);
			}
			elseif(isset($_POST[$tvid])) $tmplvar = $_POST[$tvid];
			else                         $tmplvar = '';
		}
		// save value if it was modified
		if (strlen($tmplvar) > 0 && $tmplvar != $row['default_text'])
		{
			$tmplvars[$row['id']] = array (
				$row['id'],
				$tmplvar
			);
		}
		else $tmplvars[$row['name']] = $row['id']; // Mark the variable for deletion
	endwhile;
	return $tmplvars;
}

function fix_tv_nest($target,$form_v)
{
	foreach(explode(',',$target) as $name)
	{
		$tv = ($name === 'ta') ? 'content' : $name;
		$s = "[*{$tv}*]";
		$r = "[ *{$tv}* ]";
		if(strpos($form_v[$name],$s)===false) continue;
		$form_v[$name] = str_replace($s,$r,$form_v[$name]);
	}
	return $form_v;
}

function get_alias($id,$alias,$parent,$pagetitle)
{
	global $modx;
	// friendly url alias checks
	if ($modx->config['friendly_urls'])
	{
		if(!$parent) $parent = '0';
		if ($alias && !$modx->config['allow_duplicate_alias'])
		{ // check for duplicate alias name if not allowed
			$alias = _check_duplicate_alias($id,$alias,$parent);
		}
		elseif (!$alias && $modx->config['automatic_alias'] != '0')
		{ // auto assign alias
			switch($modx->config['automatic_alias'])
			{
				case '1':
					$alias = $modx->manager->get_alias_from_title($id,$pagetitle);
					break;
				case '2':
					$alias = $modx->manager->get_alias_num_in_folder($id,$parent);
					break;
			}
			
		}
	}
	return $alias;
}

function _check_duplicate_alias($id,$alias,$parent)
{
	global $modx;
	
	if ($modx->config['use_alias_path']==1)
	{ // only check for duplicates on the same level if alias_path is on
		$rs = $modx->db->select('id','[+prefix+]site_content',"id<>'{$id}' AND alias='{$alias}' AND parent={$parent} LIMIT 1");
		$docid = $modx->db->getValue($rs);
		if($docid < 1)
		{
			$rs = $modx->db->select('id','[+prefix+]site_content',"id='{$alias}' AND alias='' AND parent='{$parent}'");
			$docid = $modx->db->getValue($rs);
		}
	}
	else
	{
		$rs = $modx->db->select('id','[+prefix+]site_content',"id<>'{$id}' AND alias='{$alias}' LIMIT 1");
		$docid = $modx->db->getValue($rs);
		if($docid < 1)
		{
			$rs = $modx->db->select('id','[+prefix+]site_content',"id='{$alias}' AND alias=''");
			$docid = $modx->db->getValue($rs);
		}
	}
	if ($docid > 0)
	{
		if ($_POST['mode'] == '27')
		{
			$modx->manager->saveFormValues(27);
			$url = "index.php?a=27&id={$id}";
		}
		else
		{
			$modx->manager->saveFormValues($_POST['mode']);
			if($_REQUEST['pid']) $pid = '&pid=' . $_REQUEST['pid'];
			$url = 'index.php?a=' . $_POST['mode'] . $pid;
		}
		$modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid, $alias), $url);
	}
	return $alias;
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

function checkDocPermission($id,$document_groups) {
	global $modx,$form_v,$_lang,$e;
	// ensure that user has not made this document inaccessible to themselves
	if($_SESSION['mgrRole'] != 1 && is_array($document_groups) && !empty($document_groups))
	{
		$document_group_list = implode(',', $document_groups);
		$document_group_list = implode(',', array_filter(explode(',',$document_group_list), 'is_numeric'));
		if(!empty($document_group_list))
		{
			$from='[+prefix+]membergroup_access mga, [+prefix+]member_groups mg';
			$mgrInternalKey = $_SESSION['mgrInternalKey'];
			$where = "mga.membergroup = mg.user_group AND mga.documentgroup IN({$document_group_list}) AND mg.member='{$mgrInternalKey}'";
			$count = $modx->db->getValue($modx->db->select('COUNT(mg.id)',$from,$where));
			if($count == 0)
			{
				if ($actionToTake == 'new') $url = 'index.php?a=4';
				else                        $url = "index.php?a=27&id={$id}";
				
				$modx->manager->saveFormValues($_POST['mode']);
				$modx->webAlertAndQuit(sprintf($_lang["resource_permissions_error"]), $url);
			}
		}
	}
	
	// get the document, but only if it already exists
	if ($_POST['mode'] === '27')
	{
		$rs = $modx->db->select('parent', '[+prefix+]site_content', "id='{$id}'");
		$total = $modx->db->getRecordCount($rs);
		if ($total > 1)
		{
			$e->setError(6);
			$e->dumpError();
		} elseif ($total < 1) {
			$e->setError(7);
			$e->dumpError();
		}
		if ($modx->config['use_udperms'] !== 1) return;
		$existingDocument = $modx->db->getRow($rs);
		
		// check to see if the user is allowed to save the document in the place he wants to save it in
		if ($existingDocument['parent'] == $form_v['parent']) return;
		
		if (!$modx->checkPermissions($form_v['parent'])) {
			if ($actionToTake == 'new') $url = "index.php?a=4";
			else                        $url = "index.php?a=27&id={$id}";
			$modx->manager->saveFormValues($_POST['mode']);
			$modx->webAlertAndQuit(sprintf($_lang['access_permission_parent_denied'], $id, $form_v['alias']), $url);
		}
	}
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

function getInputValues($id,$mode) {
	global $form_v;
	
	$db_v_names = explode(',', 'content,pagetitle,longtitle,type,description,alias,link_attributes,isfolder,richtext,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,contentType,content_dispo,donthit,menutitle,hidemenu,introtext,createdby,createdon');
	if($id) $fields['id'] = $id;
	foreach($db_v_names as $key) {
		if(!isset($form_v[$key])) $form_v[$key] = '';
		$fields[$key] = $form_v[$key];
	}
	if($mode==='edit') {
		unset($fields['createdby']);
		unset($fields['createdon']);
	}
	return $fields;
}

function checkStartDoc($id,$return_url) {
	global $modx,$form_v;

	if ($id == $modx->config['site_start']) {
		$published  = $form_v['published'];
		$pub_date   = $form_v['pub_date'];
		$unpub_date = $form_v['unpub_date'];
		if($published == 0) {
			$modx->webAlertAndQuit('Document is linked to site_start variable and cannot be unpublished!',$return_url);
		} elseif (($pub_date > $_SERVER['REQUEST_TIME'] || $unpub_date != "0")) {
			$modx->webAlertAndQuit('Document is linked to site_start variable and cannot have publish or unpublish dates set!',$return_url);
		}
	}
}

function checkParentID($id,$return_url) {
	global $modx,$form_v;

	if ($form_v['parent'] == $id) {
		$modx->webAlertAndQuit("Document can not be it's own parent!",$url);
	}
	else return $form_v['parent'];
}

function checkFolderStatus($id) {
	global $modx,$form_v;
	
	$isfolder = $form_v['isfolder'];
	// check to see document is a folder
	$rs = $modx->db->select('COUNT(id) AS count', '[+prefix+]site_content', "parent='{$id}'");
	if ($rs) {
		$row = $modx->db->getRow($rs);
		if ($row['count'] > 0) $isfolder = '1';
	} else {
		$modx->webAlertAndQuit("An error occured while attempting to find the document's children.",$url);
	}
	return $isfolder;
}

// keep original publish state, if change is not permitted
function getPublishPermission($field_name,$db_v) {
	global $modx,$form_v;
	if (!$modx->hasPermission('publish_document'))
		return $db_v[$field_name];
	else return $form_v[$field_name];
}

function checkPublished($db_v) {
	return getPublishPermission('published',$db_v);
}

function checkPub_date($db_v) {
	return getPublishPermission('pub_date',$db_v);
}

function checkUnpub_date($db_v) {
	return getPublishPermission('unpub_date',$db_v);
}

function checkPublishedon($db_v) {
	global $modx,$form_v;
	
	if(!$modx->hasPermission('publish_document'))
		return $db_v['publishedon'];
	else
	{
		// if it was changed from unpublished to published
		if(!empty($form_v['pub_date']) && $form_v['pub_date']<=$_SERVER['REQUEST_TIME'] && $form_v['published'])
			$publishedon = $form_v['pub_date'];
		elseif (0<$db_v['publishedon'] && $form_v['published'])
			$publishedon = $db_v['publishedon'];
		elseif(!$form_v['published'])
			$publishedon = 0;
		else
			$publishedon = $_SERVER['REQUEST_TIME'];
		return $publishedon;
	}
}

function checkPublishedby($db_v) {
	global $modx,$form_v;
	
	if(!$modx->hasPermission('publish_document'))
		return $db_v['publishedon'];
	else
	{
		// if it was changed from unpublished to published
		if(!empty($form_v['pub_date']) && $form_v['pub_date']<=$_SERVER['REQUEST_TIME'] && $form_v['published'])
			$publishedby = $db_v['publishedby'];
		elseif (0<$db_v['publishedon'] && $form_v['published'])
			$publishedby = $db_v['publishedby'];
		elseif(!$form_v['published'])
			$publishedby = 0;
		else
			$publishedby = $modx->getLoginUserID();
		return $publishedby;
	}
}

function getExistsValues($id, $return_url) {
	global $modx;
	$rs = $modx->db->select('*', '[+prefix+]site_content', "id='{$id}'");
	$row = $modx->db->getRow($rs);
	if (!$row) {
		$msg =  "An error occured while attempting to find the document's current parent.";
		$modx->webAlertAndQuit($msg, $return_url);
	}
	return $row;
}

function insert_tmplvars($newid,$tmplvars) {
	global $modx;
	if(empty($tmplvars)) return;
	$tvChanges = array();
	$tv['contentid'] = $newid;
	foreach ($tmplvars as $value) {
		if (is_array($value)) {
			$tv['tmplvarid'] = $value[0];
			$tv['value']     = $value[1];
			$tvChanges[] = $tv;
		}
	}
	if(!empty($tvChanges)) {
		foreach ($tvChanges as $tv) {
			$tv = $modx->db->escape($tv);
			$rs = $modx->db->insert($tv, '[+prefix+]site_tmplvar_contentvalues');
		}
	}
}

function update_tmplvars($id,$tmplvars) {
	global $modx;
	if(empty($tmplvars)) return;
	$tvChanges   = array();
	$tvAdded     = array();
	$tvDeletions = array();
	$rs = $modx->db->select('id, tmplvarid', '[+prefix+]site_tmplvar_contentvalues', "contentid='{$id}'");
	$tvIds = array ();
	while ($row = $modx->db->getRow($rs))
	{
		$tvIds[$row['tmplvarid']] = $row['id'];
	}
	$tv['contentid'] = $id;
	foreach ($tmplvars as $tmplvar)
	{
		if (!is_array($tmplvar)) {
			if (isset($tvIds[$tmplvar])) $tvDeletions[] = $tvIds[$tmplvar];
		} else {
			$tv['tmplvarid'] = $tmplvar[0];
			$tv['value']     = $tmplvar[1];
			if (isset($tvIds[$tmplvar[0]])) {
				$tvChanges[] = $tv;
			} else {
				$tvAdded[] = $tv;
			}
		}
	}
	
	if (!empty($tvDeletions)) {
		$where = 'id IN('.implode(',', $tvDeletions).')';
		$rs = $modx->db->delete('[+prefix+]site_tmplvar_contentvalues', $where);
	}
		
	if (!empty($tvAdded)) {
		foreach ($tvAdded as $tv) {
			$tv = $modx->db->escape($tv);
			$rs = $modx->db->insert($tv, '[+prefix+]site_tmplvar_contentvalues');
		}
	}
	
	if (!empty($tvChanges)) {
		foreach ($tvChanges as $tv) {
			$tv = $modx->db->escape($tv);
			$tvid = $tv['tmplvarid'];
			$rs = $modx->db->update($tv, '[+prefix+]site_tmplvar_contentvalues', "id='{$tvid}'");
		}
	}
}

// document access permissions
function setDocPermissionsNew($document_groups,$newid) {
	global $modx,$form_v;
	$parent = $form_v['parent'];
	$tbl_document_groups = $modx->getFullTableName('document_groups');
	
	$docgrp_save_attempt = false;
	if ($modx->config['use_udperms'] == 1 && is_array($document_groups))
	{
		$new_groups = array();
		foreach ($document_groups as $value_pair)
		{
			// first, split the pair (this is a new document, so ignore the second value
			$group = intval(substr($value_pair,0,strpos($value_pair,',')));
			// @see manager/actions/mutate_content.dynamic.php @ line 1138 (permissions list)
			$new_groups[] = "({$group},{$newid})";
		}
		$saved = true;
		if (!empty($new_groups))
		{
			$sql = 'INSERT INTO '.$tbl_document_groups.' (document_group, document) VALUES '. implode(',', $new_groups);
			$saved = $modx->db->query($sql) ? $saved : false;
			$docgrp_save_attempt = true;
		}
	}
	else
	{
		$isManager = $modx->hasPermission('access_permissions');
		$isWeb     = $modx->hasPermission('web_access_permissions');
		if($modx->config['use_udperms']==1 && !($isManager || $isWeb) && $parent != 0) {
			// inherit document access permissions
			$sql = "INSERT INTO {$tbl_document_groups} (document_group, document) SELECT document_group, {$newid} FROM {$tbl_document_groups} WHERE document='{$parent}'";
			$saved = $modx->db->query($sql);
			$docgrp_save_attempt = true;
		}
	}
	if ($docgrp_save_attempt && !$saved) {
		$msg = 'An error occured while attempting to add the document to a document_group.';
		$modx->webAlertAndQuit($msg);
	}
}

// update parent folder status
function updateParentStatus() {
	global $modx,$form_v;
	$parent = $form_v['parent'];
	if ($parent != 0) {
		$rs = $modx->db->update('isfolder=1', '[+prefix+]site_content', "id='{$parent}'");
		if (!$rs) {
			$msg = "An error occured while attempting to change the document's parent to a folder.";
			$modx->webAlertAndQuit($msg);
		}
	}
}

// redirect/stay options
function goNextAction($id) {
	global $form_v;
	
	$parent = $form_v['parent'];
	switch($form_v['stay']) {
		case '1':
			$header = 'Location: index.php?';
			if($form_v['type']==='document')
				$header .= 'a=4';
			elseif($form_v['type']==='reference')
				$header .= 'a=72';
			$header .= "pid={$parent}&r=1";
			break;
		case '2':
			$header = "Location: index.php?a=27&id={$id}&r=1&stay=2";
			break;
		default:
			if($parent!=='0')
				$header = "Location: index.php?a=3&id={$parent}&tab=0&r=1";
			else
				$header = "Location: index.php?a=3&id={$id}&r=1";
	}
	header($header);
	exit;
}

function setDocPermissionsEdit($document_groups,$id) {
	global $modx;
	
	if ($modx->config['use_udperms'] != 1 || !is_array($document_groups))
		return;
	
	$new_groups = array();
	// process the new input
	foreach ($document_groups as $value_pair)
	{ // @see manager/actions/mutate_content.dynamic.php @ line 1138 (permissions list)
		list($group, $link_id) = explode(',', $value_pair);
		$new_groups[$group] = $link_id;
	}

	// grab the current set of permissions on this document the user can access
	$isManager = intval($modx->hasPermission('access_permissions'));
	$isWeb     = intval($modx->hasPermission('web_access_permissions'));
	$fields = 'groups.id, groups.document_group';
	$from   = '[+prefix+]document_groups AS groups LEFT JOIN [+prefix+]documentgroup_names AS dgn ON dgn.id = groups.document_group';
	$where  = "((1={$isManager} AND dgn.private_memgroup) OR (1={$isWeb} AND dgn.private_webgroup)) AND groups.document = '{$id}'";
	$rs = $modx->db->select($fields,$from,$where);
	$old_groups = array();
	while ($row = $modx->db->getRow($rs)) {
		$old_groups[$row['document_group']] = $row['id'];
	}
	// update the permissions in the database
	$insertions = $deletions = array();
	foreach ($new_groups as $group => $link_id)
	{
		$group = intval($group);
		if (array_key_exists($group, $old_groups))
		{
			unset($old_groups[$group]);
			continue;
		}
		elseif ($link_id == 'new')
		{
			$insertions[] = "({$group},{$id})";
		}
	}
	$saved = true;
	if (!empty($insertions))
	{
		$tbl_document_groups = $modx->getFullTableName('document_groups');
		$sql_insert = 'INSERT INTO '.$tbl_document_groups.' (document_group, document) VALUES '.implode(',', $insertions);
		$saved = $modx->db->query($sql_insert) ? $saved : false;
	}
	if (!empty($old_groups))
	{
		$where = 'id IN (' . implode(',', $old_groups) . ')';
		$saved = $modx->db->delete('[+prefix+]document_groups',$where) ? $saved : false;
	}
	// necessary to remove all permissions as document is public
	if ((isset($_POST['chkalldocs']) && $_POST['chkalldocs'] == 'on'))
	{
		$saved = $modx->db->delete('[+prefix+]document_groups',"document='{$id}'") ? $saved : false;
	}
	if (!$saved)
	{
		$msg = 'An error occured while saving document groups.';
		$modx->webAlertAndQuit($msg);
	}
}

function folder2doc($parent) {
	global $modx;
	$rs = $modx->db->select('COUNT(id)', '[+prefix+]site_content', "parent={$parent}");
	if (!$rs)
		echo "An error occured while attempting to find the old parents' children.";
	$row = $modx->db->getRow($rs);
	if ($row['COUNT(id)'] == 0) {
		$rs = $modx->db->update('isfolder = 0', '[+prefix+]site_content', "id='{$parent}'");
		if (!$rs)
			echo "An error occured while attempting to change the old parent to a regular document.";
	}
}
