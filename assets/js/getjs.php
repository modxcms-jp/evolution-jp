<?php
if(!isset($_GET['target']) || empty($_GET['target'])) return;
$target = $_GET['target'];
if (!file_exists($target) || !is_dir($target)) return;
if (!function_exists('scandir')) include_once('../upgradephp/upgrade.php');

$files = scandir($target,1);
if(0<count($files) && $files!=='..' && $files!=='.')
{
	readfile($files[0]);
}
