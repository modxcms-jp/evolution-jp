<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('bk_manager')) {
	$e->setError(3);
	$e->dumpError();
}

$base_path = MODX_BASE_PATH;
if(!isset($modx->config['snapshot_path'])||strpos($modx->config['snapshot_path'],MODX_BASE_PATH)===false)
{
	if(is_dir("{$base_path}temp/backup/"))       $snapshot_path = "{$base_path}temp/backup/";
	elseif(is_dir("{$base_path}assets/backup/")) $snapshot_path = "{$base_path}assets/backup/";
}
else $snapshot_path = $modx->config['snapshot_path'];

if(!is_dir(rtrim($snapshot_path,'/')))
{
	mkdir(rtrim($snapshot_path,'/'));
	@chmod(rtrim($snapshot_path,'/'), 0777);
}
if(!is_file("{$snapshot_path}.htaccess"))
{
	$htaccess = "order deny,allow\ndeny from all\n";
	file_put_contents("{$snapshot_path}.htaccess",$htaccess);
}
if(!is_writable(rtrim($snapshot_path,'/')))
{
	echo $modx->parseText($_lang["bkmgr_alert_mkdir"],array('snapshot_path'=>$snapshot_path));
	exit;
}

if(!$_POST['file_name'])
{
    $today = $modx->toDateFormat($_SERVER['REQUEST_TIME']);
    $today = str_replace(array('/',' '), '-', $today);
    $today = str_replace(':', '', $today);
    $today = strtolower($today);
    global $path,$modx_version;
    $filename = "{$today}-{$modx_version}.sql";
}
else $filename = $_POST['file_name'];

@set_time_limit(120); // set timeout limit to 2 minutes
include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
$dumper = new Mysqldumper();
$dumper->mode = 'snapshot';
$dumper->contentsOnly = $_POST['contentsOnly'] ? 1:0;
$output = $dumper->createDump();
$dumper->snapshot($snapshot_path.$filename,$output);

$pattern = "{$snapshot_path}*.sql";
$files = glob($pattern,GLOB_NOCHECK);
$total = ($files[0] !== $pattern) ? count($files) : 0;
arsort($files);
while(10 < $total && $limit < 50)
{
	$del_file = array_pop($files);
	unlink($del_file);
	$total = count($files);
	$limit++;
}

if(!empty($output))
{
	$_SESSION['result_msg'] = 'snapshot_ok';
	header('Location: index.php?a=93');
}
else
{
	$e->setError(1, 'Unable to Backup Database');
	$e->dumpError();
}
exit;
