<?php
global $errors, $tplModules;
if (!sessionv('module') && !sessionv('installdata')) {
    return;
}

echo "<h3>" . lang('modules') . ":</h3>";

foreach ($tplModules as $i => $tplInfo) {
    if (in_array('sample', $tplInfo['installset']) && sessionv('installdata') == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }

    if (!in_array($i, sessionv('module')) && !$installSample) {
        continue;
    }

    $name = $tplInfo['name'];
    $tpl_file_path = $tplInfo['tpl_file_path'];
    if (!is_file($tpl_file_path)) {
        echo ng($name, sprintf(
            "%s '%s' %s",
            lang('unable_install_module'),
            $tpl_file_path,
            lang('not_found')
        ));
        continue;
    }

    $f = array();
    $f['description'] = $tplInfo['description'];
    $modulecode = getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tpl_file_path), 2));
    $f['modulecode'] = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $modulecode, 1);
    $f['properties'] = $tplInfo['properties'];
    $f['enable_sharedparams'] = $tplInfo['shareparams'];

    $dbv_module = db()->getObject('site_modules', "name='" . db()->escape($name) . "'");
    if (!$dbv_module) {
        $f['name'] = $name;
        $f['guid'] = $tplInfo['guid'];
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        if (!db()->insert(db()->escape($f), '[+prefix+]site_modules')) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('installed'));
        continue;
    }
    $props = propUpdate($tplInfo['properties'], $dbv_module->properties);
    if (!db()->update($f, '[+prefix+]site_modules', "name='" . db()->escape($name) . "'")) {
        $errors += 1;
        showError();
        return;
    }
    echo ok($name, lang('upgraded'));
}
