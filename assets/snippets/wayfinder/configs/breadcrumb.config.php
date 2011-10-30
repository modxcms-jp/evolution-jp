<?php
////////////////// default settings
	$hideSubMenus = !isset($hideSubMenus) ? 1 : $hideSubMenus;
	$ignoreHidden = !isset($ignoreHidden) ? true : $ignoreHidden;
	$startId      = !isset($startId)      ? 0 : $startId;
	
////////////////// template
	$outerTpl = '<div id="breadcrumbnav">[+home+][+wf.wrapper+]</div>';
	$innerTpl = '[+wf.wrapper+]';
	$rowTpl   = ' ';
	$hereTpl  = '[+wf.linktext+]';
	$delim    = ' &raquo; ';
	$activeParentRowTpl = '<a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+delim+][+wf.wrapper+]';
	
////////////////// build
	$activeParentRowTpl = str_replace('[+delim+]',$delim,$activeParentRowTpl);
	if ($modx->config['site_start'] !== $modx->documentIdentifier)
	{
		$home = $modx->getDocumentObject('id', $modx->config['site_start']);
		$home_title = $home['menutitle'] ? $home['menutitle'] : $home['pagetitle'];
		$homeLink = '<a href="' . $modx->config['site_url'] . '" title="' . $home_title . '">' . $home_title . '</a>' . $delim;
	}
	else 
	{
		$homeLink = '';
	}
	
	if ($modx->config['site_start'] !== $modx->documentIdentifier)
	{
		$outerTpl = '@CODE:' . str_replace('[+home+]',$homeLink,$outerTpl);
	}
	else
	{
		$outerTpl = '@CODE: ';
	}
	
	$innerTpl = '@CODE:' . $innerTpl;
	$rowTpl   = '@CODE:' . $rowTpl;
	$hereTpl  = '@CODE:' . $hereTpl;
	$activeParentRowTpl = '@CODE:' . $activeParentRowTpl;
