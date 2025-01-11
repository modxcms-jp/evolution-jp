<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_web_user')) {
    alert()->setError(3);
    alert()->dumpError();
}
global $_style;
if (preg_match('@^[0-9]+$@', postv('id'))) {
    $id = postv('id');
}
$oldusername = postv('oldusername');
$newusername = trim(postv('newusername', 'New User'));
$fullname = db()->escape(postv('fullname'));
$genpassword = postv('newpassword');
$passwordgenmethod = postv('passwordgenmethod');
$passwordnotifymethod = postv('passwordnotifymethod');
$specifiedpassword = postv('specifiedpassword');
$email = db()->escape(postv('email'));
$oldemail = postv('oldemail');
$phone = db()->escape(postv('phone'));
$mobilephone = db()->escape(postv('mobilephone'));
$fax = db()->escape(postv('fax'));
$dob = postv('dob') ? $modx->toTimeStamp(postv('dob')) : 0;
$country = postv('country');
$street = db()->escape(postv('street'));
$city = db()->escape(postv('city'));
$state = db()->escape(postv('state'));
$zip = db()->escape(postv('zip'));
$gender = postv('gender') ? postv('gender') : 0;
$photo = db()->escape(postv('photo'));
$comment = db()->escape(postv('comment'));
$role = postv('role') ? postv('role') : 0;
$failedlogincount = postv('failedlogincount') ? postv('failedlogincount') : 0;
$blocked = postv('blocked') ? postv('blocked') : 0;
$blockeduntil = postv('blockeduntil') ? $modx->toTimeStamp(postv('blockeduntil')) : 0;
$blockedafter = postv('blockedafter') ? $modx->toTimeStamp(postv('blockedafter')) : 0;
$user_groups = postv('user_groups');

// verify password
if ($passwordgenmethod === 'spec' && postv('specifiedpassword') != postv('confirmpassword')) {
    webAlert('Password typed is mismatched');
    exit;
}

// verify email
if (!isset($modx->config['required_email_wuser'])) {
    $modx->config['required_email_wuser'] = 1;
}

if ($modx->config['required_email_wuser']) {
    if ($email == '' || !preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,20}$/i', $email)) {
        webAlert("E-mail address doesn't seem to be valid!");
        exit;
    }
}

