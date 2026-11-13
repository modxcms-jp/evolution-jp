<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_password')) {
    alert()->setError(3);
    alert()->dumpError();
}

$password = post_strvar('pass1');

if ($password !== post_strvar('pass2')) {
    $msg = '<p class="fail">passwords don\'t match!</p>';
} elseif (empty($password)) {
    $msg = '<p class="fail">passwords don\'t empty!</p>';
} elseif (manager()->checkPasswordLength($password) === 'too_short') {
    $msg = '<p class="fail">Password is too short. Please specify a password of at least 6 characters.</p>';
} elseif (manager()->checkPasswordLength($password) === 'too_long') {
    $msg = '<p class="fail">Password is too long. Please specify a password of no more than 72 bytes.</p>';
} else {
    $uid = evo()->getLoginUserID();
    $f['password'] = manager()->hashPassword($password);
    $rs = db()->update(db()->escape($f), '[+prefix+]manager_users', sprintf("id='%d'", $uid));
    if (!$rs) {
        $msg = '<p class="fail">An error occured while attempting to save the new password.</p>';
    } else {
        $userinfo = evo()->getUserInfo($uid);
        $msg = '<p class="success">' . lang("change_password_success") . '</p>';
        $tmp = [
            "userid" => $uid,
            "username" => $userinfo['username'],
            "userpassword" => $userinfo['password']
        ];
        evo()->invokeEvent("OnManagerChangePassword", $tmp);
    }
}
$_SESSION['onetime_msg'] = $msg;
header("Location: index.php?a=28");
