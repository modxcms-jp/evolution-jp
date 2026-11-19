<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_chunk')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（new）と更新（edit）以外は拒否する
$mode = EvoFormFlow::postedMode('chunk');
$id = post_intvar('id');
if (!$mode || ($mode === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
if (postv('post') !== null && post_textvar('post') === null) {
    alert()->setError(2);
    alert()->dumpError();
}

function entity($key)
{
    if ($key === 'name') {
        return manager()->normalizeElementName(post_strvar('name'));
    }
    if ($key === 'description') {
        return post_strvar('description');
    }
    if ($key === 'post') {
        return post_textvar('post', '');
    }
    if ($key === 'locked') {
        return postv('locked') === 'on' ? 1 : 0;
    }
    if ($key === 'editor_type') {
        return post_intvar('editor_type') === 1 ? 1 : 0;
    }
    if ($key === 'category') {
        return manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));
    }

    return null;
}

/**
 * published・pub_date・unpub_date を、公開日・公開終了日と今の時刻から決める。
 * 日付が空ならチェックボックスの値のまま。空でない日付を解釈できなければ DB に渡さず編集画面へ戻す。
 * 公開日・公開終了日による published の決め方は、リソースの保存と同じ DocAPI::setPubStatus() を使う。
 */
function resolvePublishFields(string $mode, int $id): array
{
    $f = ['published' => post_intvar('published') === 1 ? 1 : 0];
    foreach (['pub_date', 'unpub_date'] as $key) {
        // 空は 0。解釈できない日付と 1970 年より前の日付は、リソースの保存と同じく差し戻す
        $timestamp = evo()->parseDateInput(post_strvar($key));
        if ($timestamp === null || $timestamp < 0) {
            manager()->redirectToElementForm(lang('mgrlog_dateinvalid'), 'chunk', $mode === 'edit' ? $id : 0);
        }
        $f[$key] = $timestamp;
    }

    evo()->loadExtension('DocAPI');
    $fields = evo()->doc->setPubStatus($f);
    return [
        'published' => (int)$fields['published'],
        'pub_date' => (int)$fields['pub_date'],
        'unpub_date' => (int)$fields['unpub_date'],
    ];
}

// 名前が空だと一覧から開けず、更新では {{旧名}} の参照まで空の名前に書き換わるため、保存しない
manager()->requireElementName(post_strvar('name'), lang('chunk'), 'chunk', $id);
// 日付が解釈できないときは、イベントや DB に進む前に差し戻す
$publish = resolvePublishFields($mode, $id);

$isNew = $mode === 'new';
// 差し戻し先と、名前の重複から除く id（新規は 0）
$formId = $isNew ? 0 : $id;

// invoke OnBeforeChunkFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $isNew ? '' : $id
];
evo()->invokeEvent('OnBeforeChunkFormSave', $tmp);

if (manager()->elementNameExists('chunk', entity('name'), $formId)) {
    manager()->redirectToElementForm(
        sprintf(lang('duplicate_name_found_general'), lang('chunk'), entity('name')),
        'chunk',
        $formId
    );
}

// 更新では、名前が変わったかを見るために、保存の前の名前を読んでおく
$was_name = $isNew ? null : db()->getValue(
    db()->select('name', '[+prefix+]site_htmlsnippets', where('id', $id))
);
$field = [
    'name' => entity('name'),
    'description' => entity('description'),
    'published' => $publish['published'],
    'pub_date' => $publish['pub_date'],
    'unpub_date' => $publish['unpub_date'],
    'snippet' => entity('post'),
    'locked' => entity('locked'),
    'editor_type' => entity('editor_type'),
    'category' => entity('category')
];
if ($isNew) {
    $savedId = db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets');
    if (!$savedId) {
        exit("Couldn't get last insert key!");
    }
} else {
    $rs = db()->update(db()->escape($field), '[+prefix+]site_htmlsnippets', where('id', $id));
    if (!$rs) {
        echo "\$rs not set! Edited htmlsnippet not saved!";
        return;
    }
    if (is_string($was_name) && entity('name') !== $was_name) {
        manager()->renameElementTags(['{{%s}}', '{{%s:', '{{%s?'], $was_name, entity('name'));
    }
    $savedId = $id;
}

// invoke OnChunkFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $savedId
];
evo()->invokeEvent('OnChunkFormSave', $tmp);

evo()->clearCache();
header('Location: ' . EvoFormFlow::afterSaveUrl('chunk', $savedId, EvoFormFlow::postedAfterSave()));
