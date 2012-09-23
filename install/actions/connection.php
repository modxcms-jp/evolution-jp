<?php
$installMode = intval($_POST['installmode']);

// Determine upgradeability
$upgradeable= 0;

if (is_file("{$base_path}manager/includes/config.inc.php"))
{
	include_once("{$base_path}manager/includes/config.inc.php");
}
if($installMode > 0)
{
	if(isset($dbase) && !empty($dbase))
	{
		if (!@ $conn = mysql_connect($database_server, $database_user, $database_password))
		{
			$upgradeable = isset ($_POST['installmode']) && $_POST['installmode'] == '0' ? 0 : 2;
		}
		elseif (!@ mysql_select_db(trim($dbase, '`'), $conn))
		{
			$upgradeable = isset ($_POST['installmode']) && $_POST['installmode'] == '0' ? 0 : 2;
		}
		else
		{
			$upgradeable = 1;
		}
		$database_name= trim($dbase, '`');
	}
	else
	{
		$upgradable= 2;
	}
}
else 
{
	if(isset($_POST['databasehost']))             $database_server = $_POST['databasehost'];
	elseif(!isset($database_server))              $database_server = 'localhost';
	
	if(isset($_SESSION['databaseloginname']))     $database_user = $_SESSION['databaseloginname'];
	elseif(!isset($database_user))                $database_user = '';
	
	if(isset($_SESSION['databaseloginpassword'])) $database_password = $_SESSION['databaseloginpassword'];
	elseif(!isset($database_password))            $database_password = '';
	
	if(isset($_POST['database_name']))            $database_name = $_POST['database_name'];
	elseif(isset($dbase))                         $database_name = trim($dbase, '`');
	else                                          $database_name = '';
	
	if(isset($_POST['tableprefix']))              $table_prefix = $_POST['tableprefix'];
	else                                          $table_prefix = 'modx_';
}

// check the database collation if not specified in the configuration
if ($upgradeable && (!isset ($database_connection_charset) || empty($database_connection_charset))) {
    if (!$rs = @ mysql_query("show session variables like 'collation_database'")) {
        $rs = @ mysql_query("show session variables like 'collation_server'");
    }
    if ($rs && $collation = mysql_fetch_row($rs)) {
        $database_collation = $collation[1];
    }
    if (empty ($database_collation)) {
        $database_collation = 'utf8_general_ci';
    }
    $database_charset = substr($database_collation, 0, strpos($database_collation, '_'));
    $database_connection_charset = $database_charset;
} else {
    $database_collation = 'utf8_general_ci';
}

// determine the database connection method if not specified in the configuration
if ($upgradeable && (!isset($database_connection_method) || empty($database_connection_method))) {
    $database_connection_method = 'SET CHARACTER SET';
}

?>
<form name="install" id="install_form" action="index.php?action=options" onsubmit="return validate();" method="post">
  <div>
    <input type="hidden" value="<?php echo $install_language?>" name="language" />
    <input type="hidden" value="1" name="chkagree" <?php echo isset($_POST['chkagree']) ? 'checked="checked" ':""; ?>/>
    <input type="hidden" value="<?php echo $installMode ?>" name="installmode" />
    <input type="hidden" value="<?php echo isset($database_connection_method) ? $database_connection_method : ''; ?>" name="database_connection_method" />
  </div>

  <h2><?php echo $_lang['connection_screen_database_info']?></h2>
  <h3><?php echo $_lang['connection_screen_server_connection_information']?></h3>
  <p><?php echo $_lang['connection_screen_server_connection_note']?></p>

  <p class="labelHolder"><label for="databasehost"><?php echo $_lang['connection_screen_database_host']?></label>
    <input id="databasehost" value="<?php echo $database_server; ?>" name="databasehost" />
  </p>
  <p class="labelHolder"><label for="databaseloginname"><?php echo $_lang['connection_screen_database_login']?></label>
    <input id="databaseloginname" name="databaseloginname" value="<?php echo $database_user; ?>" />
  </p>
  <p class="labelHolder"><label for="databaseloginpassword"><?php echo $_lang['connection_screen_database_pass']?></label>
    <input id="databaseloginpassword" type="password" name="databaseloginpassword" value="<?php echo $database_password; ?>" />
  </p>

<!-- connection test action/status message -->
  <div class="clickHere">
	&rarr; <a id="servertest" href="#"><?php echo $_lang['connection_screen_server_test_connection']?></a>
  </div>
  <div class="status" id="serverstatus" style="display:none;"></div>
<!-- end connection test action/status message -->


<div id="setCollation"><div id="collationMask">
  <h3><?php echo $_lang['connection_screen_database_connection_information']?></h3>
  <p><?php echo $_lang['connection_screen_database_connection_note']?></p>
  <p class="labelHolder"><label for="database_name"><?php echo $_lang['connection_screen_database_name']?></label>
    <input id="database_name" value="<?php echo $database_name; ?>" name="database_name" />
  </p>
  <p class="labelHolder"><label for="tableprefix"><?php echo $_lang['connection_screen_table_prefix']?></label>
    <input id="tableprefix" value="<?php echo $table_prefix; ?>" name="tableprefix" />
  </p>
<?php
  if (($installMode == 0) || ($installMode == 2)) {
?>
  <p class="labelHolder">
    <div id="connection_method" name="connection_method">
        <input type="hidden" value="SET CHARACTER SET" id="database_connection_method" name="database_connection_method" />
    </div>
  </p>
<?php
  }
