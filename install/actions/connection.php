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

$installmode = $_SESSION['installmode'];

$ph['adminname']        = isset($_SESSION['adminname'])        ? $_SESSION['adminname']       : 'admin';
$ph['adminemail']       = isset($_SESSION['adminemail'])       ? $_SESSION['adminemail']       : '';
$ph['adminpass']        = isset($_SESSION['adminpass'])        ? $_SESSION['adminpass']        : '';
$ph['adminpassconfirm'] = isset($_SESSION['adminpassconfirm']) ? $_SESSION['adminpassconfirm'] : '';

$ph['database_server']   = $_SESSION['database_server'];
$ph['database_user']     = $_SESSION['database_user'];
$ph['database_password'] = $_SESSION['database_password'];
$ph['dbase']             = $_SESSION['dbase'];
$ph['table_prefix']      = $_SESSION['table_prefix'];

$src = file_get_contents("{$base_path}install/tpl/connection.tpl");
echo  parse($src,$ph);
