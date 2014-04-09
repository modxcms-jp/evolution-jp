<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
$modx->config['preview_mode'] = '1';
if (isset($_REQUEST['id']) && preg_match('@^[0-9]+$@',$_REQUEST['id']))
	 $id = $_REQUEST['id'];
else $id = '0';

checkPermissions($id);
checkDocLock($id);

$config = $modx->config;
$docgrp  = getDocgrp();
$db_v    = getContentFromDB($id,$docgrp);
$form_v  = $_POST;
$doc = mergeContent($db_v,$form_v);
if($_REQUEST['a']==='27') checkViewUnpubDocPerm($doc['published'],$doc['editedby']);

$doc['menuindex'] = getMenuIndexAtNew($doc['menuindex']);
$doc['alias']     = getAliasAtNew($doc['alias']);
$doc['richtext']  = getRteAtNew($doc['richtext']);
if (isset ($form_v['which_editor']))
{
	$which_editor = $form_v['which_editor'];
}
else $which_editor = $config['which_editor'];

echo getJScripts();

$_SESSION['itemname'] = to_safestr($doc['pagetitle']);
?>

<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
<?php
// invoke OnDocFormPrerender event
$evtOut = $modx->invokeEvent('OnDocFormPrerender', array(
	'id' => $id
));
if (is_array($evtOut)) echo implode('', $evtOut);
?>
<input type="hidden" name="a" value="5" />
<input type="hidden" name="id" value="<?php echo $id;?>" />
<?php if($_REQUEST['pid']):?>
<input type="hidden" name="pid" value="<?php echo $_REQUEST['pid'];?>" />
<?php endif;?>
<input type="hidden" name="mode" value="<?php echo (int) $_REQUEST['a'];?>" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo isset($upload_maxsize) ? $upload_maxsize : 3145728?>" />
<input type="hidden" name="refresh_preview" value="0" />
<input type="hidden" name="newtemplate" value="" />

<fieldset id="create_edit">
	<h1>
<?php
if ($id!=0) echo "{$_lang['edit_resource_title']}(ID:{$id})";
else        echo $_lang['create_resource_title'];
?>
	</h1>

<div id="actions">
	  <ul class="actionButtons">
<?php
echo ab_save();
if ($_REQUEST['a'] !== '4' && $_REQUEST['a'] !== '72' && $id != $config['site_start'])
{
	echo ab_move();
	echo ab_duplicate();
	echo ab_delete();
}
if ($_REQUEST['a'] !== '72')
{
	echo ab_preview();
}
echo ab_cancel();
?>
	  </ul>
</div>

