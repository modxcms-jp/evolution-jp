<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

// Send an email to the user
function sendMailMessage($email, $uid, $pwd, $ufn)
{
    $message = evo()->mergeSettingsContent(
        evo()->parseText(
            evo()->config['signupemail_message'],
            array(
                'username' => $uid,
                'uid' => $uid,
                'password' => $pwd,
                'pwd' => $pwd,
                'fullname' => $ufn,
                'ufn' => $ufn,
                'site_name' => evo()->config['site_name'],
                'sname' => evo()->config['site_name'],
                'manager_email' => evo()->config['emailsender'],
                'saddr' => evo()->config['emailsender'],
                'semail' => evo()->config['emailsender'],
                'site_url' => MODX_SITE_URL,
                'surl' => MODX_SITE_URL . 'manager/'
            )
        )
    );
    if (!evo()->sendmail($email, $message)) {
        webAlert(sprintf('%s - %s', $email, lang('error_sending_email')));
        exit;
    }
}

// Save User Settings
function saveUserSettings($id)
{
    $ignore = array(
        'id',
        'oldusername',
        'oldemail',
        'newusername',
        'fullname',
        'newpassword',
        'newpasswordcheck',
        'passwordgenmethod',
        'passwordnotifymethod',
        'specifiedpassword',
        'confirmpassword',
        'email',
        'phone',
        'mobilephone',
        'fax',
        'dob',
        'country',
        'street',
        'city',
        'state',
        'zip',
        'gender',
        'photo',
        'comment',
        'role',
        'failedlogincount',
        'blocked',
        'blockeduntil',
        'blockedafter',
        'user_groups',
        'mode',
        'blockedmode',
        'stay',
        'save',
        'theme_refresher',
        'userid'
    );

    // determine which settings can be saved blank (based on 'default_{settingname}' POST checkbox values)
    $defaults = array(
        'manager_inline_style',
        'upload_images',
        'upload_media',
        'upload_files'
    );

    // get user setting field names
    $settings = array();
    $post = $_POST;
    foreach ($post as $k => $v) {
        if (in_array($k, $ignore)) {
            continue;
        } // ignore blacklist and empties
        if (is_array($v)) {
            $v = implode(',', $v);
        }
        if (!in_array($k, $defaults) && trim($v) == '') {
            continue;
        } // ignore blacklist and empties

        $settings[$k] = $v; // this value should be saved
    }
    foreach ($defaults as $k) {
        if (evo()->array_get($settings, 'default_' . $k) == 1) {
            unset($settings[$k]);
        }
        unset($settings['default_' . $k]);
    }

    db()->delete(evo()->getFullTableName('user_settings'), sprintf("user='%s'", $id));
    $savethese = array();
    foreach ($settings as $k => $v) {
        $v = db()->escape($v);
        $savethese[] = sprintf("(%s, '%s', '%s')", $id, $k, $v);
    }
    if (empty($savethese)) {
        return;
    }
    $sql = sprintf(
        'INSERT INTO %s (user, setting_name, setting_value) VALUES %s'
        , evo()->getFullTableName('user_settings')
        , implode(', ', $savethese)
    );
    if (!db()->query($sql)) {
        exit('Failed to update user settings!');
    }
    unset($_SESSION['openedArray']);
}

// Web alert -  sends an alert to web browser
function webAlert($msg)
{
    global $id;
    $mode = postv('mode');
    $url = 'index.php?a=' . $mode . ($mode == '12' ? "&id=" . $id : '');
    evo()->manager->saveFormValues($mode);
    evo()->webAlertAndQuit($msg, $url);
}

// Generate password
function generate_password($length = 10)
{
    static $password = null;
    if ($password) {
        return $password;
    }
    return substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}

function verifyPermission()
{
    if ($_SESSION['mgrRole'] == 1) {
        return true;
    }
    if (evo()->input_post('role') != 1) {
        return true;
    }
    if (!evo()->hasPermission('edit_role') || !evo()->hasPermission('save_role')) {
        return false;
    }
    if (!evo()->hasPermission('delete_role') || !evo()->hasPermission('new_role')) {
        return false;
    }
    return true;
}

function userid_byname($newusername)
{
    if (!$newusername) {
        return false;
    }
    $rs = db()->select(
        'id'
        , '[+prefix+]manager_users'
        , sprintf("username='%s'", db()->escape($newusername))
    );
    if (!db()->count($rs)) {
        return false;
    }
    return db()->getValue($rs);
}

function userid_byemail($email)
{
    if (!$email) {
        return false;
    }
    $rs = db()->select(
        'internalKey'
        , '[+prefix+]user_attributes'
        , sprintf("email='%s'", $email)
    );
    if (!db()->count($rs)) {
        return false;
    }
    return db()->getValue($rs);
}

