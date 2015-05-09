<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	$value = $this->parseInput($value,'||');
	$p = $params['delim'] ? $params['delim']:',';
	if ($p=="\\n") $p = "\n";
	return str_replace('||',$p,$value);
