<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 一覧の検索語・ページ番号・一覧モードは URL（リクエスト）だけで決め、画面状態には覚えない
$query = get_strvar('search');
$listmode = get_choicevar('listmode', ['0', '1'], '0');

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu('cntxm', 150);
$cm->addItem(
    lang('edit'),
    'js:menuAction(1)',
    style('icons_edit_document'),
    (!evo()->hasPermission('edit_user') ? 1 : 0)
);
$cm->addItem(
    lang('delete'),
    'js:menuAction(2)',
    style('icons_delete'),
    (!evo()->hasPermission('delete_user') ? 1 : 0)
);
echo $cm->render();
?>
<script language="JavaScript" type="text/javascript">
    function searchResource() {
        document.resource.submit();
    }

    function resetSearch() {
        document.resource.search.value = ''
        document.resource.submit();
    }

    function changeListMode() {
        var m = parseInt(document.resource.listmode.value) ? 1 : 0;
        if (m) document.resource.listmode.value = 0;
        else document.resource.listmode.value = 1;
        document.resource.submit();
    }

    var selectedItem;
    var contextm = <?= $cm->getClientScriptObject() ?>;

    function showContentMenu(id, e) {
        selectedItem = id;
        contextm.style.left = (e.pageX || (e.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft))) + "px";
        contextm.style.top = (e.pageY || (e.clientY + (document.documentElement.scrollTop || document.body.scrollTop))) + "px";
        contextm.style.visibility = "visible";
        e.cancelBubble = true;
        return false;
    }

    function menuAction(a) {
        var id = selectedItem;
        switch (a) {
            case 1: // edit
                EvoShell.go('index.php?a=12&id=' + id);
                break;
            case 2: // delete
                if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_user'))) ?>)) {
                    EvoCsrf.post('index.php?a=33&id=' + id);
                }
                break;
        }
    }

    // シェルの遷移で積み重ならないよう 1 回だけ登録する（contextm は実行時に最新の var を読む）
    if (!window.evoContextMenuClose) {
        window.evoContextMenuClose = true;
        document.addEventListener('click', function () {
            contextm.style.visibility = "hidden";
        });
    }

    // 表は画面の差し替えで消える。<script> を表の後ろに置かず、Evo.ready で表ができてから登録する
    Evo.ready(function () {
        const table = document.querySelector('[data-table="users"]');
        if (!table) {
            return;
        }
        table.addEventListener('click', function (e) {
            const el = e.target.closest('[data-row-menu]');
            if (el) {
                showContentMenu(Number(el.dataset.rowMenu), e);
                e.preventDefault();
            }
        });
    });
