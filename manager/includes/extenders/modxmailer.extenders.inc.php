<?php
/**
 * MODxMailer Extension config file.
 * User: tonatos
 * Date: 01.10.13
 * Time: 14:17
 */

include_once(MODX_BASE_PATH . 'manager/includes/extenders/modxmailer.class.inc.php');
$this->mail= new MODxMailer;
return ($this->mail)? true : false;