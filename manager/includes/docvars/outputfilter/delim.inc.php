<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	$value = $this->parseInput($value,'||');
	$p = $params['delim'] ? $params['delim']:',';
	if ($p=="\\n") $p = "\n";
	return str_replace('||',$p,$value);
