<?php
global $errors, $tplModules;
if (!sessionv('module') && !sessionv('installdata')) {
    return;
}

echo '<h3>' . lang('modules') . ':</h3>';

foreach ($tplModules as $k => $tplInfo) {
    if (!in_array($k, sessionv('module')) && !withSample($tplInfo['installset'])) {
        continue;
    }

    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng($tplInfo['name'], sprintf(
            "%s '%s' %s",
            lang('unable_install_module'),
            $tplInfo['tpl_file_path'],
            lang('not_found')
        ));
        continue;
    }

    $modulecode = getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tplInfo['tpl_file_path']), 2));
    $f = [
        'modulecode' => preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $modulecode, 1),
        'description' => $tplInfo['description'],
        'properties' => $tplInfo['properties'],
        'enable_sharedparams' => $tplInfo['shareparams'],
    ];
    
    $dbv_module = db()->getObject('site_modules', "name='" . db()->escape($tplInfo['name']) . "'");
    if (!$dbv_module) {
        $f['name'] = $tplInfo['name'];
        $f['guid'] = $tplInfo['guid'];
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        if (!db()->insert(db()->escape($f), '[+prefix+]site_modules')) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($tplInfo['name'], lang('installed'));
        continue;
    }
    $props = propUpdate($tplInfo['properties'], $dbv_module->properties);
    $rs = db()->update(
        db()->insert(db()->escape($f),
        '[+prefix+]site_modules',
        "name='" . db()->escape($tplInfo['name']) . "'"
    );
    if (!$rs) {
        $errors += 1;
        showError();
        return;
    }
    echo ok($tplInfo['name'], lang('upgraded'));
}
