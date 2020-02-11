<?php
if (empty($formvSnippets) && empty($installdata)) return;

echo "<h3>" . $lang_snippets . ":</h3>";
foreach ($tplSnippets as $k=>$tplInfo) {
    if(in_array('sample', $tplInfo['installset']) && $installdata == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }

    if(!in_array($k, $formvSnippets) && !$installSample) {
        continue;
    }

    $name = db()->escape($tplInfo['name']);
    $tpl_file_path = $tplInfo['tpl_file_path'];
    if (!is_file($tpl_file_path)) {
        echo ng($name, sprintf("%s '%s' %s", $lang_unable_install_snippet, $tpl_file_path, $lang_not_found));
        continue;
    }

    $f = array();
    $snippet = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path)));
    $f['snippet']     = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $snippet, 1);
    $f['description'] = $tplInfo['description'];
    $f['properties']  = $tplInfo['properties'];
    $f = db()->escape($f);

    $dbv_snippet = db()->getObject('site_snippets', "name='{$name}'");
    if ($dbv_snippet) {
        $props = propUpdate($properties,$dbv_snippet->properties);
        if (!@ db()->update($f, '[+prefix+]site_snippets', "name='{$name}'")) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name,$lang_upgraded);
    } else {
        $f['name']     = $name;
        $f['category'] = getCreateDbCategory($tplInfo['category']);
        if (!@ db()->insert($f, '[+prefix+]site_snippets')) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name,$lang_installed);
    }
}
