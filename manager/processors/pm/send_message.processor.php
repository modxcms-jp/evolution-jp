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

if (anyv('sendto') === 'u') {
    if (anyv('user') == 0) {
        alert()->setError(13);
        alert()->dumpError();
    }
    send_pm(
        array(
            'recipient'=>anyv('user'),
            'sender'=>evo()->getLoginUserID(),
            'subject'=>anyv('messagesubject', '(no subject)'),
            'message'=>anyv('messagebody', '(no message)'),
            'postdate'=>request_time(),
            'type'=>'Message',
            'private'=>1
        ),
        $from
    );
}

if (anyv('sendto') === 'g') {
    if (anyv('group') == 0) {
        alert()->setError(14);
        alert()->dumpError();
    }
    $rs = db()->select(
        'internalKey',
        '[+prefix+]user_attributes',
        array(
            where('role', '=', anyv('group')),
            'AND blocked=0'
        )
    );
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] == evo()->getLoginUserID()) {
            continue;
        }
        send_pm(
            array(
                'recipient' => $row['internalKey'],
                'sender' => evo()->getLoginUserID(),
                'subject' => anyv('messagesubject', '(no subject)'),
                'message' => anyv('messagebody', '(no message)'),
                'postdate' => request_time(),
                'type' => 'Message',
                'private' => 0
            ),
            $from
        );
    }
}

if (anyv('sendto') === 'a') {
    $rs = db()->select('id', '[+prefix+]manager_users');
    while ($row = db()->getRow($rs)) {
        if ($row['id'] == evo()->getLoginUserID()) {
            continue;
        }
        send_pm(
            array(
                'recipient' => $row['id'],
                'sender' => evo()->getLoginUserID(),
                'subject' => anyv('messagesubject', '(no subject)'),
                'message' => anyv('messagebody', '(no message)'),
                'postdate' => request_time(),
                'type' => 'Message',
                'private' => 0
            ),
            $from
        );
    }
}

header("Location: index.php?a=10");


function pm2email($from, $fields) {
    global $modx;
    if (evo()->config('pm2email', 0) == 0) {
        return;
    }

    extract($fields, EXTR_PREFIX_ALL, 'f');

    $msg = array_get($fields, 'message') . "\n\n----------------\nFrom [(site_name)]\n[(site_url)]manager/\n\n";
    $msg = evo()->mergeSettingsContent($msg);
    $params['from'] = $from['email'];
    $params['fromname'] = $from['fullname'];
    $params['subject'] = array_get($fields, 'subject');
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

function send_pm($fields, $from) {
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
function encrypt($plaintext, $key = 'modx') {
    $len = strlen($plaintext);
    $enc = '';
    for ($i = 0; $i < $len; $i++) {
        $enc .= chr(ord($plaintext[$i]) ^ ord($key[$i]));
    }
    return base64_encode($enc);
}
