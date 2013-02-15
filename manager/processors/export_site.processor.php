<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('export_static'))
{
	$e->setError(3);
	$e->dumpError();
}

if(!isset($_POST['export'])) exit;

if($modx->config['friendly_urls']==0)
{
	$modx->config['friendly_urls']  = 1;
	$modx->config['use_alias_path'] = 1;
}
$export = new EXPORT_SITE();

$maxtime = (is_numeric($_POST['maxtime'])) ? $_POST['maxtime'] : 30;
@set_time_limit($maxtime);
$exportstart = $export->get_mtime();

if(is_dir(MODX_BASE_PATH . 'temp'))       $filepath = MODX_BASE_PATH . 'temp/export';
elseif(is_dir(MODX_BASE_PATH . 'assets')) $filepath = MODX_BASE_PATH . 'assets/export';
if(strpos($modx->config['base_path'],"{$filepath}/")===0 && 0 <= strlen(str_replace("{$filepath}/",'',$modx->config['base_path'])))
{
	return $_lang['export_site.static.php6'];
}
elseif($modx->config['rb_base_dir'] === "{$filepath}/")
{
	return $modx->parsePlaceholder($_lang['export_site.static.php7'],'rb_base_url=' . $modx->config['base_url'] . $modx->config['rb_base_url']);
}

$noncache = $_POST['includenoncache']==1 ? '' : 'AND cacheable=1';

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

$export->ignore_ids = $ignore_ids;

// Support export alias path

if (is_dir($filepath))
{
	$export->removeDirectoryAll($filepath);
}
if(!is_dir($filepath))
{
	@mkdir($filepath, 0777, true);
	@chmod($filepath, 0777);
}
if(!is_writable($filepath))
{
	return $_lang['export_site_target_unwritable'];
}

$where = "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) {$noncache} {$ignore_ids}";
$rs  = $modx->db->select('count(id) as total','[+prefix+]site_content',$where);
$row = $modx->db->getRow($rs);
echo sprintf($_lang['export_site_numberdocs'], $row['total']);
$n = 1;
$export->exportDir(0, $filepath, $n, $row['total']);

$exportend = $export->get_mtime();
$totaltime = ($exportend - $exportstart);
echo sprintf ('<p>'.$_lang["export_site_time"].'</p>', round($totaltime, 3));



class EXPORT_SITE
{
	var $ignore_ids;
	
	function EXPORT_SITE()
	{
	}
	
	function get_mtime()
	{
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$mtime = $mtime[1] + $mtime[0];
		return $mtime;
	}
	
	function removeDirectoryAll($directory)
	{
		$directory = rtrim($directory,'/');
		// if the path is not valid or is not a directory ...
		if(strpos($directory,MODX_BASE_PATH)===false) return FALSE;
		
		if(!is_dir($directory))          return FALSE;
		elseif(!is_readable($directory)) return FALSE;
		else
		{
			foreach(glob($directory . '/*') as $path)
			{
				if(is_dir($path)) $this->removeDirectoryAll($path);// call myself
				else              @unlink($path);
			}
		}
		return (@rmdir($directory));
	}

	function writeAPage($docid, $filepath)
	{
		global  $modx,$_lang;
		
		if($_POST['generate_mode']==='direct')
		{
			$back_lang = $_lang;
			$src = $modx->executeParser($docid);
			//$modx->postProcess();
			$_lang = $back_lang;
		}
		else $somecontent = file_get_contents(MODX_SITE_URL . "index.php?id={$docid}");
		
		
		if($src !== false)
		{
			$repl_before = $_POST['repl_before'];
			$repl_after  = $_POST['repl_after'];
			if($repl_before!==$repl_after) $src = str_replace($repl_before,$repl_after,$src);
			$result = file_put_contents($filepath,$src);
			
			if($result !== false) return 'success';
			else                  return 'failed_no_write';
		}
		else                      return 'no_retrieve';
	}

	function getPageName($docid, $alias, $prefix, $suffix)
	{
		if(empty($alias))
		{
			$filename = $prefix.$docid.$suffix;
		}
		else
		{
			$pa = pathinfo($alias); // get path info array
			$tsuffix = !empty($pa['extension']) ? '':$suffix;
			$filename = $prefix.$alias.$tsuffix;
		}
		return $filename;
	}

	function scanDirectory($path, $docnames)
	{
		// if the path has a slash at the end, remove it
		$path = rtrim($path,'/');
		// if the path is not valid or is not a directory ...
		if(strpos($path,MODX_BASE_PATH)===false) return FALSE;
		
		if(!file_exists($path) || !is_dir($path))
		{
			return FALSE;
		}
		elseif(!is_readable($path))
		{
			return FALSE;
		}
		else
		{
			$files = glob($path . '/*');
			if(0 < count($files))
			{
				foreach($files as $filepath)
				{
					$filename = substr($filepath,strlen($path . '/'));
					if(!in_array($filename, $docnames))
					{
						if(is_dir($filepath)) $this->removeDirectoryAll($filepath);
						else                  @unlink($filepath);
					}
				}
			}
			return TRUE;
		}
	}

