<?php
	$hideSubMenus = !isset($hideSubMenus) ? 1 : $hideSubMenus;
	$ignoreHidden = !isset($ignoreHidden) ? true : $ignoreHidden;
	
	if ($modx->config['site_start'] == $modx->documentIdentifier)
	{
		$homeLink = '';
	}
	else 
	{
		$home = $modx->getDocumentObject('id', $modx->config['site_start']);
		$home_title = $home['menutitle'] ? $home['menutitle'] : $home['pagetitle'];
		$homeLink = '<a href="' . $modx->config['site_url'] . '" title="' . $home_title . '">' . $home_title . '</a> &raquo; ';
	}
	
	if ($modx->config['site_start'] !== $modx->documentIdentifier)
	{
		$outerTpl = '@CODE:<div id="breadcrumbnav">' . $homeLink . '[+wf.wrapper+]</div>';
	}
	else
	{
		$outerTpl = '@CODE: ';
	}
	
	$innerTpl = '@CODE:[+wf.wrapper+]';
	$rowTpl   = '@CODE: ';
	$hereTpl  = '@CODE:[+wf.linktext+]';
	$activeParentRowTpl = '@CODE:<a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a> &raquo; [+wf.wrapper+]';
?>