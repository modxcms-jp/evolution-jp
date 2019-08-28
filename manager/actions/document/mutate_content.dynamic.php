<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!isset($modx->config['preview_mode'])) {
    $modx->config['preview_mode'] = '1';
}
if(!isset($modx->config['tvs_below_content'])) {
    $modx->config['tvs_below_content'] = '0';
}

include_once(MODX_MANAGER_PATH . 'actions/document/mutate_content.functions.inc.php');
$modx->loadExtension('DocAPI');

$id = input_any('id'); // New is '0'
if($modx->manager->action==132||$modx->manager->action==131) {
    $modx->doc->mode = 'draft';
} else {
    $modx->doc->mode = 'normal';
}

checkPermissions($id);
if($id) {
    checkDocLock($id);
}

global $docObject;
$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

if($id) {
    $docObject = getValuesFromDB($id, $docgrp);
} else {
    $docObject = getInitialValues();
}

$modx->loadExtension('REVISION');
if($id && config('enable_draft')) {
    $modx->revisionObject = $modx->revision->getRevisionObject($id,'resource','template');
    if( $id && $modx->manager->action==131 && isset($modx->revisionObject['template']) ) //下書きのテンプレートに変更
        $docObject['template'] = $modx->revisionObject['template'];
} else {
    $modx->revisionObject = array();
}

if( isset($_REQUEST['newtemplate']) && preg_match('/\A[0-9]+\z/',$_REQUEST['newtemplate']) )
    $docObject['template'] = $_REQUEST['newtemplate'];

$tmplVars  = getTmplvars(input_any('id'),doc('template'),$docgrp);
$docObject += $tmplVars;

if($id && $modx->manager->action==131)
{
    $docObject = mergeDraft($id, $docObject);
    foreach($tmplVars as $k=>$v)
    {
        $tmplVars[$k] = $docObject[$k];
    }
}

$modx->manager->saveFormValues();
if($_POST) {
    $docObject = mergeReloadValues($docObject);
}

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = & $docObject;

$modx->event->vars['documentObject'] = & $docObject;
// invoke OnDocFormPrerender event
$tmp = array('id' => $id);
$evtOut = $modx->invokeEvent('OnDocFormPrerender', $tmp);
$modx->event->vars = array();

global $template; // For plugins (ManagerManager etc...)
$template = doc('template');

$selected_editor = (isset ($_POST['which_editor'])) ? $_POST['which_editor'] : config('which_editor');

checkViewUnpubDocPerm(doc('published'),doc('editedby'));// Only a=27

$_SESSION['itemname'] = $modx->hsc(doc('pagetitle'));

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
$ph['upload_maxsize'] = config('upload_maxsize') ? config('upload_maxsize') : 3145728;
$ph['mode'] = $modx->manager->action;
$ph['a'] = ($modx->doc->mode==='normal'&&$modx->hasPermission('save_document')) ? '5' : '128' ;
// 5:save_resource.processor.php 128:save_draft_content.processor.php

if(!$_REQUEST['pid']) {
    $tpl['head'] = str_replace('<input type="hidden" name="pid" value="[+pid+]" />', '', $tpl['head']);
} else {
    $ph['pid'] = $_REQUEST['pid'];
}

if($modx->doc->mode==='normal') {
    $ph['title'] = $_lang['create_resource_title'];
    $ph['class'] = '';
} else {
    $ph['title'] = $_lang['create_draft_title'];
    $ph['class'] = 'draft';
}
$ph['(ID:%s)'] = $id == 0 ? '' : sprintf('(ID:%s)', $id);

$ph['actionButtons'] = getActionButtons($id);
$ph['token'] = $modx->manager->makeToken();

echo parseText($tpl['head'],$ph);

$ph = array();
$ph['_lang_settings_general'] = $_lang['settings_general'];
$ph['fieldPagetitle']   = fieldPagetitle();
$ph['fieldLongtitle']   = fieldLongtitle();
$ph['fieldDescription'] = fieldDescription();
$ph['fieldAlias']       = fieldAlias($id);
$ph['fieldWeblink']     = doc('type')==='reference' ? fieldWeblink() : '';
$ph['fieldIntrotext']   = fieldIntrotext();
$ph['fieldTemplate']    = fieldTemplate();
$ph['fieldMenutitle']   = fieldMenutitle();
$ph['fieldMenuindex']   = fieldMenuindex();
$ph['renderSplit']      = renderSplit();
$ph['fieldParent']      = fieldParent();

