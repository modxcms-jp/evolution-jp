<?php
/**
 * EXPORT_SITE Extension config file
 * User: tonatos
 * Date: 01.10.13
 * Time: 14:24
 */

if(include_once(MODX_BASE_PATH . 'manager/includes/extenders/export.class.inc.php'))
{
    $this->export= new EXPORT_SITE;
    return true;
}
else return false;
