<?php

// MySQL Dump Parser
// SNUFFKIN/ Alex 2004

class SqlParser {
	var $prefix, $mysqlErrors;
	var $installFailed, $adminname, $adminemail, $adminpass, $managerlanguage;
    var $connection_charset, $connection_collation, $showSqlErrors;
    var $base_path;

	function __construct() {
		$this->base_path = str_replace('\\','/', dirname(getcwd())).'/';
		$this->prefix               = 'modx_';
		$this->adminname            = 'admin';
		$this->adminpass            = 'password';
		$this->adminemail           = 'example@example.com';
		$this->connection_charset   = 'utf8';
		$this->connection_collation = 'utf8_general_ci';
		$this->showSqlErrors        = true;
		$this->managerlanguage      = 'english';
	}
	
	function file_get_sql_contents($filename) {
		// check to make sure file exists
		if(strpos($filename,'/')===false) $path = "{$this->base_path}install/sql/{$filename}";
		else                              $path = "{$this->base_path}{$filename}";
		if (!is_file($path)) {
			$this->mysqlErrors[] = array("error" => "File '{$path}' not found");
			$this->installFailed = true ;
			return false;
		}
		return file_get_contents($path);
	}
	
	function intoDB($filename) {
	    global $modx;
		
		$idata = $this->file_get_sql_contents($filename);
		if(!$idata) return false;
		
		$dbVersion = (float) $modx->db->getVersion();
		
		if(version_compare($dbVersion,'4.1.0', '>='))
		{
			$char_collate = "DEFAULT CHARSET={$this->connection_charset} COLLATE {$this->connection_collation}";
			$idata = str_replace('ENGINE=MyISAM', "ENGINE=MyISAM {$char_collate}", $idata);
		}
		
		// replace {} tags
		$ph = array();
		$ph['PREFIX']            = $this->prefix;
		$ph['ADMINNAME']         = $this->adminname;
		$ph['ADMINPASS']         = md5($this->adminpass);
		$ph['ADMINEMAIL']        = $this->adminemail;
		$ph['ADMINFULLNAME']     = substr($this->adminemail,0,strpos($this->adminemail,'@'));
		$ph['MANAGERLANGUAGE']   = $this->managerlanguage;
		$ph['DATE_NOW']          = time();
		$idata = $modx->parseText($idata,$ph,'{','}',false);
		
		$sql_array = preg_split('@;[ \t]*\n@', $idata);
		
		foreach($sql_array as $i=>$sql)
		{
			$sql = trim($sql, "\r\n; ");
			if ($sql) $modx->db->query($sql,false);
			$error_no = $modx->db->getLastErrorNo();
			if(!$error_no) continue;
			switch($error_no) {
				case 1060:
				case 1061:
				case 1091:
				case 1054:
				case 1064:
					continue;break;
				default:
    				$this->mysqlErrors[] = array("error" => $modx->db->getLastError(), "sql" => $sql);
    				$this->installFailed = true;
			}
		}
	}
}
