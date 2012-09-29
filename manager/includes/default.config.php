<?php
$default_config['allow_duplicate_alias']    = '0';
$default_config['allow_mgr2web']            = '0';
$default_config['auto_menuindex']           = '1';
$default_config['auto_template_logic']      = 'system';
$default_config['automatic_alias']          = '2';
$default_config['blocked_minutes']          = '60';
$default_config['cache_default']            = '1';
$default_config['cache_type']               = '1';
$default_config['captcha_words']            = 'maguro,toro,tako,ika,hotate,awabi,kazunoko,ebi,kani,uni,iwashi,aji,saba,tamago,negitoro,tekka,hamachi,sanma,sake,tai,buri,hirame,unagi,anago,amaebi,ikura,kanpachi,syako';
$default_config['clean_uploaded_filename']  = '0';
$default_config['custom_contenttype']       = 'application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain';
$default_config['datepicker_offset']        = '-10';
$default_config['datetime_format']          = 'YYYY/mm/dd';
$default_config['default_template']         = '1';
$default_config['docid_incrmnt_method']     = '0';
$default_config['editor_css_path']          = '';
$default_config['editor_css_selectors']     = '';
$default_config['emailsubject']             = 'サイトからのお知らせ';
$default_config['error_page']               = '1';
$default_config['failed_login_attempts']    = '3';
$default_config['fe_editor_lang']           = 'japanese-utf8';
$default_config['friendly_alias_urls']      = '1';
$default_config['friendly_url_prefix']      = '';
$default_config['friendly_url_suffix']      = '.html';
$default_config['friendly_urls']            = '0';
$default_config['limit_by_container']       = '100';
$default_config['mail_check_timeperiod']    = '60';
$default_config['manager_direction']        = 'ltr';
$default_config['manager_language']         = 'japanese-utf8';
$default_config['manager_theme']            = 'RevoStyle';
$default_config['modx_charset']             = 'UTF-8';
$default_config['new_file_permissions']     = '0644';
$default_config['new_folder_permissions']   = '0755';
$default_config['number_of_logs']           = '100';
$default_config['number_of_messages']       = '30';
$default_config['number_of_results']        = '20';
$default_config['publish_default']          = '1';
$default_config['pm2email']                 = '1';
$default_config['rb_webuser']               = '0';
$default_config['remember_last_tab']        = '1';
$default_config['resource_tree_node_name']  = 'pagetitle';
$default_config['rss_url_news']             = 'http://feeds2.feedburner.com/modxjp';
$default_config['rss_url_security']         = 'http://feeds2.feedburner.com/modxjpsec';
$default_config['search_default']           = '1';
$default_config['send_errormail']           = '3';
$default_config['server_offset_time']       = '0';
$default_config['server_protocol']          = 'http';
$default_config['session.cookie.lifetime']  = '604800';
$default_config['show_meta']                = '0';
$default_config['site_name']                = 'My MODX Site';
$default_config['site_slogan']              = 'ここにサイトのスローガン文を表示します。';
$default_config['site_start']               = '1';
$default_config['site_status']              = "1";
$default_config['site_unavailable_message'] = 'サイトは現在メンテナンス中です。しばらくお待ちください。';
$default_config['strip_image_paths']        = '0';
$default_config['suffix_mode']              = '1';
$default_config['top_howmany']              = '10';
$default_config['track_visitors']           = '0';
$default_config['tree_page_click']          = 'auto';
$default_config['tree_show_protected']      = '0';
$default_config['udperms_allowroot']        = '0';
$default_config['unauthorized_page']        = '1';
$default_config['upload_files']             = 'aac,css,csv,cache,doc,docx,gz,gzip,htaccess,htm,html,js,ods,odp,odt,pdf,ppt,pptx,rar,tar,tgz,txt,xls,xlsx,xml,z,zip';
$default_config['upload_flash']             = 'fla,flv,swf';
$default_config['upload_images']            = 'bmp,ico,gif,jpeg,jpg,png,psd,tif,tiff';
$default_config['upload_maxsize']           = '';
$default_config['upload_media']             = 'au,avi,mp3,mp4,mpeg,mpg,wav,wmv';
$default_config['use_alias_path']           = '1';
$default_config['use_browser']              = '1';
$default_config['use_captcha']              = '0';
$default_config['use_editor']               = '1';
$default_config['use_udperms']              = '0';
$default_config['validate_referer']         = '1';
$default_config['warning_visibility']       = '2';
$default_config['which_editor']             = 'TinyMCE';
$default_config['xhtml_urls']               = '1';
$default_config['error_reporting']          = '1';

$default_config['filemanager_path']         = defined('MODX_BASE_PATH') ? MODX_BASE_PATH:'';
$default_config['rb_base_dir']              = defined('MODX_BASE_PATH') ? MODX_BASE_PATH . 'content/':'';
$default_config['rb_base_url']              = 'content/';
$default_config['enable_phx']               = '1';
$default_config['image_limit_width']         = '';

if(!isset($_GET['a']) || $_GET['a'] !=='17') return;

$default_config['signupemail_message']    = $_lang['system_email_signup'];
$default_config['websignupemail_message'] = $_lang['system_email_websignup'];
$default_config['webpwdreminder_message'] = $_lang['system_email_webreminder'];

$default_config['enable_bindings'] = '0';

if(!function_exists('mysql_set_charset'))
{
	$_lang['settings_after_install'] .= '<br /><strong style="color:red;">この環境では日本語以外の文字(中国語・韓国語・一部の機種依存文字など)を入力できません。</strong>対応が必要な場合は、サーバ環境のUTF-8エンコードの扱いを整備したうえで、dbapi.mysql.class.inc.phpのescape関数の処理を書き換えてください。mb_convert_encodingの処理を行なっている行が2行ありますので、これを削除します。';
}

switch($settings_version)
{
	case '1.0.5J-r11':
	case '1.0.6J':
	case '1.0.6J-r1':
		$tbl_site_htmlsnippets = $modx->getFullTableName('site_htmlsnippets');
		$rs = $modx->db->select('id',$tbl_site_htmlsnippets,"name='ログイン画面'");
		if(0 < $modx->db->getRecordCount($rs))
		{
			$modx->db->update('published=0',$tbl_site_htmlsnippets,"name='ログイン画面'");
		}
		$rs = $modx->db->select('id',$tbl_site_htmlsnippets,"name='ダッシュボード'");
		if(0 < $modx->db->getRecordCount($rs))
		{
			$modx->db->update('published=0',$tbl_site_htmlsnippets,"name='ダッシュボード'");
		}
		break;
}

return $default_config;