switch (postv('mode')) {
    case '87': // new user
        // check if this user name already exist
        $rs = db()->select(
            'id',
            '[+prefix+]web_users',
            sprintf("username='%s'", db()->escape($newusername))
        );
        if (db()->count($rs)) {
            webAlert('User name is already in use!');
            exit;
        }

        // check if the email address already exist
        if ($modx->config['required_email_wuser']) {
            $rs = db()->select(
                'id',
                '[+prefix+]web_user_attributes',
                sprintf("email='%s'", $email)
            );
            if (!$rs) {
                webAlert(sprintf('An error occurred while attempting to retrieve all users with email %s.', $email));
                exit;
            }
            if (db()->count($rs)) {
                webAlert("Email is already in use!");
                exit;
            }
        }

        // generate a new password for this user
        if ($specifiedpassword != '' && $passwordgenmethod === 'spec') {
            if (strlen($specifiedpassword) < 6) {
                webAlert('Password is too short!');
                exit;
            }
            $newpassword = $specifiedpassword;
        } elseif ($specifiedpassword == '' && $passwordgenmethod === 'spec') {
            webAlert("You didn't specify a password for this user!");
            exit;
        } elseif ($passwordgenmethod === 'g') {
            $newpassword = generate_password(8);
        } else {
            webAlert('No password generation method specified!');
            exit;
        }

        // invoke OnBeforeWUsrFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => null
        );
        evo()->invokeEvent('OnBeforeWUsrFormSave', $tmp);

        // create the user account
        $fields = [];
        $fields['username'] = $newusername;
        $fields['password'] = md5($newpassword);
        $internalKey = db()->insert(
            $fields,
            '[+prefix+]web_users'
        );
        if (!$internalKey) {
            webAlert("An error occurred while attempting to save the user.");
            exit;
        }

        $fields = compact(
            'internalKey',
            'fullname',
            'role',
            'email',
            'phone',
            'mobilephone',
            'fax',
            'zip',
            'street',
            'city',
            'state',
            'country',
            'gender',
            'dob',
            'photo',
            'comment',
            'blocked',
            'blockeduntil',
            'blockedafter'
        );
        $rs = db()->insert(
            $fields,
            '[+prefix+]web_user_attributes'
        );
        if (!$rs) {
            webAlert("An error occurred while attempting to save the user's attributes.");
            exit;
        }

        // Save User Settings
        saveUserSettings($internalKey);

        // invoke OnWebSaveUser event
        $tmp = array(
            'mode' => 'new',
            'userid' => $internalKey,
            'username' => $newusername,
            'userpassword' => $newpassword,
            'useremail' => $email,
            'userfullname' => $fullname
        );
        evo()->invokeEvent('OnWebSaveUser', $tmp);

        // invoke OnWUsrFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $internalKey
        );
        evo()->invokeEvent('OnWUsrFormSave', $tmp);

        /*******************************************************************************/
        // put the user in the user_groups he/ she should be in
        // first, check that up_perms are switched on!
        if ($modx->config['use_udperms'] == 1) {
            if ($user_groups) {
                $field = [];
                foreach ($user_groups as $user_group) {
                    $field['webgroup'] = (int)$user_group;
                    $field['webuser'] = $internalKey;
                    $rs = db()->insert(
                        $field,
                        '[+prefix+]web_groups'
                    );
                    if (!$rs) {
                        webAlert("An error occurred while attempting to add the user to a web group.");
                        exit;
                    }
                }
            }
        }
        // end of user_groups stuff!

        if ($modx->config['required_email_wuser'] && $passwordnotifymethod === 'e') {
            sendMailMessage($email, $newusername, $newpassword, $fullname);
            if (postv('stay') != '') {
                $a = (postv('stay') == '2') ? "88&id=" . $internalKey : '87';
                $header = "Location: index.php?a=" . $a . "&stay=" . postv('stay');
            } else {
                $header = 'Location: index.php?a=99';
            }
            header($header);
        } else {
            if (postv('stay') != '') {
                $a = (postv('stay') == '2') ? "88&id=" . $internalKey : '87';
                $stayUrl = "index.php?a=" . $a . "&stay=" . postv('stay');
            } else {
                $stayUrl = 'index.php?a=99';
            }

            include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
?>
            <h1><?= $_lang['web_user_title'] ?></h1>

            <div id="actions">
                <ul class="actionButtons">
                    <li class="mutate"><a href="<?= $stayUrl ?>"><img
                                src="<?= $_style['icons_save'] ?>" /> <?= $_lang['close'] ?></a></li>
                </ul>
            </div>

            <div class="section">
                <div class="sectionHeader"><?= $_lang['web_user_title'] ?></div>
                <div class="sectionBody">
                    <div id="disp">
                        <p>
                            <?= sprintf($_lang['password_msg'], $newusername, $newpassword) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php
            include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        }
        break;
    case '88': // edit user
        // generate a new password for this user
        if ($genpassword == 1) {
            if ($specifiedpassword != '' && $passwordgenmethod === 'spec') {
                if (strlen($specifiedpassword) < 6) {
                    webAlert("Password is too short!");
                    exit;
                }

                $newpassword = $specifiedpassword;
            } elseif ($specifiedpassword == '' && $passwordgenmethod === 'spec') {
                webAlert("You didn't specify a password for this user!");
                exit;
            } elseif ($passwordgenmethod === 'g') {
                $newpassword = generate_password(8);
            } else {
                webAlert("No password generation method specified!");
                exit;
            }
            $updatepasswordsql = ", password=MD5('" . db()->escape($newpassword) . "') ";
        }
        if ($modx->config['required_email_wuser'] && $passwordnotifymethod === 'e') {
            sendMailMessage($email, $newusername, $newpassword, $fullname);
        }

        // check if the username already exist
        $rs = db()->select(
            'id',
            '[+prefix+]web_users',
            sprintf("username='%s'", db()->escape($newusername))
        );
        if (!$rs) {
            webAlert("An error occurred while attempting to retrieve all users with username $newusername.");
            exit;
        }
        $limit = db()->count($rs);
        if ($limit > 0) {
            $row = db()->getRow($rs);
            if ($row['id'] != $id) {
                webAlert('User name is already in use!');
                exit;
            }
        }

        // check if the email address already exists
        if ($modx->config['required_email_wuser']) {
            $rs = db()->select(
                'internalKey',
                '[+prefix+]web_user_attributes',
                sprintf("email='%s'", $email)
            );
            if (!$rs) {
                webAlert("An error occurred while attempting to retrieve all users with email $email.");
                exit;
            }
            if (0 < db()->count($rs)) {
                $row = db()->getRow($rs);
                if ($row['internalKey'] != $id) {
                    webAlert("Email is already in use!");
                    exit;
                }
            }
        }

        // invoke OnBeforeWUsrFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnBeforeWUsrFormSave', $tmp);

        // update user name and password
        $sql = sprintf(
            "UPDATE %s SET username='%s'%s WHERE id='%s'",
            evo()->getFullTableName('web_users'),
            db()->escape($newusername),
            $updatepasswordsql ?? '',
            $id
        );
        $rs = db()->query($sql);
        if (!$rs) {
            webAlert("An error occurred while attempting to update the user's data.");
            exit;
        }

        $fields = compact(
            'fullname',
            'role',
            'email',
            'phone',
            'mobilephone',
            'fax',
            'zip',
            'street',
            'city',
            'state',
            'country',
            'gender',
            'dob',
            'photo',
            'comment',
            'failedlogincount',
            'blocked',
            'blockeduntil',
            'blockedafter'
        );
        $rs = db()->update(
            $fields,
            evo()->getFullTableName('web_user_attributes'),
            sprintf("internalKey='%s'", $id)
        );
        if (!$rs) {
            webAlert("An error occurred while attempting to update the user's attributes.");
            exit;
        }

        // Save User Settings
        saveUserSettings($id);

        // invoke OnWebSaveUser event
        $tmp = [
            'mode' => 'upd',
            'userid' => $id,
            'username' => $newusername,
            'userpassword' => $newpassword ?? '',
            'useremail' => $email,
            'userfullname' => $fullname,
            'oldusername' => ($oldusername != $newusername) ? $oldusername : '',
            'olduseremail' => ($oldemail != $email) ? $oldemail : ''
        ];
        evo()->invokeEvent('OnWebSaveUser', $tmp);

        // invoke OnWebChangePassword event
        if (!empty($updatepasswordsql)) {
            $tmp = array(
                'userid' => $id,
                'username' => $newusername,
                'userpassword' => $newpassword ?? ''
            );
        }
        evo()->invokeEvent('OnWebChangePassword', $tmp);

        // invoke OnWUsrFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnWUsrFormSave', $tmp);

        /*******************************************************************************/
        // put the user in the user_groups he/ she should be in
        // first, check that up_perms are switched on!
        if ($modx->config['use_udperms'] == 1) {
            // as this is an existing user, delete his/ her entries in the groups before saving the new groups
            $rs = db()->delete(evo()->getFullTableName('web_groups'), "webuser='" . $id . "'");
            if (!$rs) {
                webAlert("An error occurred while attempting to delete previous user_groups entries.");
                exit;
            }
            if ($user_groups) {
                foreach ($user_groups as $group) {
                    $sql = sprintf(
                        "INSERT INTO %s (webgroup, webuser) VALUES('%s', '%s')",
                        evo()->getFullTableName('web_groups'),
                        $group,
                        $id
                    );
                    if (!db()->query($sql)) {
                        webAlert(
                            sprintf(
                                'An error occurred while attempting to add the user to a user_group.<br />%s;',
                                $sql
                            )
                        );
                        exit;
                    }
                }
            }
        }
        // end of user_groups stuff!
        /*******************************************************************************/

        if ($genpassword == 1 && $passwordnotifymethod === 's') {
            if (postv('stay') != '') {
                $a = (postv('stay') == '2') ? "88&id=" . $id : "87";
                $stayUrl = "index.php?a=" . $a . "&stay=" . postv('stay');
            } else {
                $stayUrl = "index.php?a=99";
            }

            include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        ?>
            <h1><?= $_lang['web_user_title'] ?></h1>

            <div id="actions">
                <ul class="actionButtons">
                    <li class="mutate"><a href="<?= $stayUrl ?>"><img
                                src="<?= $_style["icons_save"] ?>" /> <?= $_lang['close'] ?></a></li>
                </ul>
            </div>

            <div class="section">
                <div class="sectionHeader"><?= $_lang['web_user_title'] ?></div>
                <div class="sectionBody">
                    <div id="disp">
                        <p><?= sprintf($_lang["password_msg"], $newusername, $newpassword) ?></p>
                    </div>
                </div>
            </div>
<?php

            include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        } else {
            if (postv('stay') != '') {
                $a = (postv('stay') == '2') ? "88&id=$id" : "87";
                $header = "Location: index.php?a=" . $a . "&stay=" . postv('stay');
            } else {
                $header = "Location: index.php?a=99";
            }
            header($header);
        }
        break;
    default:
        webAlert('Unauthorized access');
        exit;
}

