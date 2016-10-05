<?php

//back from next
if($_SESSION['prevAction']==='options') {
	$_SESSION['installdata'] = $_POST['installdata'] ? $_POST['installdata'] : '';
	$_SESSION['template']    = $_POST['template']    ? $_POST['template']    : array();
	$_SESSION['tv']          = $_POST['tv']          ? $_POST['tv']          : array();
	$_SESSION['chunk']       = $_POST['chunk']       ? $_POST['chunk']       : array();
	$_SESSION['snippet']     = $_POST['snippet']     ? $_POST['snippet']     : array();
	$_SESSION['plugin']      = $_POST['plugin']      ? $_POST['plugin']      : array();
	$_SESSION['module']      = $_POST['module']      ? $_POST['module']      : array();
}

$_ = explode(',', 'adminname,adminemail,adminpass,adminpassconfirm,database_server,database_user,database_password,dbase,table_prefix');
$ph['installmode'] = $_SESSION['installmode'];
foreach($_ as $k) {
	if(isset($_SESSION[$k]))       $ph[$k] = $_SESSION[$k];
	elseif($k==='adminname')       $ph[$k] = 'admin';
	elseif($k==='database_server') $ph[$k] = 'localhost';
	elseif($k==='table_prefix')    $ph[$k] = 'modx_';
	else                           $ph[$k] = '';
}
if($ph['database_server'] == '127.0.0.1') $ph['database_server'] = 'localhost';
$src = file_get_contents("{$base_path}install/tpl/connection.tpl");
echo  parse($src,$ph);
