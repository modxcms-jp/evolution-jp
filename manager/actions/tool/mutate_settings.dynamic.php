<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}
// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=17');
if(1<$modx->db->getRecordCount($rs)) {
	while($row = $modx->db->getRow($rs))
	{
		if($row['internalKey']!=$modx->getLoginUserID())
		{
			$msg = sprintf($_lang["lock_settings_msg"],$row['username']);
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}

// reload system settings from the database.
// this will prevent user-defined settings from being saved as system setting
if(!isset($default_config) || !is_array($default_config))
	$default_config = include_once(MODX_CORE_PATH . 'default.config.php');

$settings = array();
$rs = $modx->db->select('setting_name, setting_value', '[+prefix+]system_settings');
while($row = $modx->db->getRow($rs))
{
	$settings[$row['setting_name']] = $row['setting_value'];
}
$settings = array_merge($default_config,$settings);

if ($modx->manager->hasFormValues()) {
	$_POST = $modx->manager->loadFormValues();
}
if(setlocale(LC_CTYPE, 0)==='Japanese_Japan.932')
{
	$settings['filemanager_path'] = mb_convert_encoding($settings['filemanager_path'], 'utf-8', 'sjis-win');
	$settings['rb_base_dir']      = mb_convert_encoding($settings['rb_base_dir'], 'utf-8', 'sjis-win');
}
$settings['filemanager_path'] = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['filemanager_path']);
$settings['rb_base_dir']      = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['rb_base_dir']);
if(isset($_POST)) $settings = array_merge($settings, $_POST);

if(strpos($settings['site_url'],'[(site_url)]')!==false)
	$settings['site_url'] = str_replace('[(site_url)]', MODX_SITE_URL, $settings['site_url']);
if(strpos($settings['base_url'],'[(base_url)]')!==false)
	$settings['base_url'] = str_replace('[(base_url)]', MODX_BASE_URL, $settings['base_url']);

extract($settings, EXTR_OVERWRITE);

$displayStyle = ($_SESSION['browser']==='modern') ? 'table-row' : 'block' ;

// load languages and keys
$lang_keys = array();
$dir = scandir(MODX_CORE_PATH . 'lang');
foreach ($dir as $filename)
{
	if(substr($filename,-8)!=='.inc.php') continue;
	$languagename = str_replace('.inc.php', '', $filename);
	$lang_keys[$languagename] = get_lang_keys($filename);
}

$isDefaultUnavailableMsg = $site_unavailable_message == $_lang['siteunavailable_message_default'];
$isDefaultUnavailableMsgJs = $isDefaultUnavailableMsg ? 'true' : 'false';
$site_unavailable_message_view = isset($site_unavailable_message) ? $site_unavailable_message : $_lang['siteunavailable_message_default'];

?>

<script type="text/javascript">
$j(function(){
	$j('#furlRow1').change(function() {$j('.furlRow').fadeIn();});
	$j('#furlRow0').change(function() {$j('.furlRow').fadeOut();});
	$j('#udPerms1').change(function() {$j('.udPerms').fadeIn();});
	$j('#udPerms0').change(function() {$j('.udPerms').fadeOut();});
	$j('#udPerms1').change(function() {$j('.udPerms').fadeIn();});
	$j('#udPerms0').change(function() {$j('.udPerms').fadeOut();});
	$j('#editorRow1').change(function() {$j('.editorRow').fadeIn();});
	$j('#editorRow0').change(function() {$j('.editorRow').fadeOut();});
	$j('#rbRow1').change(function() {$j('.rbRow').fadeIn();});
	$j('#rbRow0').change(function() {$j('.rbRow').fadeOut();});
});

function addContentType()
{
	var i,o,exists=false;
	var txt = document.settings.txt_custom_contenttype;
	var lst = document.settings.lst_custom_contenttype;
	for(i=0;i<lst.options.length;i++)
	{
		if(lst.options[i].value==txt.value) {
			exists=true;
			break;
		}
	}
	if (!exists)
	{
		o = new Option(txt.value,txt.value);
		lst.options[lst.options.length]= o;
		updateContentType();
	}
	txt.value='';
}
function removeContentType()
{
	var i;
	var lst = document.settings.lst_custom_contenttype;
	for(i=0;i<lst.options.length;i++)
	{
		if(lst.options[i].selected)
		{
			lst.remove(i);
			break;
		}
	}
	updateContentType();
}
function updateContentType()
{
	var i,o,ol=[];
	var lst = document.settings.lst_custom_contenttype;
	var ct = document.settings.custom_contenttype;
	while(lst.options.length)
	{
		ol[ol.length] = lst.options[0].value;
		lst.options[0]= null;
	}
	if(ol.sort) ol.sort();
	ct.value = ol.join(",");
	for(i=0;i<ol.length;i++)
	{
		o = new Option(ol[i],ol[i]);
		lst.options[lst.options.length]= o;
	}
	documentDirty = true;
}
/**
 * @param element el were language selection comes from
 * @param string lkey language key to look up
 * @param id elupd html element to update with results
 * @param string default_str default value of string for loaded manager language - allows some level of confirmation of change from default
 */
function confirmLangChange(el, lkey, elupd)
{
	lang_current = $j('#'+elupd).val();
	lang_default = $j('#'+lkey+'_hidden').val();
	proceed = true;
	if(lang_current != lang_default)
	{
		proceed = confirm('<?php echo $_lang['confirm_setting_language_change']; ?>');
	}
	if(proceed)
	{
		//document.getElementById(elupd).value = '';
		lang = $j(el).val();
		$j.post('index.php',{'a':'118','action':'get','lang':lang,'key':lkey},function(resp)
		{
			document.getElementById(elupd).value = resp;
		});
	}
}
</script>
<form name="settings" action="index.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="a" value="30" />
	<h1><?php echo $_lang['settings_title']; ?></h1>
	<div id="actions">
		<ul class="actionButtons">
			<li id="Button1">
				<a href="#" onclick="documentDirty=false; document.settings.submit();">
					<img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']; ?>
				</a>
			</li>
			<li id="Button5">
				<a href="#" onclick="document.location.href='index.php?a=2';">
					<img src="<?php echo $_style["icons_cancel"]?>" /> <?php echo $_lang['cancel']; ?>
				</a>
			</li>
		</ul>
	</div>
