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

if (!$db) {
    $output = sprintf('<span id="server_fail" style="color:#FF0000;">%s</span>',$_lang['status_failed']);
    $bgcolor = '#ffe6eb';
}
    
else {
    $output = sprintf('<span id="server_pass" style="color:#388000;">%s</span>',$_lang['status_passed_server']);
    $bgcolor = '#e6ffeb';
    $_SESSION['database_server']   = $host;
    $_SESSION['database_user']     = $uid;
    $_SESSION['database_password'] = $pwd;
}

echo sprintf('<div style="background: %s;padding:8px;border-radius:5px;">%s</div>', $bgcolor, $_lang["status_connecting"] . $output);

$script = '<script>
    var characters = {' . getCollation() . "},
    sel = jQuery('#collation'),
    opt,
    isSelected;

jQuery.each(characters, function (value, name) {
    isSelected = (value === 'utf8_general_ci');
    opt = jQuery('<option>')
        .val(value)
        .text(name)
        .prop('selected', isSelected);
    sel.append(opt);
});
</script>";
echo $script;

function getCollation() {
    $db = sql_connect(getOption('database_server'), getOption('database_user'), getOption('database_password'));
    $rs = sql_query('SHOW COLLATION');
    while($row=sql_fetch_assoc($rs)) {
        if(substr($row['Collation'],0,4)!='utf8') continue;
        if(_cond($row['Collation'])) $_[] = sprintf("%s:'%s'", $row['Collation'], $row['Collation']);
        //$row['Charset'];
    }
    return join(',', $_);
}

function _cond($collation) {
    if(strpos($collation,'_general_ci')!==false || strpos($collation,'_unicode_ci')!==false || strpos($collation,'_bin')!==false)
        return true;
    else return false;
}