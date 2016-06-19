<?php
require_once('../manager/includes/default.config.php');
require_once('functions.php');
install_session_start();
$language = getOption('install_language');
includeLang($language);

if(isset($_POST['host'])) $host = $_POST['host'];
if(isset($_POST['uid']))  $uid  = $_POST['uid'];
$pwd  = (isset($_POST['pwd'])) ? $_POST['pwd'] : '';

if(!isset($host) || !isset($uid))
{
	$mysqli = false;
}
else $mysqli = @ new mysqli($host, $uid, $pwd);

if (!$mysqli) {
    $output = '<span id="server_fail" style="color:#FF0000;"> '.$_lang['status_failed'].'</span>';
} else {
    $output = '<span id="server_pass" style="color:#388000;"> '.$_lang['status_passed_server'].'</span>';
    $_SESSION['database_server']   = $host;
    $_SESSION['database_user']     = $uid;
    $_SESSION['database_password'] = $pwd;
}
echo '<div style="background: #eee;">' . $_lang["status_connecting"] . $output . '</div>';
