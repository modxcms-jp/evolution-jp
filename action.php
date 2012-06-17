<?php
$base_path = str_replace('\\','/',dirname(__FILE__)) . '/';
if(is_file("{$base_path}autoload.php")) $loaded_autoload = include_once("{$base_path}autoload.php");
define('MODX_API_MODE', true);
require_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();
if(isset($_GET['include']))
{
	$path = $_GET['include'];
	if(strpos($path, 'manager/')===0 && substr($path,strrpos($path,'.'))==='.php')
	{
		$path = str_replace('\\','/',realpath(MODX_BASE_PATH . $path));
		if(strpos($path,MODX_MANAGER_PATH)!==0) exit;
		if(file_exists($path)) include_once($path);
	}
}
