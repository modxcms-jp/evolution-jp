<?php
// this simulates magic_quotes_gpc off
if (get_magic_quotes_gpc())
{
	function reverse_magic_quotes($array)
	{
		if(is_array($array)) return array_map('reverse_magic_quotes', $array);
		else                 return stripslashes($array);
	}
	
	$_GET     = reverse_magic_quotes($_GET);
	$_POST    = reverse_magic_quotes($_POST);
	$_REQUEST = reverse_magic_quotes($_REQUEST);
	$_COOKIE  = reverse_magic_quotes($_COOKIE);
}
