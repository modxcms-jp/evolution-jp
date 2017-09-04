<?php
	$site_unavailable_message_view = isset($site_unavailable_message) ? $site_unavailable_message : $_lang['siteunavailable_message_default'];
?>
<!-- Site Settings -->
<div class="tab-page" id="tabPage2">
<h2 class="tab"><?php echo $_lang["settings_site"] ?></h2>
<style type="text/css">
	table.settings {border-collapse:collapse;width:100%;}
	table.settings tr {border-bottom:1px dotted #ccc;}
	table.settings th {font-size:inherit;vertical-align:top;text-align:left;}
	table.settings th,table.settings td {padding:5px;}
	table.settings td input[type=text] {width:250px;}
</style>
<table class="settings">
<tr>
	<th><?php echo $_lang["sitestatus_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["online"], form_radio('site_status','1',$site_status=='1'));?><br />
		<?php echo wrap_label($_lang["offline"],form_radio('site_status','0',$site_status=='0'));?><br />
		<?php echo $_lang["sitestatus_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["sitename_title"] ?></th>
	<td>
		<?php echo form_text('site_name');?><br />
		<?php echo $_lang["sitename_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["site_slogan_title"] ?></th>
	<td>
		<textarea name="site_slogan" id="site_slogan" style="width:300px; height: 4em;"><?php echo $site_slogan; ?></textarea><br />
		<?php echo $_lang["site_slogan_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["site_url_title"] ?></th>
	<td>
		<?php echo form_text('site_url');?><br />
		<?php echo $modx->parseText($_lang["site_url_message"],array('MODX_SITE_URL'=>MODX_SITE_URL)) ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["base_url_title"] ?></th>
	<td>
		<?php echo form_text('base_url');?><br />
		<?php echo $modx->parseText($_lang["base_url_message"],array('MODX_BASE_URL'=>MODX_BASE_URL)) ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["charset_title"]?></th>
	<td>
		<select name="modx_charset" size="1" class="inputBox" style="width:250px;">
		<?php include(MODX_CORE_PATH . 'charsets.php'); ?>
		</select><br />
		<?php echo $_lang["charset_message"]?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["xhtml_urls_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('xhtml_urls','1',$xhtml_urls=='1'));?><br />
		<?php echo wrap_label($_lang["no"], form_radio('xhtml_urls','0',$xhtml_urls=='0'));?><br />
		<?php echo $_lang["xhtml_urls_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["sitestart_title"] ?></th>
	<td>
		<?php echo form_text('site_start',10);?><br />
		<?php echo $_lang["sitestart_message"] ?></td>
</tr>
<tr>
	<th><?php echo $_lang["errorpage_title"] ?></th>
	<td>
		<?php echo form_text('error_page',10);?><br />
		<?php echo $_lang["errorpage_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["unauthorizedpage_title"] ?></th>
	<td>
		<?php echo form_text('unauthorized_page',10);?><br />
		<?php echo $_lang["unauthorizedpage_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["siteunavailable_page_title"] ?></th>
	<td>
		<?php echo form_text('site_unavailable_page',10);?><br />
		<?php echo $_lang["siteunavailable_page_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["siteunavailable_title"] ?><br />
	<p>
		<?php echo $_lang["update_settings_from_language"]; ?>
	</p>
		<select name="reload_site_unavailable" id="reload_site_unavailable_select" onchange="confirmLangChange(this, 'siteunavailable_message_default', 'site_unavailable_message_textarea');">
			<?php echo get_lang_options('siteunavailable_message_default');?>
		</select>
	</th>
	<td>
		<textarea name="site_unavailable_message" id="site_unavailable_message_textarea" style="width:100%; height: 120px;"><?php echo $site_unavailable_message_view; ?></textarea>
		<input type="hidden" name="siteunavailable_message_default" id="siteunavailable_message_default_hidden" value="<?php echo addslashes($_lang['siteunavailable_message_default']);?>" /><br />
		<?php echo $_lang['siteunavailable_message'];?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["defaulttemplate_title"] ?></th>
	<td>
		<select name="default_template" class="inputBox" onchange="wrap=document.getElementById('template_reset_options_wrapper');if(this.options[this.selectedIndex].value != '<?php echo $default_template;?>'){wrap.style.display='block';}else{wrap.style.display='none';}" style="width:150px">
		<option value="">(blank)</option>
<?php
	$field = 't.templatename, t.id, c.category';
	$from = "[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id";
	$orderby = 'c.category, t.templatename ASC';
	$rs = $modx->db->select($field,$from,'',$orderby);
	$currentCategory = '';
	while ($row = $modx->db->getRow($rs))
	{
		$thisCategory = $row['category'];
		if($thisCategory == null)
		{
			$thisCategory = $_lang["no_category"];
		}
		if($thisCategory != $currentCategory)
		{
			if($closeOptGroup)
			{
				echo "\t\t\t\t\t</optgroup>\n";
			}
			echo "\t\t\t\t\t<optgroup label=\"{$thisCategory}\">\n";
			$closeOptGroup = true;
		}
		else
		{
			$closeOptGroup = false;
		}
		$selectedtext = $row['id'] == $default_template ? ' selected="selected"' : '';
		if ($selectedtext)
		{
			$oldTmpId = $row['id'];
			$oldTmpName = $row['templatename'];
		}
		echo "\t\t\t\t\t".'<option value="'.$row['id'].'"'.$selectedtext.'>'.$row['templatename']."</option>\n";
		$currentCategory = $thisCategory;
	}
	if($thisCategory != '')
	{
		echo "\t\t\t\t\t</optgroup>\n";
	}
?>
		</select><br />
		<div id="template_reset_options_wrapper" style="display:none;">
			<?php echo wrap_label($_lang["template_reset_all"],form_radio('reset_template','1'));?><br />
			<?php echo wrap_label(sprintf($_lang["template_reset_specific"],$oldTmpName),form_radio('reset_template','2'));?>
		</div>
		<input type="hidden" name="old_template" value="<?php echo $oldTmpId; ?>" />
		<?php echo $_lang["defaulttemplate_message"] ?>
	</td>
</tr>
<tr>
<th><?php echo $_lang["defaulttemplate_logic_title"];?></th>
	<td>
<?php echo wrap_label($_lang["defaulttemplate_logic_system_message"],form_radio('auto_template_logic','system',$auto_template_logic == 'system'));?><br />
<?php echo wrap_label($_lang["defaulttemplate_logic_parent_message"],form_radio('auto_template_logic','parent',$auto_template_logic == 'parent'));?><br />
<?php echo wrap_label($_lang["defaulttemplate_logic_sibling_message"],form_radio('auto_template_logic','sibling',$auto_template_logic == 'sibling'));?><br />
	<?php echo $_lang["defaulttemplate_logic_general_message"];?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['setting_cache_type'] ?></th>
	<td>
		<?php echo wrap_label($_lang['mutate_settings.dynamic.php1'],form_radio('cache_type','1',$cache_type=='1'));?><br />
		<?php echo wrap_label($_lang['mutate_settings.dynamic.php2'],form_radio('cache_type','2',$cache_type=='2'));?><br />
		<?php echo wrap_label($_lang['mutate_settings.dynamic.php3'],form_radio('cache_type','0',$cache_type=='0'));?><br />
		<?php echo $_lang["setting_cache_type_desc"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['setting_disable_cache_at_login'] ?></th>
	<td>
		<?php echo wrap_label($_lang['enabled'] ,form_radio('disable_cache_at_login','1',$disable_cache_at_login=='1'));?><br />
		<?php echo wrap_label($_lang['disabled'],form_radio('disable_cache_at_login','0',$disable_cache_at_login=='0'));?><br />
		<?php echo $_lang["setting_disable_cache_at_login_desc"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['setting_individual_cache'] ?></th>
	<td>
		<?php echo wrap_label($_lang['enabled'] ,form_radio('individual_cache','1',$individual_cache=='1'));?><br />
		<?php echo wrap_label($_lang['disabled'],form_radio('individual_cache','0',$individual_cache=='0'));?><br />
		<?php echo $_lang["setting_individual_cache_desc"] ?>
	</td>
</tr>
<tr>
	<th>旧式のキャッシュ機構</th>
	<td>
		<?php echo wrap_label($_lang['enabled'] ,form_radio('legacy_cache','1',$legacy_cache=='1'));?><br />
		<?php echo wrap_label($_lang['disabled'],form_radio('legacy_cache','0',$legacy_cache=='0'));?><br />
		古いスニペット・プラグインは<a href="https://www.google.co.jp/search?q=modx+aliasListing+ddocumentMap+ocumentListing" target="_blank">旧式のキャッシュ機構</a>が有効でないと動作しないことがあります。その場合はこの設定を有効にしてください。このキャッシュ機構はサイトの規模が大きくなると負荷が高くなるため注意が必要です。
	</td>
</tr>
<tr>
	<th><?php echo $_lang['setting_conditional_get'] ?></th>
	<td>
		<?php echo wrap_label($_lang['enabled'] ,form_radio('conditional_get','1',$conditional_get=='1'));?><br />
		<?php echo wrap_label($_lang['disabled'],form_radio('conditional_get','0',$conditional_get=='0'));?><br />
		<?php echo $_lang["setting_conditional_get_desc"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["defaultcache_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('cache_default','1',$cache_default=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('cache_default','0',$cache_default=='0'));?><br />
		<?php echo $_lang["defaultcache_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["defaultpublish_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('publish_default','1',$publish_default=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('publish_default','0',$publish_default=='0'));?><br />
		<?php echo $_lang["defaultpublish_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["defaultsearch_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('search_default','1',$search_default=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('search_default','0',$search_default=='0'));?><br />
		<?php echo $_lang["defaultsearch_message"] ?></td>
</tr>
<tr>
	<th><?php echo $_lang["defaultmenuindex_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('auto_menuindex','1',$auto_menuindex=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('auto_menuindex','0',$auto_menuindex=='0'));?><br />
		<?php echo $_lang["defaultmenuindex_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["docid_incrmnt_method_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["docid_incrmnt_method_0"],form_radio('docid_incrmnt_method','0', $docid_incrmnt_method=='0'));?><br />
		<?php echo wrap_label($_lang["docid_incrmnt_method_1"],form_radio('docid_incrmnt_method','1', $docid_incrmnt_method=='1'));?><br />
		<?php echo wrap_label($_lang["docid_incrmnt_method_2"],form_radio('docid_incrmnt_method','2', $docid_incrmnt_method=='2'));?><br />
		<?php echo $_lang["docid_incrmnt_method_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["custom_contenttype_title"] ?></th>
	<td>
		<?php echo form_text('txt_custom_contenttype',100,'style="width:200px;"');?>
		<input type="button" value="<?php echo $_lang["add"]; ?>" onclick='addContentType()' /><br />
		<table>
			<tr>
			<td valign="top">
			<select name="lst_custom_contenttype" style="width:200px;" size="5">
<?php
	foreach(explode(',',$custom_contenttype) as $v)
	{
		echo '<option value="'.$v.'">'.$v."</option>\n";
	}
?>
			</select>
			<input name="custom_contenttype" type="hidden" value="<?php echo $custom_contenttype; ?>" />
			</td>
			<td valign="top">
				&nbsp;<input name="removecontenttype" type="button" value="<?php echo $_lang["remove"]; ?>" onclick='removeContentType()' />
			</td>
			</tr>
		</table><br />
		<?php echo $_lang["custom_contenttype_message"] ?>
	</td>
</tr>

<tr>
	<th><?php echo $_lang["serveroffset_title"] ?></th>
	<td>
		<select name="server_offset_time" size="1" class="inputBox">
<?php
	for($i=-24; $i<25; $i++)
	{
		$seconds = $i*60*60;
		$selectedtext = $seconds==$server_offset_time ? "selected='selected'" : "" ;
		echo '<option value="' . $seconds . '" ' . $selectedtext . '>' . $i . "</option>\n";
	}
?>
		</select><br />
		<?php printf($_lang["serveroffset_message"], strftime('%H:%M:%S', time()), strftime('%H:%M:%S', time()+$server_offset_time)); ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["server_protocol_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["server_protocol_http"],form_radio('server_protocol','http', $server_protocol=='http'));?><br />
		<?php echo wrap_label($_lang["server_protocol_https"],form_radio('server_protocol','https', $server_protocol=='https'));?><br />
		<?php echo $_lang["server_protocol_message"] ?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["track_visitors_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('track_visitors','1',$track_visitors=='1'));?><br />
		<?php echo wrap_label($_lang["no"], form_radio('track_visitors','0',$track_visitors=='0'));?><br />
		<?php echo $_lang["track_visitors_message"] ?>
	</td>
</tr>
<tr class="row1" style="border-bottom:none;">
	<td colspan="2" style="padding:0;">
<?php
	// invoke OnSiteSettingsRender event
	$evtOut = $modx->invokeEvent("OnSiteSettingsRender");
	if(is_array($evtOut)) echo implode("",$evtOut);
?>
	</td>
</tr>
</table>
</div>
