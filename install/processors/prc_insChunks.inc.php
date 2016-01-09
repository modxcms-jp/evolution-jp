<?php
if (empty($formvChunks) && empty($installdata)) return;

echo "<h3>{$lang_chunks}:</h3>";
foreach ($tplChunks as $i=>$tplInfo)
{
	if(in_array('sample', $tplInfo['installset']) && $installdata == 1)
		$installSample = true;
	else $installSample = false;
	
	if(!in_array($i, $formvChunks) && !$installSample) continue;
	
	$overwrite = $tplInfo['overwrite'];
	
	$name = $modx->db->escape($tplInfo['name']);
	$dbv_chunk = $modx->db->getObject('site_htmlsnippets', "name='{$name}'");
	if($dbv_chunk) $update = true;
	else           $update = false;
	
	$tpl_file_path = $tplInfo['tpl_file_path'];
	
	if (!is_file($tpl_file_path))
	{
		echo ng($name,"{$lang_unable_install_chunk} '{$tpl_file_path}' {$lang_not_found}");
		continue;
	}
	$snippet = preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', file_get_contents($tpl_file_path), 1);
	
	$f = array();
	$f['description'] = $tplInfo['description'];
	$f['snippet']     = $snippet;
	$f['category']    = getCreateDbCategory($tplInfo['category']);
	$f = $modx->db->escape($f);
	
	if ($update)
	{
		if($overwrite == 'false')
		{
			$rs =true;
			$i = 0;
			while($rs === true)
			{
				$newname = $tplInfo['name'] . '-' . str_replace('.', '_', $modx_version);
				if(0<$i) $newname . "({$i})";
				$newname = $modx->db->escape($newname);
				$rs = $modx->db->getObject('site_htmlsnippets', "name='{$newname}'");
				$name = $newname;
				$i++;
			}
		}
		if (!@ $modx->db->update($f, '[+prefix+]site_htmlsnippets', "name='{$name}'"))
		{
			$errors += 1;
			showError();
			return;
		}
		echo ok($name,$lang_upgraded);
	}
	else
	{
		$f['name'] = $name;
		if (!@ $modx->db->insert($f, '[+prefix+]site_htmlsnippets'))
		{
			$errors += 1;
			showError();
			return;
		}
		echo ok($name,$lang_installed);
	}
}
