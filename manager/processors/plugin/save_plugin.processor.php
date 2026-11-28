<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（new）と更新（edit）以外は拒否する
$mode = EvoFormFlow::postedMode('plugin');
$id = post_intvar('id');
if (!$mode || ($mode === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文と設定は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$plugincode = postv('post', '');
$properties = postv('properties', '');
if (!is_string($plugincode) || !is_string($properties)) {
    alert()->setError(2);
    alert()->dumpError();
}

$rawName = manager()->requireElementName(post_strvar('name'), lang('plugin'), 'plugin', $mode === 'edit' ? $id : 0);
$description = post_strvar('description');
$locked = postv('locked') === 'on' ? '1' : '0';
$disabled = postv('disabled') === 'on' ? '1' : '0';
$moduleguid = post_strvar('moduleguid');
// 不正な値は読み捨てる。選ばれていなければ OnWebPageInit（90）にする
$sysevents = post_idsvar('sysevents') ?: [90];
$pluginErrorReporting = post_choicevar('error_reporting', EvoErrorReporting::elementValues(), EvoErrorReporting::INHERIT);

$isNew = $mode === 'new';
// 差し戻し先と、名前の重複から除く id（新規は 0）
$formId = $isNew ? 0 : $id;

// invoke OnBeforePluginFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $isNew ? '' : $id
];
evo()->invokeEvent('OnBeforePluginFormSave', $tmp);

// 名前の重複を禁じるのは、有効なプラグインどうしだけ（無効なプラグインは同じ名前で置いておける）
if ($disabled == '0' && manager()->elementNameExists('plugin', $rawName, $formId, ['disabled' => '0'])) {
    manager()->redirectToElementForm(
        sprintf(lang('duplicate_name_found_general'), lang('plugin'), $rawName),
        'plugin',
        $formId
    );
}

$f = [
    'name' => $rawName,
    'description' => $description,
    'plugincode' => $plugincode,
    'disabled' => $disabled,
    'moduleguid' => $moduleguid,
    'locked' => $locked,
    'properties' => $properties,
    'category' => manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory')),
    'error_reporting' => $pluginErrorReporting
];
if ($isNew) {
    $savedId = db()->insert(db()->escape($f), '[+prefix+]site_plugins');
    if (!$savedId) {
        echo "Couldn't get last insert key!";
        exit;
    }
} else {
    $rs = db()->update(db()->escape($f), '[+prefix+]site_plugins', "id='{$id}'");
    if (!$rs) {
        echo "\$rs not set! Edited plugin not saved!";
        return;
    }
    $savedId = $id;
}

// save event listeners
saveEventListeners($savedId, $sysevents, $mode);

// invoke OnPluginFormSave event
$tmp = [
    'mode' => $isNew ? 'new' : 'upd',
    'id' => $savedId
];
evo()->invokeEvent('OnPluginFormSave', $tmp);

evo()->clearCache();
EvoFormFlow::redirectAfterSave('plugin', $savedId, EvoFormFlow::postedAfterSave());


# Save Plugin Event Listeners
// $sysevents は id の int の配列、$mode は new（新規）か edit（更新）
function saveEventListeners($id, array $sysevents, $mode)
{
    // save selected system events
    $tblSitePluginEvents = evo()->getFullTableName('site_plugin_events');
    $values = [];
    foreach ($sysevents as $event) {
        if ($mode === 'new') {
            $prioritySql = "select max(priority) as priority from {$tblSitePluginEvents} where evtid={$event}";
        } else {
            $prioritySql = "select priority from {$tblSitePluginEvents} where evtid={$event} and pluginid={$id}";
        }
        $rs = db()->query($prioritySql);
        $prevPriority = db()->getRow($rs);
        if ($mode === 'new') {
            $priority = isset($prevPriority['priority']) ? $prevPriority['priority'] + 1 : 1;
        } else {
            $priority = isset($prevPriority['priority']) ? $prevPriority['priority'] : 1;
        }
        $values[] = "(" . $id . "," . $event . "," . (int)$priority . ")";
    }
    db()->query("DELETE FROM {$tblSitePluginEvents} WHERE pluginid={$id}");
    if ($values) {
        db()->query("INSERT INTO {$tblSitePluginEvents} (pluginid,evtid,priority) VALUES " . implode(',', $values));
    }
}