<!-- start main wrapper -->
<div class="sectionBody">
<div class="tab-pane" id="documentPane">
	<script type="text/javascript">
	tpSettings = new WebFXTabPane(document.getElementById("documentPane"), <?php echo (($config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?> );
	</script>
	<!-- General -->
	<div class="tab-page" id="tabGeneral">
		<h2 class="tab"><?php echo $_lang['settings_general']?></h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabGeneral" ) );</script>

		<table width="99%" border="0" cellspacing="5" cellpadding="0">
<?php
$body  = input_text('pagetitle',to_safestr($doc['pagetitle']),'spellcheck="true"');
$body .= tooltip($_lang['resource_title_help']);
renderTr($_lang['resource_title'],$body);

$body  = input_text('longtitle',to_safestr($doc['longtitle']),'spellcheck="true"');
$body .= tooltip($_lang['resource_long_title_help']);
renderTr($_lang['long_title'],$body);

$body  = '<textarea name="description" class="inputBox" style="height:43px;" rows="2" cols="">' . to_safestr($doc['description']) . '</textarea>';
$body .= tooltip($_lang['resource_description_help']);
renderTr($_lang['resource_description'],$body,'vertical-align:top;');

$body = '';
if($config['suffix_mode']==1)
{
	$body .= get_scr_change_url_suffix($config['friendly_url_suffix']);
	$onkeyup = 'onkeyup="change_url_suffix();" ';
}
else $onkeyup = '';
if($config['friendly_urls']==='1' && $doc['type']!=='reference')
{
	$body .= get_alias_path($id);
	$body .= input_text('alias',to_safestr($doc['alias']), $onkeyup . 'size="20" style="width:120px;"','50');
	if($config['friendly_urls']==1)
	{
		if($config['suffix_mode']==1 && strpos($doc['alias'],'.')!==false)
		{
			$suffix = '';
		}
		else $suffix = $config['friendly_url_suffix'];
	}
	else $suffix = '';
	$body .= '<span id="url_suffix">' . $suffix . '</span>';
}
else
{
	$body .= input_text('alias',to_safestr($doc['alias']),'','100');
}
$body .= tooltip($_lang['resource_alias_help']);
renderTr($_lang['resource_alias'],$body);

if ($doc['type'] == 'reference' || $_REQUEST['a'] == '72') {
	// Web Link specific
	$head[] = $_lang['weblink'];
	$head[] = '<img name="llock" src="' . $_style['tree_folder'] . '" alt="tree_folder" onclick="enableLinkSelection(!allowLinkSelection);" style="cursor:pointer;" />';
	$doc['content'] = !empty($doc['content']) ? strip_tags(stripslashes($doc['content'])) : 'http://';
	$body = input_text('ta',$doc['content']) . tooltip($_lang['resource_weblink_help']);
	renderTr($head, $body);
}
$body = '<textarea name="introtext" class="inputBox" style="height:60px;" rows="3" cols="">'.to_safestr($doc['introtext']).'</textarea>';
$body .= tooltip($_lang['resource_summary_help']);
renderTr($_lang['resource_summary'],$body,'vertical-align:top;');
$body = '<select id="template" name="template" class="inputBox" onchange="changeTemplate();" style="width:308px">';
$body .= '<option value="0">(blank)</option>';
$body .= get_template_options($doc);
$body .= '</select>' . tooltip($_lang['page_data_template_help']);
renderTr($_lang['page_data_template'],$body);

$body = input_text('menutitle',to_safestr($doc['menutitle'])) . tooltip($_lang['resource_opt_menu_title_help']);
renderTr($_lang['resource_opt_menu_title'],$body);

$body = menuindex($doc['menuindex'],$doc['hidemenu']);
renderTr($_lang['resource_opt_menu_index'],$body);

echo renderSplit();

$parentname = getParentName($doc['parent'],$form_v['parent']);
$body = getParentForm($doc['parent'],$parentname);
renderTr($_lang['resource_parent'],$body);
?>
		</table>
<?php
if ($doc['type'] == 'document' || $_REQUEST['a'] == '4')
{
?>
		<!-- Content -->
		<div class="sectionHeader" id="content_header"><?php echo $_lang['resource_content']?></div>
		<div class="sectionBody" id="content_body">
<?php
	if (($_REQUEST['a'] == '4' || $_REQUEST['a'] == '27') && $use_editor == 1 && $doc['richtext'] == 1)
	{
		$htmlContent = $doc['content'];
?>
		<div>
			<textarea id="ta" name="ta" cols="" rows="" style="width:100%; height: 350px;"><?php echo htmlspecialchars($htmlContent)?></textarea>
			<span class="warning"><?php echo $_lang['which_editor_title']?></span>
			<select id="which_editor" name="which_editor" onchange="changeRTE();">
				<option value="none"><?php echo $_lang['none']?></option>
<?php
		// invoke OnRichTextEditorRegister event
		$evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
		if (is_array($evtOut))
		{
			$tpl = '<option value="[+editor+]" [+selected+]>[+editor+]</option>' . "\n";
			foreach ($evtOut as $editor)
			{
				$ph = array();
				$ph['editor']   = $editor;
				$ph['selected'] = ($which_editor == $editor) ? 'selected="selected"' : '';
				echo $modx->parseText($tpl, $ph);
			}
		}
?>
			</select>
		</div>
<?php
		$replace_richtexteditor = array('ta');
	}
	else
	{
		echo "\t".'<div><textarea class="phptextarea" id="ta" name="ta" style="width:100%; height: 400px;">',htmlspecialchars($doc['content']),'</textarea></div>'."\n";
	}
?>
		</div><!-- end .sectionBody -->
<?php
}
if (($doc['type'] == 'document' || $_REQUEST['a'] == '4') || ($doc['type'] == 'reference' || $_REQUEST['a'] == 72))
{
?>
		<!-- Template Variables -->
			<div class="sectionHeader" id="tv_header"><?php echo $_lang['settings_templvars']?></div>
			<div class="sectionBody tmplvars" id="tv_body">
<?php
	if (isset ($default_template))    $template = $default_template;
	else                              $template = $config['default_template'];
	
	$session_mgrRole = $_SESSION['mgrRole'];
	$where_docgrp = empty($docgrp) ? '' : " OR tva.documentgroup IN ({$docgrp})";
	
	$fields = "DISTINCT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value";
	$from = "
		[+prefix+]site_tmplvars                         AS tv 
		INNER JOIN [+prefix+]site_tmplvar_templates     AS tvtpl ON tvtpl.tmplvarid = tv.id 
		LEFT  JOIN [+prefix+]site_tmplvar_contentvalues AS tvc   ON tvc.tmplvarid   = tv.id AND tvc.contentid='{$id}'
		LEFT  JOIN [+prefix+]site_tmplvar_access        AS tva   ON tva.tmplvarid   = tv.id
		";
	$where = "
		tvtpl.templateid='{$template}'
		AND (1='{$session_mgrRole}' OR ISNULL(tva.documentgroup) {$where_docgrp})
		";
	$rs = $modx->db->select($fields,$from,$where,'tvtpl.rank,tv.rank, tv.id');
	$num_of_tv = $modx->db->getRecordCount($rs);
	if ($num_of_tv > 0)
	{
		echo "\t".'<table style="position:relative;" border="0" cellspacing="0" cellpadding="3" width="96%">'."\n";
		while($row = $modx->db->getRow($rs))
		{
			// Go through and display all Template Variables
			if ($row['type'] == 'richtext' || $row['type'] == 'htmlarea')
			{
				// Add richtext editor to the list
				if (is_array($replace_richtexteditor))
				{
					$replace_richtexteditor = array_merge($replace_richtexteditor, array('tv' . $row['id']));
				}
				else
				{
					$replace_richtexteditor = array('tv' . $row['id']);
				}
			}
			// splitter
			if ($i > 0 && $i < $num_of_tv) echo "\t\t",'<tr><td colspan="2"><div class="split"></div></td></tr>',"\n";
			
			// post back value
			if(array_key_exists('tv'.$row['id'], $form_v))
			{
				if($row['type'] == 'listbox-multiple') $tvPBV = implode('||', $form_v['tv'.$row['id']]);
				else                                   $tvPBV = $form_v['tv'.$row['id']];
			}
			else                                       $tvPBV = $row['value'];

			$zindex = ($row['type'] === 'date') ? 'z-index:100;' : '';
			if($row['type']!=='hidden')
			{
				echo '<tr><td valign="top" class="tvname"><span class="warning">'.$row['caption']."</span>\n".
			     '<br /><span class="comment">'.$row['description']."</span></td>\n".
                 '<td valign="top" style="position:relative;'.$zindex.'">'."\n".
                 $modx->renderFormElement($row['type'], $row['id'], $row['default_text'], $row['elements'], $tvPBV, '', $row)."\n".
			     "</td></tr>\n";
			}
			else
			{
				echo '<tr style="display:none;"><td colspan="2">' . $modx->renderFormElement('hidden', $row['id'], $row['default_text'], $row['elements'], $tvPBV, '', $row)."</td></tr>\n";
			}
		}
		echo "</table>\n";
	}
	else
	{
		// There aren't any Template Variables
		echo "\t<p>".$_lang['tmplvars_novars']."</p>\n";
	}
?>
			</div>
			<!-- end .sectionBody .tmplvars -->
<?php
}
?>

	</div><!-- end #tabGeneral -->

	<!-- Settings -->
	<div class="tab-page" id="tabSettings">
		<h2 class="tab"><?php echo $_lang['settings_page_settings']?></h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabSettings" ) );</script>

		<table width="99%" border="0" cellspacing="5" cellpadding="0">
		<?php $pub_disabled = disabled(!$modx->hasPermission('publish_document') || $id==$config['site_start']); ?>
			<tr style="height: 24px;">
				<td width="150"><span class="warning"><?php echo $_lang['resource_opt_published']?></span></td>
				<td>
<?php
$cond = (isset($doc['published']) && $doc['published']==1) || (!isset($doc['published']) && $publish_default==1);
echo input_checkbox('published',$cond);
echo input_hidden('published',$cond);
echo tooltip($_lang['resource_opt_published_help']);
?>
				</td>
			</tr>
			<tr style="height: 24px;">
				<td width="150"><span class="warning"><?php echo $_lang['page_data_publishdate']?></span></td>
				<td>
<?php
$doc['pub_date'] = (isset($doc['pub_date']) && $doc['pub_date']!='0') ? $modx->toDateFormat($doc['pub_date']) : '';
?>
				<input type="text" id="pub_date" <?php echo $pub_disabled ?> name="pub_date" class="DatePicker imeoff" value="<?php echo $doc['pub_date'];?>" />
                <a onclick="document.mutate.pub_date.value=''; documentDirty=true; return true;" style="cursor:pointer; cursor:hand;">
				<img src="<?php echo $_style["icons_cal_nodate"] ?>" alt="<?php echo $_lang['remove_date']?>" /></a>
				<?php echo tooltip($_lang['page_data_publishdate_help']);?>
				</td>
			</tr>
			<tr>
				<td></td>
				<td style="line-height:1;margin:0;color: #555;font-size:10px"><?php echo $config['datetime_format']; ?> HH:MM:SS</td>
			</tr>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $_lang['page_data_unpublishdate']?></span></td>
				<td>
<?php
$doc['unpub_date'] = (isset($doc['unpub_date']) && $doc['unpub_date']!='0') ? $modx->toDateFormat($doc['unpub_date']) : '';
?>
				<input type="text" id="unpub_date" <?php echo $pub_disabled ?> name="unpub_date" class="DatePicker imeoff" value="<?php echo $doc['unpub_date'];?>" onblur="documentDirty=true;" />
				<a onclick="document.mutate.unpub_date.value=''; documentDirty=true; return true;" style="cursor:pointer; cursor:hand">
				<img src="<?php echo $_style["icons_cal_nodate"] ?>" alt="<?php echo $_lang['remove_date']?>" /></a>
				<?php echo tooltip($_lang['page_data_unpublishdate_help']);?>
				</td>
			</tr>
			<tr>
				<td></td>
				<td style="line-height:1;margin:0;color: #555;font-size:10px"><?php echo $config['datetime_format']; ?> HH:MM:SS</td>
			</tr>
			<tr>
				<td colspan="2"><div class="split"></div></td>
			</tr>
		
<?php

if ($_SESSION['mgrRole'] == 1 || $_REQUEST['a'] != '73' || $_SESSION['mgrInternalKey'] == $doc['createdby'])
{
?>
			<tr style="height: 24px;"><td><span class="warning"><?php echo $_lang['resource_type']?></span></td>
				<td><select name="type" class="inputBox" style="width:200px">

                    <option value="document"<?php echo (($doc['type'] == 'document' || $_REQUEST['a'] == '85' || $_REQUEST['a'] == '4') ? ' selected="selected"' : "");?> ><?php echo $_lang["resource_type_webpage"];?></option>
                    <option value="reference"<?php echo (($doc['type'] == 'reference' || $_REQUEST['a'] == '72') ? ' selected="selected"' : "");?> ><?php echo $_lang["resource_type_weblink"];?></option>
					</select>
					<?php echo tooltip($_lang['resource_type_message']);?>
					</td>
				</tr>
<?php
	if($doc['type'] !== 'reference' && $_REQUEST['a'] !== '72')
	{
?>
			<tr style="height: 24px;"><td><span class="warning"><?php echo $_lang['page_data_contentType']?></span></td>
				<td><select name="contentType" class="inputBox" style="width:200px">
<?php
		if (!$doc['contentType']) $doc['contentType'] = 'text/html';
		
		$custom_contenttype = (isset ($custom_contenttype) ? $custom_contenttype : "text/html,text/plain,text/xml");
		$ct = explode(",", $custom_contenttype);
		for ($i = 0; $i < count($ct); $i++)
		{
			echo "\t\t\t\t\t".'<option value="'.$ct[$i].'"'.($doc['contentType'] == $ct[$i] ? ' selected="selected"' : '').'>'.$ct[$i]."</option>\n";
		}
	?>
				</select>
				<?php echo tooltip($_lang['page_data_contentType_help']);?>
				</td>
			</tr>
			<tr style="height: 24px;"><td><span class="warning"><?php echo $_lang['resource_opt_contentdispo']?></span></td>
				<td><select name="content_dispo" size="1" style="width:200px">
					<option value="0"<?php echo !$doc['content_dispo'] ? ' selected="selected"':''?>><?php echo $_lang['inline']?></option>
					<option value="1"<?php echo $doc['content_dispo']==1 ? ' selected="selected"':''?>><?php echo $_lang['attachment']?></option>
				</select>
				<?php echo tooltip($_lang['resource_opt_contentdispo_help']);?>
				</td>
			</tr>
<?php
	}
?>
			<tr>
				<td colspan="2"><div class="split"></div></td>
			</tr>
<?php
}
else
{
	if ($doc['type'] != 'reference' && $_REQUEST['a'] != '72')
	{
		// non-admin managers creating or editing a document resource
?>
            <input type="hidden" name="contentType" value="<?php echo isset($doc['contentType']) ? $doc['contentType'] : "text/html"?>" />
            <input type="hidden" name="type" value="document" />
            <input type="hidden" name="content_dispo" value="<?php echo isset($doc['content_dispo']) ? $doc['content_dispo'] : '0'?>" />
<?php
	}
	else
	{
		// non-admin managers creating or editing a reference (weblink) resource
?>
            <input type="hidden" name="type" value="reference" />
            <input type="hidden" name="contentType" value="text/html" />
<?php
	}
}//if mgrRole

$body  = input_text('link_attributes',to_safestr($doc['link_attributes']));
$body .= tooltip($_lang['link_attributes_help']);
renderTr($_lang['link_attributes'],$body);

?>

			<tr style="height: 24px;">
				<td width="150"><span class="warning"><?php echo $_lang['resource_opt_folder']?></span></td>
				<td>
<?php
$cond = ($doc['isfolder']==1||$_REQUEST['a']=='85');
echo input_checkbox('isfolder',$cond);
echo input_hidden('isfolder',$cond);
echo tooltip($_lang['resource_opt_folder_help']);
?>
				</td>
			</tr>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $_lang['resource_opt_richtext']?></span></td>
				<td>
<?php
	$disabled = ($use_editor!=1) ? ' disabled="disabled"' : '';
	$cond = (!isset($doc['richtext']) || $doc['richtext']!=0 || $_REQUEST['a']!='27');
	echo input_checkbox('richtext',$cond,$disabled);
	echo input_hidden('richtext',$cond);
	echo tooltip($_lang['resource_opt_richtext_help']);
?>
				</td>
			</tr>
			<tr style="height: 24px;">
				<td width="150"><span class="warning"><?php echo $_lang['track_visitors_title']?></span></td>
				<td>
<?php
$cond = ($doc['donthit']!=1);
echo input_checkbox('donthit',$cond);
echo input_hidden('donthit',!$cond);
echo tooltip($_lang['resource_opt_trackvisit_help']);
?>
				</td>
			</tr>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $_lang['page_data_searchable']?></span></td>
				<td>
<?php
$cond = ((isset($doc['searchable']) && $doc['searchable']==1) || (!isset($doc['searchable']) && $search_default==1));
echo input_checkbox('searchable',$cond);
echo input_hidden('searchable',$cond);
echo tooltip($_lang['page_data_searchable_help']);
?>
				</td>
			</tr>
<?php
if($doc['type'] !== 'reference' && $_REQUEST['a'] !== '72')
{
?>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $_lang['page_data_cacheable']?></span></td>
				<td>
<?php
	$cond = ((isset($doc['cacheable']) && $doc['cacheable']==1) || (!isset($doc['cacheable']) && $cache_default==1));
	$disabled = ($cache_type==0) ? ' disabled="disabled"' : '';
	echo input_checkbox('cacheable',$cond,$disabled);
	echo input_hidden('cacheable',$cond);
	echo tooltip($_lang['page_data_cacheable_help']);
?>
				</td>
			</tr>
<?php
}
?>
			<tr style="height: 24px;">
				<td><span class="warning"><?php echo $_lang['resource_opt_emptycache']?></span></td>
				<td>
<?php
$disabled = ($cache_type==0) ? ' disabled="disabled"' : '';
echo input_checkbox('syncsite',true,$disabled);
echo input_hidden('syncsite');
echo tooltip($_lang['resource_opt_emptycache_help']);
?>
				</td>
			</tr>
		</table>
	</div><!-- end #tabSettings -->

<?php
if ($modx->hasPermission('edit_doc_metatags') && isset($config['show_meta']) && $config['show_meta']==='1')
{
	// get list of site keywords
	$keywords = array();
	$ds = $modx->db->select('id,keyword', '[+prefix+]site_keywords', '', 'keyword ASC');
	$limit = $modx->db->getRecordCount($ds);
	if ($limit > 0)
	{
		while($row = $modx->db->getRow($ds))
		{
			$keywords[$row['id']] = $row['keyword'];
		}
	}
	// get selected keywords using document's id
	if (isset ($doc['id']) && count($keywords) > 0)
	{
		$keywords_selected = array();
		$ds = $modx->db->select('keyword_id', '[+prefix+]keyword_xref', "content_id='{$doc['id']}'");
		$limit = $modx->db->getRecordCount($ds);
		if ($limit > 0)
		{
			while($row = $modx->db->getRow($ds))
			{
				$keywords_selected[$row['keyword_id']] = ' selected="selected"';
			}
		}
	}
	
	// get list of site META tags
	$metatags = array();
	$ds = $modx->db->select('*', '[+prefix+]site_metatags');
	$limit = $modx->db->getRecordCount($ds);
	if ($limit > 0)
	{
		while($row = $modx->db->getRow($ds))
		{
			$metatags[$row['id']] = $row['name'];
		}
	}
	// get selected META tags using document's id
	if (isset ($doc['id']) && count($metatags) > 0)
	{
		$metatags_selected = array();
		$ds = $modx->db->select('metatag_id', '[+prefix+]site_content_metatags', "content_id='{$doc['id']}'");
		$limit = $modx->db->getRecordCount($ds);
		if ($limit > 0)
		{
			while($row = $modx->db->getRow($ds))
			{
				$metatags_selected[$row['metatag_id']] = ' selected="selected"';
			}
		}
	}
?>
	<!-- META Keywords -->
	<div class="tab-page" id="tabMeta">
		<h2 class="tab"><?php echo $_lang['meta_keywords']?></h2>
		<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabMeta" ) );</script>

		<table width="99%" border="0" cellspacing="5" cellpadding="0">
		<tr style="height: 24px;"><td><?php echo $_lang['resource_metatag_help']?><br /><br />
			<table border="0" style="width:inherit;">
			<tr>
				<td><span class="warning"><?php echo $_lang['keywords']?></span><br />
				<select name="keywords[]" multiple="multiple" size="16" class="inputBox" style="width: 200px;">
<?php
	$keys = array_keys($keywords);
	for ($i = 0; $i < count($keys); $i++)
	{
		$key = $keys[$i];
		$value = $keywords[$key];
		$selected = $keywords_selected[$key];
		echo "\t\t\t\t".'<option value="'.$key.'"'.$selected.'>'.$value."</option>\n";
	}
?>
				</select>
				<br />
				<input type="button" value="<?php echo $_lang['deselect_keywords']?>" onclick="clearKeywordSelection();" />
				</td>
				<td><span class="warning"><?php echo $_lang['metatags']?></span><br />
				<select name="metatags[]" multiple="multiple" size="16" class="inputBox" style="width: 220px;">
<?php
	$keys = array_keys($metatags);
	for ($i = 0; $i < count($keys); $i++)
	{
		$key = $keys[$i];
		$value = $metatags[$key];
		$selected = $metatags_selected[$key];
		echo "\t\t\t\t".'<option value="'.$key.'"'.$selected.'>'.$value."</option>\n";
	}
?>
				</select>
				<br />
				<input type="button" class="button" value="<?php echo $_lang['deselect_metatags']?>" onclick="clearMetatagSelection();" />
				</td>
			</tr>
			</table>
			</td>
		</tr>
		</table>
	</div><!-- end #tabMeta -->
<?php
}

/*******************************
 * Document Access Permissions */
if ($use_udperms == 1)
{
	$groupsarray = array();
	
	if($_REQUEST['a'] == '27')       $docid = $id;
	elseif(!empty($_REQUEST['pid'])) $docid = $_REQUEST['pid'];
	else                             $docid = $doc['parent'];
	
	if ($docid > 0)
	{
		// Load up, the permissions from the parent (if new document) or existing document
		$rs = $modx->db->select('id, document_group','[+prefix+]document_groups',"document='{$docid}'");
		while ($currentgroup = $modx->db->getRow($rs))
		{
			$groupsarray[] = $currentgroup['document_group'].','.$currentgroup['id'];
		}
		// Load up the current permissions and names
		$field = 'dgn.*, groups.id AS link_id';
		$from  = "[+prefix+]documentgroup_names AS dgn LEFT JOIN [+prefix+]document_groups AS groups ON groups.document_group = dgn.id  AND groups.document = {$docid}";
	}
	else
	{
		// Just load up the names, we're starting clean
		$field = '*, NULL AS link_id';
		$from  = '[+prefix+]documentgroup_names';
	}
	// Query the permissions and names from above
	$rs = $modx->db->select($field,$from,'','name');

	$isManager = $modx->hasPermission('access_permissions');
	$isWeb     = $modx->hasPermission('web_access_permissions');

	// Setup Basic attributes for each Input box
	$inputAttributes['type']    = 'checkbox';
	$inputAttributes['class']   = 'checkbox';
	$inputAttributes['name']    = 'docgroups[]';
	$inputAttributes['onclick'] = 'makePublic(false)';
	
	$permissions = array(); // New Permissions array list (this contains the HTML)
	$permissions_yes = 0; // count permissions the current mgr user has
	$permissions_no = 0; // count permissions the current mgr user doesn't have

	// retain selected doc groups between post
	if (isset($form_v['docgroups']))
		$groupsarray = array_merge($groupsarray, $form_v['docgroups']);

	// Loop through the permissions list
	while($row = $modx->db->getRow($rs))
	{
		// Create an inputValue pair (group ID and group link (if it exists))
		$inputValue = $row['id'].','.($row['link_id'] ? $row['link_id'] : 'new');
		$inputId    = 'group-'.$row['id'];

		$checked    = in_array($inputValue, $groupsarray);
		if ($checked) $notPublic = true; // Mark as private access (either web or manager)

		// Skip the access permission if the user doesn't have access...
		if ((!$isManager && $row['private_memgroup'] == '1') || (!$isWeb && $row['private_webgroup'] == '1'))
			continue;

		// Setup attributes for this Input box
		$inputAttributes['id']    = $inputId;
		$inputAttributes['value'] = $inputValue;
		if ($checked)
		        $inputAttributes['checked'] = 'checked';
		else    unset($inputAttributes['checked']);

		// Create attribute string list
		$inputString = array();
		foreach ($inputAttributes as $k => $v)
		{
			$inputString[] = $k.'="'.$v.'"';
		}

		// Make the <input> HTML
        $inputHTML = '<input '.implode(' ', $inputString).' />' . "\n";

		// does user have this permission?
		$from = "[+prefix+]membergroup_access mga, [+prefix+]member_groups mg";
		$where = "mga.membergroup = mg.user_group AND mga.documentgroup = {$row['id']} AND mg.member = {$_SESSION['mgrInternalKey']}";
		$rsp = $modx->db->select('COUNT(mg.id)',$from,$where);
		$count = $modx->db->getValue($rsp);
		
		if($count > 0) ++$permissions_yes;
		else           ++$permissions_no;
		
		$permissions[] = "\t\t".'<li>'.$inputHTML.'<label for="'.$inputId.'">'.$row['name'].'</label></li>';
	}
	// if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
	if($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0))
	{
		$permissions = array();
	}

	// See if the Access Permissions section is worth displaying...
	if (!empty($permissions))
	{
		// Add the "All Document Groups" item if we have rights in both contexts
		if ($isManager && $isWeb)
		{
			array_unshift($permissions,"\t\t".'<li><input type="checkbox" class="checkbox" name="chkalldocs" id="groupall"' . checked(!$notPublic) . ' onclick="makePublic(true);" /><label for="groupall" class="warning">' . $_lang['all_doc_groups'] . '</label></li>');
		// Output the permissions list...
		}
?>
<!-- Access Permissions -->
<div class="tab-page" id="tabAccess">
	<h2 class="tab" id="tab_access_header"><?php echo $_lang['access_permissions']?></h2>
	<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabAccess" ) );</script>
	<script type="text/javascript">
		/* <![CDATA[ */
		function makePublic(b) {
			var notPublic = false;
			var f = document.forms['mutate'];
			var chkpub = f['chkalldocs'];
			var chks = f['docgroups[]'];
			if (!chks && chkpub) {
				chkpub.checked=true;
				return false;
			} else if (!b && chkpub) {
				if (!chks.length) notPublic = chks.checked;
				else for (i = 0; i < chks.length; i++) if (chks[i].checked) notPublic = true;
				chkpub.checked = !notPublic;
			} else {
				if (!chks.length) chks.checked = (b) ? false : chks.checked;
				else for (i = 0; i < chks.length; i++) if (b) chks[i].checked = false;
				chkpub.checked = true;
			}
		}
		/* ]]> */
	</script>
	<p><?php echo $_lang['access_permissions_docs_message']?></p>
	<ul>
	<?php echo implode("\n", $permissions)."\n"; ?>
	</ul>
</div><!--div class="tab-page" id="tabAccess"-->
<?php
	} // !empty($permissions)
	elseif($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0)
           && ($_SESSION['mgrPermissions']['access_permissions'] == 1
           || $_SESSION['mgrPermissions']['web_access_permissions'] == 1))
	{
?>
	<p><?php echo $_lang["access_permissions_docs_collision"];?></p>
<?php
	}
}
/* End Document Access Permissions *
 ***********************************/
