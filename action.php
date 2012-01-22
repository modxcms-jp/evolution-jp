<?php
$base_path = str_replace('\\','/',dirname(__FILE__)) . '/';
define('MODX_API_MODE', true);
require_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();
$modx->invokeEvent('OnWebPageInit');
$path = MODX_BASE_PATH . $_GET['include'];
if(strpos($path,MODX_BASE_PATH . 'manager/')===0 && substr($path,strrpos($path,'.'))==='.php')
{
	if(file_exists($path)) include_once($path);
}
