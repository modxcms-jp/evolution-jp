<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

switch ((int) $_REQUEST['a'])
{
	case 78:
		if (!$modx->hasPermission('edit_chunk'))
		{
			$e->setError(3);
			$e->dumpError();
		}
		break;
	case 77:
		if (!$modx->hasPermission('new_chunk'))
		{
			$e->setError(3);
			$e->dumpError();
		}
		break;
	default:
		$e->setError(3);
		$e->dumpError();
}

if (isset($_REQUEST['id']))
        $id = (int)$_REQUEST['id'];
else    $id = 0;

// Get table names (alphabetical)

// Check to see the snippet editor isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', "action=78 AND id='{$id}'");
if ($modx->db->getRecordCount($rs) > 1)
{
	while ($row = $modx->db->getRow($rs))
	{
		if ($row['internalKey'] != $modx->getLoginUserID())
		{
			$msg = sprintf($_lang['lock_msg'], $row['username'], $_lang['chunk']);
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}

$content = array();
if (isset($_REQUEST['id']) && $_REQUEST['id']!='' && is_numeric($_REQUEST['id']))
{
	$rs = $modx->db->select('*','[+prefix+]site_htmlsnippets',"id='{$id}'");
	$total = $modx->db->getRecordCount($rs);
	if ($total > 1)
	{
		echo '<p>Error: Multiple Chunk sharing same unique ID.</p>';
		exit;
	}
	if ($total < 1)
	{
		echo '<p>Chunk doesn\'t exist.</p>';
		exit;
	}
	$content = $modx->db->getRow($rs);
	$_SESSION['itemname'] = $content['name'];
}
else
{
	$_SESSION['itemname'] = 'New Chunk';
}

// restore saved form
$formRestored = false;
if ($modx->manager->hasFormValues())
{
	$form_v = $modx->manager->loadFormValues();
	$formRestored = true;
}else{
	$form_v = $_POST;
}

if ($formRestored == true || isset ($_REQUEST['changeMode']))
{
	$content = array_merge($content, $form_v);
	$content['content'] = $form_v['ta'];
	if (empty ($content['pub_date'])) unset ($content['pub_date']);
	else $content['pub_date'] = $modx->toTimeStamp($content['pub_date']);
	if (empty ($content['unpub_date'])) unset ($content['unpub_date']);
	else $content['unpub_date'] = $modx->toTimeStamp($content['unpub_date']);
}

if (isset($form_v['which_editor']))
        $which_editor = $form_v['which_editor'];
elseif(!isset($content['editor_type']) || empty($content['editor_type'])) $which_editor = 'none';


// Print RTE Javascript function
?>
<script language="javascript" type="text/javascript">
$j(function(){
	$j('select[name="categoryid"]').change(function(){
		if($j(this).val()=='-1')
		{
			$j('#newcategry').fadeIn();
		}
		else
		{
			$j('#newcategry').fadeOut();
			$j('input[name="newcategory"]').val('');
		}
	});
});
// Added for RTE selection
function changeRTE() {
	var whichEditor = document.getElementById('which_editor');
	if (whichEditor) {
		for (var i = 0; i < whichEditor.length; i++) {
			if (whichEditor[i].selected) {
				newEditor = whichEditor[i].value;
				break;
			}
		}
	}
	else newEditor = '';

	documentDirty=false;
	document.mutate.a.value = <?php echo $action?>;
	document.mutate.which_editor.value = newEditor;
	document.mutate.changeMode.value = newEditor;
	document.mutate.submit();
}

function duplicaterecord(){
	if (confirm("<?php echo $_lang['confirm_duplicate_record']?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=97";
	}
}

function deletedocument() {
	if (confirm("<?php echo $_lang['confirm_delete_htmlsnippet']?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=" + document.mutate.id.value + "&a=80";
	}
}
</script>
<?php
$dayNames   = "['" . join("','",explode(',',$_lang['day_names'])) . "']";
$monthNames = "['" . join("','",explode(',',$_lang['month_names'])) . "']";
?>
<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
/* <![CDATA[ */
window.addEvent('domready', function(){
	var dpOffset = <?php echo $modx->config['datepicker_offset']; ?>;
	var dpformat = "<?php echo $modx->config['datetime_format']; ?>" + ' hh:mm:00';
	var dayNames = <?php echo $dayNames;?>;
	var monthNames = <?php echo $monthNames;?>;
	new DatePicker($('pub_date'),   {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	new DatePicker($('unpub_date'), {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
});

function resetpubdate() {
	if(document.mutate.pub_date.value!=''||document.mutate.unpub_date.value!='') {
		if (confirm("<?php echo $_lang['mutate_htmlsnippet.dynamic.php1'];?>")==true) {
			document.mutate.pub_date.value='';
			document.mutate.unpub_date.value='';
		}
	}
	documentDirty=true;
}
</script>

<form class="htmlsnippet" id="mutate" name="mutate" method="post" action="index.php" enctype="multipart/form-data">
<?php

// invoke OnChunkFormPrerender event
$tmp = array('id' => $id);
$evtOut = $modx->invokeEvent('OnChunkFormPrerender', $tmp);
if (is_array($evtOut))
	echo implode('', $evtOut);

?>
<input type="hidden" name="a" value="79" />
<input type="hidden" name="id" value="<?php echo $_REQUEST['id']?>" />
<input type="hidden" name="mode" value="<?php echo (int) $_REQUEST['a']?>" />
<input type="hidden" name="changeMode" value="" />

	<h1><?php echo $_lang['htmlsnippet_title']?></h1>

    <div id="actions">
    	  <ul class="actionButtons">
<?php if($modx->hasPermission('save_chunk')):?>
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false;jQuery('#mutate').submit();jQuery('#Button1').hide();jQuery('input,textarea,select').addClass('readonly');">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']?>
    			</a>
    			  <span class="and"> + </span>
    			<select id="stay" name="stay">
    			  <?php if ($modx->hasPermission('new_chunk')) { ?>
    			  <option id="stay1" value="1" <?php echo $_REQUEST['stay']=='1' ? ' selected=""' : ''?> ><?php echo $_lang['stay_new']?></option>
    			  <?php } ?>
    			  <option id="stay2" value="2" <?php echo $_REQUEST['stay']=='2' ? ' selected="selected"' : ''?> ><?php echo $_lang['stay']?></option>
    			  <option id="stay3" value=""  <?php echo $_REQUEST['stay']=='' ? ' selected=""' : ''?>  ><?php echo $_lang['close']?></option>
    			</select>
    		  </li>
<?php endif; ?>
<?php
    if ($_REQUEST['a'] == '78')
    {
    	$params = array('onclick'=>'duplicaterecord();','icon'=>$_style['icons_resource_duplicate'],'label'=>$_lang['duplicate']);
    	if($modx->hasPermission('new_chunk'))
    		echo $modx->manager->ab($params);
    	$params = array('onclick'=>'deletedocument();','icon'=>$_style['icons_delete_document'],'label'=>$_lang['delete']);
    	if($modx->hasPermission('delete_chunk'))
    		echo $modx->manager->ab($params);
    }
	$params = array('onclick'=>"document.location.href='index.php?a=76';",'icon'=>$_style['icons_cancel'],'label'=>$_lang['cancel']);
	echo $modx->manager->ab($params);
?>
    	  </ul>
    </div>

<div class="sectionBody">
<div class="tab-pane" id="chunkPane">
	<script type="text/javascript">
		tp = new WebFXTabPane( document.getElementById( "chunkPane" ), <?php echo (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?> );
	</script>
	<div class="tab-page" id="tabGeneral">
	<h2 class="tab"><?php echo $_lang['settings_general'];?></h2>
	<script type="text/javascript">tp.addTabPage( document.getElementById( "tabGeneral" ) );</script>
	<p><?php echo $_lang['htmlsnippet_msg']?></p>
	<table>
		<tr>
			<th align="left"><?php echo $_lang['htmlsnippet_name']?></th>
			<td align="left">{{<input name="name" type="text" maxlength="100" value="<?php echo htmlspecialchars($content['name'])?>" class="inputBox" style="width:300px;">}}</td>
		</tr>
	</table>

	<div>
		<div style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
			<span style="font-weight:bold;"><?php echo $_lang['chunk_code']?></span>
		</div>
<?php
	if($content['locked'] === '1' || $content['locked'] === 'on')
		$readonly = 'readonly';
	else $readonly = '';
?>
        <textarea dir="ltr" class="phptextarea" name="post" <?php echo $readonly;?> style="height:350px;width:100%"><?php echo isset($content['post']) ? htmlspecialchars($content['post']) : htmlspecialchars($content['snippet'])?></textarea>
	</div>

	<span class="warning"><?php echo $_lang['which_editor_title']?></span>
			<select id="which_editor" name="which_editor" onchange="gotosave=true;documentDirty=false;changeRTE();">
				<option value="none"<?php echo $which_editor == 'none' ? ' selected="selected"' : ''?>><?php echo $_lang['none']?></option>
<?php
// invoke OnRichTextEditorRegister event
$evtOut = $modx->invokeEvent('OnRichTextEditorRegister');
if (is_array($evtOut))
{
	foreach ($evtOut as $i => $editor)
	{
						echo "\t".'<option value="'.$editor.'"'.($which_editor == $editor ? ' selected="selected"' : '').'>'.$editor."</option>\n";
					}
}
?>
            </select>
<?php

// invoke OnChunkFormRender event
$tmp = array('id' => $id);
$evtOut = $modx->invokeEvent('OnChunkFormRender', $tmp);
if (is_array($evtOut))
	echo implode('', $evtOut);
?>

</div>

<div class="tab-page" id="tabInfo">
<h2 class="tab"><?php echo $_lang['settings_properties'];?></h2>
<script type="text/javascript">tp.addTabPage( document.getElementById( "tabInfo" ) );</script>
<table>
	<tr>
		<th align="left"><?php echo $_lang['chunk_opt_published'];?></th>
		<td><input name="published" onclick="resetpubdate();" type="checkbox"<?php echo (!isset($content['published']) || $content['published'] == 1) ? ' checked="checked"' : '';?> class="inputBox" value="1" /></td>
	</tr>
	<tr>
		<?php
			$content['pub_date'] = (isset($content['pub_date']) && $content['pub_date']!='0') ? $modx->toDateFormat($content['pub_date']) : '';
		?>
		<th align="left"><?php echo $_lang['page_data_publishdate'];?></th>
		<td>
			<input id="pub_date" name="pub_date" type="text" value="<?php echo $content['pub_date'];?>" class="DatePicker" />
            <a onclick="document.mutate.pub_date.value=''; documentDirty=true; return true;" style="cursor:pointer; cursor:hand;">
			<img src="<?php echo $_style["icons_cal_nodate"] ?>" alt="<?php echo $_lang['remove_date']?>" /></a>
		</td>
	</tr>
	<tr>
		<?php
			$content['unpub_date'] = (isset($content['unpub_date']) && $content['unpub_date']!='0') ? $modx->toDateFormat($content['unpub_date']) : '';
		?>
		<th align="left"><?php echo $_lang['page_data_unpublishdate'];?></th>
		<td>
			<input id="unpub_date" name="unpub_date" type="text" value="<?php echo $content['unpub_date'];?>" class="DatePicker" />
			<a onclick="document.mutate.unpub_date.value=''; documentDirty=true; return true;" style="cursor:pointer; cursor:hand">
			<img src="<?php echo $_style["icons_cal_nodate"] ?>" alt="<?php echo $_lang['remove_date']?>" /></a>
		</td>
	</tr>
	<tr>
		<th align="left"><?php echo $_lang['existing_category'];?></th>
		<td align="left"><span style="font-family:'Courier New', Courier, mono"></span>
		<select name="categoryid" style="width:300px;">
			<option value="0"><?php echo $_lang["no_category"]; ?></option>
<?php
$ds = $modx->manager->getCategories();
if ($ds) {
			foreach ($ds as $n => $v) {
			echo "\t\t\t\t".'<option value="'.$v['id'].'"'.($content['category'] == $v['id'] || (empty($content['category']) && $_POST['categoryid'] == $v['id']) ? ' selected="selected"' : '').'>'.htmlspecialchars($v['category'])."</option>\n";
			}
}
?>
        <option value="-1">&gt;&gt; <?php echo $_lang["new_category"]; ?></option>
        </select></td>
    </tr>
	<tr id="newcategry" style="display:none;">
		<th align="left" valign="middle"><?php echo $_lang['new_category']?></th>
		<td align="left" valign="top"><input name="newcategory" type="text" maxlength="45" value="<?php echo isset($content['newcategory']) ? $content['newcategory'] : ''?>" class="inputBox" style="width:300px;"></td>
	</tr>
	<tr>
		<th align="left"><?php echo $_lang['htmlsnippet_desc']?></th>
		<td align="left"><textarea name="description" style="padding:0;height:4em;width:300px;"><?php echo htmlspecialchars($content['description']);?></textarea></td>
	</tr>
	<tr>
		<th align="left" valign="middle"><?php echo $_lang['resource_opt_richtext']?></th>
		<td align="left" valign="top"><input name="editor_type" type="checkbox"<?php echo $content['editor_type'] == 1 ? ' checked="checked"' : ''?> class="inputBox" value="1" /></td>
	</tr>
<?php if($modx->hasPermission('save_chunk')==1) {?>
	<tr>
		<td align="left" colspan="2">
		<label><input name="locked" type="checkbox"<?php echo $content['locked'] == 1 || $content['locked'] == 'on' ? ' checked="checked"' : ''?> class="inputBox" value="on" /> <?php echo $_lang['lock_htmlsnippet']?>
		<span class="comment"><?php echo $_lang['lock_htmlsnippet_msg']?></span></label></td>
	</tr>
<?php } ?>
</table>
</div>
</div>
</form>
</div>
<?php
// invoke OnRichTextEditorInit event
if ($use_editor == 1) {
  $tmp = array(
		'editor' => $which_editor,
		'elements' => array(
			'post',
		));
	$evtOut = $modx->invokeEvent('OnRichTextEditorInit', $tmp);
	if (is_array($evtOut))
		echo implode('', $evtOut);
}
