<?php
if(sessionv('is_upgradeable')) {
    return;
}
if(!sessionv('template') && !sessionv('installdata')) {
    return;
}

echo "<h3>" . lang('templates') . ":</h3>";

foreach ($tplTemplates as $i=>$tplInfo) {
	if(in_array('sample', $tplInfo['installset']) && sessionv('installdata') == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }
	
	if(!in_array($i, sessionv('template')) && !$installSample) {
        continue;
    }
	
	$templatename  = $tplInfo['templatename'];
	$tpl_file_path = $tplInfo['tpl_file_path'];
	
	if (!is_file($tpl_file_path)) {
		echo ng($templatename, sprintf(
		    "%s '%s' %s"
            , lang('unable_install_template')
            , $tpl_file_path
            , lang('not_found')
        ));
		continue;
	}
	
	$f = array();
	$content = file_get_contents($tpl_file_path);
	$f['content']     = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1);
	$f['description'] = $tplInfo['description'];
	$f['category']    = getCreateDbCategory($tplInfo['category']);
	$f['locked']      = $tplInfo['locked'];
	$f = db()->escape($f);
	
	// See if the template already exists
	$templatename = db()->escape($templatename);
	$dbv_template = db()->getObject('site_templates', "templatename='" . $templatename . "'");
	if ($dbv_template) {
		if (! db()->update($f, '[+prefix+]site_templates', "templatename='" . $templatename . "'")) {
			$errors += 1;
			showError();
			return;
		}
		else echo ok($templatename,lang('upgraded'));
	} else {
		$f['templatename'] = $templatename;
		if (! db()->insert($f, '[+prefix+]site_templates')) {
			$errors += 1;
			showError();
			return;
		}
        echo ok($templatename, lang('installed'));
    }
}
