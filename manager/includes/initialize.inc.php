<?php
$init = new MODX_INIT;

$init->check_phpvar();
$init->fix_request_time();
$init->fix_document_root();
$init->fix_magic_quotes();

// automatically assign base_path and base_url
if(!isset($base_path)) $base_path = $init->get_base_path();
if(!isset($base_url))  $base_url  = $init->get_base_url($base_path);
if(!isset($site_url))  $site_url  = $init->get_site_url($base_url);
if(!isset($core_path)) $core_path = "{$base_path}manager/includes/";

if (!defined('MODX_BASE_PATH'))    define('MODX_BASE_PATH', $base_path);
if (!defined('MODX_CORE_PATH'))    define('MODX_CORE_PATH', "{$base_path}manager/includes/");
if (!defined('MODX_BASE_URL'))     define('MODX_BASE_URL', $base_url);
if (!defined('MODX_SITE_URL'))     define('MODX_SITE_URL', $site_url);
if (!defined('MODX_MANAGER_PATH')) define('MODX_MANAGER_PATH', "{$base_path}manager/");
if (!defined('MODX_MANAGER_URL'))  define('MODX_MANAGER_URL', "{$site_url}manager/");

require_once(MODX_CORE_PATH . 'version.inc.php');

if (defined('IN_MANAGER_MODE')) $init->init_mgr();

if (!defined('E_DEPRECATED')) define('E_DEPRECATED', 8192);
if (!defined('E_USER_DEPRECATED')) define('E_USER_DEPRECATED', 16384);

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

// start cms session
if(!function_exists('startCMSSession'))
{
	function startCMSSession()
	{
		global $site_sessionname;
		$_ = crc32(__FILE__);
		$_ = sprintf('%u', $_);
		$_ = base_convert($_,10,36);
		$site_sessionname = 'evo' . $_;
		session_name($site_sessionname);
		session_set_cookie_params(0,MODX_BASE_URL);
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

function set_parser_mode()
{
	if(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == true) return;
	define('IN_ETOMITE_PARSER', 'true'); // provides compatibility with etomite 0.6 and maybe later versions
	define('IN_PARSER_MODE', 'true');
	define('IN_MANAGER_MODE', 'false');
	
	if (!defined('MODX_API_MODE')) define('MODX_API_MODE', false);
	// set some settings, and address some IE issues
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', 0);
	@ini_set('session.use_only_cookies',1);
	session_cache_limiter('');
	header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"'); // header for weird cookie stuff. Blame IE.
	header('Cache-Control: private, must-revalidate');
}

class MODX_INIT {
	function MODX_INIT()
	{
	}
	
    function init_mgr()
    {
    	// send anti caching headers
    	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    	header("Cache-Control: no-store, no-cache, must-revalidate");
    	header("Cache-Control: post-check=0, pre-check=0", false);
    	header("Pragma: no-cache");
    	header("X-UA-Compatible: IE=edge;FF=3;OtherUA=4");
    }
    
    function get_base_path()
    {
    	$self = 'manager/includes/initialize.inc.php';
    	$base_path = str_replace($self,'',str_replace('\\', '/', __FILE__));
    	return $base_path;
    }
    
    function get_base_url($base_path)
    {
    	if(defined('IN_MANAGER_MODE'))
    	{
    		if(strpos($_SERVER['REQUEST_URI'],'/manager/')!==false)
    			return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/manager/')) . '/';
    		elseif(strpos($_SERVER['REQUEST_URI'],'/assets/')!==false)
    			return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/assets/')) . '/';
    	}
    	if(strpos(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']),$_SERVER['SCRIPT_NAME'])===false)
    	{
    		if(strpos(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']),'/install/index.php')!==false)
    			return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/install/')) . '/';
    		else {
    			echo 'Missing base_url';
    			exit;
    		}
    	}
    	$pos = strlen($_SERVER['SCRIPT_FILENAME']) - strlen($_SERVER['SCRIPT_NAME']);
    	$dir = substr($_SERVER['SCRIPT_FILENAME'],$pos);
    	$dir = str_replace('\\', '/', $dir);
    	$dir = substr($dir,0,strrpos($dir,'/')) . '/';
    	$dir = preg_replace('@(.*?)/manager/.*$@', '$1', $dir);
    	$dir = preg_replace('@(.*?)/assets/.*$@', '$1', $dir);
    	if(substr($_SERVER['REQUEST_URI'],0,2)==='/~') $dir = '/~' . substr($dir,1);
    	$dir = rtrim($dir, '/') . '/';
    	return $dir;
    }
    
    function get_site_url($base_url)
    {
    	if($this->is_ssl()) $scheme = 'https://';
    	else         $scheme = 'http://';
    	
    	$host = $_SERVER['HTTP_HOST'];
    	
    	$pos = strpos($host,':');
    	if($pos!==false && ($_SERVER['SERVER_PORT'] == 80 || $this->is_ssl()))
    	{
    		$host= substr($host,0,$pos);
    	}
    	$site_url = $scheme . $host . $base_url;
    	return rtrim($site_url,'/') . '/';
    }
    
    function is_ssl()
    {
    	global $https_port;
    	if(isset($_SERVER['HTTP_HTTPS']))
    		$_SERVER['HTTPS'] = $_SERVER['HTTP_HTTPS'];
    	elseif(isset($_SERVER['HTTP_X_SAKURA_HTTPS']))
    		$_SERVER['HTTPS'] = $_SERVER['HTTP_X_SAKURA_HTTPS'];
    	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==1)
    		$_SERVER['HTTPS'] = 'on';
    	if((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $_SERVER['SERVER_PORT'] == $https_port)
    	{
    		return true;
    	}
    	else return false;
    }
    
    // set the document_root :|
    function fix_document_root()
    {
        if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT']))
        {
            $_SERVER['DOCUMENT_ROOT'] = str_replace($_SERVER['PATH_INFO'], '', str_replace('\\', '/', $_SERVER['PATH_TRANSLATED'])).'/';
        }
    }
    
    // check PHP version. MODX Evolution is compatible with php 4 (4.4.2+)
    function check_phpvar()
    {
        if(version_compare(phpversion(), '5.0.0') < 0)
        {
        	echo 'MODX is compatible with PHP 5.0.0 and higher. Please upgrade your PHP installation!';
        	exit;
        }
    }
    
    function fix_magic_quotes()
    {
        if (version_compare(PHP_VERSION, '5.4') < 0) @set_magic_quotes_runtime(0);
        
        // include_once the magic_quotes_gpc workaround
        if (get_magic_quotes_gpc()) include_once(dirname(__FILE__) . '/quotes_stripper.inc.php');
    }
    
    function fix_request_time()
    {
    	if(!isset($_SERVER['REQUEST_TIME'])) $_SERVER['REQUEST_TIME'] = time();
    }
}
