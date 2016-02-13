<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

global $form_v;
$modx->loadExtension('DocAPI');
$form_v = $modx->doc->fixTvNest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
$form_v = $modx->doc->initValue($form_v);
$form_v = $modx->doc->setValue($form_v);

// preprocess POST values
$id = $form_v['id'];
if(!preg_match('@^[0-9]*$@',$id) || ($_POST['mode'] == '27' && empty($id)))
{
	$e->setError(2);
	$e->dumpError();
}

if($_POST['mode'] == '27') $actionToTake = 'edit';
else                       $actionToTake = 'new';

$document_groups = getDocGroups();

checkDocPermission($id,$document_groups);

$modx->manager->saveFormValues();

switch ($actionToTake) {
	case 'new' :
		$return_url = 'index.php?a=' . $_GET['a'];
		
		// invoke OnBeforeDocFormSave event
		$temp_id = $modx->doc->getNewDocID();
		$values = getInputValues($temp_id,'new');
		if(!empty($form_v['template']))
			$tmplvars = get_tmplvars();
		else
			$tmplvars = array();

		$param = array('mode'  => 'new',
					   'doc_vars' => $values,
					   'tv_vars'  => $tmplvars);

		$modx->invokeEvent('OnBeforeDocFormSave', $param);

		$values = $modx->db->escape($param['doc_vars']);
		$newid = $modx->db->insert($values,'[+prefix+]site_content');
		if(!$newid) {
			$msg = 'An error occured while attempting to save the new document: ' . $modx->db->getLastError();
			$modx->webAlertAndQuit($msg, $return_url);
		}

		if(!empty($param['tv_vars'])) {
			insert_tmplvars($newid,$param['tv_vars']);
		}

		setDocPermissionsNew($document_groups,$newid);

		updateParentStatus();

		if($modx->config['use_udperms']==='1') {
			$modx->manager->setWebDocsAsPrivate($newid);
			$modx->manager->setMgrDocsAsPrivate($newid);
		}
		
		if($form_v['syncsite'] == 1) $modx->clearCache();

        // invoke OnDocFormSave event
		$modx->event->vars = array('mode'=>'new','id'=>$newid);
		$modx->invokeEvent('OnDocFormSave', $modx->event->vars);

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
		$form_v['pub_date']	= checkPub_date($db_v);
		$form_v['unpub_date']  = checkUnpub_date($db_v);
		$form_v['publishedon'] = checkPublishedon($db_v['publishedon']);
		$form_v['publishedby'] = checkPublishedby($db_v);
		
		// invoke OnBeforeDocFormSave event
		$values = getInputValues($id,'edit');
		if(!empty($form_v['template']))
			$tmplvars = get_tmplvars($id);
		else
			$tmplvars = array();

		$param = array('mode' => 'upd',
					   'id'   => $id,
					   'doc_vars' => $values,
					   'tv_vars'  => $tmplvars);
		
		$modx->invokeEvent('OnBeforeDocFormSave', $param);
		
		$values = $modx->db->escape($param['doc_vars']);
		$rs = $modx->db->update($values,'[+prefix+]site_content',"id='{$id}'");
		if (!$rs) {
			$msg = "An error occured while attempting to save the edited document. The generated SQL is: <i> {$sql} </i>.";
			$modx->webAlertAndQuit($msg, $return_url);
		}
		
		if(!empty($param['tv_vars'])) {
			update_tmplvars($id,$param['tv_vars']);
		}
		
		setDocPermissionsEdit($document_groups,$id);

		updateParentStatus();
		
		// finished moving the document, now check to see if the old_parent should no longer be a folder
		if($db_v['parent']!=='0') folder2doc($db_v['parent']);

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

        // invoke OnDocFormSave event
		$modx->event->vars = array('mode'=>'upd','id'=>$id);
		$modx->invokeEvent('OnDocFormSave', $modx->event->vars);

		goNextAction($id);
		break;
	default :
		header("Location: index.php?a=7");
}

