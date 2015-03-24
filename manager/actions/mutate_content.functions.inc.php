<?php

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
		$id = (isset($_REQUEST['id'])&&preg_match('@^[1-9][0-9]*$@',$_REQUEST['id'])) ? $_REQUEST['id'] : 0;
		
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

function ab_preview($id)
{
	global $modx, $_style, $_lang;
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
	$ph['onclick'] = "gotosave=true;documentDirty=false; document.mutate.action='index.php';document.mutate.target='main';document.mutate.save.click();";
	$ph['icon'] = $_style["icons_save"];
	$ph['alt'] = 'icons_save';
	$ph['label'] = $_lang['update'];
	
	$ph['select'] = '<span class="and"> + </span><select id="stay" name="stay">%s</select>';
	
	$selected = array(1=>'', 2=>'', 3=>'');
	if ($modx->hasPermission('new_document')
		&& $_REQUEST['stay']=='1') $selected[1] = 'selected';
	elseif($_REQUEST['stay']=='2') $selected[2] = 'selected';
	elseif($_REQUEST['stay']=='')  $selected[3] = 'selected';
	
	if ($modx->hasPermission('new_document'))
		$option[] = sprintf('<option id="stay1" value="1" %s >%s</option>', $selected[1], $_lang['stay_new']);
	
	$option[] = sprintf('<option id="stay2" value="2" %s >%s</option>'    , $selected[2], $_lang['stay']);
	$option[] = sprintf('<option id="stay3" value="" %s >%s</option>'     , $selected[3], $_lang['close']);
	
	$ph['select'] = sprintf($ph['select'], join("\n", $option));
	
	return $modx->parseText($tpl,$ph);
}

function ab_cancel($id)
{
	global $modx, $_style, $_lang, $docObject;
	
	$tpl = '<li id="Button4"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	$ph['icon'] = $_style["icons_cancel"];
	$ph['alt'] = 'icons_cancel';
	$ph['label'] = $_lang['cancel'];
	if($docObject['isfolder']=='1')
		$href = "a=120&id={$id}";
	elseif(!empty($docObject['parent']))
		$href = "a=120&id={$docObject['parent']}";
	else
		$href = "a=2";
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
	global $modx, $_style, $_lang, $docObject;
	
	if(!$modx->hasPermission('delete_document')) return;
	if(!$modx->hasPermission('save_document')) return;
	$tpl = '<li id="Button3"><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
	if($docObject['deleted'] === '0')
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
		$i = 0;
		foreach($head as $v) {
			if($i===0) $ph['head'] = $v;
			else $extra_head[] = $v;
			$i++;
		}
		$ph['extra_head'] = join("\n", $extra_head);
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
	return $modx->parseText($tpl, $ph);
}

function getDefaultTemplate()
{
	global $modx;
	
    if (isset($_REQUEST['newtemplate']))  return $_REQUEST['newtemplate'];
	$pid = (isset($_REQUEST['pid']) && !empty($_REQUEST['pid'])) ? $_REQUEST['pid'] : '0';
	$site_start = $modx->config['site_start'];

	if($modx->config['auto_template_logic']==='sibling') :
		$where = "id!='{$site_start}' AND isfolder=0 AND parent='{$pid}'";
		$orderby = 'published DESC,menuindex ASC';
		$rs = $modx->db->select('template', '[+prefix+]site_content', $where, $orderby, '1');
	elseif($modx->config['auto_template_logic']==='parent' && $pid!=0) :
		$rs = $modx->db->select('template','[+prefix+]site_content',"id='{$pid}'");
	endif;
		
	if(isset($rs)&&$modx->db->getRecordCount($rs)==1) {
		$row = $modx->db->getRow($rs);
		$default_template = $row['template'];
	}
	
	if(!isset($default_template))
		$default_template = $modx->config['default_template']; // default_template is already set
	
	return $default_template;
}

