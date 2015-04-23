<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

$tbl_active_users        = $modx->getFullTableName('active_users');
$tbl_web_user_attributes = $modx->getFullTableName('web_user_attributes');
$tbl_web_user_settings = $modx->getFullTableName('web_user_settings');
$tbl_web_users = $modx->getFullTableName('web_users');
$tbl_web_groups = $modx->getFullTableName('web_groups');
$tbl_webgroup_names = $modx->getFullTableName('webgroup_names');

switch((int) $_REQUEST['a']) {
  case 88:
    if(!$modx->hasPermission('edit_web_user')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  case 87:
    if(!$modx->hasPermission('new_web_user')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  default:
    $e->setError(3);
    $e->dumpError();
}

$user = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;


// check to see the snippet editor isn't locked
$rs = $modx->db->select('internalKey, username',$tbl_active_users,"action='88' AND id='{$user}'");
$limit = $modx->db->getRecordCount($rs);
if($limit>1) {
	for ($i=0;$i<$limit;$i++) {
		$lock = $modx->db->getRow($rs);
		if($lock['internalKey']!=$modx->getLoginUserID()) {
			$msg = sprintf($_lang["lock_msg"],$lock['username'],"web user");
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}
// end check for lock

if($_REQUEST['a']=='88') {
	// get user attributes
	$rs = $modx->db->select('*',$tbl_web_user_attributes,"internalKey='{$user}'");
	$limit = $modx->db->getRecordCount($rs);
	if($limit>1) {
		echo "More than one user returned!<p>";
		exit;
	}
	if($limit<1) {
		echo "No user returned!<p>";
		exit;
	}
	$userdata = $modx->db->getRow($rs);

	// get user settings
	$rs = $modx->db->select('*',$tbl_web_user_settings,"webuser='{$user}'");
	$usersettings = array();
	while($row = $modx->db->getRow($rs))
	{
		$usersettings[$row['setting_name']]=$row['setting_value'];
	}
	extract($usersettings, EXTR_OVERWRITE);

	// get user name
	$rs = $modx->db->select('*',$tbl_web_users,"id='{$user}'");
	$limit = $modx->db->getRecordCount($rs);
	if($limit>1) {
		echo "More than one user returned while getting username!<p>";
		exit;
	}
	if($limit<1) {
		echo "No user returned while getting username!<p>";
		exit;
	}
	$usernamedata = $modx->db->getRow($rs);
	$_SESSION['itemname']=$usernamedata['username'];
} else {
	$userdata = array();
	$usersettings = array();
	$usernamedata = array();
	$_SESSION['itemname']="New web user";
}

// restore saved form
$formRestored = false;
if($modx->manager->hasFormValues()) {
	$form_v = $modx->manager->loadFormValues();
	// restore post values
	$userdata = array_merge($userdata,$form_v);
	$userdata['dob'] = ConvertDate($userdata['dob']);
	$usernamedata['username'] = $userdata['newusername'];
	$usernamedata['oldusername'] = $form_v['oldusername'];
	$usersettings = array_merge($usersettings,$userdata);
	$usersettings['allowed_days'] = is_array($form_v['allowed_days']) ? implode(",",$form_v['allowed_days']):"";
	extract($usersettings, EXTR_OVERWRITE);
}

// include the country list language file
$_country_lang = array();
$base_path = $modx->config['base_path'];
if($manager_language!="english" && is_file(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php"))
{
    include_once(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php");
}
else
{
    include_once(MODX_CORE_PATH . 'lang/country/english_country.inc.php');
}

?>
<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
window.addEvent('domready', function() {
	var dpOffset = <?php echo $modx->config['datepicker_offset']; ?>;
	var dpformat = "<?php echo $modx->config['datetime_format']; ?>";
	new DatePicker($('dob'), {'yearOffset': -90,'yearRange':1,'format':dpformat});
	if ($('blockeduntil')) {
		new DatePicker($('blockeduntil'), {'yearOffset': dpOffset,'format':dpformat + ' hh:mm:00'});
		new DatePicker($('blockedafter'), {'yearOffset': dpOffset,'format':dpformat + ' hh:mm:00'});
	}
});

function changestate(element) {
	documentDirty=true;
	currval = eval(element).value;
	if(currval==1) {
		eval(element).value=0;
	} else {
		eval(element).value=1;
	}
}

function changePasswordState(element) {
	currval = eval(element).value;
	if(currval==1) {
		document.getElementById("passwordBlock").style.display="block";
	} else {
		document.getElementById("passwordBlock").style.display="none";
	}
}

function changeblockstate(element, checkelement) {
	currval = eval(element).value;
	if(currval==1) {
		if(confirm("<?php echo $_lang['confirm_unblock']; ?>")==true){
			document.userform.blocked.value=0;
			document.userform.blockeduntil.value="";
			document.userform.blockedafter.value="";
			document.userform.failedlogincount.value=0;
			blocked.innerHTML="<b><?php echo $_lang['unblock_message']; ?></b>";
			blocked.className="TD";
			eval(element).value=0;
		} else {
			eval(checkelement).checked=true;
		}
	} else {
		if(confirm("<?php echo $_lang['confirm_block']; ?>")==true){
			document.userform.blocked.value=1;
			blocked.innerHTML="<b><?php echo $_lang['block_message']; ?></b>";
			blocked.className="warning";
			eval(element).value=1;
		} else {
			eval(checkelement).checked=false;
		}
	}
}

function resetFailed() {
	document.userform.failedlogincount.value=0;
	document.getElementById("failed").innerHTML="0";
}

function deleteuser() {
	if(confirm("<?php echo $_lang['confirm_delete_user']; ?>")==true) {
		document.location.href="index.php?id=" + document.userform.id.value + "&a=90";
	}
}

// change name
function changeName(){
	if(confirm("<?php echo $_lang['confirm_name_change']; ?>")==true) {
		var e1 = document.getElementById("showname");
		var e2 = document.getElementById("editname");
		e1.style.display = "none";
		e2.style.display = "<?php echo $displayStyle; ?>";
	}
}

function OpenServerBrowser(url, width, height ) {
	var iLeft = (screen.width  - width) / 2 ;
	var iTop  = (screen.height - height) / 2 ;

	var sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes" ;
	sOptions += ",width=" + width ;
	sOptions += ",height=" + height ;
	sOptions += ",left=" + iLeft ;
	sOptions += ",top=" + iTop ;

	var oWindow = window.open( url, "FCKBrowseWindow", sOptions ) ;
}
function BrowseServer() {
	var w = screen.width * 0.7;
	var h = screen.height * 0.7;
	OpenServerBrowser("<?php echo $base_url; ?>manager/media/browser/mcpuk/browser.php?Type=images", w, h);
}
function SetUrl(url, width, height, alt){
	document.userform.photo.value = url;
	document.images['iphoto'].src = "<?php echo $base_url; ?>" + url;
}
</script>

<style type="text/css">
	table.settings {border-collapse:collapse;width:100%;}
	table.settings tr {border-bottom:1px dotted #ccc;}
	table.settings th {font-size:inherit;vertical-align:top;text-align:left;}
	table.settings th,table.settings td {padding:5px;}
</style>

<form action="index.php?a=89" method="post" name="userform" enctype="multipart/form-data">
<?php
	// invoke OnWUsrFormPrerender event
	$evtOut = $modx->invokeEvent("OnWUsrFormPrerender",array("id" => $user));
	if(is_array($evtOut)) echo implode("",$evtOut);
?>
<input type="hidden" name="mode" value="<?php echo $_GET['a'] ?>" />
<input type="hidden" name="id" value="<?php echo $_GET['id'] ?>" />
<input type="hidden" name="blockedmode" value="<?php echo ($userdata['blocked']==1 || ($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0)|| ($userdata['blockedafter']<time() && $userdata['blockedafter']!=0) || $userdata['failedlogins']>3) ? "1":"0" ?>" />

<h1><?php echo $_lang['web_user_title']; ?></h1>

<div id="actions">
	<ul class="actionButtons">
<?php if($modx->hasPermission('save_web_user')):?>
			<li><a href="#" onclick="documentDirty=false; document.userform.save.click();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang['update']; ?></a><span class="and"> + </span>
			<select id="stay" name="stay">
			  <?php if ($modx->hasPermission('new_web_user')) { ?>
			  <option id="stay1" value="1" <?php echo $_REQUEST['stay']=='1' ? ' selected=""' : ''?> ><?php echo $_lang['stay_new']?></option>
			  <?php } ?>
			  <option id="stay2" value="2" <?php echo $_REQUEST['stay']=='2' ? ' selected="selected"' : ''?> ><?php echo $_lang['stay']?></option>
			  <option id="stay3" value=""  <?php echo $_REQUEST['stay']=='' ? ' selected=""' : ''?>  ><?php echo $_lang['close']?></option>
			</select>
			</li>
<?php endif; ?>
			<?php if ($_REQUEST['a'] == '88') { ?>
			<li><a href="#" onclick="deleteuser();"><img src="<?php echo $_style["icons_delete"] ?>" /> <?php echo $_lang['delete']; ?></a></li>
<?php } ?>
			<li><a href="#" onclick="document.location.href='index.php?a=99';"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']; ?></a></li>
	</ul>
</div>

<!-- Tab Start -->
<div class="sectionBody">
<div class="tab-pane" id="webUserPane">
	<script type="text/javascript">
		tpUser = new WebFXTabPane(document.getElementById( "webUserPane" ), <?php echo (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?> );
	</script>
    <div class="tab-page" id="tabGeneral">
    	<h2 class="tab"><?php echo $_lang["login_settings"] ?></h2>
    	<script type="text/javascript">tpUser.addTabPage( document.getElementById( "tabGeneral" ) );</script>
		<table class="settings">
		  <tr>
			<td>
				<span id="blocked" class="warning"><?php if($userdata['blocked']==1 || ($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0)|| ($userdata['blockedafter']<time() && $userdata['blockedafter']!=0) || $userdata['failedlogins']>3) { ?><b><?php echo $_lang['user_is_blocked']; ?></b><?php } ?></span>
			</td>
		  </tr>
		  <?php if(!empty($userdata['id'])) { ?>
		  <tr id="showname" style="display: <?php echo ($_GET['a']=='88' && (!isset($usernamedata['oldusername'])||$usernamedata['oldusername']==$usernamedata['username'])) ? $displayStyle : 'none';?> ">
			<td colspan="2">
				<img src="<?php echo $_style['icons_user'] ?>" alt="." />&nbsp;<b><?php echo !empty($usernamedata['oldusername']) ? $usernamedata['oldusername']:$usernamedata['username']; ?></b> - <span class="comment"><a href="#" onclick="changeName();return false;"><?php echo $_lang["change_name"]; ?></a></span>
				<input type="hidden" name="oldusername" value="<?php echo htmlspecialchars(!empty($usernamedata['oldusername']) ? $usernamedata['oldusername']:$usernamedata['username']); ?>" />
			</td>
		  </tr>
		  <?php } ?>
		  <tr id="editname" style="display:<?php echo $_GET['a']=='87'||(isset($usernamedata['oldusername']) && $usernamedata['oldusername']!=$usernamedata['username']) ? $displayStyle : 'none' ; ?>">
			<th><?php echo $_lang['username']; ?>:</th>
			<td><input type="text" name="newusername" class="inputBox" value="<?php echo htmlspecialchars(isset($_POST['newusername']) ? $_POST['newusername'] : $usernamedata['username']); ?>" maxlength="100" /></td>
		  </tr>
		  <tr>
			<th valign="top"><?php echo $_GET['a']=='87' ? $_lang['password'].":" : $_lang['change_password_new'].":" ; ?></th>
			<td>
				<?php if($_REQUEST['a']!=='87'):?>
				<input name="newpasswordcheck" type="checkbox" onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);">
				<?php endif;?>
				<input type="hidden" name="newpassword" value="<?php echo $_REQUEST['a']=="87" ? 1 : 0 ; ?>" /><br />
				<div style="display:<?php echo $_REQUEST['a']=="87" ? "block": "none" ; ?>" id="passwordBlock">
				<fieldset style="width:300px;padding:0;">
				<label><input type=radio name="passwordgenmethod" value="g" <?php echo $_POST['passwordgenmethod']=="spec" ? "" : 'checked="checked"'; ?> /><?php echo $_lang['password_gen_gen']; ?></label><br />
				<label><input type=radio name="passwordgenmethod" value="spec" <?php echo $_POST['passwordgenmethod']=="spec" ? 'checked="checked"' : ""; ?>><?php echo $_lang['password_gen_specify']; ?></label> <br />
				<div style="padding-left:20px">
				<label for="specifiedpassword" style="width:120px"><?php echo $_lang['change_password_new']; ?>:</label>
				<input type="password" name="specifiedpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
				<label for="confirmpassword" style="width:120px"><?php echo $_lang['change_password_confirm']; ?>:</label>
				<input type="password" name="confirmpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
				<span class="warning" style="font-weight:normal"><?php echo $_lang['password_gen_length']; ?></span>
				</div>
				</fieldset>
				<br />
				<fieldset style="width:300px;padding:0;">
				<label><input type=radio name="passwordnotifymethod" value="e" <?php echo $_POST['passwordnotifymethod']=="e" ? 'checked="checked"' : ""; ?> /><?php echo $_lang['password_method_email']; ?></label><br />
				<label><input type=radio name="passwordnotifymethod" value="s" <?php echo $_POST['passwordnotifymethod']=="e" ? "" : 'checked="checked"'; ?> /><?php echo $_lang['password_method_screen']; ?></label>
				</fieldset>
				</div>
			</td>
		  </tr>
			<tr>
				<th><?php echo $_lang['user_email']; ?>:</th>
				<td>
				<input type="text" name="email" class="inputBox" value="<?php echo  isset($_POST['email']) ? $_POST['email'] : $userdata['email']; ?>" />
				<input type="hidden" name="oldemail" value="<?php echo htmlspecialchars(!empty($userdata['oldemail']) ? $userdata['oldemail']:$userdata['email']); ?>" />
				</td>
			</tr>
		</table>
	</div>
<!-- Profile -->
<div class="tab-page" id="tabProfile">
<h2 class="tab"><?php echo $_lang["profile"] ?></h2>
<script type="text/javascript">tpUser.addTabPage( document.getElementById( "tabProfile" ) );</script>
<table class="settings">
<tr>
	<th><?php echo $_lang['user_full_name']; ?>:</th>
	<td><input type="text" name="fullname" class="inputBox" value="<?php echo htmlspecialchars(isset($_POST['fullname']) ? $_POST['fullname'] : $userdata['fullname']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_phone']; ?>:</th>
	<td><input type="text" name="phone" class="inputBox" value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : $userdata['phone']; ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_mobile']; ?>:</th>
	<td><input type="text" name="mobilephone" class="inputBox" value="<?php echo isset($_POST['mobilephone']) ? $_POST['mobilephone'] : $userdata['mobilephone']; ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_fax']; ?>:</th>
	<td><input type="text" name="fax" class="inputBox" value="<?php echo isset($_POST['fax']) ? $_POST['fax'] : $userdata['fax']; ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_street']; ?>:</th>
	<td><input type="text" name="street" class="inputBox" value="<?php echo htmlspecialchars($userdata['street']); ?>" onchange="documentDirty=true;" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_city']; ?>:</th>
	<td><input type="text" name="city" class="inputBox" value="<?php echo htmlspecialchars($userdata['city']); ?>" onchange="documentDirty=true;" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_state']; ?>:</th>
	<td><input type="text" name="state" class="inputBox" value="<?php echo isset($_POST['state']) ? $_POST['state'] : $userdata['state']; ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_zip']; ?>:</th>
	<td><input type="text" name="zip" class="inputBox" value="<?php echo isset($_POST['zip']) ? $_POST['zip'] : $userdata['zip']; ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_country']; ?>:</th>
	<td>
	<select size="1" name="country">
	<?php $chosenCountry = isset($_POST['country']) ? $_POST['country'] : $userdata['country']; ?>
	<option value="" <?php (!isset($chosenCountry) ? ' selected' : '') ?> >&nbsp;</option>
	<?php
	foreach ($_country_lang as $key => $country) {
	echo "<option value=\"$key\"".(isset($chosenCountry) && $chosenCountry == $key ? ' selected' : '') .">$country</option>";
	}
	?>
	</select>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['user_dob']; ?>:</th>
	<td>
	<input type="text" id="dob" name="dob" class="DatePicker" value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ($userdata['dob'] ? $modx->toDateFormat($userdata['dob'],'dateOnly'):""); ?>" onblur='documentDirty=true;'>
	<a onclick="document.userform.dob.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo $_lang['remove_date']; ?>"></a>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['user_gender']; ?>:</th>
	<td><select name="gender">
	<option value=""></option>
	<option value="1" <?php echo ($_POST['gender']=='1'||$userdata['gender']=='1')? "selected='selected'":""; ?>><?php echo $_lang['user_male']; ?></option>
	<option value="2" <?php echo ($_POST['gender']=='2'||$userdata['gender']=='2')? "selected='selected'":""; ?>><?php echo $_lang['user_female']; ?></option>
	<option value="3" <?php echo ($_POST['gender']=='3'||$userdata['gender']=='3')? "selected='selected'":""; ?>><?php echo $_lang['user_other']; ?></option>
	</select>
	</td>
</tr>
<tr>
	<th valign="top"><?php echo $_lang['comment']; ?>:</th>
	<td>
	<textarea type="text" name="comment" class="inputBox" rows="5"><?php echo htmlspecialchars(isset($_POST['comment']) ? $_POST['comment'] : $userdata['comment']); ?></textarea>
	</td>
</tr>
<tr>
	<td nowrap class="warning"><b><?php echo $_lang["user_photo"] ?></b></td>
	<td><input type="text" maxlength="255" style="width: 150px;" name="photo" value="<?php echo htmlspecialchars(isset($_POST['photo']) ? $_POST['photo'] : $userdata['photo']); ?>" /> <input type="button" value="<?php echo $_lang['insert']; ?>" onclick="BrowseServer();" />
	<div><?php echo $_lang["user_photo_message"] ?></div>
	<div>
<?php
	if(isset($_POST['photo']))         $photo = $_POST['photo'];
	elseif(!empty($userdata['photo'])) $photo = $userdata['photo'];
	else                               $photo = $modx->config['base_url'] . 'manager/' . $_style['tx'];
	
	if(substr($photo,0,1)!=='/' && !preg_match('@^https?://@',$photo))
	{
		$photo = $modx->config['base_url'] . $photo;
	}
?>
	<img name="iphoto" src="<?php echo $photo; ?>" />
	</div>
	</td>
</tr>
</table>
</div>
	<!-- Settings -->
    <div class="tab-page" id="tabSettings">
    	<h2 class="tab"><?php echo $_lang["settings_users"] ?></h2>
    	<script type="text/javascript">tpUser.addTabPage( document.getElementById( "tabSettings" ) );</script>
        <table class="settings">
          <tr>
            <td nowrap class="warning"><b><?php echo $_lang["login_homepage"] ?></b></td>
            <td >
            <input type='text' maxlength='50' style="width: 100px;" name="login_home" value="<?php echo isset($_POST['login_home']) ? $_POST['login_home'] : $usersettings['login_home']; ?>">
            <div><?php echo $_lang["login_homepage_message"] ?></div>
            </td>
          </tr>
<?php if($_GET['a']=='88'): ?>
<tr>
	<th><?php echo $_lang['user_logincount']; ?>:</th>
	<td><?php echo $userdata['logincount'] ?></td>
</tr>
<tr>
	<th><?php echo $_lang['user_prevlogin']; ?>:</th>
	<?php
	if(!empty($userdata['lastlogin'])) $lastlogin = $modx->toDateFormat($userdata['lastlogin']+$server_offset_time);
	else                               $lastlogin = '-';
	?>
	<td><?php echo $lastlogin; ?></td>
</tr>
<tr>
	<th><?php echo $_lang['user_failedlogincount']; ?>:</th>
	<td>
	<input type="hidden" name="failedlogincount" value="<?php echo $userdata['failedlogincount']; ?>">
	<span id='failed'><?php echo $userdata['failedlogincount'] ?></span>&nbsp;&nbsp;&nbsp;[<a href="javascript:resetFailed()"><?php echo $_lang['reset_failedlogins']; ?></a>]</td>
</tr>
<tr>
	<th><?php echo $_lang['user_block']; ?>:</th>
	<td><input name="blockedcheck" type="checkbox" onclick="changeblockstate(document.userform.blockedmode, document.userform.blockedcheck);"<?php echo ($userdata['blocked']==1||($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0)||($userdata['blockedafter']<time() && $userdata['blockedafter']!=0)) ? " checked='checked'": "" ; ?> /><input type="hidden" name="blocked" value="<?php echo ($userdata['blocked']==1||($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0))?1:0; ?>"></td>
</tr>
<tr>
	<th><?php echo $_lang['user_blockeduntil']; ?>:</th>
	<td>
	<input type="text" id="blockeduntil" name="blockeduntil" class="DatePicker" value="<?php echo isset($_POST['blockeduntil']) ? $_POST['blockeduntil'] : ($userdata['blockeduntil'] ? $modx->toDateFormat($userdata['blockeduntil']):""); ?>" onblur='documentDirty=true;' readonly="readonly">
	<a onclick="document.userform.blockeduntil.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo $_lang['remove_date']; ?>" /></a>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['user_blockedafter']; ?>:</th>
	<td>
	<input type="text" id="blockedafter" name="blockedafter" class="DatePicker" value="<?php echo isset($_POST['blockedafter']) ? $_POST['blockedafter'] : ($userdata['blockedafter'] ? $modx->toDateFormat($userdata['blockedafter']):""); ?>" onblur='documentDirty=true;' readonly="readonly">
	<a onclick="document.userform.blockedafter.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo $_lang['remove_date']; ?>" /></a>
	</td>
</tr>
<?php endif; ?>
          <tr>
            <td nowrap class="warning"valign="top"><b><?php echo $_lang["login_allowed_ip"] ?></b></td>
            <td>
            <input type="text" maxlength='255' style="width: 300px;" name="allowed_ip" value="<?php echo isset($_POST['allowed_ip']) ? $_POST['allowed_ip'] : $usersettings['allowed_ip']; ?>" />
            <div><?php echo $_lang["login_allowed_ip_message"] ?></div>
            </td>
          </tr>
          <tr>
            <td nowrap class="warning"valign="top"><b><?php echo $_lang["login_allowed_days"] ?></b></td>
            <td>
            	<label><input type="checkbox" name="allowed_days[]" value="1" <?php echo strpos($usersettings['allowed_days'],'1')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['sunday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="2" <?php echo strpos($usersettings['allowed_days'],'2')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['monday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="3" <?php echo strpos($usersettings['allowed_days'],'3')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['tuesday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="4" <?php echo strpos($usersettings['allowed_days'],'4')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['wednesday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="5" <?php echo strpos($usersettings['allowed_days'],'5')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['thursday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="6" <?php echo strpos($usersettings['allowed_days'],'6')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['friday']; ?></label>
            	<label><input type="checkbox" name="allowed_days[]" value="7" <?php echo strpos($usersettings['allowed_days'],'7')!==false ? "checked='checked'":""; ?> /> <?php echo $_lang['saturday']; ?></label>
            	<div><?php echo $_lang["login_allowed_days_message"] ?></div>
            </td>
          </tr>
		</table>
	</div>
<?php
if($modx->config['use_udperms']==1)
{
	$groupsarray = array();
	
	if($_GET['a']=='88')
	{ // only do this bit if the user is being edited
		$uid = intval($_GET['id']);
		$rs = $modx->db->select('*',$tbl_web_groups,"webuser='{$uid}'");
		$limit = $modx->db->getRecordCount($rs);
		for ($i = 0; $i < $limit; $i++)
		{
			$currentgroup=$modx->db->getRow($rs);
			$groupsarray[$i] = $currentgroup['webgroup'];
		}
	}
	// retain selected user groups between post
	if(is_array($_POST['user_groups']))
	{
		foreach($_POST['user_groups'] as $n => $v) $groupsarray[] = $v;
	}
?>
	<!-- Access -->
    <div class="tab-page" id="tabAccess">
    	<h2 class="tab"><?php echo $_lang["web_access_permissions"] ?></h2>
    	<script type="text/javascript">tpUser.addTabPage( document.getElementById( "tabAccess" ) );</script>
		<div class="sectionHeader"><?php echo $_lang['web_access_permissions']; ?></div>
		<div class="sectionBody">
<?php
	echo "<p>" . $_lang['access_permissions_user_message'] . "</p>";
	$rs = $modx->db->select('name,id',$tbl_webgroup_names,'','name');
	$tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
	while($row=$modx->db->getRow($rs))
	{
		$echo = $tpl;
		$echo = str_replace('[+id+]',$row['id'],$echo);
		$echo = str_replace('[+checked+]', (in_array($row['id'], $groupsarray) ? 'checked="checked"' : ''), $echo);
		$echo = str_replace('[+name+]', $row['name'], $echo);
		echo $echo;
	}
?>
	</div>
<?php
}
?>
</div>

</div>

</div>
<input type="submit" name="save" style="display:none">
<?php
	// invoke OnWUsrFormRender event
	$evtOut = $modx->invokeEvent("OnWUsrFormRender",array("id" => $user));
	if(is_array($evtOut)) echo implode("",$evtOut);
?>
</form>
<?php
// converts date format dd-mm-yyyy to php date
function ConvertDate($date) {
	global $modx;
	if ($date == "") {return "0";}
	else             {return $modx->toTimeStamp($date);}
}
