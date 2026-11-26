<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('change_password')) {
    alert()->setError(3);
    alert()->dumpError();
}
require_once __DIR__ . '/mutate_user/user_form_rows.php';

if (sessionv('onetime_msg') !== null) {
    $onetime_msg = sessionv('onetime_msg');
    sessionv('*onetime_msg', null);
} else {
    $onetime_msg = '';
}
?>

<h1><?= lang('change_password') ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                                           onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?></a></li>
    </ul>
</div>
<div class="section">
    <div class="sectionBody">
        <?= $onetime_msg ?>
        <form action="index.php" method="post" name="userform">
            <input type="hidden" name="a" value="34"/>
            <table class="settings">
                <?php
                echo EvoFieldRow::render([
                    'label' => lang('username'),
                    'body' => '<b>' . hsc(evo()->getLoginUserName()) . '</b>',
                ]);
                // パスワード管理ツールに、この利用者の新しいパスワードと分かるよう、ログイン名を添える（送信はしない）
                echo userFormPasswordRow('change', ['self' => true, 'username' => (string) evo()->getLoginUserName()]);
                ?>
            </table>
            <div class="actionButtons">
                <a class="default" href="#"
                   onclick="documentDirty=false; document.userform.save.click(); return false;"><?= lang('update') ?></a>
            </div>
            <input type="submit" name="save" class="hidden">
        </form>
    </div>
</div>
