<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
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

$subject = $modx->db->escape($_REQUEST['messagesubject']);
if($subject=='') $subject="(no subject)";
$message = $modx->db->escape($_REQUEST['messagebody']);
if($message=='') $message="(no message)";
$postdate = time();
$type = 'Message';

$rs = $modx->db->select('fullname,email', '[+prefix+]user_attributes', "internalKey='$sender'");
$from = $modx->db->getRow($rs);

if($sendto=='u') {
	if($recipient==0) {
		$e->setError(13);
		$e->dumpError();
	}
	$private = 1;
	$fields = compact('recipient','sender','subject','message','postdate','type','private');
	send_pm($fields, $from);
}

if($sendto=='g') {
	if($groupid==0) {
		$e->setError(14);
		$e->dumpError();
	}
	$rs = $modx->db->select('internalKey', '[+prefix+]user_attributes', "role={$groupid} AND blocked=0");
	$private = 0;
	while($row=$modx->db->getRow($rs))
	if($row['internalKey']!=$sender) {
		$recipient = $row['internalKey'];
		$fields = compact('recipient','sender','subject','message','postdate','type','private');
		send_pm($fields, $from);
	}
}

if($sendto=='a') {
	$rs = $modx->db->select('id','[+prefix+]manager_users');
	$private = 0;
	while($row=$modx->db->getRow($rs))
	{
		if($row['id']!=$sender) {
			$recipient = $row['id'];
			$fields = compact('recipient','sender','subject','message','postdate','type','private');
			send_pm($fields, $from);
		}
	}
}

header("Location: index.php?a=10");


function pm2email($from,$fields)
{
	global $modx;
	if($modx->config['pm2email'] == '0') return;
	
	extract($fields,EXTR_PREFIX_ALL,'f');
	
	$msg = $f_message ."\n\n----------------\nFrom [(site_name)]\n[(site_url)]manager/\n\n";
	$msg = $modx->mergeSettingsContent($msg);
	$params['from']     = $from['email'];
	$params['fromname'] = $from['fullname'];
	$params['subject']  = $f_subject;
	$params['sendto']   = $modx->db->getValue($modx->db->select('email', '[+prefix+]user_attributes', "internalKey='{$recipient}'"));
	$modx->sendmail($params,$msg);
	usleep(300000);
}

function send_pm($fields, $from)
{
	global $modx;
	
	if($modx->config['pm2email']=='1') pm2email($from,$fields);
	$fields['subject'] = encrypt($fields['subject']);
	$fields['message'] = encrypt($fields['message']);
	$rs = $modx->db->insert($fields,'[+prefix+]user_messages');
}

// http://d.hatena.ne.jp/hoge-maru/20120715/1342371992
function encrypt($plaintext, $key='modx')
{
	$len = strlen($plaintext);
	$enc = '';
	for($i = 0; $i < $len; $i++)
	{
		$asciin = ord($plaintext[$i]);
		$enc .= chr($asciin ^ ord($key[$i]));
	}
	$enc = base64_encode($enc);
	return $enc;
}
