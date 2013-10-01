<?php
/**
 * DocAPI Extension config file
 * User: tonatos
 * Date: 01.10.13
 * Time: 14:21
 */

if(include_once(MODX_BASE_PATH . 'manager/includes/extenders/doc.api.class.inc.php'))
{
    $this->doc= new DocAPI;
    return true;
}
else return false;