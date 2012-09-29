<?php
$installMode = intval($_POST['installmode']);

echo "<h2>" . $_lang['preinstall_validation'] . "</h2>";
echo "<h3>" . $_lang['summary_setup_check'] . "</h3>";
$errors = 0;
// check PHP version
echo "<p>" . $_lang['checking_php_version'];
$php_ver_comp = version_compare(phpversion(), '5.0.0');
if ($php_ver_comp < 0) {
    echo echo_failed().$_lang['you_running_php'] . phpversion() . $_lang["modx_requires_php"];
    $errors += 1;
}
echo '</p>';
// check php register globals off

$register_globals = (int) ini_get('register_globals');
if ($register_globals == '1'){
    echo "<p>" . $_lang['checking_registerglobals'];
    echo echo_failed() . "</p><p><strong>".$_lang['checking_registerglobals_note']."</strong>";
    echo '</p>';
}

// check sessions
if ($_SESSION['test'] != 1) {
echo "<p>" . $_lang['checking_sessions'];
    echo echo_failed();
echo '</p>';
    $errors += 1;
}

// check directories
// cache exists?
if (!is_dir("{$base_path}assets/cache")) {
echo "<p>" . $_lang['checking_if_cache_exist'];
    echo echo_failed();
echo '</p>';
    $errors += 1;
}

// cache writable?

echo "<p>" . $_lang['checking_if_cache_writable'];
if (!is_writable("{$base_path}assets/cache")) {
    echo echo_failed();
    $errors += 1;
} else {
    echo echo_ok();
    mkd("{$base_path}assets/cache/rss");
}
echo '</p>';

if (is_writable("{$base_path}assets/cache")) {
	// cache files writable?
	echo "<p>" . $_lang['checking_if_cache_file_writable'];
	if (!is_file("{$base_path}assets/cache/siteCache.idx.php")) {
	    // make an attempt to create the file
	    file_put_contents("{$base_path}assets/cache/siteCache.idx.php",'<?php //MODX site cache file ?>');
	}
	if (!is_writable("{$base_path}assets/cache/siteCache.idx.php")) {
	    echo echo_failed();
	    $errors += 1;
	} else {
	    echo echo_ok();
	}
	echo '</p>';
	    file_put_contents("{$base_path}assets/cache/sitePublishing.idx.php",'<?php $cacheRefreshTime=0; ?>');
	
	echo "<p>".$_lang['checking_if_cache_file2_writable'];
	if (!is_writable("{$base_path}assets/cache/sitePublishing.idx.php")) {
	    echo echo_failed();
	    $errors += 1;
	} else {
	    echo echo_ok();
	}
	echo '</p>';
}

if(!is_dir("{$base_path}assets/images"))
{
	if (!is_dir("{$base_path}content")) {
	echo "<p>" . $_lang['checking_if_content_exists'];
	    echo echo_failed();
	echo '</p>';
	    $errors += 1;
	}
	
	// cache writable?
	$dir_images = "{$base_path}content/images";
	$dir_files  = "{$base_path}content/files";
	$dir_flash  = "{$base_path}content/flash";
	$dir_media  = "{$base_path}content/media";
	
	echo "<p>" . $_lang['checking_if_content_writable'];
	if (!is_writable("{$base_path}content")) {
	    echo echo_failed();
	    $errors += 1;
	} else {
	    echo echo_ok();
		mkd($dir_images);
		mkd($dir_files);
		mkd($dir_flash);
		mkd($dir_media);
	}
	echo '</p>';
	if (is_writable("{$base_path}content"))
	{
		// File Browser directories exists?
		if (!is_dir($dir_images) || !is_dir($dir_files) || !is_dir($dir_flash) || !is_dir($dir_media))
		{
			echo "<p>".$_lang['checking_if_images_exist'];
			echo echo_failed();
			echo '</p>';
			$errors += 1;
		}
		else
		{
			// File Browser directories writable?
			echo "<p>".$_lang['checking_if_images_writable'];
			if (!is_writable($dir_images) || !is_writable($dir_files) || !is_writable($dir_flash) || !is_writable($dir_media))
			{
			    echo echo_failed();
			    $errors += 1;
			}
			else 
			{
			    echo echo_ok();
			}
			echo '</p>';
		}
	}
}

