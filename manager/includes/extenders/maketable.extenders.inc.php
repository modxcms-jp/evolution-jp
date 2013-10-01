<?php
/**
 * MakeTable Extension config file.
 * User: tonatos
 * Date: 01.10.13
 * Time: 14:23
 */

if(include_once(MODX_BASE_PATH . 'manager/includes/extenders/maketable.class.php'))
{
    $this->table= new MakeTable;
    return true;
}
else return false;