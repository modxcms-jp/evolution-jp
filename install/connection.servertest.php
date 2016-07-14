<?php
require_once('../manager/includes/default.config.php');
require_once('functions.php');
install_session_start();
$language = $_SESSION['install_language'] ? $_SESSION['install_language'] : 'english';
includeLang($language);

if(isset($_POST['host'])) $host = $_POST['host'];
if(isset($_POST['uid']))  $uid  = $_POST['uid'];
$pwd  = (isset($_POST['pwd'])) ? $_POST['pwd'] : '';

if(!isset($host) || !isset($uid))         $db = false;
$db = sql_connect($host, $uid, $pwd);

if (!$db) $output = sprintf('<span id="server_fail" style="color:#FF0000;">%s</span>',$_lang['status_failed']);
    
else {
    $output = sprintf('<span id="server_pass" style="color:#388000;">%s</span>',$_lang['status_passed_server']);
    $_SESSION['database_server']   = $host;
    $_SESSION['database_user']     = $uid;
    $_SESSION['database_password'] = $pwd;
}

echo sprintf('<div style="background: #eee;">%s</div>', $_lang["status_connecting"] . $output);
