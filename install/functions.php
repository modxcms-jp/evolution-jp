<?php
function install_session_start() {
	$_ = crc32(__FILE__);
	$_ = sprintf('%u', $_);
	$_ = base_convert($_,10,36);
	$site_sessionname = 'evo' . $_;
	session_name($site_sessionname);
	session_start();
}

function setOption($fieldName,$value='') {
	$_SESSION[$fieldName] = $value;
	return $value;
}

function getOption($fieldName) {
	if(isset($_POST[$fieldName]) &&    $_POST[$fieldName]!=='')        $rs = $_POST[$fieldName];
	elseif(isset($_SESSION[$fieldName]) && $_SESSION[$fieldName]!=='') $rs = $_SESSION[$fieldName];
	elseif(isset($GLOBALS[$fieldName])  && $GLOBALS[$fieldName]!=='')  $rs = $GLOBALS[$fieldName];
	else $rs = false;
	
	return $rs;
}

function autoDetectLang() {
	if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		return 'english';
	$lc = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	switch($lc)
	{
		case 'ja': $lang = 'japanese-utf8'; break;
		case 'ru': $lang = 'russian-utf8' ; break;
		default  : $lang = 'english'      ;
	}
	return $lang;
}

function includeLang($language, $dir='langs/') {
	global $_lang;
	
	# load language file
	$_lang = array ();
	$language = str_replace('\\','/',$language);
	if(strpos($language,'/')!==false) {
		 require_once('langs/english.inc.php');
	}
	elseif(is_file("{$dir}{$language}.inc.php")) {
		 require_once("{$dir}{$language}.inc.php");
	}
	else require_once("{$dir}english.inc.php");
}

function modx_escape($s) {
	if (function_exists('mysql_set_charset')) {
		$s = mysql_real_escape_string($s);
	}
	else {
		$s = mb_convert_encoding($s, 'eucjp-win', 'utf-8');
		$s = mysql_real_escape_string($s);
		$s = mb_convert_encoding($s, 'utf-8', 'eucjp-win');
	}
	return $s;
}

function compare_check($params) {
	
	$table_prefix = $_SESSION['table_prefix'];
	
	$name_field  = 'name';
	$name        = $params['name'];
	$mode        = 'version_compare';
	if($params['version']) {
		$new_version = $params['version'];
	}
	
	switch($params['category']) {
		case 'template':
			$table = "{$table_prefix}site_templates";
			$name_field = 'templatename';
			$mode       = 'desc_compare';
			break;
		case 'tv':
			$table = "{$table_prefix}site_tmplvars";
			$mode  = 'desc_compare';
			break;
		case 'chunk':
			$table = "{$table_prefix}site_htmlsnippets";
			$mode  = 'name_compare';
			break;
		case 'snippet':
			$table = "{$table_prefix}site_snippets";
			$mode  = 'version_compare';
			break;
		case 'plugin':
			$table = "{$table_prefix}site_plugins";
			$mode  = 'version_compare';
			break;
		case 'module':
			$table = "{$table_prefix}site_modules";
			$mode  = 'version_compare';
			break;
	}
	$sql = "SELECT * FROM `{$table}` WHERE `{$name_field}`='{$name}'";
	if($params['category']=='plugin') $sql .= " AND `disabled`='0'";
	$rs = mysql_query($sql);
	if(!$rs) echo "An error occurred while executing a query: ".mysql_error();
	else     
	{
		$row = mysql_fetch_assoc($rs);
		$count = mysql_num_rows($rs);
		if($count===1)
		{
			$new_version_str = ($new_version) ? '<strong>' . $new_version . '</strong> ':'';
			$new_desc    = $new_version_str . $params['description'];
			$old_desc    = $row['description'];
			$old_version = substr($old_desc,0,strpos($old_desc,'</strong>'));
			$old_version = strip_tags($old_version);
			if($mode == 'version_compare' && $old_version === $new_version)
			{
				                            $result = 'same';
			}
			elseif($mode == 'name_compare') $result = 'same';
			elseif($old_desc === $new_desc) $result = 'same';
			else                            $result = 'diff';
		}
		elseif($count < 1)                  $result = 'no exists';
	}
	
	return $result;
}

