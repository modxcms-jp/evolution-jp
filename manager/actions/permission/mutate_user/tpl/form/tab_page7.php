<h2 class="tab"><?php echo lang('settings_misc') ?></h2>
<table class="settings">
    <tr>
        <th><?php echo lang('filemanager_path_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 300px;" name="filemanager_path"
                   value="<?php echo htmlspecialchars(isset($user['filemanager_path']) ? $user['filemanager_path'] : ""); ?>">
            <div><?php echo lang('filemanager_path_message'); ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('uploadable_images_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_images"
                   value="<?php echo isset($user['upload_images']) ? $user['upload_images'] : ""; ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_images"
                                       value="1" <?php echo isset($user['upload_images']) ? '' : 'checked'; ?> /> <?php echo lang('user_use_config'); ?>
            </label>
            <div><?php echo lang('uploadable_images_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('uploadable_media_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_media"
                   value="<?php echo isset($user['upload_media']) ? $user['upload_media'] : ""; ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_media"
                                       value="1" <?php echo isset($user['upload_media']) ? '' : 'checked'; ?> /> <?php echo lang('user_use_config'); ?>
            </label>
            <div><?php echo lang('uploadable_media_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('uploadable_flash_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_flash"
                   value="<?php echo isset($user['upload_flash']) ? $user['upload_flash'] : ""; ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_flash"
                                       value="1" <?php echo isset($user['upload_flash']) ? '' : 'checked'; ?> /> <?php echo lang('user_use_config'); ?>
            </label>
            <div><?php echo lang('uploadable_flash_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('uploadable_files_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_files"
                   value="<?php echo isset($user['upload_files']) ? $user['upload_files'] : ""; ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_files"
                                       value="1" <?php echo isset($user['upload_files']) ? '' : 'checked'; ?> /> <?php echo lang('user_use_config'); ?>
            </label>
            <div><?php echo lang('uploadable_files_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr class='row2'>
        <th><?php echo lang('upload_maxsize_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 300px;" name="upload_maxsize"
                   value="<?php echo isset($user['upload_maxsize']) ? $user['upload_maxsize'] : ""; ?>">
            <div><?php echo sprintf(lang('upload_maxsize_message'), $modx->manager->getUploadMaxsize()) ?></div>
        </td>
    </tr>
    <tr id='rbRow1' class='row3'
        style="display: <?php echo $modx->config['use_browser'] == 1 ? $displayStyle : 'none'; ?>">
        <th><?php echo lang('rb_base_dir_title') ?></th>
        <td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_dir"
                   value="<?php echo isset($user["rb_base_dir"]) ? $user["rb_base_dir"] : ""; ?>"/>
            <div><?php echo lang('rb_base_dir_message') ?></div>
        </td>
    </tr>
    <tr id='rbRow4' class='row3'
        style="display: <?php echo $modx->config['use_browser'] == 1 ? $displayStyle : 'none'; ?>">
        <th><?php echo lang('rb_base_url_title') ?></th>
        <td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_url"
                   value="<?php echo isset($user["rb_base_url"]) ? $user["rb_base_url"] : ""; ?>"/>
            <div><?php echo lang('rb_base_url_message') ?></div>
        </td>
    </tr>
</table>
