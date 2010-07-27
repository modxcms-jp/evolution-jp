<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(strstr($settings_version, '0.9.')!==false)
{
$_lang['settings_after_install'] .= '<br /><strong style="color:red;">Version 0.9x系からのアップデートの場合はTinyMCEで画像の貼り付けができません。TinyMCEを最新にアップデートする必要があります。</strong>';
}

$manager_theme            = (is_null($manager_theme) || strstr($settings_version, '0.9.')!==false) ? 'MODxCarbon' : $manager_theme;
$show_meta                = (is_null($show_meta)) ? '0' : $show_meta;
$server_offset_time       = (is_null($server_offset_time)) ? '0' : $server_offset_time;
$server_protocol          = (is_null($server_protocol)) ? 'http' : $server_protocol;
$manager_language         = (is_null($manager_language)) ? 'japanese-utf8' : $manager_language;
$site_name                = (is_null($site_name)) ? 'My MODx Site' : $site_name;
$site_start               = (is_null($site_start)) ? '1' : $site_start;
$error_page               = (is_null($error_page)) ? '1' : $error_page;
$unauthorized_page        = (is_null($unauthorized_page)) ? '1' : $unauthorized_page;
$site_status              = (is_null($site_status)) ? '1' : $site_status;
$site_unavailable_message = (is_null($site_unavailable_message)) ? 'サイトは現在メンテナンス中です。しばらくお待ちください。' : $site_unavailable_message;
$track_visitors           = (is_null($track_visitors)) ? '0' : $track_visitors;
$resolve_hostnames        = (is_null($resolve_hostnames)) ? '0' : $resolve_hostnames;
$top_howmany              = (is_null($top_howmany)) ? '10' : $top_howmany;
$default_template         = (is_null($default_template)) ? '3' : $default_template;
$publish_default          = (is_null($publish_default)) ? '1' : $publish_default;
$cache_default            = (is_null($cache_default)) ? '1' : $cache_default;
$search_default           = (is_null($search_default)) ? '1' : $search_default;
$friendly_urls            = (is_null($friendly_urls)) ? '0' : $friendly_urls;
// $friendly_url_prefix      = (is_null($friendly_url_prefix)) ? '' : $friendly_url_prefix;
$friendly_url_suffix      = (is_null($friendly_url_suffix)) ? '.html' : $friendly_url_suffix;
$friendly_alias_urls      = (is_null($friendly_alias_urls)) ? '1' : $friendly_alias_urls;
$use_alias_path           = (is_null($use_alias_path)) ? '1' : $use_alias_path;
$use_udperms              = (is_null($use_udperms)) ? '1' : $use_udperms;
$udperms_allowroot        = (is_null($udperms_allowroot)) ? '0' : $udperms_allowroot;
$failed_login_attempts    = (is_null($failed_login_attempts)) ? '3' : $failed_login_attempts;
$blocked_minutes          = (is_null($blocked_minutes)) ? '60' : $blocked_minutes;
$use_captcha              = (is_null($use_captcha)) ? '0' : $use_captcha;
$captcha_words            = (is_null($captcha_words)) ? 'isono,fuguta,sazae,masuo,katsuo,wakame,tarao,namihei,fune,tama,mokuzu,umihei,norisuke,taiko,ikura,sakeo,norio,isasaka,hanazawa,hanako,anago' : $captcha_words;
$emailsender              = (is_null($emailsender)) ? 'myname@example.com' : $emailsender;
$emailsubject             = (is_null($emailsubject)) ? 'ログイン情報のお知らせ' : $emailsubject;
$number_of_logs           = (is_null($number_of_logs)) ? '100' : $number_of_logs;
$number_of_messages       = (is_null($number_of_messages)) ? '30' : $number_of_messages;
$number_of_results        = (is_null($number_of_results)) ? '20' : $number_of_results;
$use_editor               = (is_null($use_editor)) ? '1' : $use_editor;
$use_browser              = (is_null($use_browser)) ? '1' : $use_browser;
$rb_base_dir              = (is_null($rb_base_dir)) ? MODX_BASE_PATH . 'assets/' : $rb_base_dir;
$rb_base_url              = (is_null($rb_base_url)) ? 'assets/' : $rb_base_url;
$which_editor             = (is_null($which_editor)) ? 'TinyMCE' : $which_editor;
$fe_editor_lang           = (is_null($fe_editor_lang)) ? 'japanese-utf8' : $fe_editor_lang;
$strip_image_paths        = (is_null($strip_image_paths)) ? '0' : $strip_image_paths;
$upload_images            = (is_null($upload_images)) ? 'bmp,ico,gif,jpeg,jpg,png,psd,tif,tiff' : $upload_images;
$upload_media             = (is_null($upload_media)) ? 'au,avi,mp3,mp4,mpeg,mpg,wav,wmv' : $upload_media;
$upload_flash             = (is_null($upload_flash)) ? 'fla,flv,swf' : $upload_flash;
$upload_files             = (is_null($upload_files)) ? 'aac,au,avi,css,cache,doc,docx,gz,gzip,htaccess,htm,html,js,mp3,mp4,mpeg,mpg,ods,odp,odt,pdf,ppt,pptx,rar,tar,tgz,txt,wav,wmv,xls,xlsx,xml,z,zip' : $upload_files;
$upload_maxsize           = (is_null($upload_maxsize)) ? '1048576' : $upload_maxsize;
$new_file_permissions     = (is_null($new_file_permissions)) ? '0644' : $new_file_permissions;
$new_folder_permissions   = (is_null($new_folder_permissions)) ? '0755' : $new_folder_permissions;
// $filemanager_path         = (is_null($filemanager_path)) ? '' : $filemanager_path;
$custom_contenttype       = (is_null($custom_contenttype)) ? 'application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain' : $custom_contenttype;
$auto_menuindex           = (is_null($auto_menuindex)) ? '1' : $auto_menuindex;
$mail_check_timeperiod    = (is_null($mail_check_timeperiod)) ? '60' : $mail_check_timeperiod;
$tree_show_protected      = (is_null($tree_show_protected)) ? '0' : $tree_show_protected;
$rss_url_news             = (is_null($rss_url_news)) ? 'http://feeds2.feedburner.com/modxjp' : $rss_url_news;
$rss_url_security         = (is_null($rss_url_security)) ? 'http://feeds2.feedburner.com/modxjpsec' : $rss_url_security;
$validate_referer         = (is_null($validate_referer)) ? '1' : $validate_referer;
$datepicker_offset        = (is_null($datepicker_offset)) ? '-10' : $datepicker_offset;
$xhtml_urls               = (is_null($xhtml_urls)) ? '1' : $xhtml_urls;
$allow_duplicate_alias    = (is_null($allow_duplicate_alias)) ? '1' : $allow_duplicate_alias;
$automatic_alias          = (is_null($automatic_alias)) ? '0' : $automatic_alias;
$datetime_format          = (is_null($datetime_format)) ? 'YYYY/mm/dd' : $datetime_format;
$warning_visibility       = (is_null($warning_visibility)) ? '0' : $warning_visibility;
$remember_last_tab        = (is_null($remember_last_tab)) ? '1' : $remember_last_tab;
$modx_charset             = (is_null($modx_charset)) ? 'UTF-8' : $modx_charset;

