<?php
// 言語に依らない既定値は default.config.base.php（読み込み時に補われる）。
// ここは a=17（グローバル設定の画面）でだけ足す既定。言語の文言を使うもの（メールの文面）と、
// 行の無いサイトの動きを変えないよう設定画面にだけ出す既定（enable_bindings・make_folders・pwd_hash_algo）。
// ここに足した既定は getSettings() では補われない
$default_config = include __DIR__ . '/default.config.base.php';

if (getv('a') !== '17') {
    return $default_config;
}

$default_config['signupemail_message'] = lang('system_email_signup');
$default_config['websignupemail_message'] = lang('system_email_websignup');
$default_config['webpwdreminder_message'] = lang('system_email_webreminder');

$default_config['enable_bindings'] = '0';
$default_config['make_folders'] = '1';

$default_config['pwd_hash_algo'] = 'UNCRYPT';

return $default_config;
