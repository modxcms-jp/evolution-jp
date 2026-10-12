<?php
global $errors, $tplPlugins;
$selectedPlugins = sessionv('plugin');
$installSampleData = sessionv('installdata') == 1;
if (!hasInstallableElement($tplPlugins, $selectedPlugins, $installSampleData)) {
    return;
}

echo '<h3>' . lang('plugins') . ':</h3>';

foreach ($tplPlugins as $i => $tplInfo) {
    if (!shouldInstallElement($i, $tplInfo['installset'], $selectedPlugins, $installSampleData)) {
        continue;
    }

    $name = $tplInfo['name'];
    $tpl_file_path = $tplInfo['tpl_file_path'];
    if (!is_file($tpl_file_path)) {
        echo ng($name, sprintf(
            "%s '%s' %s",
            lang('unable_install_plugin'),
            $tpl_file_path,
            lang('not_found')
        ));
        continue;
    }

    // parse comma-separated legacy names and prepare them for sql IN clause
    if (isset($tplInfo['legacy_names'])) {
        $_ = [];
        $array_legacy_names = explode(',', $tplInfo['legacy_names']);
        while ($v = array_shift($array_legacy_names)) {
            $_[] = trim($v);
        }
        $legacy_names = implode(',', $_);
        if ($legacy_names) {
            $rs = db()->update(
                ['disabled' => '1'],
                '[+prefix+]site_plugins',
                sprintf("name IN ('%s')", db()->escape($legacy_names))
            );
        }
    }

    $f = [];
    $f['name'] = $name;
    $f['description'] = $tplInfo['description'];
    $plugincode = getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tpl_file_path), 2));
    $f['plugincode'] = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
    $enabledWhere = sprintf("name='%s' AND disabled='0'", db()->escape($name));
    $dbv_plugin = db()->getObject('site_plugins', $enabledWhere, 'id DESC');
    if ($dbv_plugin && $dbv_plugin->properties) {
        $f['properties'] = propUpdate($tplInfo['properties'], $dbv_plugin->properties);
    } else {
        $f['properties'] = $tplInfo['properties'];
    }

    $f['disabled'] = $tplInfo['disabled'];
    $f['moduleguid'] = $tplInfo['guid'];

    $pluginId = false;
    $prevPluginId = $dbv_plugin ? (int)$dbv_plugin->id : 0;

    if ($dbv_plugin === false) {
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        $pluginId = db()->insert(
            db()->escape(nullToEmpty($f)),
            '[+prefix+]site_plugins'
        );
        if (!$pluginId) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('installed'));
    } else {
        // 版が同じでも、利用者の手直しを控えとして残すため、同名の有効な行をすべて無効にして入れ直す
        $rs = db()->update(['disabled' => '1'], '[+prefix+]site_plugins', $enabledWhere);
        if ($rs) {
            $f['category'] = $dbv_plugin->category;
            $pluginId = db()->insert(
                db()->escape(nullToEmpty($f)),
                '[+prefix+]site_plugins'
            );
        }
        if (!$rs || !$pluginId) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('upgraded'));
    }

    // add system events
    $events = explode(',', $tplInfo['events']);
    if ($pluginId && $events) {
        // remove existing events
        db()->delete('[+prefix+]site_plugin_events', "pluginid='{$pluginId}'");

        // add new events
        $eventWhere = sprintf("se.name IN ('%s')", implode("','", $events));
        // DBAPI::fieldExists() は完全名のテーブルを照合できず常に偽になるため、直接確かめる
        $hasPriority = db()->count(db()->query(
            'SHOW COLUMNS FROM ' . db()->table_prefix . "site_plugin_events LIKE 'priority'"
        )) > 0;
        if ($hasPriority) {
            // 実行順は入れ替える前のプラグインから引き継ぐ
            $query = sprintf(
                'INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid, priority)'
                . " SELECT '%d', se.id, COALESCE(prev.priority, 0) FROM [+prefix+]system_eventnames se"
                . " LEFT JOIN [+prefix+]site_plugin_events prev ON prev.evtid=se.id AND prev.pluginid='%d'"
                . ' WHERE %s',
                $pluginId,
                $prevPluginId,
                $eventWhere
            );
        } else {
            $query = sprintf(
                "INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid) SELECT '%d', se.id FROM [+prefix+]system_eventnames se WHERE %s",
                $pluginId,
                $eventWhere
            );
        }
        $query = str_replace('[+prefix+]', db()->table_prefix, $query);
        db()->query($query);
    }
}
