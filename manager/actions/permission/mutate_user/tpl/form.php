<?php
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';
// 管理画面・エディター・ファイルのタブは、グローバル設定と同じ設定の定義から描く（ユーザーごとに変えられる行だけ）
$settingsForm = new EvoSettingsForm(
    EvoSettingsForm::definition(),
    $user,
    EvoSettingsForm::storedValues(),
    'user'
);
echo EvoSettingsForm::style();
?>
<form action="index.php?a=32" method="post" name="userform" enctype="multipart/form-data">
    <input type="hidden" name="mode" value="<?= $action ?>">
    <input type="hidden" name="userid" value="<?= $userid ?>">
    <input type="hidden" name="blockedmode" value="<?= blockedmode($user) ?>"/>
    <h1><?= lang('user_title') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <?php
            echo aButtonSave();
            echo aButtonDelete($action, $userid);
            echo aButtonCancel();
            ?>
        </ul>
    </div>
    <!-- Tab Start -->
    <div class="sectionBody">
        <div class="tab-pane" id="userPane">
            <div class="tab-page" id="tabGeneral">
                <?php include __DIR__ . '/form/tab_general.php'; ?>
            </div>
            <div class="tab-page" id="tabProfile">
                <?php include __DIR__ . '/form/tab_profile.php'; ?>
            </div>
            <div class="tab-page" id="tabSettings">
                <?php include __DIR__ . '/form/tab_settings.php'; ?>
            </div>
            <!-- 管理画面・エディター・ファイルの設定 -->
            <?= $settingsForm->render() ?>
            <?php
            if ($modx->config['use_udperms'] == 1) {
                echo '<div class="tab-page" id="tabAccess">';
                include __DIR__ . '/form/tab_access.php';
                echo '</div>';
            }
            // invoke OnUserFormRender event
            $tmp = [
                'id' => $userid,
                'usersettings' => $user
            ];
            $evtOut = evo()->invokeEvent('OnUserFormRender', $tmp);
            if (is_array($evtOut)) {
                echo implode('', $evtOut);
            }
            ?>
        </div>
    </div>
    <input type="submit" name="save" style="display:none">
</form>
