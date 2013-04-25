<?php
/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 * 
 * Licensed under the terms of the GNU Lesser General Public License:
 * 		http://www.opensource.org/licenses/lgpl-license.php
 * 
 * For further information visit:
 * 		http://www.fckeditor.net/
 * 
 * File Name: FileUpload.php
 * 	Implements the FileUpload command,
 * 	Checks the file uploaded is allowed, 
 * 	then moves it to the user data area. 
 * 
 * File Authors:
 * 		Grant French (grant@mcpuk.net)
 *
 * Modified:
 * 		2009-03-23 by Kazuyuki Ikeda (http://www.hikidas.com/)
 * 		(*1) fix the bug `MaxSize` unit mismatch (Kbytes => Bytes)
 * 		(*2) replace `basename` other codes, because it has bugs for multibyte characters
 * 		(*3) refuse the filename has disallowed characters
 * 		     (multibyte characters cause trouble for browsing resources)
 * 		 ++  japanese localization
 * 		2009-03-24 by Kazuyuki Ikeda (http://www.hikidas.com/)
 * 		(*4) add invoking event `OnFileManagerUpload`
 */
class FileUpload {
	var $fckphp_config;
	var $type;
	
	function FileUpload($fckphp_config,$type,$cwd)
	{
		global $modx;
		
		if(!defined('IN_MANAGER_MODE'))
		{
			define('IN_MANAGER_MODE', 'true');
			if(!defined('MODX_API_MODE')) define('MODX_API_MODE', true);
			$self = 'manager/media/browser/mcpuk/connectors/php/Commands/FileUpload.php';
			$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
			include_once("{$base_path}index.php");
		}
		
		if(!isset($_SESSION['mgrValidated'])) exit;
		
		$this->fckphp_config = $fckphp_config;
		$this->type          = $type;
		$type = rtrim($type,'/');
		$cwd  = ltrim($cwd,'/');
		$this->real_cwd = $modx->config['rb_base_dir']  . "{$type}/{$cwd}";
		$this->real_cwd = rtrim($this->real_cwd,'/');
	}
	
	function cleanFilename($filename)
	{
		$n_filename = '';
		
		//Check that it only contains valid characters
		for($i=0;$i<strlen($filename);$i++)
		{
			if(in_array(substr($filename,$i,1),$this->fckphp_config['FileNameAllowedChars']))
			{
				$n_filename .= substr($filename,$i,1);
			}
		}
		
		//If it got this far all is ok
		return $n_filename;
	}
	
	function run()
	{
		global $modx;
		$modx->config['new_file_permissions'] = octdec($modx->config['new_file_permissions']);
		
		$typeconfig=$this->fckphp_config['ResourceAreas'][$this->type];
		
		if(count($_FILES) < 1) exit(0);
		
		if($_FILES['NewFile']['name'])
		{
			$_FILES['NewFile']['name'] = str_replace("\\",'/',$_FILES['NewFile']['name']);
			$filename = explode('/',$_FILES['NewFile']['name']);
			$filename = end($filename);  // (*2)
			if(strpos($filename, '.') !==false)
			{
				$ext = strtolower(substr($filename,strrpos($filename, '.')+1));
			}
		}
		
		if($modx->config['clean_uploaded_filename']==1 && isset($ext))
		{
			$filename = $modx->stripAlias($filename, array('file_manager'));
		}
		elseif (isset($ext) && $this->cleanFilename($filename) !== $filename)
		{
			$filename = date('Ymd-his') . ".{$ext}";
			$disp = "201,'ファイル名に使えない文字が含まれているため変更しました。'";// (*3)
		}
		
		if (!array_key_exists('NewFile',$_FILES)) $disp="202,'Unable to find uploaded file.'"; //No file uploaded with field name NewFile
		elseif($_FILES['NewFile']['error'] || ($typeconfig['MaxSize']) < $_FILES['NewFile']['size'])
		{
			$disp = "202,'ファイル容量オーバーです。'";//Too big
		}
		elseif(!isset($ext))
		{
			$disp = "202,'種類を判別できないファイル名です。'";//No file extension to check
		}
		elseif (!in_array($ext,$typeconfig['AllowedExtensions']))
		{
			$disp = "202,'アップロードできない種類のファイルです。'";//Disallowed file extension
		}
		else
		{
			$filename   = substr($filename,0,strrpos($filename, '.'));
			$test       = 0;
			$dirSizes   = array();
			$globalSize = 0;
			$failSizeCheck=false;
			if ($this->fckphp_config['DiskQuota']['Global']!=-1)
			{
				foreach ($this->fckphp_config['ResourceTypes'] as $resType)
				{
					$dirSizes[$resType] = $this->getDirSize($modx->config['rb_base_dir']."$resType");
					if ($dirSizes[$resType]===false)
					{
						//Failed to stat a directory, fall out
						$failSizeCheck=true;
						$msg="\\nディスク使用量を測定できません。";
						break;
					}
					$globalSize+=$dirSizes[$resType];
				}
				
				$globalSize+=$_FILES['NewFile']['size'];
				
				if (!$failSizeCheck)
				{
					if ($globalSize>($this->fckphp_config['DiskQuota']['Global']*1048576))
					{
						$failSizeCheck=true;
						$msg="\\nリソース全体の割当ディスク容量オーバー";
					}
				}
			}
			
			if (($typeconfig['DiskQuota']!=-1)&&(!$failSizeCheck))
			{
				if ($this->fckphp_config['DiskQuota']['Global']==-1)
				{
					$dirSizes[$this->type]=
						$this->getDirSize(
							$modx->config['rb_base_dir'].$this->type);
				}
				
				if (($dirSizes[$this->type]+$_FILES['NewFile']['size']) > ($typeconfig['DiskQuota']*1048576))
				{
					$failSizeCheck=true;
					$msg="\\nリソース種類別の割当ディスク容量オーバー";
				}
			}
			
			if ((($this->fckphp_config['DiskQuota']['Global']!=-1)||($typeconfig['DiskQuota']!=-1))&&$failSizeCheck)
			{
				//Disk Quota over
				$disp="202,'割当ディスク容量オーバー, ".$msg."'";
			}
			else
			{
				$tmp_name = $_FILES['NewFile']['tmp_name'];
				if (is_file($this->real_cwd."/{$filename}.{$ext}"))
				{
					$taskDone=false;
					
					for($i=1;($i<200 && $taskDone===false);$i++)
					{
						$target = "{$this->real_cwd}/{$filename}({$i}).{$ext}";
						
						if (!is_file($target))
						{
							if (is_uploaded_file($tmp_name))
							{
								if($modx->manager->modx_move_uploaded_file($tmp_name,$target))
								{
									@chmod($target,$modx->config['new_file_permissions']);
									$basename = basename($target);
									$disp="201,'{$basename}'";
								}
								else $disp="202,'Failed to upload file, internal error.'";
							}
							else
							{
								if(rename($tmp_name,($target)))
								{
									@chmod($target,$modx->config['new_file_permissions']);
									$basename = basename($target);
									$disp="201,'{$basename}'";
								}
								else $disp="202,'Failed to upload file, internal error.'";
							}
							$uploaded_name = "{$filename}({$i}).{$ext}";	// (*4)
							$taskDone=true;
						}
					}
					if ($taskDone==false) $disp="202,'Failed to upload file, internal error..'";
				}
				else
				{
					//Upload file
					$target = "$this->real_cwd/{$filename}.{$ext}";
					if (is_uploaded_file($tmp_name))
					{
						if ($modx->manager->modx_move_uploaded_file($tmp_name,$target))
						{
							@chmod($target,$modx->config['new_file_permissions']);
							$disp='0';
						}
						else $disp="202,'Failed to upload file, internal error...'";
					}
					else
					{
						if (rename($tmp_name,($target)))
						{
							@chmod($target,$modx->config['new_file_permissions']);
							$disp='0';
						}
						else $disp="202,'Failed to upload file, internal error...'";
					}
					$uploaded_name = "{$filename}.{$ext}";	// (*4)
				}
				// (*4)
				if (substr($disp,0,3) !== '202')
				{
					$modx->invokeEvent('OnFileManagerUpload',
							array(
								'filepath'	=> $this->real_cwd,
								'filename'	=> $uploaded_name
							));
				}
			}
		}
		
		if(!empty($disp) && $disp!=='0' && substr($disp,0,3) !=='201')
		{
			$modx->logEvent(0,2,$disp,'mcpuk connector');
		}
		header ("content-type: text/html; charset={$modx->config['modx_charset']}");
?>
<html>
<head>
<title>Upload Complete</title>
</head>
<body>
<script type="text/javascript">
window.parent.frames['frmUpload'].OnUploadCompleted(<?php echo $disp; ?>) ;
</script>
</body>
</html>
<?php
	}
	
	function getDirSize($dir)
	{
		$dirSize=0;
		$files = scandir($dir);
		if ($files)
		{
			foreach ($files as $file)
			{
				if (($file != '.')&&($file != '..'))
				{
					if (is_dir("{$dir}/{$file}"))
					{
						$tmp_dirSize=$this->getDirSize("{$dir}/{$file}");
						if ($tmp_dirSize!==false) $dirSize+=$tmp_dirSize;
					}
					else $dirSize += filesize("{$dir}/{$file}");
				}
			}
		}
		else $dirSize = false;
		
		return $dirSize;
	}
}
