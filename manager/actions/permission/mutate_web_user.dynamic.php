<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}


include_once __DIR__ . '/mutate_user/form_values.php';

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

$icons_path = manager_style_image_path('icons');

$user = request_intvar('id');
if ($action === 87) {
    $user = 0; // 新規は id を使わない
}


// check to see the snippet editor isn't locked
$rs = db()->select('internalKey, username', evo()->getFullTableName('active_users'), "action='88' AND id='" . (int) $user . "'");
$limit = db()->count($rs);
if ($limit > 1) {
    for ($i = 0; $i < $limit; $i++) {
        $lock = db()->getRow($rs);
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang["lock_msg"], $lock['username'], "web user");
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
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
    $_SESSION['itemname'] = webuser('username');
} else {
    $userdata = [];
    $usersettings = [];
    $usernamedata = [];
    $_SESSION['itemname'] = "New web user";
}

// restore saved form
$formRestored = false;
if (manager()->hasFormValues()) {
    $form_v = manager()->loadFormValues();
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

function setting($name, $default = null)
{
    global $usersettings;

    return $usersettings[$name] ?? $default;
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

    function changeblockstate(element, checkelement) {
        const blocked = document.getElementById('blocked');
        currval = eval(element).value;
        if (currval == 1) {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_unblock'))) ?>) == true) {
                document.userform.blocked.value = 0;
                document.userform.blockeduntil.value = "";
                document.userform.blockedafter.value = "";
                document.userform.failedlogincount.value = 0;
                blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('unblock_message'))) ?>}));
                blocked.className = "TD";
                eval(element).value = 0;
            } else {
                eval(checkelement).checked = true;
            }
        } else {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_block'))) ?>) == true) {
                document.userform.blocked.value = 1;
                blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('block_message'))) ?>}));
                blocked.className = "warning";
                eval(element).value = 1;
            } else {
                eval(checkelement).checked = false;
            }
        }
    }

    function resetFailed() {
        document.userform.failedlogincount.value = 0;
        document.getElementById("failed").innerHTML = "0";
    }

    function deleteuser() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_user'))) ?>) == true) {
            EvoCsrf.post("index.php?id=" + document.userform.id.value + "&a=90");
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
        window.evoOpenFilePicker(<?= js_literal(config('base_url') . 'manager/media/browser/evo/browser.php?type=images') ?>);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = <?= js_literal(config('base_url')) ?> + url;
    }
</script>

<style type="text/css">
    table.settings {
        border-collapse: collapse;
        width: 100%;
    }

    table.settings tr {
        border-bottom: 1px dotted #ccc;
    }

    table.settings th {
        font-size: inherit;
        vertical-align: top;
        text-align: left;
    }

    table.settings th,
    table.settings td {
        padding: 5px;
    }
</style>

