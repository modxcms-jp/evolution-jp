<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

switch((int) $_REQUEST['a']) {
  case 35:
    if(!$modx->hasPermission('edit_role')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  case 38:
    if(!$modx->hasPermission('new_role')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  default:
    $e->setError(3);
    $e->dumpError();
}

$role = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;


// check to see the role editor isn't locked
$sql = "SELECT internalKey, username FROM $dbase.`".$table_prefix."active_users` WHERE $dbase.`".$table_prefix."active_users`.action=35 and $dbase.`".$table_prefix."active_users`.id=$role";
$rs = mysql_query($sql);
$limit = mysql_num_rows($rs);
if($limit>1) {
	for ($i=0;$i<$limit;$i++) {
		$lock = mysql_fetch_assoc($rs);
		if($lock['internalKey']!=$modx->getLoginUserID()) {
			$msg = sprintf($_lang["lock_msg"],$lock['username'],"role");
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}
// end check for lock



if($_REQUEST['a']=='35') {
	$sql = "SELECT * FROM $dbase.`".$table_prefix."user_roles` WHERE $dbase.`".$table_prefix."user_roles`.id=".$role.";";
	$rs = mysql_query($sql);
	$limit = mysql_num_rows($rs);
	if($limit>1) {
		echo "More than one role returned!<p>";
		exit;
	}
	if($limit<1) {
		echo "No role returned!<p>";
		exit;
	}
	$roledata = mysql_fetch_assoc($rs);
	$_SESSION['itemname']=$roledata['name'];
} else {
	$roledata = 0;
	$_SESSION['itemname']="New role";
}



?>
<script type="text/javascript">
function changestate(element) {
	documentDirty=true;
	currval = eval(element).value;
	if(currval==1) {
		eval(element).value=0;
	} else {
		eval(element).value=1;
	}
}

function deletedocument() {
	if(confirm("<?php echo $_lang['confirm_delete_role']; ?>")==true) {
		document.location.href="index.php?id=" + document.userform.id.value + "&a=37";
	}
}

</script>
<form action="index.php?a=36" method="post" name="userform">
<input type="hidden" name="mode" value="<?php echo $_GET['a'] ?>">
<input type="hidden" name="id" value="<?php echo $_GET['id'] ?>">

<h1><?php echo $_lang['role_title']; ?></h1>

<div id="actions">
	<ul class="actionButtons">
			<li><a href="#" onclick="documentDirty=false; document.userform.save.click();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang['save'] ?></a></li>
			<li id="btn_del"><a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete"] ?>" /> <?php echo $_lang['delete'] ?></a></li>
			<li><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=86';"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel'] ?></a></li>
	</ul>
	<?php if($_GET['a']=='38') { ?>
	<script type="text/javascript">document.getElementById("btn_del").className='disabled';</script>
	<?php } ?>
</div>

<div class="sectionBody">
<fieldset>
<table border="0" cellspacing="0" cellpadding="4">
  <tr>
    <td><?php echo $_lang['role_name']; ?>:</td>
    <td>&nbsp;</td>
    <td><input name="name" type="text" maxlength=50 value="<?php echo $roledata['name'] ; ?>" onchange="documentDirty=true;"></td>
  </tr>
  <tr>
    <td><?php echo $_lang['resource_description']; ?>:</td>
    <td>&nbsp;</td>
    <td><input name="description" type="text" maxlength=255 value="<?php echo $roledata['description'] ; ?>" size="60" onchange="documentDirty=true;"></td>
  </tr>
</table>
</fieldset>
<style type="text/css">
label {display:block;}
</style>
<fieldset>
<h3><?php echo $_lang['page_data_general']; ?></h3>
<label>
	<input name="framescheck" type="checkbox" onclick="changestate(document.userform.frames)" checked disabled>
	<input type="hidden" name="frames" value="1">
	<?php echo $_lang['role_frames']; ?>
</label>
<label>
	<input name="homecheck" type="checkbox" onclick="changestate(document.userform.home)" checked disabled>
	<input type="hidden" name="home" value="1">
	<?php echo $_lang['role_home']; ?>
</label>
<label>
	<input name="messagescheck" type="checkbox" onclick="changestate(document.userform.messages)" <?php echo $roledata['messages']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="messages" value="<?php echo $roledata['messages']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_messages']; ?>
</label>
<label>
	<input name="logoutcheck" type="checkbox" onclick="changestate(document.userform.logout)" checked disabled>
	<input type="hidden" name="logout" value="1">
	<?php echo $_lang['role_logout']; ?>
</label>
<label>
	<input name="helpcheck" type="checkbox" onclick="changestate(document.userform.help)" <?php echo $roledata['help']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="help" value="<?php echo $roledata['help']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_help']; ?>
</label>
<label>
	<input name="action_okcheck" type="checkbox" onclick="changestate(document.userform.action_ok)" checked disabled>
	<input type="hidden" name="action_ok" value="1">
	<?php echo $_lang['role_actionok']; ?>
</label>
<label>
	<input name="error_dialogcheck" type="checkbox" onclick="changestate(document.userform.error_dialog)" checked disabled>
	<input type="hidden" name="error_dialog" value="1">
	<?php echo $_lang['role_errors']; ?>
</label>
<label>
	<input name="aboutcheck" type="checkbox" onclick="changestate(document.userform.about)" checked disabled>
	<input type="hidden" name="about" value="1">
	<?php echo $_lang['role_about']; ?>
</label>

<label>
	<input name="creditscheck" type="checkbox" onclick="changestate(document.userform.credits)" checked disabled>
	<input type="hidden" name="credits" value="1">
	<?php echo $_lang['role_credits']; ?>
</label>
<label>
	<input name="change_passwordcheck" type="checkbox" onclick="changestate(document.userform.change_password)" <?php echo $roledata['change_password']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="change_password" value="<?php echo $roledata['change_password']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_change_password']; ?>
</label>
<label>
	<input name="save_passwordcheck" type="checkbox" onclick="changestate(document.userform.save_password)" <?php echo $roledata['save_password']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_password" value="<?php echo $roledata['save_password']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_password']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_content_management']; ?></h3>
<label>
	<input name="view_documentcheck" type="checkbox" onclick="changestate(document.userform.view_document)" checked disabled>
	<input type="hidden" name="view_document" value="1">
	<?php echo $_lang['role_view_docdata']; ?>
</label>
<label>
	<input name="new_documentcheck" type="checkbox" onclick="changestate(document.userform.new_document)" <?php echo $roledata['new_document']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_document" value="<?php echo $roledata['new_document']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_create_doc']; ?>
</label>
<label>
	<input name="edit_documentcheck" type="checkbox" onclick="changestate(document.userform.edit_document)" <?php echo $roledata['edit_document']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_document" value="<?php echo $roledata['edit_document']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_doc']; ?>
</label>
<label>
	<input name="save_documentcheck" type="checkbox" onclick="changestate(document.userform.save_document)" <?php echo $roledata['save_document']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_document" value="<?php echo $roledata['save_document']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_doc']; ?>
</label>
<label>
	<input name="publish_documentcheck" type="checkbox" onclick="changestate(document.userform.publish_document)" <?php echo $roledata['publish_document']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="publish_document" value="<?php echo $roledata['publish_document']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_publish_doc']; ?>
</label>
<label>
	<input name="delete_documentcheck" type="checkbox" onclick="changestate(document.userform.delete_document)" <?php echo $roledata['delete_document']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_document" value="<?php echo $roledata['delete_document']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_doc']; ?>
</label>
<label>
	<input name="empty_trashcheck" type="checkbox" onclick="changestate(document.userform.empty_trash)" <?php echo $roledata['empty_trash']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="empty_trash" value="<?php echo $roledata['empty_trash']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_empty_trash']; ?>
</label>
<label>
	<input name="edit_doc_metatagscheck" type="checkbox" onclick="changestate(document.userform.edit_doc_metatags)" <?php echo $roledata['edit_doc_metatags']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_doc_metatags" value="<?php echo $roledata['edit_doc_metatags']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_doc_metatags']; ?>
</label>
<label>
	<input name="empty_cachecheck" type="checkbox" onclick="changestate(document.userform.empty_cache)" checked disabled>
	<input type="hidden" name="empty_cache" value="1">
	<?php echo $_lang['role_cache_refresh']; ?>
</label>
<label>
	<input name="view_unpublishedcheck" type="checkbox" onclick="changestate(document.userform.view_unpublished)" <?php echo $roledata['view_unpublished']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="view_unpublished" value="<?php echo $roledata['view_unpublished']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_view_unpublished']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_template_management']; ?></h3>
<label>
	<input name="new_templatecheck" type="checkbox" onclick="changestate(document.userform.new_template)" <?php echo $roledata['new_template']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_template" value="<?php echo $roledata['new_template']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_create_template']; ?>
</label>
<label>
	<input name="edit_templatecheck" type="checkbox" onclick="changestate(document.userform.edit_template)" <?php echo $roledata['edit_template']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_template" value="<?php echo $roledata['edit_template']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_template']; ?>
</label>
<label>
	<input name="save_templatecheck" type="checkbox" onclick="changestate(document.userform.save_template)" <?php echo $roledata['save_template']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_template" value="<?php echo $roledata['save_template']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_template']; ?>
</label>
<label>
	<input name="delete_templatecheck" type="checkbox" onclick="changestate(document.userform.delete_template)" <?php echo $roledata['delete_template']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_template" value="<?php echo $roledata['delete_template']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_template']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_snippet_management']; ?></h3>
<label>
	<input name="new_snippetcheck" type="checkbox" onclick="changestate(document.userform.new_snippet)" <?php echo $roledata['new_snippet']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_snippet" value="<?php echo $roledata['new_snippet']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_create_snippet']; ?>
</label>
<label>
	<input name="edit_snippetcheck" type="checkbox" onclick="changestate(document.userform.edit_snippet)" <?php echo $roledata['edit_snippet']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_snippet" value="<?php echo $roledata['edit_snippet']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_snippet']; ?>
</label>
<label>
	<input name="save_snippetcheck" type="checkbox" onclick="changestate(document.userform.save_snippet)" <?php echo $roledata['save_snippet']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_snippet" value="<?php echo $roledata['save_snippet']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_snippet']; ?>
</label>
<label>
	<input name="delete_snippetcheck" type="checkbox" onclick="changestate(document.userform.delete_snippet)" <?php echo $roledata['delete_snippet']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_snippet" value="<?php echo $roledata['delete_snippet']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_snippet']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_chunk_management']; ?></h3>
