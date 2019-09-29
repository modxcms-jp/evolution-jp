<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;

include_once(MODX_CORE_PATH . 'helpers.php');
include_once(__DIR__ . '/mutate_content/functions.php');
include_once(tpl_base_dir().'fields.php');
include_once(tpl_base_dir().'action_buttons.php');

// $modx->config['custom_tpl_dir'] = 'manager/actions/document/mutate_content/test/';

evo()->loadExtension('DocAPI');

if(manager()->action==132||manager()->action==131) {
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
$docObject = input_any('id') ? db_value(input_any('id'), $docgrp) : default_value(input_any('pid'),input_any('newtemplate'));

evo()->loadExtension('REVISION');
if(input_any('id') && config('enable_draft')) {
    $modx->revisionObject = evo()->revision->getRevisionObject(input_any('id'),'resource','template');
    if( input_any('id') && manager()->action==131 && isset($modx->revisionObject['template']) ) //下書きのテンプレートに変更
        $docObject['template'] = evo()->revisionObject['template'];
} else {
    $modx->revisionObject = array();
}

if(preg_match('/[1-9][0-9]*/', input_any('newtemplate')) ) {
    $docObject['template'] = input_any('newtemplate');
}

$tmplVars  = getTmplvars(input_any('id'),doc('template'),$docgrp);
$docObject += $tmplVars;

if(input_any('id') && manager()->action==131) {
    $docObject = mergeDraft(input_any('id'), $docObject);
    foreach($tmplVars as $k=>$v) {
        $tmplVars[$k] = $docObject[$k];
    }
}

manager()->saveFormValues();
if(evo()->input_post()) {
    $docObject = mergeReloadValues($docObject);
}

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = & $docObject;

$modx->event->vars['documentObject'] = & $docObject;
// invoke OnDocFormPrerender event
$tmp = array('id' => input_any('id'));
$OnDocFormPrerender = evo()->invokeEvent('OnDocFormPrerender', $tmp);
$modx->event->vars = array();

global $template; // For plugins (ManagerManager etc...)
$template = doc('template');

checkViewUnpubDocPerm(doc('published'),doc('editedby'));// Only a=27

$_SESSION['itemname'] = evo()->hsc(doc('pagetitle'));

$body = array();
$body[] = parseText(
    file_get_tpl('tab_general.tpl')
    , collect_tab_general_ph(input_any('id'))
);

if(config('tvs_below_content',1)==0 && $tmplVars) {
    $body[] = parseText(
        file_get_tpl('tab_tv.tpl')
        , collect_tab_tv_ph()
    );
}

$body[] = parseText(
    file_get_tpl('tab_settings.tpl')
    , collect_tab_settings_ph(input_any('id'))
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
        $body[] = parseText(file_get_tpl('tab_access.tpl'),$ph);
    } elseif(evo()->session_var('mgrRole') != 1 && $permissions_yes == 0 && $permissions_no > 0
        && (
            evo()->session_var('mgrPermissions.access_permissions') == 1
            ||
            evo()->session_var('mgrPermissions.web_access_permissions') == 1
        )
    ) {
        $body[] = '<p>' . lang('access_permissions_docs_collision') . '</p>';
    }
}

// invoke OnDocFormRender event
$tmp = array('id' => input_any('id'));
$OnDocFormRender = evo()->invokeEvent('OnDocFormRender', $tmp);

$OnRichTextEditorInit = '';
if(config('use_editor') === '1') {
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

$template = file_get_tpl('_template.tpl');
if(evo()->input_any('pid')) {
    $template = str_replace('<input type="hidden" name="pid" value="[+pid+]" />', '', $template);
}
$ph = collect_template_ph(input_any('id'), $OnDocFormPrerender, $OnDocFormRender, $OnRichTextEditorInit);
$ph['content'] = implode("\n", $body);
echo parseText($template, $ph);
