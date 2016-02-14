<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!isset($modx->config['preview_mode']))      $modx->config['preview_mode'] = '1';
if(!isset($modx->config['tvs_below_content'])) $modx->config['tvs_below_content'] = '0';

include_once(MODX_MANAGER_PATH . 'actions/document/mutate_content.functions.inc.php');
$modx->loadExtension('DocAPI');

$id = getDocId(); // New is '0'
if($modx->manager->action==132||$modx->manager->action==131)
	$modx->doc->mode = 'draft';
else $modx->doc->mode = 'normal';

checkPermissions($id);
if($id) checkDocLock($id);

global $config, $docObject;
$config = & $modx->config;
$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

if($id) $docObject = getValuesFromDB($id,$docgrp);
else    $docObject = getInitialValues();

$modx->loadExtension('REVISION');
if($id && $modx->config['enable_draft']) {
    $modx->revisionObject = $modx->revision->getRevisionObject($id,'resource','template');
    if( $id && $modx->manager->action==131 && isset($modx->revisionObject['template']) ) //下書きのテンプレートに変更
        $docObject['template'] = $modx->revisionObject['template'];
}
else $modx->revisionObject = array();

if( isset($_REQUEST['newtemplate']) && preg_match('/\A[0-9]+\z/',$_REQUEST['newtemplate']) )
  $docObject['template'] = $_REQUEST['newtemplate'];

$tmplVars  = getTmplvars($id,$docObject['template'],$docgrp);
$docObject = $docObject + $tmplVars;

if($id && $modx->manager->action==131)
{
    $docObject = mergeDraft($id, $docObject);
    foreach($tmplVars as $k=>$v)
    {
        $tmplVars[$k] = $docObject[$k];
    }
}

$modx->manager->saveFormValues();
if($_POST)
    $docObject = mergeReloadValues($docObject);

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = & $docObject;

$modx->event->vars['documentObject'] = & $docObject;
// invoke OnDocFormPrerender event
$tmp = array('id' => $id);
$evtOut = $modx->invokeEvent('OnDocFormPrerender', $tmp);
$modx->event->vars = array();

global $template; // For plugins (ManagerManager etc...)
$template = $docObject['template'];

$selected_editor = (isset ($_POST['which_editor'])) ? $_POST['which_editor'] : $config['which_editor'];

checkViewUnpubDocPerm($docObject['published'],$docObject['editedby']);// Only a=27

$_SESSION['itemname'] = to_safestr($docObject['pagetitle']);

$tpl['head'] = getTplHead();
$tpl['foot'] = getTplFoot();
$tpl['tab-page']['general']  = getTplTabGeneral();
$tpl['tab-page']['tv']       = getTplTabTV();
$tpl['tab-page']['settings'] = getTplTabSettings();
$tpl['tab-page']['access']   = getTplTabAccess();

$ph = array();
$ph['JScripts'] = getJScripts($id);
$ph['OnDocFormPrerender']  = is_array($evtOut) ? implode("\n", $evtOut) : '';
$ph['id'] = $id;
$ph['upload_maxsize'] = $modx->config['upload_maxsize'] ? $modx->config['upload_maxsize'] : 3145728;
$ph['mode'] = $modx->manager->action;
$ph['a'] = ($modx->doc->mode==='normal'&&$modx->hasPermission('save_document')) ? '5' : '128' ;
// 5:save_resource.processor.php 128:save_draft_content.processor.php

if(!$_REQUEST['pid'])
	$tpl['head'] = str_replace('<input type="hidden" name="pid" value="[+pid+]" />','',$tpl['head']);
else $ph['pid'] = $_REQUEST['pid'];

if($modx->doc->mode==='normal') {
	$ph['title'] = $id!=0 ? "{$_lang['edit_resource_title']}(ID:{$id})" : $_lang['create_resource_title'];
	$ph['class'] = '';
} else {
	$ph['title'] = $id!=0 ? "{$_lang['edit_draft_title']}(ID:{$id})" : $_lang['create_draft_title'];
    $ph['class'] = 'draft';
}

$ph['actionButtons'] = getActionButtons($id);
$ph['token'] = $modx->genToken();
$_SESSION['token'] = $ph['token'];
$ph['remember_last_tab'] = ($modx->config['remember_last_tab'] === '2' || $_GET['stay'] === '2') ? 'true' : 'false';

echo $modx->parseText($tpl['head'],$ph);

