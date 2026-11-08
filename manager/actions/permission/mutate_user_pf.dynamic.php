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

$icons_path = manager_style_image_path('icons');

// get user name
$rs = db()->select('*', '[+prefix+]manager_users', "id='{$userid}'");
$total = db()->count($rs);
if ($total > 1) {
    exit('More than one user returned while getting username!<p>');
} elseif ($total < 1) {
    exit('No user returned while getting username!<p>');
}

$usernamedata = db()->getRow($rs);

$_SESSION['itemname'] = $usernamedata['username'];

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
$lang_path = sprintf($lcdir_path . '%s_country.inc.php', $modx->config['manager_language']);
if ($modx->config['manager_language'] != "english" && is_file($lang_path)) {
    include_once($lang_path);
}
?>
<script type="text/javascript">
    function changestate(element) {
        documentDirty = true;
        currval = eval(element).value;
        if (currval == 1) {
            eval(element).value = 0;
        } else {
            eval(element).value = 1;
        }
    }

    function changePasswordState(element) {
        currval = eval(element).value;
        if (currval == 1) {
            document.getElementById("passwordBlock").style.display = "block";
        } else {
            document.getElementById("passwordBlock").style.display = "none";
        }
    }

    // change name
    function changeName() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_name_change'))) ?>) == true) {
            var e1 = document.getElementById("showname");
            var e2 = document.getElementById("editname");
            e1.style.display = "none";
            e2.style.display = "table-row";
        }
    }

    function BrowseServer() {
        window.evoOpenFilePicker(<?= js_literal(evo()->config('base_url') . 'manager/media/browser/evo/browser.php?type=images') ?>);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = <?= js_literal(evo()->config('base_url')) ?> + url;
    }
</script>

