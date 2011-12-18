<?php
/**
 *	MODx Configuration file
 */
$database_type               = '[+database_type+]';
$database_server             = '[+database_server+]';
$database_user               = '[+database_user+]';
$database_password           = '[+database_password+]';
$database_connection_charset = '[+database_connection_charset+]';
$database_connection_method  = '[+database_connection_method+]';
$dbase                       = '`[+dbase+]`';
$table_prefix                = '[+table_prefix+]';

$lastInstallTime             = [+lastInstallTime+];
$site_sessionname            = '[+site_sessionname+]';
$https_port                  = '[+https_port+]';

error_reporting(E_ALL & ~E_NOTICE);
setlocale (LC_TIME, 'ja_JP.UTF-8');
if(function_exists('date_default_timezone_set')) date_default_timezone_set('Asia/Tokyo');

// automatically assign base_path and base_url
if(empty($base_path)) $base_path = assign_base_path();
if(empty($base_url))  $base_url  = assign_base_url();
if(empty($site_url))  $site_url  = assign_site_url($base_url);

if (!defined('MODX_BASE_PATH'))    define('MODX_BASE_PATH', $base_path);
if (!defined('MODX_BASE_URL'))     define('MODX_BASE_URL', $base_url);
if (!defined('MODX_SITE_URL'))     define('MODX_SITE_URL', $site_url);
if (!defined('MODX_MANAGER_PATH')) define('MODX_MANAGER_PATH', $base_path.'manager/');
if (!defined('MODX_MANAGER_URL'))  define('MODX_MANAGER_URL', $site_url.'manager/');

// start cms session
if(!function_exists('startCMSSession'))
{
	function startCMSSession()
	{
		global $site_sessionname;
		session_name($site_sessionname);
		session_start();
		$cookieExpiration= 0;
		if (isset ($_SESSION['mgrValidated']) || isset ($_SESSION['webValidated']))
		{
			$contextKey= isset ($_SESSION['mgrValidated']) ? 'mgr' : 'web';
			if (isset ($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime']) && is_numeric($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime']))
			{
				$cookieLifetime= intval($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime']);
			}
			if ($cookieLifetime)
			{
				$cookieExpiration= time() + $cookieLifetime;
			}
			if (!isset($_SESSION['modx.session.created.time']))
			{
				$_SESSION['modx.session.created.time'] = time();
			}
		}
		setcookie(session_name(), session_id(), $cookieExpiration, MODX_BASE_URL);
	}
}

function assign_base_url()
{
	$sapi= 'undefined';
	if (!strstr($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']) && ($sapi= @ php_sapi_name()) == 'cgi')
	{
		$script_name= $_SERVER['PHP_SELF'];
	}
	else
	{
		$script_name= $_SERVER['SCRIPT_NAME'];
	}
	$conf_dir = str_replace("\\", '/', dirname($script_name));
	$mgr_pos = strlen($conf_dir) - strpos (strrev($conf_dir), strrev('/manager')) - strlen('/manager');
	if($mgr_pos!==false) $conf_dir = substr($conf_dir,0,$mgr_pos+1);
	return rtrim($conf_dir,'/') . '/';
}

function assign_base_path()
{
	$conf_dir = str_replace("\\", '/', dirname(__FILE__));
	$mgr_pos = strlen($conf_dir) - strpos (strrev($conf_dir), strrev('/manager/')) - strlen('/manager/');
	$base_path = substr($conf_dir,0,$mgr_pos);
	return rtrim($base_path,'/') . '/';
}

// assign site_url
function assign_site_url($base_url)
{
	if(is_https($https_port)) $scheme = 'https://';
	else                      $scheme = 'http://';
	
	$host = $_SERVER['HTTP_HOST'];
	
	$pos = strpos($host,':');
	if($pos!==false && ($_SERVER['SERVER_PORT'] == 80 || is_https($https_port)))
	{
		$host= substr($host,0,$pos);
	}
	return $scheme . $host . $base_url;
}

function is_https($https_port)
{
	if((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $_SERVER['SERVER_PORT'] == $https_port)
	{
		return true;
	}
	else return false;
}
