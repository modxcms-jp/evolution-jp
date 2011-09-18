<?php
////////////////// default settings
	if(!isset($hideSubMenus)) $hideSubMenus = 1;
	if(!isset($ignoreHidden)) $ignoreHidden = true;
	if(!isset($startId))      $startId      = 0;
	
////////////////// template
	if(!isset($outerTpl)) $outerTpl = '<div id="breadcrumbnav">[+home+][+wf.wrapper+]</div>';
	else                  $outerTpl = fetch($outerTpl);
	if(!isset($innerTpl)) $innerTpl = '[+wf.wrapper+]';
	else                  $innerTpl = fetch($innerTpl);
	if(!isset($rowTpl))   $rowTpl   = ' ';
	else                  $rowTpl = fetch($rowTpl);
	if(!isset($hereTpl))  $hereTpl  = '[+wf.linktext+]';
	else                  $hereTpl = fetch($hereTpl);
	if(!isset($delim))    $delim    = ' &raquo; ';
	else                  $delim = fetch($delim);
	if(!isset($activeParentRowTpl)) $activeParentRowTpl = '<a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+delim+][+wf.wrapper+]';
	else                  $activeParentRowTpl = fetch($activeParentRowTpl);
	
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



	function fetch($tpl)
	{
		global $modx;
		$template = '';
		if(substr($tpl, 0, 5) == "@FILE")
		{
			$template = file_get_contents(ltrim(substr($tpl, 6)));
		}
		elseif(substr($tpl, 0, 5) == "@CODE")
		{
			$template = substr($tpl, 6);
		}
		elseif ($modx->getChunk($tpl) != "")
		{
			$template = $modx->getChunk($tpl);
		}
		else
		{
			$template = $tpl;
		}
		return $template;
	}
