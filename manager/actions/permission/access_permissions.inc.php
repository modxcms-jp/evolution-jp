<?php
/**
 * 権限の画面（管理ユーザー a=40・ウェブユーザー a=91）の共通のひな形。
 * 呼び出す側で権限を確かめ、$access に次の値を入れてから読み込む。テーブル名・列名は固定の値（利用者の入力を入れない）。
 *   action        送り先の処理の番号（41・92）
 *   title         見出しの言語キー
 *   groups_tab    1 つ目のタブの見出しの言語キー
 *   users_in      「グループ内ユーザー」の言語キー
 *   group_names   ユーザーグループのテーブル
 *   members       ユーザーとグループの結び付きのテーブルと、グループの列・ユーザーの列
 *   users         ユーザーのテーブル
 *   user_action   ユーザーの編集画面の処理の番号（12・88）
 *   access        ユーザーグループと文書グループの結び付きのテーブルと、ユーザーグループの列
 */
if (!isset($modx) || !evo()->isLoggedin() || !isset($access)) {
    exit;
}

$action = (int) $access['action'];

$groupSelector = function (string $table, string $name, string $empty): string {
    $rs = db()->select('id, name', "[+prefix+]{$table}", '', 'name');
    if (db()->count($rs) < 1) {
        return $empty;
    }
    $html = '<select name="' . $name . '">' . "\n";
    while ($row = db()->getRow($rs)) {
        $html .= "\t" . '<option value="' . (int) $row['id'] . '">' . hsc($row['name']) . "</option>\n";
    }
    return $html . "</select>\n";
};
$docgroupselector = $groupSelector('documentgroup_names', 'document_group', '[no groups to add]');
$usrgroupselector = $groupSelector($access['group_names'], 'user_group', '[no user groups]');

