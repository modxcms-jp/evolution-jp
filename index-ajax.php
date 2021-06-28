<?php
// Add items to this array corresponding to which directories within assets/snippets/ can be used by this file.
// Do not add entries unneccesarily.
// Any PHP files in these directories can be executed by any user.
$allowed_dirs[] = 'assets/snippets/ajaxSearch/';

if(getv('q')!=='') {
    $q = getv('q');
} elseif(postv('q')!=='') {
    $q = postv('q');
} else {
    force_exit();
}

if(strpos(postv('ucfg'),'@EVAL')!==false) force_exit();

$base_path = str_replace('\\','/', __DIR__) . '/';
$q = $base_path . $q;
$q = str_replace('\\','/',$q);

$file_ext = strtolower(substr($q,-4));

if(!is_file($q) || $file_ext!=='.php' || strpos($q, $base_path . "assets/snippets/")!==0)
	force_exit();

// permission check
$allowed = false;
foreach($allowed_dirs as $allowed_dir) {
    if(strpos($q, $base_path . $allowed_dir)===0)
    {
        define('MODX_API_MODE', true);
        include_once('index.php');
        include_once($q);
        exit;
    }
}

force_exit();

// Force exit Function (404 Not Found)
function force_exit() {
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
	exit('404 Not Found');
}
