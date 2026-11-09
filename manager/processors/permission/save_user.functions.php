<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

// 保存の種類。new・edit・profile 以外は空文字にして、どれにも当てはめない
function saveUserMode()
{
    return EvoFormFlow::postedMode('user');
}

// プロフィール（mode=profile）は自分自身の編集なので、画面を開ける change_password 権限で保存できる
function canSaveUser()
{
    if (saveUserMode() === 'profile') {
        return evo()->hasPermission('change_password')
            && post_intvar('userid') === (int)evo()->getLoginUserID()
            && (post_intvar('newpassword') !== 1 || evo()->hasPermission('save_password'));
    }
    return evo()->hasPermission('save_user');
}

// 保存に使う値。プロフィールで変えられない項目（ユーザー名・ロール・ブロック・所属グループ）は、送られた値ではなく保存済みの値を返す
function userformv($key, $default = null)
{
    static $saved = null;
    if (saveUserMode() !== 'profile') {
        return postv($key, $default);
    }
    if ($saved === null) {
        $saved = savedProfileValues();
    }
    return array_key_exists($key, $saved) ? $saved[$key] : postv($key, $default);
}

// userformv() の値を文字列として読む。文字列でも数値でもない値（配列など）と空は $default
function userformv_str($key, $default = '')
{
    $value = userformv($key);
    return is_scalar($value) ? to_strvar((string)$value, $default) : $default;
}

// userformv() の値を id として読む（正の整数だけ。それ以外は 0）
function userformv_int($key)
{
    return to_intvar(userformv($key));
}

function savedProfileValues()
{
    $userid = (int)evo()->getLoginUserID();
    $values = (array)db()->getRow(db()->select(
        'role, failedlogincount, blocked, blockeduntil, blockedafter',
        '[+prefix+]user_attributes',
        where('internalKey', '=', $userid)
    ));
    $values['newusername'] = db()->getValue(
        db()->select('username', '[+prefix+]manager_users', where('id', '=', $userid))
    );
    $values['user_groups'] = db()->getColumn(
        'user_group',
        db()->select('user_group', '[+prefix+]member_groups', where('member', '=', $userid))
    );
    return $values;
}

// 設定の定義の scope。プロフィール以外のユーザー編集画面は user
function userSettingScope()
{
    return saveUserMode() === 'profile' ? 'profile' : 'user';
}