?>

<input type="submit" name="save" style="display:none" />
<?php

	// invoke OnDocFormRender event
	$evtOut = $modx->invokeEvent('OnDocFormRender', array(
		'id' => $id,
	));
	if (is_array($evtOut)) echo implode('', $evtOut);
?>
</div><!--div class="tab-pane" id="documentPane"-->
</div><!--div class="sectionBody"-->
</fieldset>
</form>

<script type="text/javascript">
    storeCurTemplate();
</script>
<?php
if (($_REQUEST['a'] == '4' || $_REQUEST['a'] == '27' || $_REQUEST['a'] == '72') && $use_editor == 1 && is_array($replace_richtexteditor) && 0<count($replace_richtexteditor))
{
	// invoke OnRichTextEditorInit event
	$evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
		'editor' => $which_editor,
		'elements' => $replace_richtexteditor
	));
	if (is_array($evtOut)) echo implode('', $evtOut);
}

function to_safestr($str)
{
	return htmlspecialchars(stripslashes($str));
}

function input_text($name,$value,$other='',$maxlength='255')
{
	global $modx;
	
	$ph['name']      = $name;
	$ph['value']     = $value;
	$ph['maxlength'] = $maxlength;
	$ph['other']     = $other;
	$ph['class']     = 'inputBox';
	switch($name)
	{
		case 'menuindex':
			$ph['class'] .= ' number imeoff';
			break;
	}
	
	$tpl = '<input name="[+name+]" id="field_[+name+]" type="text" maxlength="[+maxlength+]" value="[+value+]" class="[+class+]" [+other+] />';
	return $modx->parseText($tpl,$ph);
}

