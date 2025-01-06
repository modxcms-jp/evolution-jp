<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_role')) {
    alert()->setError(3);
    alert()->dumpError();
}
?>
<br/>
<!-- User Roles -->

<h1><?= $_lang['role_management_title'] ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                                           onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    alt="icons_cancel" src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?>
            </a></li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <p><?= $_lang['role_management_msg'] ?></p>

        <ul class="actionButtons">
            <li><a class="default" href="index.php?a=38"><img
                        src="<?= $_style["icons_add"] ?>"/> <?= $_lang['new_role'] ?></a></li>
        </ul>
        <?php

        $tbl_user_roles = evo()->getFullTableName('user_roles');
        $rs = db()->select('name, id, description', $tbl_user_roles, '', 'name');
        $total = db()->count($rs);
        if ($total < 1) {
            echo "The request returned no roles!</div>";
            exit;
            include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        }
        ?>
        <ul>
            <style type="text/css">
                li span {
                    width: 200px;
                }
            </style>
            <?php
            while ($row = db()->getRow($rs)) {
                if ($row['id'] == 1) {
                    ?>
                    <li><span style="width: 200px"><i><?= "({$row['id']}) {$row['name']}" ?></i></span> -
                        <i><?= $_lang['administrator_role_message'] ?></i></li>
                    <?php
                } else {
                    ?>
                    <li><span style="width: 200px"><a
                                href="index.php?id=<?= $row['id'] ?>&a=35"><?= "({$row['id']}) {$row['name']}" ?></a></span>
                        - <?= $row['description'] ?></li>
                    <?php
                }
            }

            ?>
        </ul>
    </div>
</div>
