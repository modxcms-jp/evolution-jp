<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('bk_manager')) {
	$e->setError(3);
	$e->dumpError();
}

if(!isset($modx->config['snapshot_path'])||strpos($modx->config['snapshot_path'],MODX_BASE_PATH)===false) {
	if(is_dir(MODX_BASE_PATH . 'temp/backup/')) $modx->config['snapshot_path'] = MODX_BASE_PATH . 'temp/backup/';
	elseif(is_dir(MODX_BASE_PATH . 'assets/backup/')) $modx->config['snapshot_path'] = MODX_BASE_PATH . 'assets/backup/';
}

// Backup Manager by Raymond:

$mode = isset($_POST['mode']) ? $_POST['mode'] : '';

include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');

$dumper = new Mysqldumper();

if ($mode=='backup')
{
	$tables = isset($_POST['chk']) ? $_POST['chk'] : '';
	if (!is_array($tables))
	{
		$modx->webAlertAndQuit('Please select a valid table from the list below','history.back(-1);');
		exit;
	}

	/*
	 * Code taken from Ralph A. Dahlgren MySQLdumper Snippet - Etomite 0.6 - 2004-09-27
	 * Modified by Raymond 3-Jan-2005
	 * Perform MySQLdumper data dump
	 */
	@set_time_limit(120); // set timeout limit to 2 minutes
	$dumper->setDBtables($tables);
	$dumper->addDropCommand((isset($_POST['droptables']) ? true : false));
	$output = $dumper->createDump();
	$dumper->dumpSql($output);
	if(!$output)
	{
		$e->setError(1, 'Unable to Backup Database');
		$e->dumpError();
	}
	exit;

	// MySQLdumper class can be found below
}
elseif ($mode=='snapshot')
{
	if(!is_dir(rtrim($modx->config['snapshot_path'],'/')))
	{
		mkdir(rtrim($modx->config['snapshot_path'],'/'));
		@chmod(rtrim($modx->config['snapshot_path'],'/'), 0777);
	}
	if(!is_file("{$modx->config['snapshot_path']}.htaccess"))
	{
		$htaccess = "order deny,allow\ndeny from all\n";
		file_put_contents("{$modx->config['snapshot_path']}.htaccess",$htaccess);
	}
	if(!is_writable(rtrim($modx->config['snapshot_path'],'/')))
	{
		echo $modx->parseText($_lang["bkmgr_alert_mkdir"],array('snapshot_path'=>$modx->config['snapshot_path']));
		exit;
	}
	
	$today = $modx->toDateFormat($_SERVER['REQUEST_TIME']);
	$today = str_replace(array('/',' '), '-', $today);
	$today = str_replace(':', '', $today);
	$today = strtolower($today);
	global $path,$modx_version;
	$filename = "{$today}-{$modx_version}.sql";
	
	@set_time_limit(120); // set timeout limit to 2 minutes
	$dumper->mode = 'snapshot';
	$output = $dumper->createDump();
	$dumper->snapshot($modx->config['snapshot_path'].$filename,$output);
	
	$pattern = "{$modx->config['snapshot_path']}*.sql";
	$files = glob($pattern,GLOB_NOCHECK);
	$total = ($files[0] !== $pattern) ? count($files) : 0;
	arsort($files);
	while(10 < $total && $limit < 50)
	{
		$del_file = array_pop($files);
		unlink($del_file);
		$total = count($files);
		$limit++;
	}
	
	if(!empty($output))
	{
		$_SESSION['result_msg'] = 'snapshot_ok';
		header("Location: index.php?a=93");
	} else {
		$e->setError(1, 'Unable to Backup Database');
		$e->dumpError();
	}
	exit;
}
else
{
	include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');  // start normal header
}

if(isset($_SESSION['result_msg']) && $_SESSION['result_msg'] != '')
{
	switch($_SESSION['result_msg'])
	{
		case 'import_ok':
			$ph['result_msg'] = '<div class="okmsg">' . $_lang["bkmgr_import_ok"] . '</div>';
			break;
		case 'snapshot_ok':
			$ph['result_msg'] = '<div class="okmsg">' . $_lang["bkmgr_snapshot_ok"] . '</div>';
			break;
	}
	$_SESSION['result_msg'] = '';
}
else $ph['result_msg'] = '';