<div style="margin: 0 10px 0 20px">
	<input type="hidden" name="site_id" value="<?php echo $site_id; ?>" />
	<input type="hidden" name="settings_version" value="<?php echo $modx_version; ?>" />
	<!-- this field is used to check site settings have been entered/ updated after install or upgrade -->
<?php
	if(!isset($settings_version) || $settings_version!=$modx_version)
	{
	?>
	<div class='sectionBody'><p><?php echo $_lang['settings_after_install']; ?></p></div>
<?php
	}
?>
	<div class="tab-pane" id="settingsPane">
	<script type="text/javascript">
		tpSettings = new WebFXTabPane( document.getElementById( "settingsPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
	</script>
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
	<th><?php echo $_lang['setting_individual_cache'] ?></th>
	<td>
		<?php echo wrap_label($_lang['enabled'] ,form_radio('individual_cache','1',$individual_cache=='1'));?><br />
		<?php echo wrap_label($_lang['disabled'],form_radio('individual_cache','0',$individual_cache=='0'));?><br />
		<?php echo $_lang["setting_individual_cache_desc"] ?>
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

<!-- Friendly URL settings  -->
<div class="tab-page" id="tabPage3">
<h2 class="tab"><?php echo $_lang["settings_furls"] ?></h2>
<table class="settings">
<tr>
	<th><?php echo $_lang["friendlyurls_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('friendly_urls','1', $friendly_urls=='1','id="furlRow1"'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('friendly_urls','0', $friendly_urls=='0','id="furlRow0"'));?><br />
		<?php echo $_lang["friendlyurls_message"] ?>
	</td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["friendlyurlsprefix_title"] ?></th>
	<td>
		<?php echo form_text('friendly_url_prefix',50);?><br />
		<?php echo $_lang["friendlyurlsprefix_message"] ?></td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["friendlyurlsuffix_title"] ?></th>
	<td>
		<?php echo form_text('friendly_url_suffix',50);?><br />
		<?php echo $_lang["friendlyurlsuffix_message"] ?></td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang['make_folders_title'] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('make_folders','1', $make_folders=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('make_folders','0', $make_folders=='0'));?><br />
		<?php echo $_lang["make_folders_message"] ?></td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang['mutate_settings.dynamic.php4'];?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('suffix_mode','1', $suffix_mode=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('suffix_mode','0', $suffix_mode=='0'));?><br />
		<?php echo $_lang['mutate_settings.dynamic.php5'];?></td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["friendly_alias_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('friendly_alias_urls','1', $friendly_alias_urls=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('friendly_alias_urls','0', $friendly_alias_urls=='0'));?><br />
	<?php echo $_lang["friendly_alias_message"] ?></td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["use_alias_path_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('use_alias_path','1', $use_alias_path=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('use_alias_path','0', $use_alias_path=='0'));?><br />
	<?php echo $_lang["use_alias_path_message"] ?>
</td>
</tr>
<tr class='furlRow row2' style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["duplicate_alias_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('allow_duplicate_alias','1', $allow_duplicate_alias=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('allow_duplicate_alias','0', $allow_duplicate_alias=='0'));?><br />
	<?php echo $_lang["duplicate_alias_message"] ?>
</td>
</tr>
<tr class="furlRow row1" style="display: <?php echo $friendly_urls==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["automatic_alias_title"] ?></th>
<td>
	<?php echo wrap_label('pagetitle',form_radio('automatic_alias','1', $automatic_alias=='1'));?><br />
	<?php echo wrap_label('numbering in each folder',form_radio('automatic_alias','2', $automatic_alias=='2'));?><br />
	<?php echo wrap_label($_lang["disabled"],form_radio('automatic_alias','0', $automatic_alias=='0'));?><br />
	<?php echo $_lang["automatic_alias_message"] ?>
</td>
</tr>
<tr class="row1" style="border-bottom:none;">
<td colspan="2">
<?php
// invoke OnFriendlyURLSettingsRender event
$evtOut = $modx->invokeEvent("OnFriendlyURLSettingsRender");
if(is_array($evtOut)) echo implode("",$evtOut);
?>
</td>
</tr>
</table>
</div>

<!-- User settings -->
<div class="tab-page" id="tabPage4">
<h2 class="tab"><?php echo $_lang["settings_users"] ?></h2>
<table class="settings">
<tr>
	<th><?php echo $_lang["udperms_title"] ?></th>
	<td>
	<?php echo wrap_label($_lang["yes"],form_radio('use_udperms','1', $modx->config['use_udperms']=='1','id="udPerms1"'));?><br />
	<?php echo wrap_label($_lang["no"], form_radio('use_udperms','0', $modx->config['use_udperms']=='0','id="udPerms0"'));?><br />
<?php echo $_lang["udperms_message"] ?></td>
</tr>
<tr class="udPerms row2" style="display: <?php echo $modx->config['use_udperms']==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["udperms_allowroot_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('udperms_allowroot','1', $udperms_allowroot=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('udperms_allowroot','0', $udperms_allowroot=='0'));?><br />
	<?php echo $_lang["udperms_allowroot_message"] ?>
</td>
</tr>
<tr class="udPerms row1" style="display: <?php echo $modx->config['use_udperms']==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["tree_show_protected"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('tree_show_protected','1',$tree_show_protected=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('tree_show_protected','0',$tree_show_protected=='0'));?><br />
	<?php echo $_lang["tree_show_protected_message"]?>
</td>
</tr>

<tr>
<th><?php echo $_lang["default_role_title"] ?></th>
<td>
<select name="default_role">
<?php echo get_role_list();?>
</select>
	<div><?php echo $_lang["default_role_message"]?></div>
</td>
</tr>

<tr>
	<th><?php echo $_lang["validate_referer_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('validate_referer','1', $validate_referer=='1'));?><br />
		<?php echo wrap_label($_lang["no"],form_radio('validate_referer','0', $validate_referer=='0'));?><br />
		<?php echo $_lang["validate_referer_message"] ?>
	</td>
</tr>

<tr>
<th><?php echo $_lang["allow_mgr2web_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('allow_mgr2web','1', $allow_mgr2web=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('allow_mgr2web','0', $allow_mgr2web=='0'));?><br />
	<?php echo $_lang["allow_mgr2web_message"] ?>
</td>
</tr>
<tr>
<th><?php echo $_lang["failed_login_title"] ?></th>
<td>
	<?php echo form_text('failed_login_attempts',3);?><br />
<?php echo $_lang["failed_login_message"] ?></td>
</tr>
<tr>
<th><?php echo $_lang["blocked_minutes_title"] ?></th>
<td>
	<?php echo form_text('blocked_minutes',7);?><br />
<?php echo $_lang["blocked_minutes_message"] ?></td>
</tr>

<tr>
<th><?php echo $_lang['a17_error_reporting_title']; ?></th>
<td>
	<?php echo wrap_label($_lang['a17_error_reporting_opt0'],form_radio('error_reporting','0', $error_reporting==='0'));?><br />
	<?php echo wrap_label($_lang['a17_error_reporting_opt1'],form_radio('error_reporting','1', $error_reporting==='1' || !isset($error_reporting)));?><br />
	<?php echo wrap_label($_lang['a17_error_reporting_opt2'],form_radio('error_reporting','2', $error_reporting==='2'));?><br />
	<?php echo wrap_label($_lang['a17_error_reporting_opt99'],form_radio('error_reporting','99', $error_reporting==='99'));?><br />
<?php echo $_lang['a17_error_reporting_msg'];?></td>
</tr>

<tr>
<th><?php echo $_lang['mutate_settings.dynamic.php6']; ?></th>
<td>
	<?php echo wrap_label($_lang['mutate_settings.dynamic.php7'],form_radio('send_errormail','0', ($send_errormail=='0' || !isset($send_errormail))));?><br />
	<?php echo wrap_label('error',form_radio('send_errormail','3', $send_errormail=='3'));?><br />
	<?php echo wrap_label('error + warning',form_radio('send_errormail','2', $send_errormail=='2'));?><br />
	<?php echo wrap_label('error + warning + information',form_radio('send_errormail','1', $send_errormail=='1'));?><br />
<?php echo $modx->parseText($_lang['mutate_settings.dynamic.php8'],'emailsender=' . $modx->config['emailsender']);?></td>
</tr>
<?php
// Check for GD before allowing captcha to be enabled
$gdAvailable = extension_loaded('gd');
?>

<tr>
<th><?php echo $_lang["warning_visibility"] ?></th>
<td>
	<?php echo wrap_label($_lang["administrators"],form_radio('warning_visibility','0',$warning_visibility=='0'));?><br />
	<?php echo wrap_label($_lang["a17_warning_opt2"],form_radio('warning_visibility','2',$warning_visibility=='2'));?><br />
	<?php echo wrap_label($_lang["everybody"],form_radio('warning_visibility','1',$warning_visibility=='1'));?><br />
	<?php echo $_lang["warning_visibility_message"]?>
</td>
</tr>

<tr>
<th><?php echo $_lang["pwd_hash_algo_title"] ?></th>
<td>
<?php
	$phm['sel']['BLOWFISH_Y'] = $pwd_hash_algo=='BLOWFISH_Y' ?  1 : 0;
	$phm['sel']['BLOWFISH_A'] = $pwd_hash_algo=='BLOWFISH_A' ?  1 : 0;
	$phm['sel']['SHA512']     = $pwd_hash_algo=='SHA512' ?  1 : 0;
	$phm['sel']['SHA256']     = $pwd_hash_algo=='SHA256' ?  1 : 0;
	$phm['sel']['MD5']        = $pwd_hash_algo=='MD5' ?  1 : 0;
	$phm['sel']['UNCRYPT']    = $pwd_hash_algo=='UNCRYPT' ?  1 : 0;
	$phm['e']['BLOWFISH_Y'] = $modx->manager->checkHashAlgorithm('BLOWFISH_Y') ? 0:1;
	$phm['e']['BLOWFISH_A'] = $modx->manager->checkHashAlgorithm('BLOWFISH_A') ? 0:1;
	$phm['e']['SHA512']     = $modx->manager->checkHashAlgorithm('SHA512') ? 0:1;
	$phm['e']['SHA256']     = $modx->manager->checkHashAlgorithm('SHA256') ? 0:1;
	$phm['e']['MD5']        = $modx->manager->checkHashAlgorithm('MD5') ? 0:1;
	$phm['e']['UNCRYPT']    = $modx->manager->checkHashAlgorithm('UNCRYPT') ? 0:1;
?>
	<?php echo wrap_label('CRYPT_BLOWFISH_Y (salt &amp; stretch)',form_radio('pwd_hash_algo','BLOWFISH_Y',$phm['sel']['BLOWFISH_Y'], '', $phm['e']['BLOWFISH_Y']));?><br />
	<?php echo wrap_label('CRYPT_BLOWFISH_A (salt &amp; stretch)',form_radio('pwd_hash_algo','BLOWFISH_A',$phm['sel']['BLOWFISH_A'], '', $phm['e']['BLOWFISH_A']));?><br />
	<?php echo wrap_label('CRYPT_SHA512 (salt &amp; stretch)'    ,form_radio('pwd_hash_algo','SHA512'    ,$phm['sel']['SHA512']    , '', $phm['e']['SHA512']));?><br />
	<?php echo wrap_label('CRYPT_SHA256 (salt &amp; stretch)'    ,form_radio('pwd_hash_algo','SHA256'    ,$phm['sel']['SHA256']    , '', $phm['e']['SHA256']));?><br />
	<?php echo wrap_label('CRYPT_MD5'       ,form_radio('pwd_hash_algo','MD5'       ,$phm['sel']['MD5']       , '', $phm['e']['MD5']));?><br />
	<?php echo wrap_label('UNCRYPT(32 chars salt + SHA-1 hash)'   ,form_radio('pwd_hash_algo','UNCRYPT'   ,$phm['sel']['UNCRYPT']   , '', $phm['e']['UNCRYPT']));?><br />
	<?php echo $_lang["pwd_hash_algo_message"]?>
</td>
</tr>

<tr>
<th><?php echo $_lang["captcha_title"] ?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('use_captcha','1', $use_captcha=='1' && $gdAvailable,'',!$gdAvailable));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('use_captcha','0', $use_captcha=='0' || !$gdAvailable,'',!$gdAvailable));?><br />
	<?php echo $_lang["captcha_message"] ?>
</td>
</tr>
<tr>
<th><?php echo $_lang["captcha_words_title"];?>
<br />
<p><?php echo $_lang["update_settings_from_language"]; ?></p>
<select name="reload_captcha_words" id="reload_captcha_words_select" onchange="confirmLangChange(this, 'captcha_words_default', 'captcha_words_input');">
<?php echo get_lang_options('captcha_words_default');?>
</select>
</th>
<td>
	<?php echo form_text('captcha_words',255,'id="captcha_words_input" style="width:400px"');?><br />
<input type="hidden" name="captcha_words_default" id="captcha_words_default_hidden" value="<?php echo addslashes($_lang["captcha_words_default"]);?>" /><br />
<?php echo $_lang["captcha_words_message"] ?></td>
</tr>
<tr>
<th><?php echo $_lang["emailsender_title"] ?></th>
<td>
	<?php echo form_text('emailsender');?><br />
<?php echo $_lang["emailsender_message"] ?></td>
</tr>






<!--for smtp-->
<tr>
<th><?php echo $_lang["email_method_title"] ?></th>
<td>
<?php echo wrap_label($_lang["email_method_mail"],form_radio('email_method','mail', ($email_method=='mail' || !isset($email_method)) ));?>
<?php echo wrap_label($_lang["email_method_smtp"],form_radio('email_method','smtp', ($email_method=='smtp') ));?><br />
</td>
</tr>
<tr>
<th><?php echo $_lang["smtp_auth_title"] ?></th>
<td>
<?php echo wrap_label($_lang["yes"],form_radio('smtp_auth','1', ($smtp_auth=='1' || !isset($smtp_auth)) ));?>
<?php echo wrap_label($_lang["no"],form_radio('smtp_auth','0', ($smtp_auth=='0') ));?><br />
</td>
</tr>

<tr>
<th><?php echo $_lang["smtp_host_title"] ?></th>
<td ><input onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtp_host" value="<?php echo isset($smtp_host) ? $smtp_host : "smtp.example.com" ; ?>" /></td>
</tr>
<tr>
<th><?php echo $_lang["smtp_port_title"] ?></th>
<td ><input onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtp_port" value="<?php echo isset($smtp_port) ? $smtp_port : "25" ; ?>" /></td>
</tr>
<tr>
<th><?php echo $_lang["smtp_username_title"] ?></th>
<td ><input onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtp_username" value="<?php echo isset($smtp_username) ? $smtp_username : $emailsender ; ?>" /></td>
</tr>
<tr>
<th><?php echo $_lang["smtp_password_title"] ?></th>
<td ><input onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtppw" value="********************" autocomplete="off" /></td>
</tr>
<tr>
<th><?php echo $_lang["smtp_secure_title"] ?></th>
<td>
<?php echo wrap_label($_lang["none"],form_radio('smtp_secure','', ($smtp_secure=='' || !isset($smtp_secure)) )); ?>
<?php echo wrap_label("ssl",form_radio('smtp_secure','ssl', ($smtp_secure=='ssl') ));?>
<?php echo wrap_label("tls",form_radio('smtp_secure','tls', ($smtp_secure=='tls') ));?>
</td>
</tr>








	
<tr>
<th><?php echo $_lang["emailsubject_title"];?>
<br />
<p><?php echo $_lang["update_settings_from_language"]; ?></p>
<select name="reload_emailsubject" id="reload_emailsubject_select" onchange="confirmLangChange(this, 'emailsubject_default', 'emailsubject_field');">
<?php echo get_lang_options('emailsubject_default');?>
</select>
</th>
<td>
	<?php echo form_text('emailsubject',null,'id="emailsubject_field"');?><br />
<input type="hidden" name="emailsubject_default" id="emailsubject_default_hidden" value="<?php echo addslashes($_lang['emailsubject_default']);?>" /><br />
<?php echo $_lang["emailsubject_message"] ?></td>
</tr>
<tr>
<td nowrap class="warning" valign="top"><b><?php echo $_lang["signupemail_title"] ?></b>
<br />
<p><?php echo $_lang["update_settings_from_language"]; ?></p>
<select name="reload_signupemail_message" id="reload_signupemail_message_select" onchange="confirmLangChange(this, 'system_email_signup', 'signupemail_message_textarea');">
<?php echo get_lang_options('system_email_signup');?>
</select>
</td>
<td><textarea id="signupemail_message_textarea" name="signupemail_message" style="width:100%; height: 120px;"><?php echo $signupemail_message;?></textarea>
<input type="hidden" name="system_email_signup_default" id="system_email_signup_hidden" value="<?php echo addslashes($_lang['system_email_signup']);?>" /><br />
<?php echo $_lang["signupemail_message"] ?></td>
</tr>
<tr>
<td nowrap class="warning" valign="top"><b><?php echo $_lang["websignupemail_title"] ?></b>
<br />
<p><?php echo $_lang["update_settings_from_language"]; ?></p>
<select name="reload_websignupemail_message" id="reload_websignupemail_message_select" onchange="confirmLangChange(this, 'system_email_websignup', 'websignupemail_message_textarea');">
<?php echo get_lang_options('system_email_websignup');?>
</select>
</td>
<td><textarea id="websignupemail_message_textarea" name="websignupemail_message" style="width:100%; height: 120px;"><?php echo $websignupemail_message;?></textarea>
<input type="hidden" name="system_email_websignup_default" id="system_email_websignup_hidden" value="<?php echo addslashes($_lang['system_email_websignup']);?>" /><br />
<?php echo $_lang["websignupemail_message"] ?></td>
</tr>
<tr>
<td nowrap class="warning" valign="top"><b><?php echo $_lang["webpwdreminder_title"] ?></b>
<br />
<p><?php echo $_lang["update_settings_from_language"]; ?></p>
<select name="reload_system_email_webreminder_message" id="reload_system_email_webreminder_select" onchange="confirmLangChange(this, 'system_email_webreminder', 'system_email_webreminder_textarea');">
<?php echo get_lang_options('system_email_webreminder');?>
</select>
</td>
<td><textarea id="system_email_webreminder_textarea" name="webpwdreminder_message" style="width:100%; height: 120px;"><?php echo $webpwdreminder_message;?></textarea>
<input type="hidden" name="system_email_webreminder_default" id="system_email_webreminder_hidden" value="<?php echo addslashes($_lang['system_email_webreminder']);?>" /><br />
<?php echo $_lang["webpwdreminder_message"] ?></td>
</tr>

<tr>
	<th><?php echo $_lang["enable_bindings_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('enable_bindings','1',$enable_bindings=='1'));?><br />
		<?php echo wrap_label($_lang["no"], form_radio('enable_bindings','0',$enable_bindings=='0'));?><br />
		<?php echo $_lang["enable_bindings_message"] ?>
</td>
</tr>

<tr class="row1" style="border-bottom:none;">
<td colspan="2" style="padding:0;">
<?php
// invoke OnUserSettingsRender event
$evtOut = $modx->invokeEvent("OnUserSettingsRender");
if(is_array($evtOut)) echo implode("",$evtOut);
?>
</td>
</tr>
</table>
</div>

<!-- Interface & editor settings -->
<div class="tab-page" id="tabPage5">
<h2 class="tab"><?php echo $_lang["settings_ui"] ?></h2>
<table class="settings">
<tr>
<th><?php echo $_lang["manager_theme"]?></th>
<td><select name="manager_theme" size="1" class="inputBox">
<?php
$files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
foreach($files as $file)
{
	$file = str_replace('\\','/',$file);
	if($file!="." && $file!=".." && substr($file,0,1) != '.')
	{
		$themename = substr(dirname($file),strrpos(dirname($file),'/')+1);
		$selectedtext = $themename==$manager_theme ? "selected='selected'" : "" ;
		echo "<option value='$themename' $selectedtext>".ucwords(str_replace("_", " ", $themename))."</option>";
	}
}
?>
</select><br />
<?php echo $_lang["manager_theme_message"]?></td>
</tr>

<tr>
	<th><?php echo $_lang["a17_manager_inline_style_title"] ?></th>
	<td>
		<textarea name="manager_inline_style" id="manager_inline_style" style="width:95%; height: 9em;"><?php echo $manager_inline_style; ?></textarea><br />
		<?php echo $_lang["a17_manager_inline_style_message"] ?>
	</td>
</tr>

<tr>
	<th><?php echo $_lang["language_title"]?></th>
	<td>
		<select name="manager_language" size="1" class="inputBox">
		<?php echo get_lang_options(null, $manager_language);?>
		</select><br />
		<?php echo $_lang["language_message"]?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["enable_draft_title"]?></th>
	<td>
    	<?php echo wrap_label($_lang["enabled"],form_radio('enable_draft','1',$enable_draft=='1'));?><br />
    	<?php echo wrap_label($_lang["disabled"],form_radio('enable_draft','0',$enable_draft=='0'));?><br />
		<?php echo $_lang["enable_draft_message"]?>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["tree_pane_open_default_title"]?></th>
	<td>
    	<?php echo wrap_label($_lang["open"], form_radio('tree_pane_open_default', 1, $tree_pane_open_default==1));?><br />
    	<?php echo wrap_label($_lang["close"],form_radio('tree_pane_open_default', 0, $tree_pane_open_default==0));?><br />
		<?php echo $_lang["tree_pane_open_default_message"]?>
	</td>
</tr>
<?php
$tmenu_style = 'style="width:350px;"';
checkConfig('topmenu_site');
checkConfig('topmenu_element');
checkConfig('topmenu_security');
checkConfig('topmenu_user');
checkConfig('topmenu_tools');
?>
<tr>
	<th><?php echo $_lang["topmenu_items_title"]?></th>
	<td>
		<table>
		<tr><td><?php echo  $_lang['site']     . '</td><td>' . form_text('topmenu_site','',$tmenu_style);?></td></tr>
		<tr><td><?php echo  $_lang['elements'] . '</td><td>' . form_text('topmenu_element','',$tmenu_style);?></td></tr>
		<tr><td><?php echo  $_lang['users']    . '</td><td>' . form_text('topmenu_security','',$tmenu_style);?></td></tr>
		<tr><td><?php echo  $_lang['user']     . '</td><td>' . form_text('topmenu_user','',$tmenu_style);?></td></tr>
		<tr><td><?php echo  $_lang['tools']    . '</td><td>' . form_text('topmenu_tools','',$tmenu_style);?></td></tr>
		<tr><td><?php echo  $_lang['reports']  . '</td><td>' . form_text('topmenu_reports','',$tmenu_style);?></td></tr>
		</table>
		<div><?php echo $_lang["topmenu_items_message"];?></div>
	</td>
</tr>

<tr>
<th><?php echo $_lang["limit_by_container"] ?></th>
<td>
	<?php echo form_text('limit_by_container',4);?><br />
<?php echo $_lang["limit_by_container_message"]?></td>
</tr>

<tr>
<th><?php echo $_lang["tree_page_click"] ?></th>
<td>
	<?php echo wrap_label($_lang["edit_resource"],form_radio('tree_page_click','27',$tree_page_click=='27'));?><br />
	<?php echo wrap_label($_lang["doc_data_title"],form_radio('tree_page_click','3',$tree_page_click=='3'));?><br />
	<?php echo wrap_label($_lang["tree_page_click_option_auto"],form_radio('tree_page_click','auto',$tree_page_click=='auto'));?><br />
	<?php echo $_lang["tree_page_click_message"]?>
</td>
</tr>
<tr>
<th><?php echo $_lang["remember_last_tab"] ?></th>
<td>
	<?php echo wrap_label("{$_lang['yes']} (Full)",form_radio('remember_last_tab','2',$remember_last_tab=='2'));?><br />
	<?php echo wrap_label("{$_lang['yes']} (Stay mode)",form_radio('remember_last_tab','1',$remember_last_tab=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('remember_last_tab','0',$remember_last_tab=='0'));?><br />
	<?php echo $_lang["remember_last_tab_message"]?>
</td>
</tr>
<tr>
<th><?php echo $_lang["setting_resource_tree_node_name"] ?></th>
<td>
	<select name="resource_tree_node_name" size="1" class="inputBox">
<?php
	$tpl = '<option value="[+value+]" [+selected+]>[*[+value+]*]</option>' . "\n";
	$option = array('pagetitle','menutitle','alias','createdon','editedon','publishedon');
	$output = array();
	foreach($option as $v)
	{
		$selected = ($v==$resource_tree_node_name) ? 'selected' : '';
		$s = array('[+value+]','[+selected+]');
		$r = array($v,$selected);
		$output[] = str_replace($s,$r,$tpl);
	}
	echo join("\n",$output)
?>
	</select><br />
	<?php echo $_lang["setting_resource_tree_node_name_desc"]?>
</td>
</tr>

<tr>
	<th><?php echo $_lang["top_howmany_title"] ?></th>
	<td>
		<?php echo form_text('top_howmany',3);?><br />
		<?php echo $_lang["top_howmany_message"] ?>
	</td>
</tr>
<tr>
<th><?php echo $_lang["datepicker_offset"] ?></th>
<td>
	<?php echo form_text('datepicker_offset',5);?><br />
<?php echo $_lang["datepicker_offset_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["datetime_format"]?></th>
<td><select name="datetime_format" size="1" class="inputBox">
<?php
$datetime_format_list = array('dd-mm-YYYY', 'mm/dd/YYYY', 'YYYY/mm/dd');
$str = '';
foreach($datetime_format_list as $value)
{
	$selectedtext = ($datetime_format == $value) ? ' selected' : '';
	$str .= '<option value="' . $value . '"' . $selectedtext . '>';
	$str .= $value . "</option>\n";
}
echo $str;
?>
</select><br />
<?php echo $_lang["datetime_format_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["nologentries_title"]?></th>
<td>
	<?php echo form_text('number_of_logs',3);?><br />
<?php echo $_lang["nologentries_message"]?></td>
</tr>
<tr>
	<th><?php echo $_lang["automatic_optimize_table_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('automatic_optimize','1',$automatic_optimize=='1'));?><br />
		<?php echo wrap_label($_lang["no"], form_radio('automatic_optimize','0',$automatic_optimize=='0'));?><br />
		<?php echo $_lang["automatic_optimize_table_message"] ?>
	</td>
</tr>
<tr>
<th><?php echo $_lang["mail_check_timeperiod_title"] ?></th>
<td>
	<?php echo form_text('mail_check_timeperiod',5);?><br />
<?php echo $_lang["mail_check_timeperiod_message"] ?></td>
</tr>
<tr>
<th><?php echo $_lang["nomessages_title"]?></th>
<td>
	<?php echo form_text('number_of_messages',5);?><br />
<?php echo $_lang["nomessages_message"]?></td>
</tr>
<tr>
	<th><?php echo $_lang["pm2email_title"] ?></th>
	<td>
		<?php echo wrap_label($_lang["yes"],form_radio('pm2email','1',$pm2email=='1'));?><br />
		<?php echo wrap_label($_lang["no"], form_radio('pm2email','0',$pm2email=='0'));?><br />
		<?php echo $_lang["pm2email_message"] ?>
	</td>
</tr>
<tr>
<th><?php echo $_lang["noresults_title"]?></th>
<td>
	<?php echo form_text('number_of_results',5);?><br />
<?php echo $_lang["noresults_message"]?></td>
</tr>

<tr>
<th><?php echo $_lang["use_editor_title"]?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('use_editor','1',$use_editor=='1','id="editorRow1"'));?><br />
	<?php echo wrap_label($_lang["no"] ,form_radio('use_editor','0',$use_editor=='0','id="editorRow0"'));?><br />
	<?php echo $_lang["use_editor_message"]?>
</td>
</tr>

<tr class="editorRow row3" style="display: <?php echo $use_editor==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["which_editor_title"]?></th>
<td>
<?php
// invoke OnRichTextEditorRegister event
$editors = $modx->invokeEvent("OnRichTextEditorRegister");
if(is_array($editors))
{
	$which_editor_sel = '<select name="which_editor">';
	$which_editor_sel .= '<option value="none"' . ($which_editor=='none' ? ' selected="selected"' : '') . '>' . $_lang["none"] . "</option>\n";
	foreach($editors as $editor)
	{
		$editor_sel = $which_editor==$editor ? ' selected="selected"' : '';
		$which_editor_sel .= '<option value="' . $editor . '"' . $editor_sel . '>' . $editor . "</option>\n";
	}
	$which_editor_sel .= '</select><br />';
}
else $which_editor_sel = '';
echo $which_editor_sel;
?>
<?php echo $_lang["which_editor_message"]?></td>
</tr>
<tr class="editorRow row3" style="display: <?php echo $use_editor==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["fe_editor_lang_title"]?></th>
<td><select name="fe_editor_lang" size="1" class="inputBox">
<?php echo get_lang_options(null, $fe_editor_lang);?>
</select><br />
<?php echo $_lang["fe_editor_lang_message"]?></td>
</tr>
<tr class="editorRow row3" style="display: <?php echo $use_editor==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["editor_css_path_title"]?></th>
<td>
<?php echo form_text('editor_css_path','','style="width:400px;"');?><br />
<?php echo $_lang["editor_css_path_message"]?></td>
</tr>
<tr class="row1" style="border-bottom:none;">
<td colspan="2" style="padding:0;">
<?php
// invoke OnInterfaceSettingsRender event
$evtOut = $modx->invokeEvent("OnInterfaceSettingsRender");
if(is_array($evtOut)) echo implode("",$evtOut);
?>
</td>
</tr>
</table>
</div>

<!-- Miscellaneous settings -->
<div class="tab-page" id="tabPage7">
<h2 class="tab"><?php echo $_lang["settings_misc"] ?></h2>
<table class="settings">
<tr>
<th><?php echo $_lang["filemanager_path_title"]?></th>
<td>
<?php echo $_lang['default']; ?> <span id="default_filemanager_path">[(base_path)]</span> <?php echo "({$base_path})";?><br />
<?php echo form_text('filemanager_path',255,'id="filemanager_path"');?>
<input type="button" onclick="jQuery('#filemanager_path').val('[(base_path)]');" value="<?php echo $_lang["reset"]; ?>" name="reset_filemanager_path"><br />
<?php echo $_lang["filemanager_path_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["uploadable_files_title"]?></th>
<td>
<?php echo form_text('upload_files');?><br />
<?php echo $_lang["uploadable_files_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["uploadable_images_title"]?></th>
<td>
<?php echo form_text('upload_images');?><br />
<?php echo $_lang["uploadable_images_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["uploadable_media_title"]?></th>
<td>
<?php echo form_text('upload_media');?><br />
<?php echo $_lang["uploadable_media_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["uploadable_flash_title"]?></th>
<td>
<?php echo form_text('upload_flash');?><br />
<?php echo $_lang["uploadable_flash_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["upload_maxsize_title"]?></th>
<td>
<?php
if(empty($upload_maxsize))
{
	$uploadMaxsize = $modx->manager->getUploadMaxsize();
	$last = substr($uploadMaxsize,-1);
	$uploadMaxsize = substr($uploadMaxsize,0,-1);
	switch(strtolower($last))
	{
		case 'g':
			$uploadMaxsize *= 1024;
		case 'm':
			$uploadMaxsize *= 1024;
		case 'k':
			$uploadMaxsize *= 1024; break;
		default:
			$uploadMaxsize = 5000000;
	}
	$settings['upload_maxsize'] = $uploadMaxsize;
}
?>
<?php echo form_text('upload_maxsize');?><br />
<?php echo sprintf($_lang["upload_maxsize_message"],$limit_size);?></td>
</tr>
<tr>
<th><?php echo $_lang["new_file_permissions_title"]?></th>
<td>
<?php echo form_text('new_file_permissions',4);?><br />
<?php echo $_lang["new_file_permissions_message"]?></td>
</tr>
<tr>
<th><?php echo $_lang["new_folder_permissions_title"]?></th>
<td>
<?php echo form_text('new_folder_permissions',4);?><br />
<?php echo $_lang["new_folder_permissions_message"]?></td>
</tr>

<tr>
<th><?php echo $_lang["rb_title"]?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('use_browser','1',$use_browser=='1','id="rbRow1"'));?><br />
	<?php echo wrap_label($_lang["no"] ,form_radio('use_browser','0',$use_browser=='0','id="rbRow0"'));?><br />
	<?php echo $_lang["rb_message"]?>
</td>
</tr>

<tr class="rbRow row3" style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["settings_strip_image_paths_title"]?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('strip_image_paths','1',$strip_image_paths=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('strip_image_paths','0',$strip_image_paths=='0'));?><br />
	<?php echo $_lang["settings_strip_image_paths_message"]?>
</td>
</tr>

<tr class="rbRow row3" style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["rb_webuser_title"]?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('rb_webuser','1',$rb_webuser=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('rb_webuser','0',$rb_webuser=='0'));?><br />
	<?php echo $_lang["rb_webuser_message"]?>
</td>
</tr>
<tr class='rbRow row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["rb_base_dir_title"]?></th>
<td>
<?php
	$default_rb_base_dir = is_dir("{$base_path}content") ? 'content/' : 'assets/';
?>
<?php echo $_lang['default']; ?> <span id="default_rb_base_dir"><?php echo "[(base_path)]{$default_rb_base_dir}";?></span> <?php echo "({$base_path}{$default_rb_base_dir})";?><br />
<?php echo form_text('rb_base_dir',255,'id="rb_base_dir"');?>
<input type="button" onclick="jQuery('#rb_base_dir').val(jQuery('#default_rb_base_dir').text());" value="<?php echo $_lang["reset"]; ?>" name="reset_rb_base_dir"><br />
<?php echo $_lang["rb_base_dir_message"]?></td>
</tr>
<tr class='rbRow row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["rb_base_url_title"]?></th>
<td>
<?php echo $site_url . form_text('rb_base_url');?><br />
<?php echo $_lang["rb_base_url_message"]?></td>
</tr>
<tr class='rbRow row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["clean_uploaded_filename"]?></th>
<td>
	<?php echo wrap_label($_lang["yes"],form_radio('clean_uploaded_filename','1',$clean_uploaded_filename=='1'));?><br />
	<?php echo wrap_label($_lang["no"],form_radio('clean_uploaded_filename','0',$clean_uploaded_filename=='0'));?><br />
	<?php echo $_lang["clean_uploaded_filename_message"];?>
</td>
</tr>
<tr class='rbRow row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
<th><?php echo $_lang["a17_image_limit_width_title"]?></th>
<td>
<?php echo form_text('image_limit_width');?>px<br />
<?php echo $_lang["a17_image_limit_width_message"]?></td>
</tr>

<tr class="row1" style="border-bottom:none;">
<td colspan="2" style="padding:0;">
<?php
// invoke OnMiscSettingsRender event
$evtOut = $modx->invokeEvent("OnMiscSettingsRender");
if(is_array($evtOut)) echo implode("",$evtOut);
?>
</td>
</tr>
</table>
</div>
<?php
	$evtOut = $modx->invokeEvent('OnSystemSettingsRender');
	if(is_array($evtOut)) echo implode('',$evtOut);
?>
</div>
</div>
</form>
<?php
/**
* get_lang_keys
*
* @return array of keys from a language file
*/
function get_lang_keys($filename)
{
	$file = MODX_CORE_PATH . "lang/{$filename}";
	if(is_file($file) && is_readable($file))
	{
		include($file);
		return array_keys($_lang);
	}
	else
	{
		return array();
	}
}
/**
* get_langs_by_key
*
* @return array of languages that define the key in their file
*/
function get_langs_by_key($key)
{
	global $lang_keys;
	$lang_return = array();
	foreach($lang_keys as $lang=>$keys)
	{
		if(in_array($key, $keys))
		{
			$lang_return[] = $lang;
		}
	}
	return $lang_return;
}

/**
* get_lang_options
*
* returns html option list of languages
*
* @param string $key specify language key to return options of langauges that override it, default return all languages
* @param string $selected_lang specify language to select in option list, default none
* @return html option list
*/
function get_lang_options($key=null, $selected_lang=null)
{
	global $lang_keys, $_lang;
	$lang_options = '';
	if($key)
	{
		$languages = get_langs_by_key($key);
		sort($languages);
		$lang_options .= '<option value="">'.$_lang['language_title'].'</option>';
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$lang_options .= '<option value="'.$language_name.'">'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
	else
	{
		$languages = array_keys($lang_keys);
		sort($languages);
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$sel = $language_name == $selected_lang ? ' selected="selected"' : '';
			$lang_options .= '<option value="'.$language_name.'" '.$sel.'>'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
}

function form_text($name,$maxlength='255',$add='',$readonly=false)
{
	global $settings;
	
	$value = isset($settings[$name]) ? $settings[$name] : '';
	
	if($readonly) $readonly = ' disabled';
	if($add)      $add = ' ' . $add;
	if(empty($maxlength)) $maxlength = '255';
	if($maxlength<=10) $maxlength = 'maxlength="' . $maxlength . '" style="width:' . $maxlength . 'em;"';
	else               $maxlength = 'maxlength="' . $maxlength . '"';
	return '<input type="text" ' . $maxlength . ' name="' . $name . '" value="' . $value . '"' . $readonly . $add . ' />';
}

function form_radio($name,$value,$checked=false,$add='',$disabled=false) {
	if($checked)  $checked  = ' checked="checked"';
	if($disabled) $disabled = ' disabled';
	if($add)     $add = ' ' . $add;
	return '<input type="radio" name="' . $name . '" value="' . $value . '"' . $checked . $disabled . $add . ' />';
}

function wrap_label($str='',$object) {
	return "<label>{$object}\n{$str}</label>";
}

function get_role_list()
{
	global $modx, $default_role;
	
	$rs = $modx->db->select('id,name', '[+prefix+]user_roles', 'id!=1', 'save_role DESC,new_role DESC,id ASC');
	$tpl = '<option value="[+id+]" [+selected+]>[+name+]</option>';
	$options = "\n";
	while($ph=$modx->db->getRow($rs))
	{
		$ph['selected'] = ($default_role == $ph['id']) ? ' selected' : '';
		$options .= $modx->parseText($tpl,$ph);
	}
	return $options;
}

function checkConfig($key) {
	global $settings,$default_config;
	if(substr($settings[$key],0,2)==='* ')
		$settings[$key] = trim($settings[$key],'* ');
	else
		$settings[$key] = $default_config[$key];
}
