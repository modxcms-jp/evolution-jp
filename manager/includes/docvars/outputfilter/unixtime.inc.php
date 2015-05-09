<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	$value = $this->parseInput($value);
	return $this->getUnixtimeFromDateString($value);
