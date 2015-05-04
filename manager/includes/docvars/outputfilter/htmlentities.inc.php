<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	$value= $this->parseInput($value);
	if($tvtype=='checkbox'||$tvtype=='listbox-multiple')
	{
		// remove delimiter from checkbox and listbox-multiple TVs
		$value = str_replace('||','',$value);
	}
	return htmlentities($value, ENT_NOQUOTES, $this->config['modx_charset']);
