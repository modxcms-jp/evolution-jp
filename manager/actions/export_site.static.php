<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('export_static'))
{
	$e->setError(3);
	$e->dumpError();
}

// figure out the base of the server, so we know where to get the documents in order to export them
?>

<script type="text/javascript">
function reloadTree()
{
	// redirect to welcome
	document.location.href = "index.php?r=1&a=7";
}
</script>

<h1><?php echo $_lang['export_site_html']; ?></h1>
<div class="sectionBody">
<?php

if(!isset($_POST['export']))
{
	echo '<p>'.$_lang['export_site_message'].'</p>';
?>

<fieldset style="padding:10px;border:1px solid #ccc;"><legend style="font-weight:bold;"><?php echo $_lang['export_site']; ?></legend>
<form action="index.php" method="post" name="exportFrm">
<input type="hidden" name="export" value="export" />
<input type="hidden" name="a" value="83" />
<style type="text/css">
table.settings {width:100%;}
table.settings td.head {white-space:nowrap;vertical-align:top;padding-right:20px;font-weight:bold;}
</style>
<table class="settings" cellspacing="0" cellpadding="2">
  <tr>
    <td class="head"><?php echo $_lang['export_site_cacheable']; ?></td>
    <td><input type="radio" name="includenoncache" value="1" checked="checked"><?php echo $_lang['yes'];?>
		<input type="radio" name="includenoncache" value="0"><?php echo $_lang['no'];?></td>
  </tr>
  <tr>
    <td class="head">エクスポート対象</td>
    <td><input type="radio" name="target" value="0" checked="checked">更新されたページのみ
		<input type="radio" name="target" value="1">全てのページ</td>
  </tr>
  <tr>
    <td class="head">パス文字列の基準</td>
    <td><input type="text" name="site_url" value="<?php echo $modx->config['site_url']; ?>" style="width:300px;" /></td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site_prefix']; ?></td>
    <td><input type="text" name="prefix" value="<?php echo $modx->config['friendly_url_prefix']; ?>" /></td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site_suffix']; ?></td>
    <td><input type="text" name="suffix" value="<?php echo $modx->config['friendly_url_suffix']; ?>" /></td>
  </tr>
  <tr>
    <td class="head"><?php echo $_lang['export_site_maxtime']; ?></td>
    <td><input type="text" name="maxtime" value="60" />
		<br />
		<small><?php echo $_lang['export_site_maxtime_message']; ?></small>
	</td>
  </tr>
</table>

<ul class="actionButtons">
	<li><a href="#" onclick="document.exportFrm.submit();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang["export_site_start"]; ?></a></li>
</ul>
</form>
</fieldset>

<?php
}
else
{
	$export = new EXPORT_SITE();
	
	$maxtime = (is_numeric($_POST['maxtime'])) ? $_POST['maxtime'] : 30;
	@set_time_limit($maxtime);
	$exportstart = $export->get_mtime();

	$tbl_site_content = $modx->getFullTableName('site_content');
	$filepath = MODX_BASE_PATH . 'assets/export/';
	if(!is_writable($filepath))
	{
		echo $_lang['export_site_target_unwritable'];
		include "footer.inc.php";
		exit;
	}
	
	$noncache = $_POST['includenoncache']==1 ? '' : "AND {$tbl_site_content}.cacheable=1";
	
	// Support export alias path
	
	if($modx->config['friendly_urls']==1 && $modx->config['use_alias_path']==1)
	{
		$sqlcond = "{$tbl_site_content}.deleted=0 AND (({$tbl_site_content}.published=1 AND {$tbl_site_content}.type='document') OR ({$tbl_site_content}.isfolder=1)) $noncache";
		$sql = "SELECT count(id) as total FROM {$tbl_site_content} WHERE {$sqlcond}";
		$rs  = $modx->db->query($sql);
		$row = mysql_fetch_assoc($rs);
		$total = $row['total'];
		printf($_lang['export_site_numberdocs'], $total);
		$n = 1;
		$export->exportDir(0, $filepath, $n, $total);

	}
	else
	{
		$prefix = $_POST['prefix'];
		$suffix = $_POST['suffix'];
	
	// Modified for export alias path  2006/3/24 end
		$sql = "SELECT id, alias, pagetitle FROM {$tbl_site_content} WHERE {$tbl_site_content}.deleted=0 AND {$tbl_site_content}.published=1 AND {$tbl_site_content}.type='document' $noncache";
		$rs = $modx->db->query($sql);
		$total = mysql_num_rows($rs);
		printf($_lang['export_site_numberdocs'], $total);

		for($i=0; $i<$total; $i++)
		{
			$row=mysql_fetch_assoc($rs);

			$id = $row['id'];
			printf($_lang['export_site_exporting_document'], $i+1, $total, $row['pagetitle'], $id);
			$row['alias'] = urldecode($row['alias']);
			$alias = $row['alias'];
		
			if(empty($alias))
			{
				$filename = $prefix.$id.$suffix;
			}
			else
			{
				$pa = pathinfo($alias); // get path info array
				$tsuffix = !empty($pa[extension]) ? '':$suffix;
				$filename = $prefix.$alias.$tsuffix;
			}
			// get the file
			if(@$somecontent = file_get_contents(MODX_SITE_URL . 'index.php?id=' . $id))
			{
				// save it
				$filename = $filepath . $filename;
				// Write $somecontent to our opened file.
				$target_site_url = rtrim($_POST['site_url'],'/') . '/';
				$somecontent = str_replace($modx->config['site_url'],$target_site_url,$somecontent);
				if(file_put_contents($filename, $somecontent) === FALSE)
				{
					echo ' <span class="fail">'.$_lang["export_site_failed"]."</span> ".$_lang["export_site_failed_no_writee"].'<br />';
					exit;
				}
				echo ' <span class="success">'.$_lang['export_site_success'].'</span><br />';
			}
			else
			{
				echo ' <span class="fail">'.$_lang["export_site_failed"]."</span> ".$_lang["export_site_failed_no_retrieve"].'<br />';
			}
		}
	}
	$exportend = $export->get_mtime();
	$totaltime = ($exportend - $exportstart);
	printf ('<p>'.$_lang["export_site_time"].'</p>', round($totaltime, 3));
?>
<ul class="actionButtons">
	<li><a href="#" onclick="reloadTree();"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang["close"]; ?></a></li>
</ul>
<?php
}