<form action="index.php?a=89" method="post" name="userform" enctype="multipart/form-data">
    <?php
    // invoke OnWUsrFormPrerender event
    $tmp = ["id" => $user];
    $evtOut = evo()->invokeEvent("OnWUsrFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="mode" value="<?= (int) $action ?>" />
    <input type="hidden" name="id" value="<?= (int) $user ?>" />
    <input type="hidden" name="blockedmode"
        value="<?= isUserBlocked($userdata) ? "1" : "0" ?>" />

    <h1><?= $_lang['web_user_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_web_user')): ?>
                <li class="mutate"><a href="#" onclick="documentDirty=false; document.userform.save.click();"><?= $_lang['update'] ?></a><span
                        class="and"> + </span>
                    <select id="stay" name="stay">
                        <?php if (evo()->hasPermission('new_web_user')) { ?>
                            <option id="stay1"
                                value="1" <?= request_intvar('stay') === 1 ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <?php } ?>
                        <option id="stay2"
                            value="2" <?= request_intvar('stay') === 2 ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3"
                            value="" <?= request_intvar('stay') === 0 ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php if ($action === 88) { ?>
                <li><a href="#" onclick="deleteuser();"><?= $_lang['delete'] ?></a>
                </li>
            <?php } ?>
            <li><a href="#" onclick="EvoShell.go('index.php?a=99');"><?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <!-- Tab Start -->
    <div class="sectionBody">
        <div class="tab-pane" id="webUserPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= $_lang["login_settings"] ?></h2>
                <table class="settings">
                    <tr>
                        <td>
                            <span id="blocked"
                                class="warning"><?php if (isUserBlocked($userdata)) { ?>
                                    <b><?= $_lang['user_is_blocked'] ?></b><?php } ?></span>
                        </td>
                    </tr>
                    <?php if (attribute('id')) { ?>
                        <tr id="showname"
                            style="display: <?= ($action === 88 && (!webuser('oldusername') || webuser('oldusername') == webuser('username'))) ? 'table-row' : 'none' ?> ">
                            <td colspan="2">
                                <img src="<?= $_style['icons_user'] ?>"
                                    alt="." />&nbsp;<b><?= hsc(webuser('oldusername') ?: webuser('username')) ?></b>
                                - <span class="comment"><a href="#"
                                        onclick="changeName();return false;"><?= $_lang["change_name"] ?></a></span>
                                <input type="hidden" name="oldusername"
                                    value="<?= hsc(webuser('oldusername') ?: webuser('username')) ?>" />
                            </td>
                        </tr>
                    <?php } ?>
                    <tr id="editname"
                        style="display:<?= $action === 87 || (webuser('oldusername') && webuser('oldusername') != webuser('username')) ? 'table-row' : 'none' ?>">
                        <th><?= $_lang['username'] ?>:</th>
                        <td><input type="text" name="newusername" class="inputBox" autocomplete="username"
                                value="<?= hsc(post_strvar('newusername', webuser('username'))) ?>"
                                maxlength="100" /></td>
                    </tr>
                    <tr>
                        <th valign="top"><?= $action === 87 ? $_lang['password'] . ":" : $_lang['change_password_new'] . ":" ?></th>
                        <td>
                            <?php if ($action !== 87): ?>
                                <input name="newpasswordcheck" type="checkbox"
                                    onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);">
                            <?php endif; ?>
                            <input type="hidden" name="newpassword"
                                value="<?= $action === 87 ? 1 : 0 ?>" /><br />
                            <div style="display:<?= $action === 87 ? "block" : "none" ?>"
                                id="passwordBlock">
                                <fieldset style="width:300px;padding:0;">
                                    <label><input type=radio name="passwordgenmethod"
                                            value="g" <?= post_strvar('passwordgenmethod') === "spec" ? "" : 'checked="checked"' ?> /><?= $_lang['password_gen_gen'] ?>
                                    </label><br />
                                    <label><input type=radio name="passwordgenmethod"
                                            value="spec" <?= post_strvar('passwordgenmethod') === "spec" ? 'checked="checked"' : "" ?>><?= $_lang['password_gen_specify'] ?>
                                    </label> <br />
                                    <div style="padding-left:20px">
                                        <label for="specifiedpassword"
                                            style="width:120px"><?= $_lang['change_password_new'] ?>
                                            :</label>
                                        <input type="password" id="specifiedpassword" name="specifiedpassword"
                                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="new-password" /><br />
                                        <label for="confirmpassword"
                                            style="width:120px"><?= $_lang['change_password_confirm'] ?>
                                            :</label>
                                        <input type="password" id="confirmpassword" name="confirmpassword"
                                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="new-password" /><br />
                                        <span class="warning"
                                            style="font-weight:normal"><?= $_lang['password_gen_length'] ?></span>
                                    </div>
                                </fieldset>
                                <br />
                                <fieldset style="width:300px;padding:0;">
                                    <label><input type=radio name="passwordnotifymethod"
                                            value="e" <?= post_strvar('passwordnotifymethod') === "e" ? 'checked="checked"' : "" ?> /><?= $_lang['password_method_email'] ?>
                                    </label><br />
                                    <label><input type=radio name="passwordnotifymethod"
                                            value="s" <?= post_strvar('passwordnotifymethod') === "e" ? "" : 'checked="checked"' ?> /><?= $_lang['password_method_screen'] ?>
                                    </label>
                                </fieldset>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_email'] ?>:</th>
                        <td>
                            <input type="text" name="email" class="inputBox" autocomplete="email"
                                value="<?= hsc(post_strvar('email', attribute('email'))) ?>" />
                            <input type="hidden" name="oldemail"
                                value="<?= hsc(attribute('oldemail') ?: attribute('email')) ?>" />
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Profile -->
            <div class="tab-page" id="tabProfile">
                <h2 class="tab"><?= $_lang["profile"] ?></h2>
                <table class="settings">
                    <tr>
                        <th><?= $_lang['user_full_name'] ?>:</th>
                        <td><input type="text" name="fullname" class="inputBox" autocomplete="name"
                                value="<?= hsc(post_strvar('fullname', attribute('fullname'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_phone'] ?>:</th>
                        <td><input type="text" name="phone" class="inputBox" autocomplete="tel"
                                value="<?= hsc(post_strvar('phone', attribute('phone'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_mobile'] ?>:</th>
                        <td><input type="text" name="mobilephone" class="inputBox" autocomplete="tel"
                                value="<?= hsc(post_strvar('mobilephone', attribute('mobilephone'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_fax'] ?>:</th>
                        <td><input type="text" name="fax" class="inputBox" autocomplete="tel"
                                value="<?= hsc(post_strvar('fax', attribute('fax'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_street'] ?>:</th>
                        <td><input type="text" name="street" class="inputBox" autocomplete="street-address"
                                value="<?= hsc(attribute('street')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_city'] ?>:</th>
                        <td><input type="text" name="city" class="inputBox" autocomplete="address-level2"
                                value="<?= hsc(attribute('city')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_state'] ?>:</th>
                        <td><input type="text" name="state" class="inputBox" autocomplete="address-level1"
                                value="<?= hsc(post_strvar('state', attribute('state'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_zip'] ?>:</th>
                        <td><input type="text" name="zip" class="inputBox" autocomplete="postal-code"
                                value="<?= hsc(post_strvar('zip', attribute('zip'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_country'] ?>:</th>
                        <td>
                            <select size="1" name="country" autocomplete="country-name">
                                <?php $chosenCountry = post_strvar('country', attribute('country')); ?>
                                <option value="" <?php (!$chosenCountry ? ' selected' : '') ?>>&nbsp;
                                </option>
                                <?php
                                foreach ($_country_lang as $key => $country) {
                                    echo "<option value=\"$key\"" . (isset($chosenCountry) && $chosenCountry == $key ? ' selected' : '') . ">$country</option>";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_dob'] ?>:</th>
                        <td>
                            <input type="text" id="dob" name="dob" class="DatePicker"
                                value="<?= manager()->dateInputValue(attribute('dob'), 'dateOnly') ?>" onblur='documentDirty=true;'>
                            <a onclick="document.userform.dob.value=''; return true;"
                                style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                    src="<?= $icons_path ?>cal_nodate.gif"
                                    border="0"
                                    alt="<?= $_lang['remove_date'] ?>"></a>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_gender'] ?>:</th>
                        <td><select name="gender">
                                <option value=""></option>
                                <option
                                    value="1" <?= (post_intvar('gender') === 1 || attribute('gender') == 1) ? "selected='selected'" : "" ?>><?= $_lang['user_male'] ?></option>
                                <option
                                    value="2" <?= (post_intvar('gender') === 2 || attribute('gender') == 2) ? "selected='selected'" : "" ?>><?= $_lang['user_female'] ?></option>
                                <option
                                    value="3" <?= (post_intvar('gender') === 3 || attribute('gender') == 3) ? "selected='selected'" : "" ?>><?= $_lang['user_other'] ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th valign="top"><?= $_lang['comment'] ?>:</th>
                        <td>
                            <textarea type="text" name="comment" class="inputBox"
                                rows="5"><?= hsc(post_strvar('comment', attribute('comment'))) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td nowrap class="warning"><b><?= $_lang["user_photo"] ?></b></td>
                        <td><input type="text" maxlength="255" style="width: 150px;" name="photo"
                                value="<?= hsc(post_strvar('photo') ?: attribute('photo')) ?>" />
                            <input type="button" value="<?= $_lang['insert'] ?>" onclick="BrowseServer();" />
                            <div><?= $_lang["user_photo_message"] ?></div>
                            <div>
                                <?php
                                if (post_strvar('photo')) {
                                    $photo = post_strvar('photo');
                                } elseif (!empty(attribute('photo'))) {
                                    $photo = attribute('photo');
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
            <!-- Settings -->
            <div class="tab-page" id="tabSettings">
                <h2 class="tab"><?= $_lang["settings_users"] ?></h2>
                <table class="settings">
                    <tr>
                        <td nowrap class="warning"><b><?= $_lang["login_homepage"] ?></b></td>
                        <td>
                            <input type='text' maxlength='50' style="width: 100px;" name="login_home"
                                value="<?= hsc(post_strvar('login_home', setting('login_home'))) ?>">
                            <div><?= $_lang["login_homepage_message"] ?></div>
                        </td>
                    </tr>
                    <?php if ($action === 88): ?>
                        <tr>
                            <th><?= $_lang['user_logincount'] ?>:</th>
                            <td><?= (int) attribute('logincount') ?></td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_prevlogin'] ?>:</th>
                            <?php
                            if (!empty(attribute('lastlogin'))) {
                                $lastlogin = $modx->toDateFormat(attribute('lastlogin') + config('server_offset_time'));
                            } else {
                                $lastlogin = '-';
                            }
                            ?>
                            <td><?= $lastlogin ?></td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_failedlogincount'] ?>:</th>
                            <td>
                                <input type="hidden" name="failedlogincount"
                                    value="<?= (int) attribute('failedlogincount') ?>">
                                <span id='failed'><?= (int) attribute('failedlogincount') ?></span>&nbsp;&nbsp;&nbsp;[<a
                                    href="javascript:resetFailed()"><?= $_lang['reset_failedlogins'] ?></a>]
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_block'] ?>:</th>
                            <td><input name="blockedcheck" type="checkbox"
                                    onclick="changeblockstate(document.userform.blockedmode, document.userform.blockedcheck);" <?= isUserBlocked($userdata) ? " checked='checked'" : "" ?> /><input
                                    type="hidden" name="blocked"
                                    value="<?= attribute('blocked') == 1 ? 1 : 0 ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_blockeduntil'] ?>:</th>
                            <td>
                                <input type="text" id="blockeduntil" name="blockeduntil" class="DatePicker"
                                    value="<?= manager()->dateInputValue(attribute('blockeduntil')) ?>"
                                    onblur='documentDirty=true;' readonly="readonly">
                                <a onclick="document.userform.blockeduntil.value=''; return true;"
                                    style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                        src="<?= $icons_path ?>cal_nodate.gif"
                                        border="0"
                                        alt="<?= $_lang['remove_date'] ?>" /></a>
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_blockedafter'] ?>:</th>
                            <td>
                                <input type="text" id="blockedafter" name="blockedafter" class="DatePicker"
                                    value="<?= manager()->dateInputValue(attribute('blockedafter')) ?>"
                                    onblur='documentDirty=true;' readonly="readonly">
                                <a onclick="document.userform.blockedafter.value=''; return true;"
                                    style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                        src="<?= $icons_path ?>cal_nodate.gif"
                                        border="0"
                                        alt="<?= $_lang['remove_date'] ?>" /></a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td nowrap class="warning" valign="top"><b><?= $_lang["login_allowed_ip"] ?></b></td>
                        <td>
                            <input type="text" maxlength='255' style="width: 300px;" name="allowed_ip"
                                value="<?= hsc(post_strvar('allowed_ip', setting('allowed_ip'))) ?>" />
                            <div><?= $_lang["login_allowed_ip_message"] ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td nowrap class="warning" valign="top"><b><?= $_lang["login_allowed_days"] ?></b>
                        </td>
                        <td>
                            <?php $allowedDays = (string)setting('allowed_days', ''); ?>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="1" <?= strpos(
                                                    $allowedDays,
                                                    '1'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['sunday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="2" <?= strpos(
                                                    $allowedDays,
                                                    '2'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['monday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="3" <?= strpos(
                                                    $allowedDays,
                                                    '3'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['tuesday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="4" <?= strpos(
                                                    $allowedDays,
                                                    '4'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['wednesday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="5" <?= strpos(
                                                    $allowedDays,
                                                    '5'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['thursday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="6" <?= strpos(
                                                    $allowedDays,
                                                    '6'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['friday'] ?>
                            </label>
                            <label><input type="checkbox" name="allowed_days[]"
                                    value="7" <?= strpos(
                                                    $allowedDays,
                                                    '7'
                                                ) !== false ? "checked='checked'" : ""; ?> /> <?= $_lang['saturday'] ?>
                            </label>
                            <div><?= $_lang["login_allowed_days_message"] ?></div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
            if ($modx->config['use_udperms'] == 1) {
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
                    <h2 class="tab"><?= $_lang["web_access_permissions"] ?></h2>
                    <div class="sectionHeader"><?= $_lang['web_access_permissions'] ?></div>
                    <div class="sectionBody">
                        <?php
                        echo "<p>" . $_lang['access_permissions_user_message'] . "</p>";
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
    <input type="submit" name="save" style="display:none">
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
    var remember = <?= (($modx->config['remember_last_tab'] == 2) || (get_intvar('stay') === 2)) ? 'true' : 'false' ?>;
    tpUser = new WebFXTabPane(document.getElementById("webUserPane"), remember);
</script>
<?php
