<?php
include_once(MODX_CORE_PATH . 'extenders/dbapi/mysql.inc');
$this->db= new DBAPI;
$this->dbConfig= & $this->db->config; // alias for backward compatibility