if (!is_dir("{$base_path}temp")) {
echo "<p>" . $_lang['checking_if_temp_exists'];
    echo echo_failed();
echo '</p>';
    $errors += 1;
}

// cache writable?

echo "<p>" . $_lang['checking_if_temp_writable'];
if (!is_writable("{$base_path}temp")) {
    echo echo_failed();
    $errors += 1;
} else {
    echo echo_ok();
	mkd("{$base_path}temp/export");
	mkd("{$base_path}temp/backup");
	if(is_dir("{$base_path}temp/backup")) @file_put_contents("{$base_path}temp/backup/.htaccess","order deny,allow\ndeny from all");
}
echo '</p>';

if (is_writable("{$base_path}temp")) {
	// export exists?
	if (!is_dir("{$base_path}temp/export")) {
	echo '<p>'.$_lang['checking_if_export_exists'];
	echo echo_failed();
	echo '</p>';
	$errors += 1;
	}
	
	// export writable?
	echo '<p>'.$_lang['checking_if_export_writable'];
	if (!is_writable("{$base_path}temp/export")) {echo echo_failed();$errors += 1;}
	else echo echo_ok();
	echo '</p>';
	
	// backup exists?
	if (!is_dir("{$base_path}temp/backup")) {
	echo '<p>'.$_lang['checking_if_backup_exists'];
	echo echo_failed();$errors += 1;
	echo '</p>';
	}
	
	// backup writable?
	echo '<p>'.$_lang['checking_if_backup_writable'];
	if (!is_writable("{$base_path}temp/backup")) {echo echo_failed();$errors += 1;}
	else echo echo_ok();
	echo '</p>';
}

// config.inc.php writable?
echo "<p>".$_lang['checking_if_config_exist_and_writable'];
$config_path = "{$base_path}manager/includes/config.inc.php";
if (!is_file($config_path)) {
    // make an attempt to create the file
    file_put_contents($config_path,'<?php //MODx configuration file ?>');
}
@chmod($config_path, 0606);
$isWriteable = is_writable($config_path);
if (!$isWriteable) {
    echo echo_failed() . "</p><p><strong>".$_lang['config_permissions_note']."</strong>";
    $errors += 1;
} else {
    echo echo_ok();
}
echo '</p>';
// connect to the database
if ($installMode == 1) {
    include_once $config_path;
} else {
    // get db info from post
    $database_server = $_POST['databasehost'];
    $database_user = $_SESSION['databaseloginname'];
    $database_password = $_SESSION['databaseloginpassword'];
    $database_collation = $_POST['database_collation'];
    $database_charset = substr($database_collation, 0, strpos($database_collation, '_') - 1);
    $database_connection_charset = $_POST['database_connection_charset'];
    $database_connection_method = $_POST['database_connection_method'];
    $dbase = $_POST['database_name'];
    $table_prefix = $_POST['tableprefix'];
}
echo "<p>".$_lang['creating_database_connection'];
if (!@ $conn = mysql_connect($database_server, $database_user, $database_password)) {
    $errors += 1;
    echo echo_failed($_lang['database_connection_failed']) . "<p>".$_lang['database_connection_failed_note'];
} else {
    echo echo_ok();
}
echo '</p>';
// make sure we can use the database
if ($installMode > 0 && !@ mysql_query("USE {$dbase}")) {
    $errors += 1;
    echo echo_failed($_lang['database_use_failed']) . "<p>".$_lang['database_use_failed_note']."</p>";
}

// check the database collation if not specified in the configuration
if (!isset ($database_connection_charset) || empty ($database_connection_charset)) {
    if (!$rs = @ mysql_query("show session variables like 'collation_database'")) {
        $rs = @ mysql_query("show session variables like 'collation_server'");
    }
    if ($rs && $collation = mysql_fetch_row($rs)) {
        $database_collation = $collation[1];
    }
    if (empty ($database_collation)) {
        $database_collation = 'utf8_general_ci';
    }
    $database_charset = substr($database_collation, 0, strpos($database_collation, '_') - 1);
    $database_connection_charset = $database_charset;
}

// determine the database connection method if not specified in the configuration
if (!isset($database_connection_method) || empty($database_connection_method)) {
    $database_connection_method = 'SET CHARACTER SET';
}

