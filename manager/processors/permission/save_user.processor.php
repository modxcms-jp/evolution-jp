<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
include(__DIR__ . '/save_user.functions.php');
if (!canSaveUser()) {
    alert()->setError(3);
    alert()->dumpError();
}

// verify email
if (!validEmail()) {
    webAlert("E-mail address doesn't seem to be valid!");
    exit;
}

// verify role
if (!validRole()) {
    webAlert('Invalid role');
    exit;
}

// 解釈できない日付は、知らせずに 0 にせず差し戻す
if (!validDates()) {
    webAlert(lang('mgrlog_dateinvalid'));
    exit;
}

// verify admin security
if (!verifyPermission()) {
    // Check to see if user tried to spoof a "1" (admin) role
    webAlert('Illegal attempt to create/modify administrator by non-administrator!');
    exit;
}

$mode = saveUserMode();

if ($mode === 'new') { // new user
    if (trim(userformv_str('newusername')) === '') {
        webAlert('Missing user name!');
        exit;
    }
    if (userid_byname(userformv_str('newusername'))) {
        webAlert('User name is already in use!');
        exit;
    }
    if (userid_byemail(post_strvar('email'))) {
        webAlert('Email is already in use!');
        exit;
    }
    // 新規はロールを選んでいること（空・存在しないロールは、ロール 0 の権限なしのユーザーになってしまう）
    if (!roleExists(userformv_int('role'))) {
        webAlert(lang('user_role_required'));
        exit;
    }
    $passwordError = newPasswordError();
    if ($passwordError !== '') {
        webAlert($passwordError);
        exit;
    }
    newUser();
    return;
}

if (in_array($mode, ['edit', 'profile'], true)) {
    $userid = post_intvar('userid');
    if (!$userid) {
        webAlert('Missing user id!');
        exit;
    }
    $username = userformv_str('newusername');
    if (trim($username) === '') {
        webAlert('Missing user name!');
        exit;
    }
    // check if the username already exist
    $found = userid_byname($username);
    if ($found && (int)$found !== $userid) {
        webAlert('User name is already in use!');
        exit;
    }

    // check if the email address already exists
    $found = userid_byemail(post_strvar('email'));
    if ($found && (int)$found !== $userid) {
        webAlert('Email is already in use!');
        exit;
    }

    // パスワードを変えるときだけ、新しいパスワードを確かめる
    if (changesPassword()) {
        $passwordError = newPasswordError();
        if ($passwordError !== '') {
            webAlert($passwordError);
            exit;
        }
    }
    if (!manager()->isAdmin() && (int)role_byuserid($userid) === 1) {
        webAlert('You cannot alter an administrative user.');
        exit;
    }
    updateUser();
    return;
}

webAlert('Unauthorized access');

