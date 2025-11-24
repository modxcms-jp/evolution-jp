<?php
$default_config = [
    'site_url' => MODX_SITE_URL,
    'base_url' => MODX_BASE_URL,
    'allow_duplicate_alias' => '0',
    'allow_mgr2web' => '0',
    'auto_menuindex' => '1',
    'auto_template_logic' => 'system',
    'automatic_alias' => '2',
    'blocked_minutes' => '10',
    'cache_default' => '1',
    'disable_cache_at_login' => '1',
    'cache_type' => '1',
    'individual_cache' => '0',
    'legacy_cache' => '0',
    'conditional_get' => '0',
    'captcha_words' => 'maguro,toro,tako,ika,hotate,awabi,kazunoko,ebi,kani,uni,iwashi,aji,saba,tamago,negitoro,tekka,hamachi,sanma,sake,tai,buri,hirame,unagi,anago,amaebi,ikura,kanpachi,syako',
    'clean_uploaded_filename' => '0',
    'custom_contenttype' => 'application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain,application/json',
    'datepicker_offset' => '-10',
    'datetime_format' => 'YYYY/mm/dd',
    'default_template' => '1',
    'docid_incrmnt_method' => '0',
    'editor_css_path' => '',
    'editor_css_selectors' => '',
    'emailsubject' => 'サイトからのお知らせ',
    'failed_login_attempts' => '5',
    'fe_editor_lang' => 'japanese-utf8',
    'friendly_alias_urls' => '1',
    'friendly_url_prefix' => '',
    'friendly_url_suffix' => '.html',
    'friendly_urls' => '1',
    'limit_by_container' => '100',
    'modxmailer_log' => '0',
    'mail_check_timeperiod' => '60',
    'manager_direction' => 'ltr',
    'manager_language' => 'japanese-utf8',
    'manager_theme' => 'AuroraFlow',
    'modx_charset' => 'UTF-8',
    'new_file_permissions' => '0644',
    'new_folder_permissions' => '0755',
    'number_of_logs' => '100',
    'number_of_messages' => '30',
    'number_of_results' => '20',
    'publish_default' => '1',
    'pm2email' => '1',
    'rb_webuser' => '0',
    'remember_last_tab' => '1',
    'resource_tree_sortby_default' => 'menuindex',
    'resource_tree_node_name' => 'pagetitle',
    'rss_url_news' => 'https://feeds2.feedburner.com/modxjp',
    'rss_url_security' => 'https://feeds2.feedburner.com/modxjpsec',
    'search_default' => '1',
    'send_errormail' => '3',
    'server_offset_time' => '0',
    'server_protocol' => 'https',
    'session.cookie.lifetime' => '604800',
    'site_name' => 'My MODX Site',
    'site_slogan' => 'ここにサイトのスローガン文を表示します。',
    'site_start' => '1',
    'site_status' => "1",
    'site_unavailable_message' => 'サイトは現在メンテナンス中です。しばらくお待ちください。',
    'strip_image_paths' => '0',
    'suffix_mode' => '1',
    'top_howmany' => '10',
    'track_visitors' => '0',
    'tree_page_click' => 'auto',
    'tree_show_protected' => '0',
    'udperms_allowroot' => '0',
    'upload_files' => 'aac,css,csv,cache,doc,docx,gz,gzip,htaccess,htm,html,js,ods,odp,odt,pdf,ppt,pptx,rar,tar,tgz,txt,xls,xlsx,xml,z,zip',
    'upload_images' => 'bmp,ico,gif,jpeg,jpg,png,svg,psd,tif,tiff',
    'upload_maxsize' => '',
    'upload_media' => 'au,avi,mp3,mp4,mpeg,mpg,wav,wmv',
    'use_alias_path' => '1',
    'use_browser' => '1',
    'use_captcha' => '0',
    'use_editor' => '1',
    'use_udperms' => '0',
    'warning_visibility' => '2',
    'which_editor' => 'TinyMCE7',
    'xhtml_urls' => '1',
    'error_reporting' => '1',
    'filemanager_path' => defined('MODX_BASE_PATH') ? MODX_BASE_PATH : '',
    'rb_base_dir' => defined('MODX_BASE_PATH') ? MODX_BASE_PATH . 'content/' : '',
    'rb_base_url' => 'content/',
    'image_limit_width' => '',
    'manager_inline_style' => '<style type="text/css">body {font-size:0.75em;}</style>',
    'topmenu_site' => 'home,preview,refresh_site,search,resource_list,add_resource,add_weblink',
    'topmenu_element' => 'element_management,manage_files',
    'topmenu_security' => 'user_manage,web_user_manage,role_manage,manager_permissions,web_permissions,remove_locks',
    'topmenu_user' => 'change_user_pf,change_password,messages',
    'topmenu_tools' => 'bk_manager,export_site,import_site,edit_settings',
    'topmenu_reports' => 'site_schedule,eventlog_viewer,view_logging,view_sysinfo',
    'tree_pane_open_default' => '1',
    'auto_sleep_user' => '365',
    'doc_encoding' => 'UTF-8',
    'enable_draft' => '0',
    'automatic_optimize' => '1',
    'sanitize_gpc' => 1,
    'manager_docs_orderby' => 'isfolder desc, publishedon desc, editedon desc, id desc',
    'manager_treepane_trim_title' => 1,
    'convert_datauri_to_file' => '1',
];

if (getv('a') !== '17') {
    return $default_config;
}

$default_config['signupemail_message'] = lang('system_email_signup');
$default_config['websignupemail_message'] = lang('system_email_websignup');
$default_config['webpwdreminder_message'] = lang('system_email_webreminder');

$default_config['enable_bindings'] = '0';
$default_config['make_folders'] = '1';

$default_config['pwd_hash_algo'] = 'UNCRYPT';

if (!function_exists('mysqli_set_charset') && !function_exists('mysql_set_charset')) {
    global $_lang;
    $_lang['settings_after_install'] .= '<br /><strong style="color:red;">この環境では日本語以外の文字(中国語・韓国語・一部の機種依存文字など)を入力できません。</strong>対応が必要な場合は、サーバ環境のUTF-8エンコードの扱いを整備したうえで、mysqli.incのescape関数の処理を書き換えてください。mb_convert_encodingの処理を行なっている行が2行ありますので、これを削除します。';
}

switch (evo()->config('settings_version')) {
    case '1.0.5J-r11':
    case '1.0.6J':
    case '1.0.6J-r1':
        $rs = db()->select('id', '[+prefix+]site_htmlsnippets', "name='ログイン画面'");
        if (db()->count($rs)) {
            db()->update('published=0', '[+prefix+]site_htmlsnippets', "name='ログイン画面'");
        }
        $rs = db()->select('id', '[+prefix+]site_htmlsnippets', "name='ダッシュボード'");
        if (db()->count($rs)) {
            db()->update('published=0', '[+prefix+]site_htmlsnippets', "name='ダッシュボード'");
        }
        break;
}

return $default_config;
