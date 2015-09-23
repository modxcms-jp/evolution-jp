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

    // Mode check
    $rs = @ $mysqli->query("SELECT @@session.sql_mode");
    if (@ $rs->num_rows > 0 && !is_webmatrix() && !is_iis()){
        $modes = $rs->fetch_array(MYSQLI_NUM);
        $strictMode = false;
        foreach ($modes as $mode) {
    		if (stristr($mode, "STRICT_TRANS_TABLES") !== false || stristr($mode, "STRICT_ALL_TABLES") !== false) {
    			$strictMode = true;
    		}
        }
        if ($strictMode) $output .= '<br /><span style="color:#FF0000;"> '.$_lang['strict_mode'].'</span>';
    }
}
echo '<div style="background: #eee;">' . $_lang["status_connecting"] . $output . '</div>';
