<?php
global $errors, $tplTemplates;
if (sessionv('is_upgradeable')) {
    return;
}
if (!sessionv('template') && !sessionv('installdata')) {
    return;
}

echo "<h3>" . lang('templates') . ":</h3>";

foreach ($tplTemplates as $i => $tplInfo) {
    if (in_array('sample', $tplInfo['installset']) && sessionv('installdata') == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }

    if (!in_array($i, sessionv('template')) && !$installSample) {
        continue;
    }

    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng($tplInfo['templatename'], sprintf(
            "%s '%s' %s",
            lang('unable_install_template'),
            $tplInfo['tpl_file_path'],
            lang('not_found')
        ));
        continue;
    }

    $f = array();
    $content = file_get_contents($tplInfo['tpl_file_path']);
    $f['content'] = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1);
    $f['description'] = $tplInfo['description'];
    $f['category'] = getCreateDbCategory($tplInfo['category']);
    $f['locked'] = $tplInfo['locked'];

    // See if the template already exists
    $dbv_template = db()->getObject('site_templates', "templatename='" . db()->escape($tplInfo['templatename']) . "'");
    if (!$dbv_template) {
        $f['templatename'] = $tplInfo['templatename'];
        $rs = db()->insert(
            db()->escape($f),
            '[+prefix+]site_templates'
        );
        if (!$rs) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($tplInfo['templatename'], lang('installed'));
        continue;
    }
    if (!db()->update($f, '[+prefix+]site_templates', "templatename='" . db()->escape($tplInfo['templatename']) . "'")) {
        $errors += 1;
        showError();
        return;
    }
    echo ok($tplInfo['templatename'], lang('upgraded'));
}
