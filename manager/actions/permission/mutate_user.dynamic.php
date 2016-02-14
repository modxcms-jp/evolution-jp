<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

switch((int) $_REQUEST['a']) {
  case 12:
    if (!$modx->hasPermission('edit_user')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  case 11:
    if (!$modx->hasPermission('new_user')) {
      $e->setError(3);
      $e->dumpError();
    }
    break;
  default:
    $e->setError(3);
    $e->dumpError();
}

$userid = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

// check to see the snippet editor isn't locked
$rs = $modx->db->select('internalKey, username','[+prefix+]active_users',"action='12' AND id='{$userid}'");
if ($modx->db->getRecordCount($rs) > 1)
{
	while($lock = $modx->db->getRow($rs))
	{
		if ($lock['internalKey'] != $modx->getLoginUserID())
		{
			$msg = sprintf($_lang["lock_msg"], $lock['username'], "user");
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}
// end check for lock

if ($_REQUEST['a'] == '12')
{
	// get user attribute
	$rs = $modx->db->select('*','[+prefix+]user_attributes',"internalKey='{$userid}'");
	$limit = $modx->db->getRecordCount($rs);
	if($limit > 1)     {echo 'More than one user returned!<p>';exit;}
	elseif($limit < 1) {echo 'No user returned!<p>';exit;}
	$userdata = $modx->db->getRow($rs);
	if(!isset($userdata['failedlogins']) ) $userdata['failedlogins'] = 0;
	
	// get user settings
	$rs = $modx->db->select('*','[+prefix+]user_settings',"user='{$userid}'");
	$usersettings = array ();
	while ($row = $modx->db->getRow($rs))
	{
		$usersettings[$row['setting_name']] = $row['setting_value'];
	}
	
	// manually extract so that user display settings are not overwritten
	foreach ($usersettings as $k => $v)
	{
		switch($k)
		{
			case 'manager_language':
			case 'manager_theme':
				break;
			default:
				${$k} = $v;
		}
	}
	
	// get user name
	$rs = $modx->db->select('*','[+prefix+]manager_users',"id='{$userid}'");
	$limit = $modx->db->getRecordCount($rs);
	if($limit > 1)     {echo "More than one user returned while getting username!<p>"; exit;}
	elseif($limit < 1) {echo "No user returned while getting username!<p>"; exit;}
	$usernamedata = $modx->db->getRow($rs);
	$_SESSION['itemname'] = $usernamedata['username'];
}
else
{
	$userdata = array ();
	$usersettings = array ();
	$usernamedata = array ();
	$_SESSION['itemname'] = "New user";
}

// restore saved form
$formRestored = false;
if ($modx->manager->hasFormValues()) {
	$form_v = $modx->manager->loadFormValues();
	// restore post values
	$userdata = array_merge($userdata, $form_v);
	$userdata['dob'] = ConvertDate($userdata['dob']);
	$usernamedata['username'] = $userdata['newusername'];
	$usernamedata['oldusername'] = $form_v['oldusername'];
	$usersettings = array_merge($usersettings, $userdata);
	$usersettings['allowed_days'] = is_array($form_v['allowed_days']) ? implode(",", $form_v['allowed_days']) : "";
	extract($usersettings, EXTR_OVERWRITE);
}

// include the country list language file
$_country_lang = array();
include_once(MODX_CORE_PATH . 'lang/country/english_country.inc.php');
if($manager_language!="english" && is_file(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php")){
    include_once(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php");
}

$displayStyle = ($_SESSION['browser'] ==='modern') ? 'table-row' : 'block';
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
		jQuery('#passwordBlock').show(100);
	} else {
		jQuery('#passwordBlock').hide(100);
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
<?php if($_GET['id']==$modx->getLoginUserID()) { ?>
	alert("<?php echo $_lang['alert_delete_self']; ?>");
<?php } else { ?>
	if(confirm("<?php echo $_lang['confirm_delete_user']; ?>")==true) {
		document.location.href="index.php?id=" + document.userform.userid.value + "&a=33";
	}
<?php } ?>
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

<form action="index.php?a=32" method="post" name="userform" enctype="multipart/form-data">
<?php

// invoke OnUserFormPrerender event
$tmp = array ("id" => $userid);
$evtOut = $modx->invokeEvent("OnUserFormPrerender", $tmp);
if (is_array($evtOut))
	echo implode("", $evtOut);
?>
<input type="hidden" name="mode" value="<?php echo $_GET['a'] ?>">
<input type="hidden" name="userid" value="<?php echo $_GET['id'] ?>">
<input type="hidden" name="blockedmode" value="<?php echo ($userdata['blocked']==1 || ($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0)|| ($userdata['blockedafter']<time() && $userdata['blockedafter']!=0) || $userdata['failedlogins']>3) ? "1":"0" ?>" />

<h1><?php echo $_lang['user_title']; ?></h1>
    <div id="actions">
    	  <ul class="actionButtons">
<?php if($modx->hasPermission('save_user')):?>
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false; document.userform.save.click();">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']?>
    			</a>
    			  <span class="and"> + </span>
    			<select id="stay" name="stay">
    			  <option id="stay1" value="1" <?php echo selected($_REQUEST['stay']=='1');?> ><?php echo $_lang['stay_new']?></option>
    			  <option id="stay2" value="2" <?php echo selected($_REQUEST['stay']=='2');?> ><?php echo $_lang['stay']?></option>
    			  <option id="stay3" value=""  <?php echo selected($_REQUEST['stay']=='');?>  ><?php echo $_lang['close']?></option>
    			</select>
    		  </li>
<?php endif; ?>
<?php
    if ($_REQUEST['a'] == '12' && $modx->getLoginUserID()!= $userid)
    {
    	$params = array('onclick'=>'deleteuser();','icon'=>$_style['icons_delete_document'],'label'=>$_lang['delete']);
    	if($modx->hasPermission('delete_user'))
    		echo $modx->manager->ab($params);
    }
    $params = array('onclick'=>"document.location.href='index.php?a=75';",'icon'=>$_style['icons_cancel'],'label'=>$_lang['cancel']);
    echo $modx->manager->ab($params);
?>
    	  </ul>
    </div>
<!-- Tab Start -->
<div class="sectionBody">
<style type="text/css">
	table.settings {border-collapse:collapse;width:100%;}
	table.settings tr {border-bottom:1px dotted #ccc;}
	table.settings th {font-size:inherit;vertical-align:top;text-align:left;}
	table.settings th,table.settings td {padding:5px;}
</style>
<div class="tab-pane" id="userPane">
	<script type="text/javascript">
		tpUser = new WebFXTabPane(document.getElementById( "userPane" ), <?php echo (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?> );
	</script>
    <div class="tab-page" id="tabGeneral">
		<?php if($_GET['id']==$modx->getLoginUserID()) { ?><p><?php echo $_lang['user_edit_self_msg']; ?></p><?php } ?>
    	<h2 class="tab"><?php echo $_lang["login_settings"] ?></h2>
		<table class="settings">
<?php
	if($userdata['blocked']==1 || ($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0) || $userdata['failedlogins']>3):
?>
		  <tr>
			<td colspan="2">
				<span id="blocked" class="warning"><b><?php echo $_lang['user_is_blocked']; ?></b></span><br />
			</td>
		  </tr>
<?php endif; ?>
		  <?php if(!empty($userdata['id'])) { ?>
		  <tr id="showname" style="display: <?php echo ($_GET['a']=='12' && (!isset($usernamedata['oldusername'])||$usernamedata['oldusername']==$usernamedata['username'])) ? $displayStyle : 'none';?> ">
			<td colspan="2">
				<img src="<?php echo $_style['icons_user'] ?>" alt="." />&nbsp;<b><?php echo !empty($usernamedata['oldusername']) ? $usernamedata['oldusername']:$usernamedata['username']; ?></b> - <span class="comment"><a href="#" onclick="jQuery('#showname').hide(100);jQuery('#editname').show(100);return false;"><?php echo $_lang["change_name"]; ?></a></span>
				<input type="hidden" name="oldusername" value="<?php echo htmlspecialchars(!empty($usernamedata['oldusername']) ? $usernamedata['oldusername']:$usernamedata['username']); ?>" />
			</td>
		  </tr>
		  <?php } ?>
		  <tr id="editname" style="display:<?php echo $_GET['a']=='11'||(isset($usernamedata['oldusername']) && $usernamedata['oldusername']!=$usernamedata['username']) ? $displayStyle : 'none' ; ?>">
			<th><?php echo $_lang['username']; ?>:</th>
			<td><input type="text" name="newusername" class="inputBox" value="<?php echo htmlspecialchars($usernamedata['username']); ?>" maxlength="100" /></td>
		  </tr>
		  <tr>
			<th valign="top"><?php echo $_GET['a']=='11' ? $_lang['password'].":" : $_lang['change_password_new'].":" ; ?></th>
			<td>
	<?php if($_REQUEST['a']=='12'):?>
	<input name="newpasswordcheck" type="checkbox" onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);"><br />
	<?php endif; ?>
	<input type="hidden" name="newpassword" value="<?php echo $_REQUEST['a']=="11" ? 1 : 0 ; ?>" />
				<span style="display:<?php echo $_REQUEST['a']=="11" ? "block": "none" ; ?>" id="passwordBlock">
				<fieldset style="width:300px;padding:0;">
				<label><input type=radio name="passwordgenmethod" value="g" <?php echo $_POST['passwordgenmethod']=="spec" ? "" : 'checked="checked"'; ?> /><?php echo $_lang['password_gen_gen']; ?></label><br />
				<label><input type=radio name="passwordgenmethod" value="spec" <?php echo $_POST['passwordgenmethod']=="spec" ? 'checked="checked"' : ""; ?>><?php echo $_lang['password_gen_specify']; ?></label><br />
				<div style="padding-left:20px">
				<label for="specifiedpassword" style="width:120px"><?php echo $_lang['change_password_new']; ?>:</label>
				<input type="password" name="specifiedpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
				<label for="confirmpassword" style="width:120px"><?php echo $_lang['change_password_confirm']; ?>:</label>
				<input type="password" name="confirmpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
				<span class="warning" style="font-weight:normal"><?php echo $_lang['password_gen_length']; ?></span>
				</div>
				</fieldset>
				<fieldset style="width:300px;padding:0;">
				<label><input type="radio" name="passwordnotifymethod" value="e" <?php echo $_POST['passwordnotifymethod']=="e" ? 'checked="checked"' : ""; ?> /><?php echo $_lang['password_method_email']; ?></label><br />
				<label><input type="radio" name="passwordnotifymethod" value="s" <?php echo $_POST['passwordnotifymethod']=="e" ? "" : 'checked="checked"'; ?> /><?php echo $_lang['password_method_screen']; ?></label>
				</fieldset>
				</span>
			</td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_email']; ?>:</th>
			<td>
			<input type="text" name="email" class="inputBox" value="<?php echo htmlspecialchars($userdata['email']); ?>" />
			<input type="hidden" name="oldemail" value="<?php echo htmlspecialchars(!empty($userdata['oldemail']) ? $userdata['oldemail']:$userdata['email']); ?>" />
			</td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_role']; ?>:</th>
			<td>
<?php
if($userid==$modx->getLoginUserID())
{
	if($modx->hasPermission('save_role'))
		$where = 'save_role=1';
	else
		$where = 'save_role=0';
}
else
{
    $where = '';
}
$rs = $modx->db->select('name, id','[+prefix+]user_roles',$where,'save_role DESC, new_role DESC, id ASC');
?>
		<select name="role" class="inputBox">
		<?php

while ($row = $modx->db->getRow($rs))
{
	if ($_REQUEST['a']=='11')
	{
		$selectedtext = selected($row['id'] == $modx->config['default_role']);
	}
	else
	{
		$selectedtext = selected($row['id'] == $userdata['role']);
	}
?>
			<option value="<?php echo $row['id']; ?>"<?php echo $selectedtext; ?>><?php echo $row['name']; ?></option>
		<?php
}
?>
		</select>
			</td>
		  </tr>
</table>
</div>
<!-- Profile -->
<div class="tab-page" id="tabProfile">
<h2 class="tab"><?php echo $_lang["profile"] ?></h2>
<table class="settings">
<tr>
	<th><?php echo $_lang['user_full_name']; ?>:</th>
	<td><input type="text" name="fullname" class="inputBox" value="<?php echo htmlspecialchars($userdata['fullname']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_phone']; ?>:</th>
	<td><input type="text" name="phone" class="inputBox" value="<?php echo htmlspecialchars($userdata['phone']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_mobile']; ?>:</th>
	<td><input type="text" name="mobilephone" class="inputBox" value="<?php echo htmlspecialchars($userdata['mobilephone']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_fax']; ?>:</th>
	<td><input type="text" name="fax" class="inputBox" value="<?php echo htmlspecialchars($userdata['fax']); ?>" /></td>
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
	<td><input type="text" name="state" class="inputBox" value="<?php echo htmlspecialchars($userdata['state']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_zip']; ?>:</th>
	<td><input type="text" name="zip" class="inputBox" value="<?php echo htmlspecialchars($userdata['zip']); ?>" /></td>
</tr>
<tr>
	<th><?php echo $_lang['user_country']; ?>:</th>
	<td>
	<select size="1" name="country" class="inputBox">
	<?php $chosenCountry = isset($_POST['country']) ? $_POST['country'] : $userdata['country']; ?>
	<option value="" <?php echo selected(empty($chosenCountry)); ?> >&nbsp;</option>
<?php
	foreach ($_country_lang as $key => $country)
	{
		echo '<option value="' . $key . '"'.selected(isset($chosenCountry) && $chosenCountry == $key) .">{$country}</option>\n";
	}
?>
	</select>
	</td>
</tr>
	<tr>
	<th><?php echo $_lang['user_dob']; ?>:</th>
	<td>
	<input type="text" id="dob" name="dob" class="DatePicker" value="<?php echo ($userdata['dob'] ? $modx->toDateFormat($userdata['dob'],'dateOnly'):""); ?>" onblur="documentDirty=true;">
	<a onclick="document.userform.dob.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif"  border="0" alt="<?php echo $_lang['remove_date']; ?>"></a>
	</td>
</tr>
<tr>
	<th><?php echo $_lang['user_gender']; ?>:</th>
	<td><select name="gender" class="inputBox">
	<option value=""></option>
	<option value="1" <?php echo selected($userdata['gender']=='1'); ?>><?php echo $_lang['user_male']; ?></option>
	<option value="2" <?php echo selected($userdata['gender']=='2'); ?>><?php echo $_lang['user_female']; ?></option>
	<option value="3" <?php echo selected($userdata['gender']=='3'); ?>><?php echo $_lang['user_other']; ?></option>
	</select>
	</td>
</tr>
<tr>
	<th valign="top"><?php echo $_lang['comment']; ?>:</th>
	<td>
	<textarea type="text" name="comment" class="inputBox"  rows="5"><?php echo htmlspecialchars($userdata['comment']); ?></textarea>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["user_photo"] ?></th>
	<td><input type="text" maxlength="255" style="width: 150px;" name="photo" value="<?php echo htmlspecialchars($userdata['photo']); ?>" />
	<input type="button" value="<?php echo $_lang['insert']; ?>" onclick="BrowseServer();" />
	<div><?php echo $_lang["user_photo_message"]; ?></div>
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
        <table class="settings">
          <tr>
            <th><?php echo $_lang["allow_mgr_access"] ?></th>
            <td>
            	<label><input type="radio" name="allow_manager_access" value="1" <?php echo !isset($usersettings['allow_manager_access'])||$usersettings['allow_manager_access']==1 ? 'checked="checked"':'' ; ?> /> <?php echo $_lang['yes']; ?></label><br />
            	<label><input type="radio" name="allow_manager_access" value="0" <?php echo isset($usersettings['allow_manager_access']) && $usersettings['allow_manager_access']==0 ? 'checked="checked"':'' ; ?> /> <?php echo $_lang['no']; ?></label>
            	<div><?php echo $_lang["allow_mgr_access_message"] ?></div>
            </td>
          </tr>
<tr>
	<th><?php echo $_lang['user_allowed_parents']; ?>:</th>
	<td>
	<input type="text" name="allowed_parents" class="inputBox" value="<?php echo htmlspecialchars($usersettings['allowed_parents']); ?>" />
	<div><?php echo $_lang["user_allowed_parents_message"] ?></div>
	</td>
</tr>
<?php if($_GET['a']=='12'): ?>
		  <tr>
			<th><?php echo $_lang['user_logincount']; ?>:</th>
			<td><?php echo $userdata['logincount'] ?></td>
		  </tr>
<?php
	if(!empty($userdata['lastlogin']))
	{
	   $lastlogin = $modx->toDateFormat($userdata['lastlogin']+$server_offset_time);
	}
	else $lastlogin = '-';
?>
		  <tr>
			<th><?php echo $_lang['user_prevlogin']; ?>:</th>
			<td><?php echo $lastlogin ?></td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_failedlogincount']; ?>:</th>
			<td>
			<input type="hidden" name="failedlogincount" value="<?php echo $userdata['failedlogincount']; ?>">
			<span id='failed'><?php echo $userdata['failedlogincount'] ?></span>&nbsp;&nbsp;&nbsp;[<a href="javascript:resetFailed()"><?php echo $_lang['reset_failedlogins']; ?></a>]</td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_block']; ?>:</th>
			<td><label><input name="blockedcheck" type="checkbox" onclick="changeblockstate(document.userform.blocked, document.userform.blockedcheck);"<?php echo ($userdata['blocked']==1||($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0)) ? " checked": "" ; ?>><input type="hidden" name="blocked" value="<?php echo ($userdata['blocked']==1||($userdata['blockeduntil']>time() && $userdata['blockeduntil']!=0))?1:0; ?>"></label></td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_blockedafter']; ?>:</th>
			<td>
				<input type="text" id="blockedafter" name="blockedafter" class="DatePicker" value="<?php echo ($userdata['blockedafter'] ? $modx->toDateFormat($userdata['blockedafter']):""); ?>" onblur='documentDirty=true;' readonly="readonly">
				<a onclick="document.userform.blockedafter.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo $_lang['remove_date']; ?>" /></a>
			</td>
		  </tr>
		  <tr>
			<th><?php echo $_lang['user_blockeduntil']; ?>:</th>
			<td>
				<input type="text" id="blockeduntil" name="blockeduntil" class="DatePicker" value="<?php echo ($userdata['blockeduntil'] ? $modx->toDateFormat($userdata['blockeduntil']):""); ?>" onblur='documentDirty=true;' readonly="readonly">
				<a onclick="document.userform.blockeduntil.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo $_lang['remove_date']; ?>" /></a>
			</td>
		  </tr>
<?php endif;?>
          <tr>
            <th><?php echo $_lang["login_allowed_ip"] ?></th>
            <td ><input  type="text" maxlength='255' style="width: 300px;" name="allowed_ip" value="<?php echo $usersettings['allowed_ip']; ?>" />
            <div><?php echo $_lang["login_allowed_ip_message"] ?></div>
            </td>
          </tr>
          <tr>
            <th><?php echo $_lang["login_allowed_days"] ?></th>
            <td>
            <?php echo checkbox('allowed_days[]','1',$_lang['sunday'],   strpos($usersettings['allowed_days'],'1')!==false);?>
            <?php echo checkbox('allowed_days[]','2',$_lang['monday'],   strpos($usersettings['allowed_days'],'2')!==false);?>
            <?php echo checkbox('allowed_days[]','3',$_lang['tuesday'],  strpos($usersettings['allowed_days'],'3')!==false);?>
            <?php echo checkbox('allowed_days[]','4',$_lang['wednesday'],strpos($usersettings['allowed_days'],'4')!==false);?>
            <?php echo checkbox('allowed_days[]','5',$_lang['thursday'], strpos($usersettings['allowed_days'],'5')!==false);?>
            <?php echo checkbox('allowed_days[]','6',$_lang['friday'],   strpos($usersettings['allowed_days'],'6')!==false);?>
            <?php echo checkbox('allowed_days[]','7',$_lang['saturday'], strpos($usersettings['allowed_days'],'7')!==false);?>
            <div><?php echo $_lang["login_allowed_days_message"]; ?></div>
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
	<td> <select name="manager_theme" size="1" class="inputBox" onchange="document.userform.theme_refresher.value = Date.parse(new Date())">
	<option value=""><?php echo $_lang["user_use_config"]; ?></option>
<?php
	$files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
	foreach($files as $file)
	{
		$file = str_replace('\\','/',$file);
		if($file!="." && $file!=".." && substr($file,0,1) != '.')
		{
			$themename = substr(dirname($file),strrpos(dirname($file),'/')+1);
			$selectedtext = $themename==$usersettings['manager_theme'] ? "selected='selected'" : "" ;
			echo "<option value='$themename' $selectedtext>".ucwords(str_replace("_", " ", $themename))."</option>";
		}
	}
?>
	</select><input type="hidden" name="theme_refresher" value="">
	<div><?php echo $_lang["manager_theme_message"];?></div></td>
</tr>
<tr>
	<th><?php echo $_lang["a17_manager_inline_style_title"]?></th>
	<td>
	<textarea name="manager_inline_style" id="manager_inline_style" style="width:95%; height: 9em;"><?php echo $manager_inline_style; ?></textarea><br />
	&nbsp;&nbsp; <label><input type="checkbox" name="default_manager_inline_style" value="1" <?php echo isset($usersettings['manager_inline_style']) ? '' : 'checked' ; ?>  /> <?php echo $_lang["user_use_config"]; ?></label>
	<div><?php echo $_lang["a17_manager_inline_style_message"];?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["mgr_login_start"] ?></th>
	<td ><input type='text' maxlength='50' style="width: 100px;" name="manager_login_startup" value="<?php echo isset($_POST['manager_login_startup']) ? $_POST['manager_login_startup'] : $usersettings['manager_login_startup']; ?>">
	<div><?php echo $_lang["mgr_login_start_message"] ?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["language_title"] ?></th>
	<td><select name="manager_language" size="1" class="inputBox">
	<option value=""><?php echo $_lang["user_use_config"]; ?></option>
<?php
	$activelang = (!empty($usersettings['manager_language'])) ? $usersettings['manager_language'] : '';
	$dir = dir(MODX_CORE_PATH . 'lang');
	while ($file = $dir->read())
	{
		if (strpos($file, '.inc.php') !== false)
		{
			$endpos = strpos($file, ".");
			$languagename = trim(substr($file, 0, $endpos));
			$selectedtext = selected($activelang===$languagename);
?>
	<option value="<?php echo $languagename; ?>" <?php echo $selectedtext; ?>><?php echo ucwords(str_replace("_", " ", $languagename)); ?></option>
<?php
		}
	}
	$dir->close();
?>
	</select>
	<div><?php echo $_lang["language_message"]; ?></div>
	</td>
</tr>
<tr id='editorRow0' style="display: <?php echo $use_editor==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["which_editor_title"]?></th>
	<td>
	<select name="which_editor" class="inputBox">
	<option value=""><?php echo $_lang["user_use_config"]; ?></option>
<?php
	$edt = isset ($usersettings["which_editor"]) ? $usersettings["which_editor"] : '';
	// invoke OnRichTextEditorRegister event
	$evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
	echo "<option value='none'" . selected($edt == 'none') . ">" . $_lang["none"] . "</option>\n";
	if (is_array($evtOut))
	for ($i = 0; $i < count($evtOut); $i++)
	{
		$editor = $evtOut[$i];
		echo "<option value='$editor'" . selected($edt == $editor) . ">$editor</option>\n";
	}
?>
	</select>
	<div><?php echo $_lang["which_editor_message"]?></div>
	</td>
</tr>
<tr id='editorRow14' class="row3" style="display: <?php echo $use_editor==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["editor_css_path_title"]?></th>
	<td><input type='text' maxlength='255' style="width: 250px;" name="editor_css_path" value="<?php echo isset($usersettings["editor_css_path"]) ? $usersettings["editor_css_path"] : "" ; ?>" />
	<div><?php echo $_lang["editor_css_path_message"]?></div>
	</td>
	</tr>
	<tr class='row1'>
	<td colspan="2" style="padding:0;">
<?php
	// invoke OnInterfaceSettingsRender event
	$evtOut = $modx->invokeEvent("OnInterfaceSettingsRender");
	if (is_array($evtOut)) echo implode('', $evtOut);
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
	<input type='text' maxlength='255' style="width: 300px;" name="filemanager_path" value="<?php echo htmlspecialchars(isset($usersettings['filemanager_path']) ? $usersettings['filemanager_path']:""); ?>">
	<div><?php echo $_lang["filemanager_path_message"];?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["uploadable_images_title"]?></th>
	<td>
	<input type='text' maxlength='255' style="width: 250px;" name="upload_images" value="<?php echo isset($usersettings['upload_images']) ? $usersettings['upload_images'] : "" ; ?>">
	&nbsp;&nbsp; <label><input type="checkbox" name="default_upload_images" value="1" <?php echo isset($usersettings['upload_images']) ? '' : 'checked' ; ?>  /> <?php echo $_lang["user_use_config"]; ?></label>
	<div><?php echo $_lang["uploadable_images_message"].$_lang["user_upload_message"]?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["uploadable_media_title"]?></th>
	<td>
	<input type='text' maxlength='255' style="width: 250px;" name="upload_media" value="<?php echo isset($usersettings['upload_media']) ? $usersettings['upload_media'] : "" ; ?>">
	&nbsp;&nbsp; <label><input type="checkbox" name="default_upload_media" value="1" <?php echo isset($usersettings['upload_media']) ? '' : 'checked' ; ?>  /> <?php echo $_lang["user_use_config"]; ?></label>
	<div><?php echo $_lang["uploadable_media_message"].$_lang["user_upload_message"]?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["uploadable_flash_title"]?></th>
	<td>
	<input type='text' maxlength='255' style="width: 250px;" name="upload_flash" value="<?php echo isset($usersettings['upload_flash']) ? $usersettings['upload_flash'] : "" ; ?>">
	&nbsp;&nbsp; <label><input type="checkbox" name="default_upload_flash" value="1" <?php echo isset($usersettings['upload_flash']) ? '' : 'checked' ; ?>  /> <?php echo $_lang["user_use_config"]; ?></label>
	<div><?php echo $_lang["uploadable_flash_message"].$_lang["user_upload_message"]?></div>
	</td>
</tr>
<tr>
	<th><?php echo $_lang["uploadable_files_title"]?></th>
	<td>
	<input type='text' maxlength='255' style="width: 250px;" name="upload_files" value="<?php echo isset($usersettings['upload_files']) ? $usersettings['upload_files'] : "" ; ?>">
	&nbsp;&nbsp; <label><input type="checkbox" name="default_upload_files" value="1" <?php echo isset($usersettings['upload_files']) ? '' : 'checked' ; ?>  /> <?php echo $_lang["user_use_config"]; ?></label>
	<div><?php echo $_lang["uploadable_files_message"].$_lang["user_upload_message"]?></div>
	</td>
</tr>
<tr class='row2'>
	<th><?php echo $_lang["upload_maxsize_title"]?></th>
	<td>
	<input type='text' maxlength='255' style="width: 300px;" name="upload_maxsize" value="<?php echo isset($usersettings['upload_maxsize']) ? $usersettings['upload_maxsize'] : "" ; ?>">
	<div><?php echo sprintf($_lang["upload_maxsize_message"],$modx->manager->getUploadMaxsize())?></div>
	</td>
</tr>
	<tr id='rbRow1' class='row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["rb_base_dir_title"]?></th>
	<td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_dir" value="<?php echo isset($usersettings["rb_base_dir"]) ? $usersettings["rb_base_dir"]:""; ?>" />
	<div><?php echo $_lang["rb_base_dir_message"]?></div>
	</td>
</tr>
<tr id='rbRow4' class='row3' style="display: <?php echo $use_browser==1 ? $displayStyle : 'none' ; ?>">
	<th><?php echo $_lang["rb_base_url_title"]?></th>
	<td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_url" value="<?php echo isset($usersettings["rb_base_url"]) ? $usersettings["rb_base_url"]:""; ?>" />
	<div><?php echo $_lang["rb_base_url_message"]?></div>
	</td>
	</tr>
</table>
</div>
<?php
if ($modx->config['use_udperms'] == 1)
{
?>
	<!-- Access -->
	<div class="tab-page" id="tabAccess">
		<h2 class="tab"><?php echo $_lang["access_permissions"] ?></h2>
		<div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
		<div class="sectionBody">
<?php
	$groupsarray = array ();

	if ($_GET['a'] == '12')
	{ // only do this bit if the user is being edited
		$memberid = $_GET['id'];
		$rs = $modx->db->select('*','[+prefix+]member_groups',"member='{$memberid}'" );
		$limit = $modx->db->getRecordCount($rs);
		for ($i = 0; $i < $limit; $i++)
		{
			$currentgroup = $modx->db->getRow($rs);
			$groupsarray[$i] = $currentgroup['user_group'];
		}
	}

	// retain selected doc groups between post
	if (is_array($_POST['user_groups']))
	{
		foreach ($_POST['user_groups'] as $n => $v)
		{
			$groupsarray[] = $v;
		}
	}
	echo "<p>" . $_lang['access_permissions_user_message'] . "</p>";
	$rs = $modx->db->select('name, id','[+prefix+]membergroup_names','','name');
	if($modx->db->getRecordCount($rs)<1):
		echo '<div class="actionButtons"><a href="index.php?a=40" class="primary">Create user group</a></div>';
	else:
		$tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
		while($row = $modx->db->getRow($rs))
		{
			$src = $tpl;
			$ph = array();
			$ph['id'] = $row['id'];
			$ph['checked'] = in_array($row['id'], $groupsarray) ? 'checked="checked"' : '';
			$ph['name'] = $row['name'];
			$src = $modx->parseText($src,$ph);
			echo $src;
		}
	endif;
?>
		</div>
	</div>
<?php
}
?>
<?php

// invoke OnUserFormRender event
$tmp = array (
	"id" => $userid,
	'usersettings'=>$usersettings
);
$evtOut = $modx->invokeEvent("OnUserFormRender", $tmp);
if (is_array($evtOut))
	echo implode("", $evtOut);
?>
</div>
</div>
<input type="submit" name="save" style="display:none">
</form>
<?php
function selected($cond=false)
{
	if($cond) return ' selected="selected"';
}

// converts date format dd-mm-yyyy to php date
function ConvertDate($date) {
	global $modx;
	if ($date == "") { return "0"; }
	else             { return $modx->toTimeStamp($date); }
}

function checkbox($name,$value,$label,$cond)
{
	global $modx;
	$tpl = '<label><input type="checkbox" name="[+name+]" value="[+value+]" [+checked+] />[+label+]</label>';
	$ph['name'] = $name;
	$ph['value'] = $value;
	$ph['label'] = $label;
	$ph['checked'] = checked($cond);
	return $modx->parseText($tpl,$ph);
}

function checked($cond=false)
{
	if($cond===true) return 'checked="checked"';
}
