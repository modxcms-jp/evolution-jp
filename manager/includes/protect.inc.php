<?php
/**
 *    Protect against some common security flaws
 */

// Null is evil
if (isset($_SERVER['QUERY_STRING']) && strpos(urldecode($_SERVER['QUERY_STRING']), chr(0)) !== false)
    die();

// Unregister globals
if (@ ini_get('register_globals')) exit('Can not use register_globals');

if (!function_exists('modx_sanitize_gpc'))
{
	function modx_sanitize_gpc(& $target, $count=0)
	{
		$s = array('[[',']]','[!','!]','[*','*]','[(',')]','{{','}}','[+','+]','[~','~]','[^','^]');
		foreach($s as $_)
		{
			$r[] = " {$_['0']} {$_['1']} ";
		}
		foreach ($target as $key => $value)
		{
			if (is_array($value))
			{
				$count++;
				if(10 < $count)
				{
					echo 'too many nested array';
					exit;
				}
				modx_sanitize_gpc($value, $count);
			}
			else
			{
				$value = str_replace($s,$r,$value);
				$value = str_ireplace('<script', 'sanitized_by_modx<s cript', $value);
				$value = preg_replace('/&#(\d+);/', 'sanitized_by_modx& #$1', $value);
				$target[$key] = $value;
			}
			$count=0;
		}
		return $target;
	}
}
modx_sanitize_gpc($_GET);
if (!defined('IN_MANAGER_MODE') || (defined('IN_MANAGER_MODE') && (!IN_MANAGER_MODE || IN_MANAGER_MODE == 'false')))
{
    if(session_id()==='' || $_SESSION['mgrPermissions']['save_document']!=1) modx_sanitize_gpc($_POST);
}
modx_sanitize_gpc($_COOKIE);
modx_sanitize_gpc($_REQUEST);

foreach (array ('PHP_SELF', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'QUERY_STRING') as $key) {
    $_SERVER[$key] = isset ($_SERVER[$key]) ? htmlspecialchars($_SERVER[$key], ENT_QUOTES) : null;
}

// Unset vars
unset ($key, $value);
