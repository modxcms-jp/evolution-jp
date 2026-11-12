<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
unset($_SESSION['itemname']); // clear this, because it's only set for logging purposes
?>

    <h1><?= lang('search_criteria') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li
                id="Button5"
                class="mutate"
            ><a
                    href="#"
                    onclick="EvoShell.go('index.php?a=2', {discard: true});"
                ><?= lang('cancel') ?></a></li>
        </ul>
    </div>
    <div class="section">
        <div class="sectionBody">
            <form action="index.php" method="get">
                <input type="hidden" name="a" value="71"/>
                <table width="100%" border="0">
                    <tr>
                        <td width="120"><?= lang('search_criteria_id') ?></td>
                        <td width="20">&nbsp;</td>
                        <td width="120">
                            <input name="searchid" type="text" value="<?= hsc(get_strvar('searchid')) ?>"/>
                        </td>
                        <td><?= lang('search_criteria_id_msg') ?></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_title') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="pagetitle" type="text" value="<?= hsc(get_strvar('pagetitle')) ?>"/>
                        </td>
                        <td><?= lang('search_criteria_title_msg') ?></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_longtitle') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="longtitle" type="text" value="<?= hsc(get_strvar('longtitle')) ?>"/>
                        </td>
                        <td><?= lang('search_criteria_longtitle_msg') ?></td>
                    </tr>
                    <tr>
                        <td>Alias</td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="alias" type="text" value="<?= hsc(get_strvar('alias')) ?>"/>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>URL</td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="url" type="text" size="50" value="<?= hsc(get_strvar('url')) ?>"/>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_content') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="content" type="text" value="<?= hsc(get_strvar('content')) ?>"/>
                        </td>
                        <td><?= lang('search_criteria_content_msg') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <ul class="actionButtons">
                                <li><a
                                        class="default"
                                        href="#"
                                        onclick="document.getElementById('submitok').click();"
                                    ><?= lang('search') ?></a
                                    ></li>
                                <li><a
                                        href="index.php?a=2"
                                    ><?= lang('cancel') ?></a
                                    ></li>
                            </ul>
                        </td>
                    </tr>
                </table>

                <input type="submit" id="submitok" value="Search" name="submitok" style="display:none"/>
            </form>
        </div>
    </div>

