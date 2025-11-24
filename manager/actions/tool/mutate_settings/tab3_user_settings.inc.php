<script>
    jQuery(function () {
        if (jQuery('input[name="use_captcha"]:checked').val() == 0) {
            jQuery('.captchaRow').hide();
        }
        jQuery('input[name="use_captcha"]').change(function () {
            if (jQuery(this).val() == 1) {
                jQuery('tr.captchaRow').fadeIn();
            } else {
                jQuery('tr.captchaRow').fadeOut();
            }
        });
        if (jQuery('input[name="email_method"]:checked').val() == 'mail') {
            jQuery('.emailMethodRow').hide();
        }
        jQuery('input[name="email_method"]').change(function () {
            if (jQuery(this).val() == 'smtp') {
                jQuery('tr.emailMethodRow').fadeIn();
            } else {
                jQuery('tr.emailMethodRow').fadeOut();
            }
        });
    });
</script>
<div class="tab-page" id="tabPage4">
    <h2 class="tab"><?= $_lang["settings_users"] ?></h2>
    <table class="settings">
        <tr>
            <th><?= $_lang["udperms_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('use_udperms', '1', $modx->config['use_udperms'] == '1', 'id="udPermsOn"')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('use_udperms', '0', $modx->config['use_udperms'] == '0', 'id="udPermsOff"')); ?><br/>
                <?= $_lang["udperms_message"] ?></td>
        </tr>
        <tr class="udPerms" style="display: <?= $modx->config['use_udperms'] == 1 ? 'table-row' : 'none' ?>">
            <th><?= $_lang["udperms_allowroot_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('udperms_allowroot', '1', config('udperms_allowroot') == '1')); ?><br/>
                <?= wrap_label($_lang["no"], form_radio('udperms_allowroot', '0', config('udperms_allowroot') == '0')) ?>
                <br/>
                <?= $_lang["udperms_allowroot_message"] ?>
            </td>
        </tr>
        <tr class="udPerms" style="display: <?= $modx->config['use_udperms'] == 1 ? 'table-row' : 'none' ?>">
            <th><?= $_lang["tree_show_protected"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('tree_show_protected', '1', config('tree_show_protected') == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('tree_show_protected', '0', config('tree_show_protected') == '0')); ?><br/>
                <?= $_lang["tree_show_protected_message"] ?>
            </td>
        </tr>

        <tr>
            <th><?= $_lang["default_role_title"] ?></th>
            <td>
                <select name="default_role">
                    <?= get_role_list() ?>
                </select>
                <div><?= $_lang["default_role_message"] ?></div>
            </td>
        </tr>

        <tr>
            <th><?= $_lang["allow_mgr2web_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('allow_mgr2web', '1', config('allow_mgr2web') == '1')) ?><br/>
                <?= wrap_label($_lang["no"], form_radio('allow_mgr2web', '0', config('allow_mgr2web') == '0')) ?><br/>
                <?= $_lang["allow_mgr2web_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["failed_login_title"] ?></th>
            <td>
                <?= form_text('failed_login_attempts', 3) ?><br/>
                <?= $_lang["failed_login_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["blocked_minutes_title"] ?></th>
            <td>
                <?= form_text('blocked_minutes', 7) ?><br/>
                <?= $_lang["blocked_minutes_message"] ?></td>
        </tr>

        <tr>
            <th><?= $_lang['a17_error_reporting_title'] ?></th>
            <td>
                <?= wrap_label($_lang['a17_error_reporting_opt0'],
                    form_radio('error_reporting', '0', $error_reporting === '0')); ?><br/>
                <?= wrap_label($_lang['a17_error_reporting_opt1'],
                    form_radio('error_reporting', '1', $error_reporting === '1' || !isset($error_reporting))); ?><br/>
                <?= wrap_label($_lang['a17_error_reporting_opt2'],
                    form_radio('error_reporting', '2', $error_reporting === '2')); ?><br/>
                <?= wrap_label($_lang['a17_error_reporting_opt99'],
                    form_radio('error_reporting', '99', $error_reporting === '99')); ?><br/>
                <?= $_lang['a17_error_reporting_msg'] ?></td>
        </tr>

        <tr>
            <th><?= $_lang['mutate_settings.dynamic.php6'] ?></th>
            <td>
                <?= wrap_label($_lang['mutate_settings.dynamic.php7'],
                    form_radio('send_errormail', '0', ($send_errormail == '0' || !isset($send_errormail)))); ?><br/>
                <?= wrap_label('error', form_radio('send_errormail', '3', $send_errormail == '3')) ?><br/>
                <?= wrap_label('error + warning', form_radio('send_errormail', '2', $send_errormail == '2')) ?>
                <br/>
                <?= wrap_label('error + warning + information',
                    form_radio('send_errormail', '1', $send_errormail == '1')); ?><br/>
                <?= $modx->parseText($_lang['mutate_settings.dynamic.php8'],
                    ['emailsender' => $modx->config['emailsender']]); ?></td>
        </tr>
        <?php
        // Check for GD before allowing captcha to be enabled
        $gdAvailable = extension_loaded('gd');
        ?>

        <tr>
            <th><?= $_lang["warning_visibility"] ?></th>
            <td>
                <?= wrap_label($_lang["administrators"],
                    form_radio('warning_visibility', '0', config('warning_visibility') == '0')); ?><br/>
                <?= wrap_label($_lang["a17_warning_opt2"],
                    form_radio('warning_visibility', '2', config('warning_visibility') == '2')); ?><br/>
                <?= wrap_label($_lang["everybody"],
                    form_radio('warning_visibility', '1', config('warning_visibility') == '1')); ?><br/>
                <?= $_lang["warning_visibility_message"] ?>
            </td>
        </tr>


        <tr>
            <th><?= $_lang["captcha_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('use_captcha', '1', config('use_captcha') == '1' && $gdAvailable, '', !$gdAvailable)); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('use_captcha', '0', config('use_captcha') == '0' || !$gdAvailable, '', !$gdAvailable)); ?><br/>
                <?= $_lang["captcha_message"] ?>
            </td>
        </tr>
        <tr class="captchaRow">
            <th><?= $_lang["captcha_words_title"] ?>
                <br/>
                <p><?= $_lang["update_settings_from_language"] ?></p>
                <select
                    name="reload_captcha_words" id="reload_captcha_words_select"
                    onchange="confirmLangChange(this, 'captcha_words_default', 'captcha_words_input');">
                    <?= get_lang_options('captcha_words_default') ?>
                </select>
            </th>
            <td>
                <?= form_text('captcha_words', 255, 'id="captcha_words_input" style="width:400px"') ?><br/>
                <input
                    type="hidden" name="captcha_words_default" id="captcha_words_default_hidden"
                    value="<?= addslashes($_lang["captcha_words_default"]) ?>"/><br/>
                <?= $_lang["captcha_words_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["emailsender_title"] ?></th>
            <td>
                <?= form_text('emailsender') ?><br/>
                <?= $_lang["emailsender_message"] ?></td>
        </tr>


        <!--for smtp-->
        <tr>
            <th><?= $_lang["email_method_title"] ?></th>
            <td>
                <?= wrap_label($_lang["email_method_mail"],
                    form_radio(
                        'email_method',
                        'mail',
                        (evo()->config('email_method') == 'mail' || !evo()->config('email_method')))
                    ); ?>
                <?= wrap_label($_lang["email_method_smtp"],
                    form_radio('email_method', 'smtp', (evo()->config('email_method') == 'smtp'))); ?><br/>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["modxmailer_log_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('modxmailer_log', '1', config('modxmailer_log', 0) == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('modxmailer_log', '0', config('modxmailer_log', 0) == '0')); ?><br/>
                <?= $_lang["modxmailer_log_message"] ?>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_auth_title"] ?></th>
            <td>
                <?= wrap_label(
                    $_lang["yes"],
                    form_radio(
                        'smtp_auth',
                        '1',
                        config('smtp_auth', 0) == 1
                    )
                ); ?>
                <?= wrap_label(
                    $_lang["no"],
                    form_radio(
                        'smtp_auth', '0', config('smtp_auth', 0) == 0
                    )
                ); ?><br/>
            </td>
        </tr>

        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_host_title"] ?></th>
            <td><input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtp_host"
                    value="<?= config('smtp_host', 'smtp.example.com') ?>"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_port_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;"
                    name="smtp_port"
                    value="<?= config('smtp_port', '25') ?>"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_username_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;"
                    name="smtp_username"
                    value="<?= config('smtp_username', $emailsender) ?>"
                />
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_password_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtppw"
                    value="********************" autocomplete="off"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?= $_lang["smtp_secure_title"] ?></th>
            <td>
                <?= wrap_label($_lang["none"],
                    form_radio('smtp_secure', '', (config('smtp_secure') == '' || !isset($smtp_secure)))); ?>
                <?= wrap_label("ssl", form_radio('smtp_secure', 'ssl', (config('smtp_secure') == 'ssl'))) ?>
                <?= wrap_label("tls", form_radio('smtp_secure', 'tls', (config('smtp_secure') == 'tls'))) ?>
            </td>
        </tr>

        <tr>
            <th><?= $_lang["emailsubject_title"] ?>
                <br/>
                <p><?= $_lang["update_settings_from_language"] ?></p>
                <select name="reload_emailsubject" id="reload_emailsubject_select"
                        onchange="confirmLangChange(this, 'emailsubject_default', 'emailsubject_field');">
                    <?= get_lang_options('emailsubject_default') ?>
                </select>
            </th>
            <td>
                <?= form_text('emailsubject', null, 'id="emailsubject_field"') ?><br/>
                <input
                    type="hidden" name="emailsubject_default" id="emailsubject_default_hidden"
                    value="<?= addslashes($_lang['emailsubject_default']) ?>"/><br/>
                <?= $_lang["emailsubject_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top"><b><?= $_lang["signupemail_title"] ?></b>
                <br/>
                <p><?= $_lang["update_settings_from_language"] ?></p>
                <select name="reload_signupemail_message" id="reload_signupemail_message_select"
                        onchange="confirmLangChange(this, 'system_email_signup', 'signupemail_message_textarea');">
                    <?= get_lang_options('system_email_signup') ?>
                </select>
            </td>
            <td>
                <textarea
                    id="signupemail_message_textarea" name="signupemail_message"
                    style="width:100%; height: 120px;"
                ><?= $signupemail_message ?></textarea>
                <input
                    type="hidden" name="system_email_signup_default" id="system_email_signup_hidden"
                    value="<?= addslashes($_lang['system_email_signup']) ?>"
                /><br/>
                <?= $_lang["signupemail_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top">
                <b><?= $_lang["websignupemail_title"] ?></b>
                <br/>
                <p><?= $_lang["update_settings_from_language"] ?></p>
                <select name="reload_websignupemail_message" id="reload_websignupemail_message_select"
                        onchange="confirmLangChange(this, 'system_email_websignup', 'websignupemail_message_textarea');">
                    <?= get_lang_options('system_email_websignup') ?>
                </select>
            </td>
            <td>
                <textarea
                    id="websignupemail_message_textarea" name="websignupemail_message"
                    style="width:100%; height: 120px;"
                ><?= $websignupemail_message ?></textarea>
                <input
                    type="hidden" name="system_email_websignup_default" id="system_email_websignup_hidden"
                    value="<?= addslashes($_lang['system_email_websignup']) ?>"/><br/>
                <?= $_lang["websignupemail_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top"><b><?= $_lang["webpwdreminder_title"] ?></b>
                <br/>
                <p><?= $_lang["update_settings_from_language"] ?></p>
                <select name="reload_system_email_webreminder_message" id="reload_system_email_webreminder_select"
                        onchange="confirmLangChange(this, 'system_email_webreminder', 'system_email_webreminder_textarea');">
                    <?= get_lang_options('system_email_webreminder') ?>
                </select>
            </td>
            <td>
                <textarea
                    id="system_email_webreminder_textarea" name="webpwdreminder_message"
                    style="width:100%; height: 120px;"><?= $webpwdreminder_message ?></textarea>
                <input
                    type="hidden" name="system_email_webreminder_default" id="system_email_webreminder_hidden"
                    value="<?= addslashes($_lang['system_email_webreminder']) ?>"/><br/>
                <?= $_lang["webpwdreminder_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["enable_bindings_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('enable_bindings', '1', $enable_bindings == '1')) ?>
                <br/>
                <?= wrap_label($_lang["no"], form_radio('enable_bindings', '0', $enable_bindings == '0')) ?>
                <br/>
                <?= $_lang["enable_bindings_message"] ?>
            </td>
        </tr>

        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnUserSettingsRender event
                $evtOut = evo()->invokeEvent("OnUserSettingsRender");
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