function get_tmplvars($id=0)
{
	global $modx,$form_v;

	$template = $form_v['template'];
	
	if(empty($template)) return array();
	
	// get document groups for current user
	if ($_SESSION['mgrDocgroups'])
	{
		$docgrp = implode(',', $_SESSION['mgrDocgroups']);
	}
	
	$from[] = '[+prefix+]site_tmplvars AS tv';
	$from[] = 'INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid = tv.id';
	$from[] = 'LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc ON tvc.tmplvarid=tv.id';
	if($id) $from[] = "AND tvc.contentid = '{$id}'";
	$from[] = 'LEFT JOIN [+prefix+]site_tmplvar_access tva ON tva.tmplvarid=tv.id';
	$tva_docgrp = ($docgrp) ? "OR tva.documentgroup IN ({$docgrp})" : '';
	$where = "tvtpl.templateid = '{$template}' AND (1='{$_SESSION['mgrRole']}' OR ISNULL(tva.documentgroup) {$tva_docgrp})";
	$orderby = 'tv.rank';
	$from = join(' ', $from);
	$rs = $modx->db->select('DISTINCT tv.*',$from,$where,$orderby);
	
	$tmplvars = array ();
	while ($row = $modx->db->getRow($rs)):
		$tmplvar = '';
		$tvid = "tv{$row['id']}";
		
		if(!isset($form_v[$tvid]) && $row['type']!=='checkbox' && $row['type']!=='listbox-multiple')
			continue;
		
		if($row['type']==='url') {
			if( $form_v["{$tvid}_prefix"] === 'DocID' ){
		$value = $form_v[$tvid];
		if( preg_match('/\A[0-9]+\z/',$value) ) 
		  $value = '[~' . $value . '~]';
	  }elseif($form_v["{$tvid}_prefix"] !== '--') {
				$value = str_replace(array ('feed://','ftp://','http://','https://','mailto:'), '', $form_v[$tvid]);
				$value = $form_v["{$tvid}_prefix"] . $value;
			}
			else $value = $form_v[$tvid];
		}
		elseif($row['type']==='file')	$value = $form_v[$tvid];
		else {
			if(is_array($form_v[$tvid])) {
				// handles checkboxes & multiple selects elements
				$value = implode('||', $form_v[$tvid]);
			}
			elseif(isset($form_v[$tvid])) $value = $form_v[$tvid];
			else						  $value = '';
		}
		// save value if it was modified
		if(substr($row['default_text'], 0, 6) === '@@EVAL') {
		 	$eval_str = trim(substr($row['default_text'], 7));
			$row['default_text'] = eval($eval_str);
		}
		if (strlen($value) > 0 && $value != $row['default_text'])
		{
			$tmplvars[$row['id']] = $value;
		}
		else $tmplvars[$row['id']] = false; // Mark the variable for deletion
	endwhile;
	return $tmplvars;
}

function get_alias($id,$alias,$parent,$pagetitle)
{
	global $modx;
	
	if($alias) $alias = $modx->stripAlias($alias);
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
	global $modx,$_lang;
	
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
		$modx->manager->saveFormValues($_POST['mode']);
		
		$url = 'index.php?a=' . $_POST['mode'];
		if ($_POST['mode'] == '27') $url .= "&id={$id}";
		elseif($_REQUEST['pid'])	$url .= '&pid=' . $_REQUEST['pid'];
		
		if($_REQUEST['stay']) $url .= '&stay=' . $_REQUEST['stay'];
		
		$modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid, $alias), $url);
	}
	return $alias;
}

function checkDocPermission($id,$document_groups=array()) {
	global $modx,$form_v,$_lang,$e;
	// ensure that user has not made this document inaccessible to themselves
	if($_SESSION['mgrRole'] != 1 && is_array($document_groups) && !empty($document_groups))
	{
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
				else						$url = "index.php?a=27&id={$id}";
				
				$modx->manager->saveFormValues();
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
			else						$url = "index.php?a=27&id={$id}";
			$modx->manager->saveFormValues();
			$modx->webAlertAndQuit(sprintf($_lang['access_permission_parent_denied'], $id, $form_v['alias']), $url);
		}
	}
}


