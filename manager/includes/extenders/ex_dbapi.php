<?php
if(function_exists('mysqli_connect')) $fname = 'mysqli';
else                                  $fname = 'mysql';
include_once(dirname(__FILE__)."/dbapi/{$fname}.inc.php");
global $modx;
$modx = $this;
$this->db= new DBAPI;
$this->setConfig();
$this->db->connect();
$this->dbConfig= & $this->db->config; // alias for backward compatibility