// グループの名前を変える・消すフォーム（一覧の 1 行）
$renameForm = function (string $field, int $id, string $name, string $deleteOperation) use ($action): string {
    return '<li><form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data" class="field-inline">' . "\n" .
        '<input type="hidden" name="a" value="' . $action . '" />' . "\n" .
        '<input type="hidden" name="' . $field . '" value="' . $id . '" />' . "\n" .
        '<input type="hidden" name="operation" value="rename_' . $field . '" />' . "\n" .
        '<input type="text" name="newgroupname" value="' . hsc($name) . '" />' . "\n" .
        '<input type="submit" value="' . lang('rename') . '" />' . "\n" .
        '<input type="button" value="' . lang('delete') . '" data-evo-post data-href="' . hsc("index.php?a={$action}&{$field}={$id}&operation={$deleteOperation}") . '" />' . "\n" .
        '</form>';
};
?>
<h1><?= lang($access['title']) ?></h1>

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
            <h2 class="tab"><?= lang($access['groups_tab']) ?></h2>
            <p><?= lang('access_permissions_users_tab') ?></p>
            <h3><?= lang('access_permissions_add_user_group') ?></h3>
            <form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data" class="list-toolbar">
                <input type="hidden" name="a" value="<?= $action ?>"/>
                <input type="hidden" name="operation" value="add_user_group"/>
                <input type="text" value="" name="newusergroup"/>
                <input type="submit" value="<?= lang('submit') ?>"/>
            </form>
            <?php
            [$membersTable, $memberGroupCol, $memberUserCol] = $access['members'];
            $from = "[+prefix+]{$access['group_names']} AS `groupnames`";
            $from .= " LEFT JOIN [+prefix+]{$membersTable} AS `groups` ON `groups`.`{$memberGroupCol}` = `groupnames`.`id`";
            $from .= " LEFT JOIN [+prefix+]{$access['users']} AS `users` ON `users`.`id` = `groups`.`{$memberUserCol}`";
            $rs = db()->select('groupnames.*, `users`.id AS user_id, `users`.username user_name', $from, '', 'groupnames.name');
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span>';
            } else {
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] !== $pid) {
                        if ($pid != '') {
                            echo "</li></ul></li>\n";
                        }
                        echo $renameForm('user_group', (int) $row['id'], $row['name'], 'delete_user_group');
                        echo "<ul>\n";
                        echo "\t<li>" . lang($access['users_in']) . ' ';
                    }
                    if (!$row['user_id']) {
                        echo '<i>' . lang('access_permissions_no_users_in_group') . '</i>';
                        $pid = $row['id'];
                        continue;
                    }
                    if ($pid == $row['id']) {
                        echo ', ';
                    }
                    echo '<a href="index.php?a=' . (int) $access['user_action'] . '&amp;id=' . (int) $row['user_id'] . '">' . hsc($row['user_name']) . '</a>';
                    $pid = $row['id'];
                }
                echo "</li></ul></li>\n";
                echo "</ul>\n";
            }
            ?>
        </div>

        <div class="tab-page" id="tabPage2">
            <h2 class="tab"><?= lang('access_permissions_resource_groups') ?></h2>
            <p><?= lang('access_permissions_resources_tab') ?></p>
            <h3><?= lang('access_permissions_add_resource_group') ?></h3>
            <form method="post" action="index.php" name="accesspermissions" enctype="multipart/form-data" class="list-toolbar">
                <input type="hidden" name="a" value="<?= $action ?>"/>
                <input type="hidden" name="operation" value="add_document_group"/>
                <input type="text" value="" name="newdocgroup"/>
                <input type="submit" value="<?= lang('submit') ?>"/>
            </form>
            <?php
            $from = '[+prefix+]documentgroup_names AS dgnames';
            $from .= ' LEFT JOIN [+prefix+]document_groups AS dg ON dg.document_group = dgnames.id';
            $from .= ' LEFT JOIN [+prefix+]site_content AS sc ON sc.id = dg.document';
            $rs = db()->select('dgnames.id, dgnames.name, sc.id AS doc_id, sc.pagetitle AS doc_title', $from, '', 'dgnames.name, sc.id');
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span>';
            } else {
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] !== $pid) {
                        if ($pid != '') {
                            echo "</li></ul></li>\n";
                        }
                        echo $renameForm('document_group', (int) $row['id'], $row['name'], 'delete_document_group');
                        echo "<ul>\n";
                        echo "\t<li>" . lang('access_permissions_resources_in_group') . ' ';
                    }
                    if (!$row['doc_id']) {
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
            <p><?= lang('access_permissions_links_tab') ?></p>
            <?php
            [$accessTable, $accessGroupCol] = $access['access'];
            $from = "[+prefix+]{$access['group_names']} AS groupnames";
            $from .= " LEFT JOIN [+prefix+]{$accessTable} AS groupacc ON groupacc.{$accessGroupCol} = groupnames.id";
            $from .= ' LEFT JOIN [+prefix+]documentgroup_names AS dgnames ON dgnames.id = groupacc.documentgroup';
            $rs = db()->select('groupnames.*, groupacc.id AS link_id, dgnames.id AS dg_id, dgnames.name AS dg_name', $from, '', 'groupnames.name');
            if (db()->count($rs) < 1) {
                echo '<span class="warning">' . lang('no_groups_found') . '</span>';
            } else {
                ?>
                <h3><?= lang('access_permissions_group_link') ?></h3>
                <form method="post" action="index.php" name="accesspermissions" class="list-toolbar">
                    <input type="hidden" name="a" value="<?= $action ?>"/>
                    <input type="hidden" name="operation" value="add_document_group_to_user_group"/>
                    <label><?= lang('access_permissions_link_user_group') ?> <?= $usrgroupselector ?></label>
                    <label><?= lang('access_permissions_link_to_group') ?> <?= $docgroupselector ?></label>
                    <input type="submit" value="<?= lang('submit') ?>">
                </form>
                <?php
                echo "<ul>\n";
                $pid = '';
                while ($row = db()->getRow($rs)) {
                    if ($row['id'] != $pid) {
                        if ($pid != '') {
                            echo "</ul></li>\n";
                        }
                        echo '<li><b>' . hsc($row['name']) . '</b>';
                        if (!$row['dg_id']) {
                            echo ' &raquo; <i>' . lang('no_groups_found') . "</i></li>\n";
                            $pid = '';
                            continue;
                        }
                        echo "<ul>\n";
                    }
                    echo "\t<li>" . hsc($row['dg_name']);
                    echo ' <small><i>(<a href="' . hsc("index.php?a={$action}&coupling=" . (int) $row['link_id'] . '&operation=remove_document_group_from_user_group') . '" data-evo-post>';
                    echo lang('remove') . '</a>)</i></small>';
                    echo "</li>\n";
                    $pid = $row['id'];
                }
                if ($pid != '') {
                    echo "</ul></li>\n";
                }
                echo "</ul>\n";
            }
            ?>
        </div>

    </div>
</div>
<script type="text/javascript">tp1 = new WebFXTabPane(document.getElementById("tabPane1"), true);</script>
