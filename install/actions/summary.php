<?php
if(isset($_POST['chkagree'])) $chkagree = $_POST['chkagree'];
elseif(isset($_SESSION['chkagree'])) $chkagree = $_SESSION['chkagree'];

$_SESSION['installdata'] = isset($_POST['installdata']) ? $_POST['installdata'] : '';
$_SESSION['template']    = isset($_POST['template'])    ? $_POST['template'] : '';
$_SESSION['tv']          = isset($_POST['tv'])          ? $_POST['tv'] : '';
$_SESSION['chunk']       = isset($_POST['chunk'])       ? $_POST['chunk'] : '';
$_SESSION['snippet']     = isset($_POST['snippet'])     ? $_POST['snippet'] : '';
$_SESSION['plugin']      = isset($_POST['plugin'])      ? $_POST['plugin'] : '';
$_SESSION['module']      = isset($_POST['module'])      ? $_POST['module'] : '';

echo '<h2>' . $_lang['preinstall_validation'] . '</h2>';
echo '<h3>' . $_lang['summary_setup_check'] . '</h3>';
$errors = 0;

// check PHP version

if (version_compare(phpversion(), '5.0.0') < 0) {
	$_ = echo_failed().$_lang['you_running_php'] . phpversion() . $_lang['modx_requires_php'];
	$errors += 1;
}
else $_ = echo_ok();
echo p($_ . $_lang['checking_php_version'] );

// check php register globals off

$register_globals = (int) ini_get('register_globals');
if ($register_globals == '1') {
    echo p(echo_failed() . $_lang['checking_registerglobals']);
    echo p('<strong>' . $_lang['checking_registerglobals_note'] . '</strong>');
}

// check sessions
if ($_SESSION['test'] != 1) {
	echo p(echo_failed() . $_lang['checking_sessions']);
	$errors += 1;
}

// check directories
// cache exists?
if (!is_dir("{$base_path}assets/cache")) {
	echo p(echo_failed() . $_lang['checking_if_cache_exist']);
	$errors += 1;
}

// cache writable?
if (!is_writable("{$base_path}assets/cache")) {
	$_ = echo_failed();
	$errors += 1;
} else {
	$_ = echo_ok();
	mkd("{$base_path}assets/cache/rss");
}
echo p($_ . $_lang['checking_if_cache_writable']);

if (is_writable("{$base_path}assets/cache")) {
	// cache files writable?
	if (!is_file("{$base_path}assets/cache/siteCache.idx.php")) {
	    // make an attempt to create the file
	    file_put_contents("{$base_path}assets/cache/siteCache.idx.php",'<?php //MODX site cache file ?>');
	}
	if (!is_writable("{$base_path}assets/cache/siteCache.idx.php")) {
	    $_ =  echo_failed();
	    $errors += 1;
	}
	else $_ =  echo_ok();
	echo p($_ . $_lang['checking_if_cache_file_writable']);
	
    file_put_contents("{$base_path}assets/cache/basicConfig.idx.php",'<?php $cacheRefreshTime=0; ?>');
	
	if (!is_writable("{$base_path}assets/cache/basicConfig.idx.php")) {
		$_ = echo_failed();
		$errors += 1;
	}
	else $_ = echo_ok();
	echo p($_ . $_lang['checking_if_cache_file2_writable']);
}

if(!is_dir("{$base_path}assets/images")) {
	if (!is_dir("{$base_path}content")) {
		echo p(echo_failed() . $_lang['checking_if_content_exists']);
		$errors += 1;
	}
	
	// cache writable?
	$dir_images = "{$base_path}content/images";
	$dir_files  = "{$base_path}content/files";
	$dir_flash  = "{$base_path}content/flash";
	$dir_media  = "{$base_path}content/media";
	
	if (!is_writable("{$base_path}content")) {
	    $_ = echo_failed();
	    $errors += 1;
	} else {
	    $_ = echo_ok();
		mkd($dir_images);
		mkd($dir_files);
		mkd($dir_flash);
		mkd($dir_media);
	}
	echo p($_ . $_lang['checking_if_content_writable']);
	
	if (is_writable("{$base_path}content"))
	{
		// File Browser directories exists?
		if (!is_dir($dir_images) || !is_dir($dir_files) || !is_dir($dir_flash) || !is_dir($dir_media)) {
			echo p(echo_failed() . $_lang['checking_if_images_exist']);
			$errors += 1;
		} else {
			// File Browser directories writable?
			if (!is_writable($dir_images) || !is_writable($dir_files) || !is_writable($dir_flash) || !is_writable($dir_media)) {
				$_ = echo_failed();
				$errors += 1;
			} else {
				$_ = echo_ok();
			}
			echo p($_ . $_lang['checking_if_images_writable']);
		}
	}
}

