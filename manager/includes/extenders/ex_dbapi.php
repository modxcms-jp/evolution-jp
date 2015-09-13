<?php
include_once('dbapi/mysql.inc');
$this->db= new DBAPI;
$this->dbConfig= & $this->db->config; // alias for backward compatibility
