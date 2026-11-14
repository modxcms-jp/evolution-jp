<?php

/**
 * @var array $_lang
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
$cm->addItem(
    lang("run_module"),
    "js:menuAction(1)",
    style('icons_save'),
    (!evo()->hasPermission('exec_module') ? 1 : 0)
);
$cm->addSeparator();
$cm->addItem(
    lang("edit"),
    "js:menuAction(2)",
    style('icons_edit_document'),
    (!evo()->hasPermission('edit_module') ? 1 : 0)
);
$cm->addItem(
    lang("duplicate"),
    "js:menuAction(3)",
    style('icons_resource_duplicate'),
    (!evo()->hasPermission('new_module') ? 1 : 0)
);
$cm->addItem(
    lang("delete"),
    "js:menuAction(4)",
    style('icons_delete'),
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
                showContentMenu(Number(el.dataset.rowMenu), e);
                e.preventDefault();
            }
        });
    });
</script>

<h1><?= lang('module_management') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <?php if ((evo()->hasPermission('new_module'))) {
            echo '<li id="newModule" class="mutate"><a href="index.php?a=107">' . lang("new_module") . '</a></li>';
        } ?>
        <li id="Button5" class="mutate"><a href="#"
                onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?></a></li>
    </ul>
</div>

<div class="section"><div class="sectionBody">
    <!-- load modules -->
    <p><?= lang('module_management_msg') ?></p>

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

        $yesOrDash = function ($value) {
            return $value ? lang('yes') : '-';
        };
        $columns = [
            [
                'key' => 'icon',
                'label' => lang('icon'),
                'class' => 'is-shrink is-center',
                'html' => true,
                'format' => function ($value, array $row) {
                    return sprintf(
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" title="%s"><img src="%s" width="32" height="32" alt=""></a>',
                        (int) $row['id'],
                        hsc(lang('click_to_context')),
                        hsc($value !== '' && $value !== null ? $value : style('icons_modules'))
                    );
                },
            ],
            [
                'key' => 'name',
                'label' => lang('name'),
                'html' => true,
                'format' => function ($value, array $row) {
                    return sprintf(
                        '<a href="index.php?a=108&amp;id=%d" title="%s">%s</a>',
                        (int) $row['id'],
                        hsc(lang('module_edit_click_title')),
                        hsc($value)
                    );
                },
            ],
            [
                'key' => 'description',
                'label' => lang('description'),
                'html' => true,
                'format' => function ($value) {
                    return element_description_html((string) $value);
                },
            ],
            ['key' => 'locked', 'label' => lang('locked'), 'class' => 'is-shrink is-center', 'format' => $yesOrDash],
            ['key' => 'disabled', 'label' => lang('disabled'), 'class' => 'is-shrink is-center', 'format' => $yesOrDash],
        ];
        $table = new DataTable($columns, [
            'id' => 'modules',
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
