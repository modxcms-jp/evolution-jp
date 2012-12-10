<?php

	/**
	 *	System Alert Message Queue Display file
	 *	Written By Raymond Irving, April, 2005
	 *
	 *	Used to display system alert messages inside the browser
	 *
	 */

	$sysMsgs = '';
	foreach($SystemAlertMsgQueque as $_) {
		$sysMsgs .= $_.'<hr />';
	}
	// reset message queque
	unset($_SESSION['SystemAlertMsgQueque']);
	$_SESSION['SystemAlertMsgQueque'] = array();
	$SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

	if($sysMsgs!='')
	{
?>
<script type="text/javascript">
// <![CDATA[
$j(function() {
	jAlert('<?php echo $modx->db->escape($sysMsgs);?>','<?php echo $_lang['sys_alert'];?>');
});
// ]]>
</script>
<?php
	}
?>