$ph['sectionContent'] =  sectionContent();
$ph['sectionTV']      =  config('tvs_below_content') ? sectionTV() : '';

echo parseText($tpl['tab-page']['general'],$ph);


if(config('tvs_below_content')==0 && 0<count($tmplVars)) {
    $ph['TVFields'] = fieldsTV();
    $ph['_lang_tv'] = $_lang['tmplvars'];
    echo parseText($tpl['tab-page']['tv'],$ph);
}
$ph = array();
$ph['_lang_settings_page_settings'] = $_lang['settings_page_settings'];

if($modx->doc->mode==='normal') {
    $ph['fieldPublished'] = fieldPublished();
} else {
    $ph['fieldPublished'] = '';
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
if(doc('type') !== 'reference') {
    $ph['fieldContentType']   = fieldContentType();
    $ph['fieldContent_dispo'] = fieldContent_dispo();
} else {
    $ph['fieldContentType']   = html_tag(
        '<input>'
        , array(
            'type'  => 'hidden',
            'name'  => 'contentType',
            'value' => doc('contentType')
        )
    );
    $ph['fieldContent_dispo']   = html_tag(
        '<input>'
        , array(
            'type'  => 'hidden',
            'name'  => 'content_dispo',
            'value' => doc('content_dispo')
        )
    );
}
$ph['fieldLink_attributes'] = fieldLink_attributes();
$ph['fieldIsfolder']   = fieldIsfolder();
$ph['fieldRichtext']   = fieldRichtext();
$ph['fieldDonthit']    = config('track_visitors')==='1' ? fieldDonthit() : '';
$ph['fieldSearchable'] = fieldSearchable();
$ph['fieldCacheable']  = doc('type') === 'document' ? fieldCacheable() : '';
$ph['fieldSyncsite']   = fieldSyncsite();
echo parseText($tpl['tab-page']['settings'],$ph);



/*******************************
 * Document Access Permissions */
if (config('use_udperms') == 1)
{
    global $permissions_yes, $permissions_no;
    $permissions = getUDGroups($id);

    // See if the Access Permissions section is worth displaying...
    if (!empty($permissions)) {
        $ph = array();
        $ph['_lang_access_permissions'] = $_lang['access_permissions'];
        $ph['_lang_access_permissions_docs_message'] = $_lang['access_permissions_docs_message'];
        $ph['UDGroups'] = implode("\n", $permissions);
        echo parseText($tpl['tab-page']['access'],$ph);
    } elseif($_SESSION['mgrRole'] != 1 && $permissions_yes == 0 && $permissions_no > 0
        && (
            $_SESSION['mgrPermissions']['access_permissions'] == 1
            ||
            $_SESSION['mgrPermissions']['web_access_permissions'] == 1
        )
    ) {
        echo '<p>' . $_lang["access_permissions_docs_collision"] . '</p>';
    }
}
/* End Document Access Permissions *
 ***********************************/

// invoke OnDocFormRender event
$tmp = array('id' => $id);
$OnDocFormRender = $modx->invokeEvent('OnDocFormRender', $tmp);

$OnRichTextEditorInit = '';
if($modx->config['use_editor'] === '1') {
    $rte_fields = rte_fields();
    if ($rte_fields) {
        // invoke OnRichTextEditorInit event
        $tmp = array(
            'editor' => $selected_editor,
            'elements' => $rte_fields
        );
        $evtOut = $modx->invokeEvent('OnRichTextEditorInit', $tmp);
        if (is_array($evtOut)) {
            $OnRichTextEditorInit = implode('', $evtOut);
        }
    }
}
$ph['OnDocFormRender']      = is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '';
$ph['OnRichTextEditorInit'] = $OnRichTextEditorInit;
if ($modx->config['remember_last_tab'] === '2' || $_GET['stay'] === '2') {
    $ph['remember_last_tab'] = 'true';
} else {
    $ph['remember_last_tab'] = 'false';
}
echo parseText($tpl['foot'],$ph);
