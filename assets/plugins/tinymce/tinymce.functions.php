<?php
//TinyMCE RichText Editor Plugin v3.2.7

// getTinyMCESettings function
if (!function_exists('getTinyMCESettings'))
{
	function getTinyMCESettings($_lang,
	                            $path,
	                            $manager_language='english',
	                            $use_editor,
	                            $theme,
	                            $css,
	                            $plugins,
	                            $buttons1,
	                            $buttons2,
	                            $buttons3,
	                            $buttons4,
	                            $displayStyle,
	                            $action)
	{
		// language settings
		if (! @include_once($path.'/lang/'.$manager_language.'.inc.php'))
		{
			include_once($path.'/lang/english.inc.php');
		}

		if($action == 11 || $action == 12)
		{
			$themeOptions .= '					<option value="">' . $_lang['tinymce_theme_global_settings'] . '</option>' . PHP_EOL;
		}
		$arrThemes[] = array('simple',  $_lang['tinymce_theme_simple']);
		$arrThemes[] = array('editor',  $_lang['tinymce_theme_editor']);
		$arrThemes[] = array('creative',$_lang['tinymce_theme_creative']);
		$arrThemes[] = array('logic',   $_lang['tinymce_theme_logic']);
		$arrThemes[] = array('advanced',$_lang['tinymce_theme_advanced']);
		$arrThemes[] = array('custom',  $_lang['tinymce_theme_custom']);
		$arrThemesCount = count($arrThemes);
		for ($i=0;$i<$arrThemesCount;$i++)
		{
			$themeOptions .= '					<option value="' . $arrThemes[$i][0] . '"' . ($arrThemes[$i][0] == $theme ? ' selected="selected"' : '') . '>' . $arrThemes[$i][1] . '</option>' . PHP_EOL;
		}

		$display = $use_editor==1 ? $displayStyle : 'none';
		$css = isset($css) ? htmlspecialchars($css) : "";

		$str  = '<table id="editorRow_TinyMCE" style="width:inherit;" border="0" cellspacing="0" cellpadding="3">' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td colspan="2" class="warning" style="color:#707070; background-color:#eeeeee"><h4>' . $_lang["tinymce_settings"] . '</h4></td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td nowrap class="warning"><b>' . $_lang["tinymce_editor_theme_title"] . '</b></td>' . PHP_EOL;
		$str .= '    <td>' . PHP_EOL;
		$str .= '    <select name="tinymce_editor_theme">' . PHP_EOL;
		$str .= $themeOptions . PHP_EOL;
		$str .= '	</select>' . PHP_EOL;
		$str .= '	</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td width="200">&nbsp;</td>' . PHP_EOL;
		$str .= '    <td class="comment">' . $_lang["tinymce_editor_theme_message"] . '</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td colspan="2"><div class="split"></div></td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td nowrap class="warning"><b>' . $_lang["tinymce_editor_custom_plugins_title"] . '</b></td>' . PHP_EOL;
		$str .= '	<td><input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_custom_plugins" value="' . $plugins . '" />' . PHP_EOL;
		$str .= '	</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td width="200">&nbsp;</td>' . PHP_EOL;
		$str .= '	<td class="comment">' . $_lang["tinymce_editor_custom_plugins_message"] . '</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td colspan="2"><div class="split"></div></td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td nowrap class="warning" valign="top"><b>' . $_lang["tinymce_editor_custom_buttons_title"] . '</b></td>' . PHP_EOL;
		$str .= '	<td>' . PHP_EOL;
		$str .= '	Row 1: <input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_custom_buttons1" value="' . $buttons1 . '" /><br/>' . PHP_EOL;
		$str .= '	Row 2: <input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_custom_buttons2" value="' . $buttons2 . '" /><br/>' . PHP_EOL;
		$str .= '	Row 3: <input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_custom_buttons3" value="' . $buttons3 . '" /><br/>' . PHP_EOL;
		$str .= '	Row 4: <input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_custom_buttons4" value="' . $buttons4 . '" />' . PHP_EOL;
		$str .= '	</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td width="200">&nbsp;</td>' . PHP_EOL;
		$str .= '	<td class="comment">' . $_lang["tinymce_editor_custom_buttons_message"] . '</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '    <td colspan="2"><div class="split"></div></td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td nowrap class="warning"><b>' . $_lang["tinymce_editor_css_selectors_title"] . '</b></td>' . PHP_EOL;
		$str .= '	<td><input onChange="documentDirty=true;" type="text" maxlength="65000" style="width: 300px;" name="tinymce_css_selectors" value="' . $css . '" />' . PHP_EOL;
		$str .= '	</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '  <tr class="row1" style="display: ' . $display . ';">' . PHP_EOL;
		$str .= '	<td width="200">&nbsp;</td>' . PHP_EOL;
		$str .= '	<td class="comment">' . $_lang["tinymce_editor_css_selectors_message"] . '</td>' . PHP_EOL;
		$str .= '  </tr>' . PHP_EOL;
		$str .= '</table>' . PHP_EOL;
		$str = preg_replace('/(.+)/', "\t\t$1", $str);
		return $str;
	}
}



