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
 * File Name: Thumbnail.php
 * 	Implements the Thumbnail command, to return
 * 	a thumbnail to the browser for the sent file,
 * 	if the file is an image an attempt is made to
 * 	generate a thumbnail, otherwise an appropriate
 * 	icon is returned.
 * 	Output is image data
 * 
 * File Authors:
 * 		Grant French (grant@mcpuk.net)
 */

if(!defined('MODX_BASE_PATH') || strpos(str_replace('\\','/',__FILE__), MODX_BASE_PATH)!==0) exit;
include_once(MODX_BASE_PATH.'manager/media/browser/mcpuk/connectors/php/Commands/helpers/iconlookup.php');

class Thumbnail {
	var $fckphp_config;
	var $type;
	var $cwd;
	var $actual_cwd;
	var $filename;
	
	function __construct($fckphp_config,$type,$cwd) {
		$this->fckphp_config=$fckphp_config;
		$this->type=$type;
		$this->actual_cwd=str_replace('//','/',("/$type/".$cwd));
		$this->real_cwd=str_replace('//','/',($this->fckphp_config['basedir'].'/'.$this->actual_cwd));
		$this->real_cwd = rtrim($this->real_cwd,'/');
		$this->filename=str_replace(array('..','/'),'',$_GET['FileName']);
	}
	
	function run()
	{
		global $modx;
		
		//$mimeIcon=getMimeIcon($mime);
		$fullfile=$this->real_cwd.'/'.$this->filename;
		$thumbfile=$this->real_cwd.'/.thumb/'.$this->filename;
		$file_permissions   = octdec($modx->config['new_file_permissions']);
		$folder_permissions = octdec($modx->config['new_folder_permissions']);
		$icon=false;
		
		if (is_file($thumbfile)) {
			$icon=$thumbfile;
		} else {
			$thumbdir = dirname($thumbfile);
			
			$mime = $modx->getMimeType($fullfile);
			$ext=strtolower($this->getExtension($this->filename));
			
			if ($this->isImage($mime,$ext))
			{
				if(!is_dir($thumbdir)) $rs = mkdir($thumbdir,$folder_permissions,true);
				if($rs) chmod($thumbdir,$folder_permissions);
				//Try and find a thumbnail, else try to generate one
				//	else send generic picture icon.
				
				if($this->isJPEG($mime,$ext))    $result=$this->resizeFromJPEG($fullfile);
				elseif($this->isPNG($mime,$ext)) $result=$this->resizeFromPNG($fullfile);
				elseif($this->isGIF($mime,$ext)) $result=$this->resizeFromGIF($fullfile);
				else $result = false;
				
				if ($result!==false && function_exists('imagejpeg'))
				{
					imagejpeg($result,$thumbfile,80);
					@chmod($thumbfile,$file_permissions);
					$icon=$thumbfile;
				}
			}
			if($icon===false) $icon=iconLookup($mime,$ext);
		}
		
		$iconMime = $modx->getMimeType($icon);
		if ($iconMime==false) $iconMime='image/jpeg';
		
		header("Content-type: $iconMime",true);
		readfile($icon);
		
	}
	
	function isImage($mime,$ext) {
		if (
			($mime=="image/gif")||
			($mime=="image/jpeg")||
			($mime=="image/jpg")||
			($mime=="image/pjpeg")||
			($mime=="image/png")||
			($ext=="jpg")||
			($ext=="jpeg")||
			($ext=="png")||
			($ext=="gif") ) {
		
			return true;
		} else {
			return false;
		}
	}
	
	function isJPEG($mime,$ext) {
		if (($mime=="image/jpeg")||($mime=="image/jpg")||($mime=="image/pjpeg")||($ext=="jpg")||($ext=="jpeg")) {
			return true;
		} else {
			return false;
		}
	}

	function isGIF($mime,$ext) {
		if (($mime=="image/gif")||($ext=="gif")) {
			return true;
		} else {
			return false;
		}
	}
	
	function isPNG($mime,$ext) {
		if (($mime=="image/png")||($ext=="png")) {
			return true;
		} else {
			return false;
		}
	}
	
	function getExtension($filename) {
		//Get Extension
		$ext='';
		$lastpos=strrpos($this->filename,'.');
		if ($lastpos!==false) $ext=substr($this->filename,($lastpos+1));
		return strtolower($ext);
	}
	
	function resizeFromJPEG($file) {
		$img = imagecreatefromjpeg($file);
		return (($img)?$this->resizeImage($img):false);
	}
	
	function resizeFromGIF($file) {
		$img=imagecreatefromgif($file);
		return (($img)?$this->resizeImage($img):false);
	}
	
	function resizeFromPNG($file) {
		$img=imagecreatefrompng($file);
		return (($img)?$this->resizeImage($img):false);
	}
	
	function resizeImage($img) {
		//Get size for thumbnail
		$width=imagesx($img); $height=imagesy($img);
		if ($width>$height) { $n_height=$height*(64/$width); $n_width=64; } else { $n_width=$width*(64/$height); $n_height=64; }
		
		$x=0;$y=0;
		if ($n_width<64) $x=round((64-$n_width)/2);
		if ($n_height<64) $y=round((64-$n_height)/2);
		
		$thumb=imagecreatetruecolor(64,64);
		
		#Background colour fix by:
		#Ben Lancaster (benlanc@ster.me.uk)
		$bgcolor = imagecolorallocate($thumb,255,255,255);
		imagefill($thumb, 0, 0, $bgcolor);
		
		if (function_exists("imagecopyresampled")) {
			if (!($result=@imagecopyresampled($thumb,$img,$x,$y,0,0,$n_width,$n_height,$width,$height))) {
				$result=imagecopyresized($thumb,$img,$x,$y,0,0,$n_width,$n_height,$width,$height);
			}
		} else {
			$result=imagecopyresized($thumb,$img,$x,$y,0,0,$n_width,$n_height,$width,$height);
		}

		return ($result)?$thumb:false;
	}
}