class EXPORT_SITE
{
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
		if(!file_exists($directory) || !is_dir($directory))
		{
			return FALSE;
		}
		elseif(!is_readable($directory))
		{
			return FALSE;
		}
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
		
		$src = @file_get_contents(MODX_SITE_URL . 'index.php?id=' . $docid);
		if($src !== false)
		{
			$target_site_url = rtrim($_POST['site_url'],'/') . '/';
			$src = str_replace($modx->config['site_url'],$target_site_url,$src);
			$result = @file_put_contents($filepath,$src);
			if($result !== false)
			{
				echo ' <span class="success">'.$_lang["export_site_success"].'</span><br />';
			}
			else
			{
				echo ' <span class="fail">'.$_lang["export_site_failed"]."</span> " . $_lang["export_site_failed_no_write"] . ' - ' . $filepath . '</span><br />';
				return FALSE;
			}
		}
		else
		{
			echo ' <span class="fail">'.$_lang["export_site_failed"]."</span> ".$_lang["export_site_failed_no_retrieve"].'</span><br />';
//			return FALSE;
		}
		return TRUE;
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
			foreach(glob($path . '/*') as $filepath)
			{
				$filename = substr($filepath,strlen($path . '/'));
				if(!in_array($filename, $docnames))
				{
					if(is_dir($filepath)) $this->removeDirectoryAll($filepath);
					else                  @unlink($filepath);
				}
			}
			return TRUE;
		}
	}

	function exportDir($dirid, $dirpath, &$i, $total)
	{
		global $_lang;
		global $modx;
		
		$tbl_site_content = $modx->getFullTableName('site_content');
		$noncache = $_POST['includenoncache']==1 ? '' : "AND {$tbl_site_content}.cacheable=1";
		$sqlcond = "{$tbl_site_content}.deleted=0 AND (({$tbl_site_content}.published=1 AND {$tbl_site_content}.type='document') OR ({$tbl_site_content}.isfolder=1)) $noncache";
		
		$sql = "SELECT id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, editedon, published FROM {$tbl_site_content} WHERE {$tbl_site_content}.parent = ".$dirid." AND ".$sqlcond;
		$rs = mysql_query($sql);
		$dircontent = array();
		while($row = mysql_fetch_assoc($rs))
		{
			$row['alias'] = urldecode($row['alias']);
			
			if (!$row['wasNull'])
			{ // needs writing a document
				$docname = $this->getPageName($row['id'], $row['alias'], $modx->config['friendly_url_prefix'], $suffix = $modx->config['friendly_url_suffix']);
				printf($_lang['export_site_exporting_document'], $i++, $total, $row['pagetitle'], $row['id']);
				$filename = $dirpath.$docname;
				if (is_dir($filename))
				{
					$this->removeDirectoryAll($filename);
				}
				if (!file_exists($filename) || (filemtime($filename) < $row['editedon']) || $_POST['target']=='1')
				{
					if($row['published']==1)
					{
						if (!$this->writeAPage($row['id'], $filename)) exit;
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
				if (!is_dir($dirname))
				{
					if(file_exists($dirname)) @unlink($dirname);
					mkdir($dirname);
					if ($row['wasNull'])
					{
						printf($_lang['export_site_exporting_document'], $i++, $total, $row['pagetitle'], $row['id']);
						echo ' <span class="success">'.$_lang['export_site_success'].'</span><br />';
					}
				}
				else
				{
					if ($row['wasNull'])
					{
						printf($_lang['export_site_exporting_document'], $i++, $total, $row['pagetitle'], $row['id']);
						echo ' <span class="success">' . $_lang['export_site_success'] . '</span>' . $_lang["export_site_success_skip_dir"] . '<br />';
					}
				}
				$this->exportDir($row['id'], $dirname . '/', $i, $total);
				$dircontent[] = $row['alias'];
			}
		}
		// remove No-MODx files/dirs 
		if (!$this->scanDirectory($dirpath, $dircontent)) exit;
	}
}