// Send an email to the user
function sendMailMessage($email, $uid, $pwd, $ufn)
{
    global $modx, $websignupemail_message;
    global $emailsender;
    global $site_name, $site_url;
    $message = sprintf($websignupemail_message, $uid, $pwd); // use old method
    // replace placeholders
    $ph['username'] = $uid;
    $ph['uid'] = $uid;
    $ph['fullname'] = $ufn;
    $ph['ufn'] = $ufn;
    $ph['manager_email'] = $emailsender;
    $ph['saddr'] = $emailsender;
    $ph['pwd'] = $pwd;
    $ph['password'] = $pwd;
    $ph['sname'] = $site_name;
    $ph['semail'] = $emailsender;
    $ph['surl'] = $site_url;
    $message = $modx->parseText($message, $ph);
    $message = $modx->mergeSettingsContent($message);
    $message = $modx->mergeChunkContent($message);
    $message = $modx->rewriteUrls($message);

    if ($modx->sendmail($email, $message) === false) //ignore mail errors in this cas
    {
        webAlert("Error while sending mail to " . $email);
        exit;
    }
}

// Save User Settings
function saveUserSettings($id)
{
    global $modx;

    $settings = array(
        'login_home',
        'allowed_ip',
        'allowed_days'
    );

    foreach ($settings as $name) {
        db()->delete('[+prefix+]web_user_settings', "webuser='" . $id . "' and setting_name='" . $name . "'");
        $value = postv($name);
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        if ($value != '') {
            $field = [];
            $field['webuser'] = $id;
            $field['setting_name'] = $name;
            $field['setting_value'] = db()->escape($value);
            db()->insert($field, evo()->getFullTableName('web_user_settings'));
        }
    }
}

// converts date format dd-mm-yyyy to php date

// Web alert -  sends an alert to web browser
function webAlert($msg)
{
    global $id, $modx;
    $mode = postv('mode');
    $url = "index.php?a=" . $mode . ($mode == '88' ? "&id=" . $id : '');
    manager()->saveFormValues($mode);
    $modx->webAlertAndQuit($msg, $url);
}

// Generate password
function generate_password($length = 10)
{
    return substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}
