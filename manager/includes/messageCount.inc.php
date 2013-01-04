<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('messages')) return;

$uid = $modx->getLoginUserID();

$rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}' and messageread=0");
$nrnewmessages = $modx->db->getValue($rs);

$rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}'");
$nrtotalmessages = $modx->db->getValue($rs);

// ajax response
if (isset($_POST['updateMsgCount'])) {
	echo "{$nrnewmessages},{$nrtotalmessages}";
	exit();
}
