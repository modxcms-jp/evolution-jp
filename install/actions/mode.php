<?php

//back from next
if(isset($_POST['adminemail']))       $_SESSION['adminemail']       = $_POST['adminemail'];
if(isset($_POST['adminpass']))        $_SESSION['adminpass']        = $_POST['adminpass'];
if(isset($_POST['adminpassconfirm'])) $_SESSION['adminpassconfirm'] = $_POST['adminpassconfirm'];

$ph['installmode']   = $_SESSION['installmode'];
$ph['installImg']    = ($_SESSION['installmode']==0) ? 'install_new.png'                       : 'install_upg.png';
$ph['welcome_title'] = ($_SESSION['installmode']==0) ? $_lang['welcome_message_welcome']       : $_lang['welcome_message_upd_welcome'];
$ph['welcome_text']  = ($_SESSION['installmode']==0) ? $_lang['welcome_message_text']          : $_lang['welcome_message_upd_text'];
$ph['installTitle']  = ($_SESSION['installmode']==0) ? $_lang['installation_new_installation'] : $_lang['installation_upgrade_existing'];
$ph['installNote']   = ($_SESSION['installmode']==0) ? $_lang['installation_install_new_note'] : $_lang['installation_upgrade_existing_note'];
$ph['btnnext_value'] = $_lang['btnnext_value'];
$ph['lang_options']  = get_lang_options($lang_name);

$tpl = file_get_contents("{$base_path}install/tpl/mode.tpl");
echo  parse($tpl,$ph);
