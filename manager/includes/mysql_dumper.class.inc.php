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
        if ($this->in_array($table_name, ['event_log', 'manager_log'])) {
            return true;
        }
        return false;
    }

    private function is_content_table($table_name)
    {
        if (!$this->in_array(
            $table_name
            , [
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
        $tempfile_path = MODX_BASE_PATH . 'assets/cache/bktemp.pageCache.php';
        if (is_file($tempfile_path)) {
            unlink($tempfile_path);
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
        file_put_contents(
            $tempfile_path
            , implode($lf, $header)
            , FILE_APPEND | LOCK_EX
        );

        $this->_dbtables = array_flip($this->_dbtables);
        foreach ($this->_dbtables as $k => $v) {
            $this->_dbtables[$k] = '1';
        }

        foreach ($tables as $table_name) {
            if (!isset($this->_dbtables[$table_name])) {
                continue;
            }
            if (strpos($table_name, $this->table_prefix) !== 0) {
                continue;
            }
            if ($this->mode === 'snapshot' && $this->is_log_table($table_name)) {
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
                        'DROP TABLE IF EXISTS `%s`;'
                        , $table_name
                    ) . $lf;
                $output .= "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;{$lf}{$lf}";
            }
            $output .= $createtable[$table_name][0] . ';' . $lf;
            $output .= $lf;
            $output .= sprintf(
                '-- %s-- Dumping data for table `%s`%s-- %s'
                , $lf
                , $table_name
                , $lf
                , $lf
            );
            file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);

            $output = '';
            $result = db()->select('*', $table_name);
            while ($row = db()->getRow($result)) {
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
                    file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
                    $output = '';
                }
            }
            file_put_contents($tempfile_path, $output, FILE_APPEND | LOCK_EX);
        }
        $output = file_get_contents($tempfile_path);

        if (empty($output)) {
            return false;
        }

        unlink($tempfile_path);
        return $output;
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
    private function result2Array($numinarray = 0, $resource)
    {
        $array = [];
        while ($row = db()->getRow($resource, 'num')) {
            $array[] = $row[$numinarray];
        }
        db()->freeResult($resource);
        return $array;
    }

    public function dumpSql(&$dumpstring)
    {
        if (!headers_sent()) {
            header('Expires: 0');
            header('Cache-Control: private');
            header('Pragma: cache');
            header('Content-type: application/download');
            header("Content-Length: " . strlen($dumpstring));
            header(
                sprintf(
                    'Content-Disposition: attachment; filename=%s-%s_database_backup.sql'
                    , strtolower(
                        str_replace(
                            '/',
                            '-',
                            evo()->toDateFormat(
                                time(),
                                'dateOnly'
                            )
                        )
                    )
                    , globalv('settings_version')
                )
            );
        }
        echo $dumpstring;
        return true;
    }

    function snapshot($path, &$dumpstring)
    {
        $rs = file_put_contents($path, $dumpstring);
        if ($rs) {
            @chmod($path, 0666);
        }
        return $rs;
    }

    function import_sql($source)
    {
        if (strpos($source, "\r") !== false) {
            $source = str_replace(["\r\n", "\r"], "\n", $source);
        }
        $sql_array = preg_split('@;[ \t]*\n@', $source);
        foreach ($sql_array as $sql_entry) {
            $sql_entry = trim($sql_entry);
            if (empty($sql_entry)) {
                continue;
            }
            $rs = db()->query($sql_entry);
        }
        $settings = $this->getSettings();
        $this->restoreSettings($settings);

        evo()->clearCache();
        if (db()->count($rs)) {
            while ($row = db()->getRow($rs)) {
                $_SESSION['last_result'][] = $row;
            }
        }
        $_SESSION['result_msg'] = 'import_ok';
    }


    function getSettings()
    {
        $rs = db()->select('setting_name, setting_value', '[+prefix+]system_settings');
        $settings = [];
        while ($row = db()->getRow($rs)) {
            $name = $row['setting_name'];
            $value = $row['setting_value'];
            switch ($name) {
                case 'rb_base_dir':
                case 'filemanager_path':
                    if (strpos($value, '[(base_path)]') !== false) {
                        $settings[$name] = str_replace(
                            '[(base_path)]'
                            , MODX_BASE_PATH
                            , $value);
                    }
                    break;
                case 'site_url':
                    if ($value === '[(site_url)]') {
                        $settings['site_url'] = MODX_SITE_URL;
                    }
                    break;
                case 'base_url':
                    if ($value === '[(base_url)]') {
                        $settings['base_url'] = MODX_BASE_URL;
                    }
                    break;
            }
        }
        return $settings;
    }

    function restoreSettings($settings)
    {
        foreach ($settings as $k => $v) {
            db()->update(
                ['setting_value' => $v]
                , '[+prefix+]system_settings'
                , "setting_name='{$k}'"
            );
        }
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
                "SHOW TABLE STATUS FROM `%s` LIKE '%s%%'"
                , $dbname
                , $table_prefix
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
