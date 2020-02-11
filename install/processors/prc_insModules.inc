<?php

if (empty($formvModules) && empty($installdata)) return;

echo "<h3>" . $lang_modules . ":</h3>";

foreach ($tplModules as $i=>$tplInfo) {
	if(in_array('sample', $tplInfo['installset']) && $installdata == 1) {
        $installSample = true;
    } else {
        $installSample = false;
    }
	
	if(!in_array($i, $formvModules) && !$installSample) {
        continue;
    }
	
	$name = $tplInfo['name'];
	$tpl_file_path = $tplInfo['tpl_file_path'];
	if (!is_file($tpl_file_path)) {
		echo ng($name, sprintf("%s '%s' %s", $lang_unable_install_module, $tpl_file_path, $lang_not_found));
		continue;
	}
	
	$f = array();
	$f['description'] = $tplInfo['description'];
	$modulecode = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path), 2));
	$f['modulecode']  = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $modulecode, 1);
	$f['properties']  = $tplInfo['properties'];
	$f['enable_sharedparams'] = $tplInfo['shareparams'];
	$f = $modx->db->escape($f);
	
	$name = $modx->db->escape($name);
	$dbv_module = $modx->db->getObject('site_modules', "name='" . $name . "'");
	if ($dbv_module) {
		$props = propUpdate($properties,$dbv_module->properties);
		if (!@ $modx->db->update($f, '[+prefix+]site_modules', "name='" . $name . "'")) {
			$errors += 1;
			showError();
			return;
		}
		echo ok($name,$lang_upgraded);
	} else {
		$f['name']     = $name;
		$f['guid']     = $modx->db->escape($tplInfo['guid']);
		$f['category'] = getCreateDbCategory($tplInfo['category']);
		if (!@ $modx->db->insert($f, '[+prefix+]site_modules')) {
			$errors += 1;
			showError();
			return;
		}
		echo ok($name,$lang_installed);
	}
}
