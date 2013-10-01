<?php
/**
 * PHx Extension config file.
 * User: tonatos
 * Date: 01.10.13
 * Time: 14:22
 */

if(!class_exists('PHx') || !is_object($this->phx))
{
    $rs = include_once(MODX_BASE_PATH . 'manager/includes/extenders/phx.parser.class.inc.php');
    if($rs)
    {
        $this->phx= new PHx;
        return true;
    }
    else return false;
}
else return true;