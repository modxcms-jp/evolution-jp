<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	if ($value !='' || $params['default']=='Yes')
	{
		$timestamp = $this->getUnixtimeFromDateString($value);
		$p = $params['dateformat'] ? $params['dateformat'] : $this->toDateFormat(null, 'formatOnly');
		$o = strftime($p,$timestamp);
	}
	else $o = '';

	return $o;
