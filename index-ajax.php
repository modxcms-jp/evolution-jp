<?php
// Add items to this array corresponding to which directories within assets/snippets/ can be used by this file.
// Do not add entries unneccesarily.
// Any PHP files in these directories can be executed by any user.
$allowed_dirs = array('assets/snippets/ajaxSearch/');

// set some settings, and address some IE issues
@ini_set('url_rewriter.tags', '');
@ini_set('session.use_trans_sid', 0);
@ini_set('session.use_only_cookies',1);
session_cache_limiter('');
header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"'); // header for weird cookie stuff. Blame IE.
header('Cache-Control: private, must-revalidate');

if(isset($_GET['q']) && $_GET['q']!=='')       $q = $_GET['q'];
elseif(isset($_POST['q']) && $_POST['q']!=='') $q = $_POST['q'];
else {
	header('HTTP/1.1 404 Not Found');
	exit();
}

define('MODX_API_MODE', true);
include_once('index.php');
$q = MODX_BASE_PATH . $q;
$q = str_replace('\\','/',$q);

if(!is_file($q) || strtolower(substr($q,-4))!=='.php') {
	header('HTTP/1.1 404 Not Found');
	exit();
}

// permission check
$allowed = false;
foreach($allowed_dirs as $allowed_dir) {
    if(strpos($q, MODX_BASE_PATH . $allowed_dir)===0)
    {
        $allowed = true;
        break;
    }
}
if ($allowed) include_once($q);
else {
	header('HTTP/1.1 404 Not Found');
	exit();
}
