<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('bk_manager')) {
	$e->setError(3);
	$e->dumpError();
}

// Backup Manager by Raymond:

$mode = isset($_POST['mode']) ? $_POST['mode'] : '';

$source = '';
if ($mode=='restore1')
{
	if(isset($_POST['textarea']) && !empty($_POST['textarea']))
	{
		$source = trim($_POST['textarea']);
		$_SESSION['textarea'] = $source . "\n";
	}
	else
		$source = file_get_contents($_FILES['sqlfile']['tmp_name']);
}
elseif ($mode=='restore2')
{
	$base_path = MODX_BASE_PATH;
	if(!isset($modx->config['snapshot_path'])||strpos($modx->config['snapshot_path'],MODX_BASE_PATH)===false)
	{
		if(is_dir("{$base_path}temp/backup/"))       $snapshot_path = "{$base_path}temp/backup/";
		elseif(is_dir("{$base_path}assets/backup/")) $snapshot_path = "{$base_path}assets/backup/";
	}
	else $snapshot_path = $modx->config['snapshot_path'];
	
	if(strpos($_POST['filename'],'..')===false)
		$snapshot_path .= $_POST['filename'];
	if(!is_file($snapshot_path)) exit('Error');
	$source = file_get_contents($snapshot_path);
}

if(!empty($source))
{
	include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
	$dumper = new Mysqldumper();
	$dumper->import_sql($source);
}
header('Location: index.php?r=9&a=93');