<label>
	<input name="new_chunkcheck" type="checkbox" onclick="changestate(document.userform.new_chunk)" <?php echo $roledata['new_chunk']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_chunk" value="<?php echo $roledata['new_chunk']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_create_chunk']; ?>
</label>
<label>
	<input name="edit_chunkcheck" type="checkbox" onclick="changestate(document.userform.edit_chunk)" <?php echo $roledata['edit_chunk']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_chunk" value="<?php echo $roledata['edit_chunk']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_chunk']; ?>
</label>
<label>
	<input name="save_chunkcheck" type="checkbox" onclick="changestate(document.userform.save_chunk)" <?php echo $roledata['save_chunk']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_chunk" value="<?php echo $roledata['save_chunk']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_chunk']; ?>
</label>
<label>
	<input name="delete_chunkcheck" type="checkbox" onclick="changestate(document.userform.delete_chunk)" <?php echo $roledata['delete_chunk']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_chunk" value="<?php echo $roledata['delete_chunk']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_chunk']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_plugin_management']; ?></h3>
<label>
	<input name="new_plugincheck" type="checkbox" onclick="changestate(document.userform.new_plugin)" <?php echo $roledata['new_plugin']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_plugin" value="<?php echo $roledata['new_plugin']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_create_plugin']; ?>
