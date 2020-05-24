<h2 class="tab"><?php echo lang('profile') ?></h2>
<table class="settings">
    <tr>
        <th><?php echo lang('user_full_name'); ?>:</th>
        <td><input type="text" name="fullname" class="inputBox" value="<?php echo hsc(user('fullname','')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_phone'); ?>:</th>
        <td><input type="text" name="phone" class="inputBox" value="<?php echo hsc(user('phone')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_mobile'); ?>:</th>
        <td><input type="text" name="mobilephone" class="inputBox" value="<?php echo hsc(user('mobilephone')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_fax'); ?>:</th>
        <td><input type="text" name="fax" class="inputBox" value="<?php echo hsc(user('fax')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_street'); ?>:</th>
        <td><input type="text" name="street" class="inputBox" value="<?php echo hsc(user('street')); ?>" onchange="documentDirty=true;" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_city'); ?>:</th>
        <td><input type="text" name="city" class="inputBox" value="<?php echo hsc(user('city')); ?>" onchange="documentDirty=true;" /></td>
    </tr>

    <tr>
        <th><?php echo lang('user_state'); ?>:</th>
        <td><input type="text" name="state" class="inputBox" value="<?php echo hsc(user('state')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_zip'); ?>:</th>
        <td><input type="text" name="zip" class="inputBox" value="<?php echo hsc(user('zip')); ?>" /></td>
    </tr>
    <tr>
        <th><?php echo lang('user_country'); ?>:</th>
        <td>
            <select size="1" name="country" class="inputBox">
                <?php $chosenCountry = postv('country', user('country')); ?>
                <option value="" <?php echo selected(empty($chosenCountry)); ?> >&nbsp;</option>
                <?php
                foreach ($_country_lang as $key => $country)
                {
                    echo '<option value="' . $key . '"'.selected(isset($chosenCountry) && $chosenCountry == $key) .">{$country}</option>\n";
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_dob'); ?>:</th>
        <td>
            <input type="text" id="dob" name="dob" class="DatePicker" value="<?php echo (user('dob') ? evo()->toDateFormat(user('dob'),'dateOnly'):'0'); ?>" onblur="documentDirty=true;">
            <a onclick="document.userform.dob.value=''; return true;" style="cursor:pointer; cursor:hand"><img align="absmiddle" src="media/style/<?php echo $modx->config['manager_theme']; ?>/images/icons/cal_nodate.gif"  border="0" alt="<?php echo lang('remove_date'); ?>"></a>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_gender'); ?>:</th>
        <td><select name="gender" class="inputBox">
                <option value="0"></option>
                <option value="1" <?php echo selected(user('gender')==1); ?>><?php echo lang('user_male'); ?></option>
                <option value="2" <?php echo selected(user('gender')==2); ?>><?php echo lang('user_female'); ?></option>
                <option value="3" <?php echo selected(user('gender')==3); ?>><?php echo lang('user_other'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th valign="top"><?php echo lang('comment'); ?>:</th>
        <td>
            <textarea type="text" name="comment" class="inputBox"  rows="5"><?php echo hsc(user('comment')); ?></textarea>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('user_photo') ?></th>
        <td><input type="text" maxlength="255" style="width: 150px;" name="photo" value="<?php echo hsc(user('photo')); ?>" />
            <input type="button" value="<?php echo lang('insert'); ?>" onclick="BrowseServer();" />
            <div><?php echo lang('user_photo_message'); ?></div>
            <div>
                <?php
                if(isset($_POST['photo'])) {
                    $photo = $_POST['photo'];
                } elseif(user('photo')) {
                    $photo = user('photo');
                } else {
                    $photo = $modx->config['base_url'] . 'manager/' . style('tx');
                }

                if(strpos($photo, '/') !== 0 && !preg_match('@^https?://@',$photo)) {
                    $photo = $modx->config['base_url'] . $photo;
                }
                ?>
                <img name="iphoto" src="<?php echo $photo; ?>" />
            </div>
        </td>
    </tr>
</table>