// check table prefix
if ($conn && $installMode == 0) {
    echo "<p>" . $_lang['checking_table_prefix'] . $table_prefix . "`: ";
    if ($rs= @ mysql_query("SELECT COUNT(id) FROM $dbase.`" . $table_prefix . "site_content`")) {
        echo echo_failed() . "</b>" . $_lang['table_prefix_already_inuse'] . "</p>";
        echo "<p>" . $_lang['table_prefix_already_inuse_note'] . "</p>";
        $errors += 1;
    } else {
        echo echo_ok() . "</p>";
    }
} elseif ($conn && $installMode == 2) {
    echo "<p>" . $_lang['checking_table_prefix'] . $table_prefix . "`: ";
    if (!$rs = @ mysql_query("SELECT COUNT(id) FROM $dbase.`" . $table_prefix . "site_content`")) {
        echo echo_failed() . "</b>" . $_lang['table_prefix_not_exist'] . "</p>";
        $errors += 1;
        echo "<p>" . $_lang['table_prefix_not_exist_note'] . "</p>";
  } else {
        echo echo_ok() . "</p>";
  }
}

// check mysql version
if ($conn) {
    echo "<p>" . $_lang['checking_mysql_version'];
    if ( strpos(mysql_get_server_info(), '5.0.51')!==false ) {
        echo echo_failed($_lang['warning']) . "</b>&nbsp;&nbsp;<strong>". $_lang['mysql_5051'] . "</strong></p>";
        echo "<p>" . echo_failed($_lang['mysql_5051_warning'] ) . "</p>";
    } else {
        echo echo_ok() . "&nbsp;&nbsp;<strong>" . $_lang['mysql_version_is'] . mysql_get_server_info() . "</strong></p>";
    }
}

