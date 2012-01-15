<?php
$base_path = str_replace('\\','/',dirname(__FILE__)) . '/';
define('MODX_API_MODE', true);
require_once("{$base_path}index.php");
$modx->db->connect();
$modx->getSettings();
$modx->invokeEvent('OnWebPageInit');
