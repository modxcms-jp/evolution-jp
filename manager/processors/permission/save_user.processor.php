<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

evo()->loadExtension('phpass');
include(__DIR__ . '/save_user.functions.php');

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

// verify admin security
if (!verifyPermission()) {
    // Check to see if user tried to spoof a "1" (admin) role
    webAlert('Illegal attempt to create/modify administrator by non-administrator!');
    exit;
}

if (postv('mode') == 11) { // new user
    if (userid_byname(postv('newusername', 'New User'))) {
        webAlert('User name is already in use!');
        exit;
    }
    if (userid_byemail(postv('email'))) {
        webAlert('Email is already in use!');
        exit;
    }
    if (postv('passwordgenmethod') === 'spec') {
        if (!postv('specifiedpassword')) {
            webAlert("You didn't specify a password for this user!");
            exit;
        }
        if (strlen(postv('specifiedpassword')) < 6) {
            webAlert('Password is too short!');
            exit;
        }
    } elseif (postv('passwordgenmethod') !== 'g') {
        webAlert('No password generation method specified!');
        exit;
    }
    newUser();
    return;
}

if (in_array(postv('mode'), array('12', '74'))) {
    if (!preg_match('@^[1-9][0-9]*$@', postv('userid'))) {
        webAlert('Missing user id!');
    }
    // check if the username already exist
    if (userid_byname(postv('newusername')) && userid_byname(postv('newusername')) != postv('userid')) {
        webAlert('User name is already in use!');
        exit;
    }

    // check if the email address already exists
    if (userid_byemail(postv('email')) && userid_byemail(postv('email')) != postv('userid')) {
        webAlert('Email is already in use!');
        exit;
    }

    // generate a new password for this user
    if (postv('newpassword') == 1) {
        if (postv('passwordgenmethod') === 'spec') {
            if (!postv('specifiedpassword')) {
                webAlert("You didn't specify a password for this user!");
                exit;
            }
            if (strlen(postv('specifiedpassword')) < 6) {
                webAlert('Password is too short!');
                exit;
            }
        } elseif (postv('passwordgenmethod') !== 'g') {
            webAlert('No password generation method specified!');
            exit;
        }
    }
    if (evo()->session_var('mgrRole') != 1 && role_byuserid(postv('id')) == 1) {
        webAlert('You cannot alter an administrative user.');
        exit;
    }
    updateUser();
    return;
}

webAlert('Unauthorized access');