function input_checkbox($name,$checked,$other='')
{
	global $modx;
	$ph['name']    = $name;
	$ph['checked'] = ($checked) ? 'checked="checked"' : '';
	$ph['other']   = $other;
	$ph['resetpubdate'] = ($name == 'published') ? 'resetpubdate();' : '';
	if($name === 'published')
	{
		$id = (isset($_REQUEST['id'])) ? (int)$_REQUEST['id'] : 0;
		if(!$modx->hasPermission('publish_document') || $id===$modx->config['site_start'])
		{
			$ph['other'] = 'disabled="disabled"';
		}
	}
	$tpl = '<input name="[+name+]check" type="checkbox" class="checkbox" [+checked+] onclick="changestate(document.mutate.[+name+]);[+resetpubdate+]" [+other+] />';
	return $modx->parseText($tpl,$ph);
}

function checked($cond=false)
{
	if($cond) return ' checked="checked"';
}

function disabled($cond=false)
{
	if($cond) return ' disabled="disabled"';
}

function tooltip($msg)
{
	global $modx,$_style;
	
	$ph['icons_tooltip'] = "'{$_style['icons_tooltip']}'";
	$ph['icons_tooltip_over'] = $_style['icons_tooltip_over'];
	$ph['msg'] = $msg;
	$tpl = '&nbsp;&nbsp;<img src="[+icons_tooltip_over+]" alt="[+msg+]" title="[+msg+]" onclick="alert(this.alt);" style="cursor:help;" class="tooltip" />';
	return $modx->parseText($tpl,$ph);
}

