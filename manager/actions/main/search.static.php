<?php
if(!isset($modx) || !evo()->isLoggedin()) exit;
unset($_SESSION['itemname']); // clear this, because it's only set for logging purposes
?>

<h1><?php echo lang('search_criteria'); ?></h1>
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
            src="<?php echo style('icons_cancel') ?>"
        /> <?php echo lang('cancel')?></a></li>
    </ul>
</div>
<div class="section">
    <div class="sectionBody">
        <form action="index.php" method="get">
            <input type="hidden" name="a" value="71" />
            <table width="100%" border="0">
                <tr>
                    <td width="120"><?php echo lang('search_criteria_id'); ?></td>
                    <td width="20">&nbsp;</td>
                    <td width="120">
                        <input name="searchid" type="text" value="<?php echo getv('searchid');?>" />
                    </td>
                    <td><?php echo lang('search_criteria_id_msg'); ?></td>
                </tr>
                <tr>
                    <td><?php echo lang('search_criteria_title'); ?></td>
                    <td>&nbsp;</td>
                    <td>
                        <input name="pagetitle" type="text" value="<?php echo getv('pagetitle');?>" />
                    </td>
                    <td><?php echo lang('search_criteria_title_msg'); ?></td>
                </tr>
                <tr>
                    <td><?php echo lang('search_criteria_longtitle'); ?></td>
                    <td>&nbsp;</td>
                    <td>
                        <input name="longtitle" type="text" value="<?php echo getv('longtitle');?>" />
                    </td>
                    <td><?php echo lang('search_criteria_longtitle_msg'); ?></td>
                </tr>
                <tr>
                    <td>Alias</td>
                    <td>&nbsp;</td>
                    <td>
                        <input name="alias" type="text" value="<?php echo getv('alias');?>" />
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td>URL</td>
                    <td>&nbsp;</td>
                    <td>
                        <input name="url" type="text" size="50" value="<?php echo getv('url');?>" />
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td><?php echo lang('search_criteria_content'); ?></td>
                    <td>&nbsp;</td>
                    <td>
                        <input name="content" type="text" value="<?php echo getv('content');?>" />
                    </td>
                    <td><?php echo lang('search_criteria_content_msg'); ?></td>
                </tr>
                <tr>
                    <td colspan="4">
                        <ul class="actionButtons">
                            <li><a
                                    class="default"
                                    href="#"
                                    onclick="jQuery('#submitok').click();"
                                ><img
                                    src="<?php echo style('icons_save') ?>"
                                /> <?php echo lang('search') ?></a
                                ></li>
                            <li><a
                                    href="index.php?a=2"
                                ><img
                                    src="<?php echo style('icons_cancel') ?>"
                                /> <?php echo lang('cancel') ?></a
                                ></li>
                        </ul>
                    </td>
                </tr>
            </table>

            <input type="submit" id="submitok" value="Search" name="submitok" style="display:none" />
        </form>
    </div>
</div>

<?php
if(getv('submitok')) {

    if(getv('url')) {
        $url = preg_replace(
            '@' . config('friendly_url_suffix') . '$@'
            , ''
            , trim(getv('url'))
        );
        if ($url[0] === '/') {
            $url = preg_replace('@^' . MODX_BASE_URL . '@', '', $url);
        }
        if (substr($url, 0, 4) === 'http') {
            $url = preg_replace('@^' . MODX_SITE_URL . '@', '', $url);
        }
        $url = trim($url, '/');
        $searchid = evo()->getIdFromAlias($url);
        if (!$searchid) {
            $searchid = 'x';
        }
    } else {
        $searchid = getv('searchid', 0);
    }

    $where = array();
    if ($searchid) {
        $where[] = "id='" . db()->escape($searchid) . "' ";
    }
    $searchtitle = trim(getv('pagetitle',''));
    if ($searchtitle != '') {
        $where[] = "pagetitle LIKE '%" . db()->escape($searchtitle) . "%' ";
    }
    $searchlongtitle = trim(getv('longtitle'));
    if ($searchlongtitle != '') {
        $where[] = "longtitle LIKE '%" . db()->escape($searchlongtitle) . "%' ";
    }
    $search_alias = trim(getv('alias'));
    if ($search_alias != '') {
        $where[] = "alias LIKE '%" . db()->escape($search_alias) . "%' ";
    }
    $searchcontent = getv('content');
    if ($searchcontent != '') {
        $where[] = "content LIKE '%" . db()->escape($searchcontent) . "%' ";
    }

    $rs = db()->select(
        'id, contenttype, pagetitle, description, deleted, published, isfolder, type'
        , '[+prefix+]site_content'
        , implode(' and ', $where)
        , 'id'
    );
    $limit = db()->getRecordCount($rs);
    if(evo()->hasPermission('edit_document')) {
        $action = '27';
        $itemicon = style('icons_edit_document');
    }
    else {
        $action = '3';
        $itemicon = style('icons_resource_overview');
    }
?>
<div class="section">
    <div class="sectionHeader"><?php echo lang('search_results'); ?></div>
    <div class="sectionBody">
        <?php
        if($limit<1) {
            echo lang('search_empty');
        } else {
            printf('<p>'.lang('search_results_returned_msg').'</p>', $limit);
            ?>
            <script type="text/javascript" src="media/script/tablesort.js"></script>
            <table border="0" cellpadding="2" cellspacing="0" class="sortabletable sortable-onload-2 rowstyle-even" id="table-1" width="90%">
                <thead>
                <tr style="background-color:#cccccc">
                    <th width="20"></th>
                    <th class="sortable"><b><?php echo lang('search_results_returned_id'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('search_results_returned_title'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('search_results_returned_desc'); ?></b></th>
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
                    if($row['published'] == 0) {
                        $tdClass .= ' class="unpublishedNode"';
                    }
                    if($row['deleted'] == 1) {
                        $tdClass .= ' class="deletedNode"';
                    }
                    ?>
                    <tr>
                        <td align="center">
                            <a href="index.php?a=<?php echo $action;?>&id=<?php echo $row['id']; ?>" title="<?php echo lang('search_view_docdata'); ?>">
                                <img src="<?php echo $itemicon; ?>" /></a>
                        </td>
                        <td>
                            <?php
                            if ($row['isfolder'] == 1) {
                                ?>
                                <a href="index.php?a=120&id=<?php echo $row['id']; ?>" title="<?php echo lang('search_view_docdata'); ?>"><img src="<?php echo style('tree_folder'); ?>" /></a>
                                <?php
                            }
                            echo $row['id'];
                            ?>
                        </td>
                        <td<?php echo $tdClass; ?>
                        <?php
                        $pagetitle = mb_strlen($row['pagetitle'], $modx_manager_charset)>70 ? evo()->hsc(mb_substr($row['pagetitle'], 0, 70, $modx_manager_charset))."..." : evo()->hsc($row['pagetitle']);
                        ?>
                        <img src="<?php echo $icon; ?>" /> <?php echo $pagetitle ; ?></td>
                        <td<?php echo $tdClass; ?>><?php echo mb_strlen($row['description'], $modx_manager_charset)>70 ? mb_substr($row['description'], 0, 70, $modx_manager_charset)."..." : $row['description'] ; ?></td>
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