if (!is_dir("{$base_path}temp")) {
	echo p(echo_failed() . $_lang['checking_if_temp_exists']);
	$errors += 1;
}

// cache writable?

if (!is_writable("{$base_path}temp")) {
    $_ = echo_failed();
    $errors += 1;
} else {
    $_ = echo_ok();
	mkd("{$base_path}temp/export");
	mkd("{$base_path}temp/backup");
	if(is_dir("{$base_path}temp/backup")) @file_put_contents("{$base_path}temp/backup/.htaccess","order deny,allow\ndeny from all");
}
echo p($_ . $_lang['checking_if_temp_writable']);

if (is_writable("{$base_path}temp")) {
	// export exists?
	if (!is_dir("{$base_path}temp/export")) {
		echo p(echo_failed() . $_lang['checking_if_export_exists']);
		$errors += 1;
	}
	
	// export writable?
	if (!is_writable("{$base_path}temp/export")) {
		$_ = echo_failed();
		$errors += 1;
	}
	else $_ =  echo_ok();
	echo p($_ . $_lang['checking_if_export_writable']);
	
	// backup exists?
	if (!is_dir("{$base_path}temp/backup")) {
		$errors += 1;
		echo p(echo_failed() . $_lang['checking_if_backup_exists']);
	}
	
	// backup writable?
	if (!is_writable("{$base_path}temp/backup")) {
		$_ = echo_failed();
		$errors += 1;
	}
	else $_ = echo_ok();
	echo p($_ . $_lang['checking_if_backup_writable']);
}

// config.inc.php writable?
$config_path = "{$base_path}manager/includes/config.inc.php";
if (!is_file($config_path)) {
	// make an attempt to create the file
	file_put_contents($config_path,'<?php //MODX configuration file ?>');
}
@chmod($config_path, 0666);
$isWriteable = is_writable($config_path);
if (!$isWriteable) {
    $_ = echo_failed() . "</p><p><strong>".$_lang['config_permissions_note']."</strong>";
    $errors += 1;
}
else  $_ = echo_ok();
echo p($_ . $_lang['checking_if_config_exist_and_writable']);


if (!@ $conn = mysql_connect($_SESSION['database_server'], $_SESSION['database_user'], $_SESSION['database_password'])) {
    $errors += 1;
    $_ = echo_failed($_lang['database_connection_failed']) . "<p>".$_lang['database_connection_failed_note'];
} else {
    $_ = echo_ok();
}
echo p($_ . $_lang['creating_database_connection']);

// check mysql version
if ($conn) {
	if(strpos(mysql_get_server_info(), '5.0.51')!==false ) {
		$_ = echo_failed($_lang['warning']) . '&nbsp;&nbsp;<strong>'. $_lang['mysql_5051'] . '</strong>';
		echo p($_ . $_lang['checking_mysql_version']);
		echo p(echo_failed($_lang['mysql_5051_warning']));
	} else {
		$_ = echo_ok() . ' <strong>' . $_lang['mysql_version_is'] . mysql_get_server_info() . ' </strong>';
		echo p($_ . $_lang['checking_mysql_version']);
	}
	
	// check for strict mode
	$mysqlmode = @ mysql_query("SELECT @@global.sql_mode");
	if (@mysql_num_rows($mysqlmode) > 0 && !is_webmatrix() && !is_iis()) {
		$modes = mysql_fetch_array($mysqlmode, MYSQL_NUM);
		//$modes = array("STRICT_TRANS_TABLES"); // for testing
		foreach ($modes as $mode) {
			if (stristr($mode, "STRICT_TRANS_TABLES") !== false || stristr($mode, "STRICT_ALL_TABLES") !== false) {
				echo p($_lang['checking_mysql_strict_mode']);
				echo p(echo_failed($_lang['warning']) . '<strong>&nbsp;&nbsp;' . $_lang['strict_mode'] . '</strong>');
				echo p(echo_failed($_lang['strict_mode_error']));
			}
		}
	}
}
// Version and strict mode check end

