<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

if(!$modx->hasPermission('messages')) return;

$tbl_user_messages = $modx->getFullTableName('user_messages');
$uid = $modx->getLoginUserID();

$rs = $modx->db->select('count(id)', $tbl_user_messages, "recipient='{$uid}' and messageread=0");
$nrnewmessages = $modx->db->getValue($rs);
$rs = $modx->db->select('count(id)', $tbl_user_messages, "recipient='{$uid}'");
$nrtotalmessages = $modx->db->getValue($rs);

// ajax response
if (isset($_POST['updateMsgCount'])) {
	echo "{$nrnewmessages},{$nrtotalmessages}";
	exit();
}
