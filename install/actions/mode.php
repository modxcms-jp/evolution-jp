<?php

//back from next
if (post_textvar('adminemail') !== null) {
    sessionv('*adminemail', post_textvar('adminemail'));
}
if (post_textvar('adminpass') !== null) {
    sessionv('*adminpass', post_textvar('adminpass'));
}
if (post_textvar('adminpassconfirm') !== null) {
    sessionv('*adminpassconfirm', post_textvar('adminpassconfirm'));
}

$ph['is_upgradeable'] = sessionv('is_upgradeable');
if (!sessionv('is_upgradeable')) {
    $ph['installImg'] = 'install_new.png';
    $ph['welcome_title'] = lang('welcome_message_welcome');
    $ph['welcome_text'] = lang('welcome_message_text');
    $ph['installTitle'] = lang('installation_new_installation');
    $ph['installNote'] = lang('installation_install_new_note');
} else {
    $ph['installImg'] = 'install_upg.png';
    $ph['welcome_title'] = lang('welcome_message_upd_welcome');
    $ph['welcome_text'] = lang('welcome_message_upd_text');
    $ph['installTitle'] = lang('installation_upgrade_existing');
    $ph['installNote'] = lang('installation_upgrade_existing_note');
}
$ph['btnnext_value'] = lang('btnnext_value');
$ph['lang_options'] = get_lang_options(lang_name());

echo evo()->parseText(
    file_get_contents(
        MODX_BASE_PATH . 'install/tpl/mode.tpl'),
    $ph
);
