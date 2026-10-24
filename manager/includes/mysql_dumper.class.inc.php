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
if (!isset($modx) || !is_object($modx)) {
    exit;
}

class Mysqldumper
{
    public $_dbtables;
    public $_isDroptables;
    public $database_server;
    public $dbname;
    public $table_prefix;
    public $contentsOnly;
    public $mode;

    public function __construct()
    {
        if (db()->config['host'] === '127.0.0.1') {
            $this->database_server = 'localhost';
        } else {
            $this->database_server = db()->config['host'];
        }
        $this->dbname = trim(db()->dbname, '`');
        $this->table_prefix = db()->table_prefix;
        $this->mode = '';
        $this->addDropCommand(false);
        $this->_isDroptables = true;
        $this->_dbtables = [];
    }

    public function setDBtables($dbtables = false)
    {
        if (!$dbtables) {
            $this->_dbtables = $this->getTableNames();
            return;
        }
        $this->_dbtables = $dbtables;
    }

    public function addDropCommand($state)
    {
        $this->_isDroptables = $state;
    }

    private function isDroptables()
    {
        return $this->_isDroptables;
    }

    private function is_log_table($table_name)
    {
        if ($this->in_array($table_name, ['event_log', 'manager_log', 'system_cache'])) {
            return true;
        }
        return false;
    }

    private function is_content_table($table_name)
    {
        if (!$this->in_array(
            $table_name,
            [
                'site_content',
                'site_htmlsnippets',
                'site_templates',
                'system_settings',
                'site_tmplvars',
                'site_tmplvar_access',
                'site_tmplvar_contentvalues',
                'site_tmplvar_templates'
            ]
        )) {
            return false;
        }
        return true;
    }

    public function createDump()
    {
        $tempfile_path = tempnam(MODX_CACHE_PATH, 'bktemp');
        if ($tempfile_path === false) {
            return false;
        }
        if (!$this->dumpToFile($tempfile_path)) {
            if (is_file($tempfile_path)) {
                unlink($tempfile_path);
            }
            return false;
        }
        $output = file_get_contents($tempfile_path);
        unlink($tempfile_path);

        if (empty($output)) {
            return false;
        }
        return $output;
    }

