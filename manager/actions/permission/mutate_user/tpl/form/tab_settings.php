<h2 class="tab"><?php echo lang('settings_users') ?></h2>
<table class="settings">
    <tr>
        <th><?php echo lang('allow_mgr_access') ?></th>
        <td>
            <label><input type="radio" name="allow_manager_access" value="1" <?php echo !isset($user['allow_manager_access'])||$user['allow_manager_access']==1 ? 'checked="checked"':'' ; ?> /> <?php echo lang('yes'); ?></label><br />
            <label><input type="radio" name="allow_manager_access" value="0" <?php echo isset($user['allow_manager_access']) && $user['allow_manager_access']==0 ? 'checked="checked"':'' ; ?> /> <?php echo lang('no'); ?></label>
            <div><?php echo lang('allow_mgr_access_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_allowed_parents') ?>:</th>
        <td>
            <input type="text" name="allowed_parents" class="inputBox" value="<?php echo htmlspecialchars($user['allowed_parents']); ?>" />
            <div><?php echo lang('user_allowed_parents_message') ?></div>
        </td>
    </tr>
    <?php if($_GET['a']=='12'): ?>
        <tr>
            <th><?php echo lang('user_logincount'); ?>:</th>
            <td><?php echo $user['logincount'] ?></td>
        </tr>
        <?php
        if(!empty($user['lastlogin']))
        {
            $lastlogin = $modx->toDateFormat($user['lastlogin']+$modx->config['server_offset_time']);
        }
        else $lastlogin = '-';
        ?>
        <tr>
            <th><?php echo lang('user_prevlogin'); ?>:</th>
            <td><?php echo $lastlogin ?></td>
        </tr>
        <tr>
            <th><?php echo lang('user_failedlogincount'); ?>:</th>
            <td>
                <input type="hidden" name="failedlogincount" value="<?php echo $user['failedlogincount']; ?>">
                <span id='failed'><?php echo $user['failedlogincount'] ?></span>&nbsp;&nbsp;&nbsp;[<a href="javascript:resetFailed()"><?php echo lang('reset_failedlogins'); ?></a>]</td>
        </tr>
        <tr>
            <th><?php echo lang('user_block'); ?>:</th>
            <td>
                <?php $blocked = (user('blocked') || (user('blockeduntil', 0) > time() && user('blockeduntil')));?>
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
                        value="<?php echo $blocked ? 1 : 0; ?>">
                </label></td>
        </tr>
        <tr>
            <th><?php echo lang('user_blockedafter'); ?>:</th>
            <td>
                <input type="text" id="blockedafter" name="blockedafter" class="DatePicker" value="<?php echo ($user['blockedafter'] ? $modx->toDateFormat($user['blockedafter']):""); ?>" onblur='documentDirty=true;' readonly="readonly">
                <a onclick="document.userform.blockedafter.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $modx->config['manager_theme']; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo lang('remove_date'); ?>" /></a>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('user_blockeduntil'); ?>:</th>
            <td>
                <input
                    type="text"
                    id="blockeduntil"
                    name="blockeduntil"
                    class="DatePicker"
                    value="<?php echo ($user['blockeduntil'] ? $modx->toDateFormat($user['blockeduntil']):''); ?>" onblur='documentDirty=true;' readonly="readonly">
                <a onclick="document.userform.blockeduntil.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $modx->config['manager_theme']; ?>/images/icons/cal_nodate.gif" border="0" alt="<?php echo lang('remove_date'); ?>" /></a>
            </td>
        </tr>
    <?php endif;?>
    <tr>
        <th><?php echo lang('login_allowed_ip') ?></th>
        <td ><input  type="text" maxlength='255' style="width: 300px;" name="allowed_ip" value="<?php echo $user['allowed_ip']; ?>" />
            <div><?php echo lang('login_allowed_ip_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('login_allowed_days') ?></th>
        <td>
            <?php echo checkbox('allowed_days[]','1',lang('sunday'),   strpos($user['allowed_days'],'1')!==false);?>
            <?php echo checkbox('allowed_days[]','2',lang('monday'),   strpos($user['allowed_days'],'2')!==false);?>
            <?php echo checkbox('allowed_days[]','3',lang('tuesday'),  strpos($user['allowed_days'],'3')!==false);?>
            <?php echo checkbox('allowed_days[]','4',lang('wednesday'),strpos($user['allowed_days'],'4')!==false);?>
            <?php echo checkbox('allowed_days[]','5',lang('thursday'), strpos($user['allowed_days'],'5')!==false);?>
            <?php echo checkbox('allowed_days[]','6',lang('friday'),   strpos($user['allowed_days'],'6')!==false);?>
            <?php echo checkbox('allowed_days[]','7',lang('saturday'), strpos($user['allowed_days'],'7')!==false);?>
            <div><?php echo lang('login_allowed_days_message'); ?></div>
        </td>
    </tr>
</table>
