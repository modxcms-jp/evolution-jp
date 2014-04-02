<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	$value = $this->parseInput($value);
	return $this->getUnixtimeFromDateString($value);
