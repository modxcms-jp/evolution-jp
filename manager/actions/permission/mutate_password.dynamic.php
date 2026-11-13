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
    unset($_SESSION['onetime_msg']);
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
            <p><?= lang('change_password_message') ?></p>
            <table border="0" cellspacing="0" cellpadding="4">
                <tr>
                    <td><?= lang("username") ?>:</td>
                    <td><b><?= hsc($modx->getLoginUserName()) ?></b></td>
                </tr>
                <tr>
                    <td><?= lang('change_password_new') ?>:</td>
                    <td><input type="password" name="pass1" class="inputBox" style="width:150px" value=""
                               autocomplete="off"/></td>
                </tr>
                <tr>
                    <td><?= lang('change_password_confirm') ?>:</td>
                    <td><input type="password" name="pass2" class="inputBox" style="width:150px" value=""
                               autocomplete="off"/></td>
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