?>
<script language="javascript">
	function selectAll() {
		var f = document.forms['frmdb'];
		var c = f.elements['chk[]'];
		for(i=0;i<c.length;i++){
			c[i].checked=f.chkselall.checked;
		}
	}
	function backup(){
		var f = document.forms['frmdb'];
		f.mode.value='backup';
		f.target='fileDownloader';
		f.submit();
		return false;
	}
	<?php echo isset($_REQUEST['r']) ? " doRefresh(".$_REQUEST['r'].");" : "" ;?>

</script>
<h1><?php echo $_lang['bk_manager']?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
	<div class="tab-pane" id="dbmPane">
	<script type="text/javascript">
	    tpDBM = new WebFXTabPane(document.getElementById('dbmPane'));
	</script>
	<div class="tab-page" id="tabBackup">
	    <h2 class="tab"><?php echo $_lang['backup']?></h2>
	<form name="frmdb" method="post">
	<input type="hidden" name="mode" value="" />
	<p><?php echo $_lang['table_hoverinfo']?></p>

	<p class="actionButtons"><a class="primary" href="#" onclick="backup();return false;"><img src="media/style/<?php echo $modx->config['manager_theme'];?>/images/misc/ed_save.gif" /> <?php echo $_lang['database_table_clickbackup']?></a></p>
	<p><label><input type="checkbox" name="droptables" checked="checked" /><?php echo $_lang['database_table_droptablestatements']?></label></p>
	<table border="0" cellpadding="1" cellspacing="1" width="100%" bgcolor="#ccc">
		<thead><tr>
			<td width="160"><label><input type="checkbox" name="chkselall" onclick="selectAll()" title="Select All Tables" /><b><?php echo $_lang['database_table_tablename']?></b></label></td>
			<td align="right"><b><?php echo $_lang['database_table_records']?></b></td>
			<td align="right"><b><?php echo $_lang['database_collation']?></b></td>
			<td align="right"><b><?php echo $_lang['database_table_datasize']?></b></td>
			<td align="right"><b><?php echo $_lang['database_table_overhead']?></b></td>
			<td align="right"><b><?php echo $_lang['database_table_effectivesize']?></b></td>
			<td align="right"><b><?php echo $_lang['database_table_indexsize']?></b></td>
			<td align="right"><b><?php echo $_lang['database_table_totalsize']?></b></td>
		</tr></thead>
		<tbody>
			<?php
$dbase = trim($dbase,'`');
$sql = "SHOW TABLE STATUS FROM `{$dbase}` LIKE '{$table_prefix}%'";
$rs = $modx->db->query($sql);
$i = 0;
while($row = $modx->db->getRow($rs)) {
	$bgcolor = ($i % 2) ? '#EEEEEE' : '#FFFFFF';

	if (isset($dumper->_dbtables)&&!empty($dumper->_dbtables))
		 $table_string = implode(',', $dumper->_dbtables);
	else $table_string = '';

	echo '<tr bgcolor="'.$bgcolor.'" title="'.$row['Comment'].'" style="cursor:default">'."\n".
	     '<td><label><input type="checkbox" name="chk[]" value="'.$row['Name'].'"'.(strstr($table_string,$row['Name']) === false ? '' : ' checked="checked"').' /><b style="color:#009933">'.$row['Name'].'</b></label></td>'."\n".
	     '<td align="right">'.$row['Rows'].'</td>'."\n";
	echo '<td align="right">'.$row['Collation'].'</td>'."\n";

	// Enable record deletion for certain tables (TRUNCATE TABLE) if they're not already empty
	$truncateable = array(
		$table_prefix.'event_log',
		$table_prefix.'manager_log',
	);
	if($modx->hasPermission('settings') && in_array($row['Name'], $truncateable) && $row['Rows'] > 0) {
		echo '<td dir="ltr" align="right">'.
		     '<a href="index.php?a=54&mode='.$action.'&u='.$row['Name'].'" title="'.$_lang['truncate_table'].'">'.$modx->nicesize($row['Data_length']+$row['Data_free']).'</a>'.
		     '</td>'."\n";
	} else {
		echo '<td dir="ltr" align="right">'.$modx->nicesize($row['Data_length']+$row['Data_free']).'</td>'."\n";
	}

	if($modx->hasPermission('settings')) {
		echo '<td align="right">'.($row['Data_free'] > 0 ?
		     '<a href="index.php?a=54&mode='.$action.'&t='.$row['Name'].'" title="'.$_lang['optimize_table'].'">'.$modx->nicesize($row['Data_free']).'</a>' :
		     '-').
		     '</td>'."\n";
	} else {
		echo '<td align="right">'.($row['Data_free'] > 0 ? $modx->nicesize($row['Data_free']) : '-').'</td>'."\n";
	}

	echo '<td dir="ltr" align="right">'.$modx->nicesize($row['Data_length']-$row['Data_free']).'</td>'."\n".
	     '<td dir="ltr" align="right">'.$modx->nicesize($row['Index_length']).'</td>'."\n".
	     '<td dir="ltr" align="right">'.$modx->nicesize($row['Index_length']+$row['Data_length']+$row['Data_free']).'</td>'."\n".
	     "</tr>";

	$total = $total+$row['Index_length']+$row['Data_length'];
	$totaloverhead = $totaloverhead+$row['Data_free'];
	$i++;
}
?>
			<tr bgcolor="#CCCCCC">
				<td valign="top"><b><?php echo $_lang['database_table_totals']?></b></td>
				<td colspan="3">&nbsp;</td>
				<td dir="ltr" align="right" valign="top"><?php echo $totaloverhead>0 ? '<b style="color:#990033">'.$modx->nicesize($totaloverhead).'</b><br />('.number_format($totaloverhead).' B)' : '-'?></td>
				<td colspan="2">&nbsp;</td>
				<td dir="ltr" align="right" valign="top"><?php echo "<b>".$modx->nicesize($total)."</b><br />(".number_format($total)." B)"?></td>
			</tr>
		</tbody>
	</table>
