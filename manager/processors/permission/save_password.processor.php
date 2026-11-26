<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_password')) {
    alert()->setError(3);
    alert()->dumpError();
}

$uid = evo()->getLoginUserID();
$userinfo = evo()->getUserInfo($uid);
// パスワードは加工せずに受ける（前後の空白も文字のうち）
$password = post_textvar('userpassword', '');
$passwordError = $password === ''
    ? lang('password_required')
    : manager()->passwordError($password, [
        (string) ($userinfo['username'] ?? ''),
        (string) ($userinfo['email'] ?? ''),
        (string) ($userinfo['fullname'] ?? ''),
    ]);

if ($passwordError !== '') {
    $msg = '<p class="fail">' . $passwordError . '</p>';
} else {
    $hash = manager()->hashPassword($password);
    $rs = db()->update(
        db()->escape(['password' => $hash]),
        '[+prefix+]manager_users',
        sprintf("id='%d'", $uid)
    );
    if (!$rs) {
        $msg = '<p class="fail">' . lang('password_error_save') . '</p>';
    } else {
        $msg = '<p class="success">' . lang('change_password_success') . '</p>';
        // userpassword は平文ではなく保存したハッシュ（ユーザーの編集画面の同じイベントとは違う。assets/docs/password-policy.md）
        $tmp = [
            'userid' => $uid,
            'username' => $userinfo['username'],
            'userpassword' => $hash
        ];
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }
}
sessionv('*onetime_msg', $msg);
header('Location: index.php?a=28');
