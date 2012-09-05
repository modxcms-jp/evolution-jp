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
	var $cwd;
	var $newfolder;
	
	function FileUpload($fckphp_config,$type,$cwd)
	{
		$this->fckphp_config=$fckphp_config;
		$this->type=$type;
		$this->real_cwd   = rtrim($this->fckphp_config['basedir']."/{$type}/{$cwd}",'/');
		define('IN_MANAGER_MODE', true);
		define('MODX_API_MODE', true);
		$base_path = str_replace('\\','/',realpath('../../../../../../../')) . '/';
		include_once($base_path . 'index.php');
		global $modx;
		$modx->db->connect();
		$modx->getSettings();
	}
	
	function cleanFilename($filename)
	{
		$n_filename='';
		
		//Check that it only contains valid characters
		for($i=0;$i<strlen($filename);$i++)
		{
			if (in_array(substr($filename,$i,1),$this->fckphp_config['FileNameAllowedChars']))
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
		
		if(!is_dir($this->real_cwd))
		{
			@mkdir($this->real_cwd,true);
			@chmod($this->real_cwd, $modx->config['new_file_permissions']);
		}
		
		$typeconfig=$this->fckphp_config['ResourceAreas'][$this->type];
		
		header ('content-type: text/html');
		if (count($_FILES) < 1) exit(0);
		
		if (array_key_exists('NewFile',$_FILES))
		{
			if (! $_FILES['NewFile']['error'] && $_FILES['NewFile']['size']<($typeconfig['MaxSize']))
			{	// (*1)
				$filename=explode('/',str_replace("\\",'/',$_FILES['NewFile']['name']));
				$filename=end($filename);	// (*2)
				
				if ($this->cleanFilename($filename) == $filename)
				{	// (*3)
					$lastdot=strrpos($filename, '.');
					
					if ($lastdot!==false)
					{
						$ext=substr($filename,($lastdot+1));
						$filename=substr($filename,0,$lastdot);
						
						if (in_array(strtolower($ext),$typeconfig['AllowedExtensions']))
						{
							$test=0;
							$dirSizes=array();
							$globalSize=0;
							$failSizeCheck=false;
							if ($this->fckphp_config['DiskQuota']['Global']!=-1)
							{
								foreach ($this->fckphp_config['ResourceTypes'] as $resType)
								{
									$dirSizes[$resType]=
										$this->getDirSize(
											$this->fckphp_config['basedir'].'/'.$this->fckphp_config['UserFilesPath']."/$resType");
									
									if ($dirSizes[$resType]===false)
									{
										//Failed to stat a directory, fall out
										$failSizeCheck=true;
										$msg="\\nディスク使用量の測定不能。";
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
											$this->fckphp_config['basedir'].'/'.$this->fckphp_config['UserFilesPath'].'/'.$this->type);
								}
								
								if (($dirSizes[$this->type]+$_FILES['NewFile']['size'])>
									($typeconfig['DiskQuota']*1048576))
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
								if (file_exists($this->real_cwd."/{$filename}.{$ext}"))
								{
									$taskDone=false;
									
									//File already exists, try renaming
									//If there are more than 200 files with
									//	the same name giveup
									for ($i=1;(($i<200)&&($taskDone==false));$i++)
									{
										$uploaded_name = "{$filename}({$i}).{$ext}";	// (*4)
										$target_path = $this->real_cwd . "/{$uploaded_name}";
										if (!file_exists($target_path))
										{
											if (is_uploaded_file($_FILES['NewFile']['tmp_name']))
											{
												if ($modx->manager->modx_move_uploaded_file($_FILES['NewFile']['tmp_name'],$target_path))
												{
													@chmod($target_path,$modx->config['new_file_permissions']); //modified for MODx
													$disp="201,'..{$uploaded_name}'";
												}
												else
												{
													$disp="202,'Failed to upload file[1], internal error.'";
												}
											}
											else
											{
												if (rename($_FILES['NewFile']['tmp_name'],$target_path))
												{
													@chmod($target_path,$modx->config['new_file_permissions']); //modified for MODx
													$disp="201,'{$uploaded_name}'";
												}
												else
												{
													$disp="202,'Failed to upload file[2], internal error.'";
												}
											}
											$taskDone=true;	
										}
									}
									if ($taskDone==false)
									{
										$disp="202,'Failed to upload file[3], internal error..'";
										$uploaded_name = '';
									}
								}
								else
								{
									$uploaded_name = "$filename.$ext";	// (*4)
									$target_path = $this->real_cwd . "/{$uploaded_name}";
									//Upload file
									if (is_uploaded_file($_FILES['NewFile']['tmp_name']))
									{
										if ($modx->manager->modx_move_uploaded_file($_FILES['NewFile']['tmp_name'],$target_path))
										{
											@chmod($target_path,$modx->config['new_file_permissions']); //modified for MODx
											$disp="0";
										}
										else
										{
											$disp="202,'Failed to upload file[4], internal error...'";
										}
									}
									else
									{
										if (rename($_FILES['NewFile']['tmp_name'],$target_path))
										{
											@chmod($target_path,$modx->config['new_file_permissions']); //modified for MODx
											$disp="0";
										}
										else
										{
											$disp="202,'Failed to upload file[5], internal error...'";
										}
									}
								}
								// (*4)
								if (reset(explode(',', $disp)) != '202')
								{
									$uploaded_path = preg_replace('|\\/$|', '', $this->real_cwd);
									$modx->invokeEvent("OnFileManagerUpload",
											array(
												"filepath"	=> $uploaded_path,
												"filename"	=> $uploaded_name
											));
								}
							}
						} else {
							//Disallowed file extension
							$disp="202,'アップロードできない種類のファイルです。'";
						}
						
					}
					else
					{
						//No file extension to check
						$disp="202,'種類を判別できないファイル名です。'";
					}	
				} else {	// (*3)
					$disp="202,'ファイル名に使えない文字が含まれています。'";
				}
			} else {
				//Too big
				$disp="202,'ファイル容量オーバーです。'";
			}
		}
		else
		{
			//No file uploaded with field name NewFile
			$disp="202,'Unable to find uploaded file.'";
		}

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
	
	function getDirSize($dir) {
		$dirSize=0;
		$files = scandir($dir);
		if ($files)
		{
			foreach ($files as $file)
			{
				if (($file!=".")&&($file!="..")) {
					if (is_dir($dir.'/'.$file)) {
						$tmp_dirSize=$this->getDirSize($dir.'/'.$file);
						if ($tmp_dirSize!==false) $dirSize+=$tmp_dirSize;
					} else {
						$dirSize+=filesize($dir.'/'.$file);
					}
				}
			}
		} else {
			return false;
		}
		
		return $dirSize;
	}
}
