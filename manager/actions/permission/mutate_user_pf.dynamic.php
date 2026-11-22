<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
$action = request_intvar('a');
if ($action !== 74 || !evo()->hasPermission('change_password')) {
    alert()->setError(3);
    alert()->dumpError();
}

include_once __DIR__ . '/mutate_user/form_values.php';
require_once __DIR__ . '/mutate_user/user_form_rows.php';
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

$userid = (int) evo()->getLoginUserID();

// get user attribute
$rs = db()->select('*', '[+prefix+]user_attributes', "internalKey='{$userid}'");
$total = db()->count($rs);
if ($total > 1) {
    exit('More than one user returned!<p>');
} elseif ($total < 1) {
    exit('No user returned!<p>');
}

$userdata = db()->getRow($rs);

// get user settings
$rs = db()->select('*', '[+prefix+]user_settings', "user='{$userid}'");
$usersettings = [];
while ($row = db()->getRow($rs)) {
    $usersettings[$row['setting_name']] = $row['setting_value'];
}

// get user name
$rs = db()->select('*', '[+prefix+]manager_users', "id='{$userid}'");
$total = db()->count($rs);
if ($total > 1) {
    exit('More than one user returned while getting username!<p>');
} elseif ($total < 1) {
    exit('No user returned while getting username!<p>');
}

$usernamedata = db()->getRow($rs);

sessionv('*itemname', $usernamedata['username']);

// restore saved form
$formRestored = false;
if (manager()->hasFormValues()) {
    $form_v = manager()->loadFormValues();
    // 差し戻す値は文字列だけにする（配列は、配列で受ける項目だけ）
    $form_v = restorableFormValues($form_v, ['user_groups', 'allowed_days']);
    // restore post values
    $userdata = array_merge($userdata, $form_v);
    // 生年月日は解釈できればタイムスタンプに、できなければ入力の文字列のまま残す（入力欄は dateInputValue() で出す）
    $userdata['dob'] = manager()->restoredDateValue($userdata['dob'] ?? '');
    $usersettings = array_merge($usersettings, $userdata);
}

function userData($key, $default = null)
{
    global $userdata;

    return $userdata[$key] ?? $default;
}

// 管理画面・エディターのタブ（値は差し戻し込みの $usersettings）
$settingsForm = new EvoSettingsForm(
    EvoSettingsForm::definition(),
    $usersettings,
    EvoSettingsForm::storedValues(),
    'profile'
);

// include the country list language file
$_country_lang = [];
$lcdir_path = MODX_CORE_PATH . 'lang/country/';
include_once($lcdir_path . 'english_country.inc.php');
$lang_path = sprintf($lcdir_path . '%s_country.inc.php', config('manager_language'));
if (config('manager_language') != "english" && is_file($lang_path)) {
    include_once($lang_path);
}
?>
<?= userFormScript() ?>

<form action="index.php?a=32" method="post" name="userform" enctype="multipart/form-data">
    <?php

    // invoke OnUserFormPrerender event
    $tmp = ["id" => $userid];
    $pluginSettingNames = [];
    $evtOut = evo()->invokeEvent("OnUserFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
        $pluginSettingNames = EvoSettingsForm::fieldNames(implode("", $evtOut));
    }
    ?>
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('user')) ?>">
    <input type="hidden" name="userid" value="<?= (int) $userid ?>">
    <input type="hidden" name="newusername" value="<?= hsc($usernamedata['username']) ?>" />
    <input type="hidden" name="role" value="<?= (int)userData('role') ?>" />
    <input type="hidden" name="failedlogincount" value="<?= (int)userData('failedlogincount') ?>">
    <input type="hidden" name="blockedmode"
        value="<?= isUserBlocked($userdata) ? "1" : "0" ?>" />

    <h1><?= lang('profile') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button1" class="mutate">
                <a href="#" onclick="documentDirty=false; document.userform.save.click();">
                    <?= lang('update') ?>
                </a>
            </li>
            <li id="Button5" class="mutate"><a href="#" onclick="EvoShell.go('index.php?a=2');"><?= lang('cancel') ?></a></li>
        </ul>
    </div>
    <!-- Tab Start -->
    <div class="sectionBody">
        <div class="tab-pane" id="userPane">
            <!-- Profile -->
            <div class="tab-page" id="tabProfile">
                <h2 class="tab"><?= lang("profile") ?></h2>
                <table class="settings">
                    <?php
                    echo userFormPasswordRow(false, false);
                    echo userFormEmailRow($userdata);
                    echo userFormProfileRows($userdata, $_country_lang);
                    ?>
                </table>
            </div>

            <!-- 管理画面・エディターの設定（グローバル設定と同じ定義から、プロフィールで変えられる行だけを描く） -->
            <?php
            echo $settingsForm->render();
            // 本体の欄の名前は names('profile') から引くので、プラグインの欄だけを覚える
            $pluginSettingNames = array_merge($pluginSettingNames, $settingsForm->pluginFieldNames());
            ?>
        </div>
    </div>
    <input type="submit" name="save" class="hidden">
    <?php

    // invoke OnUserFormRender event
    $tmp = ["id" => $userid];
    $evtOut = evo()->invokeEvent("OnUserFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
        $pluginSettingNames = array_merge($pluginSettingNames, EvoSettingsForm::fieldNames(implode("", $evtOut)));
    }
    // 保存時に、プラグインが出した設定だけを変えられるように覚えておく（save_user.functions.php の allowedSettingNames()）
    sessionv('*pluginSettingNames.profile', array_values(array_unique($pluginSettingNames)));
    ?>
</form>
<?php include_once __DIR__ . '/mutate_user/tpl/changed_settings.php'; ?>
<script type="text/javascript">
    var remember = <?= EvoFormFlow::rememberTab() ? 'true' : 'false' ?>;
    tpUser = new WebFXTabPane(document.getElementById("userPane"), remember);
</script>
