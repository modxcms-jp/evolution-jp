<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_user')) {
    $e->setError(3);
    $e->dumpError();
}

// Send an email to the user
function sendMailMessage($email, $uid, $pwd, $ufn) {
    $ph['username'] = $uid;
    $ph['uid']      = $uid;
    $ph['password'] = $pwd;
    $ph['pwd']      = $pwd;
    $ph['fullname'] = $ufn;
    $ph['ufn']      = $ufn;
    $site_name      = evo()->config['site_name'];
    $ph['site_name'] = $site_name;
    $ph['sname']    = $site_name;
    $admin_email    = evo()->config['emailsender'];
    $ph['manager_email'] = $admin_email;
    $ph['saddr']    = $admin_email;
    $ph['semail']   = $admin_email;
    $site_url       = evo()->config['site_url'];
    $ph['site_url'] = $site_url;
    $ph['surl']     = $site_url . 'manager/';
    $message = evo()->parseText(evo()->config['signupemail_message'],$ph);
    $message = evo()->mergeSettingsContent($message);

    $rs = evo()->sendmail($email,$message);
    if (!$rs) {
        webAlert(sprintf('%s - %s', $email, lang('error_sending_email')));
        exit;
    }
}

// Save User Settings
function saveUserSettings($id) {
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
        'upload_flash',
        'upload_files'
    );

    // get user setting field names
    $settings= array ();
    $post = $_POST;
    foreach ($post as $n => $v) {
        if(in_array($n, $ignore)) {
            continue;
        } // ignore blacklist and empties
        if(!in_array($n, $defaults) && trim($v) == '') {
            continue;
        } // ignore blacklist and empties

        if(is_array($v)) {
            $v = implode(',', $v);
        }
        $settings[$n] = $v; // this value should be saved
    }
    foreach ($defaults as $k) {
        if (evo()->array_get($settings, 'default_' . $k) == 1) {
            unset($settings[$k]);
        }
        unset($settings['default_' . $k]);
    }

    evo()->db->delete(evo()->getFullTableName('user_settings'), sprintf("user='%s'", $id));
    $savethese = array();
    foreach ($settings as $k => $v) {
        $v = evo()->db->escape($v);
        $savethese[] = sprintf("(%s, '%s', '%s')", $id, $k, $v);
    }
    if(empty($savethese)) {
        return;
    }
    $sql = sprintf(
            'INSERT INTO %s (user, setting_name, setting_value) VALUES %s'
            , evo()->getFullTableName('user_settings')
            , implode(', ', $savethese)
    );
    $rs = evo()->db->query($sql);
    if (!$rs) {
        exit('Failed to update user settings!');
    }
    unset($_SESSION['openedArray']);
}

// Web alert -  sends an alert to web browser
function webAlert($msg) {
    global $id;
    $mode = $_POST['mode'];
    $url = 'index.php?a=' . $mode . ($mode == '12' ? "&id=" . $id : '');
    evo()->manager->saveFormValues($mode);
    evo()->webAlertAndQuit($msg, $url);
}

// Generate password
function generate_password($length = 10) {
    return substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}

function verifyPermission() {
    if($_SESSION['mgrRole']==1) {
        return true;
    }
    if(evo()->input_post('role')!=1) {
        return true;
    }
    if(!evo()->hasPermission('edit_role')
        || !evo()->hasPermission('save_role')
        || !evo()->hasPermission('delete_role')
        || !evo()->hasPermission('new_role')
    ) {
        return false;
    }
    return true;
}

function userid_byname($newusername) {
    $rs = evo()->db->select(
        'id'
        , '[+prefix+]manager_users'
        , sprintf("username='%s'", evo()->db->escape($newusername))
    );
    if (!evo()->db->getRecordCount($rs)) {
        return false;
    }
    return evo()->db->getValue($rs);
}
function userid_byemail($email){
    $rs = evo()->db->select(
        'internalKey'
        , '[+prefix+]user_attributes'
        , sprintf("email='%s'", $email)
    );
    if (!evo()->db->getRecordCount($rs)) {
        return false;
    }
    return evo()->db->getValue($rs);
}
function role_byuserid($userid){
    $rs = evo()->db->select(
        'role'
        , '[+prefix+]user_attributes'
        , sprintf('internalKey=%s', $userid)
    );
    if (!evo()->db->getRecordCount($rs)) {
        return false;
    }
    return evo()->db->getValue($rs);
}