function role_byuserid($userid)
{
    $rs = db()->select(
        'role'
        , '[+prefix+]user_attributes'
        , sprintf('internalKey=%s', $userid)
    );
    if (!db()->count($rs)) {
        return false;
    }
    return db()->getValue($rs);
}

function hasOldUserName()
{
    return (postv('oldusername') != postv('newusername', 'New User'));
}

function hasOldUserEmail()
{
    return (postv('oldemail') != postv('email'));
}

function newPassword()
{
    if (postv('passwordgenmethod') === 'spec') {
        return postv('specifiedpassword');
    }
    if (postv('passwordgenmethod') === 'g') {
        return generate_password(8);
    }
    webAlert('No password generation method specified!');
    exit;
}

function confirmPassword()
{
    if (postv('passwordgenmethod') !== 'spec') {
        return true;
    }
    if (postv('specifiedpassword') == postv('confirmpassword')) {
        return true;
    }
    return false;
}

function validEmail()
{
    if (!postv('email')) {
        return false;
    }
    if (!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,20}$/i", postv('email'))) {
        return false;
    }
    return true;
}

function validate()
{
    $fields = array(
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
    $rs = array();
    foreach ($fields as $field) {
        if (in_array($field, array('dob', 'gender', 'blocked', 'blockeduntil', 'blockedafter')) && !postv($field)) {
            $rs[$field] = 0;
            continue;
        }
        $rs[$field] = postv($field);
    }
    return $rs;
}

function newUser()
{
    // invoke OnBeforeUserFormSave event
    $tmp = array(
        'mode' => 'new',
        'id' => null
    );
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // build the SQL
    $internalKey = db()->insert(
        array('username' => db()->escape(postv('newusername', 'New User')))
        , '[+prefix+]manager_users'
    );
    if (!$internalKey) {
        webAlert('An error occurred while attempting to save the user.');
        exit;
    }
    db()->update(
        array('password' => evo()->phpass->HashPassword(newPassword()))
        , '[+prefix+]manager_users'
        , sprintf("id='%s'", $internalKey)
    );

    $field = validate();
    $field['internalKey'] = $internalKey;
    $rs = db()->insert(
        db()->escape($field)
        , '[+prefix+]user_attributes'
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to save the user's attributes.");
        exit;
    }

    // Save User Settings
    saveUserSettings($internalKey);

    // invoke OnManagerSaveUser event
    $tmp = array(
        'mode' => 'new',
        'userid' => $internalKey,
        'username' => postv('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail' => postv('email'),
        'userfullname' => postv('fullname'),
        'userroleid' => postv('role', 0)
    );
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnUserFormSave event
    $tmp = array(
        'mode' => 'new',
        'id' => $internalKey
    );
    evo()->invokeEvent('OnUserFormSave', $tmp);

    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        $user_groups = postv('user_groups');
        if ($user_groups) {
            foreach ($user_groups as $user_group) {
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    array('user_group' => $user_group, 'member' => $internalKey)
                    , '[+prefix+]member_groups'
                );
                if (!$rs) {
                    webAlert('An error occurred while attempting to add the user to a user_group.');
                    exit;
                }
            }
        }
    }
    // end of user_groups stuff!

    if (postv('stay') != '') {
        if (postv('stay') == '2') {
            $stayUrl = sprintf(
                'index.php?r=3&a=11&stay=%s&id=%s'
                , postv('stay')
                , $internalKey
            );
        } else {
            $stayUrl = 'index.php?r=3&a=11&stay=' . postv('stay');
        }
    } else {
        $stayUrl = 'index.php?r=3&a=75';
    }

    if (postv('passwordnotifymethod') === 'e') {
        sendMailMessage(
            postv('email')
            , postv('newusername', 'New User')
            , newPassword()
            , postv('fullname')
        );
        header('Location: ' . $stayUrl);
        exit;
    }

    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <h1><?php echo lang('user_title'); ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate"><a href="<?php echo $stayUrl ?>"><img
                        src="<?php echo style('icons_save') ?>"/> <?php echo lang('close'); ?>
                </a></li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?php echo lang('user_title'); ?></div>
        <div class="sectionBody">
            <div id="disp">
                <p>
                    <?php
                    echo sprintf(lang('password_msg'), postv('newusername', 'New User'), newPassword());
                    ?>
                </p>
            </div>
        </div>
    </div>
    <?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}

