<?php
if (!sessionv('chunk') && !sessionv('installdata')) {
    return;
}

echo '<h3>' . lang('chunks') . ':</h3>';
foreach ($tplChunks as $i=>$tplInfo) {
    if(!sessionv('installdata') || !in_array('sample', $tplInfo['installset'])) {
        $installSample = false;
    } else {
        $installSample = true;
    }

    if(!in_array($i, sessionv('chunk')) && !$installSample) {
        continue;
    }

    $name = db()->escape($tplInfo['name']);

    if (!is_file($tplInfo['tpl_file_path'])) {
        echo ng($name, sprintf("%s '%s' %s", lang('unable_install_chunk'), $tplInfo['tpl_file_path'], lang('not_found')));
        continue;
    }

    $field = array(
        'description' => $tplInfo['description'],
        'snippet'     => preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', file_get_contents($tplInfo['tpl_file_path']), 1),
        'category'    => getCreateDbCategory($tplInfo['category'])
    );

    if (db()->getObject('site_htmlsnippets', "name='" . $name . "'")) {
        if($tplInfo['overwrite'] === 'false') {
            $rs =true;
            $i = 0;
            while($rs === true) {
                $newname = $tplInfo['name'] . '-' . str_replace('.', '_', $modx_version);
                if(0<$i) {
                    sprintf('%s(%s)', $newname, $i);
                }
                $newname = db()->escape($newname);
                $rs = db()->getObject('site_htmlsnippets', "name='" . $newname . "'");
                $name = $newname;
                $i++;
            }
        }
        $updated = db()->update(
            db()->escape($field)
            , '[+prefix+]site_htmlsnippets'
            , "name='" . $name . "'"
        );
        if (!$updated) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('upgraded'));
    } else {
        $field['name'] = $name;
        if (!@ db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets')) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, lang('installed'));
    }
}
