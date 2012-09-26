<?php
/*
 * MODx Manager API Class
 * Written by Raymond Irving 2005
 *
 */

global $_PAGE; // page view state object. Usage $_PAGE['vs']['propertyname'] = $value;

// Content manager wrapper class
class ManagerAPI {
	
	var $action; // action directive

	function ManagerAPI(){
		global $action;
		$this->action = $action; // set action directive
	}
	
	function initPageViewState($id=0){
		global $_PAGE;
		$vsid = isset($_SESSION["mgrPageViewSID"]) ? $_SESSION["mgrPageViewSID"] : '';
		if($vsid!=$this->action) {
			$_SESSION["mgrPageViewSDATA"] = array(); // new view state
			$_SESSION["mgrPageViewSID"] = $id>0 ? $id:$this->action; // set id
		}
		$_PAGE['vs'] = &$_SESSION["mgrPageViewSDATA"]; // restore viewstate
	}

	// save page view state - not really necessary,
	function savePageViewState($id=0){
		$_SESSION["mgrPageViewSDATA"] = $_PAGE['vs'];
		$_SESSION["mgrPageViewSID"] = $id>0 ? $id:$this->action;
	}
	
	// check for saved form
	function hasFormValues() {
		if(isset($_SESSION["mgrFormValueId"])) {
			if($this->action==$_SESSION["mgrFormValueId"]) {
				return true;
			}
			else {
				$this->clearSavedFormValues();
			}
		}
	}	
	// saved form post from $_POST
	function saveFormValues($id=0){
		$_SESSION["mgrFormValues"] = $_POST;
		$_SESSION["mgrFormValueId"] = $id>0 ? $id:$this->action;
	}		
	// load saved form values into $_POST
	function loadFormValues(){
		if($this->hasFormValues()) {
			$p = $_SESSION["mgrFormValues"];
			foreach($p as $k=>$v) $_POST[$k]=$v;
			$this->clearSavedFormValues();
			return true;
		}
		else return false;
	}
	// clear form post
	function clearSavedFormValues(){
		unset($_SESSION["mgrFormValues"]);
		unset($_SESSION["mgrFormValueId"]);	
	}
	
	function get_alias_from_title($id,$pagetitle)
	{
	    global $modx;
	    if($id==='') $id = 0;
	    
		$alias = strtolower($modx->stripAlias(trim($pagetitle)));
		
		if(!$modx->config['allow_duplicate_alias'])
		{
		    $tbl_site_content = $modx->getFullTableName('site_content');
		    $rs = $modx->db->select('id',$tbl_site_content,"id<>'{$id}' AND alias='{$alias}'");
			if(0 < $modx->db->getRecordCount($rs))
			{
				$c = 2;
				$_ = $alias;
				while(0 < $modx->db->getRecordCount($modx->db->select('id',$tbl_site_content,"id<>'{$id}' AND alias='{$_}'")))
				{
					$_  = $alias;
					$_ .= "_{$c}";
					$c++;
				}
				$alias = $_;
			}
		}
		else $alias = '';
		
		return $alias;
	}
	
	function get_alias_num_in_folder($id,$parent)
	{
		global $modx;
	    if($id==='') $id = 0;
	    
		$tbl_site_content = $modx->getFullTableName('site_content');
		
		$rs = $modx->db->select('MAX(cast(alias as SIGNED))',$tbl_site_content,"id<>'{$id}' AND parent={$parent} AND alias REGEXP '^[0-9]+$'");
		$_ = $modx->db->getValue($rs);
		if(empty($_)) $_ = 0;
		$_++;
		while(!isset($noduplex))
		{
			$rs = $modx->db->select('id',$tbl_site_content,"id='{$_}' AND parent={$parent} AND alias=''");
			if($modx->db->getRecordCount($rs)==0) $noduplex = true;
			else $_++;
		}
		return $_;
	}
	
	function modx_move_uploaded_file($tmp_path,$target_path)
	{
		global $modx,$image_limit_width;
		
		if(isset($modx->config['image_limit_width']))
			$image_limit_width = $modx->config['image_limit_width'];
		else $image_limit_width = '';
		
		$img = getimagesize($tmp_path);
		switch($img[2])
		{
			case IMAGETYPE_JPEG: $ext = '.jpg'; break;
			case IMAGETYPE_PNG:  $ext = '.png'; break;
			case IMAGETYPE_GIF:  $ext = '.gif'; break;
			case IMAGETYPE_BMP:  $ext = '.bmp'; break;
		}
		if(isset($ext)) $target_path = substr($target_path,0,strrpos($target_path,'.')) . $ext;
		
		if(!isset($ext) || $image_limit_width==='' || $img[0] <= $image_limit_width)
		{
			$rs = move_uploaded_file($tmp_path, $target_path);
			if(!$rs)
			{
				$target_is_writable = (is_writable(dirname($target_path))) ? 'true' : 'false';
				
				$msg  = '$tmp_path = ' . "{$tmp_path}\n";
				$msg .= '$target_path = ' . "{$target_path}\n";
				$msg .= '$image_limit_width = ' . "{$image_limit_width}\n";
				$msg .= '$target_is_writable = ' . "{$target_is_writable}\n";
				if(isset($ext))
				{
					$msg .= 'getimagesize = ' . print_r($img,true);
				}
				
				$msg = str_replace("\n","<br />\n",$msg);
				$modx->logEvent(1,3,$msg,'move_uploaded_file');
			}
			return $rs;
		}
		
		$new_width = $image_limit_width;
		$new_height = (int)( ($img[1]/$img[0]) * $new_width);
		
		switch($img[2])
		{
			case IMAGETYPE_JPEG:
				$tmp_image = imagecreatefromjpeg($tmp_path);
				$new_image = imagecreatetruecolor($new_width, $new_height);
				$rs = imagecopyresampled($new_image,$tmp_image,0,0,0,0,$new_width,$new_height,$img[0],$img[1]);
				if($rs) $rs = imagejpeg($new_image, $target_path, 85);
				break;
			case IMAGETYPE_PNG:
				$tmp_image = imagecreatefrompng($tmp_path);
				$new_image = imagecreatetruecolor($new_width, $new_height);
//				imagealphablending($new_image,false);
//				imagesavealpha($new_image,true);
				$rs = imagecopyresampled($new_image,$tmp_image,0,0,0,0,$new_width,$new_height,$img[0],$img[1]);
				if($rs) $rs = imagepng($new_image, $target_path);
				break;
			case IMAGETYPE_GIF: 
			case IMAGETYPE_BMP:
				if($img[2]==IMAGETYPE_GIF)
					$tmp_image = imagecreatefromgif($tmp_path);
				if($img[2]==IMAGETYPE_BMP)
					$tmp_image = imagecreatefromwbmp($tmp_path);
				$new_image = imagecreatetruecolor($new_width, $new_height);
				$rs = imagecopyresampled($new_image,$tmp_image,0,0,0,0,$new_width,$new_height,$img[0],$img[1]);
				if($rs) $rs = imagepng($new_image, $target_path);
				break;
			default:
		}
		if($new_image)
		{
			imagedestroy($tmp_image);
			imagedestroy($new_image);
		}
		return $rs;
	}
}
