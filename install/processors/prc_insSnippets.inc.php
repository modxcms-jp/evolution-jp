<?php
global $errors, $tplSnippets;
$selectedSnippets = sessionv('snippet');
$installSampleData = sessionv('installdata') == 1;
if (!hasInstallableElement($tplSnippets, $selectedSnippets, $installSampleData)) {
    return;
}

echo '<h3>' . lang('snippets') . ':</h3>';

foreach ($tplSnippets as $k => $tplInfo) {
    if (!shouldInstallElement($k, $tplInfo['installset'], $selectedSnippets, $installSampleData)) {
        continue;
    }

    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng($tplInfo['name'], sprintf(
            "%s '%s' %s",
            lang('unable_install_snippet'),
            $tplInfo['tpl_file_path'],
            lang('not_found')
        ));
        continue;
    }

    $f = [
        'snippet' => getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tplInfo['tpl_file_path']))),
        'description' => $tplInfo['description'],
        'properties' => $tplInfo['properties']
    ];
    $dbv_snippet = db()->getObject('site_snippets', "name='" . db()->escape($tplInfo['name']) . "'");
    if (!$dbv_snippet) {
        $f['name'] = $tplInfo['name'];
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        $rs = db()->insert(
            db()->escape($f),
            '[+prefix+]site_snippets'
        );
        if (!$rs) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($tplInfo['name'], lang('installed'));
        continue;
    }
    $f['properties'] = propUpdate($tplInfo['properties'], $dbv_snippet->properties);
    if (!db()->update(db()->escape($f), '[+prefix+]site_snippets', "name='" . db()->escape($tplInfo['name']) . "'")) {
        $errors += 1;
        showError();
        return;
    }
    echo ok($tplInfo['name'], lang('upgraded'));
}
