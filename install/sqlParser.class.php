<?php

// MySQL Dump Parser
// SNUFFKIN/ Alex 2004

class SqlParser {
    public $prefix, $mysqlErrors;
    public $installFailed = false;
    public $adminname;
    public $adminemail = 'example@example.com';
    public $adminpass;
    public $connection_charset = 'utf8';
    public $connection_collation = 'utf8_general_ci';
    public $managerlanguage = 'english';

    function __construct() {
    }

    function file_get_sql_contents($filename) {
        if(strpos($filename,'/')===false) {
            $path = MODX_BASE_PATH. 'install/sql/' . $filename;
        } else {
            $path = MODX_BASE_PATH.$filename;
        }
        if (!is_file($path)) {
            $this->mysqlErrors[] = array(
                'error' => sprintf("File '%s' not found", $path)
            );
            $this->installFailed = true ;
            return false;
        }
        return file_get_contents($path);
    }

    function intoDB($filename) {
        $idata = $this->file_get_sql_contents($filename);
        if(!$idata) {
            return false;
        }

        $dbVersion = (float) db()->getVersion();

        if(version_compare($dbVersion,'4.1.0', '>=')) {
            $char_collate = sprintf(
                'DEFAULT CHARSET=%s COLLATE %s'
                , $this->connection_charset
                , $this->connection_collation
            );
            $idata = str_replace('ENGINE=MyISAM', "ENGINE=MyISAM {$char_collate}", $idata);
        }

        // replace {} tags
        $ph = array(
            'PREFIX'          => $this->prefix,
            'ADMINNAME'       => $this->adminname,
            'ADMINPASS'       => md5($this->adminpass),
            'ADMINEMAIL'      => $this->adminemail,
            'ADMINFULLNAME'   => substr($this->adminemail,0,strpos($this->adminemail,'@')),
            'MANAGERLANGUAGE' => $this->managerlanguage,
            'DATE_NOW'        => time()
        );
        $idata = evo()->parseText($idata,$ph,'{','}',false);

        $sql_array = preg_split('@;[ \t]*\n@', $idata);

        foreach($sql_array as $i=>$sql) {
            $sql = trim($sql, "\r\n; ");
            if ($sql) {
                db()->query($sql, false);
            }
            $error_no = db()->getLastErrorNo();
            if(!$error_no) {
                continue;
            }
            if(!in_array($error_no, array(1060,1061,1091,1054,1064))) {
                $this->mysqlErrors[] = array(
                    'error' => db()->getLastError(),
                    'sql'   => $sql
                );
                $this->installFailed = true;
            }
        }
        return !$this->installFailed;
    }
}
