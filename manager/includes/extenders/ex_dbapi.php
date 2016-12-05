<?php
if(function_exists('mysqli_connect')) $fname = 'mysqli';
else                                  $fname = 'mysql';
include_once(dirname(__FILE__)."/dbapi/{$fname}.inc.php");
global $modx;
$modx = $this;
$this->db= new DBAPI;
$config_path='manager/includes/config.inc.php';

if(!is_file(MODX_BASE_PATH.$config_path)) {
    $this->gotoSetup();
    exit;
}
include(MODX_BASE_PATH.$config_path);

if (!isset($lastInstallTime) || empty($lastInstallTime)) {
    $this->gotoSetup();
    exit;
}

$this->db->hostname        = $database_server;
$this->db->username        = $database_user;
$this->db->password        = $database_password;
$this->db->dbname          = $dbase;
$this->db->charset         = $database_connection_charset;
$this->db->table_prefix    = $table_prefix;
$this->db->lastInstallTime = $lastInstallTime;

$this->db->connect();
$this->dbConfig= & $this->db->config; // alias for backward compatibility
