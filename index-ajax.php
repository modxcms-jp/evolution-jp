<?php
if(isset($_GET['q']) && $_GET['q']!=='')       $q = $_GET['q'];
elseif(isset($_POST['q']) && $_POST['q']!=='') $q = $_POST['q'];
else exit;

$q = realpath($q) or die();
$q = str_replace('\\','/',$q);

define('MODX_API_MODE', true);
include_once('index.php');

if(strpos($q, MODX_BASE_PATH . 'assets/snippets/')!==0) exit;
if(strtolower(substr($q,-4))!=='.php') exit;
include_once($q);