	function exportDir($dirid, $dirpath, &$i, $total)
	{
		global $_lang;
		global $modx;
		
		$ignore_ids = $this->ignore_ids;
		$dirpath = $dirpath . '/';
		$prefix = $modx->config['friendly_url_prefix'];
		$suffix = $modx->config['friendly_url_suffix'];
		
		$tpl = ' <span class="[+status+]">[+msg1+]</span> [+msg2+]</span><br />';
		$ph = array();
		
		$ph['status'] = 'fail';
		$ph['msg1']   = $_lang['export_site_failed'];
		$ph['msg2']   = $_lang["export_site_failed_no_write"] . ' - ' . $filepath;
		$msg_failed_no_write    = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['msg2']   = $_lang["export_site_failed_no_retrieve"];
		$msg_failed_no_retrieve = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['status'] = 'success';
		$ph['msg1']   = $_lang['export_site_success'];
		$ph['msg2']   = '';
		$msg_success            = $modx->parsePlaceholder($tpl,$ph);

		
		$fields = "id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, editedon, published";
		$noncache = $_POST['includenoncache']==1 ? '' : 'AND cacheable=1';
		$where = "parent = {$dirid} AND deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) {$noncache} {$ignore_ids}";
		$rs = $modx->db->select($fields,'[+prefix+]site_content',$where);
		$dircontent = array();
		$ph = array();
		while($row = $modx->db->getRow($rs))
		{
			$row['alias'] = urldecode($row['alias']);
			
			if (!$row['wasNull'])
			{ // needs writing a document
				$docname = $this->getPageName($row['id'], $row['alias'], $prefix, $suffix);
				$ph['count']     = $i;
				$ph['total']     = $total;
				$ph['pagetitle'] = $row['pagetitle'];
				$ph['id']        = $row['id'];
				echo $modx->parsePlaceholder($_lang['export_site_exporting_document'], $ph);
				$i++;
				$filename = $dirpath.$docname;
				if (!file_exists($filename) || (filemtime($filename) < $row['editedon']) || $_POST['target']=='1')
				{
					if($row['published']==1)
					{
						switch($this->writeAPage($row['id'], $filename))
						{
							case 'failed_no_write':
								echo $msg_failed_no_write;
								exit;
								break;
							case 'failed_no_retrieve':
								echo $msg_failed_no_retrieve;
								exit;
								break;
							default:
								echo $msg_success;
						}
					}
					else
					{
						echo ' <span class="fail">'.$_lang["export_site_failed"]."</span> ".$_lang["export_site_failed_no_retrieve"].'<br />';
					}
				}
				else
				{
					echo ' <span class="success">'.$_lang['export_site_success']."</span> ".$_lang["export_site_success_skip_doc"].'<br />';
				}
				$dircontent[] = $docname;
			}
			if ($row['isfolder'])
			{ // needs making a folder
				if(empty($row['alias'])) $row['alias'] = $row['id'];
				$dirname = $dirpath . $row['alias'];
				if(strpos($dirname,MODX_BASE_PATH)===false) return FALSE;
				if (!is_dir($dirname))
				{
					if (file_exists($dirname)) @unlink($dirname);
					mkdir($dirname);
					@chmod($dirname, 0777);
					if ($row['wasNull'])
					{
						$ph['count']     = $i;
						$ph['total']     = $total;
						$ph['pagetitle'] = $row['pagetitle'];
						$ph['id']        = $row['id'];
						echo $modx->parsePlaceholder($_lang['export_site_exporting_document'], $ph);
						$i++;
						echo ' <span class="success">'.$_lang['export_site_success'].'</span><br />';
					}
				}
				else
				{
					if ($row['wasNull'])
					{
						$ph['count']     = $i;
						$ph['total']     = $total;
						$ph['pagetitle'] = $row['pagetitle'];
						$ph['id']        = $row['id'];
						echo $modx->parsePlaceholder($_lang['export_site_exporting_document'], $ph);
						$i++;
						echo ' <span class="success">' . $_lang['export_site_success'] . '</span>' . $_lang["export_site_success_skip_dir"] . '<br />';
					}
				}
				if($modx->config['make_folders']==='1')
				{
					rename($filename,$dirname . '/index.html');
				}
				$this->exportDir($row['id'], $dirname . '/', $i, $total);
				$dircontent[] = $row['alias'];
			}
		}
		// remove No-MODx files/dirs
//		if (!$this->scanDirectory($dirpath, $dircontent)) exit;
	}
}