function updateUser()
{
    // invoke OnBeforeUserFormSave event
    $tmp = array(
        'mode' => 'upd',
        'id' => postv('userid')
    );
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // update user name and password
    $field = array('username' => postv('newusername', 'New User'));
    if (postv('newpassword') == 1) {
        $field['password'] = evo()->phpass->HashPassword(newPassword());
    }
    $rs = db()->update(
        db()->escape($field)
        , '[+prefix+]manager_users'
        , sprintf("id='%s'", postv('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's data.");
        exit;
    }

    $field = validate();
    $field['failedlogincount'] = postv('failedlogincount');
    $rs = db()->update(
        db()->escape($field)
        , '[+prefix+]user_attributes'
        , sprintf("internalKey='%s'", postv('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's attributes.");
        exit;
    }

    // Save user settings
    saveUserSettings(postv('userid'));

    // invoke OnManagerSaveUser event
    $tmp = array(
        'mode' => 'upd',
        'userid' => postv('userid'),
        'username' => postv('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail' => postv('email'),
        'userfullname' => postv('fullname'),
        'userroleid' => postv('role', 0),
        'oldusername' => hasOldUserName() ? postv('oldusername') : '',
        'olduseremail' => hasOldUserEmail() ? postv('oldemail') : ''
    );
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnManagerChangePassword event
    if (postv('newpassword') == 1) {
        $tmp = array(
            'userid' => postv('userid'),
            'username' => postv('newusername', 'New User'),
            'userpassword' => newPassword()
        );
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }

    if (postv('passwordnotifymethod') === 'e' && postv('newpassword') == 1) {
        sendMailMessage(postv('email'), postv('newusername', 'New User'), newPassword(), postv('fullname'));
    }

    // invoke OnUserFormSave event
    $tmp = array(
        'mode' => 'upd',
        'id' => postv('userid')
    );
    evo()->invokeEvent('OnUserFormSave', $tmp);
    evo()->clearCache();
    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        // as this is an existing user, delete his/ her entries in the groups before saving the new groups
        $rs = db()->delete(evo()->getFullTableName('member_groups'), sprintf("member='%s'", postv('userid')));
        if (!$rs) {
            webAlert('An error occurred while attempting to delete previous user_groups entries.');
            exit;
        }
        $user_groups = postv('user_groups');
        if ($user_groups) {
            foreach ($user_groups as $user_group) {
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    array('user_group' => $user_group, 'member' => postv('userid'))
                    , '[+prefix+]member_groups'
                );
                if (!$rs) {
                    webAlert('An error occurred while attempting to add the user to a user_group.');
                    exit;
                }
            }
        }
    }
    // end of user_groups stuff!
    if (postv('userid') == evo()->getLoginUserID() && postv('newpassword') !== 1 && postv('passwordnotifymethod') !== 's') {
        ?>
        <body bgcolor='#efefef'>
        <script language="JavaScript">
            alert("<?php echo lang('user_changeddata'); ?>");
            top.location.href = 'index.php?a=8';
        </script>
        </body>
        <?php
        exit;
    }
    evo()->getSettings();
    if (postv('userid') == evo()->getLoginUserID() && $_SESSION['mgrRole'] !== postv('role', 0)) {
        $_SESSION['mgrRole'] = postv('role', 0);
        evo()->webAlertAndQuit(lang('save_user.processor.php1'), 'index.php?a=75');
        exit;
    }
    if (postv('newpassword') != 1 || postv('passwordnotifymethod') !== 's') {
        if (postv('save_action') === 'stay') {
            header(sprintf(
                'Location: index.php?a=%s&id=%s&r=3&save_action=%s'
                , postv('mode')
                , postv('userid')
                , postv('save_action')
            ));
            exit;
        }
        if (postv('save_action') !== 'close') {
            header(sprintf(
                'Location: index.php?a=11&r=3&save_action=%s'
                , postv('save_action')
            ));
            exit;
        }
        if (postv('mode') === '74') {
            header('Location: ' . 'index.php?r=3&a=2');
            exit;
        }
        header('Location: ' . 'index.php?a=75&r=3');
        exit;
    }

    if (postv('userid') == evo()->getLoginUserID()) {
        $stayUrl = 'index.php?a=8';
    } elseif (postv('save_action') !== 'close') {
        $stayUrl = sprintf(
            'index.php?a=%s&save_action=%s'
            , (postv('save_action') === 'stay') ? sprintf('%s&id=%s', postv('mode'), postv('userid')) : '11'
            , postv('save_action')
        );
    } else {
        $stayUrl = 'index.php?a=75';
    }
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <h1><?php echo lang('user_title'); ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate">
                <a href="<?php echo $stayUrl; ?>"><img
                        src="<?php echo style('icons_save') ?>"/> <?php echo (postv('userid') == evo()->getLoginUserID()) ? lang('logout') : lang('close'); ?>
                </a>
            </li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?php echo lang('user_title'); ?></div>
        <div class="sectionBody">
            <div id="disp">
                <p>
                    <?php echo sprintf(
                            lang('password_msg')
                            , postv('newusername', 'New User')
                            , newPassword()
                        ) . ((postv('userid') == evo()->getLoginUserID()) ? ' ' . lang('user_changeddata') : ''); ?>
                </p>
            </div>
        </div>
    </div>
    <?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
