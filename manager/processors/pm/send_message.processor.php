<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('messages')) {
    alert()->setError(3);
    alert()->dumpError();
}

//$db->debug = true;

if (!isset($modx->config['pm2email'])) {
    $modx->config['pm2email'] == '1';
}

$sendto = $_REQUEST['sendto'];
$recipient = $_REQUEST['user'];
$groupid = $_REQUEST['group'];

$sender = evo()->getLoginUserID();

$subject = db()->escape($_REQUEST['messagesubject']);
if ($subject == '') {
    $subject = "(no subject)";
}
$message = db()->escape($_REQUEST['messagebody']);
if ($message == '') {
    $message = "(no message)";
}
$postdate = time();
$type = 'Message';

$rs = db()->select('fullname,email', '[+prefix+]user_attributes', "internalKey='$sender'");
$from = db()->getRow($rs);

if ($sendto == 'u') {
    if ($recipient == 0) {
        alert()->setError(13);
        alert()->dumpError();
    }
    $private = 1;
    $fields = compact('recipient', 'sender', 'subject', 'message', 'postdate', 'type', 'private');
    send_pm($fields, $from);
}

if ($sendto === 'g') {
    if ($groupid == 0) {
        alert()->setError(14);
        alert()->dumpError();
    }
    $rs = db()->select('internalKey', '[+prefix+]user_attributes', "role={$groupid} AND blocked=0");
    $private = 0;
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] != $sender) {
            $recipient = $row['internalKey'];
            $fields = compact('recipient', 'sender', 'subject', 'message', 'postdate', 'type', 'private');
            send_pm($fields, $from);
        }
    }
}

if ($sendto === 'a') {
    $rs = db()->select('id', '[+prefix+]manager_users');
    $private = 0;
    while ($row = db()->getRow($rs)) {
        if ($row['id'] != $sender) {
            $recipient = $row['id'];
            $fields = compact('recipient', 'sender', 'subject', 'message', 'postdate', 'type', 'private');
            send_pm($fields, $from);
        }
    }
}

header("Location: index.php?a=10");


function pm2email($from, $fields) {
    global $modx;
    if ($modx->config['pm2email'] == '0') {
        return;
    }

    extract($fields, EXTR_PREFIX_ALL, 'f');

    $msg = $f_message . "\n\n----------------\nFrom [(site_name)]\n[(site_url)]manager/\n\n";
    $msg = $modx->mergeSettingsContent($msg);
    $params['from'] = $from['email'];
    $params['fromname'] = $from['fullname'];
    $params['subject'] = $f_subject;
    $params['sendto'] = db()->getValue(db()->select('email', '[+prefix+]user_attributes',
        "internalKey='{$recipient}'"));
    $modx->sendmail($params, $msg);
    usleep(300000);
}

function send_pm($fields, $from) {
    global $modx;

    if ($modx->config['pm2email'] == '1') {
        pm2email($from, $fields);
    }
    $fields['subject'] = encrypt($fields['subject']);
    $fields['message'] = encrypt($fields['message']);
    $rs = db()->insert($fields, '[+prefix+]user_messages');
}

// http://d.hatena.ne.jp/hoge-maru/20120715/1342371992
function encrypt($plaintext, $key = 'modx') {
    $len = strlen($plaintext);
    $enc = '';
    for ($i = 0; $i < $len; $i++) {
        $asciin = ord($plaintext[$i]);
        $enc .= chr($asciin ^ ord($key[$i]));
    }
    $enc = base64_encode($enc);
    return $enc;
}