// $old_template             = (is_null($old_template)) ? '' : $old_template;
// $fck_editor_toolbar       = (is_null($fck_editor_toolbar)) ? 'standard' : $fck_editor_toolbar;
// $fck_editor_autolang      = (is_null($fck_editor_autolang)) ? '0' : $fck_editor_autolang;
// $editor_css_selectors     = (is_null($editor_css_selectors)) ? '' : $editor_css_selectors;
// $theme_refresher          = (is_null($theme_refresher)) ? '' : $theme_refresher;
$manager_layout           = (is_null($manager_layout)) ? '4' : $manager_layout;
$manager_direction        = (is_null($manager_direction)) ? 'ltr' : $manager_direction;
$tinymce_editor_theme     = (empty($tinymce_editor_theme)) ? 'editor' : $tinymce_editor_theme;
$tinymce_custom_plugins   = (empty($tinymce_custom_plugins)) ? 'save,advlist,clearfloat,style,fullscreen,advimage,paste,advlink,media,contextmenu,table' : $tinymce_custom_plugins;
$tinymce_custom_buttons1  = (empty($tinymce_custom_buttons1)) ? 'undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,fontsizeselect,pastetext,pasteword,code,|,fullscreen,help' : $tinymce_custom_buttons1;
$tinymce_custom_buttons2  = (empty($tinymce_custom_buttons2)) ? 'image,media,link,unlink,anchor,|,justifyleft,justifycenter,justifyright,clearfloat,|,bullist,numlist,|,blockquote,outdent,indent,|,table,hr,|,styleprops,removeformat' : $tinymce_custom_buttons2;
$tinymce_css_selectors    = (is_null($tinymce_css_selectors)) ? '左寄せ=justifyleft;右寄せ=justifyright' : $tinymce_css_selectors;

$data = $modx->db->getTableMetaData($modx->getFullTableName('user_roles'));
if($data['remove_locks'] == false)
{
	$sql = 'ALTER TABLE ' . $modx->getFullTableName(user_roles)
	     . " ADD COLUMN `remove_locks` int(1) NOT NULL DEFAULT '0'";
	$modx->db->query($sql);
	$sql = 'UPDATE '      . $modx->getFullTableName(user_roles)
	     . " SET `remove_locks` = '1' WHERE `id` =1";
	$modx->db->query($sql);
}

$sql = 'REPLACE INTO ' . $modx->getFullTableName('system_eventnames')
       . ' (id,name,service,groupname) VALUES '
       . "('100', 'OnStripAlias',             '1','Documents'),
          ('201', 'OnManagerWelcomePrerender','2',''),
          ('202', 'OnManagerWelcomeHome',     '2',''),
          ('203', 'OnManagerWelcomeRender',   '2',''),
          ('204', 'OnBeforeDocDuplicate',     '1','Documents'),
          ('205', 'OnDocDuplicate',           '1','Documents')";
$modx->db->query($sql);