<?php
if (getv('submitok')) {

    if (get_strvar('url')) {
        $searchid = evo()->getIdFromUrl(get_strvar('url'));
        if (!$searchid) {
            $searchid = 'x';
        }
    } else {
        $searchid = get_strvar('searchid');
    }

    $where = [];
    if ($searchid) {
        $where[] = "id='" . db()->escape($searchid) . "' ";
    }
    $searchtitle = trim(get_strvar('pagetitle'));
    if ($searchtitle != '') {
        $where[] = "pagetitle LIKE '%" . db()->escape($searchtitle) . "%' ";
    }
    $searchlongtitle = trim(get_strvar('longtitle'));
    if ($searchlongtitle != '') {
        $where[] = "longtitle LIKE '%" . db()->escape($searchlongtitle) . "%' ";
    }
    $search_alias = trim(get_strvar('alias'));
    if ($search_alias != '') {
        $where[] = "alias LIKE '%" . db()->escape($search_alias) . "%' ";
    }
    $searchcontent = get_strvar('content');
    if ($searchcontent != '') {
        $where[] = "content LIKE '%" . db()->escape($searchcontent) . "%' ";
    }

    // ドキュメントグループによる絞り込み（判定は ManagerAPI::documentAccessCondition()。この問い合わせは表に別名が無い）。
    // 検索は開けない文書を印なしのリンクで出すしかないので、表示だけの設定の tree_show_protected は見ず、常に絞る
    $accessCond = manager()->documentAccessCondition('');
    if ($accessCond !== '') {
        $where[] = '(' . $accessCond . ')';
    }

    include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

    // 並べ替えのキー => ORDER BY の式（リクエストの値を直接 SQL に入れない）
    $sortColumns = ['id' => 'id', 'pagetitle' => 'pagetitle'];
    $linkParams = ['a' => 71, 'submitok' => get_strvar('submitok')];
    foreach (['searchid', 'pagetitle', 'longtitle', 'alias', 'url', 'content'] as $name) {
        if (get_strvar($name) !== '') {
            $linkParams[$name] = get_strvar($name);
        }
    }
    $links = DataTableLinks::fromRequest('index.php', $linkParams, array_keys($sortColumns));
    // 並べ替えの指定が無いときの既定はタイトル昇順（見出しの aria-sort は付かない）
    $orderby = 'pagetitle ASC, id ASC';
    if ($links->orderby() !== '') {
        $dir = $links->orderdir() === 'desc' ? 'DESC' : 'ASC';
        $orderby = sprintf('%s %s, id %s', $sortColumns[$links->orderby()], $dir, $dir);
    }

    // 件数を数えてから、そのページの行だけを取る
    $total = (int) db()->getValue(
        db()->select('COUNT(id)', '[+prefix+]site_content', implode(' and ', $where))
    );
    $pager = new DataTablePager($total, (int) config('number_of_results') ?: 20, $links->page());
    $rs = db()->select(
        'id, contenttype, pagetitle, description, deleted, published, isfolder, type',
        '[+prefix+]site_content',
        implode(' and ', $where),
        $orderby,
        $pager->limit() > 0 ? sprintf('%d, %d', $pager->offset(), $pager->limit()) : ''
    );
    if (manager()->can('document.edit')) {
        $action = '27';
        $itemicon = style('icons_edit_document');
    } else {
        $action = '3';
        $itemicon = style('icons_resource_overview');
    }
    ?>
    <div class="section">
        <div class="sectionHeader"><?= lang('search_results') ?></div>
        <div class="sectionBody">
            <?php
            if ($total < 1) {
                echo lang('search_empty');
            } else {
                printf('<p>' . lang('search_results_returned_msg') . '</p>', $total);

                // icons by content type
                $icons = [
                    'application/rss+xml' => style('tree_page_rss'),
                    'application/pdf' => style('tree_page_pdf'),
                    'application/vnd.ms-word' => style('tree_page_word'),
                    'application/vnd.ms-excel' => style('tree_page_excel'),
                    'text/css' => style('tree_page_css'),
                    'text/html' => style('tree_page_html'),
                    'text/plain' => style('tree_page'),
                    'text/xml' => style('tree_page_xml'),
                    'text/javascript' => style('tree_page_js'),
                    'image/gif' => style('tree_page_gif'),
                    'image/jpg' => style('tree_page_jpg'),
                    'image/png' => style('tree_page_png')
                ];

                $rows = [];
                while ($row = db()->getRow($rs)) {
                    // figure out the icon for the document...
                    if ($row['type'] === 'reference') {
                        $row['icon'] = style('tree_linkgo');
                    } elseif ($row['isfolder'] == 0) {
                        $row['icon'] = $icons[$row['contenttype']] ?? style('tree_page_html');
                    } else {
                        $row['icon'] = style('tree_folder');
                    }
                    $rows[] = $row;
                }

                // 公開していない・削除済みのリソースは、タイトルと説明の文字の色で示す（削除済みを優先）
                $wrapNode = function ($html, array $row) {
                    $class = '';
                    if ($row['deleted'] == 1) {
                        $class = 'deletedNode';
                    } elseif ($row['published'] == 0) {
                        $class = 'unpublishedNode';
                    }
                    return $class === '' ? $html : sprintf('<span class="%s">%s</span>', $class, $html);
                };
                $truncate = function ($text) use ($modx_manager_charset) {
                    $text = (string) $text;
                    return mb_strlen($text, $modx_manager_charset) > 70
                        ? hsc(mb_substr($text, 0, 70, $modx_manager_charset)) . '...'
                        : hsc($text);
                };

                $table = new DataTable(
                    [
                        [
                            'key' => 'id',
                            'class' => 'is-center is-shrink',
                            'html' => true,
                            'format' => function ($value, array $row) use ($action, $itemicon) {
                                return sprintf(
                                    '<a href="index.php?a=%s&amp;id=%d" title="%s"><img src="%s" alt="" /></a>',
                                    $action,
                                    (int) $row['id'],
                                    hsc(lang('search_view_docdata')),
                                    hsc($itemicon)
                                );
                            },
                        ],
                        [
                            'key' => 'id',
                            'label' => lang('search_results_returned_id'),
                            'sort' => 'id',
                            'html' => true,
                            'format' => function ($value, array $row) {
                                $folder = '';
                                if ($row['isfolder'] == 1) {
                                    $folder = sprintf(
                                        '<a href="index.php?a=120&amp;id=%d" title="%s"><img src="%s" alt="" /></a>',
                                        (int) $row['id'],
                                        hsc(lang('search_view_docdata')),
                                        hsc(style('tree_folder'))
                                    );
                                }
                                return $folder . (int) $row['id'];
                            },
                        ],
                        [
                            'key' => 'pagetitle',
                            'label' => lang('search_results_returned_title'),
                            'sort' => 'pagetitle',
                            'html' => true,
                            'format' => function ($value, array $row) use ($truncate, $wrapNode) {
                                return $wrapNode(
                                    sprintf('<img src="%s" alt="" /> %s', hsc($row['icon']), $truncate($value)),
                                    $row
                                );
                            },
                        ],
                        [
                            'key' => 'description',
                            'label' => lang('search_results_returned_desc'),
                            'html' => true,
                            'format' => function ($value, array $row) use ($truncate, $wrapNode) {
                                return $wrapNode($truncate($value), $row);
                            },
                        ],
                    ],
                    ['id' => 'search-results', 'rowClasses' => ['gridItem', 'gridAltItem']]
                );
                $nav = $pager->render($links, [
                    'label' => lang('pagination_label'),
                    'first' => lang('pagination_table_first'),
                    'last' => lang('pagination_table_last'),
                ]);
                echo $nav . $table->render($rows, $links) . $nav;
            }
            ?>
        </div>
    </div>
    <?php
}
