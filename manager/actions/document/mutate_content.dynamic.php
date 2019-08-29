<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!isset($modx->config['preview_mode'])) {
    $modx->config['preview_mode'] = '1';
}
if(!isset($modx->config['tvs_below_content'])) {
    $modx->config['tvs_below_content'] = '0';
}

include_once(MODX_MANAGER_PATH . 'actions/document/mutate_content.functions.inc.php');
evo()->loadExtension('DocAPI');

if(evo()->manager->action==132||evo()->manager->action==131) {
    $modx->doc->mode = 'draft';
} else {
    $modx->doc->mode = 'normal';
}

checkPermissions(input_any('id'));
if(input_any('id')) {
    checkDocLock(input_any('id'));
}

$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

global $docObject;
$docObject = input_any('id') ? getValuesFromDB(input_any('id'), $docgrp) : getInitialValues();

evo()->loadExtension('REVISION');
if(input_any('id') && config('enable_draft')) {
    $modx->revisionObject = evo()->revision->getRevisionObject(input_any('id'),'resource','template');
    if( input_any('id') && evo()->manager->action==131 && isset($modx->revisionObject['template']) ) //下書きのテンプレートに変更
        $docObject['template'] = evo()->revisionObject['template'];
} else {
    $modx->revisionObject = array();
}

if(preg_match('/[1-9][0-9]*/', evo()->input_any('newtemplate')) ) {
    $docObject['template'] = evo()->input_any('newtemplate');
}

$tmplVars  = getTmplvars(input_any('id'),doc('template'),$docgrp);
$docObject += $tmplVars;

if(input_any('id') && evo()->manager->action==131) {
    $docObject = mergeDraft(input_any('id'), $docObject);
    foreach($tmplVars as $k=>$v) {
        $tmplVars[$k] = $docObject[$k];
    }
}

evo()->manager->saveFormValues();
if(evo()->input_post()) {
    $docObject = mergeReloadValues($docObject);
}

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = & $docObject;

$modx->event->vars['documentObject'] = & $docObject;
// invoke OnDocFormPrerender event
$tmp = array('id' => input_any('id'));
$evtOut = evo()->invokeEvent('OnDocFormPrerender', $tmp);
$modx->event->vars = array();

global $template; // For plugins (ManagerManager etc...)
$template = doc('template');

checkViewUnpubDocPerm(doc('published'),doc('editedby'));// Only a=27

$_SESSION['itemname'] = evo()->hsc(doc('pagetitle'));

$tpl['header']       = file_get_tpl('_header.tpl');
$tpl['footer']       = file_get_tpl('_footer.tpl');
$tpl['tab_general']  = file_get_tpl('tab_general.tpl');
$tpl['tab_tv']       = file_get_tpl('tab_tv.tpl');
$tpl['tab_settings'] = file_get_tpl('tab_settings.tpl');
$tpl['tab_access']   = file_get_tpl('tab_access.tpl');

if(evo()->input_any('pid')) {
    $tpl['header'] = str_replace('<input type="hidden" name="pid" value="[+pid+]" />', '', $tpl['header']);
}
echo parseText(
    $tpl['header']
    , collect_header_ph(input_any('id'), $evtOut)
);

echo parseText(
    $tpl['tab_general']
    , collect_tab_general_ph()
);

if(config('tvs_below_content')==0 && $tmplVars) {
    echo parseText(
        $tpl['tab_tv']
        , collect_tab_tv_ph()
    );
}

echo parseText(
    $tpl['tab_settings']
    , collect_tab_settings_ph()
);

if (config('use_udperms') == 1) {
    global $permissions_yes, $permissions_no;
    $permissions = getUDGroups(input_any('id'));

    // See if the Access Permissions section is worth displaying...
    if ($permissions) {
        $ph = array();
        $ph['_lang_access_permissions'] = lang('access_permissions');
        $ph['_lang_access_permissions_docs_message'] = lang('access_permissions_docs_message');
        $ph['UDGroups'] = implode("\n", $permissions);
        echo parseText($tpl['tab_access'],$ph);
    } elseif(evo()->session_var('mgrRole') != 1 && $permissions_yes == 0 && $permissions_no > 0
        && (
            evo()->session_var('mgrPermissions.access_permissions') == 1
            ||
            evo()->session_var('mgrPermissions.web_access_permissions') == 1
        )
    ) {
        echo '<p>' . lang('access_permissions_docs_collision') . '</p>';
    }
}

// invoke OnDocFormRender event
$tmp = array('id' => input_any('id'));
$OnDocFormRender = evo()->invokeEvent('OnDocFormRender', $tmp);

$OnRichTextEditorInit = '';
if(evo()->config['use_editor'] === '1') {
    $rte_fields = rte_fields();
    if ($rte_fields) {
        // invoke OnRichTextEditorInit event
        $tmp = array(
            'editor' => evo()->input_post('which_editor',config('which_editor')),
            'elements' => $rte_fields
        );
        $evtOut = evo()->invokeEvent('OnRichTextEditorInit', $tmp);
        if (is_array($evtOut)) {
            $OnRichTextEditorInit = implode('', $evtOut);
        }
    }
}
$ph['OnDocFormRender']      = is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '';
$ph['OnRichTextEditorInit'] = $OnRichTextEditorInit;
if (evo()->config['remember_last_tab'] === '2' || $_GET['stay'] === '2') {
    $ph['remember_last_tab'] = 'true';
} else {
    $ph['remember_last_tab'] = 'false';
}
echo parseText($tpl['footer'],$ph);
