<?php
/**
 * Filename:       media/style/$modx->config['manager_theme']/style.php
 * Function:       Manager style variables for images and icons.
 * Encoding:       UTF-8
 * Credit:         icons by Mark James of FamFamFam http://www.famfamfam.com/lab/icons/
 * Date:           18-Mar-2010
 * Version:        1.1
 * MODX version:   1.0.6-
*/

$tab_your_info   = 1;
$tab_online      = 1;

$iconResources   = 1;
$iconNewDoc      = 1;
$iconSearch      = 1;
$iconMessage     = 1;

$iconElements    = 1;
$iconSettings    = 1;
$iconFileManager = 1;
$iconEventLog    = 1;
$iconSysInfo     = 1;
$iconHelp        = 1;

include_once(dirname(__FILE__) . '/welcome.php');
if(is_file(dirname(__FILE__) . '/config.php')) include(dirname(__FILE__) . '/config.php');

if($tab_your_info==1)   tabYourInfo();
if($tab_online==1)      tabOnlineUser();

if($iconResources==1)   iconResources();
if($iconNewDoc==1)      iconNewDoc();
if($iconSearch==1)      iconSearch();
if($iconMessage==1)     iconMessage();

if($iconElements==1)    iconElements();
if($iconFileManager==1) iconFileManager();

if($iconSettings==1)    iconSettings();
if($iconEventLog==1)    iconEventLog();
if($iconSysInfo==1)     iconSysInfo();
if($iconHelp==1)        iconHelp();

$style_path = 'media/style/' . $modx->config['manager_theme'] . '/';

// Tree Menu Toolbar
$icon_path = $style_path . 'images/icons/';
$_style['add_doc_tree']             = '<img src="'.$icon_path.'page_add.png" />';
$_style['add_weblink_tree']         = '<img src="'.$icon_path.'link_add.png" />';
$_style['collapse_tree']            = '<img src="'.$icon_path.'arrow_up.png" />';
$_style['empty_recycle_bin']        = '<img src="'.$icon_path.'trash_full.png" />';
$_style['empty_recycle_bin_empty']  = '<img src="'.$icon_path.'trash.png" />';
$_style['expand_tree']              = '<img src="'.$icon_path.'arrow_down.png" />';
$_style['hide_tree']                = '<img src="'.$icon_path.'application_side_contract.png" />';
$_style['refresh_tree']             = '<img src="'.$icon_path.'refresh.png" />';
$_style['show_tree']                = $icon_path.'application_side_expand.png';
$_style['sort_tree']                = '<img src="'.$icon_path.'sort.png" />';


// Tree Icons
$tree_path = $style_path . 'images/tree/';
$_style['tree_blanknode']           = $tree_path.'empty.gif';
$_style['tree_deletedpage']         = $tree_path.'deletedpage.gif';
$_style['tree_folder']              = $tree_path.'folder.png'; /* folder.png */
$_style['tree_folderopen']          = $tree_path.'folderopen.png'; /* folder-open.png */
$_style['tree_folder_secure']       = $tree_path.'application_double_key.png';
$_style['tree_folderopen_secure']   = $tree_path.'application_double_key.png';
$_style['tree_globe']               = $tree_path.'globe.gif';
$_style['tree_linkgo']              = $tree_path.'link_go.png';
$_style['tree_minusnode']           = $tree_path.'minusnode.gif';
$_style['tree_page']                = $tree_path.'application.gif';
$_style['tree_page_home']           = $tree_path.'application_home.gif';
$_style['tree_page_404']            = $tree_path.'application_404.gif';
$_style['tree_page_hourglass']      = $tree_path.'application_hourglass.gif';
$_style['tree_page_info']           = $tree_path.'application_info.gif';
$_style['tree_page_blank']          = $tree_path.'application.gif';
$_style['tree_page_css']            = $tree_path.'application_css.gif';
$_style['tree_page_html']           = $tree_path.'page.gif';
$_style['tree_page_xml']            = $tree_path.'application_xml.gif';
$_style['tree_page_js']             = $tree_path.'application_js.gif';
$_style['tree_page_rss']            = $tree_path.'application_rss.gif';
$_style['tree_page_pdf']            = $tree_path.'application_pdf.gif';
$_style['tree_page_word']           = $tree_path.'application_word.gif';
$_style['tree_page_excel']          = $tree_path.'application_excel.gif';
$_style['tree_page_jpg']            = $tree_path.'picture.png';
$_style['tree_page_png']            = $tree_path.'picture.png';
$_style['tree_page_gif']            = $tree_path.'picture.png';
$_style['tree_plusnode']            = $tree_path.'plusnode.gif';
$_style['tree_showtree']            = '<img src="'.$tree_path.'sitemap.png" align="absmiddle" />';
$_style['tree_weblink']             = $tree_path.'link_go.png';

