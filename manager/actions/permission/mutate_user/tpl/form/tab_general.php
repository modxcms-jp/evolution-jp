<?php if (getv('id') == evo()->getLoginUserID()) { ?>
    <p><?= lang('user_edit_self_msg') ?></p>
<?php } ?>
<h2 class="tab"><?= lang('login_settings') ?></h2>
<table class="settings">
    <?php
    if (user('blocked') == 1 || (user('blockeduntil') > time() && user('blockeduntil') != 0) || user('failedlogins') > 3) {
        ?>
        <tr>
            <td colspan="2">
                <span id="blocked" class="warning"><b><?= lang('user_is_blocked') ?></b></span><br/>
            </td>
        </tr>
    <?php } ?>
    <?php if (user('id')) { ?>
        <tr
            id="showname"
            <?php
            if (getv('a') == 11) {
                echo 'style="display:none";';
            }
            ?>
        >
            <td colspan="2">
                <img src="<?= style('icons_user') ?>"/>
                &nbsp;
                <b><?= user('oldusername', user('username')) ?></b>
                -
                <span class="comment">
                <a
                    href="#"
                    onclick="jQuery('#showname').hide(100);jQuery('#editname').show(100);return false;"
                ><?= lang('change_name') ?></a>
            </span>
                <input
                    name="oldusername"
                    type="hidden"
                    value="<?= hsc(user('oldusername', user('username'))) ?>"
                />
            </td>
        </tr>
    <?php } ?>
    <tr
        id="editname"
        <?php
        if (getv('a') == 12) {
            echo 'style="display:none";';
        }
        ?>
    >
        <th>
            <?= lang('username') ?>:
        </th>
        <td><input
                name="newusername"
                value="<?= hsc(user('username')) ?>"
                type="text"
                class="inputBox"
                maxlength="100"
            /></td>
    </tr>
    <tr>
        <th valign="top">
            <?= getv('a') == 11 ? lang('password') . ':' : lang('change_password_new') . ":" ?>
        </th>
        <td>
            <?php if (anyv('a') == 12): ?>
                <input
                    name="newpasswordcheck"
                    type="checkbox"
                    onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);"
                ><br/>
            <?php endif; ?>
            <input
                name="newpassword"
                value="<?= anyv('a') == 11 ? 1 : 0 ?>"
                type="hidden"
            />
            <span
                style="display:<?= anyv('a') == 11 ? "block" : "none" ?>"
                id="passwordBlock"
            >
                <fieldset style="width:300px;padding:0;">
                    <label>
                        <input
                            name="passwordgenmethod"
                            value="g"
                            type=radio
                            <?= postv('passwordgenmethod') === "spec" ? "" : 'checked="checked"' ?>
                        />
                        <?= lang('password_gen_gen') ?>
                    </label><br/>
                    <label>
                        <input
                            name="passwordgenmethod"
                            value="spec"
                            type=radio
                            <?= postv('passwordgenmethod') === "spec" ? 'checked="checked"' : "" ?>
                        >
                        <?= lang('password_gen_specify') ?>
                    </label><br/>
                    <div style="padding-left:20px">
                        <label for="specifiedpassword" style="width:120px">
                            <?= lang('change_password_new') ?>:
                        </label>
                        <input
                            name="specifiedpassword"
                            type="password"
                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                            size="20"
                            autocomplete="off"
                        /><br/>
                        <label for="confirmpassword" style="width:120px">
                            <?= lang('change_password_confirm') ?>:
                        </label>
                        <input
                            name="confirmpassword"
                            type="password"
                            onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                            size="20"
                            autocomplete="off"
                        /><br/>
                        <span
                            class="warning"
                            style="font-weight:normal"
                        ><?= lang('password_gen_length') ?></span>
                    </div>
                </fieldset>
                <fieldset
                    style="width:300px;padding:0;"
                >
                    <label>
                        <input
                            name="passwordnotifymethod"
                            value="e"
                            type="radio"
                            <?= postv('passwordnotifymethod') === "e" ? 'checked="checked"' : "" ?>
                        /><?= lang('password_method_email') ?>
                    </label><br/>
                    <label>
                        <input
                            type="radio"
                            name="passwordnotifymethod"
                            value="s"
                            <?= postv('passwordnotifymethod') === 'e' ? '' : 'checked="checked"' ?>
                        /><?= lang('password_method_screen') ?>
                    </label>
                </fieldset>
            </span>
        </td>
    </tr>
    <tr>
        <th><?= lang('user_email') ?>:</th>
        <td>
            <input
                name="email"
                value="<?= hsc(user('email')) ?>"
                type="text"
                class="inputBox"
            />
            <input
                name="oldemail"
                value="<?= hsc(user('oldemail', user('email'))) ?>"
                type="hidden"
            />
        </td>
    </tr>
    <tr>
        <th><?= lang('user_role') ?>:</th>
        <td>
            <?php
            if ($userid == evo()->getLoginUserID()) {
                if (evo()->hasPermission('save_role')) {
                    $where = 'save_role=1';
                } else {
                    $where = 'save_role=0';
                }
            } else {
                $where = '';
            }
            $rs = db()->select(
                'name, id'
                , '[+prefix+]user_roles'
                , $where
                , 'save_role DESC, new_role DESC, id ASC'
            );
            $options = [];
            while ($row = db()->getRow($rs)) {
                if (anyv('a') == 11) {
                    $selected = ($row['id'] == evo()->config['default_role']);
                } else {
                    $selected = ($row['id'] == user('role'));
                }
                $options[] = html_tag(
                    'option'
                    , [
                        'value' => $row['id'],
                        'selected' => $selected ? null : ''
                    ]
                    , $row['name']
                );
            }
            ?>
            <select name="role" class="inputBox">
                <?= implode("\n", $options) ?>
            </select>
        </td>
    </tr>
</table>
