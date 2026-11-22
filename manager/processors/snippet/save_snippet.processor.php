<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_snippet')) {
    alert()->setError(3);
    alert()->dumpError();
}
// 新規（new）と更新（edit）以外は拒否する
$mode = EvoFormFlow::postedMode('snippet');
$id = post_intvar('id');
if (!$mode || ($mode === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文と設定は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$snippet = postv('post', '');
$properties = postv('properties', '');
if (!is_string($snippet) || !is_string($properties)) {
    alert()->setError(2);
    alert()->dumpError();
}
$snippet = trim($snippet);

$rawName = manager()->requireElementName(post_strvar('name'), lang('snippet'), 'snippet', $mode === 'edit' ? $id : 0);
$description = post_strvar('description');
$locked = postv('locked') === 'on' ? 1 : 0;
$tbl_site_snippets = evo()->getFullTableName('site_snippets');

$errorReporting = post_choicevar('error_reporting', EvoErrorReporting::elementValues(), 'inherit');

// strip out PHP tags from snippets
if (strncmp($snippet, '<?', 2) == 0) {
    $snippet = substr($snippet, 2);
    if (strncmp($snippet, 'php', 3) == 0) {
        $snippet = substr($snippet, 3);
    }
    if (substr($snippet, -2, 2) == '?>') {
        $snippet = substr($snippet, 0, -2);
    }
}
$moduleguid = post_strvar('moduleguid');

$isNew = $mode === 'new';
$formId = $isNew ? 0 : $id;

// disallow duplicate names
if (manager()->elementNameExists('snippet', $rawName, $formId)) {
    manager()->redirectToElementForm(
        sprintf(lang('duplicate_name_found_general'), lang('snippet'), $rawName),
        'snippet',
        $formId
    );
}

// invoke OnBeforeSnipFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $isNew ? '' : $id
];
evo()->invokeEvent('OnBeforeSnipFormSave', $tmp);

$field = [
    'name' => $rawName,
    'description' => $description,
    'snippet' => $snippet,
    'moduleguid' => $moduleguid,
    'locked' => $locked,
    'properties' => $properties,
    'category' => manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory')),
    'error_reporting' => $errorReporting,
];
if ($isNew) {
    $savedId = db()->insert(db()->escape($field), $tbl_site_snippets);
    if (!$savedId) {
        echo '$newid not set! New snippet not saved!';
        exit;
    }
} else {
    $rs = db()->update(db()->escape($field), $tbl_site_snippets, "id='{$id}'");
    if (!$rs) {
        echo '$rs not set! Edited snippet not saved!';
        exit;
    }
    $savedId = $id;
}

// invoke OnSnipFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $savedId
];
evo()->invokeEvent('OnSnipFormSave', $tmp);

evo()->clearCache();
header('Location: ' . EvoFormFlow::afterSaveUrl('snippet', $savedId, EvoFormFlow::postedAfterSave()));
