<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('change_password')) {
    alert()->setError(3);
    alert()->dumpError();
}
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
            <?php // パスワード管理ソフトが「この利用者の新しいパスワードの設定」と分かるように、ログイン名を添える（送信はしない） ?>
            <input type="text" hidden readonly autocomplete="username" value="<?= hsc(evo()->getLoginUserName()) ?>"/>
            <p><?= lang('password_rule') ?></p>
            <table border="0" cellspacing="0" cellpadding="4">
                <tr>
                    <td><?= lang('username') ?>:</td>
                    <td><b><?= hsc(evo()->getLoginUserName()) ?></b></td>
                </tr>
                <tr>
                    <td><?= lang('change_password_new') ?>:</td>
                    <td><input type="password" name="pass1" class="inputBox" style="width:150px" value=""
                               autocomplete="new-password"/></td>
                </tr>
                <tr>
                    <td><?= lang('change_password_confirm') ?>:</td>
                    <td><input type="password" name="pass2" class="inputBox" style="width:150px" value=""
                               autocomplete="new-password"/></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="actionButtons" style="float:right;">
                            <a class="default" href="#"
                               onclick="documentDirty=false; document.userform.save.click();"><?= lang('update') ?></a>
                        </div>
                    </td>
                </tr>
            </table>
            <input type="submit" name="save" style="display:none">
        </form>
    </div>
</div>
