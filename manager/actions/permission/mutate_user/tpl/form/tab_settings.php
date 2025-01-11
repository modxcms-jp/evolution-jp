<h2 class="tab"><?= lang('settings_users') ?></h2>
<table class="settings">
    <?php
        $allowManagerAccess = user('allow_manager_access') === null || user('allow_manager_access') == 1;
    ?>
    <tr>
        <th><?= lang('allow_mgr_access') ?></th>
        <td>
            <label><input type="radio" name="allow_manager_access"
                          value="1" <?= $allowManagerAccess ? 'checked' : '' ?> /> <?= lang('yes') ?>
            </label><br/>
            <label><input type="radio" name="allow_manager_access"
                          value="0" <?= !$allowManagerAccess ? 'checked' :'' ?> /> <?= lang('no') ?>
            </label>
            <div><?= lang('allow_mgr_access_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('user_allowed_parents') ?>:</th>
        <td>
            <input type="text" name="allowed_parents" class="inputBox"
                   value="<?= htmlspecialchars(user('allowed_parents')) ?>"/>
            <div><?= lang('user_allowed_parents_message') ?></div>
        </td>
    </tr>
    <?php if (getv('a') == 12): ?>
        <tr>
            <th><?= lang('user_logincount') ?>:</th>
            <td><?= user('logincount') ?></td>
        </tr>
        <?php
        if (!empty(user('lastlogin'))) {
            $lastlogin = $modx->toDateFormat(user('lastlogin') + $modx->config['server_offset_time']);
        } else {
            $lastlogin = '-';
        }
        ?>
        <tr>
            <th><?= lang('user_prevlogin') ?>:</th>
            <td><?= $lastlogin ?></td>
        </tr>
        <tr>
            <th><?= lang('user_failedlogincount') ?>:</th>
            <td>
                <input type="hidden" name="failedlogincount" value="<?= user('failedlogincount') ?>">
                <span id='failed'><?= user('failedlogincount') ?></span>&nbsp;&nbsp;&nbsp;[<a
                    href="javascript:resetFailed()"><?= lang('reset_failedlogins') ?></a>]
            </td>
        </tr>
        <tr>
            <th><?= lang('user_block') ?>:</th>
            <td>
                <?php $blocked = (user('blocked') || (user('blockeduntil', 0) > time() && user('blockeduntil'))); ?>
                <label>
                    <input
                        name="blockedcheck"
                        type="checkbox"
                        onclick="changeblockstate(document.userform.blocked, document.userform.blockedcheck);"
                        <?php
                        if ($blocked) {
                            echo "checked";
                        } ?>
                    >
                    <input
                        type="hidden"
                        name="blocked"
                        value="<?= $blocked ? 1 : 0 ?>">
                </label></td>
        </tr>
        <tr>
            <th><?= lang('user_blockedafter') ?>:</th>
            <td>
                <input type="text" id="blockedafter" name="blockedafter" class="DatePicker"
                       value="<?php echo(user('blockedafter') ? $modx->toDateFormat(user('blockedafter')) : ""); ?>"
                       onblur='documentDirty=true;' readonly="readonly">
                <a onclick="document.userform.blockedafter.value=''; return true;"
                   style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                                            src="media/style/<?= $modx->config['manager_theme'] ?>/images/icons/cal_nodate.gif"
                                                            border="0" alt="<?= lang('remove_date') ?>"/></a>
            </td>
        </tr>
        <tr>
            <th><?= lang('user_blockeduntil') ?>:</th>
            <td>
                <input
                    type="text"
                    id="blockeduntil"
                    name="blockeduntil"
                    class="DatePicker"
                    value="<?php echo(user('blockeduntil') ? $modx->toDateFormat(user('blockeduntil')) : ''); ?>"
                    onblur='documentDirty=true;' readonly="readonly">
                <a onclick="document.userform.blockeduntil.value=''; return true;"
                   style="cursor:pointer; cursor:hand"><img align="absmiddle"
                                                            src="media/style/<?= $modx->config['manager_theme'] ?>/images/icons/cal_nodate.gif"
                                                            border="0" alt="<?= lang('remove_date') ?>"/></a>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <th><?= lang('login_allowed_ip') ?></th>
        <td><input type="text" maxlength='255' style="width: 300px;" name="allowed_ip"
                   value="<?= user('allowed_ip') ?>"/>
            <div><?= lang('login_allowed_ip_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('login_allowed_days') ?></th>
        <td>
            <?= checkbox('allowed_days[]', '1', lang('sunday'), strpos(user('allowed_days'), '1') !== false) ?>
            <?= checkbox('allowed_days[]', '2', lang('monday'), strpos(user('allowed_days'), '2') !== false) ?>
            <?= checkbox('allowed_days[]', '3', lang('tuesday'),
                strpos(user('allowed_days'), '3') !== false); ?>
            <?= checkbox('allowed_days[]', '4', lang('wednesday'),
                strpos(user('allowed_days'), '4') !== false); ?>
            <?= checkbox('allowed_days[]', '5', lang('thursday'),
                strpos(user('allowed_days'), '5') !== false); ?>
            <?= checkbox('allowed_days[]', '6', lang('friday'), strpos(user('allowed_days'), '6') !== false) ?>
            <?= checkbox('allowed_days[]', '7', lang('saturday'),
                strpos(user('allowed_days'), '7') !== false); ?>
            <div><?= lang('login_allowed_days_message') ?></div>
        </td>
    </tr>
</table>
