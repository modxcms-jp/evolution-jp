<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('logs')) {
	$e->setError(3);
	$e->dumpError();
}

$rs = $modx->db->select('DISTINCT internalKey, username, action, itemid, itemname','[+prefix+]manager_log');
$logs = array();
while ($row = $modx->db->getRow($rs))
{
	$logs[] = $row;
}
$form_v = $_REQUEST;
?>
<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
window.addEvent('domready', function() {
	var dpOffset = <?php echo $modx->config['datepicker_offset']; ?>;
	var dpformat = "<?php echo $modx->config['datetime_format']; ?>" + ' hh:mm:00';
	new DatePicker($('datefrom'), {'yearOffset': dpOffset,'format':dpformat});
	new DatePicker($('dateto'), {'yearOffset': dpOffset,'format':dpformat});
});
</script>
<h1><?php echo $_lang["mgrlog_view"]?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
<form action="index.php?a=13" name="logging" class="mutate" method="POST">
<div class="tab-pane" id="logPane">
	<script type="text/javascript">
		tpMgrLogSearch = new WebFXTabPane(document.getElementById('logPane'));
	</script>
	<div class="tab-page" id="tabGeneral">
		<h2 class="tab"><?php echo $_lang['general'];?></h2>
		<script type="text/javascript">
			tpSettings.addTabPage(document.getElementById('tabGeneral'));
		</script>
		<table border="0" cellpadding="2" cellspacing="0">
		 <tbody>
		  <tr style="background-color:#fff;">
		    <td style="width:120px;"><b><?php echo $_lang["mgrlog_msg"]; ?></b></td>
		    <td align="right">
		      <input type="text" name="message" class="inputbox" style="width:240px" value="<?php echo $form_v['message']; ?>" />
		    </td>
		  </tr>
		  <tr>
		    <td><b><?php echo $_lang["mgrlog_user"]?></b></td>
		    <td align="right">
			<select name="searchuser" class="inputBox" style="width:240px">
				<option value="0"><?php echo $_lang["mgrlog_anyall"]?></option>
		<?php
			// get all users currently in the log
			$logs_user = record_sort(array_unique_multi($logs, 'internalKey'), 'username');
			foreach ($logs_user as $row) {
				$selectedtext = $row['internalKey'] == $form_v['searchuser'] ? 'selected="selected"' : '';
				echo sprintf('<option value="%s" %s>%s</option>', $row['internalKey'],$selectedtext,$row['username'])."\n";
			}
		?>	</select>
		    </td>
		  </tr>
		</table>
	</div>
	<div class="tab-page" id="tabSettings">
		<h2 class="tab"><?php echo $_lang['option'];?></h2>
		<script type="text/javascript">
			tpSettings.addTabPage(document.getElementById('tabSettings'));
		</script>
		<table border="0" cellpadding="2" cellspacing="0">
		 <tbody>
		  <tr>
		    <td><b><?php echo $_lang["mgrlog_action"]; ?></b></td>
		    <td align="right">
			<select name="action" class="inputBox" style="width:240px;">
				<option value="0"><?php echo $_lang["mgrlog_anyall"]; ?></option>
		<?php
			// get all available actions in the log
			include_once(MODX_CORE_PATH . 'actionlist.inc.php');
			$logs_actions = record_sort(array_unique_multi($logs, 'action'), 'action');
			foreach ($logs_actions as $row) {
				$action = getAction($row['action']);
				if ($action == 'Idle') continue;
				$selectedtext = $row['action'] == $form_v['action'] ? 'selected="selected"' : '';
				echo sprintf('<option value="%s" %s>%s - %s</option>', $row['action'],$selectedtext,$row['action'],$action)."\n";
			}
		?>	</select>
		    </td>
		  </tr>
		  <tr style="background-color:#fff;">
		    <td><b><?php echo $_lang["mgrlog_itemid"]; ?></b></td>
		    <td align="right">
			<select name="itemid" class="inputBox" style="width:240px">
				<option value="0"><?php echo $_lang["mgrlog_anyall"]; ?></option>
		<?php
			// get all itemid currently in logging
			$logs_items = record_sort(array_unique_multi($logs, 'itemid'), 'itemid');
			foreach ($logs_items as $row) {
				$selectedtext = $row['itemid'] == $form_v['itemid'] ? ' selected="selected"' : '';
				echo '<option value="'.$row['itemid'].'"'.$selectedtext.'>'.$row['itemid']."</option>\n";
			}
		?>	</select>
		    </td>
		  </tr>
		  <tr>
		    <td><b><?php echo $_lang["mgrlog_itemname"]; ?></b></td>
		    <td align="right">
			<select name="itemname" class="inputBox" style="width:240px">
				<option value="0"><?php echo $_lang["mgrlog_anyall"]; ?></option>
		<?php
			// get all itemname currently in logging
			$logs_names = record_sort(array_unique_multi($logs, 'itemname'), 'itemname');
			foreach ($logs_names as $row) {
				$selectedtext = $row['itemname'] == $form_v['itemname'] ? ' selected="selected"' : '';
				echo '<option value="'.$row['itemname'].'"'.$selectedtext.'>'.$row['itemname']."</option>\n";
			}
		?>	</select>
		    </td>
		  </tr>
		  <tr>
		    <td><b><?php echo $_lang["mgrlog_datefr"]; ?></b></td>
		        <td align="right">
		        	<input type="text" id="datefrom" name="datefrom" class="DatePicker" value="<?php echo isset($form_v['datefrom']) ? $form_v['datefrom'] : "" ; ?>" />
				  	<a onclick="document.logging.datefrom.value=''; return true;" style="cursor:pointer; cursor:hand"><img src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="No date" /></a>
			  </td>
		  </tr>
		  <tr style="background-color:#fff;">
		    <td><b><?php echo $_lang["mgrlog_dateto"]; ?></b></td>
		    <td align="right">
				  <input type="text" id="dateto" name="dateto" class="DatePicker" value="<?php echo isset($form_v['dateto']) ? $form_v['dateto'] : "" ; ?>" />
				  <a onclick="document.logging.dateto.value=''; return true;" style="cursor:pointer; cursor:hand"><img src="media/style/<?php echo $manager_theme; ?>/images/icons/cal_nodate.gif" border="0" alt="No date" /></a>
				 </td>
		      </tr>
		  <tr>
		    <td><b><?php echo $_lang["mgrlog_results"]; ?></b></td>
		    <td align="right">
		      <input type="text" name="nrresults" class="inputbox" style="width:100px" value="<?php echo isset($form_v['nrresults']) ? $form_v['nrresults'] : $number_of_logs; ?>" /><img src="<?php echo $_style['tx']; ?>" border="0" />
		    </td>
		  </tr>
		  </tbody>
		</table>
	</div>
	<ul class="actionButtons" style="margin-top:1em;margin-left:5px;">
		<li><a href="#" class="default" onclick="document.logging.log_submit.click();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang['search']; ?></a></li>
		<li><a href="index.php?a=2"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']; ?></a></li>
	</ul>
      <input type="submit" name="log_submit" value="<?php echo $_lang["mgrlog_searchlogs"]?>" style="display:none;" />
