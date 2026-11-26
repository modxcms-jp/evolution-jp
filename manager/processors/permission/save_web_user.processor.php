<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_web_user')) {
    alert()->setError(3);
    alert()->dumpError();
}
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';
$id = post_intvar('id');
$oldusername = post_strvar('oldusername');
$newusername = trim(post_strvar('newusername'));
$fullname = post_strvar('fullname');
$genpassword = post_intvar('newpassword');
// 新しいパスワード（加工しない）。新規は画面が常に送る。すでにあるユーザーは「パスワードを変更」を押したときだけ
$newpassword = post_textvar('userpassword', '');
$email = post_strvar('email');
$oldemail = post_strvar('oldemail');
$phone = post_strvar('phone');
$mobilephone = post_strvar('mobilephone');
$fax = post_strvar('fax');
// 日付は空なら 0、解釈できない・整数の列に入らないなら null（下で差し戻す）
$dob = evo()->parseDateInput(post_strvar('dob'));
$country = post_strvar('country');
$street = post_strvar('street');
$city = post_strvar('city');
$state = post_strvar('state');
$zip = post_strvar('zip');
$gender = post_intvar('gender');
$photo = post_strvar('photo');
$comment = post_strvar('comment');
$role = post_intvar('role');
$failedlogincount = post_intvar('failedlogincount');
$blocked = post_intvar('blocked');
$blockeduntil = evo()->parseDateInput(post_strvar('blockeduntil'));
$blockedafter = evo()->parseDateInput(post_strvar('blockedafter'));
$user_groups = post_idsvar('user_groups');
$mode = EvoFormFlow::postedMode('web_user');
$afterSave = EvoFormFlow::postedAfterSave();

// verify email
if (config('required_email_wuser') === null) {
    evo()->setConfig('required_email_wuser', 1);
}

if (config('required_email_wuser')) {
    if ($email == '' || !preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,20}$/i', $email)) {
        webAlert("E-mail address doesn't seem to be valid!");
        exit;
    }
}

// 解釈できない日付は、DB に渡さず差し戻す
if ($dob === null || $blockeduntil === null || $blockedafter === null) {
    webAlert(lang('mgrlog_dateinvalid'));
    exit;
}

