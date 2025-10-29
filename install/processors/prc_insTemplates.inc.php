<?php
global $errors, $tplTemplates;
if (sessionv('is_upgradeable')) {
    return;
}
$selectedTemplates = sessionv('template');
$installSampleData = sessionv('installdata') == 1;
if (!hasInstallableElement($tplTemplates, $selectedTemplates, $installSampleData)) {
    return;
}

echo "<h3>" . lang('templates') . ":</h3>";

foreach ($tplTemplates as $i => $tplInfo) {
    if (!shouldInstallElement($i, $tplInfo['installset'], $selectedTemplates, $installSampleData)) {
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

    $content = file_get_contents($tplInfo['tpl_file_path']);
    $f = [
        'content'     => preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1),
        'description' => $tplInfo['description'],
        'category'    => getCreateDbCategory($tplInfo['category']),
        'locked'      => $tplInfo['locked']
    ];

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