</label>
<label>
	<input name="edit_plugincheck" type="checkbox" onclick="changestate(document.userform.edit_plugin)" <?php echo $roledata['edit_plugin']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_plugin" value="<?php echo $roledata['edit_plugin']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_plugin']; ?>
</label>
<label>
	<input name="save_plugincheck" type="checkbox" onclick="changestate(document.userform.save_plugin)" <?php echo $roledata['save_plugin']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_plugin" value="<?php echo $roledata['save_plugin']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_plugin']; ?>
</label>
<label>
	<input name="delete_plugincheck" type="checkbox" onclick="changestate(document.userform.delete_plugin)" <?php echo $roledata['delete_plugin']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_plugin" value="<?php echo $roledata['delete_plugin']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_plugin']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_module_management']; ?></h3>
<label>
	<input name="new_modulecheck" type="checkbox" onclick="changestate(document.userform.new_module)" <?php echo $roledata['new_module']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_module" value="<?php echo $roledata['new_module']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_new_module']; ?>
</label>
<label>
	<input name="edit_modulecheck" type="checkbox" onclick="changestate(document.userform.edit_module)" <?php echo $roledata['edit_module']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_module" value="<?php echo $roledata['edit_module']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_module']; ?>
</label>
<label>
	<input name="save_modulecheck" type="checkbox" onclick="changestate(document.userform.save_module)" <?php echo $roledata['save_module']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_module" value="<?php echo $roledata['save_module']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_module']; ?>
