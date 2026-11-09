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

$rawName = manager()->requireElementName(post_strvar('name'), $_lang['snippet'], 'snippet', $id);
$description = post_strvar('description');
$locked = postv('locked') === 'on' ? 1 : 0;
$tbl_site_snippets = evo()->getFullTableName('site_snippets');

$errorReporting = post_choicevar('error_reporting', ['inherit', '0', '1', '2', '99'], 'inherit');

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

// disallow duplicate names
$where = "name='" . db()->escape($rawName) . "'";
if ($id) {
    $where .= ' AND id!=' . $id;
}
$rs = db()->select('COUNT(id)', '[+prefix+]site_snippets', $where);
$count = db()->getValue($rs);
if ($count > 0) {
    manager()->redirectToElementForm(
        sprintf($_lang['duplicate_name_found_general'], $_lang['snippet'], $rawName),
        'snippet',
        $id
    );
}

switch ($mode) {
    case 'new':
        // invoke OnBeforeSnipFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforeSnipFormSave', $tmp);

        $categoryid = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the new doc
        $field = [];
        $field['name'] = $rawName;
        $field['description'] = $description;
        $field['snippet'] = $snippet;
        $field['moduleguid'] = $moduleguid;
        $field['locked'] = $locked;
        $field['properties'] = $properties;
        $field['category'] = $categoryid;
        $field['error_reporting'] = $errorReporting;
        $newid = db()->insert(db()->escape($field), $tbl_site_snippets);
        if (!$newid) {
            echo '$newid not set! New snippet not saved!';
            exit;
        }

        // invoke OnSnipFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => $newid
        ];
        evo()->invokeEvent('OnSnipFormSave', $tmp);
        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        header('Location: ' . EvoFormFlow::afterSaveUrl('snippet', $newid, EvoFormFlow::postedAfterSave()));
        break;

    case 'edit':
        // invoke OnBeforeSnipFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnBeforeSnipFormSave', $tmp);

        $categoryid = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the edited doc
        $field = [];
        $field['name'] = $rawName;
        $field['description'] = $description;
        $field['snippet'] = $snippet;
        $field['moduleguid'] = $moduleguid;
        $field['locked'] = $locked;
        $field['properties'] = $properties;
        $field['category'] = $categoryid;
        $field['error_reporting'] = $errorReporting;
        $rs = db()->update(db()->escape($field), $tbl_site_snippets, "id='{$id}'");
        if (!$rs) {
            echo '$rs not set! Edited snippet not saved!';
            exit;
        }

// invoke OnSnipFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnSnipFormSave', $tmp);
        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        header('Location: ' . EvoFormFlow::afterSaveUrl('snippet', $id, EvoFormFlow::postedAfterSave()));
        break;
}