function input_hidden($name,$cond=true)
{
	global $modx;
	
	$ph['name']  = $name;
	$ph['value'] = ($cond) ? '1' : '0';
	$tpl = '<input type="hidden" name="[+name+]" class="hidden" value="[+value+]" />';
	return $modx->parseText($tpl,$ph);
}

function ab_preview()
{
	global $modx, $_style, $_lang, $id;
	$tpl = '<li id="Button5"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	$actionurl = $modx->makeUrl($id,'','','full');
	$ph['onclick'] = "openprev('$actionurl');return false;";
	$ph['icon'] = $_style["icons_preview_resource"];
	$ph['alt'] = 'preview resource';
	$ph['label'] = $_lang['preview'];
	return $modx->parseText($tpl,$ph);
}

function ab_save()
{
	global $modx, $_style, $_lang;
	
	if(!$modx->hasPermission('save_document')) return;
	$tpl = '<li id="Button1"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a>[+select+]</li>';
	$ph['onclick'] = "documentDirty=false; document.mutate.action='index.php';document.mutate.target='main'; document.mutate.mode.value=" . (int)$_REQUEST['a'] . ";document.mutate.save.click();";
	$ph['icon'] = $_style["icons_save"];
	$ph['alt'] = 'icons_save';
	$ph['label'] = $_lang['update'];
	$ph['select'] = '<span class="and"> + </span><select id="stay" name="stay">';
	if ($modx->hasPermission('new_document'))
	{
		$selected = $_REQUEST['stay']=='1' ? ' selected=""' : '';
		$ph['select'] .= '<option id="stay1" value="1" ' . $selected . ' >' . $_lang['stay_new'] . '</option>';
	}
	$selected = $_REQUEST['stay']=='2' ? ' selected="selected"' : '';
	$ph['select'] .= '<option id="stay2" value="2" ' . $selected . ' >' . $_lang['stay'] . '</option>';
	$selected = $_REQUEST['stay']=='' ? ' selected=""' : '';
	$ph['select'] .= '<option id="stay3" value="" ' . $selected . '>' . $_lang['close'] . '</option></select>';
	
	return $modx->parseText($tpl,$ph);
}