switch ($mode) {
    case 'new': // new user
        if ($newusername === '') {
            webAlert('Missing user name!');
            exit;
        }
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
        if (config('required_email_wuser')) {
            $rs = db()->select(
                'id',
                '[+prefix+]web_user_attributes',
                sprintf("email='%s'", db()->escape($email))
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

        $passwordError = webUserPasswordError($newpassword, $newusername, $email, $fullname);
        if ($passwordError !== '') {
            webAlert($passwordError);
            exit;
        }

        // invoke OnBeforeWUsrFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => null
        ];
        evo()->invokeEvent('OnBeforeWUsrFormSave', $tmp);

        // create the user account
        $fields = [];
        $fields['username'] = db()->escape($newusername);
        // Web ユーザーは表側のログイン（weblogin）が MD5 で照合するので、保存の形を変えない（assets/docs/password-policy.md）
        $fields['password'] = md5($newpassword);
        $internalKey = db()->insert(
            $fields,
            '[+prefix+]web_users'
        );
        if (!$internalKey) {
            webAlert("An error occurred while attempting to save the user.");
            exit;
        }

        $fields = [
            'internalKey' => $internalKey,
            'fullname' => $fullname,
            'role' => $role,
            'email' => $email,
            'phone' => $phone,
            'mobilephone' => $mobilephone,
            'fax' => $fax,
            'zip' => $zip,
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'gender' => $gender,
            'dob' => $dob,
            'photo' => $photo,
            'comment' => $comment,
            'blocked' => $blocked,
            'blockeduntil' => $blockeduntil,
            'blockedafter' => $blockedafter
        ];
        $rs = db()->insert(
            db()->escape($fields),
            '[+prefix+]web_user_attributes'
        );
        if (!$rs) {
            webAlert("An error occurred while attempting to save the user's attributes.");
            exit;
        }

        // Save User Settings
        saveUserSettings($internalKey);

        // invoke OnWebSaveUser event
        $tmp = [
            'mode' => 'new',
            'userid' => $internalKey,
            'username' => $newusername,
            'userpassword' => $newpassword,
            'useremail' => $email,
            'userfullname' => $fullname
        ];
        evo()->invokeEvent('OnWebSaveUser', $tmp);

        // invoke OnWUsrFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => $internalKey
        ];
        evo()->invokeEvent('OnWUsrFormSave', $tmp);

        /*******************************************************************************/
        // put the user in the user_groups he/ she should be in
        // first, check that up_perms are switched on!
        if (config('use_udperms') == 1) {
            $field = [];
            foreach ($user_groups as $user_group) {
                $field['webgroup'] = $user_group;
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
        // end of user_groups stuff!

        // パスワードは画面にもメールにも出さず、ほかの保存と同じ行き先へ戻す
        header('Location: ' . EvoFormFlow::afterSaveUrl('web_user', $internalKey, $afterSave));
        break;
    case 'edit': // edit user
        if (!$id) {
            webAlert('Missing user id!');
            exit;
        }
        if ($newusername === '') {
            webAlert('Missing user name!');
            exit;
        }
        // generate a new password for this user
        if ($genpassword === 1) {
            $passwordError = webUserPasswordError($newpassword, $newusername, $email, $fullname);
            if ($passwordError !== '') {
                webAlert($passwordError);
                exit;
            }
            $updatepasswordsql = ", password='" . md5($newpassword) . "' ";
        } else {
            $newpassword = '';
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
        if (config('required_email_wuser')) {
            $rs = db()->select(
                'internalKey',
                '[+prefix+]web_user_attributes',
                sprintf("email='%s'", db()->escape($email))
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
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
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

        $fields = [
            'fullname' => $fullname,
            'role' => $role,
            'email' => $email,
            'phone' => $phone,
            'mobilephone' => $mobilephone,
            'fax' => $fax,
            'zip' => $zip,
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'gender' => $gender,
            'dob' => $dob,
            'photo' => $photo,
            'comment' => $comment,
            'failedlogincount' => $failedlogincount,
            'blocked' => $blocked,
            'blockeduntil' => $blockeduntil,
            'blockedafter' => $blockedafter
        ];
        $rs = db()->update(
            db()->escape($fields),
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
            'userpassword' => $newpassword,
            'useremail' => $email,
            'userfullname' => $fullname,
            'oldusername' => ($oldusername !== $newusername) ? $oldusername : '',
            'olduseremail' => ($oldemail !== $email) ? $oldemail : ''
        ];
        evo()->invokeEvent('OnWebSaveUser', $tmp);

        // invoke OnWebChangePassword event
        if (!empty($updatepasswordsql)) {
            $tmp = [
                'userid' => $id,
                'username' => $newusername,
                'userpassword' => $newpassword
            ];
            evo()->invokeEvent('OnWebChangePassword', $tmp);
        }

        // invoke OnWUsrFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnWUsrFormSave', $tmp);

        /*******************************************************************************/
        // put the user in the user_groups he/ she should be in
        // first, check that up_perms are switched on!
        if (config('use_udperms') == 1) {
            // as this is an existing user, delete his/ her entries in the groups before saving the new groups
            $rs = db()->delete(evo()->getFullTableName('web_groups'), "webuser='" . $id . "'");
            if (!$rs) {
                webAlert("An error occurred while attempting to delete previous user_groups entries.");
                exit;
            }
            foreach ($user_groups as $group) {
                $sql = sprintf(
                    "INSERT INTO %s (webgroup, webuser) VALUES('%d', '%d')",
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
        // end of user_groups stuff!
        /*******************************************************************************/

        header('Location: ' . EvoFormFlow::afterSaveUrl('web_user', $id, $afterSave));
        break;
    default:
        webAlert('Unauthorized access');
        exit;
}

// 新しいパスワードが決まりに合わなければ、利用者に見せるエラー文。合えば ''
function webUserPasswordError($password, $username, $email, $fullname)
{
    if ($password === '') {
        return lang('password_required');
    }
    return manager()->passwordError($password, [$username, $email, $fullname]);
}

// Save User Settings
function saveUserSettings($id)
{
    // 保存してよい名前は、設定の定義の scope web の行
    foreach (EvoSettingsForm::names('web') as $name) {
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

// Web alert -  sends an alert to web browser
function webAlert($msg)
{
    $mode = EvoFormFlow::postedMode('web_user');
    // 新規か更新でないとき（不正な mode）は、入力を今の画面の番号で残して一覧へ戻す
    manager()->saveFormValues($mode ? EvoFormFlow::screen('web_user', $mode) : 0);
    evo()->webAlertAndQuit($msg, EvoFormFlow::formUrl('web_user', $mode, post_intvar('id'), EvoFormFlow::postedAfterSave()));
}
