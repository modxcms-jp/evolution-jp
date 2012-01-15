<?php
$base_path = str_replace('\\','/',dirname(__FILE__)) . '/';
define('MODX_API_MODE', true);
require_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();
$modx->invokeEvent('OnWebPageInit');
if(isset($_GET['include']) && strpos($_GET['include'],'..')===false && file_exists(MODX_BASE_PATH . $_GET['include']))
{
	$path = MODX_BASE_PATH . $_GET['include'];
	include_once($path);
}
