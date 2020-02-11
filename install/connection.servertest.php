<?php
define('MODX_API_MODE', true);
define('MODX_BASE_PATH', str_replace('\\','/', dirname(__DIR__)).'/');
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
require_once(MODX_BASE_PATH.'manager/includes/default.config.php');
require_once(MODX_BASE_PATH . 'install/functions.php');

$language = sessionv('install_language', 'english');
includeLang($language);

$modx->db->hostname = postv('host','');
$modx->db->username = postv('uid','');
$modx->db->password = postv('pwd','');
$modx->db->connect();

if (!$modx->db->isConnected()) {
    $output = sprintf(
        '<span id="server_fail" style="color:#FF0000;">%s</span>'
        , lang('status_failed')
    );
    $bgcolor = '#ffe6eb';
}
    
else {
    $output = sprintf(
        '<span id="server_pass" style="color:#388000;">%s</span>'
        , lang('status_passed_server')
    );
    $bgcolor = '#e6ffeb';
    sessionv('*database_server', db()->hostname);
    sessionv('*database_user', db()->username);
    sessionv('*database_password', db()->password);
}

echo sprintf(
    '<div style="background: %s;padding:8px;border-radius:5px;">%s</div>'
    , $bgcolor
    , lang('status_connecting') . $output
);

$script = "<script>
    let opt;
    let characters = {%s}
    let sel = jQuery('#collation');

jQuery.each(characters, function (value, name) {
    isSelected = (value === 'utf8_general_ci');
    opt = jQuery('option').val(value).text(name)
        .prop('selected', isSelected);
    sel.append(opt);
});
</script>";
echo sprintf($script, getCollation());

function getCollation() {
    $rs = db()->query("SHOW COLLATION LIKE 'utf8%'");
    while($row=db()->getRow($rs)) {
        if(isSafeCollation($row['Collation'])) {
            $_[] = sprintf("%s:'%s'", $row['Collation'], $row['Collation']);
        }
        //$row['Charset'];
    }
    return implode(',', $_);
}

function isSafeCollation($collation) {
    if (strpos($collation,'_general_c')!==false) {
        return true;
    }

    if (strpos($collation,'_unicode_c')!==false) {
        return true;
    }

    if (strpos($collation,'_bin')!==false) {
        return true;
    }

    return false;
}