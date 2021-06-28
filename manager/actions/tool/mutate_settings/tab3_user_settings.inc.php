<script>
jQuery(function(){
    if (jQuery('input[name="use_captcha"]:checked').val()==0) {
        jQuery('.captchaRow').hide();
    }
    jQuery('input[name="use_captcha"]').change(function () {
        if(jQuery(this).val()==1) {
            jQuery('tr.captchaRow').fadeIn();
        } else {
            jQuery('tr.captchaRow').fadeOut();
        }
    });
    if (jQuery('input[name="email_method"]:checked').val()=='mail') {
        jQuery('.emailMethodRow').hide();
    }
    jQuery('input[name="email_method"]').change(function () {
        if(jQuery(this).val()=='smtp') {
            jQuery('tr.emailMethodRow').fadeIn();
        } else {
            jQuery('tr.emailMethodRow').fadeOut();
        }
    });
});
</script>
<div class="tab-page" id="tabPage4">
    <h2 class="tab"><?php echo $_lang["settings_users"] ?></h2>
    <table class="settings">
        <tr>
            <th><?php echo $_lang["udperms_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('use_udperms', '1', $modx->config['use_udperms'] == '1', 'id="udPermsOn"')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('use_udperms', '0', $modx->config['use_udperms'] == '0', 'id="udPermsOff"')); ?><br/>
                <?php echo $_lang["udperms_message"] ?></td>
        </tr>
        <tr class="udPerms" style="display: <?php echo $modx->config['use_udperms'] == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["udperms_allowroot_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('udperms_allowroot', '1', $udperms_allowroot == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('udperms_allowroot', '0', $udperms_allowroot == '0')); ?>
                <br/>
                <?php echo $_lang["udperms_allowroot_message"] ?>
            </td>
        </tr>
        <tr class="udPerms" style="display: <?php echo $modx->config['use_udperms'] == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["tree_show_protected"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('tree_show_protected', '1', $tree_show_protected == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('tree_show_protected', '0', $tree_show_protected == '0')); ?><br/>
                <?php echo $_lang["tree_show_protected_message"] ?>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["default_role_title"] ?></th>
            <td>
                <select name="default_role">
                    <?php echo get_role_list(); ?>
                </select>
                <div><?php echo $_lang["default_role_message"] ?></div>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["validate_referer_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('validate_referer', '1', $validate_referer == '1')); ?>
                <br/>
                <?php echo wrap_label($_lang["no"], form_radio('validate_referer', '0', $validate_referer == '0')); ?>
                <br/>
                <?php echo $_lang["validate_referer_message"] ?>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["allow_mgr2web_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('allow_mgr2web', '1', $allow_mgr2web == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('allow_mgr2web', '0', $allow_mgr2web == '0')); ?><br/>
                <?php echo $_lang["allow_mgr2web_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["failed_login_title"] ?></th>
            <td>
                <?php echo form_text('failed_login_attempts', 3); ?><br/>
                <?php echo $_lang["failed_login_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["blocked_minutes_title"] ?></th>
            <td>
                <?php echo form_text('blocked_minutes', 7); ?><br/>
                <?php echo $_lang["blocked_minutes_message"] ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang['a17_error_reporting_title']; ?></th>
            <td>
                <?php echo wrap_label($_lang['a17_error_reporting_opt0'],
                    form_radio('error_reporting', '0', $error_reporting === '0')); ?><br/>
                <?php echo wrap_label($_lang['a17_error_reporting_opt1'],
                    form_radio('error_reporting', '1', $error_reporting === '1' || !isset($error_reporting))); ?><br/>
                <?php echo wrap_label($_lang['a17_error_reporting_opt2'],
                    form_radio('error_reporting', '2', $error_reporting === '2')); ?><br/>
                <?php echo wrap_label($_lang['a17_error_reporting_opt99'],
                    form_radio('error_reporting', '99', $error_reporting === '99')); ?><br/>
                <?php echo $_lang['a17_error_reporting_msg']; ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang['mutate_settings.dynamic.php6']; ?></th>
            <td>
                <?php echo wrap_label($_lang['mutate_settings.dynamic.php7'],
                    form_radio('send_errormail', '0', ($send_errormail == '0' || !isset($send_errormail)))); ?><br/>
                <?php echo wrap_label('error', form_radio('send_errormail', '3', $send_errormail == '3')); ?><br/>
                <?php echo wrap_label('error + warning', form_radio('send_errormail', '2', $send_errormail == '2')); ?>
                <br/>
                <?php echo wrap_label('error + warning + information',
                    form_radio('send_errormail', '1', $send_errormail == '1')); ?><br/>
                <?php echo $modx->parseText($_lang['mutate_settings.dynamic.php8'],
                    array('emailsender' => $modx->config['emailsender'])); ?></td>
        </tr>
        <?php
        // Check for GD before allowing captcha to be enabled
        $gdAvailable = extension_loaded('gd');
        ?>

        <tr>
            <th><?php echo $_lang["warning_visibility"] ?></th>
            <td>
                <?php echo wrap_label($_lang["administrators"],
                    form_radio('warning_visibility', '0', $warning_visibility == '0')); ?><br/>
                <?php echo wrap_label($_lang["a17_warning_opt2"],
                    form_radio('warning_visibility', '2', $warning_visibility == '2')); ?><br/>
                <?php echo wrap_label($_lang["everybody"],
                    form_radio('warning_visibility', '1', $warning_visibility == '1')); ?><br/>
                <?php echo $_lang["warning_visibility_message"] ?>
            </td>
        </tr>


        <tr>
            <th><?php echo $_lang["captcha_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('use_captcha', '1', $use_captcha == '1' && $gdAvailable, '', !$gdAvailable)); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('use_captcha', '0', $use_captcha == '0' || !$gdAvailable, '', !$gdAvailable)); ?><br/>
                <?php echo $_lang["captcha_message"] ?>
            </td>
        </tr>
        <tr class="captchaRow">
            <th><?php echo $_lang["captcha_words_title"]; ?>
                <br/>
                <p><?php echo $_lang["update_settings_from_language"]; ?></p>
                <select
                    name="reload_captcha_words" id="reload_captcha_words_select"
                    onchange="confirmLangChange(this, 'captcha_words_default', 'captcha_words_input');">
                    <?php echo get_lang_options('captcha_words_default'); ?>
                </select>
            </th>
            <td>
                <?php echo form_text('captcha_words', 255, 'id="captcha_words_input" style="width:400px"'); ?><br/>
                <input
                    type="hidden" name="captcha_words_default" id="captcha_words_default_hidden"
                    value="<?php echo addslashes($_lang["captcha_words_default"]); ?>"/><br/>
                <?php echo $_lang["captcha_words_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["emailsender_title"] ?></th>
            <td>
                <?php echo form_text('emailsender'); ?><br/>
                <?php echo $_lang["emailsender_message"] ?></td>
        </tr>


        <!--for smtp-->
        <tr>
            <th><?php echo $_lang["email_method_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["email_method_mail"],
                    form_radio('email_method', 'mail', ($email_method == 'mail' || !isset($email_method)))); ?>
                <?php echo wrap_label($_lang["email_method_smtp"],
                    form_radio('email_method', 'smtp', ($email_method == 'smtp'))); ?><br/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_auth_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('smtp_auth', '1', ($smtp_auth == '1' || !isset($smtp_auth)))); ?>
                <?php echo wrap_label($_lang["no"], form_radio('smtp_auth', '0', ($smtp_auth == '0'))); ?><br/>
            </td>
        </tr>

        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_host_title"] ?></th>
            <td><input
                onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtp_host"
                value="<?php echo isset($smtp_host) ? $smtp_host : "smtp.example.com"; ?>"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_port_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" 
                    name="smtp_port"
                value="<?php echo isset($smtp_port) ? $smtp_port : "25"; ?>"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_username_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;"
                    name="smtp_username"
                    value="<?php echo isset($smtp_username) ? $smtp_username : $emailsender; ?>"
                />
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_password_title"] ?></th>
            <td>
                <input
                    onchange="documentDirty=true;" type="text" maxlength="255" style="width: 250px;" name="smtppw"
                    value="********************" autocomplete="off"/>
            </td>
        </tr>
        <tr class="emailMethodRow">
            <th><?php echo $_lang["smtp_secure_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["none"],
                    form_radio('smtp_secure', '', ($smtp_secure == '' || !isset($smtp_secure)))); ?>
                <?php echo wrap_label("ssl", form_radio('smtp_secure', 'ssl', ($smtp_secure == 'ssl'))); ?>
                <?php echo wrap_label("tls", form_radio('smtp_secure', 'tls', ($smtp_secure == 'tls'))); ?>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["emailsubject_title"]; ?>
                <br/>
                <p><?php echo $_lang["update_settings_from_language"]; ?></p>
                <select name="reload_emailsubject" id="reload_emailsubject_select"
                        onchange="confirmLangChange(this, 'emailsubject_default', 'emailsubject_field');">
                    <?php echo get_lang_options('emailsubject_default'); ?>
                </select>
            </th>
            <td>
                <?php echo form_text('emailsubject', null, 'id="emailsubject_field"'); ?><br/>
                <input
                    type="hidden" name="emailsubject_default" id="emailsubject_default_hidden"
                    value="<?php echo addslashes($_lang['emailsubject_default']); ?>"/><br/>
                <?php echo $_lang["emailsubject_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top"><b><?php echo $_lang["signupemail_title"] ?></b>
                <br/>
                <p><?php echo $_lang["update_settings_from_language"]; ?></p>
                <select name="reload_signupemail_message" id="reload_signupemail_message_select"
                        onchange="confirmLangChange(this, 'system_email_signup', 'signupemail_message_textarea');">
                    <?php echo get_lang_options('system_email_signup'); ?>
                </select>
            </td>
            <td>
                <textarea
                    id="signupemail_message_textarea" name="signupemail_message"
                    style="width:100%; height: 120px;"
                ><?php echo $signupemail_message; ?></textarea>
                <input
                    type="hidden" name="system_email_signup_default" id="system_email_signup_hidden"
                    value="<?php echo addslashes($_lang['system_email_signup']); ?>"
                /><br/>
                <?php echo $_lang["signupemail_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top">
                <b><?php echo $_lang["websignupemail_title"] ?></b>
                <br/>
                <p><?php echo $_lang["update_settings_from_language"]; ?></p>
                <select name="reload_websignupemail_message" id="reload_websignupemail_message_select"
                        onchange="confirmLangChange(this, 'system_email_websignup', 'websignupemail_message_textarea');">
                    <?php echo get_lang_options('system_email_websignup'); ?>
                </select>
            </td>
            <td>
                <textarea
                    id="websignupemail_message_textarea" name="websignupemail_message"
                    style="width:100%; height: 120px;"
                ><?php echo $websignupemail_message; ?></textarea>
                <input
                    type="hidden" name="system_email_websignup_default" id="system_email_websignup_hidden"
                    value="<?php echo addslashes($_lang['system_email_websignup']); ?>"/><br/>
                <?php echo $_lang["websignupemail_message"] ?></td>
        </tr>
        <tr>
            <td nowrap class="warning" valign="top"><b><?php echo $_lang["webpwdreminder_title"] ?></b>
                <br/>
                <p><?php echo $_lang["update_settings_from_language"]; ?></p>
                <select name="reload_system_email_webreminder_message" id="reload_system_email_webreminder_select"
                        onchange="confirmLangChange(this, 'system_email_webreminder', 'system_email_webreminder_textarea');">
                    <?php echo get_lang_options('system_email_webreminder'); ?>
                </select>
            </td>
            <td>
                <textarea
                    id="system_email_webreminder_textarea" name="webpwdreminder_message"
                    style="width:100%; height: 120px;"><?php echo $webpwdreminder_message; ?></textarea>
                <input
                    type="hidden" name="system_email_webreminder_default" id="system_email_webreminder_hidden"
                    value="<?php echo addslashes($_lang['system_email_webreminder']); ?>"/><br/>
                <?php echo $_lang["webpwdreminder_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["enable_bindings_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('enable_bindings', '1', $enable_bindings == '1')); ?>
                <br/>
                <?php echo wrap_label($_lang["no"], form_radio('enable_bindings', '0', $enable_bindings == '0')); ?>
                <br/>
                <?php echo $_lang["enable_bindings_message"] ?>
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
