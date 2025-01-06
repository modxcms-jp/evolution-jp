<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('change_password')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (isset($_SESSION['onetime_msg'])) {
    $onetime_msg = $_SESSION['onetime_msg'];
    unset($_SESSION['onetime_msg']);
} else {
    $onetime_msg = '';
}
?>

<h1><?= $_lang['change_password'] ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                                           onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?></a></li>
    </ul>
</div>
<div class="section">
    <div class="sectionBody">
        <?= $onetime_msg ?>
        <form action="index.php" method="post" name="userform">
            <input type="hidden" name="a" value="34"/>
            <p><?= $_lang['change_password_message'] ?></p>
            <table border="0" cellspacing="0" cellpadding="4">
                <tr>
                    <td><?= $_lang["username"] ?>:</td>
                    <td><b><?= $modx->getLoginUserName() ?></b></td>
                </tr>
                <tr>
                    <td><?= $_lang['change_password_new'] ?>:</td>
                    <td><input type="password" name="pass1" class="inputBox" style="width:150px" value=""
                               autocomplete="off"/></td>
                </tr>
                <tr>
                    <td><?= $_lang['change_password_confirm'] ?>:</td>
                    <td><input type="password" name="pass2" class="inputBox" style="width:150px" value=""
                               autocomplete="off"/></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="actionButtons" style="float:right;">
                            <a class="default" href="#"
                               onclick="documentDirty=false; document.userform.save.click();"><img
                                    src="<?= $_style["icons_save"] ?>"/> <?= $_lang['update'] ?></a>
                        </div>
                    </td>
                </tr>
            </table>
            <input type="submit" name="save" style="display:none">
        </form>
    </div>
</div>
