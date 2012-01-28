<?php
if (IN_MANAGER_MODE != 'true') die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

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
$tbl_active_users      = $modx->getFullTableName('active_users');
$tbl_site_htmlsnippets = $modx->getFullTableName('site_htmlsnippets');

// Check to see the snippet editor isn't locked
$rs = $modx->db->select('internalKey, username', $tbl_active_users, "action=78 AND id='{$id}'");
if ($modx->db->getRecordCount($rs) > 1)
{
	while ($row = $modx->db->getRow($rs))
	{
		if ($row['internalKey'] != $modx->getLoginUserID())
		{
			$msg = sprintf($_lang['lock_msg'], $row['username'], 'chunk');
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}

$content = array();
if (isset($_REQUEST['id']) && $_REQUEST['id']!='' && is_numeric($_REQUEST['id']))
{
	$rs = $modx->db->select('*',$tbl_site_htmlsnippets,"id='{$id}'");
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
	if ($content['locked'] == 1 && $_SESSION['mgrRole'] != 1)
	{
		$e->setError(3);
		$e->dumpError();
	}
}
else
{
	$_SESSION['itemname'] = 'New Chunk';
}

if (isset($_POST['which_editor']))
        $which_editor = $_POST['which_editor'];
else    $which_editor = 'none';

$formRestored = $modx->manager->loadFormValues();
if($formRestored) $content = array_merge($content, $_POST);


// Print RTE Javascript function
?>
<script language="javascript" type="text/javascript">
// Added for RTE selection
function changeRTE(){
	var whichEditor = document.getElementById('which_editor');
	if (whichEditor) for (var i=0; i<whichEditor.length; i++){
		if (whichEditor[i].selected){
			newEditor = whichEditor[i].value;
			break;
		}
	}

	documentDirty=false;
	document.mutate.a.value = <?php echo $action?>;
	document.mutate.which_editor.value = newEditor;
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

<form class="htmlsnippet" id="mutate" name="mutate" method="post" action="index.php" enctype="multipart/form-data">
<?php

// invoke OnChunkFormPrerender event
$evtOut = $modx->invokeEvent('OnChunkFormPrerender', array(
	'id' => $id,
));
if (is_array($evtOut))
	echo implode('', $evtOut);

?>
<input type="hidden" name="a" value="79" />
<input type="hidden" name="id" value="<?php echo $_REQUEST['id']?>" />
<input type="hidden" name="mode" value="<?php echo (int) $_REQUEST['a']?>" />

	<h1><?php echo $_lang['htmlsnippet_title']?></h1>

    <div id="actions">
    	  <ul class="actionButtons">
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false; document.mutate.save.click();">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['save']?>
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
    		  <?php
    			if ($_REQUEST['a'] == '78') { ?>
    		  <li id="Button2"><a href="#" onclick="duplicaterecord();"><img src="<?php echo $_style["icons_resource_duplicate"] ?>" /> <?php echo $_lang["duplicate"]; ?></a></li>	    
    		  <li id="Button3" class="disabled"><a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete_document"]?>" /> <?php echo $_lang['delete']?></a></li>
    		  <?php } else { ?>
    		  <li id="Button3"><a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete_document"]?>" /> <?php echo $_lang['delete']?></a></li>
    		  <?php } ?>	
    		  <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=76';"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
    	  </ul>
    </div>

<script type="text/javascript" src="media/script/tabpane.js"></script>
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
			<td align="left"><?php echo $_lang['htmlsnippet_name']?>:</td>
			<td align="left">{{<input name="name" type="text" maxlength="100" value="<?php echo htmlspecialchars($content['name'])?>" class="inputBox" style="width:300px;" onChange='documentDirty=true;'>}}<span class="warning" id="savingMessage">&nbsp;</span></td>
		</tr>
	</table>

	<div>
		<div style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
			<span style="font-weight:bold;"><?php echo $_lang['chunk_code']?></span>
		</div>
        <textarea dir="ltr" class="phptextarea" name="post" style="height:350px;width:100%" onchange="documentDirty=true;"><?php echo isset($content['post']) ? htmlspecialchars($content['post']) : htmlspecialchars($content['snippet'])?></textarea>
	</div>

	<span class="warning"><?php echo $_lang['which_editor_title']?></span>
			<select id="which_editor" name="which_editor" onchange="changeRTE();">
				<option value="none"<?php echo $which_editor == 'none' ? ' selected="selected"' : ''?>><?php echo $_lang['none']?></option>
<?php
// invoke OnRichTextEditorRegister event
$evtOut = $modx->invokeEvent('OnRichTextEditorRegister');
if (is_array($evtOut)) {
					foreach ($evtOut as $i => $editor) {
						echo "\t".'<option value="'.$editor.'"'.($which_editor == $editor ? ' selected="selected"' : '').'>'.$editor."</option>\n";
					}
}
?>
            </select>
<?php

// invoke OnChunkFormRender event
$evtOut = $modx->invokeEvent('OnChunkFormRender', array(
	'id' => $id,
));
if (is_array($evtOut))
	echo implode('', $evtOut);
?>

</div>

<div class="tab-page" id="tabInfo">
<h2 class="tab"><?php echo $_lang['settings_properties'];?></h2>
<script type="text/javascript">tp.addTabPage( document.getElementById( "tabInfo" ) );</script>
<table>
	<tr>
		<td align="left"><?php echo $_lang['existing_category']?>:</td>
		<td align="left"><span style="font-family:'Courier New', Courier, mono"></span>
		<select name="categoryid" style="width:300px;" onChange='documentDirty=true;'>
			<option>&nbsp;</option>
<?php
include_once(MODX_MANAGER_PATH.'includes/categories.inc.php');
$ds = getCategories();
if ($ds) {
			foreach ($ds as $n => $v) {
			echo "\t\t\t\t".'<option value="'.$v['id'].'"'.($content['category'] == $v['id'] || (empty($content['category']) && $_POST['categoryid'] == $v['id']) ? ' selected="selected"' : '').'>'.htmlspecialchars($v['category'])."</option>\n";
			}
}
?>
        </select></td>
    </tr>
	<tr>
		<td align="left" valign="top" style="padding-top:5px;"><?php echo $_lang['new_category']?>:</td>
		<td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="<?php echo isset($content['newcategory']) ? $content['newcategory'] : ''?>" class="inputBox" style="width:300px;" onChange="documentDirty=true;"></td>
	</tr>
	<tr>
		<td align="left"><?php echo $_lang['htmlsnippet_desc']?>:</td>
		<td align="left"><input name="description" type="text" maxlength="255" value="<?php echo htmlspecialchars($content['description'])?>" class="inputBox" style="width:300px;" onChange='documentDirty=true;'></td>
	</tr>
	<tr>
		<td align="left" colspan="2">
		<label><input name="locked" type="checkbox"<?php echo $content['locked'] == 1 || $content['locked'] == 'on' ? ' checked="checked"' : ''?> class="inputBox" value="on" /> <?php echo $_lang['lock_htmlsnippet']?>
		<span class="comment"><?php echo $_lang['lock_htmlsnippet_msg']?></span></label></td>
	</tr>
</table>
</div>

<input type="submit" name="save" style="display:none;" />
</form>
</div>
<?php
// invoke OnRichTextEditorInit event
if ($use_editor == 1) {
	$evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
		'editor' => $which_editor,
		'elements' => array(
			'post',
		),
	));
	if (is_array($evtOut))
		echo implode('', $evtOut);
}
