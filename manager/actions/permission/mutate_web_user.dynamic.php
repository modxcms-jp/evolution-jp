<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$tbl_active_users = evo()->getFullTableName('active_users');
$tbl_web_user_settings = evo()->getFullTableName('web_user_settings');
$tbl_web_users = evo()->getFullTableName('web_users');
$tbl_web_groups = evo()->getFullTableName('web_groups');
$tbl_webgroup_names = evo()->getFullTableName('webgroup_names');

switch ((int)anyv('a')) {
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

$user = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;


// check to see the snippet editor isn't locked
$rs = db()->select('internalKey, username', $tbl_active_users, "action='88' AND id='{$user}'");
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

if (anyv('a') == 88) {
    // get user attributes
    $rs = db()->select('*', evo()->getFullTableName('web_user_attributes'), "internalKey='{$user}'");
    $limit = db()->count($rs);
    if (!$limit) {
        echo "No user returned!<p>";
        exit;
    }
    $userdata = db()->getRow($rs);

    // get user settings
    $rs = db()->select('*', $tbl_web_user_settings, "webuser='{$user}'");
    $usersettings = [];
    while ($row = db()->getRow($rs)) {
        $usersettings[$row['setting_name']] = $row['setting_value'];
    }
    extract($usersettings, EXTR_OVERWRITE);

    // get user name
    $rs = db()->select('*', $tbl_web_users, "id='{$user}'");
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
    // restore post values
    $userdata = array_merge($userdata, $form_v);
    $userdata['dob'] = ConvertDate($userdata['dob']);
    $usernamedata['username'] = $userdata['newusername'];
    if (isset($form_v['oldusername'])) {
        $usernamedata['oldusername'] = $form_v['oldusername'];
    }
    $usersettings = array_merge($usersettings, $form_v);
    $allowedDays = $form_v['allowed_days'] ?? '';
    $usersettings['allowed_days'] = is_array($allowedDays) ? implode(",", $allowedDays) : (string)$allowedDays;
    extract($usersettings, EXTR_OVERWRITE);
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
$base_path = $modx->config['base_path'];
if ($manager_language != "english" && is_file(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php")) {
    include_once(MODX_CORE_PATH . "lang/country/{$manager_language}_country.inc.php");
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
        currval = eval(element).value;
        if (currval == 1) {
            if (confirm("<?= $_lang['confirm_unblock'] ?>") == true) {
                document.userform.blocked.value = 0;
                document.userform.blockeduntil.value = "";
                document.userform.blockedafter.value = "";
                document.userform.failedlogincount.value = 0;
                blocked.innerHTML = "<b><?= $_lang['unblock_message'] ?></b>";
                blocked.className = "TD";
                eval(element).value = 0;
            } else {
                eval(checkelement).checked = true;
            }
        } else {
            if (confirm("<?= $_lang['confirm_block'] ?>") == true) {
                document.userform.blocked.value = 1;
                blocked.innerHTML = "<b><?= $_lang['block_message'] ?></b>";
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
        if (confirm("<?= $_lang['confirm_delete_user'] ?>") == true) {
            document.location.href = "index.php?id=" + document.userform.id.value + "&a=90";
        }
    }

    // change name
    function changeName() {
        if (confirm("<?= $_lang['confirm_name_change'] ?>") == true) {
            var e1 = document.getElementById("showname");
            var e2 = document.getElementById("editname");
            e1.style.display = "none";
            e2.style.display = "table-row";
        }
    }

    function OpenServerBrowser(url, width, height) {
        var iLeft = (screen.width - width) / 2;
        var iTop = (screen.height - height) / 2;

        var sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes";
        sOptions += ",width=" + width;
        sOptions += ",height=" + height;
        sOptions += ",left=" + iLeft;
        sOptions += ",top=" + iTop;

        var oWindow = window.open(url, "FCKBrowseWindow", sOptions);
    }

    function BrowseServer() {
        var w = screen.width * 0.7;
        var h = screen.height * 0.7;
        OpenServerBrowser("<?= $base_url ?>manager/media/browser/mcpuk/browser.php?Type=images", w, h);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = "<?= $base_url ?>" + url;
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
    $tmp = array("id" => $user);
    $evtOut = evo()->invokeEvent("OnWUsrFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="mode" value="<?= getv('a') ?>" />
    <input type="hidden" name="id" value="<?= getv('id') ?>" />
    <input type="hidden" name="blockedmode"
        value="<?= (attribute('blocked') == 1 || (attribute('blockeduntil') > time() && attribute('blockeduntil') != 0) || (attribute('blockedafter') < time() && attribute('blockedafter') != 0) || attribute('failedlogins') > 3) ? "1" : "0" ?>" />

    <h1><?= $_lang['web_user_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_web_user')): ?>
                <li class="mutate"><a href="#" onclick="documentDirty=false; document.userform.save.click();"><img
                            src="<?= $_style["icons_save"] ?>" /> <?= $_lang['update'] ?></a><span
                        class="and"> + </span>
                    <select id="stay" name="stay">
                        <?php if (evo()->hasPermission('new_web_user')) { ?>
                            <option id="stay1"
                                value="1" <?= anyv('stay') == 1 ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <?php } ?>
                        <option id="stay2"
                            value="2" <?= anyv('stay') == 2 ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3"
                            value="" <?= anyv('stay') == '' ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php if (anyv('a') == 88) { ?>
                <li><a href="#" onclick="deleteuser();"><img
                            src="<?= $_style["icons_delete"] ?>" /> <?= $_lang['delete'] ?></a>
                </li>
            <?php } ?>
            <li><a href="#" onclick="document.location.href='index.php?a=99';"><img
                        src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a></li>
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
                                class="warning"><?php if (attribute('blocked') == 1 || (attribute('blockeduntil') > time() && attribute('blockeduntil')) || (attribute('blockedafter') < time() && attribute('blockedafter')) || attribute('failedlogins') > 3) { ?>
                                    <b><?= $_lang['user_is_blocked'] ?></b><?php } ?></span>
                        </td>
                    </tr>
                    <?php if (attribute('id')) { ?>
                        <tr id="showname"
                            style="display: <?= (getv('a') == 88 && (!webuser('oldusername') || webuser('oldusername') == webuser('username'))) ? 'table-row' : 'none' ?> ">
                            <td colspan="2">
                                <img src="<?= $_style['icons_user'] ?>"
                                    alt="." />&nbsp;<b><?= webuser('oldusername') ?: webuser('username') ?></b>
                                - <span class="comment"><a href="#"
                                        onclick="changeName();return false;"><?= $_lang["change_name"] ?></a></span>
                                <input type="hidden" name="oldusername"
                                    value="<?= hsc(webuser('oldusername') ?: webuser('username')) ?>" />
                            </td>
                        </tr>
                    <?php } ?>
                    <tr id="editname"
                        style="display:<?= getv('a') == '87' || (webuser('oldusername') && webuser('oldusername') != webuser('username')) ? 'table-row' : 'none' ?>">
                        <th><?= $_lang['username'] ?>:</th>
                        <td><input type="text" name="newusername" class="inputBox"
                                value="<?= hsc(postv('newusername', webuser('username'))) ?>"
                                maxlength="100" /></td>
                    </tr>
                    <tr>
                        <th valign="top"><?= getv('a') == 87 ? $_lang['password'] . ":" : $_lang['change_password_new'] . ":" ?></th>
                        <td>
                            <?php if (anyv('a') != 87): ?>
                                <input name="newpasswordcheck" type="checkbox"
                                    onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);">
                            <?php endif; ?>
                            <input type="hidden" name="newpassword"
                                value="<?= anyv('a') == 87 ? 1 : 0 ?>" /><br />
                            <div style="display:<?= anyv('a') == 87 ? "block" : "none" ?>"
                                id="passwordBlock">
                                <fieldset style="width:300px;padding:0;">
                                    <label><input type=radio name="passwordgenmethod"
                                            value="g" <?= postv('passwordgenmethod') == "spec" ? "" : 'checked="checked"' ?> /><?= $_lang['password_gen_gen'] ?>
                                    </label><br />
                                    <label><input type=radio name="passwordgenmethod"
                                            value="spec" <?= postv('passwordgenmethod') == "spec" ? 'checked="checked"' : "" ?>><?= $_lang['password_gen_specify'] ?>
                                    </label> <br />
                                    <div style="padding-left:20px">
                                        <label for="specifiedpassword"
                                            style="width:120px"><?= $_lang['change_password_new'] ?>
                                            :</label>
                                        <input type="password" name="specifiedpassword"
                                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="off" /><br />
                                        <label for="confirmpassword"
                                            style="width:120px"><?= $_lang['change_password_confirm'] ?>
                                            :</label>
                                        <input type="password" name="confirmpassword"
                                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                                            size="20" autocomplete="off" /><br />
                                        <span class="warning"
                                            style="font-weight:normal"><?= $_lang['password_gen_length'] ?></span>
                                    </div>
                                </fieldset>
                                <br />
                                <fieldset style="width:300px;padding:0;">
                                    <label><input type=radio name="passwordnotifymethod"
                                            value="e" <?= postv('passwordnotifymethod') == "e" ? 'checked="checked"' : "" ?> /><?= $_lang['password_method_email'] ?>
                                    </label><br />
                                    <label><input type=radio name="passwordnotifymethod"
                                            value="s" <?= postv('passwordnotifymethod') == "e" ? "" : 'checked="checked"' ?> /><?= $_lang['password_method_screen'] ?>
                                    </label>
                                </fieldset>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_email'] ?>:</th>
                        <td>
                            <input type="text" name="email" class="inputBox"
                                value="<?= postv('email', attribute('email')) ?>" />
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
                        <td><input type="text" name="fullname" class="inputBox"
                                value="<?= hsc(postv('fullname', attribute('fullname'))) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_phone'] ?>:</th>
                        <td><input type="text" name="phone" class="inputBox"
                                value="<?= postv('phone', attribute('phone')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_mobile'] ?>:</th>
                        <td><input type="text" name="mobilephone" class="inputBox"
                                value="<?= postv('mobilephone', attribute('mobilephone')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_fax'] ?>:</th>
                        <td><input type="text" name="fax" class="inputBox"
                                value="<?= postv('fax', attribute('fax')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_street'] ?>:</th>
                        <td><input type="text" name="street" class="inputBox"
                                value="<?= hsc(attribute('street')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_city'] ?>:</th>
                        <td><input type="text" name="city" class="inputBox"
                                value="<?= hsc(attribute('city')) ?>"
                                onchange="documentDirty=true;" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_state'] ?>:</th>
                        <td><input type="text" name="state" class="inputBox"
                                value="<?= postv('state', attribute('state')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_zip'] ?>:</th>
                        <td><input type="text" name="zip" class="inputBox"
                                value="<?= postv('zip', attribute('zip')) ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_country'] ?>:</th>
                        <td>
                            <select size="1" name="country">
                                <?php $chosenCountry = postv('country', attribute('country')); ?>
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
                                value="<?= postv('dob', attribute('dob') ? $modx->toDateFormat(attribute('dob'), 'dateOnly') : ""); ?>" onblur='documentDirty=true;'>
                            <a onclick="document.userform.dob.value=''; return true;"
                                style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                    src="media/style/<?= $manager_theme ?>/images/icons/cal_nodate.gif"
                                    border="0"
                                    alt="<?= $_lang['remove_date'] ?>"></a>
                        </td>
                    </tr>
                    <tr>
                        <th><?= $_lang['user_gender'] ?>:</th>
                        <td><select name="gender">
                                <option value=""></option>
                                <option
                                    value="1" <?= (postv('gender') == 1 || attribute('gender') == 1) ? "selected='selected'" : "" ?>><?= $_lang['user_male'] ?></option>
                                <option
                                    value="2" <?= (postv('gender') == 2 || attribute('gender') == 2) ? "selected='selected'" : "" ?>><?= $_lang['user_female'] ?></option>
                                <option
                                    value="3" <?= (postv('gender') == 3 || attribute('gender') == 3) ? "selected='selected'" : "" ?>><?= $_lang['user_other'] ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th valign="top"><?= $_lang['comment'] ?>:</th>
                        <td>
                            <textarea type="text" name="comment" class="inputBox"
                                rows="5"><?= hsc(postv('comment', attribute('comment'))) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td nowrap class="warning"><b><?= $_lang["user_photo"] ?></b></td>
                        <td><input type="text" maxlength="255" style="width: 150px;" name="photo"
                                value="<?= hsc(postv('photo') ?: attribute('photo')) ?>" />
                            <input type="button" value="<?= $_lang['insert'] ?>" onclick="BrowseServer();" />
                            <div><?= $_lang["user_photo_message"] ?></div>
                            <div>
                                <?php
                                if (postv('photo')) {
                                    $photo = postv('photo');
                                } elseif (!empty(attribute('photo'))) {
                                    $photo = attribute('photo');
                                } else {
                                    $photo = $modx->config['base_url'] . 'manager/' . $_style['tx'];
                                }

                                if (substr($photo, 0, 1) !== '/' && !preg_match('@^https?://@', $photo)) {
                                    $photo = $modx->config['base_url'] . $photo;
                                }
                                ?>
                                <img name="iphoto" src="<?= $photo ?>" />
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
                                value="<?= postv('login_home', setting('login_home')) ?>">
                            <div><?= $_lang["login_homepage_message"] ?></div>
                        </td>
                    </tr>
                    <?php if (getv('a') == 88): ?>
                        <tr>
                            <th><?= $_lang['user_logincount'] ?>:</th>
                            <td><?= attribute('logincount') ?></td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_prevlogin'] ?>:</th>
                            <?php
                            if (!empty(attribute('lastlogin'))) {
                                $lastlogin = $modx->toDateFormat(attribute('lastlogin') + $server_offset_time);
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
                                    value="<?= attribute('failedlogincount') ?>">
                                <span id='failed'><?= attribute('failedlogincount') ?></span>&nbsp;&nbsp;&nbsp;[<a
                                    href="javascript:resetFailed()"><?= $_lang['reset_failedlogins'] ?></a>]
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_block'] ?>:</th>
                            <td><input name="blockedcheck" type="checkbox"
                                    onclick="changeblockstate(document.userform.blockedmode, document.userform.blockedcheck);" <?= (attribute('blocked') == 1 || (attribute('blockeduntil') > time() && attribute('blockeduntil') != 0) || (attribute('blockedafter') < time() && attribute('blockedafter') != 0)) ? " checked='checked'" : "" ?> /><input
                                    type="hidden" name="blocked"
                                    value="<?= (attribute('blocked') == 1 || (attribute('blockeduntil') > time() && attribute('blockeduntil') != 0)) ? 1 : 0 ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_blockeduntil'] ?>:</th>
                            <td>
                                <input type="text" id="blockeduntil" name="blockeduntil" class="DatePicker"
                                    value="<?= postv('blockeduntil', attribute('blockeduntil') ? $modx->toDateFormat(attribute('blockeduntil')) : "") ?>"
                                    onblur='documentDirty=true;' readonly="readonly">
                                <a onclick="document.userform.blockeduntil.value=''; return true;"
                                    style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                        src="media/style/<?= $manager_theme ?>/images/icons/cal_nodate.gif"
                                        border="0"
                                        alt="<?= $_lang['remove_date'] ?>" /></a>
                            </td>
                        </tr>
                        <tr>
                            <th><?= $_lang['user_blockedafter'] ?>:</th>
                            <td>
                                <input type="text" id="blockedafter" name="blockedafter" class="DatePicker"
                                    value="<?= postv('blockedafter', attribute('blockedafter') ? $modx->toDateFormat(attribute('blockedafter')) : "") ?>"
                                    onblur='documentDirty=true;' readonly="readonly">
                                <a onclick="document.userform.blockedafter.value=''; return true;"
                                    style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                        src="media/style/<?= $manager_theme ?>/images/icons/cal_nodate.gif"
                                        border="0"
                                        alt="<?= $_lang['remove_date'] ?>" /></a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td nowrap class="warning" valign="top"><b><?= $_lang["login_allowed_ip"] ?></b></td>
                        <td>
                            <input type="text" maxlength='255' style="width: 300px;" name="allowed_ip"
                                value="<?= postv('allowed_ip', setting('allowed_ip')) ?>" />
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

                if (getv('a') == 88) { // only do this bit if the user is being edited
                    $uid = intval(getv('id'));
                    $rs = db()->select('*', $tbl_web_groups, "webuser='{$uid}'");
                    $limit = db()->count($rs);
                    for ($i = 0; $i < $limit; $i++) {
                        $currentgroup = db()->getRow($rs);
                        $groupsarray[$i] = $currentgroup['webgroup'];
                    }
                }
                // retain selected user groups between post
                if (is_array(postv('user_groups'))) {
                    foreach (postv('user_groups', []) as $n => $v) {
                        $groupsarray[] = $v;
                    }
                }
            ?>
                <!-- Access -->
                <div class="tab-page" id="tabAccess">
                    <h2 class="tab"><?= $_lang["web_access_permissions"] ?></h2>
                    <div class="sectionHeader"><?= $_lang['web_access_permissions'] ?></div>
                    <div class="sectionBody">
                        <?php
                        echo "<p>" . $_lang['access_permissions_user_message'] . "</p>";
                        $rs = db()->select('name,id', $tbl_webgroup_names, '', 'name');
                        $tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
                        while ($row = db()->getRow($rs)) {
                            $echo = $tpl;
                            $echo = str_replace('[+id+]', $row['id'], $echo);
                            $echo = str_replace(
                                '[+checked+]',
                                (in_array($row['id'], $groupsarray) ? 'checked="checked"' : ''),
                                $echo
                            );
                            $echo = str_replace('[+name+]', $row['name'], $echo);
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
    $tmp = array("id" => $user);
    $evtOut = evo()->invokeEvent("OnWUsrFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
</form>
<script type="text/javascript">
    var remember = <?= (($modx->config['remember_last_tab'] == 2) || (getv('stay') == 2)) ? 'true' : 'false' ?>;
    tpUser = new WebFXTabPane(document.getElementById("webUserPane"), remember);
</script>
<?php

// converts date format dd-mm-yyyy to php date
function ConvertDate($date)
{
    global $modx;
    if ($date == "") {
        return "0";
    } else {
        return $modx->toTimeStamp($date);
    }
}
