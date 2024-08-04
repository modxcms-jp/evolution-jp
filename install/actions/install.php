<h2><?= lang('install_results') ?></h2>
<?php
ob_start();
include_once(MODX_SETUP_PATH . 'instprocessor.php');
$content = ob_get_contents();
ob_end_clean();
echo $content;
session_destroy();

if ($errors == 0) {
    // check if install folder is removeable
    if ((is_writable('../install') || is_webmatrix()) && !is_iis()) { ?>
        <label style="float:left;line-height:18px;">
            <input
                type="checkbox" id="rminstaller" value="1" checked
            ><?= lang('remove_install_folder_auto') ?>
        </label>
        <?php
    } else {
        ?>
        <span
            style="float:left;color:#505050;line-height:18px;"
        ><?= lang('remove_install_folder_manual') ?></span>
        <?php
    }
}
?>
<p class="buttonlinks">
    <a id="closepage" title="<?= lang('btnclose_value') ?>">
        <span><?= lang('btnclose_value') ?></span>
    </a>
</p>
<br>
<br>
<script>
    jQuery('#closepage span').click(function () {
        if (jQuery('#rminstaller').prop('checked')) {
            window.location.href = "../manager/processors/remove_installer.processor.php?rminstall=1";
        } else {
            window.location.href = "../manager/";
        }
    });
</script>
