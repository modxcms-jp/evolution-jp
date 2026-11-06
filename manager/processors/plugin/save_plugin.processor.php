<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（101）と更新（102）以外は拒否する
$mode = post_choicevar('mode', [101, 102], 0);
$id = post_intvar('id');
if (!$mode || ($mode === 102 && !$id)) {
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

$rawName = manager()->requireElementName(post_strvar('name'), $_lang['plugin'], 101, 102, $id);
$description = post_strvar('description');
$locked = postv('locked') === 'on' ? '1' : '0';
$disabled = postv('disabled') === 'on' ? '1' : '0';
$moduleguid = post_strvar('moduleguid');
// 不正な値は読み捨てる。選ばれていなければ OnWebPageInit（90）にする
$sysevents = post_idsvar('sysevents') ?: [90];
$pluginErrorReporting = post_choicevar('error_reporting', ['inherit', '0', '1', '2', '99'], 'inherit');

switch ($mode) {
    case 101:

        // invoke OnBeforePluginFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforePluginFormSave', $tmp);

        // disallow duplicate names for active plugins
        if ($disabled == '0') {
            $rs = db()->select('COUNT(id)', '[+prefix+]site_plugins', "name='" . db()->escape($rawName) . "' AND disabled='0'");
            $count = db()->getValue($rs);
            if ($count > 0) {
                manager()->redirectToElementForm(
                    sprintf($_lang['duplicate_name_found_general'], $_lang['plugin'], $rawName),
                    101,
                    102,
                    0
                );
            }
        }

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the new plugin
        $f = [
            'name' => $rawName,
            'description' => $description,
            'plugincode' => $plugincode,
            'disabled' => $disabled,
            'moduleguid' => $moduleguid,
            'locked' => $locked,
            'properties' => $properties,
            'category' => $category
        ];
        $f['error_reporting'] = $pluginErrorReporting;
        $newid = db()->insert(db()->escape($f), '[+prefix+]site_plugins');
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // save event listeners
        saveEventListeners($newid, $sysevents, $mode);

        // invoke OnPluginFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => $newid
        ];
        evo()->invokeEvent('OnPluginFormSave', $tmp);

        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "102&id={$newid}" : "101";
            $header = "Location: index.php?a={$a}&stay={$stay}";
        } else {
            $header = "Location: index.php?a=76";
        }
        header($header);
        break;
    case 102:

        // invoke OnBeforePluginFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent("OnBeforePluginFormSave", $tmp);

        // disallow duplicate names for active plugins
        if ($disabled == '0') {
            $rs = db()->select('COUNT(*)', '[+prefix+]site_plugins',
                "name='" . db()->escape($rawName) . "' AND id!='{$id}' AND disabled='0'");
            if (db()->getValue($rs) > 0) {
                manager()->redirectToElementForm(
                    sprintf($_lang['duplicate_name_found_general'], $_lang['plugin'], $rawName),
                    101,
                    102,
                    $id
                );
            }
        }

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the edited plugin
        $f = [
            'name' => $rawName,
            'description' => $description,
            'plugincode' => $plugincode,
            'disabled' => $disabled,
            'moduleguid' => $moduleguid,
            'locked' => $locked,
            'properties' => $properties,
            'category' => $category
        ];
        $f['error_reporting'] = $pluginErrorReporting;
        $rs = db()->update(db()->escape($f), '[+prefix+]site_plugins', "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited plugin not saved!";
        } else {
            // save event listeners
            saveEventListeners($id, $sysevents, $mode);

            // invoke OnPluginFormSave event
            $tmp = [
                'mode' => 'upd',
                'id' => $id
            ];
            evo()->invokeEvent('OnPluginFormSave', $tmp);

            // empty cache
            $modx->clearCache(); // first empty the cache
            // finished emptying cache - redirect
            $stay = post_choicevar('stay', [1, 2], 0);
            if ($stay) {
                $a = ($stay === 2) ? "102&id={$id}" : "101";
                $header = "Location: index.php?a={$a}&stay={$stay}";
            } else {
                $header = 'Location: index.php?a=76';
            }
            header($header);
        }
        break;
}


# Save Plugin Event Listeners
// $sysevents は id の int の配列、$mode は 101（新規）か 102（更新）
function saveEventListeners($id, array $sysevents, $mode)
{
    // save selected system events
    $tblSitePluginEvents = evo()->getFullTableName('site_plugin_events');
    $values = [];
    foreach ($sysevents as $event) {
        if ($mode === 101) {
            $prioritySql = "select max(priority) as priority from {$tblSitePluginEvents} where evtid={$event}";
        } else {
            $prioritySql = "select priority from {$tblSitePluginEvents} where evtid={$event} and pluginid={$id}";
        }
        $rs = db()->query($prioritySql);
        $prevPriority = db()->getRow($rs);
        if ($mode === 101) {
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