$_style['tree_page_secure']         = $tree_path.'application_key.gif';
$_style['tree_page_blank_secure']   = $tree_path.'application_html_secure.gif';
$_style['tree_page_css_secure']     = $tree_path.'application_css_secure.gif';
$_style['tree_page_html_secure']    = $tree_path.'application_html_secure.gif';
$_style['tree_page_xml_secure']     = $tree_path.'application_xml_secure.gif';
$_style['tree_page_js_secure']      = $tree_path.'application_js_secure.gif';
$_style['tree_page_rss_secure']     = $tree_path.'application_rss_secure.gif';
$_style['tree_page_pdf_secure']     = $tree_path.'application_pdf_secure.gif';
$_style['tree_page_word_secure']    = $tree_path.'application_word_secure.gif';
$_style['tree_page_excel_secure']   = $tree_path.'application_excel_secure.gif';


// Icons
$_style['icons_add']                = $icon_path.'add.png';
$_style['icons_cal']                = $icon_path.'cal.gif';
$_style['icons_cal_nodate']         = $icon_path.'cal_nodate.gif';
$_style['icons_cancel']             = $icon_path.'stop.png';
$_style['icons_close']              = $icon_path.'stop.png';
$_style['icons_delete']             = $icon_path.'delete.png';
$_style['icons_delete_document']    = $icon_path.'delete.png';
$_style['icons_delete_complete']    = $icon_path.'trash_full.png';
$_style['icons_resource_overview']  = $icon_path.'page_white_magnify.png';
$_style['icons_resource_duplicate'] = $icon_path.'page_white_copy.png';
$_style['icons_edit_document']      = $icon_path.'write.png';
$_style['icons_email']              = $icon_path.'email.png';
$_style['icons_folder']             = $icon_path.'folder.png';
$_style['icons_home']               = $icon_path.'home.gif';
$_style['icons_information']        = $icon_path.'information.png';
$_style['icons_loading_doc_tree']   = $icon_path.'information.png'; // top bar
$_style['icons_mail']               = $icon_path.'email.png'; // top bar
$_style['icons_message_forward']    = $icon_path.'forward.gif';
$_style['icons_message_reply']      = $icon_path.'reply.gif';
$_style['icons_modules']            = $icon_path.'32x/modules.gif';
$_style['icons_move_document']      = $icon_path.'page_white_go.png';
$_style['icons_new_document']       = $icon_path.'page_white_add.png';
$_style['icons_new_weblink']        = $icon_path.'world_link.png';
$_style['icons_preview_resource']   = $icon_path.'page_white_magnify.png';
$_style['icons_publish_document']   = $icon_path.'clock_play.png';
$_style['icons_refresh']            = $icon_path.'refresh.png'; 
$_style['icons_save']               = $icon_path.'save.png';
$_style['icons_set_parent']         = $icon_path.'stick.gif';
$_style['icons_table']              = $icon_path.'table.gif'; 
$_style['icons_undelete_resource']  = $icon_path.'b092.gif';
$_style['icons_unpublish_resource'] = $icon_path.'clock_stop.png';
$_style['icons_user']               = $icon_path.'vcard.png';
$_style['icons_view_document']      = $icon_path.'context_view.gif';
$_style['icons_weblink']            = $icon_path.'world_link.png';
$_style['icons_working']            = $icon_path.'exclamation.png'; // top bar
$_style['sort']                     = $icon_path.'sort.png';

// Indicators
$_style['icons_tooltip']            = $icon_path.'b02.gif';
$_style['icons_tooltip_over']       = $icon_path.'b02_trans.gif';

// Large Icons
$_style['icons_backup_large']       = $icon_path.'32x/backup.png';
$_style['icons_mail_large']         = $icon_path.'32x/mail.png';
$_style['icons_mail_new_large']     = $icon_path.'32x/mail_new.png';
$_style['icons_modules_large']      = $icon_path.'32x/modules.gif';
$_style['icons_resources_large']    = $icon_path.'32x/resources.png';
$_style['icons_elements_large']     = $icon_path.'32x/elements.png';
$_style['icons_security_large']     = $icon_path.'32x/users.png';
$_style['icons_webusers_large']     = $icon_path.'32x/users.png';
$_style['icons_sysinfo_large']      = $icon_path.'32x/info.png';
$_style['icons_search_large']       = $icon_path.'32x/search.png';
$_style['icons_log_large']          = $icon_path.'32x/log.png';
$_style['icons_files_large']        = $icon_path.'32x/files.png';
$_style['icons_help_large']         = $icon_path.'32x/help.png';

// Miscellaneous
$_style['ajax_loader']              = '<p>'.$_lang['loading_page'].'</p><p><img src="'.$style_path.'images/misc/ajax-loader.gif" alt="Please wait" /></p>';
$_style['tx']                       = $style_path.'images/misc/_tx_.gif';
