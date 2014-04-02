<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	if ($value !='' || $params['default']=='Yes')
	{
		$timestamp = $this->getUnixtimeFromDateString($value);
		$p = $params['format'] ? $params['format'] : $this->toDateFormat(null, 'formatOnly');
		$o = strftime($p,$timestamp);
	}
	else $o = '';

	return $o;
