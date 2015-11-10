<?php
include_once('initialize.functions.inc');
$init = new MODX_INIT;

$init->fix_favicon_req();
$init->check_phpvar();
$init->fix_request_time();
$init->fix_document_root();
$init->fix_magic_quotes();
$init->fix_server_addr();
$init->fix_ssl();

// automatically assign base_path and base_url
if(!isset($base_path)) $base_path = $init->get_base_path();
if(!isset($base_url))  $base_url  = $init->get_base_url($base_path);
if(!isset($site_url))  $site_url  = $init->get_site_url($base_url);
if(!isset($core_path)) $core_path = "{$base_path}manager/includes/";

if (!defined('MODX_BASE_PATH'))    define('MODX_BASE_PATH', $base_path);
if (!defined('MODX_CORE_PATH'))    define('MODX_CORE_PATH', str_replace('\\','/',dirname(__FILE__)).'/');
if (!defined('MODX_BASE_URL'))     define('MODX_BASE_URL', $base_url);
if (!defined('MODX_SITE_URL'))     define('MODX_SITE_URL', $site_url);
if (!defined('MODX_MANAGER_PATH')) define('MODX_MANAGER_PATH', "{$base_path}manager/");
if (!defined('MODX_MANAGER_URL'))  define('MODX_MANAGER_URL', "{$site_url}manager/");

require_once(MODX_CORE_PATH . 'version.inc.php');

if (defined('IN_MANAGER_MODE')) $init->init_mgr();

if (!defined('E_DEPRECATED')) define('E_DEPRECATED', 8192);

error_reporting(E_ALL & ~E_NOTICE);