<?php
if ($totaloverhead > 0) {
	echo '<p>'.$_lang['database_overhead'].'</p>';
}
?>
</form>
</div>
<!-- This iframe is used when downloading file backup file -->
<iframe name="fileDownloader" width="1" height="1" style="display:none; width:1px; height:1px;"></iframe>
<div class="tab-page" id="tabRestore">
	<h2 class="tab"><?php echo $_lang["bkmgr_restore_title"];?></h2>
	<?php echo $ph['result_msg']; ?>
	<?php echo $_lang["bkmgr_restore_msg"]; ?>
	<form method="post" name="mutate" enctype="multipart/form-data" action="index.php">
	<input type="hidden" name="a" value="305" />
	<input type="hidden" name="mode" value="restore1" />
	<script type="text/javascript">
	function showhide(a)
	{
		var f=document.getElementById('sqlfile');
		var t=document.getElementById('textarea');
		if(a=='file')
		{
			f.style.display = 'block';
			t.style.display = 'none';
		}
		else
		{
			t.style.display = 'block';
			f.style.display = 'none';
		}
	}
	</script>
<?php
if(isset($_SESSION['textarea']) && !empty($_SESSION['textarea']))
{
	$value = $_SESSION['textarea'];
	unset($_SESSION['textarea']);
	$_SESSION['console_mode'] = 'text';
	$f_display = 'none';
	$t_display = 'block';
}
else
{
	$value = '';
	$_SESSION['console_mode'] = 'file';
	$f_display = 'block';
	$t_display = 'none';
}

if(isset($_SESSION['last_result']) || !empty($_SESSION['last_result']))
{
	$last_result = $_SESSION['last_result'];
	unset($_SESSION['last_result']);
	if(count($last_result)<1) $result = '';
	elseif(count($last_result)==1) echo $last_result[0];
	else
	{
		$last_result = array_merge(array(), array_diff($last_result, array('')));
		foreach($last_result['0'] as $k=>$v)
		{
			$title[] = $k;
		}
		$result = '<tr><th>' . join('</th><th>',$title) . '</th></tr>';
		foreach($last_result as $row)
		{
			$result_value = array();
			if($row)
			{
				foreach($row as $k=>$v)
				{
					$result_value[] = $v;
				}
				$result .= '<tr><td>' . join('</td><td>',$result_value) . '</td></tr>';
			}
		}
		$style = '<style type="text/css">table th {border:1px solid #ccc;background-color:#ddd;}</style>';
		$result = $style . '<table>' . $result . '</table>';
	}
}