</script>
<form action="index.php" name="resource" method="get">
    <input type="hidden" name="a" value="75" />
    <input type="hidden" name="id" value="<?= hsc($id ?? '') ?>" />
    <input type="hidden" name="listmode" value="<?= hsc($listmode ?? '') ?>" />

    <h1><?= lang('user_management_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#"
                    onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?></a></li>
        </ul>
    </div>

    <div class="section"><div class="sectionBody">
        <p><?= lang('user_management_msg') ?></p>
        <div class="actionButtons">
            <table border="0" style="width:100%">
                <tr>
                    <?php if (evo()->hasPermission('new_user')): ?>
                        <td><a href="index.php?a=11" class="default"><?= lang('new_user') ?></a>
                        </td>
                    <?php endif; ?>
                    <td nowrap="nowrap">
                        <table border="0" style="float:right">
                            <tr>
                                <td><?= lang("search") ?></td>
                                <td><input class="searchtext" name="search" type="text" size="15"
                                        value="<?= hsc($query) ?>" /></td>
                                <td><a href="#" class="default" title="<?= lang("search") ?>"
                                        onclick="searchResource();return false;"><?= lang('go') ?></a></td>
                                <td><a href="#" title="<?= lang("reset") ?>"
                                        onclick="resetSearch();return false;"><img
                                            src="<?= style('icons_refresh') ?>" /></a>
                                </td>
                                <td><a href="#" title="<?= lang("list_mode") ?>"
                                        onclick="changeListMode();return false;"><img
                                            src="<?= style('icons_table') ?>" /></a></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
        $tbl_manager_users = evo()->getFullTableName('manager_users');
        $tbl_user_attributes = evo()->getFullTableName('user_attributes');
        $tbl_user_roles = evo()->getFullTableName('user_roles');
        $blocked = userBlockedSqlCondition('mua');
        $field = "mu.id,mu.username,roles.name AS rolename,mua.fullname,mua.email,mua.thislogin,mua.logincount,{$blocked} AS blocked";
        $from = "{$tbl_manager_users} AS mu";
        $from .= " INNER JOIN {$tbl_user_attributes} AS mua ON mua.internalKey=mu.id";
        $from .= " LEFT JOIN {$tbl_user_roles} AS roles ON mua.role=roles.id";
        // Build WHERE clause: combine search conditions and role restriction
        $whereParts = [];

        if ($query !== '') {
            $keyword = db()->escape($query);
            $whereParts[] = "(mu.username LIKE '{$keyword}%' OR mua.fullname LIKE '%{$keyword}%' OR mua.email LIKE '{$keyword}%')";
        }

        // Non-admins should not see users with role == 1
        if (sessionv('mgrRole') != 1) {
            $whereParts[] = 'mua.role != 1';
        }

        $where = count($whereParts) ? implode(' AND ', $whereParts) : '';
        $orderby = "{$blocked} ASC, mua.thislogin DESC";

        // listmode=1 は全件を 1 ページに出す
        $total = (int) db()->getValue(db()->select('COUNT(mu.id)', $from, $where));
        $page = get_intvar('page') ?: 1;
        $perPage = $listmode == '1' ? 0 : ((int) evo()->config('number_of_results') ?: 20);
        $pager = new DataTablePager($total, $perPage, $page);
        $params = ['a' => 75];
        if ($query !== '') {
            $params['search'] = $query;
        }
        $links = new DataTableLinks('index.php', $params, $pager->page());

        $ds = db()->select(
            $field,
            $from,
            $where,
            $orderby,
            $pager->limit() ? sprintf('%d,%d', $pager->offset(), $pager->limit()) : ''
        );
        $rows = db()->getRows($ds) ?: [];

        $columns = [
            [
                'key' => 'username',
                'label' => lang('username'),
                'html' => true,
                'format' => function ($value, array $row) {
                    $id = (int) $row['id'];
                    return sprintf(
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" title="%s"><img src="%s" /></a>'
                        . '<span class="%s"><a href="index.php?a=12&amp;id=%d" title="%s">%s</a></span>',
                        $id,
                        hsc(lang('click_to_context')),
                        hsc(style('icons_user')),
                        $row['blocked'] ? 'unpublished' : 'published',
                        $id,
                        hsc(lang('click_to_edit_title')),
                        hsc($value)
                    );
                },
            ],
            ['key' => 'fullname', 'label' => lang('user_full_name')],
            ['key' => 'email', 'label' => lang('email')],
            ['key' => 'rolename', 'label' => lang('role')],
            [
                'key' => 'thislogin',
                'label' => lang('login_button'),
                'format' => function ($value) {
                    if (!$value) {
                        return '-';
                    }
                    return evo()->mb_strftime(evo()->toDateFormat(null, 'formatOnly') . ' %H:%M', $value);
                },
            ],
            ['key' => 'logincount', 'label' => lang('user_logincount')],
            [
                'key' => 'blocked',
                'label' => lang('user_block'),
                'format' => function ($value) {
                    return $value ? lang('yes') : '-';
                },
            ],
        ];
        $table = new DataTable($columns, [
            'id' => 'users',
            'tableClass' => 'grid',
            'emptyMessage' => lang('no_records_found'),
        ]);
        $nav = $pager->render($links, [
            'label' => lang('pagination_label'),
            'first' => lang('pagination_table_first'),
            'last' => lang('pagination_table_last'),
        ]);
        echo $nav . $table->render($rows, $links) . $nav;
        ?>
    </div></div>
</form>
