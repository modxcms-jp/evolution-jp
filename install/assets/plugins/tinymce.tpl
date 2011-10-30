//<?php
/**
 * TinyMCE Rich Text Editor
 * 
 * TinyMCE リッチテキストエディタ
 *
 * @category 	plugin
 * @version 	3.2.7p1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &customparams=Custom Parameters;textarea;content_css : "assets/plugins/tinymce/style/content.css",popup_css_add : "assets/plugins/tinymce/style/popup_add.css",accessibility_warnings : false,valid_elements : "*[*]",body_class:"content", &tinyFormats=Block Formats;text;p,h2,h3,h4,h5,h6,div,blockquote,code,pre &entity_encoding=Entity Encoding;list;named,numeric,raw;named &entities=Entities;text; &tinyPathOptions=Path Options;list;rootrelative,docrelative,fullpathurl;rootrelative &tinyCleanup=Cleanup;list;enabled,disabled;enabled &tinyResizing=Advanced Resizing;list;true,false;true &advimage_styles=Advanced Image Styles;text; &advlink_styles=Advanced Link Styles;text; &disabledButtons=Disabled Buttons;text; &tinyLinkList=Link List;list;enabled,disabled;enabled &webtheme=Web Theme;list;simple,editor,creative,custom;simple &webPlugins=Web Plugins;text;style,advimage,advlink,searchreplace,print,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,visualchars,media &webButtons1=Web Buttons 1;text;undo,redo,selectall,separator,pastetext,pasteword,separator,search,replace,separator,nonbreaking,hr,charmap,separator,image,link,unlink,anchor,media,separator,cleanup,removeformat,separator,fullscreen,print,code,help &webButtons2=Web Buttons 2;text;bold,italic,underline,strikethrough,sub,sup,separator,separator,blockquote,bullist,numlist,outdent,indent,separator,justifyleft,justifycenter,justifyright,justifyfull,separator,styleselect,formatselect,separator,styleprops &webButtons3=Web Buttons 3;text; &webButtons4=Web Buttons 4;text; &webAlign=Web Toolbar Alignment;list;ltr,rtl;ltr
 * @internal	@events OnRichTextEditorRegister,OnRichTextEditorInit,OnInterfaceSettingsRender 
 * @internal	@modx_category Manager and Admin
 * @internal    @legacy_names TinyMCE
 */


/*
 * Written By Jeff Whitfield
 * and yama
 */

// Set the name of the plugin folder
$pluginfolder = "tinymce";

include_once MODX_BASE_PATH .'assets/plugins/'.$pluginfolder.'/tinymce.lang.php';
include_once MODX_BASE_PATH .'assets/plugins/'.$pluginfolder.'/tinymce.functions.php';

// Set path and base setting variables
if(!isset($tinyPath)) { 
	global $tinyPath, $tinyURL;
	$tinyPath = MODX_BASE_PATH .'assets/plugins/'.$pluginfolder; 
	$tinyURL = MODX_BASE_URL .'assets/plugins/'.$pluginfolder; 
}
$base_url = MODX_BASE_URL;
$displayStyle = ( ($_SESSION['browser']=='mz') || ($_SESSION['browser']=='op') || ($_SESSION['browser']=='sf')) ? "table-row" : "block" ;

