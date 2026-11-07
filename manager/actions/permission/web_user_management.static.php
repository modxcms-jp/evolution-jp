<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_web_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

manager()->initPageViewState();

$icons_path = manager_style_image_path('icons');

global $_PAGE;

if (request_strvar('op') === 'reset') {
    $query = '';
    $_PAGE['vs']['search'] = '';
} else {
    $query = request_strvar('search') ?: array_get($_PAGE, 'vs.search');
    $_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = request_choicevar('listmode', ['0', '1'], array_get($_PAGE, 'vs.lm'));
$_PAGE['vs']['lm'] = $listmode;


// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
$cm->addItem(
    $_lang["edit"],
    "js:menuAction(1)",
    "{$icons_path}logging.gif",
    (!evo()->hasPermission('edit_user') ? 1 : 0)
);
$cm->addItem(
    $_lang["delete"],
    "js:menuAction(2)",
    "{$icons_path}delete.gif",
    (!evo()->hasPermission('delete_user') ? 1 : 0)
);
echo $cm->render();

?>
<script language="JavaScript" type="text/javascript">
    function searchResource() {
        document.resource.op.value = "srch";
        document.resource.submit();
    }

    function resetSearch() {
        document.resource.search.value = ''
        document.resource.op.value = "reset";
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
                window.location.href = 'index.php?a=88&id=' + id;
                break;
            case 2: // delete
                if (confirm("<?= $_lang['confirm_delete_user'] ?>") == true) {
                    EvoCsrf.post('index.php?a=90&id=' + id);
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
        const table = document.querySelector('[data-table="web-users"]');
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
    <input type="hidden" name="a" value="99" />
    <input type="hidden" name="id" value="<?= hsc($id ?? '') ?>" />
    <input type="hidden" name="listmode" value="<?= hsc($listmode ?? '') ?>" />
    <input type="hidden" name="op" value="" />

    <h1><?= $_lang['web_user_management_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#"
                    onclick="documentDirty=false;document.location.href='index.php?a=2';"><?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <div class="sectionBody">
        <p><?= $_lang['web_user_management_msg'] ?></p>
        <div class="actionButtons">
            <table border="0" style="width:100%">
                <tr>
                    <td><a class="default" href="index.php?a=87"><?= $_lang['new_web_user'] ?></a>
                    </td>
                    <td nowrap="nowrap">
                        <table border="0" style="float:right">
                            <tr>
                                <td><?= $_lang["search"] ?></td>
                                <td>
                                    <input class="searchtext" name="search" type="text" size="15"
                                        value="<?= hsc(request_strvar('search')) ?>" />
                                </td>
                                <td>
                                    <a class="default" href="#" title="<?= $_lang["search"] ?>"
                                        onclick="searchResource();return false;"><?= $_lang["go"] ?></a>
                                </td>
                                <td><a href="#" title="<?= $_lang["reset"] ?>"
                                        onclick="resetSearch();return false;"><img
                                            src="<?= $_style['icons_refresh'] ?>" /></a>
                                </td>
                                <td><a href="#" title="<?= $_lang["list_mode"] ?>"
                                        onclick="changeListMode();return false;"><img
                                            src="<?= $_style['icons_table'] ?>" /></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <?php
        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
        $search = request_strvar('search');
        $from = evo()->getFullTableName('web_users') . ' wu'
            . ' INNER JOIN ' . evo()->getFullTableName('web_user_attributes') . ' wua ON wua.internalKey=wu.id';
        $where = '';
        if ($search !== '') {
            $keyword = db()->escape($search);
            $where = "(wu.username LIKE '{$keyword}%') OR (wua.fullname LIKE '%{$keyword}%') OR (wua.email LIKE '{$keyword}%')";
        }

        // 前に見たページは画面状態に覚える。listmode=1 は全件を 1 ページに出す
        $total = (int) db()->getValue(db()->select('COUNT(wu.id)', $from, $where));
        $page = request_strvar('op') === 'reset'
            ? 1
            : (get_intvar('page') ?: (int) array_get($_PAGE, 'vs.page', 1));
        $perPage = $listmode == '1' ? 0 : ((int) evo()->config('number_of_results') ?: 20);
        $pager = new DataTablePager($total, $perPage, $page);
        $_PAGE['vs']['page'] = $pager->page();
        $params = ['a' => 99];
        if ($search !== '') {
            $params['search'] = $search;
        }
        $links = new DataTableLinks('index.php', $params, $pager->page());

        $ds = db()->select(
            'wu.id,wu.username,wua.fullname,wua.email,wua.gender,(wua.blocked=1 OR wua.blockeduntil>' . (int) time() . ') AS blocked',
            $from,
            $where,
            'wu.username',
            $pager->limit() ? sprintf('%d,%d', $pager->offset(), $pager->limit()) : ''
        );
        $rows = db()->getRows($ds) ?: [];

        $columns = [
            [
                'key' => 'id',
                'label' => $_lang['icon'],
                'class' => 'is-shrink is-center',
                'html' => true,
                'format' => function ($value) use ($_style, $_lang) {
                    return sprintf(
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" title="%s"><img src="%s" /></a>',
                        (int) $value,
                        hsc($_lang['click_to_context']),
                        hsc($_style['icons_user'])
                    );
                },
            ],
            [
                'key' => 'username',
                'label' => $_lang['username'],
                'html' => true,
                'format' => function ($value, array $row) use ($_lang) {
                    return sprintf(
                        '<a href="index.php?a=88&amp;id=%d" title="%s">%s</a>',
                        (int) $row['id'],
                        hsc($_lang['click_to_edit_title']),
                        hsc($value)
                    );
                },
            ],
            ['key' => 'fullname', 'label' => $_lang['user_full_name']],
            ['key' => 'email', 'label' => $_lang['email']],
            [
                'key' => 'gender',
                'label' => $_lang['user_gender'],
                'class' => 'is-shrink is-center',
                'format' => function ($value) use ($_lang) {
                    if ((int) $value === 1) {
                        return $_lang['user_male'];
                    }
                    return (int) $value === 2 ? $_lang['user_female'] : '-';
                },
            ],
            [
                'key' => 'blocked',
                'label' => $_lang['user_block'],
                'class' => 'is-shrink is-center',
                'format' => function ($value) use ($_lang) {
                    return $value ? $_lang['yes'] : '-';
                },
            ],
        ];
        $table = new DataTable($columns, [
            'id' => 'web-users',
            'tableClass' => 'grid',
            'rowClasses' => ['gridItem', 'gridAltItem'],
            'emptyMessage' => $_lang['no_records_found'],
        ]);
        $nav = $pager->render($links, [
            'label' => lang('pagination_label'),
            'first' => lang('pagination_table_first'),
            'last' => lang('pagination_table_last'),
        ]);
        echo $nav . $table->render($rows, $links) . $nav;
            ?>
    </div>
</form>