// check for strict mode
if ($conn)
{
    $mysqlmode = @ mysql_query("SELECT @@global.sql_mode");
    if (@mysql_num_rows($mysqlmode) > 0 && !is_webmatrix() && !is_iis())
    {
        $modes = mysql_fetch_array($mysqlmode, MYSQL_NUM);
        //$modes = array("STRICT_TRANS_TABLES"); // for testing
        foreach ($modes as $mode)
        {
            if (stristr($mode, "STRICT_TRANS_TABLES") !== false || stristr($mode, "STRICT_ALL_TABLES") !== false)
            {
                echo "<p>". $_lang['checking_mysql_strict_mode'] . "</p>";
                echo '<p>' . echo_failed($_lang['warning']) . "</b> <strong>&nbsp;&nbsp;" . $_lang['strict_mode'] . "</strong></p>";
                echo "<p>" . echo_failed($_lang['strict_mode_error'])  . "</p>";
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

if($installMode > 0 && $_POST['installdata'] == "1") {
    echo "<p class=\"notes\"><strong>{$_lang['sample_web_site']}:</strong> {$_lang['sample_web_site_note']}</p>\n";
}

if ($errors > 0) {
?>
      <p>
      <?php
      echo "<strong>{$_lang['setup_cannot_continue']}</strong>";
      echo $errors > 1 ? $errors." " : "";
      if ($errors > 1) echo $_lang['errors'];
      else echo $_lang['error'];
      if ($errors > 1) echo $_lang['please_correct_errors'];
      else echo $_lang['please_correct_error'];
      if ($errors > 1) echo $_lang['and_try_again_plural'];
      else echo $_lang['and_try_again'];
      echo $_lang['visit_forum'];
      ?>
      </p>
      <?php
}

echo "<p>&nbsp;</p>";

$nextAction= $errors > 0 ? 'summary' : 'install';
$nextButton= $errors > 0 ? $_lang['retry'] : $_lang['install'];
$nextVisibility= $errors > 0 || isset($_POST['chkagree']) ? 'visible' : 'hidden';
$agreeToggle= $errors > 0 ? '' : ' onclick="if(document.getElementById(\'chkagree\').checked){document.getElementById(\'nextbutton\').style.visibility=\'visible\';}else{document.getElementById(\'nextbutton\').style.visibility=\'hidden\';}"';
$trimed_db_name = trim($_POST['database_name'], '`');
echo <<< EOT
<form name="install" id="install_form" action="index.php?action={$nextAction}" method="post">
  <div>
    <input type="hidden" value="{$install_language}" name="language" />
	<input type="hidden" value="{$manager_language}" name="managerlanguage" />
    <input type="hidden" value="{$installMode}" name="installmode" />
    <input type="hidden" value="{$trimed_db_name}" name="database_name" />
    <input type="hidden" value="{$_POST['tableprefix']}" name="tableprefix" />
    <input type="hidden" value="{$_POST['database_collation']}" name="database_collation" />
    <input type="hidden" value="{$_POST['database_connection_charset']}" name="database_connection_charset" />
    <input type="hidden" value="{$_POST['database_connection_method']}" name="database_connection_method" />
    <input type="hidden" value="{$_POST['databasehost']}" name="databasehost" />
    <input type="hidden" value="{$_POST['cmsadmin']}" name="cmsadmin" />
    <input type="hidden" value="{$_POST['cmsadminemail']}" name="cmsadminemail" />
    <input type="hidden" value="{$_POST['cmspassword']}" name="cmspassword" />
    <input type="hidden" value="{$_POST['cmspasswordconfirm']}" name="cmspasswordconfirm" />
    <input type="hidden" value="1" name="options_selected" />
    <input type="hidden" value="{$_POST['installdata']}" name="installdata" />
EOT;
$templates = isset ($_POST['template']) ? $_POST['template'] : array ();
foreach ($templates as $i => $template) echo "<input type=\"hidden\" name=\"template[]\" value=\"$template\" />\n";
$tvs = isset ($_POST['tv']) ? $_POST['tv'] : array ();
foreach ($tvs as $i => $tv) echo "<input type=\"hidden\" name=\"tv[]\" value=\"$tv\" />\n";
$chunks = isset ($_POST['chunk']) ? $_POST['chunk'] : array ();
foreach ($chunks as $i => $chunk) echo "<input type=\"hidden\" name=\"chunk[]\" value=\"$chunk\" />\n";
$snippets = isset ($_POST['snippet']) ? $_POST['snippet'] : array ();
foreach ($snippets as $i => $snippet) echo "<input type=\"hidden\" name=\"snippet[]\" value=\"$snippet\" />\n";
$plugins = isset ($_POST['plugin']) ? $_POST['plugin'] : array ();
foreach ($plugins as $i => $plugin) echo "<input type=\"hidden\" name=\"plugin[]\" value=\"$plugin\" />\n";
$modules = isset ($_POST['module']) ? $_POST['module'] : array ();
foreach ($modules as $i => $module) echo "<input type=\"hidden\" name=\"module[]\" value=\"$module\" />\n";
?>
</div>

<h2><?php echo $_lang['agree_to_terms'];?></h2>
<p>
<input type="checkbox" value="1" id="chkagree" name="chkagree" style="line-height:18px" <?php echo isset($_POST['chkagree']) ? 'checked="checked" ':""; ?><?php echo $agreeToggle;?>/><label for="chkagree" style="display:inline;float:none;line-height:18px;"> <?php echo $_lang['iagree_box']?> </label>
</p>
    <p class="buttonlinks">
        <a href="javascript:document.getElementById('install_form').action='index.php?action=options&language=<?php $install_language?>';document.getElementById('install_form').submit();" class="prev" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a id="nextbutton" href="javascript:document.getElementById('install_form').submit();" title="<?php echo $nextButton ?>" style="visibility:<?php echo $nextVisibility;?>"><span><?php echo $nextButton ?></span></a>
    </p>
</form>

<?php
function echo_failed($msg=NULL)
{
	global $_lang;
	if(is_null($msg)) $msg = $_lang['failed'];
	return '<span class="notok">' . $msg . '</span>';
}

function echo_ok()
{
	global $_lang;
	return '<span class="ok">' . $_lang['ok'] . '</span>';
}

function mkd($path)
{
	// if(ini_get('safe_mode') !=0) return;
	
	if(!is_dir($path))
	{
		$rs = @mkdir($path, true);
		if($rs) $rs = @chmod($path, 0777);
	}
	
	if(!is_file("{$path}/index.html"))
	{
		$rs = @file_put_contents("{$path}/index.html",'');
		if($rs) @chmod("{$path}/index.html", 0666);
		if(!is_writable("{$path}/index.html")) echo echo_failed($path);
	}
	
	return $rs;
}
