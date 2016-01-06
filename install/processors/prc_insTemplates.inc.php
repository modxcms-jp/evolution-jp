<?php
if($installmode==1)                               return;
if(empty($formvTemplates) && empty($installdata)) return;

echo "<h3>{$lang_templates}:</h3>";

foreach ($tplTemplates as $i=>$tplInfo)
{
	if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
		$installSample = true;
	else $installSample = false;
	
	if(!in_array($i, $formvTemplates) && !$installSample) continue;
	
	$templatename  = $tplInfo['templatename'];
	$tpl_file_path = $tplInfo['tpl_file_path'];
	
	if (!is_file($tpl_file_path)) {
		echo ng($templatename, "{$lang_unable_install_template} '{$tpl_file_path}' {$lang_not_found}");
		continue;
	}
	
	$f = array();
	$content = file_get_contents($tpl_file_path);
	$f['content']     = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1);
	$f['description'] = $tplInfo['description'];
	$f['category']    = getCreateDbCategory($tplInfo['category']); // Create the category if it does not already exist
	$f['locked']      = $tplInfo['locked'];
	$f = $modx->db->escape($f);
	
	// See if the template already exists
	$templatename = $modx->db->escape($templatename);
	$dbv_template = $modx->db->getObject('site_templates', "templatename='{$templatename}'");
	if ($dbv_template)
	{
		if (!@ $modx->db->update($f, '[+prefix+]site_templates', "templatename='{$templatename}'"))
		{
			$errors += 1;
			showError();
			return;
		}
		else echo ok($templatename,$lang_upgraded);
	}
	else
	{
		$f['templatename'] = $templatename;
		if (!@ $modx->db->insert($f, '[+prefix+]site_templates'))
		{
			$errors += 1;
			showError();
			return;
		}
		else echo ok($templatename,$lang_installed);
	}
}
