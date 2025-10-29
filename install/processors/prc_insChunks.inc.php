<?php
global $errors, $tplChunks, $modx_version;
$selectedChunks = sessionv('chunk');
$installSampleData = sessionv('installdata') == 1;
if (!hasInstallableElement($tplChunks, $selectedChunks, $installSampleData)) {
    return;
}

echo sprintf('<h3>%s:</h3>', lang('chunks'));
foreach ($tplChunks as $i => $tplInfo) {
    if (!shouldInstallElement($i, $tplInfo['installset'], $selectedChunks, $installSampleData)) {
        continue;
    }
    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng(
            $tplInfo['name'],
            sprintf(
                "%s '%s' %s",
                lang('unable_install_chunk'),
                $tplInfo['tpl_file_path'],
                lang('not_found')
            )
        );
        continue;
    }
    $field = array(
        'name' => $tplInfo['name'],
        'description' => $tplInfo['description'],
        'snippet' => preg_replace(
            "@^.*?/\*\*.*?\*/\s+@s",
            '',
            file_get_contents($tplInfo['tpl_file_path']),
            1
        ),
        'category' => getCreateDbCategory($tplInfo['category'])
    );

    $rs = db()->select(
        '*',
        '[+prefix+]site_htmlsnippets',
        sprintf(
            "name='%s'",
            db()->escape($tplInfo['name'])
        )
    );
    if (!db()->count($rs)) {
        $rs = db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets');
        if (!$rs) {
            $errors++;
            showError();
            return;
        }
        echo ok($tplInfo['name'], lang('installed'));
        continue;
    }
    if ($tplInfo['overwrite'] !== 'false') {
        $updated = db()->update(
            db()->escape($field),
            '[+prefix+]site_htmlsnippets',
            sprintf("name='%s'", db()->escape($tplInfo['name']))
        );
        echo ok($tplInfo['name'], lang('upgraded'));
        continue;
    }
    $swap_name = $tplInfo['name'] . '-' . str_replace('.', '_', $modx_version);
    $i = 0;
    while ($i < 100) {
        $field['name'] = $i ? sprintf('%s(%s)', $swap_name, $i) : $swap_name;
        $rs = db()->select(
            '*',
            '[+prefix+]site_htmlsnippets',
            sprintf(
                "name='%s'",
                db()->escape($field['name'])
            )
        );
        if (!db()->count($rs)) {
            break;
        }
        $i++;
    }
    $rs = db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets');
    if (!$rs) {
        $errors++;
        showError();
        return;
    }
    echo ok($tplInfo['name'], lang('upgraded'));
}
