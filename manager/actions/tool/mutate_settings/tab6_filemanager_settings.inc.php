<!-- Miscellaneous settings -->
<div class="tab-page" id="tabPage7">
    <h2 class="tab"><?php echo $_lang["settings_misc"] ?></h2>
    <table class="settings">
        <tr>
            <th><?php echo $_lang["filemanager_path_title"] ?></th>
            <td>
                <?php
                if (MODX_BASE_PATH === config('filemanager_path')) {
                    $modx->config['filemanager_path'] = '[(base_path)]';
                }
                ?>
                <?php echo $_lang['default']; ?> <span
                    id="default_filemanager_path">[(base_path)]</span> <?php echo "({$base_path})"; ?><br/>
                <?php echo form_text('filemanager_path', 255, 'id="filemanager_path"'); ?>
                <input type="button" onclick="jQuery('#filemanager_path').val('[(base_path)]');"
                       value="<?php echo $_lang["reset"]; ?>" name="reset_filemanager_path"><br/>
                <?php echo $_lang["filemanager_path_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["uploadable_files_title"] ?></th>
            <td>
                <?php echo form_text('upload_files'); ?><br/>
                <?php echo $_lang["uploadable_files_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["uploadable_images_title"] ?></th>
            <td>
                <?php echo form_text('upload_images'); ?><br/>
                <?php echo $_lang["uploadable_images_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["uploadable_media_title"] ?></th>
            <td>
                <?php echo form_text('upload_media'); ?><br/>
                <?php echo $_lang["uploadable_media_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["uploadable_flash_title"] ?></th>
            <td>
                <?php echo form_text('upload_flash'); ?><br/>
                <?php echo $_lang["uploadable_flash_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["upload_maxsize_title"] ?></th>
            <td>
                <?php
                $limit_size = $modx->manager->getUploadMaxsize();
                if (empty($modx->config['upload_maxsize'])) {
                    $last = substr($limit_size, -1);
                    $limit_size = substr($limit_size, 0, -1);
                    switch (strtolower($last)) {
                        case 'g':
                            $limit_size *= 1024;
                        case 'm':
                            $limit_size *= 1024;
                        case 'k':
                            $limit_size *= 1024;
                            break;
                        default:
                            $limit_size = 5000000;
                    }
                    $settings['upload_maxsize'] = $limit_size;
                }
                ?>
                <?php echo form_text('upload_maxsize'); ?><br/>
                <?php echo sprintf($_lang["upload_maxsize_message"], evo()->nicesize($limit_size)); ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["new_file_permissions_title"] ?></th>
            <td>
                <?php echo form_text('new_file_permissions', 4); ?><br/>
                <?php echo $_lang["new_file_permissions_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["new_folder_permissions_title"] ?></th>
            <td>
                <?php echo form_text('new_folder_permissions', 4); ?><br/>
                <?php echo $_lang["new_folder_permissions_message"] ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang["rb_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('use_browser', '1', $use_browser == '1', 'id="rbRowOn"')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('use_browser', '0', $use_browser == '0', 'id="rbRowOff"')); ?><br/>
                <?php echo $_lang["rb_message"] ?>
            </td>
        </tr>

        <tr class="rbRow" style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["settings_strip_image_paths_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('strip_image_paths', '1', $strip_image_paths == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('strip_image_paths', '0', $strip_image_paths == '0')); ?>
                <br/>
                <?php echo $_lang["settings_strip_image_paths_message"] ?>
            </td>
        </tr>

        <tr class="rbRow" style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["rb_webuser_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('rb_webuser', '1', $rb_webuser == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('rb_webuser', '0', $rb_webuser == '0')); ?><br/>
                <?php echo $_lang["rb_webuser_message"] ?>
            </td>
        </tr>
        <tr class='rbRow' style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["rb_base_dir_title"] ?></th>
            <td>
                <?php
                $default_rb_base_dir = is_dir("{$base_path}content") ? 'content/' : 'assets/';
                if (MODX_BASE_PATH . 'content/' === config('rb_base_dir')) {
                    $modx->config['rb_base_dir'] = '[(base_path)]content/';
                } elseif (MODX_BASE_PATH . 'assets/' === config('rb_base_dir')) {
                    $modx->config['rb_base_dir'] = '[(base_path)]assets/';
                }
                ?>
                <?php echo $_lang['default']; ?> <span
                    id="default_rb_base_dir"><?php echo "[(base_path)]{$default_rb_base_dir}"; ?></span> <?php echo "({$base_path}{$default_rb_base_dir})"; ?>
                <br/>
                <?php echo form_text('rb_base_dir', 255, 'id="rb_base_dir"'); ?>
                <input type="button" onclick="jQuery('#rb_base_dir').val(jQuery('#default_rb_base_dir').text());"
                       value="<?php echo $_lang["reset"]; ?>" name="reset_rb_base_dir"><br/>
                <?php echo $_lang["rb_base_dir_message"] ?></td>
        </tr>
        <tr class='rbRow' style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["rb_base_url_title"] ?></th>
            <td>
                <?php echo $site_url . form_text('rb_base_url'); ?><br/>
                <?php echo $_lang["rb_base_url_message"] ?></td>
        </tr>
        <tr class='rbRow' style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["clean_uploaded_filename"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('clean_uploaded_filename', '1', $clean_uploaded_filename == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('clean_uploaded_filename', '0', $clean_uploaded_filename == '0')); ?><br/>
                <?php echo $_lang["clean_uploaded_filename_message"]; ?>
            </td>
        </tr>
        <tr class='rbRow' style="display: <?php echo $use_browser == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["a17_image_limit_width_title"] ?></th>
            <td>
                <?php echo form_text('image_limit_width'); ?>px<br/>
                <?php echo $_lang["a17_image_limit_width_message"] ?></td>
        </tr>

        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnMiscSettingsRender event
                $evtOut = evo()->invokeEvent("OnMiscSettingsRender");
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
<?php
$evtOut = evo()->invokeEvent('OnSystemSettingsRender');
if (is_array($evtOut)) {
    echo implode('', $evtOut);
}
?>
