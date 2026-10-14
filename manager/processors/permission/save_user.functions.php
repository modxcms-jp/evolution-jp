<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

// プロフィール（a=74）は自分自身の編集なので、画面を開ける change_password 権限で保存できる
function canSaveUser()
{
    if (postv('mode') == 74) {
        return evo()->hasPermission('change_password')
            && post_intvar('userid') === (int)evo()->getLoginUserID();
    }
    return evo()->hasPermission('save_user');
}

// プロフィールで変えられない項目（ユーザー名・ロール・ブロック・所属グループ）は、送られた値ではなく保存済みの値を使う
function useSavedProfileValues()
{
    $userid = (int)evo()->getLoginUserID();
    $_POST['newusername'] = db()->getValue(
        db()->select('username', '[+prefix+]manager_users', where('id', '=', $userid))
    );
    $attributes = db()->getRow(db()->select(
        'role, failedlogincount, blocked, blockeduntil, blockedafter',
        '[+prefix+]user_attributes',
        where('internalKey', '=', $userid)
    ));
    foreach ((array)$attributes as $k => $v) {
        $_POST[$k] = $v;
    }
    $_POST['user_groups'] = db()->getColumn(
        'user_group',
        db()->select('user_group', '[+prefix+]member_groups', where('member', '=', $userid))
    );
}

// プロフィール画面に出している設定の名前。プラグインが足す欄（OnInterfaceSettingsRender）は、画面と同じく呼んで入力名を拾う
function profileSettingNames()
{
    $names = [
        'manager_theme',
        'manager_inline_style',
        'manager_login_startup',
        'manager_language',
        'which_editor',
        'editor_css_path'
    ];
    $evtOut = evo()->invokeEvent('OnInterfaceSettingsRender');
    if (is_array($evtOut) && preg_match_all('@name=["\']([A-Za-z0-9_]+)["\']@', implode('', $evtOut), $m)) {
        $names = array_merge($names, $m[1]);
    }
    return $names;
}

