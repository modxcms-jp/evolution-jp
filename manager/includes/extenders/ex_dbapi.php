<?php
include_once(dirname(__FILE__).'/dbapi/mysqli.inc');
$this->db= new DBAPI;
$this->dbConfig= & $this->db->config; // alias for backward compatibility
