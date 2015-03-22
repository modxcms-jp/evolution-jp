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
	
	function initPageViewState($id=0)
	{
		global $_PAGE;
		$vsid = isset($_SESSION['mgrPageViewSID']) ? $_SESSION['mgrPageViewSID'] : '';
		if($vsid!=$this->action)
		{
			$_SESSION['mgrPageViewSDATA'] = array(); // new view state
			$_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action; // set id
		}
		$_PAGE['vs'] = &$_SESSION['mgrPageViewSDATA']; // restore viewstate
	}

	// save page view state - not really necessary,
	function savePageViewState($id=0)
	{
		$_SESSION['mgrPageViewSDATA'] = $_PAGE['vs'];
		$_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action;
	}
	
	// check for saved form
	function hasFormValues() {
		if(isset($_SESSION['mgrFormValueId']) && isset($_SESSION['mgrFormValues']) && !empty($_SESSION['mgrFormValues']))
		{
			if($this->action==$_SESSION['mgrFormValueId'] && is_array($_SESSION['mgrFormValues']))
			{
				return true;
			}
			else
			{
				$this->clearSavedFormValues();
			}
		}
		return false;
	}
	// saved form post from $_POST
	function saveFormValues($id=0)
	{
		$_SESSION['mgrFormValues']  = $_POST;
		$_SESSION['mgrFormValueId'] = ($id > 0) ? $id : $this->action;
	}
	// load saved form values into $_POST
	function loadFormValues()
	{
		if($this->hasFormValues())
		{
			$p = $_SESSION['mgrFormValues'];
			foreach($p as $k=>$v)
			{
				$_POST[$k]=$v;
			}
			$this->clearSavedFormValues();
			return $_POST;
		}
		else return false;
	}
	// clear form post
	function clearSavedFormValues()
	{
		unset($_SESSION['mgrFormValues']);
		unset($_SESSION['mgrFormValueId']);
	}
	
	function get_alias_from_title($id=0,$pagetitle='')
	{
	    global $modx;
	    if($id==='') $id = 0;

	    $pagetitle = trim($pagetitle);
	    if($pagetitle!=='')
	    	$alias = strtolower($modx->stripAlias($pagetitle));
	    return '';
		
		if(!$modx->config['allow_duplicate_alias'])
		{
		    $rs = $modx->db->select('id','[+prefix+]site_content',"id<>'{$id}' AND alias='{$alias}'");
			if(0 < $modx->db->getRecordCount($rs))
			{
				$c = 2;
				$_ = $alias;
				while(0 < $modx->db->getRecordCount($modx->db->select('id','[+prefix+]site_content',"id!='{$id}' AND alias='{$_}'")))
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
	
	function get_alias_num_in_folder($id='0',$parent='0')
	{
		global $modx;
		
	    if(empty($id))     $id     = '0';
	    if(empty($parent)) $parent = '0';
	    
		$rs = $modx->db->select('MAX(cast(alias as SIGNED))','[+prefix+]site_content',"id<>'{$id}' AND parent='{$parent}' AND alias REGEXP '^[0-9]+$'");
		$_ = $modx->db->getValue($rs);
		if(empty($_)) $_ = 0;
		$_++;
		while(!isset($noduplex))
		{
			$rs = $modx->db->select('id','[+prefix+]site_content',"id='{$_}' AND parent={$parent} AND alias=''");
			if($modx->db->getRecordCount($rs)==0) $noduplex = true;
			else $_++;
		}
		return $_;
	}
	
	function modx_move_uploaded_file($tmp_path,$target_path)
	{
		global $modx,$image_limit_width;
		
		$target_path = str_replace('\\','/', $target_path);
		$new_file_permissions = octdec($modx->config['new_file_permissions']);
		
		if(strpos($target_path, $modx->config['filemanager_path'])!==0)
		{
			$msg = "Can't upload to '{$target_path}'.";
			$modx->logEvent(1,3,$msg,'move_uploaded_file');
		}
		
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
			else @chmod($target_path, $new_file_permissions);
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
		if($rs) @chmod($target_path, $new_file_permissions);
		return $rs;
	}
	
	function validate_referer($flag)
	{
		global $modx;
		
		if($flag!=1) return;
        if(isset($_GET['frame']) && $_GET['frame']==='main')
        {
        	switch($modx->manager->action)
        	{
            	case '3' :
            	case '120' :
            	case '4' :
            	case '72' :
            	case '27' :
            	case '8' :
            	case '87' :
            	case '88' :
            	case '11' :
            	case '12' :
            	case '74' :
            	case '28' :
            	case '35' :
            	case '38' :
            	case '16' :
            	case '19' :
            	case '22' :
            	case '23' :
            	case '77' :
            	case '78' :
            	case '18' :
            	case '106' :
            	case '107' :
            	case '108' :
            	case '100' :
            	case '101' :
            	case '102' :
            	case '200' :
            	case '31' :
            	case '40' :
            	case '91' :
            	case '41' :
            	case '92' :
            	case '17' :
            	case '53' :
            	case '13' :
            	case '10' :
            	case '70' :
            	case '71' :
            	case '59' :
            	case '75' :
            	case '99' :
            	case '86' :
            	case '76' :
            	case '83' :
            	case '93' :
            	case '95' :
            	case '9' :
            	case '301' :
            	case '302' :
            	case '115' :
            	case '112' :
                	unset($_GET['frame']);
                	$_SESSION['mainframe'] = $_GET;
                	header('Location:' . MODX_MANAGER_URL);
                	exit;
                	break;
            	default :
        	}
        }

		$referer = isset($_SERVER['HTTP_REFERER']) ? strip_tags($_SERVER['HTTP_REFERER']) : '';
		
		if(empty($referer))
		{
			echo "A possible CSRF attempt was detected. No referer was provided by the server.";
			exit();
		}
		else
		{
			$referer  = str_replace(array('http://', 'https://'), '//', $referer);
			$site_url = str_replace(array('http://', 'https://'), '//', MODX_SITE_URL);
			if(stripos($referer, $site_url)!==0)
			{
				echo "A possible CSRF attempt was detected from referer: {$referer}.";
				exit();
			}
		}
	}
	
	function checkToken()
	{
		if(isset($_POST['token']) && !empty($_POST['token']))    $token = $_POST['token'];
		elseif(isset($_GET['token']) && !empty($_GET['token']))  $token = $_GET['token'];
		else                                                     $token = false;
		
		if(isset($_SESSION['token']) && !empty($_SESSION['token']) && $_SESSION['token']===$token)
		{
			$rs =true;
		}
		else $rs = false;
		$_SESSION['token'] = '';
		return $rs;
	}
	
	function makeToken()
	{
		$newToken = uniqid('');
		$_SESSION['token'] = $newToken;
		return $newToken;
	}
	
	function remove_locks($action='all',$limit_time=120)
	{
		global $modx;
		
		$limit_time = time() - $limit_time;
		if($action === 'all')
		{
			$action = '';
		}
		else
		{
			$action = intval($action);
			$action = "action={$action} and";
		}
		$modx->db->delete('[+prefix+]active_users',"{$action} lasthit < {$limit_time}");
	}
	
	function genHash($password, $seed='1')
	{ // $seed is user_id basically
		global $modx;
		
		if(isset($modx->config['pwd_hash_algo']) && !empty($modx->config['pwd_hash_algo']))
			$algorithm = $modx->config['pwd_hash_algo'];
		else $algorithm = 'UNCRYPT';
		
		$salt = md5($password . $seed);
		
		switch($algorithm)
		{
			case 'BLOWFISH_Y':
				$salt = '$2y$07$' . substr($salt,0,22);
				break;
			case 'BLOWFISH_A':
				$salt = '$2a$07$' . substr($salt,0,22);
				break;
			case 'SHA512':
				$salt = '$6$' . substr($salt,0,16);
				break;
			case 'SHA256':
				$salt = '$5$' . substr($salt,0,16);
				break;
			case 'MD5':
				$salt = '$1$' . substr($salt,0,8);
				break;
			case 'UNCRYPT':
				break;
		}
		
		if($algorithm!=='UNCRYPT')
		{
			$password = sha1($password) . crypt($password,$salt);
		}
		else $password = sha1($salt.$password);
		
		$result = strtolower($algorithm) . '>' . md5($salt.$password) . substr(md5($salt),0,8);
		
		return $result;
	}
	
	function getUserHashAlgorithm($uid)
	{
		global $modx;
		
		$user = $modx->db->getObject('manager_users',"id='{$uid}'");
		
		if(strpos($user->password,'>')===false) $algo = 'NOSALT';
		else
		{
			$algo = substr($user->password,0,strpos($user->password,'>'));
		}
		return strtoupper($algo);
	}
	
	function checkHashAlgorithm($algorithm='')
	{
		if(empty($algorithm)) return;
		
		switch($algorithm)
		{
			case 'BLOWFISH_Y':
				if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
				{
					if(version_compare('5.3.7', PHP_VERSION) <= 0) $result = true;
				}
				break;
			case 'BLOWFISH_A':
				if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1) $result = true;
				break;
			case 'SHA512':
				if(defined('CRYPT_SHA512') && CRYPT_SHA512 == 1) $result = true;
				break;
			case 'SHA256':
				if(defined('CRYPT_SHA256') && CRYPT_SHA256 == 1) $result = true;
				break;
			case 'MD5':
				if(defined('CRYPT_MD5') && CRYPT_MD5 == 1 && PHP_VERSION != '5.3.7')
					$result = true;
				break;
			case 'UNCRYPT':
				$result = true;
				break;
		}
		
		if(!isset($result)) $result = false;
		
		return $result;
	}

	function getSystemChecksum($check_files) {
		global $modx;
		
		$check_files = trim($check_files);
		$check_files = explode("\n", $check_files);
		foreach($check_files as $file) {
			$file = trim($file);
			$file = MODX_BASE_PATH . $file;
			if(!is_file($file)) continue;
			$_[$file]= md5_file($file);
		}
		return serialize($_);
	}
	
	function setSystemChecksum($checksum) {
		global $modx;
		$tbl_system_settings = $modx->getFullTableName('system_settings');
		$sql = "REPLACE INTO {$tbl_system_settings} (setting_name, setting_value) VALUES ('sys_files_checksum','{$checksum}')";
        $modx->db->query($sql);
	}
	
	function checkSystemChecksum() {
		global $modx;

		if(!isset($modx->config['check_files_onlogin']) || empty($modx->config['check_files_onlogin'])) return '0';
		
		$current = $this->getSystemChecksum($modx->config['check_files_onlogin']);
		if(empty($current)) return;
		
		if(!isset($modx->config['sys_files_checksum']) || empty($modx->config['sys_files_checksum']))
		{
			$this->setSystemChecksum($current);
			return;
		}
		if($current===$modx->config['sys_files_checksum']) $result = '0';
		else                                               $result = 'modified';

		return $result;
	}
	
	function setView($action)
	{
		$actions = explode(',', '10,100,101,102,106,107,108,11,112,113,114,115,117,74,12,120,13,16,17,18,19,2,200,22,23,26,27,28,29,3,300,301,31,35,38,4,40,51,53,59,70,71,72,75,76,77,78,81,83,84,86,87,88,9,91,93,95,99,998,999');
		if(in_array($action,$actions))
		{
			if(isset($_SESSION['current_request_uri'])) $_SESSION['previous_request_uri'] = $_SESSION['current_request_uri'];
			$_SESSION['current_request_uri'] = $_SERVER['REQUEST_URI'];
		}
	}
    
    function ab($ph)
    {
    	global $modx;
    	
    	$tpl = '<li><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    	$ph['alt']     = isset($ph['alt']) ? $ph['alt'] : $ph['label'];
    	return $modx->parseText($tpl,$ph);
    }
    
	//Helper functions for categories
	//Kyle Jaebker - 08/07/06
	
	//Create a new category
	function newCategory($newCat)
	{
		global $modx;
		$field['category'] = $modx->db->escape($newCat);
		$newCatid = $modx->db->insert($field,'[+prefix+]categories');
		if(!$newCatid) $newCatid = 0;
		return $newCatid;
	}
	
		//check if new category already exists
	function checkCategory($newCat = '')
	{
		global $modx;
		$rs = $modx->db->select('id,category','[+prefix+]categories','','category');
		if($rs)
		{
			while($row = $modx->db->getRow($rs))
			{
				if ($row['category'] == $newCat)
				{
					return $row['id'];
				}
			}
		}
		return 0;
	}
	
	//Get all categories
	function getCategories()
	{
		global $modx;
		$cats = $modx->db->select('id, category', '[+prefix+]categories', '', 'category');
		$resourceArray = array();
		if($cats)
		{
			while($row = $modx->db->getRow($cats))
			{
				$resourceArray[] = array('id' => $row['id'], 'category' => stripslashes( $row['category'] )); // pixelchutes
			}
		}
		return $resourceArray;
	}

	//Delete category & associations
	function deleteCategory($catId=0)
	{
		global $modx;
		if ($catId)
		{
			$resetTables = array('site_plugins', 'site_snippets', 'site_htmlsnippets', 'site_templates', 'site_tmplvars', 'site_modules');
			foreach ($resetTables as $n=>$v)
			{
				$field['category'] = '0';
				$modx->db->update($field, "[+prefix+]{$v}", "category='{$catId}'");
			}
			$modx->db->delete('[+prefix+]categories',"id='{$catId}'");
		}
	}
	
	/**
	 *	System Alert Message Queue Display file
	 *	Written By Raymond Irving, April, 2005
	 *
	 *	Used to display system alert messages inside the browser
	 *
	 */

	function sysAlert($sysAlertMsgQueque='') {
		global $modx,$_lang;
		
		if(empty($sysAlertMsgQueque))
			$sysAlertMsgQueque = $modx->SystemAlertMsgQueque;
		if(empty($sysAlertMsgQueque)) return;
		if(!is_array($sysAlertMsgQueque)) $sysAlertMsgQueque = array($sysAlertMsgQueque);
		
		$alerts = array();
		foreach($sysAlertMsgQueque as $_) {
			$alerts[] = $_;
		}
		$sysMsgs = implode('<hr />',$alerts);
		
		// reset message queque
		unset($_SESSION['SystemAlertMsgQueque']);
		$_SESSION['SystemAlertMsgQueque'] = array();
		$sysAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];
	
		$tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/sysalert.tpl');
		$ph['alerts'] = $modx->db->escape($sysMsgs);
		$ph['title']  = $_lang['sys_alert'];
		return $modx->parseText($tpl,$ph);
	}
	
	function getMessageCount() {
		global $modx;
		
		if(!$modx->hasPermission('messages')) return;
		
		$uid = $modx->getLoginUserID();
		
		$rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}' and messageread=0");
		$new = $modx->db->getValue($rs);
		
		$rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}'");
		$total = $modx->db->getValue($rs);
		
		// ajax response
		if (isset($_POST['updateMsgCount'])) {
			echo "{$new},{$total}";
			exit();
		}
		else return array('new'=>$new,'total'=>$total);
	}
	
	// get user's document groups
	function getMgrDocgroups($uid=0) {
		global $modx;
		if(empty($uid)) $uid=$modx->getLoginUserID();
		$field ='uga.documentgroup as documentgroup';
		$from = '[+prefix+]member_groups ug INNER JOIN [+prefix+]membergroup_access uga ON uga.membergroup=ug.user_group';
		$rs = $modx->db->select($field,$from,"ug.member='{$uid}'");
		$documentgroup = array();
		if(0<$modx->db->getRecordCount($rs)) {
			while ($row = $modx->db->getRow($rs)) {
				$documentgroup[]=$row['documentgroup'];
			}
		}
		return $documentgroup;
	}
	
	function getMemberGroups($uid=0) {
		global $modx;
		if(empty($uid)) $uid = $modx->getLoginUserID();
		$field ='user_group,name';
		if(preg_match('@^[1-9][0-9]*$@',$uid))
		{
			$where = "ug.member='{$uid}'";
		}
		else $where = '';
		$from = '[+prefix+]member_groups ug INNER JOIN [+prefix+]membergroup_names ugnames ON ug.user_group=ugnames.id';
		$rs = $modx->db->select($field,$from,$where);
		$group = array();
		if(0<$modx->db->getRecordCount($rs)) {
			while ($row = $modx->db->getRow($rs)) {
				$group[$row['user_group']]=$row['name'];
			}
		}
		return $group;
	}
	/**
	 *	Secure Manager Documents
	 *	This script will mark manager documents as private
	 *
	 *	A document will be marked as private only if a manager user group 
	 *	is assigned to the document group that the document belongs to.
	 *
	 */
	function setMgrDocsAsPrivate($docid='') {
		global $modx;
		
		if($docid>0) $where = "id='{$docid}'";
		else         $where = 'privatemgr=1';
		$modx->db->update(array('privatemgr'=>0), '[+prefix+]site_content', $where);
		
		$field = 'sc.id';
		$from  = '[+prefix+]site_content sc'
				.' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
				.' LEFT JOIN [+prefix+]membergroup_access mga ON mga.documentgroup = dg.document_group';
		if($docid>0) $where = "sc.id='{$docid}' AND mga.id > 0";
		else         $where = 'mga.id > 0';
		$rs = $modx->db->select($field,$from,$where);
		$ids = $modx->db->getColumn('id',$rs);
		if(count($ids)>0) {
			$ids = implode(',', $ids);
			$modx->db->update(array('privatemgr'=>1),'[+prefix+]site_content', "id IN ({$ids})");
		}
		else $ids = '';
		return $ids;
	}
	
	/**
	 *	Secure Web Documents
	 *	This script will mark web documents as private
	 *
	 *	A document will be marked as private only if a web user group 
	 *	is assigned to the document group that the document belongs to.
	 *
	 */
	function setWebDocsAsPrivate($docid='') {
		global $modx;
		
		if($docid>0) $where = "id='{$docid}'";
		else         $where = 'privateweb=1';
		$modx->db->update(array('privateweb'=>0), '[+prefix+]site_content', $where);
		
		$field = 'DISTINCT sc.id';
		$from  = '[+prefix+]site_content sc'
				.' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
				.' LEFT JOIN [+prefix+]webgroup_access wga ON wga.documentgroup = dg.document_group';
		if($docid>0) $where = "sc.id='{$docid}' AND wga.id > 0";
		else         $where = 'wga.id > 0';
		$rs = $modx->db->select($field,$from,$where);
		$ids = $modx->db->getColumn('id',$rs);
		if(count($ids)>0) {
			$ids = implode(',', $ids);
			$modx->db->update(array('privateweb'=>1),'[+prefix+]site_content', "id IN ({$ids})");
		}
		else $ids = '';
		return $ids;
	}
	
	function getStylePath() {
		return MODX_MANAGER_PATH . 'media/style/';
	}
	
	function renderTabPane($ph) {
		global $modx;
		
		$style_path = $this->getStylePath();
		
		if(is_file("{$style_path}common/block_tabpane.tpl"))
			$tpl = file_get_contents("{$style_path}common/block_tabpane.tpl");
		else return;
		
		if(!isset($ph['id']))        $ph['id']        = 'tab'.uniqid('id');
		if(!isset($ph['tab-pages'])) $ph['tab-pages'] = 'content';
		elseif(is_array($ph['tab-pages'])) join("\n", $ph['tab-pages']);
		
		return $modx->parseText($tpl,$ph);
	}
	
	function renderTabPage($ph) {
		global $modx;
		
		$style_path = $this->getStylePath();
		
		if(is_file("{$style_path}common/block_tabpage.tpl"))
			$tpl = file_get_contents("{$style_path}common/block_tabpage.tpl");
		else $tpl = false;
		
		if(!$tpl) return;
		if(!isset($ph['id']))      $ph['id']      = 'id'.uniqid('id');
		if(!isset($ph['title']))   $ph['title']   = 'title';
		if(!isset($ph['content'])) $ph['content'] = 'content';
		return $modx->parseText($tpl,$ph);
	}
	
	function renderSection($ph) {
		global $modx;
		
		$style_path = $this->getStylePath();
		
		if(is_file("{$style_path}common/block_section.tpl"))
			$tpl = file_get_contents("{$style_path}common/block_section.tpl");
		else $tpl = false;
		
		if(!$tpl) return;
		if(!isset($ph['id']))      $ph['id']      = 'id'.uniqid('id');
		if(!isset($ph['title']))   $ph['title']   = 'title';
		if(!isset($ph['content'])) $ph['content'] = 'content';
		return $modx->parseText($tpl,$ph);
	}
	
	function renderTr($ph) {
		global $modx;
		
		$style_path = $this->getStylePath();
		
		if(is_file("{$style_path}common/block_tr.tpl"))
			$tpl = file_get_contents("{$style_path}common/block_tr.tpl");
		else $tpl = false;
		
		if(!$tpl) return;
		if(!isset($ph['id']))      $ph['id']      = 'id'.uniqid('id');
		if(!isset($ph['title']))   $ph['title']   = 'title';
		if(!isset($ph['content'])) $ph['content'] = 'content';
		return $modx->parseText($tpl,$ph);
	}
	
	function isAllowed($id)
	{
		global $modx;
		
		if(!$id)
		{
			if($_REQUEST['pid']) $id = $_REQUEST['pid'];
			else return true;
		}
		
		if(!isset($modx->config['allowed_parents']) || empty($modx->config['allowed_parents']))
			return true;
		
		if(!isset($modx->user_allowed_docs))
			$modx->user_allowed_docs = $this->getUserAllowedDocs();
		
		if(!in_array($id,$modx->user_allowed_docs))
			return false;
		else return true;
	}
	
	function isContainAllowed($id)
	{
		global $modx;
		if($this->isAllowed($id)) return true;
		
		$childlen = $modx->getChildIds($id);
		if(empty($childlen)) return false;
		
		$findflag = false;
		foreach($childlen as $child)
		{
			if(in_array($child,$modx->user_allowed_docs))
			{
				$findflag = true;
				break;
			}
		}
		return $findflag;
	}
	
	function getUserAllowedDocs()
	{
		global $modx;
		
		$modx->user_allowed_docs = array();
		$allowed_parents = trim($modx->config['allowed_parents']);
		$allowed_parents = preg_replace('@\s+@', ' ', $allowed_parents);
		$allowed_parents = str_replace(array(' ','|'), ',', $allowed_parents);
		$allowed_parents = explode(',', $allowed_parents);
		if(empty($allowed_parents)) return;
		
		foreach($allowed_parents as $parent)
		{
			$parent = trim($parent);
			$allowed_docs = $modx->getChildIds($parent);
			$allowed_docs[] = $parent;
			$modx->user_allowed_docs = array_merge($modx->user_allowed_docs,$allowed_docs);
		}
		return $modx->user_allowed_docs;
	}
	
	function getUploadMaxsize()
	{
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size       = ini_get('post_max_size');
		$memory_limit        = ini_get('memory_limit');
        if(version_compare($upload_max_filesize, $post_max_size,'<'))
        	$limit_size = $upload_max_filesize;
        else $limit_size = $post_max_size;
        
        if(version_compare($memory_limit, $limit_size,'<'))
        	$limit_size = $memory_limit;
    	return $limit_size;
	}
}
