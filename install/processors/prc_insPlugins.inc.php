<?php
global $errors, $tplPlugins;
if (!sessionv('plugin') && !sessionv('installdata')) {
    return;
}

echo '<h3>' . lang('plugins') . ':</h3>';

foreach ($tplPlugins as $i=>$tplInfo) {
    if(in_array('sample', $tplInfo['installset']) && sessionv('installdata') == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }

    if(!in_array($i, sessionv('plugin')) && !$installSample) {
        continue;
    }

    $name        = $tplInfo['name'];
    $tpl_file_path = $tplInfo['tpl_file_path'];
    if(!is_file($tpl_file_path)) {
        echo ng($name, sprintf(
            "%s '%s' %s"
            , lang('unable_install_plugin')
            , $tpl_file_path
            , lang('not_found')
        ));
        continue;
    }

    // parse comma-separated legacy names and prepare them for sql IN clause
    if(isset($tplInfo['legacy_names'])) {
        $_ = array();
        $array_legacy_names = explode(',', $tplInfo['legacy_names']);
        while($v = array_shift($array_legacy_names)) {
            $_[] = trim($v);
        }
        $legacy_names = implode(',', $_);
        if($legacy_names) {
            $legacy_names = db()->escape($legacy_names);
            $rs = db()->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "name IN ('{$legacy_names}')");
        }
    }

    $f = array();
    $f['name']        = $name;
    $f['description'] = $tplInfo['description'];
    $plugincode = getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tpl_file_path), 2));
    $f['plugincode']  = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
    $name = db()->escape($name);
    $dbv_plugin = db()->getObject('site_plugins', "name='" . $name . "' AND disabled='0'");
    if($dbv_plugin->properties) {
        $f['properties']  = propUpdate($tplInfo['properties'],$dbv_plugin->properties);
    } else {
        $f['properties']  = $tplInfo['properties'];
    }

    $f['disabled']    = $tplInfo['disabled'];
    $f['moduleguid']  = db()->escape($tplInfo['guid']);
    $f = db()->escape($f);

    $pluginId = false;

    if($dbv_plugin === false || $dbv_plugin->description === $tplInfo['description']) {
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        $pluginId = db()->insert($f, '[+prefix+]site_plugins');
        if (!$pluginId) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('installed'));
    } else {
        $rs = db()->update(array('disabled' => '1'), '[+prefix+]site_plugins', "id='{$dbv_plugin->id}'");
        if ($rs) {
            $f['category'] = db()->escape($dbv_plugin->category);
            $pluginId = db()->insert($f, '[+prefix+]site_plugins');
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
    if($pluginId && count($events) > 0) {
        // remove existing events
        db()->delete('[+prefix+]site_plugin_events', "pluginid='{$pluginId}'");

        // add new events
        $selected = sprintf(
            "SELECT '%s' as 'pluginid',se.id as 'evtid' FROM [+prefix+]system_eventnames se WHERE name IN ('%s')"
            , $pluginId
            , implode("','", $events)
        );
        $query = 'INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid) ' . $selected;
        $query = str_replace('[+prefix+]',db()->table_prefix,$query);
        db()->query($query);
    }
}
