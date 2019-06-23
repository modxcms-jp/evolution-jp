<?php
class WFBC
{
	function __construct()
	{
	}
	
	function fetch($tpl)
	{
		global $modx;
		if(strpos($tpl, '@FILE') === 0)
		{
			$template = file_get_contents(ltrim(substr($tpl, 6)));
		}
		elseif(strpos($tpl, '@CODE') === 0)
		{
			$template = substr($tpl, 6);
		}
		elseif ($modx->getChunk($tpl) != '')
		{
			$template = $modx->getChunk($tpl);
		}
		else
		{
			$template = $tpl;
		}
		return $template;
	}
}