function hasOldUserName() {
    return (post('oldusername') != post('newusername', 'New User'));
}

function hasOldUserEmail() {
    return (post('oldemail') != post('email'));
}

function newPassword() {
    if (post('passwordgenmethod') === 'spec') {
        return post('specifiedpassword');
    }
    if (post('passwordgenmethod') === 'g') {
        return generate_password(8);
    }
    webAlert('No password generation method specified!');
    exit;
}

function confirmPassword() {
    if (post('passwordgenmethod') !== 'spec') {
        return true;
    }
    if(post('specifiedpassword') == post('confirmpassword')) {
        return true;
    }
    return false;
}

function validEmail() {
    if (!post('email')) {
        return false;
    }
    if(!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,20}$/i", post('email'))) {
        return false;
    }
    return true;
}

function field() {
    $fields = array('fullname','role','email','phone','mobilephone','fax','zip','street','city','state','country','gender','dob','photo','comment','blocked','blockeduntil','blockedafter');
    $rs = array();
    foreach ($fields as $field) {
        $rs[$field] = post($field);
    }
    return $rs;
}

function newUser() {
    // invoke OnBeforeUserFormSave event
    $tmp = array (
        'mode' => 'new',
        'id'   => null
    );
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // build the SQL
    $internalKey = db()->insert(
        array('username'=>db()->escape(post('newusername', 'New User')))
        , '[+prefix+]manager_users'
    );
    if (!$internalKey) {
        webAlert('An error occurred while attempting to save the user.');
        exit;
    }
    db()->update(
        array('password'=>evo()->phpass->HashPassword(newPassword()))
        , '[+prefix+]manager_users'
        , sprintf("id='%s'", $internalKey)
    );

    $field = field();
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
    $tmp = array (
        'mode'         => 'new',
        'userid'       => $internalKey,
        'username'     => post('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail'    => post('email'),
        'userfullname' => post('fullname'),
        'userroleid'   => post('role', 0)
    );
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnUserFormSave event
    $tmp = array (
        'mode' => 'new',
        'id'   => $internalKey
    );
    evo()->invokeEvent('OnUserFormSave', $tmp);

    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        $user_groups = post('user_groups');
        if ($user_groups) {
            foreach ($user_groups as $user_group){
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    array('user_group'=>$user_group,'member'=>$internalKey)
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

    if (post('stay') != '') {
        $stayUrl = sprintf(
            'index.php?r=3&a=11&stay=%s'
            , post('stay')
        );
        if (post('stay') == '2') {
            $stayUrl = sprintf(
                'index.php?r=3&a=11&stay=%s&id=%s'
                , post('stay')
                , $internalKey
            );
        }
    } else {
        $stayUrl = 'index.php?r=3&a=75';
    }

    if (post('passwordnotifymethod') === 'e') {
        sendMailMessage(
            post('email')
            , post('newusername', 'New User')
            , newPassword()
            , post('fullname')
        );
        header('Location: ' . $stayUrl);
        exit;
    }

    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <h1><?php echo lang('user_title'); ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate"><a href="<?php echo $stayUrl ?>"><img src="<?php echo style('icons_save') ?>" /> <?php echo lang('close'); ?>
                </a></li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?php echo lang('user_title'); ?></div>
        <div class="sectionBody">
            <div id="disp">
                <p>
                    <?php
                    echo sprintf(lang('password_msg'), post('newusername', 'New User'), newPassword());
                    ?>
                </p>
            </div>
        </div>
    </div>
    <?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}

function updateUser() {
    // invoke OnBeforeUserFormSave event
    $tmp = array (
        'mode' => 'upd',
        'id'   => post('userid')
    );
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // update user name and password
    $field = array('username' => post('newusername', 'New User'));
    if(post('newpassword')==1) {
        $field['password'] = evo()->phpass->HashPassword(newPassword());
    }
    $rs = db()->update(
        db()->escape($field)
        , '[+prefix+]manager_users'
        , sprintf("id='%s'", post('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's data.");
        exit;
    }

    $field = field();
    $field['failedlogincount'] = post('failedlogincount');
    $rs = db()->update(
        db()->escape($field)
        , '[+prefix+]user_attributes'
        , sprintf("internalKey='%s'", post('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's attributes.");
        exit;
    }

    // Save user settings
    saveUserSettings(post('userid'));

    // invoke OnManagerSaveUser event
    $tmp = array (
        'mode'         => 'upd',
        'userid'       => post('userid'),
        'username'     => post('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail'    => post('email'),
        'userfullname' => post('fullname'),
        'userroleid'   => post('role', 0),
        'oldusername'  => hasOldUserName()  ? post('oldusername') : '',
        'olduseremail' => hasOldUserEmail() ? post('oldemail')    : ''
    );
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnManagerChangePassword event
    if (post('newpassword')==1) {
        $tmp = array(
            'userid' => post('userid'),
            'username' => post('newusername', 'New User'),
            'userpassword' => newPassword()
        );
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }

    if (post('passwordnotifymethod') === 'e' && post('newpassword') == 1) {
        sendMailMessage(post('email'), post('newusername', 'New User'), newPassword(), post('fullname'));
    }

    // invoke OnUserFormSave event
    $tmp = array (
        'mode' => 'upd',
        'id' => post('userid')
    );
    evo()->invokeEvent('OnUserFormSave', $tmp);
    evo()->clearCache();
    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        // as this is an existing user, delete his/ her entries in the groups before saving the new groups
        $rs = db()->delete(evo()->getFullTableName('member_groups'), sprintf("member='%s'", post('userid')));
        if (!$rs) {
            webAlert('An error occurred while attempting to delete previous user_groups entries.');
            exit;
        }
        $user_groups = post('user_groups');
        if ($user_groups){
            foreach ($user_groups as $user_group){
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    array('user_group'=>$user_group,'member'=>post('userid'))
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
    if (post('userid') == evo()->getLoginUserID() && post('newpassword') !==1 && post('passwordnotifymethod') !='s') {
        ?>
        <body bgcolor='#efefef'>
        <script language="JavaScript">
            alert("<?php echo lang('user_changeddata'); ?>");
            top.location.href='index.php?a=8';
        </script>
        </body>
        <?php
        exit;
    }
    evo()->getSettings();
    if (post('userid') == evo()->getLoginUserID() && $_SESSION['mgrRole'] !== post('role', 0)) {
        $_SESSION['mgrRole'] = post('role', 0);
        evo()->webAlertAndQuit(lang('save_user.processor.php1'),'index.php?a=75');
        exit;
    }
    if (post('newpassword') != 1 || post('passwordnotifymethod') !== 's') {
        if (post('save_action') != 'close') {
            if (post('save_action') == 'stay') {
                $url = sprintf('index.php?a=%s&id=%s', post('mode'), post('userid'));
            } else {
                $url = 'index.php?a=11';
            }
            $url .= sprintf('&r=3&save_action=%s', post('save_action'));
        } elseif (post('mode') === '74') {
            $url = 'index.php?r=3&a=2';
        } else {
            $url = 'index.php?a=75&r=3';
        }
        header('Location: ' . $url);
        exit;
    }

    if(post('userid') == evo()->getLoginUserID()) {
        $stayUrl = 'index.php?a=8';
    } elseif (post('save_action') != 'close') {
        $a = (post('save_action') == 'stay') ? post('mode') . '&id=' . post('userid') : '11';
        $stayUrl = sprintf(
            'index.php?a=%s&save_action=%s'
            , $a
            , post('save_action')
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
                <a href="<?php echo $stayUrl; ?>"><img src="<?php echo style('icons_save') ?>"/> <?php echo (post('userid') == evo()->getLoginUserID()) ? lang('logout') : lang('close'); ?></a>
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
                            , post('newusername', 'New User')
                            , newPassword()
                        ) . ((post('userid') == evo()->getLoginUserID()) ? ' ' . lang('user_changeddata') : ''); ?>
                </p>
            </div>
        </div>
    </div>
    <?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}