<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if (!$modx->hasPermission('save_user')) {
    $e->setError(3);
    $e->dumpError();
}

// Send an email to the user
function sendMailMessage($email, $uid, $pwd, $ufn)
{
    global $modx,$_lang;
    $ph['username'] = $uid;
    $ph['uid']      = $uid;
    $ph['password'] = $pwd;
    $ph['pwd']      = $pwd;
    $ph['fullname'] = $ufn;
    $ph['ufn']      = $ufn;
    $site_name      = $modx->config['site_name'];
    $ph['site_name'] = $site_name;
    $ph['sname']    = $site_name;
    $admin_email    = $modx->config['emailsender'];
    $ph['manager_email'] = $admin_email;
    $ph['saddr']    = $admin_email;
    $ph['semail']   = $admin_email;
    $site_url       = $modx->config['site_url'];
    $ph['site_url'] = $site_url;
    $ph['surl']     = $site_url . 'manager/';
    $message = $modx->parseText($modx->config['signupemail_message'],$ph);
    $message = $modx->mergeSettingsContent($message);

    $rs = $modx->sendmail($email,$message);
    if ($rs === false) //ignore mail errors in this cas
    {
        webAlert($email . " - " . $_lang['error_sending_email']);
        exit;
    }
}

// Save User Settings
function saveUserSettings($id)
{
    global $modx;

    // array of post values to ignore in this function
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
        if ($modx->array_get($settings, 'default_' . $k) == 1) {
            unset($settings[$k]);
        }
        unset($settings['default_' . $k]);
    }

    $modx->db->delete($modx->getFullTableName('user_settings'), sprintf("user='%s'", $id));
    $savethese = array();
    foreach ($settings as $k => $v) {
        $v = $modx->db->escape($v);
        $savethese[] = sprintf("(%s, '%s', '%s')", $id, $k, $v);
    }
    if(empty($savethese)) {
        return;
    }
    $sql = sprintf(
            'INSERT INTO %s (user, setting_name, setting_value) VALUES %s'
            , $modx->getFullTableName('user_settings')
            , implode(', ', $savethese)
    );
    $rs = $modx->db->query($sql);
    if (!$rs) {
        exit('Failed to update user settings!');
    }
    unset($_SESSION['openedArray']);
}

// Web alert -  sends an alert to web browser
function webAlert($msg) {
    global $id, $modx;
    $mode = $_POST['mode'];
    $url = 'index.php?a=' . $mode . ($mode == '12' ? "&id=" . $id : '');
    $modx->manager->saveFormValues($mode);
    $modx->webAlertAndQuit($msg, $url);
}

// Generate password
function generate_password($length = 10) {
    return substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}

function verifyPermission() {
    global $modx;
    if($_SESSION['mgrRole']==1) {
        return true;
    }
    if($modx->input_post('role')!=1) {
        return true;
    }
    if(!$modx->hasPermission('edit_role')
        || !$modx->hasPermission('save_role')
        || !$modx->hasPermission('delete_role')
        || !$modx->hasPermission('new_role')
    ) {
        return false;
    }
    return true;
}

function userid_byname($newusername) {
    global $modx;
    $rs = $modx->db->select(
        'id'
        , '[+prefix+]manager_users'
        , sprintf("username='%s'", $modx->db->escape($newusername))
    );
    if (!$modx->db->getRecordCount($rs)) {
        return false;
    }
    return $modx->db->getValue($rs);
}
function userid_byemail($email){
    global $modx;
    $rs = $modx->db->select(
        'internalKey'
        , '[+prefix+]user_attributes'
        , sprintf("email='%s'", $email)
    );
    if (!$modx->db->getRecordCount($rs)) {
        return false;
    }
    return $modx->db->getValue($rs);
}
function role_byuserid($userid){
    global $modx;
    $rs = $modx->db->select(
        'role'
        , '[+prefix+]user_attributes'
        , sprintf('internalKey=%s', $userid)
    );
    if (!$modx->db->getRecordCount($rs)) {
        return false;
    }
    return $modx->db->getValue($rs);
}
