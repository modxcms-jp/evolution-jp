<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
// 新規（new）と更新（edit）以外は拒否する
$mode = EvoFormFlow::postedMode('template');
$id = post_intvar('id');
if (!$mode || ($mode === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$content = postv('content', '');
if (!is_string($content)) {
    alert()->setError(2);
    alert()->dumpError();
}

$rawName = manager()->requireElementName(post_strvar('templatename'), lang('template'), 'template', $mode === 'edit' ? $id : 0);

$tbl_site_templates = evo()->getFullTableName('site_templates');

$field = [];
$field['templatename'] = $rawName;
$field['description'] = post_strvar('description');
$field['content'] = $content;
$field['locked'] = postv('locked') === 'on' ? 1 : 0;
$field['parent'] = post_intvar('parent');

$isNew = $mode === 'new';
$formId = $isNew ? 0 : $id;

// invoke OnBeforeTempFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $isNew ? '' : $id
];
evo()->invokeEvent('OnBeforeTempFormSave', $tmp);

// disallow duplicate names
if (manager()->elementNameExists('template', $rawName, $formId)) {
    manager()->redirectToElementForm(
        sprintf(lang('duplicate_name_found_general'), lang('template'), $rawName),
        'template',
        $formId
    );
}

$field['category'] = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

if ($isNew) {
    $savedId = db()->insert(db()->escape($field), $tbl_site_templates);
    if (!$savedId) {
        echo "Couldn't get last insert key!";
        exit;
    }
} else {
    $rs = db()->update(db()->escape($field), $tbl_site_templates, "id='{$id}'");
    if (!$rs) {
        echo "\$rs not set! Edited template not saved!";
        return;
    }
    $savedId = $id;
}

// invoke OnTempFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $savedId
];
evo()->invokeEvent('OnTempFormSave', $tmp);

evo()->clearCache();
EvoFormFlow::redirectAfterSave('template', $savedId, EvoFormFlow::postedAfterSave());
