<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('access_permissions')) {
    alert()->setError(3);
    alert()->dumpError();
}

// find all document groups, for the select :)
$rs = db()->select('*', '[+prefix+]documentgroup_names', '', 'name');
if (db()->count($rs) < 1) {
    $docgroupselector = '[no groups to add]';
} else {
    $docgroupselector = '<select name="document_group">' . "\n";
    while ($row = db()->getRow($rs)) {
        $docgroupselector .= "\t" . '<option value="' . (int) $row['id'] . '">' . hsc($row['name']) . "</option>\n";
    }
    $docgroupselector .= "</select>\n";
}

$rs = db()->select('*', '[+prefix+]membergroup_names', '', 'name');
if (db()->count($rs) < 1) {
    $usrgroupselector = '[no user groups]';
} else {
    $usrgroupselector = '<select name="user_group">' . "\n";
    while ($row = db()->getRow($rs)) {
        $usrgroupselector .= "\t" . '<option value="' . (int) $row['id'] . '">' . hsc($row['name']) . "</option>\n";
    }
    $usrgroupselector .= "</select>\n";
}

?>
<h1><?= lang('mgr_access_permissions') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#"
                                           onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?>
            </a></li>
    </ul>
</div>

<div class="sectionBody">
    <p><?= lang('access_permissions_introtext') ?></p><?= config('use_udperms') != 1 ? '<p>' . lang('access_permissions_off') . '</p>' : '' ?>

    <div class="tab-pane" id="tabPane1">

        <div class="tab-page" id="tabPage1">
            <h2 class="tab"><?= lang('access_permissions_user_groups') ?></h2>
            <?php
            // User Groups
            echo '<p>' . lang('access_permissions_users_tab') . '</p>';
            ?>
            <h3><?= lang('access_permissions_add_user_group') ?></h3>
            <form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data" class="list-toolbar">
                <input type="hidden" name="a" value="41"/>
                <input type="hidden" name="operation" value="add_user_group"/>
                <input type="text" value="" name="newusergroup"/>
                <input type="submit" value="<?= lang('submit') ?>"/>
            </form>
            <?php
            $field = 'groupnames.*, `users`.id AS user_id, `users`.username user_name';
            $from = "[+prefix+]membergroup_names AS `groupnames`";
            $from .= " LEFT JOIN [+prefix+]member_groups AS `groups` ON `groups`.`user_group` = `groupnames`.`id`";
            $from .= " LEFT JOIN [+prefix+]manager_users AS `users` ON `users`.`id` = `groups`.`member`";
            $orderby = 'groupnames.name';
            $rs = db()->select($field, $from, '', $orderby);
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span>';
            } else {
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] !== $pid) {
                        if ($pid != '') {
                            echo "</li></ul></li>\n";
                        } // close previous one

                        // display the current user group with a rename/delete form
                        echo '<li><form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data">' . "\n" .
                            '<input type="hidden" name="a" value="41" />' . "\n" .
                            '<input type="hidden" name="user_group" value="' . (int) $row['id'] . '" />' . "\n" .
                            '<input type="hidden" name="operation" value="rename_user_group" />' . "\n" .
                            '<input type="text" name="newgroupname" value="' . hsc($row['name']) . '" />' . "\n" .
                            '<input type="submit" value="' . lang('rename') . '" />' . "\n" .
                            '<input type="button" value="' . lang('delete') . '" data-evo-post data-href="' . hsc('index.php?a=41&user_group=' . (int) $row['id'] . '&operation=delete_user_group') . '" />' . "\n" .
                            '</form>';

                        echo "<ul>\n";
                        echo "\t<li>" . lang('access_permissions_users_in_group') . ' ';
                    }
                    if (!$row['user_id']) {
                        // no users in group
                        echo '<i>' . lang('access_permissions_no_users_in_group') . '</i>';
                        $pid = $row['id'];
                        continue;
                    }
                    if ($pid == $row['id']) {
                        echo ', ';
                    } // comma separation :)
                    echo '<a href="index.php?a=12&amp;id=' . (int) $row['user_id'] . '">' . hsc($row['user_name']) . '</a>';
                    $pid = $row['id'];
                }
                echo "</li></ul></li>\n";
                echo "</ul>\n";
            }
            ?>
        </div>


        <div class="tab-page" id="tabPage2">
            <h2 class="tab"><?= lang('access_permissions_resource_groups') ?></h2>
            <?php
            // Document Groups

            echo '<p>' . lang('access_permissions_resources_tab') . '</p>';
            ?>
            <h3><?= lang('access_permissions_add_resource_group') ?></h3>
            <form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data" class="list-toolbar">
                <input type="hidden" name="a" value="41"/>
                <input type="hidden" name="operation" value="add_document_group"/>
                <input type="text" value="" name="newdocgroup"/>
                <input type="submit" value="<?= lang('submit') ?>"/>
            </form>
            <?php
            $field = 'dgnames.id, dgnames.name, sc.id AS doc_id, sc.pagetitle AS doc_title';
            $from = "[+prefix+]documentgroup_names AS dgnames";
            $from .= " LEFT JOIN [+prefix+]document_groups AS dg ON dg.document_group = dgnames.id";
            $from .= " LEFT JOIN [+prefix+]site_content AS sc ON sc.id = dg.document";
            $orderby = 'dgnames.name, sc.id';
            $rs = db()->select($field, $from, '', $orderby);
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span>';
            } else {
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] !== $pid) {
                        if ($pid != '') {
                            echo "</li></ul></li>\n";
                        } // close previous one

                        echo '<li><form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data">' . "\n" .
                            '<input type="hidden" name="a" value="41" />' . "\n" .
                            '<input type="hidden" name="document_group" value="' . (int) $row['id'] . '" />' . "\n" .
                            '<input type="hidden" name="operation" value="rename_document_group" />' . "\n" .
                            '<input type="text" name="newgroupname" value="' . hsc($row['name']) . '">' . "\n" .
                            '<input type="submit" value="' . lang('rename') . '">' . "\n" .
                            '<input type="button" value="' . lang('delete') . '" data-evo-post data-href="' . hsc('index.php?a=41&document_group=' . (int) $row['id'] . '&operation=delete_document_group') . '" />' . "\n" .
                            '</form>';

                        echo "<ul>\n";
                        echo "\t<li>" . lang('access_permissions_resources_in_group') . ' ';
                    }
                    if (!$row['doc_id']) {
                        // no documents in group
                        echo lang('access_permissions_no_resources_in_group');
                        $pid = $row['id'];
                        continue;
                    }
                    if ($pid == $row['id']) {
                        echo ", \n";
                    }
                    echo '<a href="index.php?a=3&amp;id=' . (int) $row['doc_id'] . '" title="' . hsc($row['doc_title']) . '">' . (int) $row['doc_id'] . '</a>';
                    $pid = $row['id'];
                }
                echo "</li></ul></li>\n";
                echo "</ul>\n";
            }
            ?>
        </div>

        <div class="tab-page" id="tabPage3">
            <h2 class="tab"><?= lang('access_permissions_links') ?></h2>
            <?php
            // User/Document Group Links

            echo '<p>' . lang('access_permissions_links_tab') . '</p>';

            $field = 'groupnames.*, groupacc.id AS link_id, dgnames.id AS dg_id, dgnames.name AS dg_name';
            $from = "[+prefix+]membergroup_names AS groupnames";
            $from .= " LEFT JOIN [+prefix+]membergroup_access AS groupacc ON groupacc.membergroup = groupnames.id";
            $from .= " LEFT JOIN [+prefix+]documentgroup_names AS dgnames ON dgnames.id = groupacc.documentgroup";
            $rs = db()->select($field, $from, '', 'name');
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span><br />';
            } else {
                ?>
                <h3><?= lang("access_permissions_group_link") ?></h3>
                <form method="post" action="index.php" name="accesspermissions" class="list-toolbar">
                    <input type="hidden" name="a" value="41"/>
                    <input type="hidden" name="operation" value="add_document_group_to_user_group"/>
                    <label><?= lang("access_permissions_link_user_group") ?> <?= $usrgroupselector ?></label>
                    <label><?= lang("access_permissions_link_to_group") ?> <?= $docgroupselector ?></label>
                    <input type="submit" value="<?= lang('submit') ?>">
                </form>
                <?php
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] != $pid) {
                        if ($pid != '') {
                            echo "</ul></li>\n";
                        } // close previous one
                        echo '<li><b>' . hsc($row['name']) . '</b>';

                        if (!$row['dg_id']) {
                            echo ' &raquo; <i>' . lang('no_groups_found') . "</i></li>\n";
                            $pid = '';
                            continue;
                        } else {
                            echo "<ul>\n";
                        }
                    }
                    if (!$row['dg_id']) {
                        continue;
                    }
                    echo "\t<li>" . hsc($row['dg_name']);
                    echo ' <small><i>(<a href="' . hsc('index.php?a=41&coupling=' . (int) $row['link_id'] . '&operation=remove_document_group_from_user_group') . '" data-evo-post>';
                    echo lang('remove') . '</a>)</i></small>';
                    echo "</li>\n";

                    $pid = $row['id'];
                }
                echo '</ul>';
            }
            ?>
        </div>

    </div>
</div>
<script type="text/javascript">tp1 = new WebFXTabPane(document.getElementById("tabPane1"), true);</script>
