<?php

if (empty($formvPlugins) && empty($installdata)) return;

echo "<h3>{$lang_plugins}:</h3>";

foreach ($tplPlugins as $i=>$tplInfo) {
	
	if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
		$installSample = true;
	else $installSample = false;
	
	if(!in_array($i, $formvPlugins) && !$installSample) continue;
	
	$name        = $tplInfo['name'];
	$tpl_file_path = $tplInfo['tpl_file_path'];
	if(!is_file($tpl_file_path))
	{
		echo ng($name, $lang_unable_install_plugin . " '{$tpl_file_path}' " . $lang_not_found);
		continue;
	}
	
	// parse comma-separated legacy names and prepare them for sql IN clause
	if(isset($tplInfo['legacy_names']))
	{
		$_ = array();
		$array_legacy_names = explode(',', $tplInfo['legacy_names']);
		while($v = array_shift($array_legacy_names))
		{
			$_[] = trim($v);
		}
		$legacy_names = join(',', $_);
		// disable legacy versions based on legacy_names provided
		if(!empty($legacy_names))
		{
			$legacy_names = $modx->db->escape($legacy_names);
			$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "name IN ('{$legacy_names}')");
		}
	}
	
	$f = array();
	$f['name']        = $name;
	$f['description'] = $tplInfo['description'];
	$plugincode = getLast(preg_split("@(//)?\s*\<\?php@", file_get_contents($tpl_file_path), 2));
	$f['plugincode']  = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $plugincode, 1);
	$f['properties']  = propUpdate($tplInfo['properties'],$dbv_plugin->properties);
	$f['disabled']    = $tplInfo['disabled'];
	$f['moduleguid']  = $modx->db->escape($tplInfo['guid']);
	$f = $modx->db->escape($f);
	
	$pluginId = false;
	
	$name = $modx->db->escape($name);
	$dbv_plugin = $modx->db->getObject('site_plugins', "name='{$name}' AND disabled='0'");
	if($dbv_plugin!==false && $dbv_plugin->description !== $tplInfo['description'])
	{
		$rs = $modx->db->update(array('disabled'=>'1'), '[+prefix+]site_plugins', "id='{$dbv_plugin->id}'");
		if($rs)
		{
			$f['category']  = $modx->db->escape($dbv_plugin->category);
			$pluginId = $modx->db->insert($f, '[+prefix+]site_plugins');
		}
		if(!$rs || !$pluginId)
		{
			$errors += 1;
			showError();
			return;
		}
		else echo ok($name,$lang_upgraded);
	}
	else
	{
		$f['category']    = getCreateDbCategory($tplInfo['category']);
		$pluginId = $modx->db->insert($f, '[+prefix+]site_plugins');
		if(!$pluginId) {
			$errors += 1;
			showError();
			return;
		}
		echo ok($name,$lang_installed);
	}
	
	// add system events
	$events = explode(',', $tplInfo['events']);
	if($pluginId && count($events) > 0)
	{
		// remove existing events
		$modx->db->delete('[+prefix+]site_plugin_events', "pluginid='{$pluginId}'");
		
		// add new events
		$events = implode("','", $events);
		$selected = "SELECT '{$pluginId}' as 'pluginid',se.id as 'evtid' FROM [+prefix+]system_eventnames se WHERE name IN ('{$events}')";
		$query = "INSERT INTO [+prefix+]site_plugin_events (pluginid, evtid) {$selected}";
		$query = str_replace('[+prefix+]',$modx->db->table_prefix,$query);
		$modx->db->query($query);
	}
}
