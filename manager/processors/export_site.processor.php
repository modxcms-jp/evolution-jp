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
{
	return $_lang['export_site.static.php6'];
}
elseif($modx->config['rb_base_dir'] === "{$export_dir}/")
{
	return $modx->parsePlaceholder($_lang['export_site.static.php7'],'rb_base_url=' . $modx->config['base_url'] . $modx->config['rb_base_url']);
}

$modx->regOption('ignore_ids',$_POST['ignore_ids']);
if($_POST['ignore_ids'] !== '')
{
	$ignore_ids = explode(',', $_POST['ignore_ids']);
	foreach($ignore_ids as $i=>$v)
	{
		$v = $modx->db->escape(trim($v));
		$ignore_ids[$i] = "'{$v}'";
	}
	$ignore_ids = join(',', $ignore_ids);
	$ignore_ids = "AND NOT id IN ({$ignore_ids})";
}
else $ignore_ids = '';

$modx->export->ignore_ids = $ignore_ids;

// Support export alias path

if (is_dir($export_dir))
{
	$modx->export->removeDirectoryAll($export_dir);
}
if(!is_dir($export_dir))
{
	@mkdir($export_dir, 0777, true);
	@chmod($export_dir, 0777);
}
if(!is_writable($export_dir))
{
	return $_lang['export_site_target_unwritable'];
}

$noncache = $modx->config['export_includenoncache']==1 ? '' : 'AND cacheable=1';
$where = "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) {$noncache} {$ignore_ids}";
$rs  = $modx->db->select('count(id) as total','[+prefix+]site_content',$where);
$row = $modx->db->getRow($rs);

$output = sprintf($_lang['export_site_numberdocs'], $row['total']);
$n = 0;
$modx->export->total = $row['total'];
$output .= $modx->export->exportDir(0, $export_dir);

$exportend = $modx->export->get_mtime();
$totaltime = ($exportend - $modx->export->exportstart);
$output .= sprintf ('<p>'.$_lang["export_site_time"].'</p>', round($totaltime, 3));
return $output;