<form action="index.php?a=32" method="post" name="userform" enctype="multipart/form-data">
    <?php

    // invoke OnUserFormPrerender event
    $tmp = ["id" => $userid];
    $pluginSettingNames = [];
    $evtOut = evo()->invokeEvent("OnUserFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
        $pluginSettingNames = fieldNames(implode("", $evtOut));
    }
    ?>
    <input type="hidden" name="mode" value="74">
    <input type="hidden" name="userid" value="<?= (int) $userid ?>">
    <input type="hidden" name="newusername" value="<?= hsc($usernamedata['username']) ?>" />
    <input type="hidden" name="role" value="<?= (int)userData('role') ?>" />
    <input type="hidden" name="failedlogincount" value="<?= (int)userData('failedlogincount') ?>">
    <input type="hidden" name="blockedmode"
        value="<?= isUserBlocked($userdata) ? "1" : "0" ?>" />

    <h1><?= $_lang['profile'] ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button1" class="mutate">
                <a href="#" onclick="documentDirty=false; document.userform.save.click();">
                    <?= $_lang['update'] ?>
                </a>
            </li>
            <li id="Button5" class="mutate"><a href="#" onclick="EvoShell.go('index.php?a=2');"><?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>
    <!-- Tab Start -->
    <div class="sectionBody">
        <?= EvoSettingsForm::style() ?>
        <div class="tab-pane" id="userPane">
            <!-- Profile -->
            <div class="tab-page" id="tabProfile">
                <h2 class="tab"><?= $_lang["profile"] ?></h2>
                <table class="settings">
                    <tr>
                        <th valign="top"><?= $_lang['change_password_new'] . ":" ?></th>
                        <td><label><input name="newpasswordcheck" type="checkbox"
                                    onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);"><input
                                    type="hidden" name="newpassword"
                                    value="0" /></label><br />
                            <span style="display:none"
                                id="passwordBlock">
                                <fieldset style="width:300px">
                                    <legend><b><?= $_lang['password_gen_method'] ?></b></legend>
                                    <label><input type=radio name="passwordgenmethod"
                                            value="g" <?= post_strvar('passwordgenmethod') === "spec" ? "" : 'checked="checked"' ?> /><?= $_lang['password_gen_gen'] ?></label><br />
                                    <label><input type=radio name="passwordgenmethod"
                                            value="spec" <?= post_strvar('passwordgenmethod') === "spec" ? 'checked="checked"' : "" ?>><?= $_lang['password_gen_specify'] ?></label><br />
                                    <div style="padding-left:20px">
                                        <label for="specifiedpassword" style="width:120px"><?= $_lang['change_password_new'] ?>:</label>
                                        <input type="password" id="specifiedpassword" name="specifiedpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="new-password" /><br />
                                        <label for="confirmpassword" style="width:120px"><?= $_lang['change_password_confirm'] ?>:</label>
                                        <input type="password" id="confirmpassword" name="confirmpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="new-password" /><br />
                                        <small><span class="warning" style="font-weight:normal"><?= $_lang['password_gen_length'] ?></span></small>
                                    </div>
                                </fieldset>
                                <input type="hidden" name="passwordnotifymethod" value="s" />
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_email'] ?>:</th>
                        <td>
                            <input type="text" name="email" class="inputBox" autocomplete="email"
                                value="<?= hsc(userData('email')) ?>" />
                            <input type="hidden" name="oldemail"
                                value="<?= hsc(userData('oldemail') ?: userData('email')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_full_name'] ?>:</th>
                        <td><input type="text" name="fullname" class="inputBox" autocomplete="name"
                                value="<?= hsc(userData('fullname')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_phone'] ?>:</th>
                        <td><input type="text" name="phone" class="inputBox" autocomplete="tel"
                                value="<?= hsc(userData('phone')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_mobile'] ?>:</th>
                        <td><input type="text" name="mobilephone" class="inputBox" autocomplete="tel"
                                value="<?= hsc(userData('mobilephone')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_fax'] ?>:</th>
                        <td><input type="text" name="fax" class="inputBox" autocomplete="tel"
                                value="<?= hsc(userData('fax')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_street'] ?>:</th>
                        <td><input type="text" name="street" class="inputBox" autocomplete="street-address"
                                value="<?= hsc(userData('street')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_city'] ?>:</th>
                        <td><input type="text" name="city" class="inputBox" autocomplete="address-level2"
                                value="<?= hsc(userData('city')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_state'] ?>:</th>
                        <td><input type="text" name="state" class="inputBox" autocomplete="address-level1"
                                value="<?= hsc(userData('state')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_zip'] ?>:</th>
                        <td><input type="text" name="zip" class="inputBox" autocomplete="postal-code"
                                value="<?= hsc(userData('zip')) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_country'] ?>:</th>
                        <td>
                            <select size="1" name="country" class="inputBox" autocomplete="country-name">
                                <?php $chosenCountry = post_strvar('country', $userdata['country']); ?>
                                <option value="" <?= selected(empty($chosenCountry)) ?>>&nbsp;</option>
                                <?php
                                foreach ($_country_lang as $key => $country) {
                                    echo '<option value="' . $key . '"' . selected(isset($chosenCountry) && $chosenCountry == $key) . ">{$country}</option>\n";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_dob'] ?>:</th>
                        <td>
                            <input type="text" id="dob" name="dob" class="DatePicker"
                                value="<?= manager()->dateInputValue(userData('dob'), 'dateOnly') ?>" onblur="documentDirty=true;">
                            <a onclick="document.userform.dob.value=''; return true;"
                                style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                    src="<?= $icons_path ?>cal_nodate.gif"
                                    border="0"
                                    alt="<?= $_lang['remove_date'] ?>"></a>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_gender'] ?>:</th>
                        <td><select name="gender" class="inputBox">
                                <option value=""></option>
                                <option
                                    value="1" <?= selected(userData('gender') == 1) ?>><?= $_lang['user_male'] ?></option>
                                <option
                                    value="2" <?= selected(userData('gender') == 2) ?>><?= $_lang['user_female'] ?></option>
                                <option
                                    value="3" <?= selected(userData('gender') == 3) ?>><?= $_lang['user_other'] ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th valign="top"><?= $_lang['comment'] ?>:</th>
                        <td>
                            <textarea type="text" name="comment" class="inputBox"
                                rows="5"><?= hsc(userData('comment')) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang["user_photo"] ?></th>
                        <td><input type="text" maxlength="255" style="width: 150px;" name="photo"
                                value="<?= hsc(userData('photo')) ?>" />
                            <input type="button" value="<?= $_lang['insert'] ?>" onclick="BrowseServer();" />
                            <div><?= $_lang["user_photo_message"] ?></div>
                            <div>
                                <?php
                                if (post_strvar('photo')) {
                                    $photo = post_strvar('photo');
                                } elseif (!empty($userdata['photo'])) {
                                    $photo = $userdata['photo'];
                                } else {
                                    $photo = $modx->config['base_url'] . 'manager/' . $_style['tx'];
                                }

                                if (substr($photo, 0, 1) !== '/' && !preg_match('@^https?://@', $photo)) {
                                    $photo = $modx->config['base_url'] . $photo;
                                }
                                ?>
                                <img name="iphoto" src="<?= hsc($photo) ?>" />
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 管理画面・エディターの設定（グローバル設定と同じ定義から、プロフィールで変えられる行だけを描く） -->
            <?php
            $settingsHtml = $settingsForm->render();
            echo $settingsHtml;
            // 本体の欄の名前は names('profile') から引くので、プラグインの欄だけを覚える
            $pluginSettingNames = array_merge(
                $pluginSettingNames,
                array_diff(fieldNames($settingsHtml), EvoSettingsForm::names('profile'))
            );
            ?>
        </div>
    </div>
    <input type="submit" name="save" style="display:none">
    <?php

    // invoke OnUserFormRender event
    $tmp = ["id" => $userid];
    $evtOut = evo()->invokeEvent("OnUserFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
        $pluginSettingNames = array_merge($pluginSettingNames, fieldNames(implode("", $evtOut)));
    }
    // 保存時に、プラグインが出した設定だけを変えられるように覚えておく（save_user.functions.php の profileSettingNames()）
    sessionv('*profileSettingNames', $pluginSettingNames);
    ?>
</form>
<?php include_once __DIR__ . '/mutate_user/tpl/changed_settings.php'; ?>
<script type="text/javascript">
    var remember = <?= EvoFormFlow::rememberTab() ? 'true' : 'false' ?>;
    tpUser = new WebFXTabPane(document.getElementById("userPane"), remember);
</script>
<?php

// HTML の入力欄（input / select / textarea / button）の名前。コメントや script などの送信されない部分は見ない。
// PHP が POST を受け取るときと同じ形にそろえる（「[」より前だけを使い、「.」と空白は「_」になる）
function fieldNames($html)
{
    $html = preg_replace(
        ['@<!--.*?-->@s', '@<(script|template|style|noscript)\b.*?</\1\s*>@is', '@(<textarea\b[^>]*>).*?</textarea\s*>@is'],
        ['', '', '$1'],
        $html
    );
    preg_match_all('@<(?:input|select|textarea|button)\b[^>]*>@i', $html, $tags);
    $names = [];
    foreach ($tags[0] as $tag) {
        // disabled の欄は送信されない
        if (preg_match('@\sdisabled\b@i', $tag)) {
            continue;
        }
        if (!preg_match('@\sname\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))@i', $tag, $m)) {
            continue;
        }
        $name = html_entity_decode(implode('', array_slice($m, 1)), ENT_QUOTES);
        $names[] = str_replace(['.', ' '], '_', strstr($name, '[', true) ?: $name);
    }
    return $names;
}

function checkbox($name, $value, $label, $cond)
{
    global $modx;
    $tpl = '<label><input type="checkbox" name="[+name+]" value="[+value+]" [+checked+] />[+label+]</label>';
    $ph['name'] = $name;
    $ph['value'] = $value;
    $ph['label'] = $label;
    $ph['checked'] = checked($cond);
    return $modx->parseText($tpl, $ph);
}

function checked($cond)
{
    if ($cond) {
        return 'checked';
    }
    return '';
}

function selected($cond)
{
    if ($cond) {
        return 'selected';
    }
    return '';
}
