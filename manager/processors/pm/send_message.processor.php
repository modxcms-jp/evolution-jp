<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('messages')) {
    alert()->setError(3);
    alert()->dumpError();
}

$rs = db()->select(
    'fullname,email',
    '[+prefix+]user_attributes',
    where('internalKey', '=', evo()->getLoginUserID())
);
$from = db()->getRow($rs);

$sendto = request_choicevar('sendto', ['u', 'g', 'a'], '');
$subject = request_strvar('messagesubject');
$message = request_strvar('messagebody');

if ($sendto === 'u') {
    if (!request_intvar('user')) {
        alert()->setError(13);
        alert()->dumpError();
    }
    send_pm(
        [
            'recipient' => request_intvar('user'),
            'sender' => evo()->getLoginUserID(),
            'subject' => $subject,
            'message' => $message,
            'postdate' => request_time(),
            'type' => 'Message',
            'private' => 1
        ],
        $from
    );
}

if ($sendto === 'g') {
    if (!request_intvar('group')) {
        alert()->setError(14);
        alert()->dumpError();
    }
    $rs = db()->select(
        'internalKey',
        '[+prefix+]user_attributes',
        [
            where('role', '=', request_intvar('group')),
            'AND blocked=0'
        ]
    );
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] == evo()->getLoginUserID()) {
            continue;
        }
        send_pm(
            [
                'recipient' => $row['internalKey'],
                'sender' => evo()->getLoginUserID(),
                'subject' => $subject,
                'message' => $message,
                'postdate' => request_time(),
                'type' => 'Message',
                'private' => 0
            ],
            $from
        );
    }
}

if ($sendto === 'a') {
    $rs = db()->select('id', '[+prefix+]manager_users');
    while ($row = db()->getRow($rs)) {
        if ($row['id'] == evo()->getLoginUserID()) {
            continue;
        }
        send_pm(
            [
                'recipient' => $row['id'],
                'sender' => evo()->getLoginUserID(),
                'subject' => $subject,
                'message' => $message,
                'postdate' => request_time(),
                'type' => 'Message',
                'private' => 0
            ],
            $from
        );
    }
}

header("Location: index.php?a=10");


function pm2email($from, $fields)
{
    global $modx;
    if (evo()->config('pm2email', 0) == 0) {
        return;
    }

    $msg = array_get($fields, 'message') . "\n\n----------------\nFrom [(site_name)]\n[(site_url)]manager/\n\n";
    $msg = evo()->mergeSettingsContent($msg);
    $params['from'] = $from['email'];
    $params['fromname'] = $from['fullname'];
    $params['subject'] = array_get($fields, 'subject');
    $params['type'] = 'text';
    $params['sendto'] = db()->getValue(
        db()->select(
            'email',
            '[+prefix+]user_attributes',
            where('internalKey', '=', array_get($fields, 'recipient'))
        )
    );
    $modx->sendmail($params, $msg);
    usleep(300000);
}

function send_pm($fields, $from)
{
    if (evo()->config('pm2email', 0) == 1) {
        pm2email($from, $fields);
    }
    $fields['subject'] = encrypt($fields['subject']);
    $fields['message'] = encrypt($fields['message']);
    db()->insert(
        db()->escape($fields),
        '[+prefix+]user_messages'
    );
}

// http://d.hatena.ne.jp/hoge-maru/20120715/1342371992
function encrypt($plaintext, $key = 'modx')
{
    $len = strlen($plaintext);
    $enc = '';
    for ($i = 0; $i < $len; $i++) {
        $enc .= chr(ord($plaintext[$i]) ^ (isset($key[$i]) ? ord($key[$i]) : 0));
    }
    return base64_encode($enc);
}
