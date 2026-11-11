<?php

// MySQL Dump Parser
// SNUFFKIN/ Alex 2004

class SqlParser
{
    public $prefix, $mysqlErrors;
    public $installFailed = false;
    public $adminname;
    public $adminemail = 'example@example.com';
    public $adminpass;
    public $connection_charset = 'utf8mb4';
    public $connection_collation = 'utf8mb4_general_ci';
    public $managerlanguage = 'english';

    public function __construct()
    {
    }

    private function file_get_sql_contents($filename)
    {
        $path = strpos($filename, '/') === false
            ? MODX_BASE_PATH . 'install/sql/' . $filename
            : MODX_BASE_PATH . $filename;
        if (!is_file($path)) {
            $this->mysqlErrors[] = [
                'error' => sprintf("File '%s' not found", $path)
            ];
            $this->installFailed = true;
            return false;
        }
        return file_get_contents($path);
    }

    public function intoDB($filename)
    {
        $idata = $this->file_get_sql_contents($filename);
        if (!$idata) {
            return false;
        }

        if (version_compare((float)db()->getVersion(), '5.0.0', '<')) {
            exit('DBのバージョンが古いためインストールできません。');
        }

        $tableOption = vsprintf(
            'CHARSET=%s COLLATE %s',
            [$this->connection_charset, $this->connection_collation]
        );
        $sql_array = preg_split(
            '@;[ \t]*\n@',
            evo()->parseText(
                str_replace('{TABLE_OPTION}', $tableOption, $idata),
                [
                    'PREFIX' => $this->prefix,
                    'ADMINNAME' => db()->escape($this->adminname),
                    'ADMINPASS' => md5($this->adminpass),
                    'ADMINEMAIL' => db()->escape($this->adminemail),
                    'ADMINFULLNAME' => db()->escape(
                        ($atPos = strpos($this->adminemail, '@')) !== false
                            ? substr($this->adminemail, 0, $atPos)
                            : $this->adminemail
                    ),
                    'MANAGERLANGUAGE' => db()->escape($this->managerlanguage),
                    'DATE_NOW' => time()
                ],
                '{',
                '}',
                false
            )
        );

        $innoDbFulltext = self::supportsInnoDbFulltext((string)db()->getVersion());

        foreach ($sql_array as $sql) {
            $sql = trim($sql, "\r\n; ");
            if (!$sql) {
                continue;
            }
            if (!$innoDbFulltext) {
                $sql = self::withMyIsamForFulltext($sql);
            }
            db()->query($sql, false);
            $error_no = db()->getLastErrorNo();
            if (!$error_no) {
                continue;
            }
            // 同梱 SQL は IF NOT EXISTS / IF EXISTS でやり直しに耐えるため、エラーはすべて失敗として扱う
            $this->mysqlErrors[] = [
                'error' => db()->getLastError(),
                'sql' => $sql
            ];
            $this->installFailed = true;
        }
        return !$this->installFailed;
    }

    /**
     * InnoDB の表に FULLTEXT 索引を作れる DB か（MySQL 5.6.4 以降・MariaDB 10.0.5 以降）。
     *
     * mysqli の server_info は、MariaDB を "5.5.5-10.4.32-MariaDB" の形で返すことがあるので、
     * そのときは後ろの版で判定する。
     */
    public static function supportsInnoDbFulltext($serverInfo)
    {
        $serverInfo = (string)$serverInfo;
        if (preg_match('/^5\.5\.5-(\d+\.\d+\.\d+)/', $serverInfo, $m)) {
            $serverInfo = $m[1] . '-MariaDB';
        }
        if (!preg_match('/^(\d+\.\d+\.\d+)/', $serverInfo, $m)) {
            // 版が読めないときは、今の DB とみなして何も変えない
            return true;
        }
        $required = stripos($serverInfo, 'mariadb') !== false ? '10.0.5' : '5.6.4';
        return version_compare($m[1], $required, '>=');
    }

    /**
     * FULLTEXT 索引を含み、ストレージエンジンの指定が無い CREATE TABLE に ENGINE=MyISAM を付ける。
     *
     * InnoDB の FULLTEXT に対応していない DB（MySQL 5.5 など）では、既定の InnoDB でこの表を作れない。
     * IF NOT EXISTS でも定義を先に検査するため、表がすでにあってもエラーになる。
     * 表の定義の閉じ括弧のあとの CHARSET= の前に入れる（{TABLE_OPTION} は "CHARSET=… COLLATE …" に置き換え済み）。
     */
    public static function withMyIsamForFulltext($sql)
    {
        if (!preg_match('/^\s*CREATE\s+TABLE\b/i', $sql)
            || stripos($sql, 'FULLTEXT') === false
            || preg_match('/\bENGINE\s*=/i', $sql)) {
            return $sql;
        }
        return preg_replace('/\)\s*(CHARSET\s*=)/i', ') ENGINE=MyISAM $1', $sql, 1);
    }
}
