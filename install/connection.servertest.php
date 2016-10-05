<?php
define('MODX_API_MODE', true);
include_once('../manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
require_once('../manager/includes/default.config.php');
require_once('functions.php');
$language = $_SESSION['install_language'] ? $_SESSION['install_language'] : 'english';
includeLang($language);

$modx->db->hostname = $host = !isset($_POST['host']) ? '' : $_POST['host'];
$modx->db->username = !isset($_POST['uid']) ?  '' : $_POST['uid'];
$modx->db->password = !isset($_POST['pwd']) ?  '' : $_POST['pwd'];
$modx->db->connect();

if (!$modx->db->isConnected()) {
    $output = sprintf('<span id="server_fail" style="color:#FF0000;">%s</span>',$_lang['status_failed']);
    $bgcolor = '#ffe6eb';
}
    
else {
    $output = sprintf('<span id="server_pass" style="color:#388000;">%s</span>',$_lang['status_passed_server']);
    $bgcolor = '#e6ffeb';
    $_SESSION['database_server']   = $host;
    $_SESSION['database_user']     = $modx->db->username;
    $_SESSION['database_password'] = $modx->db->password;
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
    global $modx;
    $rs = $modx->db->query("SHOW COLLATION LIKE 'utf8%'");
    while($row=$modx->db->getRow($rs)) {
        if(isSafeCollation($row['Collation'])) $_[] = sprintf("%s:'%s'", $row['Collation'], $row['Collation']);
        //$row['Charset'];
    }
    return join(',', $_);
}

function isSafeCollation($collation) {
    if    (strpos($collation,'_general_c')!==false) return true;
    elseif(strpos($collation,'_unicode_c')!==false) return true;
    elseif(strpos($collation,'_bin')!==false)     return true;
    else                                          return false;
}