</label>
<label>
	<input name="delete_modulecheck" type="checkbox" onclick="changestate(document.userform.delete_module)" <?php echo $roledata['delete_module']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_module" value="<?php echo $roledata['delete_module']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_module']; ?>
</label>
<label>
	<input name="exec_modulecheck" type="checkbox" onclick="changestate(document.userform.exec_module)" <?php echo $roledata['exec_module']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="exec_module" value="<?php echo $roledata['exec_module']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_run_module']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_eventlog_management']; ?></h3>
<label>
	<input name="view_eventlogcheck" type="checkbox" onclick="changestate(document.userform.view_eventlog)" <?php echo $roledata['view_eventlog']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="view_eventlog" value="<?php echo $roledata['view_eventlog']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_view_eventlog']; ?>
</label>
<label>
	<input name="delete_eventlogcheck" type="checkbox" onclick="changestate(document.userform.delete_eventlog)" <?php echo $roledata['delete_eventlog']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_eventlog" value="<?php echo $roledata['delete_eventlog']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_eventlog']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_user_management']; ?></h3>
<label>
	<input name="new_usercheck" type="checkbox" onclick="changestate(document.userform.new_user)" <?php echo $roledata['new_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_user" value="<?php echo $roledata['new_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_new_user']; ?>
</label>
<label>
	<input name="edit_usercheck" type="checkbox" onclick="changestate(document.userform.edit_user)" <?php echo $roledata['edit_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_user" value="<?php echo $roledata['edit_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_user']; ?>
</label>
<label>
	<input name="save_usercheck" type="checkbox" onclick="changestate(document.userform.save_user)" <?php echo $roledata['save_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_user" value="<?php echo $roledata['save_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_user']; ?>
</label>
<label>
	<input name="delete_usercheck" type="checkbox" onclick="changestate(document.userform.delete_user)" <?php echo $roledata['delete_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_user" value="<?php echo $roledata['delete_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_user']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_web_user_management']; ?></h3>
<label>
	<input name="new_web_usercheck" type="checkbox" onclick="changestate(document.userform.new_web_user)" <?php echo $roledata['new_web_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_web_user" value="<?php echo $roledata['new_web_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_new_web_user']; ?>
</label>
<label>
	<input name="edit_web_usercheck" type="checkbox" onclick="changestate(document.userform.edit_web_user)" <?php echo $roledata['edit_web_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_web_user" value="<?php echo $roledata['edit_web_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_web_user']; ?>
</label>
<label>
	<input name="save_web_usercheck" type="checkbox" onclick="changestate(document.userform.save_web_user)" <?php echo $roledata['save_web_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_web_user" value="<?php echo $roledata['save_web_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_web_user']; ?>
</label>
<label>
	<input name="delete_web_usercheck" type="checkbox" onclick="changestate(document.userform.delete_web_user)" <?php echo $roledata['delete_web_user']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_web_user" value="<?php echo $roledata['delete_web_user']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_web_user']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_udperms']; ?></h3>
<label>
	<input name="access_permissionscheck" type="checkbox" onclick="changestate(document.userform.access_permissions)" <?php echo $roledata['access_permissions']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="access_permissions" value="<?php echo $roledata['access_permissions']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_access_persmissions']; ?>
</label>
<label>
	<input name="web_access_permissionscheck" type="checkbox" onclick="changestate(document.userform.web_access_permissions)" <?php echo $roledata['web_access_permissions']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="web_access_permissions" value="<?php echo $roledata['web_access_permissions']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_web_access_persmissions']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_role_management']; ?></h3>
<label>
	<input name="new_rolecheck" type="checkbox" onclick="changestate(document.userform.new_role)" <?php echo $roledata['new_role']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="new_role" value="<?php echo $roledata['new_role']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_new_role']; ?>
</label>
<label>
	<input name="edit_rolecheck" type="checkbox" onclick="changestate(document.userform.edit_role)" <?php echo $roledata['edit_role']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="edit_role" value="<?php echo $roledata['edit_role']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_role']; ?>
</label>
<label>
	<input name="save_rolecheck" type="checkbox" onclick="changestate(document.userform.save_role)" <?php echo $roledata['save_role']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="save_role" value="<?php echo $roledata['save_role']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_save_role']; ?>
</label>
<label>
	<input name="delete_rolecheck" type="checkbox" onclick="changestate(document.userform.delete_role)" <?php echo $roledata['delete_role']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="delete_role" value="<?php echo $roledata['delete_role']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_delete_role']; ?>
</label>
</fieldset>

<fieldset>
<h3><?php echo $_lang['role_config_management']; ?></h3>
<label>
	<input name="logscheck" type="checkbox" onclick="changestate(document.userform.logs)" <?php echo $roledata['logs']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="logs" value="<?php echo $roledata['logs']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_view_logs']; ?>
</label>
<label>
	<input name="settingscheck" type="checkbox" onclick="changestate(document.userform.settings)" <?php echo $roledata['settings']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="settings" value="<?php echo $roledata['settings']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_edit_settings']; ?>
</label>
<label>
	<input name="file_managercheck" type="checkbox" onclick="changestate(document.userform.file_manager)" <?php echo $roledata['file_manager']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="file_manager" value="<?php echo $roledata['file_manager']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_file_manager']; ?>
</label>
<label>
	<input name="bk_managercheck" type="checkbox" onclick="changestate(document.userform.bk_manager)" <?php echo $roledata['bk_manager']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="bk_manager" value="<?php echo $roledata['bk_manager']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_bk_manager']; ?>
</label>
<label>
	<input name="manage_metatagscheck" type="checkbox" onclick="changestate(document.userform.manage_metatags)" <?php echo $roledata['manage_metatags']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="manage_metatags" value="<?php echo $roledata['manage_metatags']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_manage_metatags']; ?>
</label>
<label>
	<input name="importcheck" type="checkbox" onclick="changestate(document.userform.import_static)" <?php echo $roledata['import_static']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="import_static" value="<?php echo $roledata['import_static']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_import_static']; ?>
</label>
<label>
	<input name="exportcheck" type="checkbox" onclick="changestate(document.userform.export_static)" <?php echo $roledata['export_static']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="export_static" value="<?php echo $roledata['export_static']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_export_static']; ?>
</label>
<label>
	<input name="removelockscheck" type="checkbox" onclick="changestate(document.userform.remove_locks)" <?php echo $roledata['remove_locks']==1 ? "checked" : "" ; ?>>
	<input type="hidden" name="remove_locks" value="<?php echo $roledata['remove_locks']==1 ? 1 : 0 ; ?>">
	<?php echo $_lang['role_remove_locks']; ?>
</label>
</fieldset>

<input type="submit" name="save" style="display:none">
</form>

</div>
