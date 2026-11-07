<?php

/**
 * @var array $_lang
 * @var array $_style
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

global $_PAGE;
if (!(evo()->hasPermission('new_module') || evo()->hasPermission('edit_module') || evo()->hasPermission('exec_module'))) {
    alert()->setError(3);
    alert()->dumpError();
}

manager()->initPageViewState();

// get and save search string
$query = '';
$sqlQuery = '';
if (anyv('op') === 'reset') {
    $_PAGE['vs']['search'] = '';
} else {
    // 空の検索語は「検索語を消す」操作なので、送られたかどうかで保存済みの値と使い分ける
    $query = request_textvar('search') ?? array_get($_PAGE, 'vs.search', '');
    if ($query !== '') {
        $sqlQuery = db()->escape($query);
    }
    $_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = request_choicevar('listmode', ['0', '1'], array_get($_PAGE, 'vs.lm'));
$_PAGE['vs']['lm'] = $listmode;


// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
$cm->addItem(
    $_lang["run_module"],
    "js:menuAction(1)",
    $_style['icons_save'],
    (!evo()->hasPermission('exec_module') ? 1 : 0)
);
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

    function showContentMenu(id, e) {
        selectedItem = id;
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
                dontShowWorker = true; // prevent worker from being displayed
                window.location.href = 'index.php?a=112&id=' + id;
                break;
            case 2: // edit
                window.location.href = 'index.php?a=108&id=' + id;
                break;
            case 3: // duplicate
                if (confirm("<?= $_lang['confirm_duplicate_record'] ?>")) {
                    EvoCsrf.post('index.php?a=111&id=' + id);
                }
                break;
            case 4: // delete
                if (confirm("<?= $_lang['confirm_delete_module'] ?>")) {
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

    // 表は画面の差し替えで消える。リスナーを登録する <script> を表の後ろに置くと入れ物の最後の子が
    // <script> になり余白の規則が当たらないため、Evo.ready で表が出来てから登録する
    Evo.ready(function () {
        const table = document.querySelector('[data-table="modules"]');
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

<h1><?= $_lang['module_management'] ?></h1>

<div class="sectionBody">
    <!-- load modules -->
    <div id="actions">
        <ul class="actionButtons">
            <?php if ((evo()->hasPermission('new_module'))) {
                echo '<li id="newModule" class="mutate"><a href="index.php?a=107">' . $_lang["new_module"] . '</a></li>';
            } ?>
            <li id="Button5" class="mutate"><a href="#"
                    onclick="documentDirty=false;document.location.href='index.php?a=2';"><?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <p><?= $_lang['module_management_msg'] ?></p>

    <?php
        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
        $from = '[+prefix+]site_modules';
        if (!empty($sqlQuery)) {
            $where = "(name LIKE '%{$sqlQuery}%') OR (description LIKE '%{$sqlQuery}%')";
        } else {
            $where = '';
        }

        // 前に見たページは画面状態に覚える。listmode=1 は全件を 1 ページに出す
        $total = (int) db()->getValue(db()->select('COUNT(id)', $from, $where));
        $page = anyv('op') === 'reset'
            ? 1
            : (get_intvar('page') ?: (int) array_get($_PAGE, 'vs.page', 1));
        $perPage = $listmode == '1' ? 0 : ((int) evo()->config('number_of_results') ?: 20);
        $pager = new DataTablePager($total, $perPage, $page);
        $_PAGE['vs']['page'] = $pager->page();
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
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" title="%s"><img src="%s" width="32" height="32" alt=""></a>',
                        (int) $row['id'],
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