$ph = array();
$ph['_lang_settings_general'] = $_lang['settings_general'];
$ph['fieldPagetitle']   = fieldPagetitle();
$ph['fieldLongtitle']   = fieldLongtitle();
$ph['fieldDescription'] = fieldDescription();
$ph['fieldAlias']       = fieldAlias($id);
$ph['fieldWeblink']     = ($docObject['type']==='reference') ? fieldWeblink() : '';
$ph['fieldIntrotext']   = fieldIntrotext();
$ph['fieldTemplate']    = fieldTemplate();
$ph['fieldMenutitle']   = fieldMenutitle();
$ph['fieldMenuindex']   = fieldMenuindex();
$ph['renderSplit']      = renderSplit();
$ph['fieldParent']      = fieldParent();

$ph['sectionContent'] =  sectionContent();
$ph['sectionTV']      =  $modx->config['tvs_below_content'] ? sectionTV() : '';

echo $modx->parseText($tpl['tab-page']['general'],$ph);


if($modx->config['tvs_below_content']==0&&0<count($tmplVars)) {
	$ph['TVFields'] = fieldsTV();
	$ph['_lang_tv'] = $_lang['tmplvars'];
	echo $modx->parseText($tpl['tab-page']['tv'],$ph);
}
$ph = array();
$ph['_lang_settings_page_settings'] = $_lang['settings_page_settings'];

if($modx->doc->mode==='normal') {
	$ph['fieldPublished'] =  fieldPublished();
}

$ph['fieldPub_date']   = fieldPub_date($id);
$ph['fieldUnpub_date'] = fieldUnpub_date($id);

//下書きでかつ採用日の指定がない場合はSplit1は表示しない
if( empty($ph['fieldPub_date']) ){
	$ph['renderSplit1'] = '';
}else{
	$ph['renderSplit1'] = renderSplit();
}
$ph['renderSplit2'] = renderSplit();

$ph['fieldType'] = fieldType();
if($docObject['type'] !== 'reference') {
	$ph['fieldContentType']   = fieldContentType();
	$ph['fieldContent_dispo'] = fieldContent_dispo();
} else {
	$ph['fieldContentType']   = sprintf('<input type="hidden" name="contentType" value="%s" />',$docObject['contentType']);
	$ph['fieldContent_dispo'] = sprintf('<input type="hidden" name="content_dispo" value="%s" />',$docObject['content_dispo']);
}
$ph['fieldLink_attributes'] = fieldLink_attributes();
$ph['fieldIsfolder']   = fieldIsfolder();
$ph['fieldRichtext']   = fieldRichtext();
$ph['fieldDonthit']    = $modx->config['track_visitors']==='1' ? fieldDonthit() : '';
$ph['fieldSearchable'] = fieldSearchable();
$ph['fieldCacheable']  = $docObject['type'] === 'document' ? fieldCacheable() : '';
$ph['fieldSyncsite']   = fieldSyncsite();
echo $modx->parseText($tpl['tab-page']['settings'],$ph);



/*******************************
 * Document Access Permissions */
if ($modx->config['use_udperms'] == 1)
{
	global $permissions_yes, $permissions_no;
	$permissions = getUDGroups($id);
	
	// See if the Access Permissions section is worth displaying...
	if (!empty($permissions)):
		$ph = array();
		$ph['_lang_access_permissions'] = $_lang['access_permissions'];
		$ph['_lang_access_permissions_docs_message'] = $_lang['access_permissions_docs_message'];
		$ph['UDGroups'] = implode("\n", $permissions);
		echo $modx->parseText($tpl['tab-page']['access'],$ph);
	elseif($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0)
           && ($_SESSION['mgrPermissions']['access_permissions'] == 1
           || $_SESSION['mgrPermissions']['web_access_permissions'] == 1)):
		echo '<p>' . $_lang["access_permissions_docs_collision"] . '</p>';
	endif;
}
/* End Document Access Permissions *
 ***********************************/

// invoke OnDocFormRender event
$tmp = array('id' => $id);
$OnDocFormRender = $modx->invokeEvent('OnDocFormRender', $tmp);

$OnRichTextEditorInit = '';
if($modx->config['use_editor'] === '1') {
	if(is_array($rte_field) && 0<count($rte_field)) {
		// invoke OnRichTextEditorInit event
    $tmp = array(
			'editor' => $selected_editor,
			'elements' => $rte_field
		);
		$evtOut = $modx->invokeEvent('OnRichTextEditorInit', $tmp);
		if (is_array($evtOut)) $OnRichTextEditorInit = implode('', $evtOut);
	}
}
$ph['OnDocFormRender']      = is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '';
$ph['OnRichTextEditorInit'] = $OnRichTextEditorInit;
echo $modx->parseText($tpl['foot'],$ph);

