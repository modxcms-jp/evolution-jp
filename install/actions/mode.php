<?php

//back from next
if(isset($_POST['adminemail'])) {
    $_SESSION['adminemail'] = $_POST['adminemail'];
}
if(isset($_POST['adminpass'])) {
    $_SESSION['adminpass'] = $_POST['adminpass'];
}
if(isset($_POST['adminpassconfirm'])) {
    $_SESSION['adminpassconfirm'] = $_POST['adminpassconfirm'];
}

$ph['installmode']   = $_SESSION['installmode'];
if ($_SESSION['installmode'] == 0) {
    $ph['installImg'] = 'install_new.png';
    $ph['welcome_title'] = $_lang['welcome_message_welcome'];
    $ph['welcome_text'] = $_lang['welcome_message_text'];
    $ph['installTitle'] = $_lang['installation_new_installation'];
    $ph['installNote'] = $_lang['installation_install_new_note'];
} else {
    $ph['installImg'] = 'install_upg.png';
    $ph['welcome_title'] = $_lang['welcome_message_upd_welcome'];
    $ph['welcome_text'] = $_lang['welcome_message_upd_text'];
    $ph['installTitle'] = $_lang['installation_upgrade_existing'];
    $ph['installNote'] = $_lang['installation_upgrade_existing_note'];
}
$ph['btnnext_value'] = $_lang['btnnext_value'];
$ph['lang_options']  = get_lang_options($lang_name);

echo  evo()->parseText(
    file_get_contents(
        MODX_BASE_PATH . 'install/tpl/mode.tpl')
    , $ph
);