function ab_cancel()
{
	global $modx, $_style, $_lang, $doc, $id;
	$tpl = '<li id="Button4"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	$ph['icon'] = $_style["icons_cancel"];
	$ph['alt'] = 'icons_cancel';
	$ph['label'] = $_lang['cancel'];
	if(isset($doc['parent']) && $doc['parent']!=='0')
	{
		if($doc['isfolder']=='0') $href = "a=3&id={$doc['parent']}&tab=0";
		else                          $href = "a=3&id={$id}&tab=0";
	}
	elseif($doc['isfolder']=='1' && $doc['parent']=='0')
	{
		$href = "a=3&id={$id}&tab=0";
	}
	elseif($_GET['pid'])
	{
		$_GET['pid'] = intval($_GET['pid']);
		$href = "a=3&id={$_GET['pid']}&tab=0";
	}
	else $href = "a=2";
	$ph['onclick'] = "document.location.href='index.php?{$href}';";
	
	return $modx->parseText($tpl,$ph);
}

function ab_move()
{
	global $modx, $_style, $_lang;
	if(!$modx->hasPermission('save_document')) return;
	$tpl = '<li id="Button2"><a href="#" onclick="movedocument();"><img src="[+icon+]" /> [+label+]</a></li>';
	$ph['icon'] = $_style["icons_move_document"];
	$ph['label'] = $_lang['move'];
	return $modx->parseText($tpl,$ph);
}

function ab_duplicate()
{
	global $modx, $_style, $_lang;
	if(!$modx->hasPermission('new_document')) return;
	$tpl = '<li id="Button6"><a href="#" onclick="duplicatedocument();"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	$ph['icon'] = $_style["icons_resource_duplicate"];
	$ph['alt'] = 'icons_resource_duplicate';
	$ph['label'] = $_lang['duplicate'];
	return $modx->parseText($tpl,$ph);
}

function ab_delete()
{
	global $modx, $_style, $_lang, $doc;
	if(!$modx->hasPermission('delete_document')) return;
	if(!$modx->hasPermission('save_document')) return;
	$tpl = '<li id="Button3"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	if($doc['deleted'] === '0')
	{
		$ph['onclick'] = 'deletedocument();';
		$ph['icon'] = $_style["icons_delete_document"];
		$ph['alt'] = 'icons_delete_document';
		$ph['label'] = $_lang['delete'];
	}
	else
	{
		$ph['onclick'] = 'undeletedocument();';
		$ph['icon'] = $_style["icons_undelete_resource"];
		$ph['alt'] = 'icons_undelete_document';
		$ph['label'] = $_lang['undelete_resource'];
	}
	return $modx->parseText($tpl,$ph);
}

function get_alias_path($id)
{
	global $modx;

	$pid = intval($_REQUEST['pid']);
	if(!$modx->aliasListing) $modx->setAliasListing();
	
	if($modx->config['use_alias_path']==='0') $path = '';
	elseif($pid)
	{
		if($modx->aliasListing[$pid]['path'])
		{
			$path = $modx->aliasListing[$pid]['path'] . '/' . $modx->aliasListing[$pid]['alias'];
		}
		else $path = $modx->aliasListing[$pid]['alias'];
	}
	elseif($id) $path = $modx->aliasListing[$id]['path'];
	else        $path = '';
	if($path!=='') $path = $modx->config['base_url'] . $path . '/';
	else           $path = $modx->config['base_url'];
	
	if(30 < strlen($path)) $path .= '<br />';
	return $path;
}

function get_scr_change_url_suffix($suffix)
{
	$scr = <<< EOT
	<script type="text/javascript">
	function change_url_suffix() {
		var a = document.getElementById("field_alias");
		var s = document.getElementById("url_suffix");
		if(0 < a.value.indexOf('.')) s.innerHTML = '';
		else s.innerHTML = '{$suffix}';
	}
	</script>
EOT;
	return $scr;
}

function renderTr($head, $body,$rowstyle='')
{
	global $modx;
	
	if(!is_array($head)) {
		$ph['head'] = $head;
		$ph['extra_head'] = '';
	}
	else {
		$ph['head'] = $head[0];
		$ph['extra_head'] = "\n" . $head[1];
	}
	if(is_array($body)) $body = join("\n", $body);
	$ph['body'] = $body;
	$ph['rowstyle'] = $rowstyle;
	
	$tpl =<<< EOT
	<tr style="height: 24px;[+rowstyle+]">
		<td width="120" align="left">
			<span class="warning">[+head+]</span>[+extra_head+]
		</td>
		<td>
			[+body+]
		</td>
	</tr>
EOT;
	echo $modx->parseText($tpl, $ph);
}

