<?php
global $errors, $tplSnippets;
if (!sessionv('snippet') && !sessionv('installdata')) {
    return;
}

echo '<h3>' . lang('snippets') . ':</h3>';

foreach ($tplSnippets as $k => $tplInfo) {
    if (!in_array($k, sessionv('snippet')) && !withSample($tplInfo['installset'])) {
        continue;
    }

    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng($tplInfo['name'], sprintf(
            "%s '%s' %s"
            , lang('unable_install_snippet')
            , $tplInfo['tpl_file_path']
            , lang('not_found')
        ));
        continue;
    }

    $f = array(
        'snippet' => getLast(preg_split("@(//)?\s*<\?php@", file_get_contents($tplInfo['tpl_file_path']))),
        'description' => $tplInfo['description'],
        'properties' => $tplInfo['properties']
    );
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
    $props = propUpdate($tplInfo['properties'], $dbv_snippet->properties);
    if (!@ db()->update($f, '[+prefix+]site_snippets', "name='" . db()->escape($tplInfo['name']) . "'")) {
        $errors += 1;
        showError();
        return;
    }
    echo ok($tplInfo['name'], lang('upgraded'));
}