function parse_docblock($fullpath) {
	$params = array();
	if(!is_readable($fullpath)) return false;
	
	$tpl = @fopen($fullpath, 'r');
	if(!$tpl)                   return false;
	
	$docblock_start_found = false;
	$name_found           = false;
	$description_found    = false;
	
	while(!feof($tpl))
	{
		$line = fgets($tpl);
		if(!$docblock_start_found)
		{	// find docblock start
			if(strpos($line, '/**') !== false) $docblock_start_found = true;
			continue;
		}
		elseif(!$name_found)
		{	// find name
			$ma = null;
			if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
			{
				$params['name'] = trim($ma[1]);
				$name_found = !empty($params['name']);
			}
			continue;
		}
		elseif(!$description_found)
		{	// find description
			$ma = null;
			if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
			{
				$params['description'] = trim($ma[1]);
				$description_found = !empty($params['description']);
			}
			continue;
		}
		else
		{
			$ma = null;
			if(preg_match("/^\s+\*\s+\@([^\s]+)\s+(.+)/", $line, $ma))
			{
				$param = trim($ma[1]);
				$val   = trim($ma[2]);
				if(!empty($param) && !empty($val))
				{
					if($param == 'internal')
					{
						$ma = null;
						if(preg_match("/\@([^\s]+)\s+(.+)/", $val, $ma))
						{
							$param = trim($ma[1]);
							$val = trim($ma[2]);
						}
						if(empty($param)) continue;
					}
					$params[$param] = $val;
				}
			}
			elseif(preg_match("/^\s*\*\/\s*$/", $line))
			{
				break;
			}
		}
	}
	@fclose($tpl);
	return $params;
}

function clean_up($sqlParser) {
	$ids = array();
	$mysqlVerOk = -1;
	
	$table_prefix = $sqlParser->prefix;
	
	if(function_exists("mysql_get_server_info")) {
		$mysqlVerOk = (version_compare(mysql_get_server_info(),"4.0.20")>=0);
	}	
	
	// secure web documents - privateweb 
	mysql_query("UPDATE `{$table_prefix}site_content` SET privateweb = 0 WHERE privateweb = 1");
	$sql =  "SELECT DISTINCT sc.id 
			 FROM `{$table_prefix}site_content` sc
			 LEFT JOIN `{$table_prefix}document_groups` dg ON dg.document = sc.id
			 LEFT JOIN `{$table_prefix}webgroup_access` wga ON wga.documentgroup = dg.document_group
			 WHERE wga.id>0";
	$ds = mysql_query($sql);
	if(!$ds)
	{
		echo "An error occurred while executing a query: ".mysql_error();
	}
	else {
		while($r = mysql_fetch_assoc($ds)) $ids[]=$r["id"];
		if(count($ids)>0) {
			mysql_query("UPDATE `{$table_prefix}site_content` SET privateweb = 1 WHERE id IN (".implode(", ",$ids).")");	
			unset($ids);
		}
	}
	
	// secure manager documents privatemgr
	mysql_query("UPDATE `{$table_prefix}site_content` SET privatemgr = 0 WHERE privatemgr = 1");
	$sql =  "SELECT DISTINCT sc.id 
			 FROM `{$table_prefix}site_content` sc
			 LEFT JOIN `{$table_prefix}document_groups` dg ON dg.document = sc.id
			 LEFT JOIN `{$table_prefix}membergroup_access` mga ON mga.documentgroup = dg.document_group
			 WHERE mga.id>0";
	$ds = mysql_query($sql);
	if(!$ds)
	{
		echo "An error occurred while executing a query: ".mysql_error();
	}
	else
	{
		while($r = mysql_fetch_assoc($ds))
		{
			$ids[] = $r['id'];
		}
		
		if(count($ids)>0) {
			$ids = join(', ',$ids);
			mysql_query("UPDATE `{$table_prefix}site_content` SET privatemgr = 1 WHERE id IN ({$ids})");	
			unset($ids);
		}
	}
}

// Property Update function
function propUpdate($new,$old)
{
	// Split properties up into arrays
	$returnArr = array();
	$newArr = explode('&',$new);
	$oldArr = explode('&',$old);
	
	foreach ($newArr as $k => $v)
	{
		if(!empty($v))
		{
			$tempArr = explode('=',trim($v));
			$returnArr[$tempArr[0]] = $tempArr[1];
		}
	}
	foreach ($oldArr as $k => $v)
	{
		if(!empty($v))
		{
			$tempArr = explode('=',trim($v));
			$returnArr[$tempArr[0]] = $tempArr[1];
		}
	}
	
	// Make unique array
	$returnArr = array_unique($returnArr);
	
	// Build new string for new properties value
	foreach ($returnArr as $k => $v)
	{
		$return .= "&{$k}={$v} ";
	}
	return modx_escape($return);
}