function getDefaultTemplate($template)
{
	global $modx;
	
    if (isset($_REQUEST['newtemplate'])) return $_REQUEST['newtemplate'];
    elseif(isset($template))  return $template;
    
	switch($modx->config['auto_template_logic'])
	{
		case 'sibling':
			if(!isset($_GET['pid']) || empty($_GET['pid']))
		    {
		    	$site_start = $modx->config['site_start'];
		    	$where = "sc.isfolder=0 AND sc.id!='{$site_start}'";
		    	$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', $where, 'menuindex', 'ASC', 1);
		    	if(isset($sibl[0]['template']) && $sibl[0]['template']!=='') $default_template = $sibl[0]['template'];
			}
			else
			{
				$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 1, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
				if(isset($sibl[0]['template']) && $sibl[0]['template']!=='') $default_template = $sibl[0]['template'];
				else
				{
					$sibl = $modx->getDocumentChildren($_REQUEST['pid'], 0, 0, 'template', 'isfolder=0', 'menuindex', 'ASC', 1);
					if(isset($sibl[0]['template']) && $sibl[0]['template']!=='') $default_template = $sibl[0]['template'];
				}
			}
			break;
		case 'parent':
			if (isset($_REQUEST['pid']) && !empty($_REQUEST['pid']))
			{
				$parent = $modx->getPageInfo($_REQUEST['pid'], 0, 'template');
				if(isset($parent['template'])) $default_template = $parent['template'];
			}
			break;
		case 'system':
		default: // default_template is already set
			$default_template = $modx->config['default_template'];
	}
	if(!isset($default_template)) $default_template = $modx->config['default_template']; // default_template is already set
	
	return $default_template;
}

// check permissions
function checkPermissions($id) {
	global $modx, $_lang, $e;
	
	switch ($_REQUEST['a']) {
		case 27:
			if (!$modx->hasPermission('edit_document')) {
				$modx->config['remember_last_tab'] = 0;
				$e->setError(3);
				$e->dumpError();
			}
			$modx->manager->remove_locks('27');
			break;
		case 85:
		case 72:
		case 4:
			if (!$modx->hasPermission('new_document')) {
				$e->setError(3);
				$e->dumpError();
			} elseif(isset($_REQUEST['pid']) && $_REQUEST['pid'] != '0') {
				// check user has permissions for parent
				$targetpid = empty($_REQUEST['pid']) ? 0 : $_REQUEST['pid'];
				if (!$modx->checkPermissions($targetpid)) {
					$e->setError(3);
					$e->dumpError();
				}
			}
			break;
		default:
			$e->setError(3);
			$e->dumpError();
	}
	
	if ($action == 27 && !$modx->checkPermissions($id))
	{
		//editing an existing document
		// check permissions on the document
?>
<br /><br />
<div class="section">
<div class="sectionHeader"><?php echo $_lang['access_permissions']?></div>
<div class="sectionBody">
	<p><?php echo $_lang['access_permission_denied']?></p>
</div>
</div>
	<?php
		include(MODX_CORE_PATH . 'footer.inc.php');
		exit;
	}
}

function checkDocLock($id) {
	global $modx, $_lang, $e;
	
	// Check to see the document isn't locked
	$rs = $modx->db->select('internalKey, username','[+prefix+]active_users',"action=27 AND id='{$id}'");
	if (1 < $modx->db->getRecordCount($rs))
	{
		while($row = $modx->db->getRow($rs))
		{
			if ($row['internalKey'] != $modx->getLoginUserID())
			{
				$msg = sprintf($_lang['lock_msg'], $row['username'], $_lang['resource']);
				$e->setError(5, $msg);
				$e->dumpError();
			}
		}
	}
}

// get document groups for current user
function getDocgrp() {
	if ($_SESSION['mgrDocgroups'])
		return implode(',', $_SESSION['mgrDocgroups']);
	else return array();
}

function getContentFromDB($id,$docgrp) {
	global $modx,$e;
	
	if($id==='0') return array();
	
	$access  = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
	$access .= empty($docgrp) ? '' : " OR dg.document_group IN ({$docgrp})";
	$from = "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document=sc.id";
	$rs = $modx->db->select('DISTINCT sc.*', $from, "sc.id='{$id}' AND ({$access})");
	$limit = $modx->db->getRecordCount($rs);
	if ($limit > 1)
	{
		$e->setError(6);
		$e->dumpError();
	}
	if ($limit < 1)
	{
		$e->setError(3);
		$e->dumpError();
	}
	return $modx->db->getRow($rs);
}

// restore saved form
function mergeContent($db_v,$form_v) {
	global $modx;
	
	if ($modx->manager->hasFormValues())
	{
		$modx->manager->loadFormValues();
		$formRestored = true;
	}
	$formRestored = false;
	
	// retain form values if template was changed
	// edited to convert pub_date and unpub_date
	// sottwell 02-09-2006
	if ($formRestored == false && !isset ($_REQUEST['newtemplate']))
		return $db_v;
	else
	{
		$doc = array_merge($db_v, $form_v);
		$doc['content'] = $form_v['ta'];
		
		if (empty ($doc['pub_date']))
			unset ($doc['pub_date']);
		else
			$doc['pub_date'] = $modx->toTimeStamp($doc['pub_date']);
		
		if (empty ($doc['unpub_date']))
			unset ($doc['unpub_date']);
		else
			$doc['unpub_date'] = $modx->toTimeStamp($doc['unpub_date']);
	}
	
	return $doc;
}

function checkViewUnpubDocPerm($published,$editedby) {
	global $modx;
	
	if($modx->hasPermission('view_unpublished')) return;
	if($published!=='0')                         return;
	
	$userid = $modx->getLoginUserID();
	if ($userid != $editedby) {
		$modx->config['remember_last_tab'] = 0;
		$e->setError(3);
		$e->dumpError();
	}
}

// increase menu index if this is a new document
function getMenuIndexAtNew($menuindex) {
	global $modx;
	if (!empty($_REQUEST['id'])) return $menuindex;
	
	if (is_null($modx->config['auto_menuindex']) || $modx->config['auto_menuindex'])
	{
		$pid = intval($_REQUEST['pid']);
		return $modx->db->getValue($modx->db->select('count(id)','[+prefix+]site_content',"parent='{$pid}'")) + 1;
	}
	else return '0';
}

function getAliasAtNew($alias) {
	global $modx;
	
	$pid = $_REQUEST['pid'] ? $_REQUEST['pid'] : '0';
	if (empty($alias) && $modx->config['automatic_alias'] === '2') {
		return $modx->manager->get_alias_num_in_folder(0,$pid);}
	else return $alias;
}

