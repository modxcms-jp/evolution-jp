<?php
global $errors, $tplTVs;
if (sessionv('is_upgradeable')) {
    return;
}

$selectedTVs = sessionv('tv');
$installSampleData = sessionv('installdata') == 1;
if (!hasInstallableElement($tplTVs, $selectedTVs, $installSampleData)) {
    return;
}

echo "<h3>" . lang('tvs') . ":</h3> ";
foreach ($tplTVs as $i => $tplInfo) {
    if (!shouldInstallElement($i, $tplInfo['installset'], $selectedTVs, $installSampleData)) {
        continue;
    }

    $name = $tplInfo['name'];
    $f = [];
    $f['type'] = $tplInfo['input_type'];
    $f['caption'] = $tplInfo['caption'];
    $f['description'] = $tplInfo['description'];
    $f['category'] = getCreateDbCategory($tplInfo['category']);
    $f['locked'] = $tplInfo['locked'];
    $f['elements'] = $tplInfo['elements'];
    $f['default_text'] = $tplInfo['default_text'];
    $f['display'] = $tplInfo['display'];
    $f['display_params'] = $tplInfo['display_params'];

    $dbv_tmplvar = db()->getObject('site_tmplvars', "name='" . $name . "'");
    if (!$dbv_tmplvar) {
        $f['name'] = $name;
        $tmplvarid = db()->insert(
            db()->escape($f),
            '[+prefix+]site_tmplvars'
        );
        if (!$tmplvarid) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('installed'));
    } else {
        $tmplvarid = $dbv_tmplvar->id;
        $rs = db()->update(
            db()->escape($f),
            '[+prefix+]site_tmplvars',
            "id='" . $tmplvarid . "'"
        );
        if (!$rs) {
            $errors += 1;
            showError();
            return;
        }
        db()->delete('[+prefix+]site_tmplvar_templates', "tmplvarid='" . $dbv_tmplvar->id . "'");
        echo ok($name, lang('upgraded'));
    }

    // add template assignments
    $templatenames = explode(',', $tplInfo['template_assignments']);
    if (empty($templatenames)) {
        continue;
    }

    // add tv -> template assignments
    foreach ($templatenames as $templatename) {
        $dbv_template = db()->getObject(
            'site_templates',
            "templatename='" . db()->escape($templatename) . "'"
        );
        if (!$dbv_template) {
            continue;
        }
        db()->insert(
            ['tmplvarid' => $tmplvarid, 'templateid' => $dbv_template->id],
            '[+prefix+]site_tmplvar_templates'
        );
    }
}
