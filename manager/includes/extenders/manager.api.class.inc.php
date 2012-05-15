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
}
