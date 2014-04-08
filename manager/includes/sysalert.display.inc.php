<?php

	/**
	 *	System Alert Message Queue Display file
	 *	Written By Raymond Irving, April, 2005
	 *
	 *	Used to display system alert messages inside the browser
	 *
	 */

	$alerts = array();
	foreach($modx->SystemAlertMsgQueque as $_) {
		$alerts[] = $_;
	}
	if(0<count($alerts)) $sysMsgs = implode('<hr />',$alerts);
	else $sysMsgs = '';
	
	// reset message queque
	unset($_SESSION['SystemAlertMsgQueque']);
	$_SESSION['SystemAlertMsgQueque'] = array();
	$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

	if($sysMsgs!=='')
	{
		$tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/sysalert.tpl');
		$ph['alerts'] = $modx->db->escape($sysMsgs);
		$ph['title']  = $_lang['sys_alert'];
		echo $modx->parseText($tpl,$ph);
	}
?>