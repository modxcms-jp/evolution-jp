<?php
$errors = 0;
$install_aborted = false;
$keep_login = false;
ob_start();
include_once(MODX_SETUP_PATH . 'instprocessor.php');
$content = ob_get_contents();
ob_end_clean();

// 見出しは結果に合わせる（中断・エラーのときに「実行しました」と出さない）
if ($install_aborted) {
    $results_title = lang('install_aborted');
} elseif ($errors > 0) {
    $results_title = lang('install_results_failed');
} else {
    $results_title = lang('install_results');
}
?>
<h2><?= $results_title ?></h2>
<?php
echo $content;
if (empty($keep_login)) {
    session_destroy();
}

if ($install_aborted) {
    ?>
    <p class="buttonlinks">
        <a href="index.php?action=mode" title="<?= lang('install_start_over') ?>">
            <span><?= lang('install_start_over') ?></span>
        </a>
    </p>
    <?php
    // index.php は include の戻り値で読み込みの成否を見るので、true を返す
    return true;
}

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
    document.querySelectorAll('#closepage span').forEach(function (el) {
        el.addEventListener('click', function () {
            var rminstaller = document.getElementById('rminstaller');
            if (rminstaller && rminstaller.checked) {
                window.location.href = "../manager/processors/remove_installer.processor.php?rminstall=1";
            } else {
                window.location.href = "../manager/";
            }
        });
    });
</script>
