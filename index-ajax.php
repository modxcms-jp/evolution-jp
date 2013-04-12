<?php
// Add items to this array corresponding to which directories within assets/snippets/ can be used by this file.
// Do not add entries unneccesarily.
// Any PHP files in these directories can be executed by any user.
$allowed_dirs = array('assets/snippets/ajaxSearch/');

if(isset($_GET['q']) && $_GET['q']!=='')       $q = $_GET['q'];
elseif(isset($_POST['q']) && $_POST['q']!=='') $q = $_POST['q'];
else exit;

$q = realpath($q) or die();
$q = str_replace('\\','/',$q);

define('MODX_API_MODE', true);
include_once('index.php');

if(strpos($q, MODX_BASE_PATH . 'assets/snippets/')!==0) exit;
if(strtolower(substr($q,-4))!=='.php') exit;

// permission check
$allowed = false;
foreach($allowed_dirs as $allowed_dir) {
    if (substr($axhandler_rel, 0, strlen($allowed_dir)) == $allowed_dir) {
        $allowed = true;
        break;
    }
}
if ($allowed) include_once($q);
