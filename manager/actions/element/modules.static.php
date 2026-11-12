<?php

/**
 * @var array $_lang
 * @var array $_style
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!(evo()->hasPermission('new_module') || evo()->hasPermission('edit_module') || evo()->hasPermission('exec_module'))) {
    alert()->setError(3);
    alert()->dumpError();
}

// 一覧の検索語・ページ番号・一覧モードは URL（リクエスト）だけで決め、画面状態には覚えない
$query = get_strvar('search');
$listmode = get_choicevar('listmode', ['0', '1'], '0');


// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
// 実行のメニューは、モジュールごとに実行してよいか（ManagerAPI::canExecuteModule()）が違うので、
// 実行できる項目と実行できない（無効の）項目を並べ、showContentMenu() が行ごとに切り替える
$cm->addItem($_lang["run_module"], "js:menuAction(1)", $_style['icons_save'], 0);
$cm->addItem($_lang["run_module"], "js:menuAction(1)", $_style['icons_save'], 1);
$cm->addSeparator();
$cm->addItem(
    $_lang["edit"],
    "js:menuAction(2)",
    $_style['icons_edit_document'],
    (!evo()->hasPermission('edit_module') ? 1 : 0)
);
$cm->addItem(
    $_lang["duplicate"],
    "js:menuAction(3)",
    $_style['icons_resource_duplicate'],
    (!evo()->hasPermission('new_module') ? 1 : 0)
);
$cm->addItem(
    $_lang["delete"],
    "js:menuAction(4)",
    $_style['icons_delete'],
    (!evo()->hasPermission('delete_module') ? 1 : 0)
);
echo $cm->render();

?>
<script type="text/javascript">
    var selectedItem;
    var contextm = <?= $cm->getClientScriptObject() ?>;

    function showContentMenu(id, e, canExecute) {
        selectedItem = id;
        // 先頭の 2 項目が実行のメニュー（実行できる項目・できない項目）
        contextm.children[0].style.display = canExecute ? '' : 'none';
        contextm.children[1].style.display = canExecute ? 'none' : '';
        e = e || window.event;
        var scrollLeft = document.documentElement.scrollLeft || document.body.scrollLeft || 0;
        var scrollTop = document.documentElement.scrollTop || document.body.scrollTop || 0;
        var posX = (typeof e.pageX !== 'undefined' ? e.pageX : e.clientX + scrollLeft);
        var posY = (typeof e.pageY !== 'undefined' ? e.pageY : e.clientY + scrollTop);

        contextm.style.left = posX<?= $modx_textdir === 'rtl' ? '-190' : ''; ?> + "px"; //offset menu if RTL is selected
        contextm.style.top = posY + "px";
        contextm.style.visibility = "visible";

        if (e.stopPropagation) {
            e.stopPropagation();
        }
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.cancelBubble = true;
        e.returnValue = false;
        return false;
    }

    function menuAction(a) {
        var id = selectedItem;
        switch (a) {
            case 1: // run module
                EvoShell.go('index.php?a=112&id=' + id);
                break;
            case 2: // edit
                EvoShell.go('index.php?a=108&id=' + id);
                break;
            case 3: // duplicate
                if (confirm(<?= js_literal(stripcslashes(lang('confirm_duplicate_record'))) ?>)) {
                    EvoCsrf.post('index.php?a=111&id=' + id);
                }
                break;
            case 4: // delete
                if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_module'))) ?>)) {
                    EvoCsrf.post('index.php?a=110&id=' + id);
                }
                break;
        }
    }

    // Hide context menu when clicking anywhere on the document
    // シェルの遷移で積み重ならないよう 1 回だけ登録する（contextm は実行時に最新の var を読む）
    if (!window.evoContextMenuClose) {
        window.evoContextMenuClose = true;
        document.addEventListener('click', function () {
            contextm.style.visibility = "hidden";
        });
    }

    // 表は画面の差し替えで消える。<script> を表の後ろに置かず、Evo.ready で表ができてから登録する
    Evo.ready(function () {
        const table = document.querySelector('[data-table="modules"]');
        if (!table) {
            return;
        }
        table.addEventListener('click', function (e) {
            const el = e.target.closest('[data-row-menu]');
            if (el) {
                showContentMenu(Number(el.dataset.rowMenu), e, el.dataset.canExecute === '1');
                e.preventDefault();
            }
        });
    });
</script>

<h1><?= $_lang['module_management'] ?></h1>

<div class="sectionBody">
    <!-- load modules -->
    <div id="actions">
        <ul class="actionButtons">
            <?php if ((evo()->hasPermission('new_module'))) {
                echo '<li id="newModule" class="mutate"><a href="index.php?a=107">' . $_lang["new_module"] . '</a></li>';
            } ?>
            <li id="Button5" class="mutate"><a href="#"
                    onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <p><?= $_lang['module_management_msg'] ?></p>

    <?php
        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
        $from = '[+prefix+]site_modules';
        if ($query !== '') {
            $keyword = db()->escape($query);
            $where = "(name LIKE '%{$keyword}%') OR (description LIKE '%{$keyword}%')";
        } else {
            $where = '';
        }

        // listmode=1 は全件を 1 ページに出す
        $total = (int) db()->getValue(db()->select('COUNT(id)', $from, $where));
        $page = get_intvar('page') ?: 1;
        $perPage = $listmode == '1' ? 0 : ((int) evo()->config('number_of_results') ?: 20);
        $pager = new DataTablePager($total, $perPage, $page);
        $params = ['a' => 106];
        if ($query !== '') {
            $params['search'] = $query;
        }
        $links = new DataTableLinks('index.php', $params, $pager->page());

        $ds = db()->select(
            'id,name,description,locked,disabled,icon',
            $from,
            $where,
            'editedon DESC, name ASC',
            $pager->limit() ? sprintf('%d,%d', $pager->offset(), $pager->limit()) : ''
        );
        $rows = db()->getRows($ds) ?: [];

        $yesOrDash = function ($value) use ($_lang) {
            return $value ? $_lang['yes'] : '-';
        };
        $columns = [
            [
                'key' => 'icon',
                'label' => $_lang['icon'],
                'class' => 'is-shrink is-center',
                'html' => true,
                'format' => function ($value, array $row) use ($_style, $_lang) {
                    return sprintf(
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" data-can-execute="%d" title="%s"><img src="%s" width="32" height="32" alt=""></a>',
                        (int) $row['id'],
                        manager()->canExecuteModule((int) $row['id']) ? 1 : 0,
                        hsc($_lang['click_to_context']),
                        hsc($value !== '' && $value !== null ? $value : $_style['icons_modules'])
                    );
                },
            ],
            [
                'key' => 'name',
                'label' => $_lang['name'],
                'html' => true,
                'format' => function ($value, array $row) use ($_lang) {
                    return sprintf(
                        '<a href="index.php?a=108&amp;id=%d" title="%s">%s</a>',
                        (int) $row['id'],
                        hsc($_lang['module_edit_click_title']),
                        hsc($value)
                    );
                },
            ],
            [
                'key' => 'description',
                'label' => $_lang['description'],
                'html' => true,
                'format' => function ($value) {
                    return element_description_html((string) $value);
                },
            ],
            ['key' => 'locked', 'label' => $_lang['locked'], 'class' => 'is-shrink is-center', 'format' => $yesOrDash],
            ['key' => 'disabled', 'label' => $_lang['disabled'], 'class' => 'is-shrink is-center', 'format' => $yesOrDash],
        ];
        $table = new DataTable($columns, [
            'id' => 'modules',
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
