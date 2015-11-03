 <?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(isset($_REQUEST['id']) && preg_match('@^[0-9]+$@',$_REQUEST['id'])) $id = $_REQUEST['id'];
else                                                                   $id = '';

switch((int) $_REQUEST['a'])
{
	case 16:
	if(!$modx->hasPermission('edit_template'))
	{
		$e->setError(3);
		$e->dumpError();
	}
	break;
case 19:
	if(!$modx->hasPermission('new_template'))
	{
		$e->setError(3);
		$e->dumpError();
	}
	break;
default:
	$e->setError(3);
	$e->dumpError();
}

if(!empty($id))
{
	// check to see the template editor isn't locked
	$rs = $modx->db->select('internalKey, username','[+prefix+]active_users',"action=16 AND id='{$id}'");
	if($modx->db->getRecordCount($rs)>1)
	{
		while ($row = $modx->db->getRow($rs))
		{
			if($row['internalKey'] != $modx->getLoginUserID())
			{
				$msg = sprintf($_lang['lock_msg'],$row['username'],$_lang['template']);
				$e->setError(5, $msg);
				$e->dumpError();
			}
		}
	} // end check for lock
}

$templateObject = array();
if(!empty($id)) {
	$templateObject = $modx->db->getObject('site_templates',"id='{$id}'");
	$total = count($templateObject);
	if($total > 1)
	{
		echo "Oops, something went terribly wrong...<p>";
		echo "More results returned than expected. Which sucks. <p>Aborting.";
		exit;
	}
	if($total < 1)
	{
		echo "Oops, something went terribly wrong...<p>";
		echo "No database record has been found for this template. <p>Aborting.";
		exit;
	}
	$_SESSION['itemname']=$templateObject->templatename;
}
else
{
	$_SESSION['itemname']="New template";
}

$templateObject = array_merge((array)$templateObject, $_POST);
$templateObject = (object) $templateObject;

