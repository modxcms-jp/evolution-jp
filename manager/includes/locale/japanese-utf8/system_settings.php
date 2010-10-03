<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(strstr($settings_version, '0.9.')!==false)
{
$_lang['settings_after_install'] .= '<br /><strong style="color:red;">Version 0.9x系からのアップデートの場合はTinyMCEで画像の貼り付けができません。TinyMCEを最新にアップデートする必要があります。</strong><br />';
$_lang['settings_after_install'] .= '<br /><strong style="color:red;">また、QuidkEditプラグイン・Bottom button barプラグインを無効にすることをおすすめします。</strong>';
}

$manager_theme            = set_default('MODxCarbon',$manager_theme,strstr($settings_version, '0.9.')!==false);
$show_meta                = set_default('0', $show_meta);
$server_offset_time       = set_default('0', $server_offset_time);
$server_protocol          = set_default('http', $server_protocol);
$manager_language         = set_default('japanese-utf8', $manager_language);
$site_name                = set_default('MODxサイト', $site_name);
$site_start               = set_default('1', $site_start);
$error_page               = set_default('1', $error_page);
$unauthorized_page        = set_default('1', $unauthorized_page);
$site_status              = set_default('1', $site_status);
$site_unavailable_message = set_default('サイトは現在メンテナンス中です。しばらくお待ちください。', $site_unavailable_message);
$track_visitors           = set_default('0', $track_visitors);
$resolve_hostnames        = set_default('0', $resolve_hostnames);
$top_howmany              = set_default('10', $top_howmany);
$default_template         = set_default('3', $default_template);
$publish_default          = set_default('1', $publish_default);
$cache_default            = set_default('1', $cache_default);
$search_default           = set_default('1', $search_default);
$friendly_urls            = set_default('0', $friendly_urls);
// $friendly_url_prefix      = set_default('', $friendly_url_prefix);
$friendly_url_suffix      = set_default('.html', $friendly_url_suffix);
$friendly_alias_urls      = set_default('1', $friendly_alias_urls);
$use_alias_path           = set_default('1', $use_alias_path);
$use_udperms              = set_default('1', $use_udperms);
$udperms_allowroot        = set_default('0', $udperms_allowroot);
$failed_login_attempts    = set_default('3', $failed_login_attempts);
$blocked_minutes          = set_default('60', $blocked_minutes);
$use_captcha              = set_default('0', $use_captcha);
$captcha_words            = set_default('isono,fuguta,sazae,masuo,katsuo,wakame,tarao,namihei,fune,tama,mokuzu,umihei,norisuke,taiko,ikura,sakeo,norio,isasaka,hanazawa,hanako,anago', $captcha_words);
$emailsender              = set_default('myname@example.com', $emailsender);
$emailsubject             = set_default('ログイン情報のお知らせ', $emailsubject);
$number_of_logs           = set_default('100', $number_of_logs);
$number_of_messages       = set_default('30', $number_of_messages);
$number_of_results        = set_default('20', $number_of_results);
$use_editor               = set_default('1', $use_editor);
$use_browser              = set_default('1', $use_browser);
$rb_base_dir              = set_default(MODX_BASE_PATH, $rb_base_dir);
$rb_base_url              = set_default('assets/', $rb_base_url);
$which_editor             = set_default('TinyMCE', $which_editor);
$fe_editor_lang           = set_default('japanese-utf8', $fe_editor_lang);
$strip_image_paths        = set_default('0', $strip_image_paths);
$upload_images            = set_default('bmp,ico,gif,jpeg,jpg,png,psd,tif,tiff', $upload_images);
$upload_media             = set_default('au,avi,mp3,mp4,mpeg,mpg,wav,wmv', $upload_media);
$upload_flash             = set_default('fla,flv,swf', $upload_flash);
$upload_files             = set_default('aac,au,avi,css,cache,csv,doc,docx,gz,gzip,htaccess,htm,html,js,mp3,mp4,mpeg,mpg,ods,odp,odt,pdf,ppt,pptx,rar,tar,tgz,txt,wav,wmv,xls,xlsx,xml,z,zip', $upload_files);
$upload_maxsize           = set_default('1048576', $upload_maxsize);
$new_file_permissions     = set_default('0644', $new_file_permissions);
$new_folder_permissions   = set_default('0755', $new_folder_permissions);
// $filemanager_path         = set_default('', $filemanager_path);
$custom_contenttype       = set_default('application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain', $custom_contenttype);
$auto_menuindex           = set_default('1', $auto_menuindex);
$mail_check_timeperiod    = set_default('60', $mail_check_timeperiod);
$tree_show_protected      = set_default('0', $tree_show_protected);
$rss_url_news             = set_default('http://feeds2.feedburner.com/modxjp', $rss_url_news);
$rss_url_security         = set_default('http://feeds2.feedburner.com/modxjpsec', $rss_url_security);
$validate_referer         = set_default('1', $validate_referer);
$datepicker_offset        = set_default('-10', $datepicker_offset);
$xhtml_urls               = set_default('1', $xhtml_urls);
$allow_duplicate_alias    = set_default('1', $allow_duplicate_alias);
$automatic_alias          = set_default('0', $automatic_alias);
$datetime_format          = set_default('YYYY/mm/dd', $datetime_format);
$warning_visibility       = set_default('0', $warning_visibility);
$remember_last_tab        = set_default('1', $remember_last_tab);
$modx_charset             = set_default('UTF-8', $modx_charset);

// $old_template             = set_default('', $old_template);
// $fck_editor_toolbar       = set_default('standard', $fck_editor_toolbar);
// $fck_editor_autolang      = set_default('0', $fck_editor_autolang);
// $editor_css_selectors     = set_default('', $editor_css_selectors);
// $theme_refresher          = set_default('', $theme_refresher);
$manager_layout           = set_default('4', $manager_layout);
$manager_direction        = set_default('ltr', $manager_direction);
$tinymce_editor_theme     = set_default('editor', $tinymce_editor_theme);
$tinymce_custom_plugins   = set_default('save,advlist,clearfloat,style,fullscreen,advimage,paste,advlink,media,contextmenu,table', $tinymce_custom_plugins);
$tinymce_custom_buttons1  = set_default('undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,fontsizeselect,pastetext,pasteword,code,|,fullscreen,help', $tinymce_custom_buttons1);
$tinymce_custom_buttons2  = set_default('image,media,link,unlink,anchor,|,justifyleft,justifycenter,justifyright,clearfloat,|,bullist,numlist,|,blockquote,outdent,indent,|,table,hr,|,styleprops,removeformat', $tinymce_custom_buttons2);
$tinymce_css_selectors    = set_default('左寄せ=justifyleft;右寄せ=justifyright', $tinymce_css_selectors);

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

function set_default($default_value,$current_value,$flag = false)
{
	if(is_null($current_value) || $flag == true)
	{
		$value = $default_value;
	}
	else
	{
		$value = $current_value;
	}
	return $value;
}
