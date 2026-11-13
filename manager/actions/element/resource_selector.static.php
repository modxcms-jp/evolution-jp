<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_module')) {
    alert()->setError(3);
    alert()->dumpError();
}

$mxla = globalv('modx_lang_attribute') ?: 'en';

/**
 * Resource Selector
 * Created by Raymond Irving May, 2005
 *
 * Selects a resource and returns the id values to the window.opener["callback"]() function as an array.
 * The name of the callback function is passed via the url as &callback
 */

// get name of callback function
// 文字列かつ JS の識別子の形のときだけ受け付ける
$callback = request_strvar('callback');
if (!preg_match('/^[A-Za-z_$][\w$]*$/D', $callback)) {
    $callback = '';
}

// get resource type
$type = request_choicevar('type', ['snippet', 'template', 'tv', 'chunk', 'plugin', 'document'], 'document');

// get selection mode: single (default) / multiple
$selectMode = request_choicevar('select', ['single', 'multiple'], 'single');

// get search string
$query = request_strvar('search');

// select SQL（件数と本体で FROM 以降を共有する。ORDER BY は本体だけ）
$title = '';
$likeWhere = function (string $nameCol, string $descCol) use ($query) {
    if (!$query) {
        return '';
    }
    $keyword = db()->escape($query);
    return "({$nameCol} LIKE '%{$keyword}%') OR ({$descCol} LIKE '%{$keyword}%')";
};
switch ($type) {
    case "snippet":
        $title = lang("snippet");
        $cols = 'id,name,description';
        $from = evo()->getFullTableName('site_snippets');
        $where = $likeWhere('name', 'description');
        $orderby = 'name';
        break;

    case "template":
        $title = lang("template");
        $cols = "id,templatename as 'name',description";
        $from = evo()->getFullTableName('site_templates');
        $where = $likeWhere('templatename', 'description');
        $orderby = 'templatename';
        break;

    case ("tv"):
        $title = lang("tv");
        $cols = 'id,name,description';
        $from = evo()->getFullTableName('site_tmplvars');
        $where = $likeWhere('name', 'description');
        $orderby = 'name';
        break;

    case ("chunk"):
        $title = lang("chunk");
        $cols = 'id,name,description';
        $from = evo()->getFullTableName('site_htmlsnippets');
        $where = $likeWhere('name', 'description');
        $orderby = 'name';
        break;

    case ("plugin"):
        $title = lang("plugin");
        $cols = 'id,name,description';
        $from = evo()->getFullTableName('site_plugins');
        $where = $likeWhere('name', 'description');
        $orderby = 'name';
        break;

    case ("document"):
        $title = lang("resource");
        $cols = "id,pagetitle as 'name',longtitle as 'description'";
        $from = evo()->getFullTableName('site_content');
        $where = $likeWhere('pagetitle', 'longtitle');
        // 文書グループによる絞り込み（判定は ManagerAPI::documentAccessCondition()）。検索条件の OR を包んでから足す
        $accessCond = manager()->documentAccessCondition('');
        if ($accessCond !== '') {
            $where = $where === '' ? $accessCond : "({$where}) AND {$accessCond}";
        }
        $orderby = 'pagetitle';
        break;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html <?= (globalv('modx_textdir') === 'rtl' ? 'dir="rtl" lang="' : 'lang="') . hsc($mxla) . '" xml:lang="' . hsc($mxla) . '"' ?>>

<head>
    <title><?= $title . ' ' . lang('element_selector_title') ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= hsc(evo()->config('modx_charset')) ?>"/>
    <link rel="stylesheet" type="text/css"
          href="<?= hsc(manager_asset('media/style/' . evo()->config('manager_theme') . '/style.css')) ?>"/>
</head>

<body ondragstart="return false">

<script language="JavaScript" type="text/javascript">
    function saveSelection() {
        var a = window.opener.chkBoxArray || {};
        var ctrl = document.selector.elements['id[]'];
        var ids = [];
        var list = !ctrl ? [] : (ctrl.length === undefined ? [ctrl] : Array.prototype.slice.call(ctrl));
        // 今のページのチェックの状態を、ページをまたいで覚えている選択に反映する
        list.forEach(function (c) {
            a[c.value] = c.checked;
        });
        // 全ページで選ばれているものを渡す（チェックを外したものは除く）
        Object.keys(a).forEach(function (id) {
            if (a[id]) ids.push(id);
        });
        if (!ids.length && !list.length) return; // 0 件のときは何もしない
        var callback = window.opener[<?= js_literal($callback) ?>];
        if (callback) callback(<?= js_literal($type) ?>, ids);
        window.close();
    }

    function searchResource() {
        document.selector.op.value = "srch";
        document.selector.submit();
    }

    function resetSearch() {
        document.selector.search.value = "";
        searchResource()
    }

    function changeListMode() {
        var m = parseInt(document.selector.listmode.value) ? 1 : 0;
        if (m) document.selector.listmode.value = 0;
        else document.selector.listmode.value = 1;
        document.selector.submit();
    }

    // restore checkbox function
    function restoreChkBoxes() {
        var i, c, chk;
        var a = window.opener.chkBoxArray;
        var f = document.selector;
        chk = f.elements['id[]'];
        if (!chk) return; // 0 件のときは何もしない
        if (!chk.length) chk.checked = (a[chk.value]) ? true : false;
        else {
            for (i = 0; i < chk.length; i++) {
                c = chk[i];
                c.checked = (a[c.value]) ? true : false;
            }
        }
    }

    // set checkbox value
    function setCheckbox(chk) {
        var a = window.opener.chkBoxArray;
        // 単一選択（radio）では、ほかのページで選んだものを残さない
        if (chk.type === 'radio') {
            Object.keys(a).forEach(function (id) {
                a[id] = false;
            });
        }
        a[chk.value] = chk.checked;
    }

    // 表の入力の変更を受ける（インラインの onclick を出さない）
    document.addEventListener('change', function (e) {
        if (e.target.name === 'id[]') {
            setCheckbox(e.target);
        }
    });

    // restore checkboxes
    setTimeout("restoreChkBoxes();", 100);
</script>
<form name="selector" method="get">
    <input type="hidden" name="id" value="<?= request_intvar('id') ?>"/>
    <input type="hidden" name="a" value="<?= request_intvar('a') ?>"/>
    <input type="hidden" name="listmode" value="<?= hsc(request_strvar('listmode')) ?>"/>
    <input type="hidden" name="op" value=""/>
    <input type="hidden" name="type" value="<?= hsc($type) ?>"/>
    <input type="hidden" name="select" value="<?= hsc($selectMode) ?>"/>
    <input type="hidden" name="callback" value="<?= hsc($callback) ?>"/>
    <div class="section" style="margin:1em;">
        <div class="sectionHeader"
            style="margin:0px"><?= $title . " - " . lang('element_selector_title') ?></div>
        <div class="sectionBody" style="margin-right:0px;margin-left:0px;">
            <p><?= lang('element_selector_msg') ?></p>
            <!-- resources -->
            <table width="100%" border="0" cellspacing="1" cellpadding="2">
                <tr>
                    <td>
                        <div class="searchbar">
                            <table border="0" width="100%">
                                <tr>
                                    <td nowrap="nowrap">
                                        <table border="0">
                                            <tr>
                                                <td><?= lang("search") ?></td>
                                                <td><input class="searchtext" name="search" type="text" size="15"
                                                           value="<?= hsc($query) ?>"/></td>
                                                <td class="actionButtons"><a href="#"
                                                                             title="<?= lang("search") ?>"
                                                                             onclick="searchResource();return false;"><?= lang("go") ?></a>
                                                </td>
                                                <td class="actionButtons"><a href="#"
                                                                             title="<?= lang("reset") ?>"
                                                                             onclick="resetSearch();return false;"><img
                                                            src="<?= style('icons_refresh') ?>" /></a></td>
                                                <td class="actionButtons"><a href="#"
                                                                             title="<?= lang("list_mode") ?>"
                                                                             onclick="changeListMode();return false;"><img
                                                            src="<?= style('icons_table') ?>" /></a></td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="230" class="actionButtons">
                                        <a href="#" style="float:right;margin-left:2px;" onclick="window.close()"><?= lang('cancel') ?>
                                        </a>
                                        <a href="#" style="float:right;margin-left:2px;" onclick="saveSelection()"><?= lang('insert') ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td valign="top" align="left">
                        <?php
                        include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
                        $total = (int) db()->getValue(db()->select('COUNT(*)', $from, $where));
                        $listmode = request_intvar('listmode');
                        $params = ['a' => 84, 'type' => $type, 'select' => $selectMode, 'callback' => $callback];
                        if ($query !== '') {
                            $params['search'] = $query;
                        }
                        if ($listmode) {
                            $params['listmode'] = $listmode;
                        }
                        $perPage = $listmode === 1 ? 0 : ((int) evo()->config('number_of_results', 20) ?: 20);
                        $links = new DataTableLinks('index.php', $params, get_intvar('page'));
                        $pager = new DataTablePager($total, $perPage, $links->page());
                        $ds = db()->select(
                            $cols,
                            $from,
                            $where,
                            $orderby,
                            $pager->limit() ? sprintf('%d,%d', $pager->offset(), $pager->limit()) : ''
                        );
                        if (!$ds) {
                            echo "An error occured while loading records.";
                            exit;
                        }
                        $rows = db()->getRows($ds) ?: [];
                        $inputType = $selectMode === 'multiple' ? 'checkbox' : 'radio';
                        $columns = [
                            [
                                'key' => 'name',
                                'label' => lang('name'),
                                'html' => true,
                                'format' => function ($value, array $row) use ($inputType) {
                                    return sprintf(
                                        '<label><input type="%s" name="id[]" value="%d"> %s</label>',
                                        $inputType,
                                        (int) $row['id'],
                                        hsc($value)
                                    );
                                },
                            ],
                            // 要素の説明は無害化して b・strong だけ戻す。文書のロングタイトルは既定のエスケープ
                            $type === 'document'
                                ? ['key' => 'description', 'label' => lang('description')]
                                : [
                                    'key' => 'description',
                                    'label' => lang('description'),
                                    'html' => true,
                                    'format' => function ($value) {
                                        return element_description_html((string) $value);
                                    },
                                ],
                        ];
                        $table = new DataTable($columns, [
                            'id' => 'selector',
                            'tableClass' => 'grid',
                            'rowClasses' => ['gridItem', 'gridAltItem'],
                            'emptyMessage' => lang('no_records_found'),
                        ]);
                        $nav = $pager->render($links, [
                            'label' => lang('pagination_label'),
                            'first' => lang('pagination_table_first'),
                            'last' => lang('pagination_table_last'),
                        ]);
                        echo $nav . $table->render($rows, $links) . $nav;
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</form>
</body>

</html>
