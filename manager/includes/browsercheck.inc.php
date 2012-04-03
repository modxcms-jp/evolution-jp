<?php 
require_once(dirname(__FILE__).'/protect.inc.php');
require_once(MODX_BASE_PATH.'manager/includes/sniff/phpSniff.class.php');

if(!isset($_GET['UA'])) $_GET['UA'] = ''; 
if(!isset($_GET['cc'])) $_GET['cc'] = ''; 
if(!isset($_GET['dl'])) $_GET['dl'] = ''; 
if(!isset($_GET['am'])) $_GET['am'] = '';

$sniffer_settings = array(	'check_cookies'=>$_GET['cc'],
							'default_language'=>$_GET['dl'],
							'allow_masquerading'=>$_GET['am']); 

$client = new phpSniff($_GET['UA'],$sniffer_settings);

$client->get_property('UA');
