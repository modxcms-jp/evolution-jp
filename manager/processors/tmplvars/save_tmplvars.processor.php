<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（new）と更新（edit）以外は拒否する
$mode = EvoFormFlow::postedMode('tmplvar');
$id = post_intvar('id');
if (!$mode || ($mode === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 既定値と選択肢は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$elements = postv('elements', '');
$default_text = postv('default_text', '');
if (!is_string($elements) || !is_string($default_text)) {
    alert()->setError(2);
    alert()->dumpError();
}

$rawName = manager()->requireElementName(post_strvar('name'), lang('tv'), 'tmplvar', $mode === 'edit' ? $id : 0);
$description = post_strvar('description');
$caption = post_strvar('caption', $rawName);
$type = post_strvar('type');
$rank = post_strvar('rank', 0);
$display = post_strvar('display');
$display_params = post_strvar('params');
$locked = postv('locked') === 'on' ? 1 : 0;

$isNew = $mode === 'new';
// 差し戻し先と、名前の重複から除く id（新規は 0）
$formId = $isNew ? 0 : $id;

// invoke OnBeforeTVFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $isNew ? '' : $id
];
evo()->invokeEvent('OnBeforeTVFormSave', $tmp);

if (manager()->elementNameExists('tmplvar', $rawName, $formId)) {
    manager()->redirectToElementForm(
        sprintf(lang('duplicate_name_found_general'), lang('tv'), $rawName),
        'tmplvar',
        $formId
    );
}
if (check_reserved_names($rawName) !== false) {
    manager()->redirectToElementForm(
        sprintf(lang('reserved_name_warning'), $rawName),
        'tmplvar',
        $formId
    );
}

$category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

// 更新では、名前が変わったかを見るために、保存の前の名前を読んでおく
$was_name = $isNew ? null : db()->getValue(db()->select('name', '[+prefix+]site_tmplvars', "id='{$id}'"));
$field = [
    'name' => $rawName,
    'description' => $description,
    'caption' => $caption,
    'type' => $type,
    'elements' => $elements,
    'default_text' => $default_text,
    'display' => $display,
    'display_params' => $display_params,
    'rank' => $rank,
    'locked' => $locked,
    'category' => $category
];
if ($isNew) {
    $savedId = db()->insert(db()->escape($field), '[+prefix+]site_tmplvars');
    if (!$savedId) {
        echo "Couldn't get last insert key!";
        exit;
    }
} else {
    $rs = db()->update(db()->escape($field), '[+prefix+]site_tmplvars', "id='{$id}'");
    if (!$rs) {
        echo "\$rs not set! Edited variable not saved!";
        exit;
    }
    // update all references to this TV
    if (is_string($was_name) && $rawName !== $was_name) {
        manager()->renameElementTags(['[*%s*]', '[*%s:'], $was_name, $rawName);
    }
    $savedId = $id;
}

// save access permissions
saveTemplateAccess($savedId);
saveDocumentAccessPermissons($savedId);

// invoke OnTVFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $savedId
];
evo()->invokeEvent('OnTVFormSave', $tmp);

evo()->clearCache();
header('Location: ' . EvoFormFlow::afterSaveUrl('tmplvar', $savedId, EvoFormFlow::postedAfterSave()));

function saveTemplateAccess($id)
{
    $getRankArray = [];

    $getRank = db()->select('templateid,`rank`', '[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");

    while ($row = db()->getRow($getRank)) {
        $getRankArray[$row['templateid']] = $row['rank'];
    }
    db()->delete('[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");

    // update template selections
    // 配列だけを受ける。配列でないときは何も割り当てない
    $templates = postv('template'); // get muli-templates based on S.BRENNAN mod
    if (!is_array($templates)) {
        return;
    }
    foreach (to_idsvar($templates) as $iValue) {
        $setRank = $getRankArray[$iValue] ?? 0;
        $field = [
            'tmplvarid' => $id,
            'templateid' => $iValue,
            'rank' => $setRank
        ];
        db()->insert(db()->escape($field), '[+prefix+]site_tmplvar_templates');
    }
}

function saveDocumentAccessPermissons($id)
{
    // check for permission update access
    if (config('use_udperms') == 1) {
        // グループのアクセス設定を変える権限が無いときは、今の設定を変えない（画面も欄を出さない）
        if (!evo()->hasPermission('access_permissions')) {
            return;
        }

        $docgroups = postv('document_groups');

        // delete old permissions on the tv
        $rs = db()->delete('[+prefix+]site_tmplvar_access', "tmplvarid='{$id}'");
        if (!$rs) {
            echo 'An error occurred while attempting to delete previous template variable access permission entries.';
            exit;
        }
        if (is_array($docgroups)) {
            foreach (to_idsvar($docgroups) as $documentGroupId) {
                $field = [
                    'tmplvarid' => $id,
                    'documentgroup' => $documentGroupId
                ];

                $rs = db()->insert(db()->escape($field), '[+prefix+]site_tmplvar_access');
                if (!$rs) {
                    echo "An error occurred while attempting to save template variable access permissions.";
                    exit;
                }
            }
        }
    }
}

function check_reserved_names($name)
{
    // disallow reserved names
    $reserved_names = explode(
        ',',
        'id,type,contentType,pagetitle,longtitle,description,alias,link_attributes,published,pub_date,unpub_date,parent,isfolder,introtext,content,richtext,template,menuindex,searchable,cacheable,createdby,createdon,editedby,editedon,deleted,deletedon,deletedby,publishedon,publishedby,menutitle,donthit,haskeywords,hasmetatags,privateweb,privatemgr,content_dispo,hidemenu'
    );
    return in_array($name, $reserved_names);
}
