<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	$value = $this->parseInput($value);
	$format = strtolower($params['stringformat']);
	if($format=='zen-han')            $o = mb_convert_kana($value,'as',$this->config['modx_charset']);
	else if($format=='han-zen')       $o = mb_convert_kana($value,'AS',$this->config['modx_charset']);
	else if($format=='upper case')    $o = strtoupper($value);
	else if($format=='lower case')    $o = strtolower($value);
	else if($format=='sentence case') $o = ucfirst($value);
	else if($format=='capitalize')    $o = ucwords($value);
	else if($format=='nl2br')         $o = nl2br($value);
	else if($format=='number format') $o = number_format($value);
	else if($format=='htmlspecialchars') $o = htmlspecialchars($value,ENT_QUOTES,$this->config['modx_charset']);
	else if($format=='htmlentities')  $o = htmlentities($value,ENT_QUOTES,$this->config['modx_charset']);
	else $o = $value;
	return $o;