function getCreateDbCategory($category) {
	global $modx;
	
    $category_id = 0;
    if(!empty($category)) {
        $category = $modx->db->escape($category);
        $dbv_category = $modx->db->getObject('categories', "category='{$category}'");
        if($dbv_category) $category_id = $dbv_category->id;
        else
        {
            $category_id = $modx->db->insert(array('category'=>$category), '[+prefix+]categories');
            if(!$category_id) exit('Get category id error');
        }
    }
    return $category_id;
}

function parse($src,$ph,$left='[+',$right='+]')
{
	foreach($ph as $k=>$v)
	{
		$k = $left . $k . $right;
		$src = str_replace($k,$v,$src);
	}
	return $src;
}

function is_webmatrix()
{
	return (isset($_SERVER['WEBMATRIXMODE'])) ? true : false;
}

function is_iis()
{
	return (strpos($_SERVER['SERVER_SOFTWARE'],'IIS')) ? true : false;
}

function get_installmode()
{
	global $base_path,$database_server, $database_user, $database_password, $dbase, $table_prefix;
	
	$conf_path = "{$base_path}manager/includes/config.inc.php";
	if (!is_file($conf_path)) $installmode = 0;
	elseif(isset($_POST['installmode'])) $installmode = $_POST['installmode'];
	else
	{
		include_once("{$base_path}manager/includes/config.inc.php");
		
		if(!isset($dbase) || empty($dbase)) $installmode = 0;
		else
		{
			$conn = @ mysql_connect($database_server, $database_user, $database_password);
			if($conn)
			{
				$_SESSION['database_server']   = $database_server;
				$_SESSION['database_user']     = $database_user;
				$_SESSION['database_password'] = $database_password;
				
				$dbase = trim($dbase, '`');
				$rs = @ mysql_select_db($dbase, $conn);
			}
			else $rs = false;
			
			if($rs)
			{
				$_SESSION['dbase']                      = $dbase;
				$_SESSION['table_prefix']               = $table_prefix;
				$_SESSION['database_collation']         = 'utf8_general_ci';
				$_SESSION['database_connection_method'] = 'SET CHARACTER SET';
				
				$tbl_system_settings = "`{$dbase}`.`{$table_prefix}system_settings`";
				$rs = mysql_query("SELECT setting_value FROM {$tbl_system_settings} WHERE setting_name='settings_version'");
				if($rs)
				{
					$row = mysql_fetch_assoc($rs);
					$settings_version = $row['setting_value'];
				}
				else $settings_version = '';
				
				if (empty($settings_version)) $installmode = 0;
				else                          $installmode = 1;
			}
			else $installmode = 1;
		}
	}
	return $installmode;
}

function getFullTableName($table_name)
{
	$dbase        = getOption('dbase');
	$table_prefix = getOption('table_prefix');
	return "`{$dbase}`.`{$table_prefix}{$table_name}`";
}

function parseProperties($propertyString)
{
	$parameter= array ();
	if (!empty($propertyString))
	{
		$tmpParams= explode('&', $propertyString);
		for ($x= 0; $x < count($tmpParams); $x++)
		{
			if (strpos($tmpParams[$x], '=', 0))
			{
				$pTmp= explode('=', $tmpParams[$x]);
				$pvTmp= explode(';', trim($pTmp[1]));
				if ($pvTmp[1] == 'list' && $pvTmp[3] != '')
				{
					$parameter[trim($pTmp[0])]= $pvTmp[3]; //list default
				}
				elseif ($pvTmp[1] != 'list' && $pvTmp[2] != '')
				{
					$parameter[trim($pTmp[0])]= $pvTmp[2];
				}
			}
		}
	}
	return $parameter;
}

function result($status='ok',$ph=array())
{
	$ph['status'] = $status;
	$ph['name']   = ($ph['name']) ? "&nbsp;&nbsp;{$ph['name']} : " : '';
	if(!isset($ph['msg'])) $ph['msg'] = '';
	$tpl = '<p>[+name+]<span class="[+status+]">[+msg+]</span></p>';
	return parse($tpl,$ph);
}

function get_langs()
{
	$langs = array();
	foreach(glob('langs/*.inc.php') as $path)
	{
		if(substr($path,6,1)==='.') continue;
		$langs[] = substr($path,6,strpos($path,'.inc.php')-6);
	}
	sort($langs);
	return $langs;
}

