<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
include(__DIR__ . '/save_user.functions.php');
if (!canSaveUser()) {
    alert()->setError(3);
    alert()->dumpError();
}

evo()->loadExtension('phpass');

// verify password
if (!confirmPassword()) {
    webAlert('Password typed is mismatched');
    exit;
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
    if (post_strvar('passwordgenmethod') === 'spec') {
        if (!post_strvar('specifiedpassword')) {
            webAlert("You didn't specify a password for this user!");
            exit;
        }
        if (strlen(post_strvar('specifiedpassword')) < 6) {
            webAlert('Password is too short!');
            exit;
        }
    } elseif (post_strvar('passwordgenmethod') !== 'g') {
        webAlert('No password generation method specified!');
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

    // generate a new password for this user
    if (post_intvar('newpassword') === 1) {
        if (post_strvar('passwordgenmethod') === 'spec') {
            if (!post_strvar('specifiedpassword')) {
                webAlert("You didn't specify a password for this user!");
                exit;
            }
            if (strlen(post_strvar('specifiedpassword')) < 6) {
                webAlert('Password is too short!');
                exit;
            }
        } elseif (post_strvar('passwordgenmethod') !== 'g') {
            webAlert('No password generation method specified!');
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