// ユーザー設定として保存してよい名前。本体の画面に出す設定（EvoSettingsForm）と、
// 画面を表示したときにプラグインのイベントの出力から覚えた欄の名前（セッション pluginSettingNames.<scope>）
function allowedSettingNames($scope)
{
    return array_values(array_unique(array_merge(
        EvoSettingsForm::names($scope),
        (array)sessionv('pluginSettingNames.' . $scope, [])
    )));
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
// 受ける名前は allowedSettingNames()（定義の scope・USER_TAB_KEYS・画面が覚えたプラグインの欄）だけ。
// 一覧に無い名前のすでにある行（auth_token など）は、値を変えずに残す
function saveUserSettings($id)
{
    $scope = userSettingScope();
    $allowed = allowedSettingNames($scope);
    // 「システム設定に従う」（default_{設定名}）が付く設定は、チェックを外せば空の値も保存できる
    $defaults = EvoSettingsForm::userDefaultNames($scope);
    // 画面で変えていない項目は、表示されていた値（既定値や、編集している管理者自身の値）ではなく保存済みの値を残す
    $changed = changedSettingNames();
    $current = currentUserSettings($id);

    $settings = [];
    foreach ($allowed as $name) {
        if ($changed !== null
            && !in_array($name, $changed, true)
            && !in_array('default_' . $name, $changed, true)) {
            if (array_key_exists($name, $current)) {
                $settings[$name] = $current[$name];
            }
            continue;
        }
        if (in_array($name, $defaults, true) && postv('default_' . $name) == 1) {
            continue;
        }
        $v = postv($name);
        if ($v === null) {
            continue;
        }
        if (is_array($v)) {
            $v = implode(',', $v);
        }
        if (!in_array($name, $defaults, true) && trim($v) == '') {
            continue;
        }
        $settings[$name] = $v;
    }

    // 言語とテーマは読み込むファイルのパスになるので、実在する名前だけを保存する
    $files = [
        'manager_language' => MODX_CORE_PATH . 'lang/%s.inc.php',
        'manager_theme' => MODX_MANAGER_PATH . 'media/style/%s/style.php'
    ];
    foreach ($files as $k => $format) {
        if (isset($settings[$k])
            && (!preg_match('@^[A-Za-z0-9_-]+$@', $settings[$k]) || !is_file(sprintf($format, $settings[$k])))) {
            unset($settings[$k]);
        }
    }

    // ファイルの URL の頭は、自サイトの URL を含まない相対パスにそろえる（システム設定と同じ扱い）
    if (isset($settings['rb_base_url']) && trim($settings['rb_base_url']) !== '') {
        $settings['rb_base_url'] = init::normalize_rb_base_url($settings['rb_base_url'], config('site_url'), MODX_BASE_URL);
    }

    // システム設定と同じ値は保存しない（あとでシステム設定を変えたときに、そのユーザーも追従させる）
    $system = systemSettings();
    foreach ($settings as $k => $v) {
        $systemValue = $system[$k] ?? null;
        if ($k === 'rb_base_url' && $systemValue !== null && trim((string)$systemValue) !== '') {
            // 比べるためだけにそろえる（DB に残る古い形の値と食い違わないように）
            $systemValue = init::normalize_rb_base_url($systemValue, config('site_url'), MODX_BASE_URL);
        }
        if ($systemValue !== null && (string)$systemValue === (string)$v) {
            unset($settings[$k]);
        }
    }

    // 一覧に無い名前の行は、検査や整形をかけずにそのまま残す
    foreach ($current as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            $settings[$k] = $v;
        }
    }

    db()->delete(evo()->getFullTableName('user_settings'), sprintf("user='%s'", $id));
    $savethese = [];
    foreach ($settings as $k => $v) {
        $savethese[] = sprintf("(%s, '%s', '%s')", $id, db()->escape($k), db()->escape($v));
    }
    // 開いていたツリーの記憶は、保存する行が 0 件のときも消す（次にツリーを開いたとき、設定から作り直す）
    sessionv('*openedArray', null);
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
}

// 画面で変えた項目名の一覧。送られていない（JS が動かなかった）ときは null を返し、送られた値をすべて保存する
function changedSettingNames()
{
    $json = postv('changed_settings');
    if (!is_string($json)) {
        return null;
    }
    $names = json_decode($json, true);
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
    $mode = saveUserMode();
    // 新規・更新・プロフィールでないとき（不正な mode）は、入力を今の画面の番号で残して一覧へ戻す
    evo()->manager->saveFormValues($mode ? EvoFormFlow::screen('user', $mode) : 0);
    evo()->webAlertAndQuit(
        $msg,
        EvoFormFlow::formUrl('user', $mode, post_intvar('userid'), EvoFormFlow::postedAfterSave())
    );
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
    if (userformv_int('role') !== 1) {
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
        sprintf("email='%s'", db()->escape($email))
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
        sprintf('internalKey=%d', $userid)
    );
    if (!db()->count($rs)) {
        return false;
    }
    return db()->getValue($rs);
}

function hasOldUserName()
{
    return (post_strvar('oldusername') !== userformv_str('newusername'));
}

function hasOldUserEmail()
{
    return (post_strvar('oldemail') !== post_strvar('email'));
}

