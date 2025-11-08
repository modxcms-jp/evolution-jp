<?php
include_once __DIR__ . '/dbapi/mysqli.inc.php';

global $modx;
$modx = $this;
$config_path = 'manager/includes/config.inc.php';

if (!is_file(MODX_BASE_PATH . $config_path)) {
    $rs = $this->gotoSetup();
} else {
    $rs = include(MODX_BASE_PATH . $config_path);
}

if (!isset($lastInstallTime) || !$lastInstallTime) {
    $rs = $this->gotoSetup();
}

// Initialize DBAPI even in installation mode
// In installation mode, connection parameters will be set later via db()->prop() and db()->connect()
if (!$rs) {
    // Installation mode: initialize with empty values
    $this->db = new DBAPI('', '', '', '');
    return true;
}

// Normal mode: initialize with configuration parameters
$this->db = new DBAPI(
    $database_server,
    $dbase,
    $database_user,
    $database_password,
    $table_prefix ?? '',
    $database_connection_charset ?? 'utf8mb4',
    $database_connection_method ?? 'SET CHARACTER SET'
);

$rs = $this->db->connect();
if (!$rs) {
    exit('Cannot access db');
}
// alias for backward compatibility
$this->dbConfig = &$this->db->config;

function where($field, $op, $value = null)
{
    if ($value === null) {
        $value = $op;
        $op = '=';
    }
    return sprintf(
        strpos($field, '`') === false ? '`%s` %s "%s"' : '%s %s "%s"',
        $field, $op, $value
    );
}

function and_where($field, $op, $value = null)
{
    return 'AND ' . where($field, $op, $value);
}

function where_in($field, $values = [])
{
    if (!$values) {
        return null;
    }
    foreach ($values as $i => $v) {
        $values[$i] = "'" . db()->escape($v) . "'";
    }
    return sprintf(
        strpos($field, '`') === false ? '`%s` IN (%s)' : '%s IN (%s)',
        $field,
        implode(',', $values)
    );
}

function and_where_in($field, $values = [])
{
    if (!$values) {
        return null;
    }
    return 'AND ' . where_in($field, $values);
}