function getRteAtNew($richtext) {
	global $modx;
	if($_REQUEST['a'] == '4' || $_REQUEST['a'] == '72')
		return $modx->config['use_editor'];
	else return $richtext;
}

function getJScripts() {
	global $modx,$_lang,$_style,$action;
	$tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/jscripts.tpl');
	$dayNames   = "['" . join("','",explode(',',$_lang['day_names'])) . "']";
	$monthNames = "['" . join("','",explode(',',$_lang['month_names'])) . "']";
	if(!isset($modx->config['imanager_url']))
		$modx->config['imanager_url'] = "{$base_url}manager/media/browser/mcpuk/browser.php?Type=images";
	
	if(!isset($modx->config['fmanager_url']))
		$modx->config['fmanager_url'] = "{$base_url}manager/media/browser/mcpuk/browser.php?Type=files";
	
	$ph['imanager_url'] = $modx->config['imanager_url'];
	$ph['fmanager_url'] = $modx->config['fmanager_url'];
	$ph['preview_mode'] = $modx->config['preview_mode'] ? $modx->config['preview_mode'] : '0';
	$ph['datepicker_offset'] = $modx->config['datepicker_offset'];
	$ph['datetime_format'] = $modx->config['datetime_format'];
	$ph['dayNames'] = $dayNames;
	$ph['monthNames'] = $monthNames;
	$ph['lang_confirm_delete_resource'] = $_lang['confirm_delete_resource'];
	$ph['lang_confirm_undelete'] = $_lang['confirm_undelete'];
	$ph['id'] = $_REQUEST['id'];
	$ph['lang_mutate_content.dynamic.php1'] = $_lang['mutate_content.dynamic.php1'];
	$ph['style_tree_folder'] = $_style["tree_folder"];
	$ph['style_icons_set_parent'] = $_style["icons_set_parent"];
	$ph['style_tree_folder'] = $_style["tree_folder"];
	$ph['lang_confirm_resource_duplicate'] = $_lang['confirm_resource_duplicate'];
	$ph['lang_illegal_parent_self'] = $_lang['illegal_parent_self'];
	$ph['lang_illegal_parent_child'] = $_lang['illegal_parent_child'];
	$ph['action'] = $action;
	
	return $modx->parseText($tpl,$ph);
}

function get_template_options($doc) {
	global $modx, $_lang;
	
	$options = '';
	$from = "[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id";
	$rs = $modx->db->select('t.templatename, t.id, c.category', $from,'', 'c.category, t.templatename ASC');
	
	$default_template = getDefaultTemplate($doc['template']);
	
	$currentCategory = '';
	$closeOptGroup = false;
	
	while ($row = $modx->db->getRow($rs))
	{
		$each_category = $row['category'];
		if($each_category == null) $each_category = $_lang["no_category"];
		
		if($each_category != $currentCategory)
		{
			if($closeOptGroup) $options .= "</optgroup>\n";
			
			$options .= "<optgroup label=\"{$each_category}\">\n";
			$closeOptGroup = true;
		}
		else $closeOptGroup = false;
		
		$selectedtext = ($row['id']==$default_template) ? ' selected="selected"' : '';
		
		$options .= '<option value="'.$row['id'].'"'.$selectedtext.'>'.$row['templatename']."</option>\n";
		$currentCategory = $each_category;
	}
	if($each_category != '') $options .= "</optgroup>\n";
	return $options;
}

function menuindex($menuindex, $hidemenu) {
	global $modx, $_lang;
	$tpl = <<< EOT
<table cellpadding="0" cellspacing="0" style="width:333px;">
	<tr>
		<td style="white-space:nowrap;">
			[+menuindex+]
			<input type="button" value="&lt;" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();" />
			<input type="button" value="&gt;" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();" />
			[+resource_opt_menu_index_help+]
		</td>
		<td style="text-align:right;">
			<span class="warning">[+resource_opt_show_menu+]</span>&nbsp;
			[+hidemenu+]
			[+hidemenu_hidden+]
			[+resource_opt_show_menu_help+]
		</td>
	</tr>
</table>
EOT;
	$ph = array();
	$ph['menuindex'] = input_text('menuindex',$menuindex,'style="width:40px;"','5');
	$ph['resource_opt_menu_index_help'] = tooltip($_lang['resource_opt_menu_index_help']);
	$ph['resource_opt_show_menu'] = $_lang['resource_opt_show_menu'];
	$cond = ($hidemenu!=1);
	$ph['hidemenu'] = input_checkbox('hidemenu',$cond);
	$ph['hidemenu_hidden'] = input_hidden('hidemenu',!$cond);
	$ph['resource_opt_show_menu_help'] = tooltip($_lang['resource_opt_show_menu_help']);
	return $modx->parseText($tpl, $ph);
}

function renderSplit() {
	$tpl = <<< EOT
<tr>
	<td colspan="2"><div class="split"></div></td>
</tr>
EOT;
	return $tpl;
}

function getParentName(&$v_parent, $dbv_parent) {
	global $modx;
	
	$parentlookup = false;
	$parentname   = $modx->config['site_name'];
	if (isset ($_REQUEST['id']))
		if ($v_parent != 0)             $parentlookup = $v_parent;
	elseif (isset ($_REQUEST['pid']))
		if ($_REQUEST['pid'] != 0)      $parentlookup = $_REQUEST['pid'];
	elseif (isset($form_v['parent']))
		if ($form_v['parent'] != 0)     $parentlookup = $form_v['parent'];
	else                                $v_parent = 0;
		                            
	if($parentlookup !== false && preg_match('@^[0-9]+$@', $parentlookup))
	{
		$rs = $modx->db->select('pagetitle','[+prefix+]site_content',"id='{$parentlookup}'");
		$limit = $modx->db->getRecordCount($rs);
		if ($limit != 1)
		{
			$e->setError(8);
			$e->dumpError();
		}
		$parentrs = $modx->db->getRow($rs);
		$parentname = $parentrs['pagetitle'];
	}
	
	return $parentname;
}

function getParentForm($parent,$pname) {
	global $modx,$_lang,$_style;
	
	$tpl = <<< EOT
&nbsp;<img alt="tree_folder" name="plock" src="[+icon_tree_folder+]" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" />
<b><span id="parentName" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" >
[+pid+] ([+pname+])</span></b>
[+tooltip+]
<input type="hidden" name="parent" value="[+pid+]" />
EOT;
	$ph['pid'] = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $doc['parent'];
	$ph['pname'] = $pname;
	$ph['tooltip'] = tooltip($_lang['resource_parent_help']);
	$ph['icon_tree_folder'] = $_style['tree_folder'];
	return $modx->parseText($tpl,$ph);
}
