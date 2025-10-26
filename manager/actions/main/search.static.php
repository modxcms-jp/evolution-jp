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
                    onclick="documentDirty=false;document.location.href='index.php?a=2';"
                ><img
                        alt="icons_cancel"
                        src="<?= style('icons_cancel') ?>"
                    /> <?= lang('cancel') ?></a></li>
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
                            <input name="searchid" type="text" value="<?= getv('searchid') ?>"/>
                        </td>
                        <td><?= lang('search_criteria_id_msg') ?></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_title') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="pagetitle" type="text" value="<?= getv('pagetitle') ?>"/>
                        </td>
                        <td><?= lang('search_criteria_title_msg') ?></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_longtitle') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="longtitle" type="text" value="<?= getv('longtitle') ?>"/>
                        </td>
                        <td><?= lang('search_criteria_longtitle_msg') ?></td>
                    </tr>
                    <tr>
                        <td>Alias</td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="alias" type="text" value="<?= getv('alias') ?>"/>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>URL</td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="url" type="text" size="50" value="<?= getv('url') ?>"/>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><?= lang('search_criteria_content') ?></td>
                        <td>&nbsp;</td>
                        <td>
                            <input name="content" type="text" value="<?= getv('content') ?>"/>
                        </td>
                        <td><?= lang('search_criteria_content_msg') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <ul class="actionButtons">
                                <li><a
                                        class="default"
                                        href="#"
                                        onclick="jQuery('#submitok').click();"
                                    ><img
                                            src="<?= style('icons_save') ?>"
                                        /> <?= lang('search') ?></a
                                    ></li>
                                <li><a
                                        href="index.php?a=2"
                                    ><img
                                            src="<?= style('icons_cancel') ?>"
                                        /> <?= lang('cancel') ?></a
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

    if (getv('url')) {
        $searchid = evo()->getIdFromUrl(getv('url'));
        if (!$searchid) {
            $searchid = 'x';
        }
    } else {
        $searchid = getv('searchid', 0);
    }

    $where = [];
    if ($searchid) {
        $where[] = "id='" . db()->escape($searchid) . "' ";
    }
    $searchtitle = trim((string) getv('pagetitle'));
    if ($searchtitle != '') {
        $where[] = "pagetitle LIKE '%" . db()->escape($searchtitle) . "%' ";
    }
    $searchlongtitle = trim((string) getv('longtitle'));
    if ($searchlongtitle != '') {
        $where[] = "longtitle LIKE '%" . db()->escape($searchlongtitle) . "%' ";
    }
    $search_alias = trim((string) getv('alias'));
    if ($search_alias != '') {
        $where[] = "alias LIKE '%" . db()->escape($search_alias) . "%' ";
    }
    $searchcontent = getv('content', '');
    if ($searchcontent != '') {
        $where[] = "content LIKE '%" . db()->escape($searchcontent) . "%' ";
    }

    $rs = db()->select(
        'id, contenttype, pagetitle, description, deleted, published, isfolder, type'
        , '[+prefix+]site_content'
        , implode(' and ', $where)
        , 'id'
    );
    $limit = db()->count($rs);
    if (evo()->hasPermission('edit_document')) {
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
            if ($limit < 1) {
                echo lang('search_empty');
            } else {
                printf('<p>' . lang('search_results_returned_msg') . '</p>', $limit);
                ?>
                <script type="text/javascript" src="media/script/tablesort.js"></script>
                <table border="0" cellpadding="2" cellspacing="0" class="sortabletable sortable-onload-2 rowstyle-even"
                       id="table-1" width="90%">
                    <thead>
                    <tr style="background-color:#cccccc">
                        <th width="20"></th>
                        <th class="sortable"><b><?= lang('search_results_returned_id') ?></b></th>
                        <th class="sortable"><b><?= lang('search_results_returned_title') ?></b></th>
                        <th class="sortable"><b><?= lang('search_results_returned_desc') ?></b></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // icons by content type
                    $icons = array(
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
                    );

                    while ($row = db()->getRow($rs)) {
                        // figure out the icon for the document...
                        $icon = '';
                        if ($row['type'] === 'reference') {
                            $icon .= style('tree_linkgo');
                        } elseif ($row['isfolder'] == 0) {
                            $icon .= isset($icons[$row['contenttype']]) ? $icons[$row['contenttype']] : style('tree_page_html');
                        } else {
                            $icon .= style('tree_folder');
                        }

                        $tdClass = '';
                        if ($row['published'] == 0) {
                            $tdClass .= ' class="unpublishedNode"';
                        }
                        if ($row['deleted'] == 1) {
                            $tdClass .= ' class="deletedNode"';
                        }
                        ?>
                        <tr>
                            <td align="center">
                                <a href="index.php?a=<?= $action ?>&id=<?= $row['id'] ?>"
                                   title="<?= lang('search_view_docdata') ?>">
                                    <img src="<?= $itemicon ?>"/></a>
                            </td>
                            <td>
                                <?php
                                if ($row['isfolder'] == 1) {
                                    ?>
                                    <a href="index.php?a=120&id=<?= $row['id'] ?>"
                                       title="<?= lang('search_view_docdata') ?>"><img
                                            src="<?= style('tree_folder') ?>"/></a>
                                    <?php
                                }
                                echo $row['id'];
                                ?>
                            </td>
                            <td<?= $tdClass ?>
                            <?php
                            $pagetitle = mb_strlen($row['pagetitle'],
                                $modx_manager_charset) > 70 ? evo()->hsc(mb_substr($row['pagetitle'], 0, 70,
                                    $modx_manager_charset)) . "..." : evo()->hsc($row['pagetitle']);
                            ?>
                            <img src="<?= $icon ?>"/> <?= $pagetitle ?></td>
                            <td<?= $tdClass ?>><?= mb_strlen($row['description'],
                                    $modx_manager_charset) > 70 ? mb_substr($row['description'], 0, 70,
                                        $modx_manager_charset) . "..." : $row['description']; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
}
