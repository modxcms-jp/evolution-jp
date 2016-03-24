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
		$this->prefix = 'modx_';
		$this->adminname = 'admin';
		$this->adminpass = 'password';
		$this->adminemail = 'example@example.com';
		$this->connection_charset = 'utf8';
		$this->connection_collation = 'utf8_general_ci';
		$this->showSqlErrors = true;
		$this->managerlanguage = 'english';
	}

	function intoDB($filename) {
	    global $mysqli;
		
		// check to make sure file exists
		$path = "{$this->base_path}install/sql/{$filename}";
		if (!is_file($path)) {
			$this->mysqlErrors[] = array("error" => "File '$path' not found");
			$this->installFailed = true ;
			return false;
		}
		
		$idata = file_get_contents($path);
		
		$idata = str_replace("\r", '', $idata);
		
		$dbVersion = $mysqli->server_info;
		$dbVersion = (float) $mysqli->server_info;
		if(version_compare($dbVersion,'4.1.0', '>='))
		{
			$char_collate = "DEFAULT CHARSET={$this->connection_charset} COLLATE {$this->connection_collation}";
			$idata = str_replace('ENGINE=MyISAM', "ENGINE=MyISAM {$char_collate}", $idata);
		}
		
		// replace {} tags
		$ph = array();
		$ph['PREFIX']            = $this->prefix;
		$ph['ADMINNAME']         = $this->adminname;
		$ph['ADMINFULLNAME']     = substr($this->adminemail,0,strpos($this->adminemail,'@'));
		$ph['ADMINEMAIL']        = $this->adminemail;
		$ph['ADMINPASS']         = genHash($this->adminpass, '1');
		$ph['MANAGERLANGUAGE']   = $this->managerlanguage;
		$ph['DATE_NOW']          = time();
		$idata = parse($idata,$ph,'{','}');
		
		$sql_array = preg_split('@;[ \t]*\n@', $idata);
		
		$num = 0;
		foreach($sql_array as $sql_entry)
		{
			$sql_do = trim($sql_entry, "\r\n; ");
			$num++;
			if ($sql_do) $mysqli->query($sql_do);
			if($mysqli->error)
			{
				// Ignore duplicate and drop errors - Raymond
				if (!$this->showSqlErrors)
				{
					$errno = $mysqli->errno;
					if ($errno == 1060 || $errno == 1061 || $errno == 1091 || $errno == 1054) continue;
				}
				// End Ignore duplicate
				$this->mysqlErrors[] = array("error" => $mysqli->error, "sql" => $sql_do);
				$this->installFailed = true;
			}
		}
	}
}
