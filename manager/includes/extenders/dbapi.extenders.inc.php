<?php
/**
 * DBAPI Extension config file
 * User: tonatos@gmail.com
 * Date: 01.10.13
 * Time: 13:32
 */

global $database_type;

if (!isset($database_type)||empty($database_type)) $database_type = 'mysql';
if (include_once(MODX_BASE_PATH . "manager/includes/extenders/dbapi.{$database_type}.class.inc.php"))
{
    $this->db= new DBAPI;
    $this->dbConfig= & $this->db->config; // alias for backward compatibility
    return true;
}
else return false;