function getInputValues($id=0,$mode='new') {
	global $modx,$form_v;
	
	$db_v_names = explode(',', 'content,pagetitle,longtitle,type,description,alias,link_attributes,isfolder,richtext,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,contentType,content_dispo,donthit,menutitle,hidemenu,introtext,createdby,createdon');
	if($id) $fields['id'] = $id;
	foreach($db_v_names as $key) {
		if(!isset($form_v[$key])) $form_v[$key] = '';
		$fields[$key] = $form_v[$key];
	}
	$fields['editedby'] = $modx->getLoginUserID();
	if($mode==='new') {
		$fields['publishedon'] = checkPublishedon(0);
	}
	elseif($mode==='edit') {
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

function checkPublishedon($timestamp) {
	global $modx,$form_v;
	
	if(!$modx->hasPermission('publish_document'))
		return $timestamp;
	else
	{
		// if it was changed from unpublished to published
		if(!empty($form_v['pub_date']) && $form_v['pub_date']<=$_SERVER['REQUEST_TIME'] && $form_v['published'])
			$publishedon = $form_v['pub_date'];
		elseif (0<$timestamp && $form_v['published'])
			$publishedon = $timestamp;
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

function insert_tmplvars($docid,$tmplvars) {
	global $modx;
	if(empty($tmplvars)) return;
	$tvChanges = array();
	$tv['contentid'] = $docid;
	foreach ($tmplvars as $tmplvarid=>$value) {
		if ($value!==false) {
			$tv['tmplvarid'] = $tmplvarid;
			$tv['value']	 = $value;
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

function update_tmplvars($docid,$tmplvars) {
	global $modx;
	if(empty($tmplvars)) return;
	$tvChanges   = array();
	$tvAdded	 = array();
	$tvDeletions = array();
	$rs = $modx->db->select('id, tmplvarid', '[+prefix+]site_tmplvar_contentvalues', "contentid='{$docid}'");
	$tvIds = array ();
	while ($row = $modx->db->getRow($rs))
	{
		$tvIds[$row['tmplvarid']] = $row['id'];
	}
	$tv['contentid'] = $docid;
	foreach ($tmplvars as $tmplvarid=>$value)
	{
		if ($value===false) {
			if (isset($tvIds[$tmplvarid])) $tvDeletions[] = $tvIds[$tmplvarid];
		} else {
			$tv['tmplvarid'] = $tmplvarid;
			$tv['value']	 = $value;
			if (isset($tvIds[$tmplvarid])) {
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
			$rs = $modx->db->update($tv, '[+prefix+]site_tmplvar_contentvalues', "tmplvarid='{$tvid}' AND contentid='{$docid}'");
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
		$isWeb	 = $modx->hasPermission('web_access_permissions');
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
	if($form_v['type']==='reference') $a = '4';
	else							  $a = '72';
	switch($form_v['stay']) {
		case 'new':  $header = "Location: index.php?a={$a}&pid={$parent}&r=1&stay=new"; break;
		case 'stay': $header = "Location: index.php?a=27&id={$id}&r=1&stay=stay"; break;
		default:
			if($parent!=0) $header = "Location: index.php?a=120&id={$parent}&r=1";
			else		   $header = "Location: index.php?a=3&id={$id}&r=1";
	}
	header($header);
}

function setDocPermissionsEdit($document_groups,$id) {
	global $modx;
	
	if ($modx->config['use_udperms'] != 1 || !is_array($document_groups))
		return;
	
	$new_groups = array();
	// process the new input
	foreach ($document_groups as $value_pair)
	{
		list($group, $link_id) = explode(',', $value_pair);
		$new_groups[$group] = $link_id;
	}

	// grab the current set of permissions on this document the user can access
	$isManager = intval($modx->hasPermission('access_permissions'));
	$isWeb	 = intval($modx->hasPermission('web_access_permissions'));
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

function getDocGroups()
{
	if(isset($_POST['chkalldocs']) && $_POST['chkalldocs'] === 'on')
		$rs = array();
	elseif(!isset($_POST['docgroups']))
		$rs = array();
	else
		$rs = $_POST['docgroups'];
	return $rs;
}
