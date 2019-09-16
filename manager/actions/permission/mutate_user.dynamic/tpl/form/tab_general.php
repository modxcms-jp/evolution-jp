<?php if(evo()->input_get('id')==evo()->getLoginUserID()) { ?>
    <p><?php echo lang('user_edit_self_msg'); ?></p>
<?php } ?>
<h2 class="tab"><?php echo lang('login_settings') ?></h2>
<table class="settings">
<?php
if($user['blocked']==1 || ($user['blockeduntil']>time() && $user['blockeduntil']!=0) || $user['failedlogins']>3) {
?>
    <tr>
        <td colspan="2">
            <span id="blocked" class="warning"><b><?php echo lang('user_is_blocked'); ?></b></span><br />
        </td>
    </tr>
<?php } ?>
<?php if($user['id']) { ?>
    <tr id="showname" style="display: <?php echo ($_GET['a']=='12' && (!isset($user['oldusername'])||$user['oldusername']==$user['username'])) ? $displayStyle : 'none';?> ">
        <td colspan="2">
            <img src="<?php echo style('icons_user') ?>" />
            &nbsp;
            <b><?php echo $user['oldusername'] ? $user['oldusername']:$user['username']; ?></b>
            -
            <span class="comment">
            <a
                href="#"
                onclick="jQuery('#showname').hide(100);jQuery('#editname').show(100);return false;"
            ><?php echo lang('change_name'); ?></a>
            </span>
            <input type="hidden" name="oldusername" value="<?php echo hsc($user['oldusername'] ? $user['oldusername']:$user['username']); ?>" />
        </td>
    </tr>
<?php } ?>
    <tr id="editname" style="display:<?php echo $_GET['a']=='11'||(isset($user['oldusername']) && $user['oldusername']!=$user['username']) ? $displayStyle : 'none' ; ?>">
        <th><?php echo lang('username'); ?>:</th>
        <td><input type="text" name="newusername" class="inputBox" value="<?php echo htmlspecialchars($user['username']); ?>" maxlength="100" /></td>
    </tr>
    <tr>
        <th valign="top"><?php echo $_GET['a']=='11' ? lang('password').":" : lang('change_password_new').":" ; ?></th>
        <td>
            <?php if($_REQUEST['a']=='12'):?>
                <input name="newpasswordcheck" type="checkbox" onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);"><br />
            <?php endif; ?>
            <input type="hidden" name="newpassword" value="<?php echo $_REQUEST['a']=="11" ? 1 : 0 ; ?>" />
            <span style="display:<?php echo $_REQUEST['a']=="11" ? "block": "none" ; ?>" id="passwordBlock">
<fieldset style="width:300px;padding:0;">
<label><input type=radio name="passwordgenmethod" value="g" <?php echo $_POST['passwordgenmethod']=="spec" ? "" : 'checked="checked"'; ?> /><?php echo lang('password_gen_gen'); ?></label><br />
<label><input type=radio name="passwordgenmethod" value="spec" <?php echo $_POST['passwordgenmethod']=="spec" ? 'checked="checked"' : ""; ?>><?php echo lang('password_gen_specify'); ?></label><br />
<div style="padding-left:20px">
<label for="specifiedpassword" style="width:120px"><?php echo lang('change_password_new'); ?>:</label>
<input type="password" name="specifiedpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
<label for="confirmpassword" style="width:120px"><?php echo lang('change_password_confirm'); ?>:</label>
<input type="password" name="confirmpassword" onkeypress="document.userform.passwordgenmethod[1].checked=true;" size="20" autocomplete="off" /><br />
<span class="warning" style="font-weight:normal"><?php echo lang('password_gen_length'); ?></span>
</div>
</fieldset>
<fieldset style="width:300px;padding:0;">
<label>
<input type="radio" name="passwordnotifymethod" value="e" <?php echo $_POST['passwordnotifymethod']=="e" ? 'checked="checked"' : ""; ?> /><?php echo lang('password_method_email'); ?>
</label><br />
<label>
<input type="radio" name="passwordnotifymethod" value="s" <?php echo $_POST['passwordnotifymethod']=="e" ? "" : 'checked="checked"'; ?> /><?php echo lang('password_method_screen'); ?>
</label>
</fieldset>
</span>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_email'); ?>:</th>
        <td>
            <input type="text" name="email" class="inputBox" value="<?php echo htmlspecialchars($user['email']); ?>" />
            <input type="hidden" name="oldemail" value="<?php echo htmlspecialchars(!empty($user['oldemail']) ? $user['oldemail']:$user['email']); ?>" />
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_role'); ?>:</th>
        <td>
            <?php
            if($userid==$modx->getLoginUserID()) {
                if($modx->hasPermission('save_role')) {
                    $where = 'save_role=1';
                } else {
                    $where = 'save_role=0';
                }
            } else {
                $where = '';
            }
            $rs = $modx->db->select(
                'name, id'
                , '[+prefix+]user_roles'
                , $where
                , 'save_role DESC, new_role DESC, id ASC'
            );
            $options = array();
            while ($row = $modx->db->getRow($rs)) {
                if (input_any('a') == 11) {
                    $selected = ($row['id'] == evo()->config['default_role']);
                } else {
                    $selected = ($row['id'] == $user['role']);
                }
                $options[] = html_tag(
                    'option'
                    , array(
                        'value' => $row['id'],
                        'selected' => $selected ? null : ''
                    )
                    , $row['name']
                );
            }
            ?>
            <select name="role" class="inputBox">
                <?php echo implode("\n",$options);?>
            </select>
        </td>
    </tr>
</table>
