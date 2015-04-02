<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!isset($modx->config['preview_mode']))      $modx->config['preview_mode'] = '1';
if(!isset($modx->config['tvs_below_content'])) $modx->config['tvs_below_content'] = '0';

include_once(MODX_MANAGER_PATH . 'actions/mutate_content.functions.inc.php');
$modx->loadExtension('DocAPI');

$id = getDocId(); // New is '0'

checkPermissions($id);
if($id) checkDocLock($id);

global $config, $docObject;
$config = & $modx->config;
$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

if($id) $docObject = getValuesFromDB($id,$docgrp);
else    $docObject = getInitialValues();

if($id) {
    $modx->loadExtension('REVISION');
    $modx->revisionObject = $modx->revision->getRevisionObject($id);
}
else $modx->revisionObject = array();
if(isset($modx->revisionObject['draft'])) $modx->hasDraft = '1';

$tmplVars  = getTmplvars($id,$docObject['template'],$docgrp);
$docObject = $docObject + $tmplVars;

if($id && $modx->manager->action==131)
{
    $docObject = mergeDraft($id, $docObject, 'draft');
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
$evtOut = $modx->invokeEvent('OnDocFormPrerender', array('id' => $id));
$modx->event->vars = array();

global $template, $selected_editor; // For plugins (ManagerManager etc...)
$template = $docObject['template'];

$selected_editor = (isset ($form_v['which_editor'])) ? $form_v['which_editor'] : $config['which_editor'];

checkViewUnpubDocPerm($docObject['published'],$docObject['editedby']);// Only a=27

$_SESSION['itemname'] = to_safestr($docObject['pagetitle']);

$tpl['head'] = getTplHead();
$tpl['foot'] = getTplFoot();
$tpl['tab-page']['general']  = getTplTabGeneral();
$tpl['tab-page']['tv']       = getTplTabTV();
$tpl['tab-page']['settings'] = getTplTabSettings();
$tpl['tab-page']['access']   = getTplTabAccess();

$ph = array();
$ph['JScripts'] = getJScripts();
$ph['OnDocFormPrerender']  = is_array($evtOut) ? implode("\n", $evtOut) : '';
$ph['id'] = $id;
$ph['upload_maxsize'] = $modx->config['upload_maxsize'] ? $modx->config['upload_maxsize'] : 3145728;
$ph['mode'] = $modx->manager->action;
$ph['a'] = ($modx->manager->action==27&&$modx->hasPermission('save_document')) ? '5' : '128' ;
// 5:save_resource.processor.php 128:save_draft_content.processor.php

if(!$_REQUEST['pid'])
	$tpl['head'] = str_replace('<input type="hidden" name="pid" value="[+pid+]" />','',$tpl['head']);
else $ph['pid'] = $_REQUEST['pid'];

if($modx->manager->action==131)
{
	$ph['title'] = $id!=0 ? "{$_lang['edit_draft_title']}(ID:{$id})" : $_lang['create_draft_title'];
    $ph['class'] = 'draft';
}
else
{
	$ph['title'] = $id!=0 ? "{$_lang['edit_resource_title']}(ID:{$id})" : $_lang['create_resource_title'];
    $ph['class'] = '';
}
$ph['actionButtons'] = getActionButtons($id);
$ph['remember_last_tab'] = ($config['remember_last_tab'] === '2' || $_GET['stay'] === '2') ? 'true' : 'false';
$ph['token'] = $modx->genToken();
$_SESSION['token'] = $ph['token'];

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
$ph['fieldPublished']  =  fieldPublished();
$ph['fieldPub_date']   = fieldPub_date($id);
$ph['fieldUnpub_date'] = fieldUnpub_date($id);
$ph['renderSplit'] = renderSplit();
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
$OnDocFormRender = $modx->invokeEvent('OnDocFormRender', array(
	'id' => $id,
));

$OnRichTextEditorInit = '';
if($modx->config['use_editor'] === '1') {
	if(is_array($rte_field) && 0<count($rte_field)) {
		// invoke OnRichTextEditorInit event
		$evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
			'editor' => $selected_editor,
			'elements' => $rte_field
		));
		if (is_array($evtOut)) $OnRichTextEditorInit = implode('', $evtOut);
	}
}
$ph['OnDocFormRender']      = is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '';
$ph['OnRichTextEditorInit'] = $OnRichTextEditorInit;
echo $modx->parseText($tpl['foot'],$ph);