?>
  <p class="labelHolder">
    <div id="collation" name="collation">
		<select id="database_collation" name="database_collation">
        	<option value="<?php echo isset($_POST['database_collation']) ? $_POST['database_collation']: $database_collation ?>" selected="selected">
          	<?php echo isset($_POST['database_collation']) ? $_POST['database_collation']: $database_collation ?>
        	</option>
    	</select>
	</div>
  </p>

  <div class="clickHere">
	&rarr; <a id="databasetest" href="#"><?php echo $_lang['connection_screen_database_test_connection']?></a>
  </div>
  <div class="status" id="databasestatus" style="display:none;">&nbsp;</div>
</div></div>


<?php
  if ($installMode == 0) {
?>

  <div id="AUH" style="margin-top:1.5em;display:none;"><div id="AUHMask">
  	<h2><?php echo $_lang['connection_screen_defaults']?></h2>
    <h3><?php echo $_lang['connection_screen_default_admin_user']?></h3>
    <p><?php echo $_lang['connection_screen_default_admin_note']?></p>
    <p class="labelHolder"><label for="cmsadmin"><?php echo $_lang['connection_screen_default_admin_login']?></label>
      <input id="cmsadmin" value="<?php echo isset($_POST['cmsadmin']) ? $_POST['cmsadmin']:"admin" ?>" name="cmsadmin" />
    </p>
    <p class="labelHolder"><label for="cmsadminemail"><?php echo $_lang['connection_screen_default_admin_email']?></label>
      <input id="cmsadminemail" value="<?php echo isset($_POST['cmsadminemail']) ? $_POST['cmsadminemail']:"" ?>" name="cmsadminemail" style="width:300px;" />
    </p>
    <p class="labelHolder"><label for="cmspassword"><?php echo $_lang['connection_screen_default_admin_password']?></label>
      <input id="cmspassword" type="password" name="cmspassword" value="<?php echo isset($_POST['cmspassword']) ? $_POST['cmspassword']:"" ?>" />
    </p>
    <p class="labelHolder"><label for="cmspasswordconfirm"><?php echo $_lang['connection_screen_default_admin_password_confirm']?></label>
      <input id="cmspasswordconfirm" type="password" name="cmspasswordconfirm" value="<?php echo isset($_POST['cmspasswordconfirm']) ? $_POST['cmspasswordconfirm']:"" ?>" />
    </p>

    <input type="hidden" name="managerlanguage" id="managerlanguage_select" value="<?php echo $default_config['manager_language'];?>" />
</div></div>

<?php
}
?>




    <p class="buttonlinks">
        <a href="javascript:document.getElementById('install_form').action='index.php?action=mode';document.getElementById('install_form').submit();" class="prev" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a style="display:inline;" href="javascript:if(validate()){document.getElementById('install_form').action='index.php?action=options';document.getElementById('install_form').submit();}" title="<?php echo $_lang['btnnext_value']?>"><span><?php echo $_lang['btnnext_value']?></span></a>
    </p>
</form>


<script type="text/javascript" src="../manager/media/script/jquery/jquery.min.js"></script>
<script type="text/javascript">
language ='<?php echo $install_language?>';
installMode ='<?php echo $installMode ?>';
</script>
<script type="text/javascript" src="connection.js"></script>

<script type="text/javascript">
/* <![CDATA[ */
  // validate
  function validate() {
    var f = document.install;
    if(f.databasehost.value=="") {
      alert("<?php echo $_lang['alert_enter_host']?>");
      f.databasehost.focus();
      return false;
    }
    if(f.databaseloginname.value=="") {
      alert("<?php echo $_lang['alert_enter_login']?>");
      f.databaseloginname.focus();
      return false;
    }
    ss = document.getElementById('serverstatus');
    ssv = ss.innerHTML;
    if(ssv.length==0) {
      alert("<?php echo $_lang['alert_server_test_connection']?>");
      return false;
    }
    if (ssv.indexOf("failed") >=0) {
      alert("<?php echo $_lang['alert_server_test_connection_failed']?>");
      return false;
    }   
    if(f.database_name.value=="") {
      alert("<?php echo $_lang['alert_enter_database_name']?>");
      f.database_name.focus();
      return false;
    }
    var alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if(alpha.indexOf(f.tableprefix.value.charAt(0),0) == -1) {
      alert("<?php echo $_lang['alert_table_prefixes']?>");
      f.tableprefix.focus();
      return false;
    }
    dbs = document.getElementById('databasestatus');
    dbsv = dbs.innerHTML;
    if(dbsv.length==0 || dbsv == '&nbsp;') {
      alert("<?php echo $_lang['alert_database_test_connection']?>");
      return false;
    }
    if (dbsv.indexOf("failed") >=0) {
      alert("<?php echo $_lang['alert_database_test_connection_failed']?>");
      return false;
    }   
    if(f.cmsadmin && f.cmsadmin.value=="") {
      alert("<?php echo $_lang['alert_enter_adminlogin']?>");
      f.cmsadmin.focus();
      return false;
    }
    if(f.cmspassword && f.cmspassword.value=="") {
      alert("<?php echo $_lang['alert_enter_adminpassword']?>");
      f.cmspassword.focus();
      return false;
    }
    if(f.cmspassword && f.cmspassword.value!=f.cmspasswordconfirm.value) {
      alert("<?php echo $_lang['alert_enter_adminconfirm']?>");
      f.cmspassword.focus();
      return false;
    }
    return true;
  }
  /* ]]> */
</script>
