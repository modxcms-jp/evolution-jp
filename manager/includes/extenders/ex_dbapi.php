<?php
include_once(
sprintf(
    '%s/dbapi/%s.inc.php'
    , __DIR__
    , function_exists('mysqli_connect') ? 'mysqli' : 'mysql'
)
);
global $modx;
$modx = $this;
$this->db = new DBAPI;
$config_path = 'manager/includes/config.inc.php';

if (!is_file(MODX_BASE_PATH . $config_path)) {
    $rs = $this->gotoSetup();
} else {
    $rs = include(MODX_BASE_PATH . $config_path);
}

if (!isset($lastInstallTime) || !$lastInstallTime) {
    $rs = $this->gotoSetup();
}
if (!$rs) {
    return true;
}

$this->db->hostname = $database_server;
$this->db->username = $database_user;
$this->db->password = $database_password;
$this->db->dbname = $dbase;
$this->db->charset = $database_connection_charset;
$this->db->table_prefix = $table_prefix;

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
        $field, $op, db()->escape($value)
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

