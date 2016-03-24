<?php
/*
* @package  MySQLdumper
* @version  1.0
* @author   Dennis Mozes <opensource@mosix.nl>
* @url		http://www.mosix.nl/mysqldumper
* @since    PHP 4.0
* @copyright Dennis Mozes
* @license GNU/LGPL License: http://www.gnu.org/copyleft/lgpl.html
*
* Modified by Raymond for use with this module
*
**/
if(!isset($modx) || !is_object($modx)) exit;

class Mysqldumper {
	var $_dbtables;
	var $_isDroptables;
	var $database_server;
	var $dbname;
	var $table_prefix;
	var $contentsOnly;

	function __construct() {
		global $modx;
		// Don't drop tables by default.
		$this->database_server = $modx->db->config['host']==='127.0.0.1' ? 'localhost' : $modx->db->config['host'];
		$this->dbname          = trim($modx->db->config['dbase'],'`');
		$this->table_prefix    = $modx->db->config['table_prefix'];
		$this->mode            = '';
		$this->addDropCommand(false);
		$this->_isDroptables = true;
		$this->_dbtables = false;
	}

	function setDBtables($dbtables=false) {
		if($dbtables===false) $this->_dbtables = $this->getTableNames();
		else                  $this->_dbtables = $dbtables;
	}

	// If set to true, it will generate 'DROP TABLE IF EXISTS'-statements for each table.
	function addDropCommand($state) { $this->_isDroptables = $state; }
	function isDroptables()        { return $this->_isDroptables; }

	function createDump() {
		global $modx;

		if(empty($this->database_server) || empty($this->dbname)) return false;
		if($this->_dbtables===false) $this->setDBtables();

		$table_prefix = $this->table_prefix;
		// Set line feed
		$lf = "\n";
		$tempfile_path = $modx->config['base_path'] . 'assets/cache/bktemp.pageCache.php';

		$result = $modx->db->query('SHOW TABLES');
		$tables = $this->result2Array(0, $result);
		
		foreach ($tables as $table_name) {
			$result = $modx->db->query("SHOW CREATE TABLE `{$table_name}`");
			$createtable[$table_name] = $this->result2Array(1, $result);
		}
		// Set header
		$output  = "-- {$lf}";
		$output .= "--  ".addslashes($modx->config['site_name'])." Database Dump{$lf}";
		$output .= "--  MODX Version:{$modx->config['settings_version']}{$lf}";
		$output .= "--  {$lf}";
		$output .= "--  Host: {$this->database_server}{$lf}";
		$output .= "--  Generation Time: " . $modx->toDateFormat(time()) . $lf;
		$output .= "--  Server version: ". $modx->db->getVersion() . $lf;
		$output .= "--  PHP Version: " . phpversion() . $lf;
		$output .= "--  Database : `{$this->dbname}`{$lf}";
		$output .= "-- ";
		file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
		$output = '';

		// Generate dumptext for the tables.
		if (isset($this->_dbtables) && !empty($this->_dbtables)) {
			$this->_dbtables=array_flip($this->_dbtables);
			foreach($this->_dbtables as $k=>$v) {
				$this->_dbtables[$k] = '1';
			}
		}
		else return false;
		
		foreach ($tables as $table_name) {
			// check for selected table
			if(!isset($this->_dbtables[$table_name])) continue;
			if(!preg_match("@^{$table_prefix}@", $table_name)) continue;
			if($this->mode==='snapshot')
			{
				switch($table_name)
				{
					case "{$table_prefix}event_log":
					case "{$table_prefix}manager_log":
						continue 2;
				}
			}
			
			if($this->contentsOnly)
			{
				switch($table_name)
				{
					case "{$table_prefix}site_content":
					case "{$table_prefix}site_htmlsnippets":
					case "{$table_prefix}site_templates":
					case "{$table_prefix}system_settings":
					case "{$table_prefix}site_tmplvars":
					case "{$table_prefix}site_tmplvar_access":
					case "{$table_prefix}site_tmplvar_contentvalues":
					case "{$table_prefix}site_tmplvar_templates":
						break;
					default:
						continue 2;
				}
			}
			
			$output .= "{$lf}{$lf}-- --------------------------------------------------------{$lf}{$lf}";
			$output .= "-- {$lf}-- Table structure for table `{$table_name}`{$lf}";
			$output .= "-- {$lf}{$lf}";
			// Generate DROP TABLE statement when client wants it to.
			if($this->isDroptables()) {
				$output .= "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;{$lf}";
				$output .= "DROP TABLE IF EXISTS `{$table_name}`;{$lf}";
				$output .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;{$lf}{$lf}";
			}
			$output .= "{$createtable[$table_name][0]};{$lf}";
			$output .= $lf;
			$output .= "-- {$lf}-- Dumping data for table `{$table_name}`{$lf}-- {$lf}";
			$result = $modx->db->select('*',$table_name);
			while($row = $modx->db->getRow($result)) {
				$insertdump = $lf;
				$insertdump .= "INSERT INTO `{$table_name}` VALUES (";
				if($table_name==="{$table_prefix}system_settings") $row = $this->convertValues($row);
				foreach($row as $value) {
					$value = addslashes($value);
					if(strpos($value,"\\'")!==false)  $value = str_replace("\\'","''",$value);
					if(strpos($value,"\r\n")!==false) $value = str_replace("\r\n", "\n", $value);
					if(strpos($value,"\r")!==false)   $value = str_replace("\r", "\n", $value);
					$value = str_replace("\n", '\\n', $value);
					$insertdump .= "'{$value}',";
				}
				$output .= rtrim($insertdump,',') . ");";
				if(1048576 < strlen($output))
				{
					file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
					$output = '';
				}
			}
			file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
			$output = '';
		}
		$output = file_get_contents($tempfile_path);
		if(empty($output)) return false;
		else unlink($tempfile_path);
		return $output;
	}
	
