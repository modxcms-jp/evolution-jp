<?php
$install_language = $_SESSION['install_language'];

$installmode = getOption('installmode');
setOption('installmode', $installmode);

$upgradeable = 0;

if (is_file("{$base_path}manager/includes/config.inc.php"))
{
	global $dbase,$database_server,$database_user,$database_password,$table_prefix;
	include_once("{$base_path}manager/includes/config.inc.php");
}

$dbase             = getOption('dbase');
$dbase = trim($dbase, '`');
$database_server   = getOption('database_server');
if($database_server===false) $database_server = setOption('database_server','localhost');
$database_user     = getOption('database_user');
$database_password = getOption('database_password');
$table_prefix      = getOption('table_prefix', 'modx_');
if($table_prefix===false)    $table_prefix = setOption('table_prefix', 'modx_');

$cmsadminemail      = getOption('cmsadminemail');
$cmspassword        = getOption('cmspassword');
$cmspasswordconfirm = getOption('cmspasswordconfirm');

if($installmode == 1)
{
	if(!empty($dbase) && !empty($database_server) && !empty($database_user))
	{
		$conn =  mysql_connect($database_server, $database_user, $database_password);
		if($conn) $rs = mysql_select_db($dbase, $conn);
		
		if(!$rs) $upgradeable = getOption('installmode');
		if($upgradeable===false) $upgradeable = setOption('upgradeable',2);
	}
}

setOption('database_collation', 'utf8_general_ci');

// determine the database connection method if not specified in the configuration
if ($upgradeable && (!isset($database_connection_method) || empty($database_connection_method))) {
    $database_connection_method = 'SET CHARACTER SET';
}

?>

<form id="install" action="index.php?action=options" method="POST">
  <h2><?php echo $_lang['connection_screen_database_info']?></h2>
  <h3><?php echo $_lang['connection_screen_server_connection_information']?></h3>
  <p><?php echo $_lang['connection_screen_server_connection_note']?></p>

  <p class="labelHolder"><label for="database_server"><?php echo $_lang['connection_screen_database_host']?></label>
    <input id="database_server" value="<?php echo $database_server; ?>" name="database_server" />
  </p>
  <p class="labelHolder"><label for="database_user"><?php echo $_lang['connection_screen_database_login']?></label>
    <input id="database_user" name="database_user" value="<?php echo $database_user; ?>" />
  </p>
  <p class="labelHolder"><label for="database_password"><?php echo $_lang['connection_screen_database_pass']?></label>
    <input id="database_password" type="password" name="database_password" value="<?php echo $database_password; ?>" />
  </p>

<!-- connection test action/status message -->
  <div class="clickHere">
	&rarr; <a id="servertest" href="#footer"><?php echo $_lang['connection_screen_server_test_connection']?></a>
  </div>
  <div class="status" id="serverstatus" style="display:none;"></div>
<!-- end connection test action/status message -->


<div id="setCollation"><div id="collationMask">
  <h3><?php echo $_lang['connection_screen_database_connection_information']?></h3>
  <p><?php echo $_lang['connection_screen_database_connection_note']?></p>
  <p class="labelHolder"><label for="dbase"><?php echo $_lang['connection_screen_database_name']?></label>
    <input id="dbase" value="<?php echo $dbase; ?>" name="dbase" />
  </p>
  <p class="labelHolder"><label for="table_prefix"><?php echo $_lang['connection_screen_table_prefix']?></label>
    <input id="table_prefix" value="<?php echo $table_prefix; ?>" name="table_prefix" />
  </p>
<?php
  if (($installmode == 0) || ($installmode == 2)) {
?>
  <p class="labelHolder">
    <div id="connection_method" name="connection_method">
        <input type="hidden" value="SET CHARACTER SET" id="database_connection_method" name="database_connection_method" />
    </div>
  </p>
<?php
  }
?>
  <div class="clickHere">
	&rarr; <a id="databasetest" href="#footer"><?php echo $_lang['connection_screen_database_test_connection']?></a>
  </div>
  <div class="status" id="databasestatus" style="display:none;">&nbsp;</div>
</div></div>

<script type="text/javascript">
$('#servertest').click(function(){
	var target = $('html, body');
	target.animate({ scrollTop: $('#footer').offset().top }, 'slow');
});
$('#databasetest').click(function(){
	var target = $('html, body');
	target.animate({ scrollTop: $('#footer').offset().top }, 'slow');
});
</script>

<?php
  if ($installmode == 0) {
?>

  <div id="AUH" style="margin-top:1.5em;display:none;"><div id="AUHMask">
  	<h2><?php echo $_lang['connection_screen_defaults']?></h2>
    <h3><?php echo $_lang['connection_screen_default_admin_user']?></h3>
    <p><?php echo $_lang['connection_screen_default_admin_note']?></p>
    <p class="labelHolder"><label for="cmsadmin"><?php echo $_lang['connection_screen_default_admin_login']?></label>
      <input id="cmsadmin" value="<?php echo isset($_POST['cmsadmin']) ? $_POST['cmsadmin']:"admin" ?>" name="cmsadmin" />
    </p>
    <p class="labelHolder"><label for="cmsadminemail"><?php echo $_lang['connection_screen_default_admin_email']?></label>
      <input id="cmsadminemail" value="<?php echo $cmsadminemail; ?>" name="cmsadminemail" style="width:300px;" />
    </p>
    <p class="labelHolder"><label for="cmspassword"><?php echo $_lang['connection_screen_default_admin_password']?></label>
      <input id="cmspassword" type="password" name="cmspassword" value="<?php echo $cmspassword; ?>" />
    </p>
    <p class="labelHolder"><label for="cmspasswordconfirm"><?php echo $_lang['connection_screen_default_admin_password_confirm']?></label>
      <input id="cmspasswordconfirm" type="password" name="cmspasswordconfirm" value="<?php echo $cmspasswordconfirm; ?>" />
    </p>
</div></div>

<?php
}
?>

    <p class="buttonlinks">
        <a href="javascript:void(0);" class="prev" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a href="javascript:void(0);" class="next" title="<?php echo $_lang['btnnext_value']?>" style="display:none;"><span><?php echo $_lang['btnnext_value']?></span></a>
    </p>
</form>

<script type="text/javascript">
	if($('#cmspasswordconfirm').val() != '') $('a.next').css('display','block');
	$('#cmspasswordconfirm').focus(function(){
		$('a.next').css('display','block');
	});
	
	$('a.prev').click(function(){
		$('#install').attr({action:'index.php?action=mode'});
		$('#install').submit();
	});
	$('a.next').click(function(){
		if($('#cmspassword').val() !== $('#cmspasswordconfirm').val())
		{
			alert("<?php echo $_lang['alert_enter_adminpassword']?>");
		}
		else
		{
			$('#install').submit();
		}
	});
	var language ='<?php echo $install_language?>';
	var installMode ='<?php echo $installmode ?>';
</script>
<script type="text/javascript" src="connection.js"></script>
