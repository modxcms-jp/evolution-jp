<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

include_once(__DIR__ . '/mutate_content/functions.php');
include_once(tpl_base_dir() . 'fields.php');
include_once(tpl_base_dir() . 'action_buttons.php');

// $modx->config['custom_tpl_dir'] = 'manager/actions/document/mutate_content/test/';

evo()->loadExtension('DocAPI');

$modx->doc->mode = 'normal';

checkPermissions(request_intvar('id'));
if (request_intvar('id')) {
    checkDocLock(request_intvar('id'));
}

$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

global $docObject;
if (request_intvar('id')) {
    $docObject = db_value(request_intvar('id'), $docgrp);
} else {
    $docObject = default_value(request_intvar('pid'), request_intvar('newtemplate'));
}

evo()->loadExtension('REVISION');
if (request_intvar('id') && config('enable_draft')) {
    $modx->revisionObject = evo()->revision->getRevisionObject(
        request_intvar('id'),
        'resource',
        'template'
    );
} else {
    $modx->revisionObject = [];
}

if (preg_match('/[1-9][0-9]*/', request_intvar('newtemplate'))) {
    $docObject['template'] = request_intvar('newtemplate');
}

$tmplVars = getTmplvars(request_intvar('id'), doc('template'), $docgrp);
$docObject += $tmplVars;

manager()->saveFormValues();
if (postv()) {
    $docObject = mergeReloadValues($docObject);
}

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = &$docObject;

$modx->event->vars['documentObject'] = &$docObject;
// invoke OnDocFormPrerender event
$tmp = ['id' => request_intvar('id')];
$OnDocFormPrerender = evo()->invokeEvent('OnDocFormPrerender', $tmp);
$modx->event->vars = [];

global $template; // For plugins (ManagerManager etc...)
$template = doc('template');

checkViewUnpubDocPerm(doc('published'), doc('editedby'));// Only a=27

$_SESSION['itemname'] = evo()->hsc(doc('pagetitle'));

$token = sessionv('token');
if (!$token) {
    $token = $modx->genTokenString();
    sessionv('*token', $token);
    evo()->logEvent(
        0,
        1,
        sprintf(
            'Preview token refreshed for resource edit (id:%s, prefix:%s, length:%s)',
            request_intvar('id') ?: 'new',
            substr($token, 0, 8),
            strlen($token)
        ),
        'mutate_content'
    );
}
sessionv('*token', $token);

$body = [];
$body[] = parseText(
    file_get_tpl('tab_general.tpl'),
    collect_tab_general_ph(request_intvar('id'))
);

if (!config('tvs_below_content', 1) && $tmplVars) {
    $body[] = parseText(
        file_get_tpl('tab_tv.tpl'),
        collect_tab_tv_ph()
    );
}

$body[] = parseText(
    file_get_tpl('tab_settings.tpl'),
    collect_tab_settings_ph(request_intvar('id'))
);

if (config('use_udperms') == 1) {
    global $permissions_yes, $permissions_no;
    $permissions = getUDGroups(request_intvar('id'));

    // See if the Access Permissions section is worth displaying...
    if ($permissions) {
        $ph = [];
        $ph['_lang_access_permissions'] = lang('access_permissions');
        $ph['_lang_access_permissions_docs_message'] = lang('access_permissions_docs_message');
        $ph['UDGroups'] = implode("\n", $permissions);
        $body[] = parseText(file_get_tpl('tab_access.tpl'), $ph);
    } elseif (!manager()->isAdmin() && $permissions_yes == 0 && $permissions_no > 0
        && (
            sessionv('mgrPermissions.access_permissions') == 1
            ||
            sessionv('mgrPermissions.web_access_permissions') == 1
        )
    ) {
        $body[] = '<p>' . lang('access_permissions_docs_collision') . '</p>';
    }
}

// invoke OnDocFormRender event
$tmp = ['id' => request_intvar('id')];
$OnDocFormRender = evo()->invokeEvent('OnDocFormRender', $tmp);

$OnRichTextEditorInit = '';
if (config('use_editor') === '1') {
    $rte_fields = rte_fields();
    if ($rte_fields) {
        // invoke OnRichTextEditorInit event
        $tmp = [
            'editor' => evo()->input_post('which_editor', config('which_editor')),
            'elements' => $rte_fields
        ];
        $evtOut = evo()->invokeEvent('OnRichTextEditorInit', $tmp);
        if (is_array($evtOut)) {
            $OnRichTextEditorInit = implode('', $evtOut);
        }
    }
}

$template = file_get_tpl('_template.tpl');
if (evo()->input_any('pid')) {
    $template = str_replace('<input type="hidden" name="pid" value="[+pid+]" />', '', $template);
}
$ph = collect_template_ph(
    request_intvar('id'),
    $OnDocFormPrerender,
    $OnDocFormRender,
    $OnRichTextEditorInit,
    $token
);
$ph['content'] = implode("\n", $body);
echo parseText($template, $ph);
