<?php

//back from next
if($prevAction==='options') {
	$_SESSION['installdata'] = $_POST['installdata'] ? $_POST['installdata'] : '';
	$_SESSION['template']    = $_POST['template'];
	$_SESSION['tv']          = $_POST['tv'];
	$_SESSION['chunk']       = $_POST['chunk'];
	$_SESSION['snippet']     = $_POST['snippet'];
	$_SESSION['plugin']      = $_POST['plugin'];
	$_SESSION['module']      = $_POST['module'];
}

if(isset($_POST['installmode'])) $_SESSION['installmode'] = $_POST['installmode'];
$installmode = $_SESSION['installmode'];

$ph['adminname']        = isset($_SESSION['adminname'])        ? $_SESSION['adminname']       : 'admin';
$ph['adminemail']       = isset($_SESSION['adminemail'])       ? $_SESSION['adminemail']       : '';
$ph['adminpass']        = isset($_SESSION['adminpass'])        ? $_SESSION['adminpass']        : '';
$ph['adminpassconfirm'] = isset($_SESSION['adminpassconfirm']) ? $_SESSION['adminpassconfirm'] : '';

if (is_file("{$base_path}manager/includes/config.inc.php")) {
	global $dbase,$database_server,$database_user,$database_password,$table_prefix;
	include_once("{$base_path}manager/includes/config.inc.php");
}

$dbase                      = get_dbase();
$database_server            = get_database_server();
$database_user              = get_database_user();
$database_password          = get_database_password();
$table_prefix               = get_table_prefix();
$database_connection_method = get_database_connection_method();
$database_collation         = get_database_collation();

if($installmode == 1)
{
	if(!empty($dbase) && !empty($database_server) && !empty($database_user))
	{
		$mysqli = new mysqli($database_server, $database_user, $database_password, $dbase);
		if(!$mysqli) {
			$installmode = '2';
			$_SESSION['installmode'] = '2';
		}
	}
}

$ph['database_server']   = $database_server;
$ph['database_user']     = $database_user;
$ph['database_password'] = $database_password;
$ph['dbase']             = $dbase;
$ph['table_prefix']      = $table_prefix;

$ph['set_block_connection_method'] = get_set_block_connection_method($installmode,$ph);
$ph['AUH']                         = get_set_block_AUH($installmode,$ph);

$src = file_get_contents("{$base_path}install/tpl/connection.tpl");
echo  parse($src,$ph);

function get_set_block_connection_method($installmode,$ph) {
    if ($installmode != 0 && $installmode != 2) $tpl = '';
	else {
		$tpl = <<< TPL
<p class="labelHolder">
<div id="connection_method" name="connection_method">
    <input type="hidden" value="SET CHARACTER SET" id="database_connection_method" name="database_connection_method" />
</div>
</p>
TPL;
	}
	return parse($tpl,$ph);
}

function get_set_block_AUH($installmode,$ph) {
	if($installmode != 0) $tpl = '';
	else {
		$tpl = <<< TPL
<div id="AUH" style="margin-top:1.5em;display:none;">
<div id="AUHMask">
<h2>[+connection_screen_defaults+]</h2>
<h3>[+connection_screen_default_admin_user+]</h3>
<p>[+connection_screen_default_admin_note+]</p>
<p class="labelHolder"><label for="adminname">[+connection_screen_default_admin_login+]</label>
  <input id="adminname" value="[+adminname+]" name="adminname" />
</p>
<p class="labelHolder"><label for="adminemail">[+connection_screen_default_admin_email+]</label>
  <input id="adminemail" value="[+adminemail+]" name="adminemail" style="width:300px;" />
</p>
<p class="labelHolder"><label for="adminpass">[+connection_screen_default_admin_password+]</label>
  <input id="adminpass" type="password" name="adminpass" value="[+adminpass+]" />
</p>
<p class="labelHolder"><label for="adminpassconfirm">[+connection_screen_default_admin_password_confirm+]</label>
  <input id="adminpassconfirm" type="password" name="adminpassconfirm" value="[+adminpassconfirm+]" />
</p>
</div>
</div>
TPL;
	}
	return parse($tpl,$ph);
}
