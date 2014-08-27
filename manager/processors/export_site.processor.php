<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('export_static'))
{
	$e->setError(3);
	$e->dumpError();
}

$modx->loadExtension('EXPORT_SITE');

if(is_dir(MODX_BASE_PATH . 'temp'))       $export_dir = MODX_BASE_PATH . 'temp/export';
elseif(is_dir(MODX_BASE_PATH . 'assets')) $export_dir = MODX_BASE_PATH . 'assets/export';

if(strpos($modx->config['base_path'],"{$export_dir}/")===0 && 0 <= strlen(str_replace("{$export_dir}/",'',$modx->config['base_path'])))
	return $_lang['export_site.static.php6'];
elseif($modx->config['rb_base_dir'] === $export_dir . '/')
	return $modx->parseText($_lang['export_site.static.php7'],'rb_base_url=' . $modx->config['base_url'] . $modx->config['rb_base_url']);
elseif(!is_writable($export_dir))
	return $_lang['export_site_target_unwritable'];

$maxtime = (preg_match('@^[0-9]+$@',$_POST['maxtime'])) ? $_POST['maxtime'] : 60;
//@set_time_limit($maxtime);

$modx->export->maxtime       = $maxtime;
$modx->export->generate_mode = $_POST['generate_mode'];
$modx->export->setExportDir($export_dir);
$modx->export->removeDirectoryAll($export_dir);

$ignore_ids      = $modx->getOption('export_ignore_ids');
$repl_before     = $modx->getOption('export_repl_before');
$repl_after      = $modx->getOption('export_repl_after');
$includenoncache = $modx->getOption('export_includenoncache');

$info=array();
$info['generate_mode'] = $_POST['generate_mode'];
$info['ignore_ids']    = $_POST['ignore_ids'];
$info['repl_after']    = $_POST['repl_before'];
$info['repl_after']    = $_POST['repl_after'];
$info['export_dir']    = $export_dir;

$evtOut = $modx->invokeEvent('OnExportPreExec',$info);
if(is_array($evtOut)) echo implode("\n",$evtOut);

$modx->regOption('export_ignore_ids',$_POST['ignore_ids']);
$modx->regOption('export_generate_mode',$_POST['generate_mode']);
$modx->regOption('export_includenoncache',$_POST['includenoncache']);
$modx->regOption('export_repl_before',$_POST['repl_before']);
$modx->regOption('export_repl_after',$_POST['repl_after']);


if($ignore_ids!==$_POST['ignore_ids']
 ||$includenoncache!==$_POST['includenoncache']
 ||$repl_before!==$_POST['repl_before']
 ||$repl_after !==$_POST['repl_after']) {
	$modx->clearCache();
}

$total = $modx->export->getTotal($_POST['ignore_ids'], $modx->config['export_includenoncache']);

$output = sprintf($_lang['export_site_numberdocs'], $total);
$modx->export->total = $total;

$modx->export->repl_before = $_POST['repl_before'];
$modx->export->repl_after  = $_POST['repl_after'];

$output .= $modx->export->run();

$exportend = $modx->export->get_mtime();
$totaltime = ($exportend - $modx->export->exportstart);
$output .= sprintf ('<p>'.$_lang["export_site_time"].'</p>', round($totaltime, 3));

$info=array();
$info['generate_mode'] = $_POST['generate_mode'];
$info['ignore_ids']    = $_POST['ignore_ids'];
$info['repl_after']    = $_POST['repl_before'];
$info['repl_after']    = $_POST['repl_after'];
$info['export_dir']    = $export_dir;
$info['output']    = $output;
$info['totatlime'] = $totaltime;
$evtOut = $modx->invokeEvent('OnExportExec',$info);
if(is_array($evtOut)) echo implode("\n",$evtOut);

return $output;