function newPassword()
{
    static $password = null;

    if ($password) {
        return $password;
    }

    switch (post_choicevar('passwordgenmethod', ['spec', 'g'], '')) {
        case 'spec':
            $password = post_strvar('specifiedpassword');
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
    if (post_strvar('passwordgenmethod') !== 'spec') {
        return true;
    }
    if (post_strvar('specifiedpassword') === post_strvar('confirmpassword')) {
        return true;
    }
    return false;
}

function validEmail()
{
    $email = post_strvar('email');
    if (!$email) {
        return false;
    }
    if (!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,20}$/i", $email)) {
        return false;
    }
    return true;
}

// 送られたロールが、空（未送信を含む）か、0 以上の整数として読めるか。それ以外は保存しない
function validRole()
{
    $role = userformv('role');
    if ($role === null || $role === '' || $role === 0 || $role === '0') {
        return true;
    }
    return userformv_int('role') > 0;
}

// 日付の欄（生年月日・ブロックの日時）がすべて解釈できて、整数の列に入るか
function validDates()
{
    foreach (['dob', 'blockeduntil', 'blockedafter'] as $field) {
        if (evo()->parseDateInput(userformv_str($field)) === null) {
            return false;
        }
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
        if ($field === 'role') {
            $rs[$field] = userformv_int('role');
            continue;
        }
        $value = userformv_str($field);
        if (in_array($field, ['dob', 'blockeduntil', 'blockedafter'])) {
            // 入力欄の日付書式の文字列をタイムスタンプにする（空は 0。解釈できない日付は validDates() で先に差し戻す）
            $value = (int)evo()->parseDateInput($value);
        } elseif (in_array($field, ['gender', 'blocked']) && !$value) {
            $value = 0;
        }
        $rs[$field] = $value;
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
        ['username' => db()->escape(userformv_str('newusername'))],
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
        'username' => userformv_str('newusername'),
        'userpassword' => newPassword(),
        'useremail' => post_strvar('email'),
        'userfullname' => post_strvar('fullname'),
        'userroleid' => userformv_int('role')
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
        foreach (to_idsvar(userformv('user_groups')) as $user_group) {
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
    // end of user_groups stuff!

    $stayUrl = EvoFormFlow::afterSaveUrl('user', (int)$internalKey, EvoFormFlow::postedAfterSave(), ['refresh' => 'tree']);

    if (post_strvar('passwordnotifymethod') === 'e') {
        sendMailMessage(
            post_strvar('email'),
            userformv_str('newusername'),
            newPassword(),
            post_strvar('fullname')
        );
        header('Location: ' . $stayUrl);
        exit;
    }

    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
?>
    <h1><?= lang('user_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate"><a href="<?= hsc($stayUrl) ?>"><?= lang('close') ?>
                </a></li>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?= lang('user_title') ?></div>
        <div class="sectionBody">
            <div id="disp">
                <p>
                    <?php
                    echo sprintf(lang('password_msg'), hsc(userformv_str('newusername')), hsc(newPassword()));
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
    $userid = post_intvar('userid');
    $username = userformv_str('newusername');
    $changePassword = post_intvar('newpassword') === 1;
    $notifyMethod = post_strvar('passwordnotifymethod');
    $isSelf = $userid === (int)evo()->getLoginUserID();

    // invoke OnBeforeUserFormSave event
    $tmp = [
        'mode' => 'upd',
        'id' => $userid
    ];
    evo()->invokeEvent('OnBeforeUserFormSave', $tmp);

    // update user name and password
    $field = ['username' => $username];
    if ($changePassword) {
        $field['password'] = evo()->phpass->HashPassword(newPassword());
    }
    $rs = db()->update(
        db()->escape($field),
        '[+prefix+]manager_users',
        sprintf("id='%d'", $userid)
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's data.");
        exit;
    }

    $field = validate();
    $field['failedlogincount'] = userformv_int('failedlogincount');
    $rs = db()->update(
        db()->escape($field),
        '[+prefix+]user_attributes',
        sprintf("internalKey='%d'", $userid)
    );
    if (!$rs) {
        webAlert("An error occurred while attempting to update the user's attributes.");
        exit;
    }

    // Save user settings
    saveUserSettings($userid);

    // invoke OnManagerSaveUser event
    $tmp = [
        'mode' => 'upd',
        'userid' => $userid,
        'username' => $username,
        'userpassword' => newPassword(),
        'useremail' => post_strvar('email'),
        'userfullname' => post_strvar('fullname'),
        'userroleid' => userformv_int('role'),
        'oldusername' => hasOldUserName() ? post_strvar('oldusername') : '',
        'olduseremail' => hasOldUserEmail() ? post_strvar('oldemail') : ''
    ];
    evo()->invokeEvent('OnManagerSaveUser', $tmp);

    // invoke OnManagerChangePassword event
    if ($changePassword) {
        $tmp = [
            'userid' => $userid,
            'username' => $username,
            'userpassword' => newPassword()
        ];
        evo()->invokeEvent('OnManagerChangePassword', $tmp);
    }

    if ($notifyMethod === 'e' && $changePassword) {
        sendMailMessage(post_strvar('email'), $username, newPassword(), post_strvar('fullname'));
    }

    // invoke OnUserFormSave event
    $tmp = [
        'mode' => 'upd',
        'id' => $userid
    ];
    evo()->invokeEvent('OnUserFormSave', $tmp);
    evo()->clearCache();
    // put the user in the user_groups he/ she should be in
    // first, check that up_perms are switched on!
    if (evo()->config['use_udperms'] == 1) {
        // as this is an existing user, delete his/ her entries in the groups before saving the new groups
        $rs = db()->delete(evo()->getFullTableName('member_groups'), sprintf("member='%d'", $userid));
        if (!$rs) {
            webAlert('An error occurred while attempting to delete previous user_groups entries.');
            exit;
        }
        foreach (to_idsvar(userformv('user_groups')) as $user_group) {
            $rs = db()->insert(
                ['user_group' => $user_group, 'member' => $userid],
                '[+prefix+]member_groups'
            );
            if (!$rs) {
                webAlert('An error occurred while attempting to add the user to a user_group.');
                exit;
            }
        }
    }
    // end of user_groups stuff!
    if ($isSelf && $notifyMethod !== 's') {
    ?>

        <body bgcolor='#efefef'>
            <form id="evoLogoutForm" method="post" action="index.php?a=8" target="_top" data-no-ajax><?= csrfTokenField() ?></form>
            <script language="JavaScript">
                alert(<?= js_literal(stripcslashes(lang('user_changeddata'))) ?>);
                document.getElementById('evoLogoutForm').submit();
            </script>
        </body>
    <?php
        exit;
    }
    evo()->getSettings();
    if ($isSelf && (string)sessionv('mgrRole') !== (string)userformv_int('role')) {
        sessionv('*mgrRole', (string)userformv_int('role'));
        evo()->webAlertAndQuit(lang('save_user_relogin'), 'index.php?a=75');
        exit;
    }
    $afterSave = EvoFormFlow::postedAfterSave();
    if (!$changePassword || $notifyMethod !== 's') {
        if (saveUserMode() === 'profile') {
            header('Location: ' . 'index.php?refresh=tree&a=2');
            exit;
        }
        header('Location: ' . EvoFormFlow::afterSaveUrl('user', $userid, $afterSave, ['refresh' => 'tree']));
        exit;
    }

    // パスワードを画面に表示するときの「閉じる」。新規・メール通知と同じくツリーを読み直す
    $stayUrl = $isSelf ? 'index.php?a=8' : EvoFormFlow::afterSaveUrl('user', $userid, $afterSave, ['refresh' => 'tree']);
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <h1><?= lang('user_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate">
                <a href="<?= hsc($stayUrl) ?>"<?= $isSelf ? ' data-evo-post="full"' : '' ?>><?= $isSelf ? lang('logout') : lang('close') ?>
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
                        hsc($username),
                        hsc(newPassword())
                    ) . ($isSelf ? ' ' . lang('user_changeddata') : ''); ?>
                </p>
            </div>
        </div>
    </div>
<?php

    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
