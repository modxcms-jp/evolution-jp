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

<h1><?= lang('role_management_title') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                                           onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?>
            </a></li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <p><?= lang('role_management_msg') ?></p>

        <ul class="actionButtons">
            <li><a class="default" href="index.php?a=38"><?= lang('new_role') ?></a></li>
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
            <?php
            while ($row = db()->getRow($rs)) {
                if ($row['id'] == 1) {
                    ?>
                    <li><span><i><?= "(" . (int) $row['id'] . ") " . hsc($row['name']) ?></i></span> -
                        <i><?= lang('administrator_role_message') ?></i></li>
                    <?php
                } else {
                    ?>
                    <li><span><a
                                href="index.php?id=<?= (int)$row['id'] ?>&a=35"><?= "(" . (int) $row['id'] . ") " . hsc($row['name']) ?></a></span>
                        - <?= hsc($row['description']) ?></li>
                    <?php
                }
            }

            ?>
        </ul>
    </div>
</div>
