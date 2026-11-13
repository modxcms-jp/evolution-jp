<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_password')) {
    alert()->setError(3);
    alert()->dumpError();
}

$password = post_strvar('pass1');
$passwordError = manager()->passwordError($password);

if ($password !== post_strvar('pass2')) {
    $msg = '<p class="fail">' . lang('password_error_mismatch') . '</p>';
} elseif ($passwordError !== '') {
    $msg = '<p class="fail">' . $passwordError . '</p>';
} else {
    $uid = evo()->getLoginUserID();
    $rs = db()->update(
        db()->escape(['password' => manager()->hashPassword($password)]),
        '[+prefix+]manager_users',
        sprintf("id='%d'", $uid)
    );
    if (!$rs) {
        $msg = '<p class="fail">' . lang('password_error_save') . '</p>';
    } else {
        $userinfo = evo()->getUserInfo($uid);
        $msg = '<p class="success">' . lang('change_password_success') . '</p>';
        $tmp = [
            'userid' => $uid,
            'username' => $userinfo['username'],
            'userpassword' => $userinfo['password']
        ];
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }
}
sessionv('*onetime_msg', $msg);
header('Location: index.php?a=28');