// Handle event
$e = &$modx->Event; 
switch ($e->name) { 
	case "OnRichTextEditorRegister": // register only for backend
		$e->output("TinyMCE");
		break;

	case "OnRichTextEditorInit": 
		if($editor=="TinyMCE") {
			$elementList = implode(",", $elements);
			if(isset($forfrontend)||$modx->isFrontend()){
				$frontend = 'true';
				$frontend_language = isset($modx->config['fe_editor_lang']) ? $modx->config['fe_editor_lang']:"";
				$tinymce_language = getTinyMCELang($frontend_language);
				$webuser = (isset($modx->config['rb_webuser']) ? $modx->config['rb_webuser'] : null);
				
				$html = getTinyMCEScript($elementList,
				                         $webtheme,
				                         $width,
				                         $height,
				                         $tinymce_language,
				                         $frontend,
				                         $base_url,
				                         $webPlugins,
				                         $webButtons1,
				                         $webButtons2,
				                         $webButtons3,
				                         $webButtons4,
				                         $disabledButtons,
				                         $tinyFormats,
				                         $entity_encoding,
				                         $entities,
				                         $tinyPathOptions,
				                         $tinyCleanup,
				                         $tinyResizing,
				                         $modx->config['editor_css_path'],
				                         $modx->config['tinymce_css_selectors'],
				                         $modx->config['use_browser'],
				                         $webAlign,
				                         null,
				                         null,
				                         $tinyLinkList,
				                         $customparams,
				                         $site_url,
				                         $tinyURL,
				                         $webuser);
			}
			else
			{
				$frontend = 'false';
				$manager_language = $modx->config['manager_language'];
				$tinymce_language = getTinyMCELang($manager_language);
				$html = getTinyMCEScript($elementList,
				                         $modx->config['tinymce_editor_theme'],
				                         $width,
				                         $height,
				                         $tinymce_language,
				                         $frontend,
				                         MODX_BASE_URL,
				                         $modx->config['tinymce_custom_plugins'],
				                         $modx->config['tinymce_custom_buttons1'],
				                         $modx->config['tinymce_custom_buttons2'],
				                         $modx->config['tinymce_custom_buttons3'],
				                         $modx->config['tinymce_custom_buttons4'],
				                         $disabledButtons,
				                         $tinyFormats,
				                         $entity_encoding,
				                         $entities,
				                         $tinyPathOptions,
				                         $tinyCleanup,
				                         $tinyResizing,
				                         $modx->config['editor_css_path'],
				                         $modx->config['tinymce_css_selectors'],
				                         $modx->config['use_browser'],
				                         $modx->config['manager_direction'],
				                         $advimage_styles,
				                         $advlink_styles,
				                         $tinyLinkList,
				                         $customparams,
				                         MODX_SITE_URL,
				                         $tinyURL,
				                         null);
			}
			$e->output($html);
		}		
		break;

	case "OnInterfaceSettingsRender":
		global $usersettings,$settings;
		$action = $modx->manager->action;
		switch ($action) {
    		case 11:
        		$tinysettings = "";
        		break;
    		case 12:
        		$tinysettings = $usersettings;
        		break;
    		default:
        		$tinysettings = $settings;
        		break;
    	}
		$tinymce_editor_theme = $tinysettings['tinymce_editor_theme'];
		$tinymce_css_selectors = $tinysettings['tinymce_css_selectors'];
		$tinymce_custom_plugins = $tinysettings['tinymce_custom_plugins'];
		$tinymce_custom_buttons1 = $tinysettings['tinymce_custom_buttons1'];
		$tinymce_custom_buttons2 = $tinysettings['tinymce_custom_buttons2'];
		$tinymce_custom_buttons3 = $tinysettings['tinymce_custom_buttons3'];
		$tinymce_custom_buttons4 = $tinysettings['tinymce_custom_buttons4'];
		$manager_language = $modx->config['manager_language'];
		$html = getTinyMCESettings($_lang,
		                           $tinyPath,
		                           $modx->config['manager_language'],
		                           $modx->config['use_editor'],
		                           $tinymce_editor_theme,
		                           $tinymce_css_selectors,
		                           $tinymce_custom_plugins,
		                           $tinymce_custom_buttons1,
		                           $tinymce_custom_buttons2,
		                           $tinymce_custom_buttons3,
		                           $tinymce_custom_buttons4,
		                           $displayStyle,
		                           $action);
		$e->output($html);
		break;

   default :    
      return; // stop here - this is very important. 
      break; 
}