?>
<script type="text/javascript">
jQuery(function(){
	var tpstatus = <?php echo (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?>;
	tpTemplates = new WebFXTabPane( document.getElementById( "templatesPane" ), tpstatus );
    jQuery.get("index.php",
        { a: "1", ajaxa: "16", target:"use_resources", id:"<?php echo $id;?>" },
        function(data){
            jQuery('div#use_resources').html(data);
        });
	jQuery('select[name="categoryid"]').change(function(){
		if(jQuery(this).val()=='-1')
		{
			jQuery('#newcategry').fadeIn();
		}
		else
		{
			jQuery('#newcategry').fadeOut();
			jQuery('input[name="newcategory"]').val('');
		}
	});
});

function duplicaterecord(){
	if(confirm("<?php echo $_lang['confirm_duplicate_record'] ?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=<?php echo $_REQUEST['id']; ?>&a=96";
	}
}

function deletedocument() {
	if(confirm("<?php echo $_lang['confirm_delete_template']; ?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=" + document.mutate.id.value + "&a=21";
	}
}

</script>

<form name="mutate" id="mutate" method="POST" action="index.php" enctype="multipart/form-data">
<?php
	// invoke OnTempFormPrerender event
  $tmp = array("id" => $id);
	$evtOut = $modx->invokeEvent("OnTempFormPrerender",$tmp);
	if(is_array($evtOut)) echo implode("",$evtOut);
?>
<input type="hidden" name="a" value="20">
<input type="hidden" name="id" value="<?php echo $_REQUEST['id'];?>">
<input type="hidden" name="mode" value="<?php echo (int) $_REQUEST['a'];?>">

	<h1><?php echo $_lang['template_title']; ?></h1>

    <div id="actions">
    	  <ul class="actionButtons">
<?php if($modx->hasPermission('save_template')):?>
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false;jQuery('#mutate').submit();jQuery('#Button1').hide();jQuery('input,textarea,select').addClass('readonly');">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']?>
    			</a>
    			  <span class="and"> + </span>
    			<select id="stay" name="stay">
    			  <option id="stay1" value="1" <?php echo $_REQUEST['stay']=='1' ? ' selected=""' : ''?> ><?php echo $_lang['stay_new']?></option>
    			  <option id="stay2" value="2" <?php echo $_REQUEST['stay']=='2' ? ' selected="selected"' : ''?> ><?php echo $_lang['stay']?></option>
    			  <option id="stay3" value=""  <?php echo $_REQUEST['stay']=='' ? ' selected=""' : ''?>  ><?php echo $_lang['close']?></option>
    			</select>
    		  </li>
<?php endif; ?>
<?php
	if ($_REQUEST['a'] == '16')
    {
    	$params = array('onclick'=>'duplicaterecord();','icon'=>$_style['icons_resource_duplicate'],'label'=>$_lang['duplicate']);
    	if($modx->hasPermission('new_template'))
    		echo $modx->manager->ab($params);
    	$params = array('onclick'=>'deletedocument();','icon'=>$_style['icons_delete_document'],'label'=>$_lang['delete']);
    	if($modx->hasPermission('delete_template'))
    		echo $modx->manager->ab($params);
    }
	$params = array('onclick'=>"document.location.href='index.php?a=76';",'icon'=>$_style['icons_cancel'],'label'=>$_lang['cancel']);
	echo $modx->manager->ab($params);
?>
    	  </ul>
    </div>

<div class="sectionBody">
<div class="tab-pane" id="templatesPane">
	<div class="tab-page" id="tabTemplate">
    	<h2 class="tab"><?php echo $_lang["template_edit_tab"] ?></h2>
	<div style="margin-bottom:10px;">
	<b><?php echo $_lang['template_name']; ?></b>
	<input name="templatename" type="text" maxlength="100" value="<?php echo htmlspecialchars($templateObject->templatename);?>" class="inputBox" style="width:200px;">
<?php
	$where = $id ? "parent!='{$id}'" : '';
	$rs = $modx->db->select('*','[+prefix+]site_templates',$where);
	$parent = array();
	while($row = $modx->db->getRow($rs))
	{
		if($id==$row['id']) continue;
		$parent[] = array('id'=>$row['id'],'templatename'=>htmlspecialchars($row['templatename']));
	}
	$tpl = '<option value="[+id+]" [+selected+]>[+templatename+]([+id+])</option>';
	$option = array();
	foreach($parent as $ph)
	{
		$ph['selected'] = $templateObject->parent==$ph['id'] ? 'selected' : '';
		$option[] = $modx->parseText($tpl, $ph);
	}
echo $_lang["template_parent"];
?>
		<select name="parent">
			<option value="0">None</option>
			<?php echo join("\n", $option);?>
		</select>
	</div>
	<!-- HTML text editor start -->
<?php
	if($templateObject->parent!=='0')
			$parent = getParentValues($templateObject->parent);
	
    if(!empty($parent)) {
		$head = $parent['head'];
		$foot = $parent['foot'];
    }
    
?>
	<div style="width:100%;position:relative">
	    <div style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
	    	<span style="float:left;font-weight:bold;"><?php echo $_lang['template_code']; ?></span>
		</div>
	<?php if(isset($head)) echo $head;?>
	<?php
		if($templateObject->locked==1)
			$readonly = 'readonly';
		else $readonly = '';
	?>
        <textarea dir="ltr" name="content" <?php echo $readonly;?> class="phptextarea" style="width:100%; height: 370px;"><?php echo htmlspecialchars($templateObject->content); ?></textarea>
	<?php if(isset($foot)) echo $foot;?>
	</div>
	<!-- HTML text editor end -->
	</div>

<div class="tab-page" id="tabProp">
<h2 class="tab"><?php echo $_lang['settings_properties'];?></h2>
<table>
	  <tr>
		<th><?php echo $_lang['existing_category']; ?>:</th>
		<td><select name="categoryid" style="width:300px;">
				<option value="0"><?php echo $_lang["no_category"]; ?></option>
		        <?php
					$ds = $modx->manager->getCategories();
					if($ds) foreach($ds as $n=>$v)
					{
						echo "<option value='".$v['id']."'".($templateObject->category==$v['id']? " selected='selected'":"").">".htmlspecialchars($v['category'])."</option>";
					}
				?>
				<option value="-1">&gt;&gt; <?php echo $_lang["new_category"]; ?></option>
			</select>
		</td>
	</tr>
	<tr id="newcategry" style="display:none;">
		<th valign="top" style="padding-top:5px;"><?php echo $_lang['new_category']; ?>:</th>
		<td valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="<?php echo isset($templateObject->newcategory) ? $templateObject->newcategory : '' ?>" class="inputBox" style="width:300px;"></td>
	</tr>
	<tr>
		<th><?php echo $_lang['template_desc']; ?>:&nbsp;&nbsp;</th>
		<td><textarea name="description" style="padding:0;height:4em;"><?php echo htmlspecialchars($templateObject->description);?></textarea></td>
	</tr>
<?php if($modx->hasPermission('save_template')==1) {?>
	  <tr>
	    <td colspan="2">
	    <label><input name="locked" type="checkbox" <?php echo $templateObject->locked==1 ? "checked='checked'" : "" ;?> class="inputBox"> <?php echo $_lang['lock_template']; ?> <span class="comment"><?php echo $_lang['lock_template_msg']; ?></span></label></td>
	  </tr>
<?php } ?>
</table>
</div>

<?php
if ($_REQUEST['a'] == '16')
{
	$field = "tv.name as 'name', tv.id as 'id', tpl.templateid as tplid, tpl.rank, if(isnull(cat.category),'{$_lang['no_category']}',cat.category) as category, tv.description as 'desc'";
	$from  = "[+prefix+]site_tmplvar_templates tpl";
	$from .= " INNER JOIN [+prefix+]site_tmplvars tv ON tv.id = tpl.tmplvarid";
	$from .= " LEFT JOIN [+prefix+]categories cat ON tv.category = cat.id";
	$where = "tpl.templateid='{$id}'";
	$orderby = 'tpl.rank, tv.rank, tv.id';
	$rs = $modx->db->select($field,$from,$where,$orderby);
	$total = $modx->db->getRecordCount($rs);
?>
	
	<div class="tab-page" id="tabInfo">
		<h2 class="tab"><?php echo $_lang["info"] ?></h2>
		<?php echo "<p>{$_lang['template_tv_msg']}</p>"; ?>
		<div class="sectionHeader">
			<?php echo $_lang["template_assignedtv_tab"];?>
		</div>
		<div class="sectionBody">
<?php
	if($total>0)
	{
		$tvList = '<ul>';
		while ($row = $modx->db->getRow($rs))
		{
			$desc = $row['desc'] ? " ({$row['desc']})" : '';
			$tvList .= '<li><a href="index.php?id=' . $row['id'] . '&amp;a=301">'.$row['name'] . '</a>' . $desc . '</li>';
		}
		$tvList .= '</ul>';
	}
	else
	{
		$tvList = $_lang['template_no_tv'];
	}
	echo $tvList;
?>
			<ul class="actionButtons" style="margin-top:15px;">
<?php
	$query = $_GET['id'] ? '&amp;tpl=' . intval($_GET['id']) : '';
?>
				<li><a href="index.php?&amp;a=300<?php echo $query;?>"><img src="<?php echo $_style['icons_add'];?>" /> <?php echo $_lang['new_tmplvars'];?></a></li>
<?php
	if($modx->hasPermission('save_template') && $total > 1)
	{
		echo '<li><a href="index.php?a=117&amp;id=' . $_REQUEST['id'] . '"><img src="' . $_style['sort'] . '" />' . $_lang['template_tv_edit'] . '</a></li>';
	}
?>
		</ul>
		</div>
		<div class="sectionHeader"><?php echo $_lang['a16_use_resources']; ?></div>
		<div class="sectionBody"><div id="use_resources"></div></div>
	</div>
<?php
}
?>
<div class="tab-page" id="tabHelp">
<h2 class="tab">ヘルプ</h2>
<?php echo $_lang['template_msg'];?>
</div>
<?php
// invoke OnTempFormRender event
$tmp = array('id' => $id);
$evtOut = $modx->invokeEvent("OnTempFormRender",$tmp);
if(is_array($evtOut)) echo implode("",$evtOut);
?>
</form>
</div>

<?php
function getParentValues($parent) {
	global $modx;
	
	$parent = $modx->db->getObject('site_templates',"id='{$parent}'");
	if(count($parent)==1 && strpos($parent->content,'[*content*]')!==false) {
		$content = explode('[*content*]',$parent->content,2);
		$divstyle = "border:1px solid #C3C3C3;padding:1em;background-color:#f7f7f7;";
		$prestyle = "white-space: pre-wrap;display:block;width:auto; font-family: 'Courier New','Courier', monospace;";
		$head = convert($content[0]);
		$head = sprintf('<div style="%s border-bottom:none;"><pre style="%s">%s</pre></div>',$divstyle,$prestyle,$head);
		$foot = convert($content[1]);
		$foot = sprintf('<div style="%s border-top:none;"><pre style="%s">%s</pre></div>',$divstyle,$prestyle,$foot);
		return compact('head','foot');
	}
	else return array();
}

function convert($value) {
	global $modx;
	$value = trim($value);
	$value = htmlspecialchars($value, ENT_QUOTES, $modx->config['modx_charset']);
	return $value;
}
