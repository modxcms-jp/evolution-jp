<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}


include_once __DIR__ . '/mutate_user/form_values.php';
require_once __DIR__ . '/mutate_user/user_form_rows.php';
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

$action = request_intvar('a');

switch ($action) {
    case 88:
        if (!evo()->hasPermission('edit_web_user')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 87:
        if (!evo()->hasPermission('new_web_user')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

$user = request_intvar('id');
if ($action === 87) {
    $user = 0; // 新規は id を使わない
}


// check to see the snippet editor isn't locked
$username = $user ? manager()->editLockedBy(EvoFormFlow::screen('web_user', 'edit'), $user) : '';
if ($username !== '') {
    alert()->setError(5, sprintf(lang('lock_msg'), $username, 'web user'));
    alert()->dumpError();
}
// end check for lock

if ($action === 88) {
    // get user attributes
    $rs = db()->select('*', evo()->getFullTableName('web_user_attributes'), "internalKey='" . (int) $user . "'");
    $limit = db()->count($rs);
    if (!$limit) {
        echo "No user returned!<p>";
        exit;
    }
    $userdata = db()->getRow($rs);

    // get user settings
    $rs = db()->select('*', evo()->getFullTableName('web_user_settings'), "webuser='" . (int) $user . "'");
    $usersettings = [];
    while ($row = db()->getRow($rs)) {
        $usersettings[$row['setting_name']] = $row['setting_value'];
    }

    // get user name
    $rs = db()->select('*', evo()->getFullTableName('web_users'), "id='" . (int) $user . "'");
    $limit = db()->count($rs);
    if ($limit > 1) {
        echo "More than one user returned while getting username!<p>";
        exit;
    }
    if ($limit < 1) {
        echo "No user returned while getting username!<p>";
        exit;
    }
    $usernamedata = db()->getRow($rs);
    sessionv('*itemname', webuser('username'));
} else {
    $userdata = [];
    $usersettings = [];
    $usernamedata = [];
    sessionv('*itemname', "New web user");
}

// restore saved form
$formRestored = false;
// パスワードは差し戻さない（saveFormValues() が残さない）。変えようとしていたかだけを、欄を開き直すために見る
$passwordRestored = false;
if (manager()->hasFormValues()) {
    $form_v = manager()->loadFormValues();
    $passwordRestored = ($form_v['newpassword'] ?? '') === '1';
    // 差し戻す値は文字列だけにする（配列は、配列で受ける項目だけ）
    $form_v = restorableFormValues($form_v, ['user_groups', 'allowed_days']);
    // restore post values
    $userdata = array_merge($userdata, $form_v);
    // 日付は解釈できればタイムスタンプに、できなければ入力の文字列のまま残す（入力欄は dateInputValue() で出す）
    foreach (['dob', 'blockeduntil', 'blockedafter'] as $dateField) {
        $userdata[$dateField] = manager()->restoredDateValue($userdata[$dateField] ?? '');
    }
    $usernamedata['username'] = $userdata['newusername'] ?? '';
    if (isset($form_v['oldusername'])) {
        $usernamedata['oldusername'] = $form_v['oldusername'];
    }
    $usersettings = array_merge($usersettings, $form_v);
    $allowedDays = $form_v['allowed_days'] ?? '';
    $usersettings['allowed_days'] = is_array($allowedDays) ? implode(",", $allowedDays) : $allowedDays;
}

function attribute($name, $default = null)
{
    global $userdata;

    return $userdata[$name] ?? $default;
}

function webuser($name, $default = null)
{
    global $usernamedata;

    return $usernamedata[$name] ?? $default;
}

// include the country list language file
$_country_lang = [];
$managerLanguage = config('manager_language');
if ($managerLanguage != "english" && is_file(MODX_CORE_PATH . "lang/country/{$managerLanguage}_country.inc.php")) {
    include_once(MODX_CORE_PATH . "lang/country/{$managerLanguage}_country.inc.php");
} else {
    include_once(MODX_CORE_PATH . 'lang/country/english_country.inc.php');
}

?>
<?= userFormScript() ?>
<script type="text/javascript">
    function deleteuser() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_user'))) ?>) == true) {
            EvoCsrf.post("index.php?id=" + document.userform.id.value + "&a=90");
        }
    }
</script>

<form action="index.php?a=89" method="post" name="userform" enctype="multipart/form-data">
    <?php
    // invoke OnWUsrFormPrerender event
    $tmp = ["id" => $user];
    $evtOut = evo()->invokeEvent("OnWUsrFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('web_user')) ?>" />
    <input type="hidden" name="id" value="<?= (int) $user ?>" />
    <input type="hidden" name="blockedmode"
        value="<?= isUserBlocked($userdata) ? "1" : "0" ?>" />

    <h1><?= lang('web_user_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_web_user')): ?>
                <li id="Button1" class="mutate"><a href="#" onclick="documentDirty=false; document.userform.save.click();"><?= lang('update') ?></a><span
                        class="and"> + </span>
                    <select id="stay" name="after_save">
                        <?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), evo()->hasPermission('new_web_user')) ?>
                    </select>
                </li>
            <?php endif; ?>
            <?php if ($action === 88) { ?>
                <li><a href="#" onclick="deleteuser();"><?= lang('delete') ?></a>
                </li>
            <?php } ?>
            <li><a href="#" onclick="EvoShell.go('index.php?a=99');"><?= lang('cancel') ?></a></li>
        </ul>
    </div>

    <!-- Tab Start -->
    <div class="sectionBody">
        <div class="tab-pane" id="webUserPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= lang("login_settings") ?></h2>
                <table class="settings">
                    <?php
                    $oldUsername = webuser('oldusername');
                    // 名前を変えた入力のまま差し戻したときと新規は、初めから名前の入力欄を出す
                    $nameEditing = $action === 87 || ($oldUsername && $oldUsername != webuser('username'));

                    echo userFormBlockedNotice($userdata, (bool) attribute('id'));

                    echo userFormNameRows([
                        'username' => webuser('username'),
                        'oldusername' => $oldUsername ?: webuser('username'),
                        'hasName' => (bool) attribute('id'),
                        'editing' => $nameEditing,
                        'confirmRename' => true,
                    ]);

                    echo userFormPasswordRow($action === 87 ? 'new' : 'set', ['restored' => $passwordRestored]);

                    echo userFormEmailRow($userdata);
                    ?>
                </table>
                <?php
                // ログインの状態（設定ではないので、設定の定義には入れない。編集のときだけ出る）
                if ($action === 88) {
                    ?>
                    <table class="settings">
                        <?= userFormLoginStateRows($userdata) ?>
                    </table>
                    <?php
                }
                ?>
            </div>
            <!-- Profile -->
            <div class="tab-page" id="tabProfile">
                <h2 class="tab"><?= lang("profile") ?></h2>
                <table class="settings">
                    <?= userFormProfileRows($userdata, $_country_lang) ?>
                </table>
            </div>
            <!-- ログイン先・ログインを許可する IP と曜日（設定の定義の scope web） -->
            <?= (new EvoSettingsForm(
                EvoSettingsForm::definition(),
                $usersettings,
                EvoSettingsForm::storedValues(),
                'web'
            ))->render() ?>
            <?php
            if (config('use_udperms') == 1) {
                $groupsarray = [];

                if ($action === 88) { // only do this bit if the user is being edited
                    $rs = db()->select('*', evo()->getFullTableName('web_groups'), "webuser='" . (int) $user . "'");
                    $limit = db()->count($rs);
                    for ($i = 0; $i < $limit; $i++) {
                        $currentgroup = db()->getRow($rs);
                        $groupsarray[$i] = $currentgroup['webgroup'];
                    }
                }
                // 保存に失敗した差し戻しなら、送った所属グループを使う
                if (is_array($userdata['user_groups'] ?? null)) {
                    $groupsarray = $userdata['user_groups'];
                }
            ?>
                <!-- Access -->
                <div class="tab-page" id="tabAccess">
                    <h2 class="tab"><?= lang("web_access_permissions") ?></h2>
                    <div class="sectionHeader"><?= lang('web_access_permissions') ?></div>
                    <div class="sectionBody">
                        <?php
                        echo "<p>" . lang('access_permissions_user_message') . "</p>";
                        $rs = db()->select('name,id', evo()->getFullTableName('webgroup_names'), '', 'name');
                        $tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
                        while ($row = db()->getRow($rs)) {
                            $echo = $tpl;
                            $echo = str_replace('[+id+]', (int) $row['id'], $echo);
                            $echo = str_replace(
                                '[+checked+]',
                                (in_array($row['id'], $groupsarray) ? 'checked="checked"' : ''),
                                $echo
                            );
                            $echo = str_replace('[+name+]', hsc($row['name']), $echo);
                            echo $echo;
                        }
                        ?>
                    </div>
                <?php
            }
                ?>
                </div>

        </div>

    </div>
    <input type="submit" name="save" class="hidden">
    <?php
    // invoke OnWUsrFormRender event
    $tmp = ["id" => $user];
    $evtOut = evo()->invokeEvent("OnWUsrFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
</form>
<script type="text/javascript">
    var remember = <?= EvoFormFlow::rememberTab() ? 'true' : 'false' ?>;
    tpUser = new WebFXTabPane(document.getElementById("webUserPane"), remember);
</script>
<?php
