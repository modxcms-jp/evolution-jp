<?php
include_once(__DIR__.'/dbapi/mysqli.inc');
$this->db= new DBAPI;
$this->dbConfig= & $this->db->config; // alias for backward compatibility