	function convertValues($row)
	{
		switch($row['setting_name'])
		{
			case 'filemanager_path':
			case 'rb_base_dir':
			if(strpos($row['setting_value'],MODX_BASE_PATH)!==false)
				$row['setting_value'] = str_replace(MODX_BASE_PATH,'[(base_path)]',$row['setting_value']);
    			break;
			case 'site_url':
			if($row['setting_value']===MODX_SITE_URL)
				$row['setting_value'] = '[(site_url)]';
    			break;
			case 'base_url':
			if($row['setting_value']===MODX_BASE_URL)
				$row['setting_value'] = '[(base_url)]';
    			break;
		}
		return $row;
	}
	
	// Private function object2Array.
	function object2Array($obj) {
		$array = null;
		if(is_object($obj)) {
			$array = array();
			foreach (get_object_vars($obj) as $key => $value) {
				if (is_object($value))
				        $array[$key] = $this->object2Array($value);
				else    $array[$key] = $value;
			}
		}
		return $array;
	}

	// Private function result2Array.
	function result2Array($numinarray = 0, $resource) {
		global $modx;
		$array = array();
		while ($row = $modx->db->getRow($resource,'num')) {
			$array[] = $row[$numinarray];
		}
		$modx->db->freeResult($resource);
		return $array;
	}
	
    function dumpSql(&$dumpstring) {
    	global $modx,$modx_version;
    	$today = $modx->toDateFormat(time(),'dateOnly');
    	$today = str_replace('/', '-', $today);
    	$today = strtolower($today);
    	$size = strlen($dumpstring);
    	if(!headers_sent()) {
    	    header('Expires: 0');
            header('Cache-Control: private');
            header('Pragma: cache');
    		header('Content-type: application/download');
    		header("Content-Length: {$size}");
    		header("Content-Disposition: attachment; filename={$today}-{$modx_version}_database_backup.sql");
    	}
    	echo $dumpstring;
    	return true;
    }
    
    function snapshot($path,&$dumpstring) {
    	return @file_put_contents($path,$dumpstring);
    }
    
    function import_sql($source)
    {
    	global $modx,$e;
    	
    	if(strpos($source, "\r")!==false) $source = str_replace(array("\r\n","\r"),"\n",$source);
    	$sql_array = preg_split('@;[ \t]*\n@', $source);
    	foreach($sql_array as $sql_entry)
    	{
    		if(empty($sql_entry)) continue;
    		$rs = $modx->db->query($sql_entry);
    	}
    	$settings = $this->getSettings();
    	$this->restoreSettings($settings);
    	
    	$modx->clearCache();
    	if(0 < $modx->db->getRecordCount($rs))
    	{
    		while($row = $modx->db->getRow($rs))
    		{
    			$_SESSION['last_result'][] = $row;
    		}
    	}
    	$_SESSION['result_msg'] = 'import_ok';
    }
    
    
    function getSettings()
    {
    	global $modx;
    	
    	$rs = $modx->db->select('setting_name, setting_value','[+prefix+]system_settings');
    	
    	$settings = array();
    	while ($row = $modx->db->getRow($rs))
    	{
    		$name  = $row['setting_name'];
    		$value = $row['setting_value'];
    		switch($name)
    		{
    			case 'rb_base_dir':
    			case 'filemanager_path':
    				if(strpos($value,'[(base_path)]')!==false)
    					$settings[$name] = str_replace('[(base_path)]',MODX_BASE_PATH,$value);
    				break;
    			case 'site_url':
    				if($value==='[(site_url)]')
    					$settings['site_url'] = MODX_SITE_URL;
    				break;
    			case 'base_url':
    				if($value==='[(base_url)]')
    					$settings['base_url'] = MODX_BASE_URL;
    				break;
    		}
    	}
    	return $settings;
    }
    
    function restoreSettings($settings)
    {
    	global $modx;
    	
    	foreach($settings as $k=>$v)
    	{
    		$modx->db->update(array('setting_value'=>$v),'[+prefix+]system_settings',"setting_name='{$k}'");
    	}
    }
    function getTableNames($dbname='',$table_prefix='') {
    	global $modx;
    	
		if($table_prefix==='') $table_prefix = $this->table_prefix;
		$table_prefix = str_replace('_', '\\_', $table_prefix);
		if($dbname==='') $dbname = $this->dbname;
		$sql = "SHOW TABLE STATUS FROM `{$dbname}` LIKE '{$table_prefix}%'";
		$rs = $modx->db->query($sql);
		
		$tables = array();
		if(0<$modx->db->getRecordCount($rs)) {
			while($row = $modx->db->getRow($rs))
			{
				$tables[] = $row['Name'];
			}
		}
		
		return $tables;
    }
}