// check permissions
function checkPermissions($id) {
	global $modx, $_lang, $e;
	
	$isAllowed = $modx->manager->isAllowed($id);
	if (!isset($_GET['pid'])&&!$isAllowed)
	{
		$e->setError(3);
		$e->dumpError();
	}
	
	switch ($_REQUEST['a']) {
		case 27:
			if (!$modx->hasPermission('edit_document')) {
				$modx->config['remember_last_tab'] = 0;
				$e->setError(3);
				$e->dumpError();
			}
			$modx->manager->remove_locks('27');
			break;
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
	
	if ($_REQUEST['a'] == 27 && !$modx->checkPermissions($id))
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
	if (isset($_SESSION['mgrDocgroups'])||!empty($_SESSION['mgrDocgroups']))
		return implode(',', $_SESSION['mgrDocgroups']);
	else return '';
}

function getValuesFromDB($id,$docgrp) {
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
function mergeValues($initial_v,$db_v,$form_v) {
	global $modx;
	
	if ($modx->manager->hasFormValues())
	{
		$form_v = $modx->manager->loadFormValues();
		$formRestored = true;
	}
	else $formRestored = false;
	
	// retain form values if template was changed
	// edited to convert pub_date and unpub_date
	// sottwell 02-09-2006
	if ($formRestored == false && !isset ($_REQUEST['newtemplate'])):
		$docObject = array_merge($initial_v,$db_v);
	else:
		$docObject = array_merge($initial_v,$db_v, $form_v);
		if(isset($form_v['ta'])) $docObject['content'] = $form_v['ta'];
		
	endif;
	
	if (!isset($docObject['pub_date'])||empty($docObject['pub_date']))
		$docObject['pub_date'] = '';
	else
	{
		$docObject['pub_date'] = $modx->toTimeStamp($docObject['pub_date']);
		$docObject['pub_date'] = $modx->toDateFormat($docObject['pub_date']);
	}
	
	if (!isset($docObject['unpub_date'])||empty($docObject['unpub_date']))
		$docObject['unpub_date'] = '';
	else
	{
		$docObject['unpub_date'] = $modx->toTimeStamp($docObject['unpub_date']);
		$docObject['unpub_date'] = $modx->toDateFormat($docObject['unpub_date']);
	}
	
	return $docObject;
}

function checkViewUnpubDocPerm($published,$editedby) {
	global $modx;
	
	if($_REQUEST['a']!=='27') return;
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
function getMenuIndexAtNew() {
	global $modx;
	if ($modx->config['auto_menuindex']==='1')
	{
		$pid = isset($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;
		return $modx->db->getValue($modx->db->select('count(id)','[+prefix+]site_content',"parent='{$pid}'")) + 1;
	}
	else return '0';
}

function getAliasAtNew() {
	global $modx;
	
	$pid = $_REQUEST['pid'] ? $_REQUEST['pid'] : '0';
	if($modx->config['automatic_alias'] === '2')
		return $modx->manager->get_alias_num_in_folder(0,$pid);
	else return '';
}

function getJScripts() {
	global $modx,$_lang,$_style,$action;
	$tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/jscripts.tpl');
	$dayNames   = "['" . join("','",explode(',',$_lang['day_names'])) . "']";
	$monthNames = "['" . join("','",explode(',',$_lang['month_names'])) . "']";
	$base_url = $modx->config['base_url'];
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

function get_template_options() {
	global $modx, $_lang, $docObject;
	
	$options = '';
	$from = '[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id';
	$field = sprintf("t.templatename, t.id, IFNULL(c.category,'%s') AS category", $_lang['no_category']);
	$rs = $modx->db->select($field, $from,'', 'c.category, t.templatename ASC');
	
	$currentCategory = '';
	$closeOptGroup = false;
	
	while ($row = $modx->db->getRow($rs))
	{
		$each_category = $row['category'];
		
		if($each_category != $currentCategory)
		{
			if($closeOptGroup) $options .= "</optgroup>\n";
			
			$options .= sprintf('<optgroup label="%s">',$each_category);
			$closeOptGroup = true;
		}
		else $closeOptGroup = true;
		
		$selected = ($row['id']==$docObject['template']) ? ' selected' : '';
		$options .= sprintf('<option value="%s" %s>%s</option>',$row['id'],$selected,$row['templatename']);
		$currentCategory = $each_category;
	}
	if($each_category != '') $options .= "</optgroup>\n";
	return $options;
}

function menuindex() {
	global $modx, $docObject, $_lang;
	
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
	$ph['menuindex'] = input_text('menuindex',$docObject['menuindex'],'style="width:62px;"','8');
	$ph['resource_opt_menu_index_help'] = tooltip($_lang['resource_opt_menu_index_help']);
	$ph['resource_opt_show_menu'] = $_lang['resource_opt_show_menu'];
	$cond = ($docObject['hidemenu']!=1);
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

function getParentName(&$v_parent) {
	global $modx;
	
	$parentlookup = false;
	$parentname   = $modx->config['site_name'];
	if (isset($_REQUEST['id'])) {
		if ($v_parent != 0)            $parentlookup = $v_parent;
	}
	elseif(isset($_REQUEST['pid'])) {
		if($_REQUEST['pid'] != 0)      $parentlookup = $_REQUEST['pid'];
	}
	elseif(isset($v_parent)) {
		if($v_parent != 0)             $parentlookup = $v_parent;
	}
	else                                $v_parent = 0;
	
	if($parentlookup !== false && preg_match('@^[1-9][0-9]*$@', $parentlookup)):
		$rs = $modx->db->select('pagetitle','[+prefix+]site_content',"id='{$parentlookup}'");
		$limit = $modx->db->getRecordCount($rs);
		if ($limit != 1):
			$e->setError(8);
			$e->dumpError();
		endif;
		$parentrs = $modx->db->getRow($rs);
		$parentname = $parentrs['pagetitle'];
	endif;
	
	return $parentname;
}

function getParentForm($pname) {
	global $modx,$docObject,$_lang,$_style;
	
	$tpl = <<< EOT
&nbsp;<img alt="tree_folder" name="plock" src="[+icon_tree_folder+]" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" />
<b><span id="parentName" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" >
[+pid+] ([+pname+])</span></b>
[+tooltip+]
<input type="hidden" name="parent" value="[+pid+]" />
EOT;
	$ph['pid'] = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $docObject['parent'];
	$ph['pname'] = $pname;
	$ph['tooltip'] = tooltip($_lang['resource_parent_help']);
	$ph['icon_tree_folder'] = $_style['tree_folder'];
	return $modx->parseText($tpl,$ph);
}

function getActionButtons($id) {
	global $modx;
	
	$tpl = <<< EOT
<div id="actions">
	<ul class="actionButtons">
		[+saveButton+]
		[+moveButton+]
		[+duplicateButton+]
		[+deleteButton+]
		[+previewButton+]
		[+cancelButton+]
	</ul>
</div>
EOT;
	$ph['saveButton']      = ab_save();
	if ($_REQUEST['a'] !== '4' && $_REQUEST['a'] !== '72' && $id != $config['site_start']) {
		$ph['moveButton']      = ab_move();
		$ph['duplicateButton'] = ab_duplicate();
		$ph['deleteButton']    = ab_delete();
	}
	if ($_REQUEST['a'] !== '72') {
		$ph['previewButton']   = ab_preview($id);
	}
	$ph['cancelButton']    = ab_cancel($id);
	
	$rs = $modx->parseText($tpl,$ph);
	
	return preg_replace('@\[\+[^\]]+\+\]@','',$rs);
}

function fieldPagetitle() {
	global $_lang, $docObject;
	$body  = input_text('pagetitle',to_safestr($docObject['pagetitle']),'spellcheck="true"');
	$body .= tooltip($_lang['resource_title_help']);
	return renderTr($_lang['resource_title'],$body);
}

function fieldLongtitle() {
	global $docObject,$_lang;
	$body  = input_text('longtitle',to_safestr($docObject['longtitle']),'spellcheck="true"');
	$body .= tooltip($_lang['resource_long_title_help']);
	return renderTr($_lang['long_title'],$body);
}

function fieldDescription() {
	global $docObject,$_lang;
	$description = to_safestr($docObject['description']);
	$body  = '<textarea name="description" class="inputBox" style="height:43px;" rows="2" cols="">' . $description . '</textarea>';
	$body .= tooltip($_lang['resource_description_help']);
	return  renderTr($_lang['resource_description'],$body,'vertical-align:top;');
}

function fieldAlias($id) {
	global $config,$docObject,$_lang;
	
	$body = '';
	$onkeyup = '';
	if($config['suffix_mode']==1)
	{
		$body = get_scr_change_url_suffix($config['friendly_url_suffix']);
		$onkeyup = 'onkeyup="change_url_suffix();" ';
	}
	
	if($config['friendly_urls']==='1' && $docObject['type']==='document')
	{
		$body .= get_alias_path($id);
		$body .= input_text('alias',to_safestr($docObject['alias']), $onkeyup . 'size="20" style="width:120px;"','50');
		$suffix = '';
		if($config['friendly_urls']==1) {
			if($config['suffix_mode']!=1 || strpos($docObject['alias'],'.')===false)
				$suffix = $config['friendly_url_suffix'];
		}
		$body .= '<span id="url_suffix">' . $suffix . '</span>';
	}
	else
	{
		$body .= input_text('alias',to_safestr($docObject['alias']),'','100');
	}
	$body .= tooltip($_lang['resource_alias_help']);
	return renderTr($_lang['resource_alias'],$body);
}

// Web Link specific
function fieldWeblink() {
	global $docObject, $_lang,$_style;
	$head[] = $_lang['weblink'];
	$head[] = '<img name="llock" src="' . $_style['tree_folder'] . '" alt="tree_folder" onclick="enableLinkSelection(!allowLinkSelection);" style="cursor:pointer;" />';
	$weblink = !empty($docObject['content']) ? strip_tags(stripslashes($docObject['content'])) : 'http://';
	$body[] = input_text('ta',$weblink);
	$body[] = '<input type="button" onclick="BrowseFileServer(\'field_ta\')" value="' . $_lang['insert'] . '">';
	$body[] = tooltip($_lang['resource_weblink_help']);
	return renderTr($head, $body);
}

function fieldIntrotext() {
	global $docObject,$_lang;
	$introtext = to_safestr($docObject['introtext']);
	$body = '<textarea name="introtext" class="inputBox" style="height:60px;" rows="3" cols="">'.$introtext.'</textarea>';
	$body .= tooltip($_lang['resource_summary_help']);
	return renderTr($_lang['resource_summary'],$body,'vertical-align:top;');
}

function fieldTemplate() {
	global $_lang;
	$body = '<select id="template" name="template" class="inputBox" onchange="changeTemplate();" style="width:308px">';
	$body .= '<option value="0">(blank)</option>';
	$body .= get_template_options();
	$body .= '</select>' . tooltip($_lang['page_data_template_help']);
	return renderTr($_lang['page_data_template'],$body);
}

function fieldMenutitle() {
	global $docObject,$_lang;
	$body = input_text('menutitle',to_safestr($docObject['menutitle'])) . tooltip($_lang['resource_opt_menu_title_help']);
	return renderTr($_lang['resource_opt_menu_title'],$body);
}

function fieldMenuindex() {
	global $_lang;
	$body = menuindex();
	return renderTr($_lang['resource_opt_menu_index'],$body);
}

function fieldParent() {
	global $docObject, $_lang;
	
	$parentname = getParentName($docObject['parent']);
	$body = getParentForm($parentname);
	return renderTr($_lang['resource_parent'],$body);
}

function getTmplvars($id,$template,$docgrp) {
	global $modx;
	
	$session_mgrRole = $_SESSION['mgrRole'];
	$where_docgrp = empty($docgrp) ? '' : " OR tva.documentgroup IN ({$docgrp})";
	
	if(empty($template)) return array();
	
	$fields = "DISTINCT tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value";
	$from = "
		[+prefix+]site_tmplvars                         AS tv 
		INNER JOIN [+prefix+]site_tmplvar_templates     AS tvtpl ON tvtpl.tmplvarid = tv.id 
		LEFT  JOIN [+prefix+]site_tmplvar_contentvalues AS tvc   ON tvc.tmplvarid   = tv.id AND tvc.contentid='{$id}'
		LEFT  JOIN [+prefix+]site_tmplvar_access        AS tva   ON tva.tmplvarid   = tv.id
		";
	$where = "tvtpl.templateid='{$template}' AND (1='{$session_mgrRole}' OR ISNULL(tva.documentgroup) {$where_docgrp})";
	
	$rs = $modx->db->select($fields,$from,$where,'tvtpl.rank,tv.rank, tv.id');
	if(0<$modx->db->getRecordCount($rs))
	{
		while($row = $modx->db->getRow($rs))
		{
			$tmplVars[$row['name']] = $row;
		}
	}
	else $tmplVars = array();
	return $tmplVars;
}

function rteContent($htmlcontent,$editors) {
	global $modx, $_lang;
	$tpl = <<< EOT
	<textarea id="ta" name="ta" cols="" rows="" style="width:100%; height: 350px;">[+content+]</textarea>
	<span class="warning">[+_lang_which_editor_title+]</span>
	[+editorSelecter+]
EOT;
	$ph['content'] = $htmlcontent;
	$ph['_lang_which_editor_title'] = $_lang['which_editor_title'];
	$ph['editorSelecter'] = getEditors($editors);
	return $modx->parseText($tpl,$ph);
}

function getEditors($editors) {
	global $modx,$_lang,$selected_editor;
	if (!is_array($editors)) return '';
	
	$rs = '';
	$tpl = '<option value="[+editor+]" [+selected+]>[+editor+]</option>';
	$options = array();
	foreach ($editors as $editor) {
		$ph = array();
		$ph['editor']   = $editor;
		$ph['selected'] = ($selected_editor === $editor) ? 'selected' : '';
		$options[] = $modx->parseText($tpl, $ph);
	}
	
	if(!empty($options)) {
		$tpl = <<< EOT
<select id="which_editor" name="which_editor" onchange="changeRTE();">
	<option value="none">[+_lang_none+]</option>
	[+options+]
</select>
EOT;
		$ph = array();
		$ph['_lang_none'] = $_lang['none'];
		$ph['options'] = implode("\n", $options);
		$rs = $modx->parseText($tpl, $ph);
	}
	return $rs;
}

function getTplSectionContent() {
	$tpl = <<< EOT
	<div class="sectionHeader" id="content_header">[+header+]</div>
	<div class="sectionBody" id="content_body">
		<div>[+body+]</div>
	</div>
EOT;
	return $tpl;
}

function getTplSectionTV() {
	$tpl = <<< EOT
	<div class="sectionHeader" id="tv_header">[+header+]</div>
	<div class="sectionBody tmplvars" id="tv_body">
		<div>[+body+]</div>
	</div>
EOT;
	return $tpl;
}

function sectionContent() {
	global $modx, $_lang, $docObject, $rte_field;
	if ($docObject['type'] !== 'document')
		return '';
	
	$tpl = getTplSectionContent();
	$htmlcontent = htmlspecialchars($docObject['content']);
	
	$ph['header'] = $_lang['resource_content'];
	$planetpl = '<textarea class="phptextarea" id="ta" name="ta" style="width:100%; height: 400px;">'.$htmlcontent.'</textarea>';
	if (($_REQUEST['a'] == '4' || $_REQUEST['a'] == '27') && $modx->config['use_editor'] == 1 && $docObject['richtext'] == 1):
		// invoke OnRichTextEditorRegister event
		$editors = $modx->invokeEvent('OnRichTextEditorRegister');
		if(!empty($editors))
			$ph['body'] = rteContent($htmlcontent,$editors);
		else
			$ph['body'] = $planetpl;
		$rte_field = array('ta');
	else:
		$ph['body'] = $planetpl;
	endif;
	
	return $modx->parseText($tpl,$ph);
}

function getTplTVRow() {
	$tpl = <<< EOT
<tr>
	<td valign="top" class="tvname">
	<span class="warning">[+caption+]</span><br />
	<span class="comment">[+description+]</span>
	</td>
	<td valign="top" style="position:relative;[+zindex+]">
    [+FormElement+]
	</td>
</tr>
EOT;
	return $tpl;
}

function sectionTV() {
	global $modx, $_lang;
	$tpl = getTplSectionTV();
	$ph = array();
	$ph['header'] = $_lang['settings_templvars'];
	$ph['body'] = fieldsTV();
	return $modx->parseText($tpl,$ph);
}

function fieldsTV() {
	global $modx, $form_v,$_lang, $tmplVars, $rte_field;
	
	$tpl = getTplTVRow();
	$total = count($tmplVars);
	if(empty($total)) return '';
	
	$i = 0;
	$output = array();
	$hidden = array();
	$output[] = '<table style="position:relative;" border="0" cellspacing="0" cellpadding="3" width="96%">';
	$splitLine = renderSplit();
	foreach($tmplVars as $tv):
		$tvid = 'tv' . $tv['id'];
		// Go through and display all Template Variables
		if ($tv['type'] == 'richtext' || $tv['type'] == 'htmlarea'):
			// Add richtext editor to the list
			if (is_array($rte_field))
				$rte_field = array_merge($rte_field, array($tvid));
			else
				$rte_field = array($tvid);
		endif;
		
		// post back value
		if(array_key_exists($tvid, $form_v)):
			if($tv['type'] === 'listbox-multiple') $tvPBV = implode('||', $form_v[$tvid]);
			else                                   $tvPBV = $form_v[$tvid];
		else:                                      $tvPBV = $tv['value'];
		endif;
		
		if($tv['type']!=='hidden')
		{
			$ph = array();
			$ph['caption']     = htmlspecialchars($tv['caption'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['description'] = $tv['description'];
			$ph['zindex']      = ($tv['type'] === 'date') ? 'z-index:100;' : '';
			$ph['FormElement'] = $modx->renderFormElement($tv['type'], $tv['id'], $tv['default_text'], $tv['elements'], $tvPBV, '', $tv);
			if($ph['FormElement']!=='')
			{
				$output[] = $modx->parseText($tpl,$ph);
				if ($i < $total) $output[] = $splitLine;
			}
		}
		else
		{
			$formElement = $modx->renderFormElement('hidden', $tv['id'], $tv['default_text'], $tv['elements'], $tvPBV, '', $tv);
			$hidden[] = $formElement;
		}
		$i++;
	endforeach;
	
	if(!empty($output) && $output[$total+1]===$splitLine) array_pop($output);
	
	$output[] = '</table>';
	
	return join("\n",$output) . join("\n", $hidden);
}

function fieldPublished() {
	global $_lang,$docObject;
	$published = $docObject['published'];
	$body = input_checkbox('published',$published==='1');
	$body .= input_hidden('published',$published==='1');
	$body .= tooltip($_lang['resource_opt_published_help']);
	return renderTr($_lang['resource_opt_published'],$body);
}

function fieldPub_date($id) {
	global $modx,$_lang,$_style,$config,$docObject;
	
	$tpl[] = '<input type="text" id="pub_date" [+disabled+] name="pub_date" class="DatePicker imeoff" value="[+pub_date+]" />';
	$tpl[] = '<a onclick="document.mutate.pub_date.value=\'\'; documentDirty=true; return true;" style="cursor:pointer; cursor:hand;">';
	$tpl[] = '<img src="[+icons_cal_nodate+]" alt="[+remove_date+]" /></a>';
	$tpl[] = tooltip($_lang['page_data_publishdate_help']);
	$tpl[] = <<< EOT
<tr>
	<td></td>
	<td style="line-height:1;margin:0;color: #555;font-size:10px">[+datetime_format+] HH:MM:SS</td>
</tr>
EOT;
	$tpl = implode("\n",$tpl);
	$ph['disabled']         = disabled(!$modx->hasPermission('publish_document') || $id==$config['site_start']);
	$ph['pub_date']         = $docObject['pub_date'];
	$ph['icons_cal_nodate'] = $_style['icons_cal_nodate'];
	$ph['remove_date']      = $_lang['remove_date'];
	$ph['datetime_format']  = $config['datetime_format'];
	$body = $modx->parseText($tpl,$ph);
	return renderTr($_lang['page_data_publishdate'],$body);
}

function fieldUnpub_date($id) {
	global $modx,$_lang,$_style,$config,$docObject;
	$tpl[] = '<input type="text" id="unpub_date" [+disabled+] name="unpub_date" class="DatePicker imeoff" value="[+unpub_date+]" onblur="documentDirty=true;" />';
	$tpl[] = '<a onclick="document.mutate.unpub_date.value=\'\'; documentDirty=true; return true;" style="cursor:pointer; cursor:hand">';
	$tpl[] = '<img src="[+icons_cal_nodate+]" alt="[+remove_date+]" /></a>';
	$tpl[] = tooltip($_lang['page_data_unpublishdate_help']);
	$tpl[] = <<< EOT
<tr>
	<td></td>
	<td style="line-height:1;margin:0;color: #555;font-size:10px">[+datetime_format+] HH:MM:SS</td>
</tr>
EOT;
	$tpl = implode("\n",$tpl);
	$ph['disabled']         = disabled(!$modx->hasPermission('publish_document') || $id==$config['site_start']);
	$ph['unpub_date']       = $docObject['unpub_date'];
	$ph['icons_cal_nodate'] = $_style['icons_cal_nodate'];
	$ph['remove_date']      = $_lang['remove_date'];
	$ph['datetime_format']  = $config['datetime_format'];
	$body = $modx->parseText($tpl,$ph);
	return renderTr($_lang['page_data_unpublishdate'],$body);
}

function getDocId() {
	if (isset($_REQUEST['id']) && preg_match('@^[1-9][0-9]*$@',$_REQUEST['id']))
		 $id = $_REQUEST['id'];
	else $id = '0';
	return $id;
}

function getInitialValues() {
	global $modx,$default_template;
	
	$init_v['menuindex'] = getMenuIndexAtNew();
	$init_v['alias']     = getAliasAtNew();
	$init_v['richtext']  = $modx->config['use_editor'];
	$init_v['published'] = $modx->config['publish_default'];
	$init_v['contentType'] = 'text/html';
	$init_v['content_dispo'] = '0';
	$init_v['which_editor'] = $modx->config['which_editor'];
	$init_v['searchable'] = $modx->config['search_default'];
	$init_v['cacheable'] = $modx->config['cache_default'];
	
	if($_REQUEST['a']==='4')      $init_v['type'] = 'document';
	elseif($_REQUEST['a']==='72') $init_v['type'] = 'reference';
	
	if(isset($_GET['pid'])) $init_v['parent'] = $_GET['pid'];
	
	if(isset ($_REQUEST['newtemplate']))
		$init_v['template'] = $_REQUEST['newtemplate'];
	else
		$init_v['template']  = $default_template;
	
	return $init_v;
}

function fieldLink_attributes() {
	global $modx,$_lang,$docObject;
	$body  = input_text('link_attributes',to_safestr($docObject['link_attributes']));
	$body .= tooltip($_lang['link_attributes_help']);
	return renderTr($_lang['link_attributes'],$body);
}

function fieldIsfolder() {
	global $modx,$_lang,$docObject;
	$cond = ($docObject['isfolder']==1);
	$haschildren = $modx->db->getValue($modx->db->select('count(id)','[+prefix+]site_content',"parent='{$id}'"));
	$disabled = $id!=0&&0<$haschildren ? 'disabled' : '';
	$body = input_checkbox('isfolder',$cond,$disabled);
	$body .= input_hidden('isfolder',$cond);
	$body .= tooltip($_lang['resource_opt_folder_help']);
	return renderTr($_lang['resource_opt_folder'],$body);
}

function fieldRichtext() {
	global $modx,$_lang,$docObject;
	$disabled = ($modx->config['use_editor']!=1) ? ' disabled="disabled"' : '';
	$cond = (!isset($docObject['richtext']) || $docObject['richtext']!=0 || $_REQUEST['a']!='27');
	$body = input_checkbox('richtext',$cond,$disabled);
	$body .= input_hidden('richtext',$cond);
	$body .= tooltip($_lang['resource_opt_richtext_help']);
	return renderTr($_lang['resource_opt_richtext'],$body);
}

function fieldDonthit() {
	global $modx,$_lang,$docObject;
	$cond = ($docObject['donthit']!=1);
	$body = input_checkbox('donthit',$cond);
	$body .= input_hidden('donthit',!$cond);
	$body .= tooltip($_lang['resource_opt_trackvisit_help']);
	return renderTr($_lang['track_visitors_title'],$body);
}


function fieldSearchable() {
	global $modx,$_lang,$docObject;
	$cond = ($docObject['searchable']==1);
	$body = input_checkbox('searchable',$cond);
	$body .= input_hidden('searchable',$cond);
	$body .= tooltip($_lang['page_data_searchable_help']);
	return renderTr($_lang['page_data_searchable'],$body);
}

function fieldCacheable() {
	global $modx,$_lang,$docObject;
	$cond = ($docObject['cacheable']==1);
	$disabled = ($modx->config['cache_type']==='0') ? ' disabled' : '';
	$body = input_checkbox('cacheable',$cond,$disabled);
	$body .= input_hidden('cacheable',$cond);
	$body .= tooltip($_lang['page_data_cacheable_help']);
	return renderTr($_lang['page_data_cacheable'],$body);
}

function fieldSyncsite() {
	global $modx,$_lang;
	$disabled = ($modx->config['cache_type']==='0') ? ' disabled' : '';
	$body = input_checkbox('syncsite',true,$disabled);
	$body .= input_hidden('syncsite');
	$body .= tooltip($_lang['resource_opt_emptycache_help']);
	return renderTr($_lang['resource_opt_emptycache'],$body);
}

function fieldType() {
	global $modx,$_lang,$docObject;
	
	$tpl = <<< EOT
<select name="type" class="inputBox" style="width:200px">
    <option value="document" [+selected_doc+]>[+resource_type_webpage+]</option>
    <option value="reference" [+selected_ref+]>[+resource_type_weblink+]</option>
</select>
EOT;
	$ph = array();
	$ph['selected_ref'] = ($docObject['type']==='reference') ? 'selected' : '';
	$ph['selected_doc'] = empty($ph['selected_ref']) ? 'selected' : '';
	$ph['resource_type_webpage'] = $_lang["resource_type_webpage"];
	$ph['resource_type_weblink'] = $_lang["resource_type_weblink"];
	$body = $modx->parseText($tpl, $ph).tooltip($_lang['resource_type_message']);
	return renderTr($_lang['resource_type'],$body);
}

function fieldContentType() {
	global $modx,$_lang,$docObject;
	
	if($docObject['type'] === 'reference') return;
	$tpl = <<< EOT
<select name="contentType" class="inputBox" style="width:200px">
	[+option+]
</select>
EOT;
	$ct = explode(',', $modx->config['custom_contenttype']);
	$option = array();
	foreach ($ct as $value)
	{
		$ph['selected'] = $docObject['contentType'] === $value ? ' selected' : '';
		$ph['value'] = $value;
		$option[] = $modx->parseText('<option value="[+value+]" [+selected+]>[+value+]</option>',$ph);
	}
	$ph = array();
	$ph['option'] = join("\n", $option);
	$body = $modx->parseText($tpl,$ph) . tooltip($_lang['page_data_contentType_help']);
	return renderTr($_lang['page_data_contentType'],$body);
}

function fieldContent_dispo() {
	global $modx,$_lang,$docObject;
	
	if($docObject['type'] === 'reference') return;
	$tpl = <<< EOT
<select name="content_dispo" size="1" style="width:200px">
	<option value="0" [+sel_inline+]>[+inline+]</option>
	<option value="1" [+sel_attachment+]>[+attachment+]</option>
</select>
EOT;
	$ph = array();
	$ph['sel_attachment'] = $docObject['content_dispo']==1 ? 'selected' : '';
	$ph['sel_inline'] = $ph['sel_attachment']==='' ? 'selected' : '';
	$ph['inline']     = $_lang['inline'];
	$ph['attachment'] = $_lang['attachment'];
	$body = $modx->parseText($tpl,$ph);
	return renderTr($_lang['resource_opt_contentdispo'],$body);
}

function getKeywords() {
	global $modx;
	
	// get list of site keywords
	$rs = $modx->db->select('id,keyword', '[+prefix+]site_keywords', '', 'keyword ASC');
	$total = $modx->db->getRecordCount($rs);
	$keywords = array();
	if (0<$total)
	{
		while($row = $modx->db->getRow($rs))
		{
			$keywords[$row['id']] = $row['keyword'];
		}
	}
	return $keywords;
}

function getSelectedKeywords() {
	global $modx,$docObject;
	// get selected keywords using document's id
	$keywords_selected = array();
	if (isset ($docObject['id']) && 0<count($keywords))
	{
		$rs = $modx->db->select('keyword_id', '[+prefix+]keyword_xref', "content_id='{$docObject['id']}'");
		$total = $modx->db->getRecordCount($rs);
		if (0<$total)
		{
			while($row = $modx->db->getRow($rs))
			{
				$keywords_selected[$row['keyword_id']] = ' selected="selected"';
			}
		}
	}
	return $keywords_selected;
}

function getMetatags() {
	global $modx;
	// get list of site META tags
	$rs = $modx->db->select('*', '[+prefix+]site_metatags');
	$total = $modx->db->getRecordCount($rs);
	$metatags = array();
	if (0<$total)
	{
		while($row = $modx->db->getRow($rs))
		{
			$metatags[$row['id']] = $row['name'];
		}
	}
	return $metatags;
}

function getSelectedMetatags() {
	global $modx,$docObject;
	// get selected META tags using document's id
	$metatags_selected = array();
	if (isset ($docObject['id']) && count($metatags) > 0)
	{
		$rs = $modx->db->select('metatag_id', '[+prefix+]site_content_metatags', "content_id='{$docObject['id']}'");
		$total = $modx->db->getRecordCount($rs);
		if (0<$total)
		{
			while($row = $modx->db->getRow($rs))
			{
				$metatags_selected[$row['metatag_id']] = ' selected="selected"';
			}
		}
	}
	return $metatags_selected;
}

function getGroups($docid) {
	global $modx;
	// Load up, the permissions from the parent (if new document) or existing document
	$rs = $modx->db->select('id, document_group','[+prefix+]document_groups',"document='{$docid}'");
	$groupsarray = array();
	while ($row = $modx->db->getRow($rs))
	{
		$groupsarray[] = $row['document_group'].','.$row['id'];
	}
	return $groupsarray;
}

function getUDGroups($id) {
	global $modx,$_lang,$docObject,$permissions_yes, $permissions_no;
	
	$form_v = $_POST;
	$groupsarray = array();
	
	if($_REQUEST['a'] == '27')       $docid = $id;
	elseif(!empty($_REQUEST['pid'])) $docid = $_REQUEST['pid'];
	else                             $docid = $docObject['parent'];
	
	if (0<$docid)
	{
		$groupsarray = getGroups($docid);
		// Load up the current permissions and names
		$field = 'dgn.*, groups.id AS link_id';
		$from[] = '[+prefix+]documentgroup_names AS dgn';
		$from[] = "LEFT JOIN [+prefix+]document_groups AS groups ON groups.document_group = dgn.id AND groups.document = {$docid}";
		$from = implode(' ', $from);
	}
	else
	{
		// Just load up the names, we're starting clean
		$field = '*, NULL AS link_id';
		$from  = '[+prefix+]documentgroup_names';
	}

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

	// Query the permissions and names from above
	$rs = $modx->db->select($field,$from,'','name');
	
	// Loop through the permissions list
	while($row = $modx->db->getRow($rs)):
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
		$count = $modx->db->getValue($modx->db->select('COUNT(mg.id)',$from,$where));
		
		if($count > 0) ++$permissions_yes;
		else           ++$permissions_no;
		
		$permissions[] = "\t\t".'<li>'.$inputHTML.'<label for="'.$inputId.'">'.$row['name'].'</label></li>';
	endwhile;
	
	if(!empty($permissions)) {
		// Add the "All Document Groups" item if we have rights in both contexts
		if ($isManager && $isWeb)
		{
			array_unshift($permissions,"\t\t".'<li><input type="checkbox" class="checkbox" name="chkalldocs" id="groupall"' . checked(!$notPublic) . ' onclick="makePublic(true);" /><label for="groupall" class="warning">' . $_lang['all_doc_groups'] . '</label></li>');
		// Output the permissions list...
		}
	}

		// if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
	if($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0))
	{
		$permissions = array();
	}
	return $permissions;
}
