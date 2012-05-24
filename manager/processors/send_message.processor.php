<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('messages')) {
	$e->setError(3);
	$e->dumpError();
}

//$db->debug = true;

if(!isset($modx->config['pm2email'])) $modx->config['pm2email'] == '1';

$sendto    = $_REQUEST['sendto'];
$recipient = $_REQUEST['user'];
$groupid   = $_REQUEST['group'];

$sender = $modx->getLoginUserID();

$subject = addslashes($_REQUEST['messagesubject']);
if($subject=='') $subject="(no subject)";
$message = addslashes($_REQUEST['messagebody']);
if($message=='') $message="(no message)";
$postdate = time();
$type = 'Message';

$tbl_user_messages   = $modx->getFullTableName('user_messages');
$tbl_user_attributes = $modx->getFullTableName('user_attributes');

$rs = $modx->db->select('fullname,email', $tbl_user_attributes, "internalKey='$sender'");
$from = $modx->db->getRow($rs);

if($sendto=='u') {
	if($recipient==0) {
		$e->setError(13);
		$e->dumpError();
	}
	$private = 1;
	$fields = compact('recipient','sender','subject','message','postdate','type','private');
	$rs = $modx->db->insert($fields,$tbl_user_messages);
	if($rs) pm2email($from,$fields);
}

if($sendto=='g') {
	if($groupid==0) {
		$e->setError(14);
		$e->dumpError();
	}
	$rs = $modx->db->select('internalKey', $tbl_user_attributes, "role={$groupid} AND blocked=0");
	$private = 0;
	while($row=$modx->db->getRow($rs))
	if($row['internalKey']!=$sender) {
		$recipient = $row['internalKey'];
		$fields = compact('recipient','sender','subject','message','postdate','type','private');
		$rs = $modx->db->insert($fields,$tbl_user_messages);
		if($rs) pm2email($from,$fields);
	}
}

if($sendto=='a') {
	$tbl_manager_users = $modx->getFullTableName('manager_users');
	$rs = $modx->db->select('id',$tbl_manager_users);
	$private = 0;
	while($row=$modx->db->getRow($rs))
	{
		if($row['id']!=$sender) {
			$recipient = $row['id'];
			$fields = compact('recipient','sender','subject','message','postdate','type','private');
			$rs = $modx->db->insert($fields,$tbl_user_messages);
			if($rs) pm2email($from,$fields);
		}
	}
}

header("Location: index.php?a=10");


function pm2email($from,$fields)
{
	global $modx;
	if($modx->config['pm2email'] == '0') return;
	
	$tbl_user_attributes = $modx->getFullTableName('user_attributes');
	extract($fields);
	
	$msg = $message ."\n\n----------------\nFrom [(site_name)]\n[(site_url)]manager/\n\n";
	$msg = $modx->mergeSettingsContent($msg);
	$params['from']     = $from['email'];
	$params['fromname'] = $from['fullname'];
	$params['subject']  = $subject;
	$params['sendto']   = $modx->db->getValue($modx->db->select('email', $tbl_user_attributes, "internalKey='$recipient'"));
	$modx->sendmail($params,$msg);
	usleep(300000);
}