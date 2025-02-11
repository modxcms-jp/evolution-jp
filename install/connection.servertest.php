<?php
include '../define-path.php';
define('MODX_API_MODE', true);
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');
include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$modx = new DocumentParser;
require_once MODX_BASE_PATH . 'install/functions.php';
$_lang = includeLang(sessionv('install_language', 'english'));
$modx->db->hostname = postv('host', 'localhost');
$modx->db->username = postv('uid', 'root');
$modx->db->password = postv('pwd', 'passwford');
db()->connect();

if (!db()->isConnected()) {
    exit(sprintf(
        '<div style="background: #ffe6eb;padding:8px;border-radius:5px;"><span id="server_fail" style="color:#FF0000;">%s</span></div>'
        , lang('status_failed')
    ));
}

$output = sprintf(
    '<span id="server_pass" style="color:#388000;">%s</span>'
    , lang('status_passed_server')
);
sessionv('*database_server', db()->hostname);
sessionv('*database_user', db()->username);
sessionv('*database_password', db()->password);

echo sprintf(
    '<div style="background: #e6ffeb;padding:8px;border-radius:5px;">%s</div>'
    , lang('status_connecting') . $output
);

$script = '<script>
    let characters = {' . getCollation() . "};
    let sel = jQuery('#collation');
    let opt;
    let isSelected;

jQuery.each(characters, function (value, name) {
    isSelected = (value === 'utf8mb4_general_ci');
    opt = jQuery('<option>')
        .val(value)
        .text(name)
        .prop('selected', isSelected);
    sel.append(opt);
});
</script>";
echo $script;

function getCollation()
{
    $rs = db()->query("SHOW COLLATION LIKE 'utf8%'");
    while ($row = db()->getRow($rs)) {
        if (isSafeCollation($row['Collation'])) {
            $_[] = sprintf("%s:'%s'", $row['Collation'], $row['Collation']);
        }
        //$row['Charset'];
    }
    return implode(',', $_);
}

function isSafeCollation($collation)
{
    if (strpos($collation, '_general_c') !== false) {
        return true;
    }

    if (strpos($collation, '_unicode_c') !== false) {
        return true;
    }

    if (strpos($collation, '_bin') !== false) {
        return true;
    }

    return false;
}