// andrazk 20070416 - add install flag and disable manager login
// assets/cache writable?

if (is_writable("../assets/cache")) {
    // make an attempt to create the file
    file_put_contents("{$base_path}assets/cache/installProc.inc.php",'<?php $installStartTime = '.time().'; ?>');
}

if ($errors > 0) {
?>
      <p>
<?php
	echo "<strong>{$_lang['setup_cannot_continue']}</strong>";
	if ($errors > 1) {
		echo $errors . ' ';
		echo $_lang['errors'];
		echo $_lang['please_correct_errors'];
		echo $_lang['and_try_again_plural'];
	} else {
		echo $_lang['error'];
		echo $_lang['please_correct_error'];
		echo $_lang['and_try_again'];
	}
	echo $_lang['visit_forum'];
?>
      </p>
<?php
}

echo p('&nbsp;');

$nextAction= $errors > 0 ? 'summary' : 'install';
$nextButton= $errors > 0 ? $_lang['retry'] : $_lang['install'];
$nextVisibility= $errors > 0 || $chkagree ? 'visible' : 'hidden';
$agreeToggle= $errors > 0 ? '' : ' onclick="if(document.getElementById(\'chkagree\').checked){document.getElementById(\'nextbutton\').style.visibility=\'visible\';}else{document.getElementById(\'nextbutton\').style.visibility=\'hidden\';}"';
?>
<form id="install" action="index.php?action=<?php echo $nextAction;?>" method="POST">
  <div>
    <input type="hidden" value="1" name="options_selected" />
    <input type="hidden" name="prev_action" value="summary" />
</div>

<h2><?php echo $_lang['agree_to_terms'];?></h2>
<p>
<input type="checkbox" value="1" id="chkagree" name="chkagree" style="line-height:18px" <?php echo $chkagree ? 'checked="checked" ':""; ?><?php echo $agreeToggle;?>/><label for="chkagree" style="display:inline;float:none;line-height:18px;"> <?php echo $_lang['iagree_box']?> </label>
</p>
    <p class="buttonlinks">
        <a href="javascript:void(0);" class="prev" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a href="javascript:void(0);" class="next" id="nextbutton" title="<?php echo $nextButton ?>" style="visibility:<?php echo $nextVisibility;?>"><span><?php echo $nextButton ?></span></a>
    </p>
</form>
<script type="text/javascript">
jQuery('a.prev').click(function(){
	jQuery('#install').attr({action:'index.php?action=options'});
	jQuery('#install').submit();
});
jQuery('a.next').click(function(){
	jQuery('#install').submit();
});
</script>

<?php
function echo_ok() {
	global $_lang;
	return '<span class="ok">' . $_lang['ok'] . '</span>';
}

function echo_failed($msg=NULL) {
	global $_lang;
	if(is_null($msg)) $msg = $_lang['failed'];
	return '<span class="notok">' . $msg . '</span>';
}

function mkd($path) {
	if(!is_dir($path)) {
		$rs = @mkdir($path, 0777, true);
		if($rs) $rs = @chmod($path, 0777);
	}
	
	if(!is_file("{$path}/index.html")) {
		$rs = @file_put_contents("{$path}/index.html",'');
		if($rs) @chmod("{$path}/index.html", 0666);
		if(!is_writable("{$path}/index.html")) echo echo_failed($path);
	}
	
	return $rs;
}

function p($str) {
	return "<p>{$str}</p>";
}