function get_lang_options($install_language)
{
	$langs = get_langs();
	
	foreach ($langs as $language)
	{
		$abrv_language = explode('-',$language);
		$option[] = '<option value="' . $language . '"'. (($language == $install_language) ? ' selected="selected"' : null) .'>' . ucwords($abrv_language[0]). '</option>';
	}
	return "\n" . join("\n",$option);
}

function genHash($password, $seed='1')
{
	$salt = md5($password . $seed);
	$password = sha1($salt.$password);
	$result = 'uncrypt>' . md5($salt.$password) . substr(md5($salt),0,8);
	
	return $result;
}

function collectTpls($path)
{
	$files1 = glob("{$path}*/*.install_base.tpl");
	$files2 = glob("{$path}*.install_base.tpl");
	$files = array_merge((array)$files1,(array)$files2);
	natcasesort($files);
	
	return $files;
}

function ph()
{
	global $_lang,$cmsName,$cmsVersion,$modx_textdir,$modx_release_date;

	if(isset($_SESSION['installmode'])) $installmode = $_SESSION['installmode'];
	else                                $installmode = get_installmode();

	$ph['pagetitle']     = $_lang['modx_install'];
	$ph['textdir']       = ($modx_textdir && $modx_textdir==='rtl') ? ' id="rtl"':'';
	$ph['help_link']     = $installmode == 0 ? $_lang['help_link_new'] : $_lang['help_link_upd'];
	$ph['version']       = $cmsName.' '.$cmsVersion;
	$ph['release_date']  = (($modx_textdir && $modx_textdir==='rtl') ? '&rlm;':'') . $modx_release_date;
	$ph['footer1']       = $_lang['modx_footer1'];
	$ph['footer2']       = $_lang['modx_footer2'];
	return $ph;
}

function install_sessionCheck()
{
	global $_lang;
	
	// session loop-back tester
	if(!isset($_GET['action']) || $_GET['action']!=='mode')
	{
		if(!isset($_SESSION['test']) || $_SESSION['test']!=1)
		{
			echo '
<html>
<head>
	<title>Install Problem</title>
	<style type="text/css">
		*{margin:0;padding:0}
		body{margin:150px;background:#eee;}
		.install{padding:10px;border:3px solid #ffc565;background:#ffddb4;margin:0 auto;text-align:center;}
		p{ margin:20px 0; }
		a{margin-top:30px;padding:5px;}
	</style>
</head>
<body>
	<div class="install">
		<p>' . $_lang["session_problem"] . '</p>
		<p><a href="./">' .$_lang["session_problem_try_again"] . '</a></p>
	</div>
</body>
</html>';
		exit;
		}
	}
}

function get_dbase() {
    global $dbase;
    
    if(isset($_SESSION['dbase']))
    	$dbase = $_SESSION['dbase'];
    elseif(!isset($dbase) || empty($dbase))
    	$dbase = '';
    
    $dbase = trim($dbase, '`');
    
    return $dbase;
}

function get_database_server() {
	global $database_server;
	
	if(isset($_SESSION['database_server']))
		$database_server = $_SESSION['database_server'];
	elseif(!isset($database_server) || empty($database_server))
		$database_server = 'localhost';

	return $database_server;
}

function get_database_user() {
	if(isset($_SESSION['database_user']))
		$database_user = $_SESSION['database_user'];
	elseif(!isset($database_user) || empty($database_user))
		$database_user = '';

	return $database_user;
}

function get_database_password() {
	global $database_password;
	
    if(isset($_SESSION['database_password']))
    	$database_password = $_SESSION['database_password'];
    elseif(!isset($database_password) || empty($database_password))
    	$database_password = '';

    return $database_password;
}

function get_table_prefix() {
	global $table_prefix;
	
	if(isset($_SESSION['table_prefix']))
		$table_prefix = $_SESSION['table_prefix'];
	elseif(!isset($table_prefix) || empty($table_prefix))
		$table_prefix = 'modx_';

	return $table_prefix;
}

function get_database_connection_method() {
	global $database_connection_method;
	
	if(isset($_SESSION['database_connection_method']))
		$database_connection_method = $_SESSION['database_connection_method'];
	elseif(!isset($database_connection_method) || empty($database_connection_method))
		$database_connection_method = 'SET CHARACTER SET';

	return $database_connection_method;
}

function get_database_collation() {
	global $database_collation;
	
	if(isset($_SESSION['database_collation']))
		$database_connection_method = $_SESSION['database_collation'];
	elseif(!isset($database_collation) || empty($database_collation))
		$database_collation = 'utf8_general_ci';

	return $database_collation;
}