// Send an email to the user
function sendMailMessage($email, $uid, $pwd, $ufn)
{
    $message = evo()->mergeSettingsContent(
        evo()->parseText(
            evo()->config['signupemail_message'],
            [
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
            ]
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
    $ignore = [
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
        'userid',
        'csrf_token',
        'save_action',
        'blockedcheck',
        'changed_settings'
    ];

    // determine which settings can be saved blank (based on 'default_{settingname}' POST checkbox values)
    $defaults = [
        'manager_inline_style',
        'upload_images',
        'upload_media',
        'upload_files'
    ];

    // get user setting field names
    $settings = [];
    $post = postv();
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
    // 画面で変えていない項目は、表示されていた値（既定値や、編集している管理者自身の値）ではなく保存済みの値を残す
    $changed = changedSettingNames();
    if ($changed !== null) {
        $current = currentUserSettings($id);
        foreach (array_keys($post) as $k) {
            if (in_array($k, $ignore)) {
                continue;
            }
            // 「システム設定に従う」チェックボックス（default_{設定名}）は、設定本体と一組で扱う
            $name = strpos($k, 'default_') === 0 && in_array(substr($k, 8), $defaults) ? substr($k, 8) : $k;
            if (in_array($name, $changed) || in_array('default_' . $name, $changed)) {
                continue;
            }
            if (array_key_exists($k, $current)) {
                $settings[$k] = $current[$k];
            } else {
                unset($settings[$k]);
            }
        }
    }

    foreach ($defaults as $k) {
        if (evo()->array_get($settings, 'default_' . $k) == 1) {
            unset($settings[$k]);
        }
        unset($settings['default_' . $k]);
    }

    // プロフィールでは画面に出している設定だけを変え、それ以外（ファイル管理のパスなど）は保存済みの値を残す
    if (postv('mode') == 74) {
        $allowed = profileSettingNames();
        $settings = array_intersect_key($settings, array_flip($allowed));
        foreach (currentUserSettings($id) as $k => $v) {
            if (!in_array($k, $allowed)) {
                $settings[$k] = $v;
            }
        }
    }

    // システム設定と同じ値は保存しない（あとでシステム設定を変えたときに、そのユーザーも追従させる）
    $system = systemSettings();
    foreach ($settings as $k => $v) {
        if (array_key_exists($k, $system) && (string)$system[$k] === (string)$v) {
            unset($settings[$k]);
        }
    }

    db()->delete(evo()->getFullTableName('user_settings'), sprintf("user='%s'", $id));
    $savethese = [];
    foreach ($settings as $k => $v) {
        $savethese[] = sprintf("(%s, '%s', '%s')", $id, db()->escape($k), db()->escape($v));
    }
    if (empty($savethese)) {
        return;
    }
    $sql = sprintf(
        'INSERT INTO %s (user, setting_name, setting_value) VALUES %s',
        evo()->getFullTableName('user_settings'),
        implode(', ', $savethese)
    );
    if (!db()->query($sql)) {
        exit('Failed to update user settings!');
    }
    unset($_SESSION['openedArray']);
}

// 画面で変えた項目名の一覧。送られていない（JS が動かなかった）ときは null を返し、送られた値をすべて保存する
function changedSettingNames()
{
    $json = postv('changed_settings');
    if ($json === null) {
        return null;
    }
    $names = json_decode((string)$json, true);
    if (!is_array($names)) {
        return null;
    }
    return array_values(array_filter($names, 'is_string'));
}

function currentUserSettings($id)
{
    $settings = [];
    $rs = db()->select('setting_name, setting_value', '[+prefix+]user_settings', sprintf("user='%d'", $id));
    while ($row = db()->getRow($rs)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    return $settings;
}

function systemSettings()
{
    $settings = [];
    $rs = db()->select('setting_name, setting_value', '[+prefix+]system_settings');
    while ($row = db()->getRow($rs)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    return $settings;
}

// Web alert -  sends an alert to web browser
function webAlert($msg)
{
    $mode = postv('mode');
    $url = 'index.php?a=' . $mode . ($mode == '12' ? '&id=' . post_intvar('userid') : '');
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
    if (manager()->isAdmin()) {
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
        'id',
        '[+prefix+]manager_users',
        sprintf("username='%s'", db()->escape($newusername))
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
        'internalKey',
        '[+prefix+]user_attributes',
        sprintf("email='%s'", $email)
    );
    if (!db()->count($rs)) {
        return false;
    }
    return db()->getValue($rs);
}

function role_byuserid($userid)
{
    $rs = db()->select(
        'role',
        '[+prefix+]user_attributes',
        sprintf('internalKey=%s', $userid)
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
    static $password = null;

    if ($password) {
        return $password;
    }

    switch (postv('passwordgenmethod')) {
        case 'spec':
            $password = postv('specifiedpassword');
            break;
        case 'g':
            $password = generate_password(8);
            break;
        default:
            webAlert('No password generation method specified!');
            exit;
    }

    return $password;
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
    $fields = [
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
    ];
    $rs = [];
    foreach ($fields as $field) {
        if (in_array($field, ['dob', 'gender', 'blocked', 'blockeduntil', 'blockedafter']) && !postv($field)) {
            $rs[$field] = 0;
            continue;
        }
        $rs[$field] = postv($field, $field === 'role' ? 0 : '');
    }
    return $rs;
}

function newUser()
{
    // invoke OnBeforeUserFormSave event
    $tmp = [
        'mode' => 'new',
        'id' => null
    ];
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // build the SQL
    $internalKey = db()->insert(
        ['username' => db()->escape(postv('newusername', 'New User'))],
        '[+prefix+]manager_users'
    );
    if (!$internalKey) {
        webAlert('An error occurred while attempting to save the user.');
        exit;
    }
    db()->update(
        ['password' => evo()->phpass->HashPassword(newPassword())],
        '[+prefix+]manager_users',
        sprintf("id='%s'", $internalKey)
    );

    $field = validate();
    $field['internalKey'] = $internalKey;
    $rs = db()->insert(
        db()->escape($field),
        '[+prefix+]user_attributes'
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to save the user's attributes.");
        exit;
    }

    // Save User Settings
    saveUserSettings($internalKey);

    // invoke OnManagerSaveUser event
    $tmp = [
        'mode' => 'new',
        'userid' => $internalKey,
        'username' => postv('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail' => postv('email'),
        'userfullname' => postv('fullname'),
        'userroleid' => postv('role', 0)
    ];
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnUserFormSave event
    $tmp = [
        'mode' => 'new',
        'id' => $internalKey
    ];
    evo()->invokeEvent('OnUserFormSave', $tmp);

    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        $user_groups = postv('user_groups');
        if ($user_groups) {
            foreach ($user_groups as $user_group) {
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    ['user_group' => $user_group, 'member' => $internalKey],
                    '[+prefix+]member_groups'
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
                'index.php?r=3&a=11&stay=%s&id=%s',
                postv('stay'),
                $internalKey
            );
        } else {
            $stayUrl = 'index.php?r=3&a=11&stay=' . postv('stay');
        }
    } else {
        $stayUrl = 'index.php?r=3&a=75';
    }

    if (postv('passwordnotifymethod') === 'e') {
        sendMailMessage(
            postv('email'),
            postv('newusername', 'New User'),
            newPassword(),
            postv('fullname')
        );
        header('Location: ' . $stayUrl);
        exit;
    }

    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
?>
    <h1><?= lang('user_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate"><a href="<?= $stayUrl ?>"><img
                        src="<?= style('icons_save') ?>" /> <?= lang('close') ?>
                </a></li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?= lang('user_title') ?></div>
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
    $tmp = [
        'mode' => 'upd',
        'id' => postv('userid')
    ];
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // update user name and password
    $field = ['username' => postv('newusername', 'New User')];
    if (postv('newpassword') == 1) {
        $field['password'] = evo()->phpass->HashPassword(newPassword());
    }
    $rs = db()->update(
        db()->escape($field),
        '[+prefix+]manager_users',
        sprintf("id='%s'", post_intvar('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's data.");
        exit;
    }

    $field = validate();
    $field['failedlogincount'] = postv('failedlogincount');
    $rs = db()->update(
        db()->escape($field),
        '[+prefix+]user_attributes',
        sprintf("internalKey='%s'", post_intvar('userid'))
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's attributes.");
        exit;
    }

    // Save user settings
    saveUserSettings(post_intvar('userid'));

    // invoke OnManagerSaveUser event
    $tmp = [
        'mode' => 'upd',
        'userid' => postv('userid'),
        'username' => postv('newusername', 'New User'),
        'userpassword' => newPassword(),
        'useremail' => postv('email'),
        'userfullname' => postv('fullname'),
        'userroleid' => postv('role', 0),
        'oldusername' => hasOldUserName() ? postv('oldusername') : '',
        'olduseremail' => hasOldUserEmail() ? postv('oldemail') : ''
    ];
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnManagerChangePassword event
    if (postv('newpassword') == 1) {
        $tmp = [
            'userid' => postv('userid'),
            'username' => postv('newusername', 'New User'),
            'userpassword' => newPassword()
        ];
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }

    if (postv('passwordnotifymethod') === 'e' && postv('newpassword') == 1) {
        sendMailMessage(postv('email'), postv('newusername', 'New User'), newPassword(), postv('fullname'));
    }

    // invoke OnUserFormSave event
    $tmp = [
        'mode' => 'upd',
        'id' => postv('userid')
    ];
    evo()->invokeEvent('OnUserFormSave', $tmp);
    evo()->clearCache();
    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        // as this is an existing user, delete his/ her entries in the groups before saving the new groups
        $rs = db()->delete(evo()->getFullTableName('member_groups'), sprintf("member='%s'", post_intvar('userid')));
        if (!$rs) {
            webAlert('An error occurred while attempting to delete previous user_groups entries.');
            exit;
        }
        $user_groups = postv('user_groups');
        if ($user_groups) {
            foreach ($user_groups as $user_group) {
                $user_group = (int)$user_group;
                $rs = db()->insert(
                    ['user_group' => $user_group, 'member' => post_intvar('userid')],
                    '[+prefix+]member_groups'
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
            <form id="evoLogoutForm" method="post" action="index.php?a=8" target="_top" data-no-ajax><?= csrfTokenField() ?></form>
            <script language="JavaScript">
                alert("<?= lang('user_changeddata') ?>");
                document.getElementById('evoLogoutForm').submit();
            </script>
        </body>
    <?php
        exit;
    }
    evo()->getSettings();
    if (postv('userid') == evo()->getLoginUserID() && sessionv('mgrRole') !== postv('role', 0)) {
        $_SESSION['mgrRole'] = postv('role', 0);
        evo()->webAlertAndQuit(lang('save_user.processor.php1'), 'index.php?a=75');
        exit;
    }
    if (postv('newpassword') != 1 || postv('passwordnotifymethod') !== 's') {
        if (postv('mode') == 74) {
            header('Location: ' . 'index.php?r=3&a=2');
            exit;
        }
        if (postv('save_action') === 'stay') {
            header(sprintf(
                'Location: index.php?a=%s&id=%s&r=3&save_action=%s',
                postv('mode'),
                postv('userid'),
                postv('save_action')
            ));
            exit;
        }
        if (postv('save_action') !== 'close') {
            header(sprintf(
                'Location: index.php?a=11&r=3&save_action=%s',
                postv('save_action')
            ));
            exit;
        }
        header('Location: ' . 'index.php?a=75&r=3');
        exit;
    }

    if (postv('userid') == evo()->getLoginUserID()) {
        $stayUrl = 'index.php?a=8';
    } elseif (postv('save_action') !== 'close') {
        $stayUrl = sprintf(
            'index.php?a=%s&save_action=%s',
            (postv('save_action') === 'stay') ? sprintf('%s&id=%s', postv('mode'), postv('userid')) : '11',
            postv('save_action')
        );
    } else {
        $stayUrl = 'index.php?a=75';
    }
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <h1><?= lang('user_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate">
                <a href="<?= hsc($stayUrl) ?>"<?= postv('userid') == evo()->getLoginUserID() ? ' data-evo-post="full"' : '' ?>><img
                        src="<?= style('icons_save') ?>" /> <?= (postv('userid') == evo()->getLoginUserID()) ? lang('logout') : lang('close') ?>
                </a>
            </li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?= lang('user_title') ?></div>
        <div class="sectionBody">
            <div id="disp">
                <p>
                    <?= sprintf(
                        lang('password_msg'),
                        postv('newusername', 'New User'),
                        newPassword()
                    ) . ((postv('userid') == evo()->getLoginUserID()) ? ' ' . lang('user_changeddata') : ''); ?>
                </p>
            </div>
        </div>
    </div>
<?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
