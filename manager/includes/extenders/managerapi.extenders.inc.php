<?php
/**
 * ManagerAPI Extension config file
 * User: tonatos@gmail.com
 * Date: 01.10.13
 * Time: 14:16
 */

if(include_once(MODX_BASE_PATH . 'manager/includes/extenders/manager.api.class.inc.php'))
{
    $this->manager= new ManagerAPI;
    return true;
}
else return false;