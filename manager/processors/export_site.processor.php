<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('export_static'))
{
	$e->setError(3);
	$e->dumpError();
}

$export = new EXPORT_SITE();

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

if (is_dir($export_dir))
{
	$export->removeDirectoryAll($export_dir);
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

$where = "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) {$noncache} {$ignore_ids}";
$rs  = $modx->db->select('count(id) as total','[+prefix+]site_content',$where);
$row = $modx->db->getRow($rs);

$output = sprintf($_lang['export_site_numberdocs'], $row['total']);
$n = 0;
$export->total = $row['total'];
$output .= $export->exportDir(0, $export_dir);

$exportend = $export->get_mtime();
$totaltime = ($exportend - $export->exportstart);
$output .= sprintf ('<p>'.$_lang["export_site_time"].'</p>', round($totaltime, 3));
return $output;


class EXPORT_SITE
{
	var $total;
	var $count;
	var $ignore_ids;
	var $exportstart;
	var $repl_before;
	var $repl_after;
	var $output = array();
	
	function EXPORT_SITE()
	{
		$maxtime = (is_numeric($_POST['maxtime'])) ? $_POST['maxtime'] : 30;
		@set_time_limit($maxtime);
		$this->exportstart = $this->get_mtime();
		$this->repl_before = $_POST['repl_before'];
		$this->repl_after  = $_POST['repl_after'];
		$this->count = 0;
		$this->setUrlMode();
	}
	
	function get_mtime()
	{
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$mtime = $mtime[1] + $mtime[0];
		return $mtime;
	}
	
	function setUrlMode()
	{
		global $modx;
		
		if($modx->config['friendly_urls']==0)
		{
			$modx->config['friendly_urls']  = 1;
			$modx->config['use_alias_path'] = 1;
		}
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
				if(is_dir($path)) $this->removeDirectoryAll($path);
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
			
			$_lang = $back_lang;
		}
		else $somecontent = file_get_contents(MODX_SITE_URL . "index.php?id={$docid}");
		
		
		if($src !== false)
		{
			if($this->repl_before!==$this->repl_after) $src = str_replace($this->repl_before,$this->repl_after,$src);
			$result = file_put_contents($filepath,$src);
			
			if($result !== false) return 'success';
			else                  return 'failed_no_write';
		}
		else                      return 'no_retrieve';
	}

	function getFileName($docid, $alias='', $prefix, $suffix)
	{
		global $modx;
		
		if($alias==='') $filename = $prefix.$docid.$suffix;
		else
		{
			if($modx->config['suffix_mode']==='1' && strpos($alias, '.')!==false)
			{
				$suffix = '';
			}
			$filename = $prefix.$alias.$suffix;
		}
		return $filename;
	}

	function exportDir($dirid, $dirpath)
	{
		global $_lang;
		global $modx;
		
		$ignore_ids = $this->ignore_ids;
		$dirpath = $dirpath . '/';
		$prefix = $modx->config['friendly_url_prefix'];
		$suffix = $modx->config['friendly_url_suffix'];
		
		$tpl = ' <span class="[+status+]">[+msg1+]</span> [+msg2+]</span>';
		$ph = array();
		
		$ph['status'] = 'fail';
		$ph['msg1']   = $_lang['export_site_failed'];
		$ph['msg2']   = $_lang["export_site_failed_no_write"] . ' - ' . $dirpath;
		$msg_failed_no_write    = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['msg2']   = $_lang["export_site_failed_no_retrieve"];
		$msg_failed_no_retrieve = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['status'] = 'success';
		$ph['msg1']   = $_lang['export_site_success'];
		$ph['msg2']   = '';
		$msg_success            = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['msg2']   = $_lang['export_site_success_skip_doc'];
		$msg_success_skip_doc = $modx->parsePlaceholder($tpl,$ph);
		
		$ph['msg2']   = $_lang['export_site_success_skip_dir'];
		$msg_success_skip_dir = $modx->parsePlaceholder($tpl,$ph);
		
		$fields = "id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, editedon, published";
		$noncache = $_POST['includenoncache']==1 ? '' : 'AND cacheable=1';
		$where = "parent = '{$dirid}' AND deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) {$noncache} {$ignore_ids}";
		$rs = $modx->db->select($fields,'[+prefix+]site_content',$where);
		
		$ph = array();
		$ph['total']     = $this->total;
		while($row = $modx->db->getRow($rs))
		{
			$this->count++;
			$row['alias'] = urldecode($row['alias']);
			
			$row['count']     = $this->count;
			
			if (!$row['wasNull'])
			{ // needs writing a document
				$docname = $this->getFileName($row['id'], $row['alias'], $prefix, $suffix);
				$filename = $dirpath.$docname;
				if (!file_exists($filename) || (filemtime($filename) < $row['editedon']) || $_POST['target']=='1')
				{
					if($row['published']==='1')
					{
						switch($this->writeAPage($row['id'], $filename))
						{
							case 'failed_no_write'   : $row['status'] = $msg_failed_no_write   ; exit;
							case 'failed_no_retrieve': $row['status'] = $msg_failed_no_retrieve; exit;
							default:                   $row['status'] = $msg_success;
						}
					}
					else $row['status'] = $msg_failed_no_retrieve;
				}
				else     $row['status'] = $msg_success_skip_doc;
				$this->output[] = $modx->parsePlaceholder($_lang['export_site_exporting_document'], $row);
			}
			else
			{
				$row['status'] = $msg_success_skip_dir;
				$this->output[] = $modx->parsePlaceholder($_lang['export_site_exporting_document'], $row);
			}
			if ($row['isfolder'])
			{ // needs making a folder
				$end_dir = (!empty($row['alias'])) ? $row['alias'] : $row['id'];
				$dir_path = $dirpath . $end_dir;
				if(strpos($dir_path,MODX_BASE_PATH)===false) return FALSE;
				if (!is_dir($dir_path))
				{
					if (is_file($dir_path)) @unlink($dir_path);
					mkdir($dir_path);
					@chmod($dir_path, 0777);
					
				}
				
				
				if($modx->config['make_folders']==='1')
				{
					rename($filename,$dir_path . '/index.html');
				}
				$this->exportDir($row['id'], $dir_path . '/');
			}
		}
		return join("\n", $this->output);
	}
}