// getTinyMCEScript function
if (!function_exists('getTinyMCEScript'))
{
	function getTinyMCEScript($elmList,
	                          $theme,
	                          $width,
	                          $height,
	                          $language='en',
	                          $frontend,
	                          $base_url,
	                          $plugins,
	                          $buttons1,
	                          $buttons2,
	                          $buttons3,
	                          $buttons4,
	                          $disabledButtons,
	                          $blockFormats,
	                          $entity_encoding,
	                          $entities,
	                          $pathoptions,
	                          $cleanup,
	                          $resizing,
	                          $css_path,
	                          $css_selectors,
	                          $use_browser,
	                          $toolbar_align,
	                          $advimage_styles,
	                          $advlink_styles,
	                          $linklist,
	                          $customparams,
	                          $site_url,
	                          $tinyURL,
	                          $webuser)
	{

		switch($theme)
		{
		case 'simple':
			$plugins  = "emotions,safari,advimage,advlink,paste,contextmenu";
			$buttons1 = "undo,redo,|,bold,strikethrough,|,justifyleft,justifycenter,justifyright,|,link,unlink,image,emotions,|,hr,|,help";
			$buttons2 = "";
		    break;
		case 'editor':
			$blockFormats = "p,h2,h3,h4,h5,h6,div,blockquote,code,pre,address";
			$plugins  = "safari,style,fullscreen,advimage,paste,advlink,media,contextmenu,table";
			$buttons1 = "undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,styleselect,fontsizeselect,code,|,fullscreen,help";
			$buttons2 = "image,media,link,unlink,anchor,|,bullist,numlist,|,blockquote,outdent,indent,|,justifyleft,justifycenter,justifyright,|,table,|,hr,|,styleprops,removeformat,|,pastetext,pasteword";
			$buttons3 = "";
			$buttons4 = "";
		    break;
		case 'creative':
			$blockFormats = "p,h2,h3,h4,h5,h6,div,blockquote,code,pre,address";
			$plugins = "layer,safari,style,fullscreen,advimage,advhr,paste,advlink,media,contextmenu,table";
			$buttons1 = "undo,undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,styleselect,fontsizeselect,code";
			$buttons2 = "image,media,link,unlink,anchor,|,bullist,numlist,|,blockquote,outdent,indent,|,justifyleft,justifycenter,justifyright,|,advhr,|,styleprops,removeformat,|,pastetext,pasteword";
			$buttons3 = "insertlayer,absolute,moveforward,movebackward,|,tablecontrols,|,fullscreen,help";
		    break;
		case 'logic':
			$blockFormats = "p,h2,h3,h4,h5,h6,div,blockquote,code,pre,address";
			$plugins = "xhtmlxtras,safari,style,fullscreen,advimage,paste,advlink,media,contextmenu,table";
			$buttons1 = "undo,redo,|,bold,forecolor,backcolor,strikethrough,formatselect,styleselect,fontsizeselect,code,|,fullscreen,help";
			$buttons2 = "image,media,link,unlink,anchor,|,bullist,numlist,|,blockquote,outdent,indent,|,justifyleft,justifycenter,justifyright,|,table,|,hr,|,styleprops,removeformat,|,pastetext,pasteword";
			$buttons3 = "charmap,sup,sub,|,cite,ins,del,abbr,acronym,attribs";
		    break;
		}
		
		$str  = '<script language="javascript" type="text/javascript" src="' . $tinyURL . '/jscripts/tiny_mce/tiny_mce.js"></script>' . PHP_EOL;
		$str .= '<script language="javascript" type="text/javascript" src="' . $tinyURL . '/xconfig.js"></script>' . PHP_EOL;
		$init = buildTinymceInit($elmList,
	                          $theme,
	                          $width,
	                          $height,
	                          $language,
	                          $frontend,
	                          $base_url,
	                          $plugins,
	                          $buttons1,
	                          $buttons2,
	                          $buttons3,
	                          $buttons4,
	                          $disabledButtons,
	                          $blockFormats,
	                          $entity_encoding,
	                          $entities,
	                          $pathoptions,
	                          $cleanup,
	                          $resizing,
	                          $css_path,
	                          $css_selectors,
	                          $use_browser,
	                          $toolbar_align,
	                          $advimage_styles,
	                          $advlink_styles,
	                          $linklist,
	                          $customparams,
	                          $site_url,
	                          $tinyURL,
	                          $webuser);
		$init = wrap_js_block($init);
		$str .= $init . PHP_EOL;
		
		$str .= wrap_js_block(buildTinyCallback($base_url, $tinyURL)) . PHP_EOL;
		
		$str2  = 'function myCustomOnChangeHandler() {' . PHP_EOL;
		$str2 .= '	documentDirty = true;' . PHP_EOL;
		$str2 .= '}' . PHP_EOL;
		$str2 = wrap_js_block($str2) . PHP_EOL;
		$str .= $str2 . PHP_EOL;
		return $str;
	}

	function buildTinymceInit($elmList,
	                          $theme,
	                          $width = '100%',
	                          $height = '400px',
	                          $language = 'en',
	                          $frontend,
	                          $base_url,
	                          $plugins,
	                          $buttons1,
	                          $buttons2,
	                          $buttons3,
	                          $buttons4,
	                          $disabledButtons,
	                          $blockFormats,
	                          $entity_encoding,
	                          $entities,
	                          $pathoptions,
	                          $cleanup,
	                          $resizing,
	                          $css_path,
	                          $css_selectors,
	                          $use_browser,
	                          $toolbar_align,
	                          $advimage_styles,
	                          $advlink_styles,
	                          $linklist,
	                          $customparams,
	                          $site_url,
	                          $tinyURL,
	                          $webuser)
	{
		// Build init options
		$tinymceInit['theme']                      = addQuote('advanced');
		$tinymceInit['mode']                       = addQuote('exact');
		if($width)
			{$tinymceInit['width']                 = addQuote(str_replace("px", "", $width));}
		if($height)
			{$tinymceInit['height']                = addQuote(str_replace("px", "", $height));}
		$tinymceInit['language']                   = addQuote($language);
		if($elmList)
			{ $tinymceInit['elements']             = addQuote($elmList); }
		switch($pathoptions)
		{
			case "docrelative":
				$tinymceInit['relative_urls']      = 'true';
				$tinymceInit['document_base_url']  = addQuote($site_url);
			break;
			case "rootrelative":
				$tinymceInit['relative_urls']      = 'false';
				$tinymceInit['remove_script_host'] = 'true';
				$tinymceInit['document_base_url']  = addQuote($site_url);
			break;
			case "fullpathurl":
				$tinymceInit['relative_urls']      = 'false';
				$tinymceInit['remove_script_host'] = 'false';
				$tinymceInit['document_base_url']  = addQuote($site_url);
			break;
			default:
				$tinymceInit['convert_urls']       = 'false';
		}
		$tinymceInit['valid_elements']             = 'tinymce_valid_elements';
		$tinymceInit['extended_valid_elements']    = 'tinymce_extended_valid_elements';
		$tinymceInit['invalid_elements']           = 'tinymce_invalid_elements';
		if($css_path)
			{ $tinymceInit['content_css']          = addQuote($css_path); }
		$tinymceInit['entity_encoding']            = addQuote($entity_encoding);
		if($entity_encoding == "named" && !empty($entities))
			{ $tinymceInit['entities']             = addQuote($entities); }
		$tinymceInit['cleanup'] = ($cleanup == 'enabled' || empty($cleanup)) ? 'true' : 'false';
		$tinymceInit['apply_source_formatting']    = 'true';
		$tinymceInit['remove_linebreaks']          = 'false';
		$tinymceInit['convert_fonts_to_spans']     = 'true';
		if(($frontend=='false' || ($frontend=='true' && $webuser)) && $use_browser==1)
			{ $tinymceInit['file_browser_callback']      = addQuote('myFileBrowser'); }
		if($frontend=='false' && ($linklist == 'enabled'))
			{ $tinymceInit['external_link_list_url']     = addQuote($tinyURL . '/tinymce.linklist.php'); }
		if(isset($blockFormats))
			{$tinymceInit['theme_advanced_blockformats'] = addQuote($blockFormats);}
		if($css_selectors)
			{ $tinymceInit['theme_advanced_styles']      = addQuote($css_selectors);}
		$tinymceInit['plugins'] = addQuote($plugins);
		$tinymceInit['theme_advanced_buttons1']          = addQuote($buttons1);
		$tinymceInit['theme_advanced_buttons2']          = addQuote($buttons2);
		$tinymceInit['theme_advanced_buttons3']          = addQuote($buttons3);
		$tinymceInit['theme_advanced_buttons4']          = addQuote($buttons4);
		$tinymceInit['theme_advanced_toolbar_location']  = addQuote('top');
		$tinymceInit['theme_advanced_toolbar_align']     = addQuote(($toolbar_align == 'rtl') ? 'right' : 'left');
		$tinymceInit['theme_advanced_path_location']     = addQuote('bottom');
		$tinymceInit['theme_advanced_disable']           = addQuote($disabledButtons);
		if($resizing)
			{ $tinymceInit['theme_advanced_resizing']    = 'true'; }
		$tinymceInit['theme_advanced_resize_horizontal'] = 'false';
		if($advimage_styles)
			{ $tinymceInit['advimage_styles']            = addQuote($advimage_styles); }
		if($advlink_styles)
			{ $tinymceInit['advlink_styles']             = addQuote($advlink_styles); }
		$tinymceInit['plugin_insertdate_dateFormat']     = addQuote('%Y-%m-%d');
		$tinymceInit['plugin_insertdate_timeFormat']     = addQuote('%H:%M:%S');
		$tinymceInit['button_tile_map'] = 'false';
		if(!empty($customparams))
		{
		    $params = explode(",",$customparams);
		    foreach($params as $value)
			{
				list($param1, $param2) = explode(':', $value);
				$param1 = trim($param1);
				$param2 = trim($param2);
	   			if(!empty($param1) && !empty($param2))
	   				$tinymceInit[$param1] = $param2;
			}
		}
		if($frontend=='false')
			{ $tinymceInit['onchange_callback'] = addQuote('myCustomOnChangeHandler'); }
		$str = join_assoc(' : ', ',' . PHP_EOL, $tinymceInit);
		$str  = '	tinyMCE.init({' . PHP_EOL . $str . PHP_EOL . '});';
		return $str;
	}
	
	function buildTinyCallback($base_url, $tinyURL)
	{
		$str  = 'function myFileBrowser (field_name, url, type, win) {' . PHP_EOL;
		$str .= '    if (type == "media") {type = win.document.getElementById("media_type").value;}' . PHP_EOL;
		$str .= '	var cmsURL = "' . $base_url . 'manager/media/browser/mcpuk/browser.php?Connector=';
		$str .= $base_url . 'manager/media/browser/mcpuk/connectors/php/connector.php&ServerPath=';
		$str .= $base_url . '&editor=tinymce&editorpath=' . $tinyURL . '";' . PHP_EOL;
		$str .= '	switch (type) {' . PHP_EOL;
		$str .= '		case "image":' . PHP_EOL;
		$str .= '			type = "images";' . PHP_EOL;
		$str .= '			break;' . PHP_EOL;
		$str .= '		case "media":' . PHP_EOL;
		$str .= '		case "qt":' . PHP_EOL;
		$str .= '		case "wmp":' . PHP_EOL;
		$str .= '		case "rmp":' . PHP_EOL;
		$str .= '			type = "media";' . PHP_EOL;
		$str .= '			break;' . PHP_EOL;
		$str .= '		case "shockwave":' . PHP_EOL;
		$str .= '		case "flash":' . PHP_EOL;
		$str .= '			type = "flash";' . PHP_EOL;
		$str .= '			break;' . PHP_EOL;
		$str .= '		case "file":' . PHP_EOL;
		$str .= '			type = "files";' . PHP_EOL;
		$str .= '			break;' . PHP_EOL;
		$str .= '		default:' . PHP_EOL;
		$str .= '			return false;' . PHP_EOL;
		$str .= '	}' . PHP_EOL;
		$str .= '	if (cmsURL.indexOf("?") < 0) {' . PHP_EOL;
		$str .= '	    //add the type as the only query parameter' . PHP_EOL;
		$str .= '	    cmsURL = cmsURL + "?type=" + type;' . PHP_EOL;
		$str .= '	}' . PHP_EOL;
		$str .= '	else {' . PHP_EOL;
		$str .= '	    //add the type as an additional query parameter' . PHP_EOL;
		$str .= '	    // (PHP session ID is now included if there is one at all)' . PHP_EOL;
		$str .= '	    cmsURL = cmsURL + "&type=" + type;' . PHP_EOL;
		$str .= '	}' . PHP_EOL;
		$str .= '' . PHP_EOL;
		$str .= '	var windowManager = tinyMCE.activeEditor.windowManager.open({' . PHP_EOL;
		$str .= '	    file : cmsURL,' . PHP_EOL;
		$str .= '	    width : screen.width * 0.7,  // Your dimensions may differ - toy around with them!' . PHP_EOL;
		$str .= '	    height : screen.height * 0.7,' . PHP_EOL;
		$str .= '	    resizable : "yes",' . PHP_EOL;
		$str .= '	    inline : "yes",  // This parameter only has an effect if you use the inlinepopups plugin!' . PHP_EOL;
		$str .= '	    close_previous : "no"' . PHP_EOL;
		$str .= '	}, {' . PHP_EOL;
		$str .= '	    window : win,' . PHP_EOL;
		$str .= '	    input : field_name' . PHP_EOL;
		$str .= '	});' . PHP_EOL;
		$str .= '	if (window.focus) {windowManager.focus()}' . PHP_EOL;
		$str .= '	return false;' . PHP_EOL;
		$str .= '}' . PHP_EOL;
		$str = preg_replace('/(.+)/', "\t$1", $str);
		return $str;
	}

	function addQuote($value)
	{
		return '"' . $value . '"';
	}
	
	function wrap_js_block($block)
	{
		$result  = '<script language="javascript" type="text/javascript">' . PHP_EOL;
		$result .= $block;
		$result .= '</script>' . PHP_EOL;
		return $result;
	}
	
	function join_assoc( $inner_glue, $outer_glue, $array, $skip_empty=false)
	{
		$output=array();
		foreach ($array as $key => $item)
		{
			if (!$skip_empty || $item)
			{
				$output[] = "\t\t" . $key. $inner_glue. $item;
			}
		}
		return join($outer_glue, $output);
	}
}
?>