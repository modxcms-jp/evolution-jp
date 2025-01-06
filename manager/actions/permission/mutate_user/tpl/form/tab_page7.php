<h2 class="tab"><?= lang('settings_misc') ?></h2>
<table class="settings">
    <tr>
        <th><?= lang('filemanager_path_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 300px;" name="filemanager_path"
                   value="<?= htmlspecialchars(isset($user['filemanager_path']) ? $user['filemanager_path'] : "") ?>">
            <div><?= lang('filemanager_path_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('uploadable_images_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_images"
                   value="<?= isset($user['upload_images']) ? $user['upload_images'] : "" ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_images"
                                       value="1" <?= isset($user['upload_images']) ? '' : 'checked' ?> /> <?= lang('user_use_config') ?>
            </label>
            <div><?= lang('uploadable_images_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('uploadable_media_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_media"
                   value="<?= isset($user['upload_media']) ? $user['upload_media'] : "" ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_media"
                                       value="1" <?= isset($user['upload_media']) ? '' : 'checked' ?> /> <?= lang('user_use_config') ?>
            </label>
            <div><?= lang('uploadable_media_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('uploadable_files_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 250px;" name="upload_files"
                   value="<?= isset($user['upload_files']) ? $user['upload_files'] : "" ?>">
            &nbsp;&nbsp; <label><input type="checkbox" name="default_upload_files"
                                       value="1" <?= isset($user['upload_files']) ? '' : 'checked' ?> /> <?= lang('user_use_config') ?>
            </label>
            <div><?= lang('uploadable_files_message') . lang('user_upload_message') ?></div>
        </td>
    </tr>
    <tr class='row2'>
        <th><?= lang('upload_maxsize_title') ?></th>
        <td>
            <input type='text' maxlength='255' style="width: 300px;" name="upload_maxsize"
                   value="<?= evo()->config('upload_maxsize', $modx->manager->getUploadMaxsize()) ?>">
            <div><?= sprintf(lang('upload_maxsize_message'), $modx->manager->getUploadMaxsize()) ?></div>
        </td>
    </tr>
    <tr id='rbRow1' class='row3'
        style="display: <?= $modx->config['use_browser'] == 1 ? $displayStyle : 'none' ?>">
        <th><?= lang('rb_base_dir_title') ?></th>
        <td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_dir"
                   value="<?= isset($user["rb_base_dir"]) ? $user["rb_base_dir"] : "" ?>"/>
            <div><?= lang('rb_base_dir_message') ?></div>
        </td>
    </tr>
    <tr id='rbRow4' class='row3'
        style="display: <?= $modx->config['use_browser'] == 1 ? $displayStyle : 'none' ?>">
        <th><?= lang('rb_base_url_title') ?></th>
        <td><input type='text' maxlength='255' style="width: 300px;" name="rb_base_url"
                   value="<?= isset($user["rb_base_url"]) ? $user["rb_base_url"] : "" ?>"/>
            <div><?= lang('rb_base_url_message') ?></div>
        </td>
    </tr>
</table>