?>
	<p>
	<label><input type="radio" name="sel" onclick="showhide('file');" <?php echo checked(!isset($_SESSION['console_mode']) || $_SESSION['console_mode'] !== 'text');?> /> <?php echo $_lang["bkmgr_run_sql_file_label"];?></label>
	<label><input type="radio" name="sel" onclick="showhide('textarea');" <?php echo checked(isset($_SESSION['console_mode']) && $_SESSION['console_mode'] === 'text');?> /> <?php echo $_lang["bkmgr_run_sql_direct_label"];?></label>
	</p>
	<div><input type="file" name="sqlfile" id="sqlfile" size="70" style="display:<?php echo $f_display;?>;" /></div>
	<div id="textarea" style="display:<?php echo $t_display;?>;">
		<textarea name="textarea" style="width:500px;height:200px;"><?php echo $value;?></textarea>
	</div>
	<div class="actionButtons" style="margin-top:10px;">
	<a href="#" class="primary" onclick="document.mutate.save.click();"><img alt="icons_save" src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang["bkmgr_run_sql_submit"];?></a>
	</div>
	<input type="submit" name="save" style="display:none;" />
	</form>
<?php
	if(isset($result)) echo '<div style="margin-top:20px;"><p style="font-weight:bold;"><?php echo $_lang["bkmgr_run_sql_result"];?></p>' . $result . '</div>';
?>
</div>
<?php
    $today = $modx->toDateFormat($_SERVER['REQUEST_TIME']);
    $today = str_replace(array('/',' '), '-', $today);
    $today = str_replace(':', '', $today);
    $today = strtolower($today);
    global $modx_version;
    $filename = "{$today}-{$modx_version}.sql";
?>
<div class="tab-page" id="tabSnapshot">
	<h2 class="tab"><?php echo $_lang["bkmgr_snapshot_title"];?></h2>
	<?php echo $ph['result_msg']; ?>
	<?php echo $modx->parseText($_lang["bkmgr_snapshot_msg"],"snapshot_path={$modx->config['snapshot_path']}");?>
	<form method="post" name="snapshot" action="index.php">
	<input type="hidden" name="a" value="307" />
	<input type="hidden" name="mode" value="snapshot" />
	<table>
	<tr><th><?php echo $_lang["bk.contentOnly"];?></th><td><input type="checkbox" name="contentsOnly" value="1" /></td></tr>
	<tr><th><?php echo $_lang["bk.fileName"];?></th><td><input type="text" name="file_name" size="50" value="<?php echo $filename;?>" /></td></tr>
	</table>
	<div class="actionButtons" style="margin-top:10px;margin-bottom:10px;">
	<a href="#" class="primary" onclick="document.snapshot.save.click();"><img alt="icons_save" src="<?php echo $_style["icons_add"]?>" /><?php echo $_lang["bkmgr_snapshot_submit"];?></a>
	<input type="submit" name="save" style="display:none;" />
	</form>
	</div>
	<style type="text/css">
	table {background-color:#fff;border-collapse:collapse;}
	table td {padding:4px;}
	</style>
<div class="sectionHeader"><?php echo $_lang["bkmgr_snapshot_list_title"];?></div>
<div class="sectionBody">
	<form method="post" name="restore2" action="index.php">
	<input type="hidden" name="a" value="305" />
	<input type="hidden" name="mode" value="restore2" />
	<input type="hidden" name="filename" value="" />
<?php
$pattern = "{$modx->config['snapshot_path']}*.sql";
$files = glob($pattern,GLOB_NOCHECK);
$total = ($files[0] !== $pattern) ? count($files) : 0;
if(is_array($files) && 0 < $total)
{
	echo '<ul>';
	arsort($files);
	$tpl = '<li>[+filename+] ([+filesize+]) (<a href="#" onclick="document.restore2.filename.value=\'[+filename+]\';document.restore2.save.click()">' . $_lang["bkmgr_restore_submit"] . '</a>)</li>' . "\n";
	while ($file = array_shift($files))
	{
		$timestamp = filemtime($file);
		$filename = substr($file,strrpos($file,'/')+1);
		$filesize = $modx->nicesize(filesize($file));
		$output[$timestamp] = str_replace(array('[+filename+]','[+filesize+]'),array($filename,$filesize),$tpl);
	}
	krsort($output);
	foreach($output as $v)
	{
		echo $v;
	}
	echo '</ul>';
}
else
{
	echo $_lang["bkmgr_snapshot_nothing"];
}
?>
<input type="submit" name="save" style="display:none;" />
	</form>
</div>
</div>

</div>

</div>

<?php
	include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php'); // send footer



function checked($cond)
{
	if($cond) return ' checked';
}