</div>
</div>

</form>

<?php if(isset($_POST['log_submit'])||isset($_GET['log_submit'])) :?>
<div class="section">
<div class="sectionHeader"><?php echo $_lang["mgrlog_qresults"]; ?></div>
<div class="sectionBody" id="lyr2">
<?php
if(isset($form_v['log_submit'])) {
	// get the selections the user made.
	$sqladd = array();
	$form_v = $modx->db->escape($form_v);
	if($form_v['searchuser']!=0) $sqladd[] = "internalKey='".intval($form_v['searchuser'])."'";
	if($form_v['action']!=0)     $sqladd[] = "action=".intval($form_v['action']);
	if($form_v['itemid']!=0 || $form_v['itemid']=="-")
	                            $sqladd[] = "itemid='".$form_v['itemid']."'";
	if($form_v['itemname']!='0') $sqladd[] = "itemname='".$form_v['itemname']."'";
	if($form_v['message']!="")   $sqladd[] = "message LIKE '%".$form_v['message']."%'";
	// date stuff
	if($form_v['datefrom']!="")  $sqladd[] = "timestamp>".convertdate($form_v['datefrom']);
	if($form_v['dateto']!="")    $sqladd[] = "timestamp<".convertdate($form_v['dateto']);

	// If current position is not set, set it to zero
	if( !isset( $form_v['int_cur_position'] ) || $form_v['int_cur_position'] == 0 ){
		 $int_cur_position = 0;
	} else {
		$int_cur_position = $form_v['int_cur_position'];
	}

	// Number of result to display on the page, will be in the LIMIT of the sql query also
	$int_num_result = is_numeric($form_v['nrresults']) ? $form_v['nrresults'] : $number_of_logs;

	$extargv = "&a=13&searchuser=".$form_v['searchuser']."&action=".$form_v['action'].
		"&itemid=".$form_v['itemid']."&itemname=".$form_v['itemname']."&message=".
		$form_v['message']."&dateto=".$form_v['dateto']."&datefrom=".
		$form_v['datefrom']."&nrresults=".$int_num_result."&log_submit=".$form_v['log_submit']; // extra argv here (could be anything depending on your page)

	// build the sql
	$where = (!empty($sqladd)) ? implode(' AND ', $sqladd) : '';
	$total = $modx->db->getValue($modx->db->select('COUNT(id)','[+prefix+]manager_log',$where));
	$orderby = 'timestamp DESC, id DESC';
	$limit = "{$int_cur_position}, {$int_num_result}";
	$rs = $modx->db->select('*','[+prefix+]manager_log',$where,$orderby,$limit);
	if($total<1) {
		echo '<p>'.$_lang["mgrlog_emptysrch"].'</p>';
	} else {
		echo '<p>'.$_lang["mgrlog_sortinst"].'</p>';

		include_once(MODX_CORE_PATH . 'paginate.inc.php');
		// New instance of the Paging class, you can modify the color and the width of the html table
		$p = new Paging( $total, $int_cur_position, $int_num_result, $extargv );

		// Load up the 2 array in order to display result
		$array_paging = $p->getPagingArray();
		$array_row_paging = $p->getPagingRowArray();
		$current_row = $int_cur_position/$int_num_result;

		// Display the result as you like...
		echo "<p>". $_lang["paging_showing"]." ". $array_paging['lower'];
		echo " ". $_lang["paging_to"] . " ". $array_paging['upper'];
		echo " (". $array_paging['total'] . " " . $_lang["paging_total"] . ")<br />";
		$paging = $array_paging['first_link'] . $_lang["paging_first"] . (isset($array_paging['first_link']) ? "</a> " : " ");
		$paging .= $array_paging['previous_link'] . $_lang["paging_prev"] . (isset($array_paging['previous_link']) ? "</a> " : " ");
		$pagesfound = sizeof($array_row_paging);
		if($pagesfound>6) {
			$paging .= $array_row_paging[$current_row-2];
			$paging .= $array_row_paging[$current_row-1];
			$paging .= $array_row_paging[$current_row];
			$paging .= $array_row_paging[$current_row+1];
			$paging .= $array_row_paging[$current_row+2];
		} else {
			for( $i=0; $i<$pagesfound; $i++ ){
				$paging .= $array_row_paging[$i] ."&nbsp;";
			}
		}
		$paging .= $array_paging['next_link'] . $_lang["paging_next"] . (isset($array_paging['next_link']) ? "</a> " : " ") . " ";
		$paging .= $array_paging['last_link'] . $_lang["paging_last"] . (isset($array_paging['last_link']) ? "</a> " : " ") . "</p>";
		echo $paging;
		?>
		<script type="text/javascript" src="media/script/tablesort.js"></script>
		<table class="sortabletable rowstyle-even" id="table-1">
		<thead><tr>
			<th class="sortable"><b><?php echo $_lang["mgrlog_time"]; ?></b></th>
			<th class="sortable"><b><?php echo $_lang["mgrlog_action"]; ?></b></th>
			<th class="sortable"><b><?php echo $_lang["mgrlog_itemid"]; ?></b></th>
			<th class="sortable"><b><?php echo $_lang["mgrlog_username"]; ?></b></th>
		</tr></thead>
		<tbody>
		<?php
		// grab the entire log file...
		$tpl = <<< EOT
<tr class="[+class+]">
	<td>[+datetime+]</td>
	<td>[[+action+]] [+message+]</td>
	<td>[+title+]</td>
	<td><a href="index.php?a=13&searchuser=[+internalKey+]&itemname=0&log_submit=true">[+username+]</a></td>
</tr>
EOT;
		$logentries = array();
		$i = 0;
		while ($row = $modx->db->getRow($rs)):
			$row['itemname'] = htmlspecialchars($row['itemname'],ENT_QUOTES,$modx->config['modx_charset']);
			if(!preg_match('/^[0-9]+$/', $row['itemid']))
				$row['title'] = '<div style="text-align:center;">-</div>';
			elseif($row['action']==3||$row['action']==27||$row['action']==5)
				$row['title'] = $modx->parseText('<a href="index.php?a=3&amp;id=[+itemid+]">[[+itemid+]][+itemname+]</a>',$row);
			else
				$row['title'] = $modx->parseText('[[+itemid+]] [+itemname+]',$row);
			$row['class'] = $i % 2 ? 'even' : '';
			$row['datetime'] = $modx->toDateFormat($row['timestamp']+$server_offset_time);
			echo $modx->parseText($tpl,$row);
		$i++;
		endwhile;
		?>
	</tbody>
	</table>
	<?php
	echo $paging;
	}
	?>
	</div>
</div>
	<?php
	global $action; $action = 1;
} else {
    echo $_lang["mgrlog_noquery"];
}
endif;



function array_unique_multi($array, $checkKey) {
	// Use the builtin if we're not a multi-dimensional array
	if (!is_array(current($array)) || empty($checkKey)) return array_unique($array);

	$ret = array();
	$checkValues = array(); // contains the unique key Values
	foreach ($array as $key => $current) {
		if (in_array($current[$checkKey], $checkValues)) continue; // duplicate

		$checkValues[] = $current[$checkKey];
		$ret[$key] = $current;
	}
	return $ret;
}

function record_sort($array, $key) {
	$hash = array();
	foreach ($array as $k => $v) $hash[$k] = $v[$key];

	natsort($hash);

	$records = array();
	foreach ($hash as $k => $row)
		$records[$k] = $array[$k];

	return $records;
}

// function to check date and convert to us date
function convertdate($date) {
	global $_lang, $modx;
	$timestamp = $modx->toTimeStamp($date);
	return $timestamp;
}
