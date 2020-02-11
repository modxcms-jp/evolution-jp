<?php
if($_SESSION['installmode']==1) {
    return;
}
if (empty($formvTvs) && empty($installdata)) {
    return;
}

echo "<h3>" . $lang_tvs . ":</h3> ";
foreach ($tplTVs as $i=>$tplInfo) {
    if(in_array('sample', $tplInfo['installset']) && $installdata == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }

    if(!in_array($i, $formvTvs) && !$installSample) {
        continue;
    }

    $name = db()->escape($tplInfo['name']);
    $f = array();
    $f['type']           = $tplInfo['input_type'];
    $f['caption']        = $tplInfo['caption'];
    $f['description']    = $tplInfo['description'];
    $f['category']       = getCreateDbCategory($tplInfo['category']);
    $f['locked']         = $tplInfo['locked'];
    $f['elements']       = $tplInfo['elements'];
    $f['default_text']   = $tplInfo['default_text'];
    $f['display']        = $tplInfo['display'];
    $f['display_params'] = $tplInfo['display_params'];
    $f = db()->escape($f);

    $dbv_tmplvar = db()->getObject('site_tmplvars', "name='" . $name . "'");
    if ($dbv_tmplvar) {
        $tmplvarid = $dbv_tmplvar->id;
        $rs = db()->update($f, '[+prefix+]site_tmplvars', "id='" . $tmplvarid . "'");
        if (!$rs) {
            $errors += 1;
            showError();
            return;
        }
        db()->delete('[+prefix+]site_tmplvar_templates', "tmplvarid='" . $dbv_tmplvar->id . "'");
        echo ok($name,$lang_upgraded);
    } else {
        $f['name'] = $name;
        $tmplvarid = db()->insert($f, '[+prefix+]site_tmplvars');
        if (!$tmplvarid) {
            $errors += 1;
            showError();
            return;
        }
        echo ok($name, $lang_installed);
    }

    // add template assignments
    $templatenames = explode(',', $tplInfo['template_assignments']);
    if(empty($templatenames)) continue;

    // add tv -> template assignments
    foreach ($templatenames as $templatename) {
        $templatename = db()->escape($templatename);
        $dbv_template = db()->getObject('site_templates', "templatename='" . $templatename . "'");
        if ($dbv_template) {
            $f = array('tmplvarid'=>$tmplvarid, 'templateid'=>$dbv_template->id);
            db()->insert($f, '[+prefix+]site_tmplvar_templates');
        }
    }
}
