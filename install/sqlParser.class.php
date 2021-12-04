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

    public function __construct() {
    }

    private function file_get_sql_contents($filename) {
        $path = strpos($filename,'/')===false
            ? MODX_BASE_PATH. 'install/sql/' . $filename
            : MODX_BASE_PATH.$filename;
        if (!is_file($path)) {
            $this->mysqlErrors[] = array(
                'error' => sprintf("File '%s' not found", $path)
            );
            $this->installFailed = true ;
            return false;
        }
        return file_get_contents($path);
    }

    public function intoDB($filename) {
        $idata = $this->file_get_sql_contents($filename);
        if(!$idata) {
            return false;
        }

        if(version_compare((float) db()->getVersion(), '5.0.0', '<')) {
            exit('DBのバージョンが古いためインストールできません。');
        }

        $sql_array = preg_split(
            '@;[ \t]*\n@',
            evo()->parseText(
                str_replace(
                    'ENGINE=MyISAM',
                    sprintf(
                        'ENGINE=MyISAM DEFAULT CHARSET=%s COLLATE %s'
                        , $this->connection_charset
                        , $this->connection_collation
                    ),
                    $idata
                ),
                array(
                    'PREFIX'          => $this->prefix,
                    'ADMINNAME'       => $this->adminname,
                    'ADMINPASS'       => md5($this->adminpass),
                    'ADMINEMAIL'      => $this->adminemail,
                    'ADMINFULLNAME'   => substr($this->adminemail,0,strpos($this->adminemail,'@')),
                    'MANAGERLANGUAGE' => $this->managerlanguage,
                    'DATE_NOW'        => time()
                ),
                '{',
                '}',
                false
            )
        );

        foreach($sql_array as $sql) {
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