    /**
     * 同じディレクトリの一時ファイルへ書き出し、書き終えてから $path へ rename する。
     * 失敗したときは $path に中途半端なファイルを残さない。
     */
    public function dumpToFile($path)
    {
        if (empty($this->database_server) || empty($this->dbname)) {
            return false;
        }

        if (!$this->_dbtables) {
            $this->_dbtables = $this->getTableNames();
            if (!$this->_dbtables) {
                return false;
            }
        }

        // Set line feed
        $lf = "\n";
        $tempfile_path = tempnam(dirname($path), 'dump');
        if ($tempfile_path === false) {
            return false;
        }
        // 時間切れや fatal で止まっても一時ファイルを残さない（rename 後は何もしない）
        register_shutdown_function(function () use ($tempfile_path) {
            if (is_file($tempfile_path)) {
                @unlink($tempfile_path);
            }
        });
        $fp = fopen($tempfile_path, 'wb');
        if ($fp === false) {
            unlink($tempfile_path);
            return false;
        }

        $result = db()->query('SHOW TABLES');
        $tables = $this->result2Array(0, $result);

        foreach ($tables as $table_name) {
            $result = db()->query("SHOW CREATE TABLE `{$table_name}`");
            $createtable[$table_name] = $this->result2Array(1, $result);
        }
        // Set header
        $header = [];
        $header[] = '-- ';
        $header[] = '--  ' . addslashes(evo()->config('site_name')) . ' Database Dump';
        $header[] = '--  MODX Version:' . evo()->config('settings_version');
        $header[] = '--  ';
        $header[] = '--  Host: ' . $this->database_server;
        $header[] = '--  Generation Time: ' . evo()->toDateFormat(time());
        $header[] = '--  Server version: ' . db()->getVersion();
        $header[] = '--  PHP Version: ' . phpversion();
        $header[] = '--  Database : `' . $this->dbname . '`';
        $header[] = '-- ';
        $ok = $this->write($fp, implode($lf, $header));

        $this->_dbtables = array_flip($this->_dbtables);
        foreach ($this->_dbtables as $k => $v) {
            $this->_dbtables[$k] = '1';
        }

        foreach ($tables as $table_name) {
            if (!$ok) {
                break;
            }
            if (!isset($this->_dbtables[$table_name])) {
                continue;
            }
            if (strpos($table_name, $this->table_prefix) !== 0) {
                continue;
            }
            if ($this->contentsOnly && !$this->is_content_table($table_name)) {
                continue;
            }

            $output = sprintf(
                '%s%s-- --------------------------------------------------------%s%s',
                $lf,
                $lf,
                $lf,
                $lf
            );
            $output .= sprintf('-- %s-- Table structure for table `%s`%s', $lf, $table_name, $lf);
            $output .= sprintf('-- %s%s', $lf, $lf);
            // Generate DROP TABLE statement when client wants it to.
            if ($this->isDroptables()) {
                $output .= 'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;' . $lf;
                $output .= sprintf(
                    'DROP TABLE IF EXISTS `%s`;',
                    $table_name
                ) . $lf;
                $output .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;{$lf}{$lf}";
            }
            $output .= $createtable[$table_name][0] . ';' . $lf;
            $output .= $lf;

            if ($this->mode === 'snapshot' && $this->is_log_table($table_name)) {
                continue;
            }

            $output .= sprintf(
                '-- %s-- Dumping data for table `%s`%s-- %s',
                $lf,
                $table_name,
                $lf,
                $lf
            );
            $ok = $this->write($fp, $output);

            $output = '';
            $result = db()->select('*', $table_name);
            while ($ok && ($row = db()->getRow($result))) {
                $insertdump = $lf;
                $insertdump .= "INSERT INTO `{$table_name}` VALUES (";
                if ($table_name === $this->table_prefix . 'system_settings') {
                    $row = $this->convertValues($row);
                }
                foreach ($row as $value) {
                    if ($value === null) {
                        $value = 'NULL';
                    } else {
                        $value = addslashes($value);
                        if (strpos($value, "\\'") !== false) {
                            $value = str_replace("\\'", "''", $value);
                        }
                        if (strpos($value, "\r\n") !== false) {
                            $value = str_replace("\r\n", "\n", $value);
                        }
                        if (strpos($value, "\r") !== false) {
                            $value = str_replace("\r", "\n", $value);
                        }
                        $value = str_replace("\n", '\\n', $value);
                        $value = "'{$value}'";
                    }
                    $insertdump .= $value . ',';
                }
                $output .= rtrim($insertdump, ',') . ");".$lf;
                if (1048576 < strlen($output)) {
                    $ok = $this->write($fp, $output);
                    $output = '';
                }
            }
            if ($ok) {
                $ok = $this->write($fp, $output);
            }
        }
        $ok = fclose($fp) && $ok;

        if (!$ok || filesize($tempfile_path) === 0 || !rename($tempfile_path, $path)) {
            unlink($tempfile_path);
            return false;
        }
        return true;
    }

    private function write($fp, $data)
    {
        return $data === '' || fwrite($fp, $data) === strlen($data);
    }

    private function in_array($table_name, $table_names)
    {
        foreach ($table_names as $name) {
            if ($table_name === $this->table_prefix . $name) {
                return true;
            }
        }
        return false;
    }

    private function convertValues($row)
    {
        switch ($row['setting_name']) {
            case 'filemanager_path':
            case 'rb_base_dir':
                if (strpos($row['setting_value'], MODX_BASE_PATH) === 0) {
                    $row['setting_value'] = str_replace(MODX_BASE_PATH, '[(base_path)]', $row['setting_value']);
                }
                break;
            case 'site_url':
                if ($row['setting_value'] === MODX_SITE_URL) {
                    $row['setting_value'] = '[(site_url)]';
                }
                break;
            case 'base_url':
                if ($row['setting_value'] === MODX_BASE_URL) {
                    $row['setting_value'] = '[(base_url)]';
                }
                break;
        }
        return $row;
    }

    private function object2Array($obj)
    {
        if (!is_object($obj)) {
            return null;
        }
        $array = [];
        foreach (get_object_vars($obj) as $key => $value) {
            if (is_object($value)) {
                $array[$key] = $this->object2Array($value);
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    // Private function result2Array.
    private function result2Array($numinarray, $resource)
    {
        $array = [];
        while ($row = db()->getRow($resource, 'num')) {
            $array[] = $row[$numinarray];
        }
        db()->freeResult($resource);
        return $array;
    }

    function getTableNames($dbname = '', $table_prefix = '')
    {
        if (!$table_prefix) {
            $table_prefix = $this->table_prefix;
        }
        $table_prefix = str_replace('_', '\\_', $table_prefix);
        if ($dbname === '') {
            $dbname = $this->dbname;
        }
        $rs = db()->query(
            sprintf(
                "SHOW TABLE STATUS FROM `%s` LIKE '%s%%'",
                $dbname,
                $table_prefix
            )
        );

        $tables = [];
        if (db()->count($rs)) {
            while ($row = db()->getRow($rs)) {
                $tables[] = $row['Name'];
            }
        }

        return $tables;
    }
}
