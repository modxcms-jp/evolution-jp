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
    public $connection_charset = 'utf8';
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
                    'ADMINNAME' => $this->adminname,
                    'ADMINPASS' => md5($this->adminpass),
                    'ADMINEMAIL' => $this->adminemail,
                    'ADMINFULLNAME' => ($atPos = strpos($this->adminemail, '@')) !== false
                        ? substr($this->adminemail, 0, $atPos)
                        : $this->adminemail,
                    'MANAGERLANGUAGE' => $this->managerlanguage,
                    'DATE_NOW' => time()
                ],
                '{',
                '}',
                false
            )
        );

        foreach ($sql_array as $sql) {
            $sql = trim($sql, "\r\n; ");
            if (!$sql) {
                continue;
            }
            db()->query($sql, false);
            $error_no = db()->getLastErrorNo();
            if (!$error_no) {
                continue;
            }
            if ($this->isIgnorableError($sql, $error_no)) {
                continue;
            }
            $this->mysqlErrors[] = [
                'error' => db()->getLastError(),
                'sql' => $sql
            ];
            $this->installFailed = true;
        }
        return !$this->installFailed;
    }

    // スキーマ変更のやり直しで起きるエラー（列・キーの重複や不在など）だけを無視する。
    // データ投入（INSERT/REPLACE など）の失敗は、同じエラー番号でも失敗として扱う
    private function isIgnorableError($sql, $error_no)
    {
        if (!in_array($error_no, [1060, 1061, 1091, 1054, 1064])) {
            return false;
        }
        $statement = ltrim(preg_replace('@^(\s*--[^\n]*\n)+@', '', $sql));
        return (bool)preg_match('@^(CREATE|ALTER|DROP)\b@i', $statement);
    }
}
