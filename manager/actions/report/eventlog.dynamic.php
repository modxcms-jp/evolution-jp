<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_eventlog')) {
    alert()->setError(3);
    alert()->dumpError();
}

global $_PAGE;
manager()->initPageViewState();

// get and save search string
if (anyv('op') === 'reset') {
    $search = $query = '';
    $_PAGE['vs']['search'] = '';
} else {
    $search = $query = anyv('search', array_get($_PAGE, 'vs.search', ''));
    if (!is_numeric($search)) {
        $search = db()->escape($query);
    }
    $_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = anyv('listmode', array_get($_PAGE, 'vs.lm'));
$_PAGE['vs']['lm'] = $listmode;

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
$cm->addItem(lang('view_log'), "js:menuAction(1)", style('icons_save'));
$cm->addSeparator();
$cm->addItem(lang('delete'), "js:menuAction(2)", style('icons_delete'),
    (!evo()->hasPermission('delete_eventlog') ? 1 : 0));
echo $cm->render();

?>
<script type="text/javascript">
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

    function exportLog() {
        var form = document.resource;
        var previousAction = form.getAttribute('action');
        var previousTarget = form.getAttribute('target');

        form.action = 'index.php?a=121';
        form.target = 'fileDownloader';
        form.submit();

        if (previousAction !== null) {
            form.action = previousAction;
        } else {
            form.removeAttribute('action');
        }
        if (previousTarget !== null) {
            form.target = previousTarget;
        } else {
            form.removeAttribute('target');
        }
    }

    var selectedItem;
    var contextm = <?= $cm->getClientScriptObject()?>;

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
            case 1:		// view log details
                window.location.href = 'index.php?a=115&id=' + id;
                break;
            case 2:		// clear log
                window.location.href = 'index.php?a=116&id=' + id;
                break;
        }
    }

    document.addEventListener('click', function () {
        contextm.style.visibility = "hidden";
    });
</script>
<form name="resource" method="post">
    <input type="hidden" name="id" value="<?= $id ?? '' ?>"/>
    <input type="hidden" name="listmode" value="<?= $listmode ?>"/>
    <input type="hidden" name="op" value=""/>

    <h1><?= lang('eventlog_viewer') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate">
                <a href="#"
                    onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    alt="icons_cancel"
                    src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?>
                </a>
            </li>
        </ul>
    </div>

    <div class="sectionBody">
        <!-- load modules -->
        <p><?= $_lang['eventlog_msg'] ?></p>
        <div class="actionButtons">
            <table border="0" style="width:100%">
                <tr>
                    <td>
                        <a href="index.php?a=116&cls=1">
                            <img
                                src="<?= $_style["icons_delete_document"] ?>"
                                align="absmiddle"
                            /> <?= lang('clear_log') ?>
                        </a>
                    </td>
                    <td>
                        <a href="#" onclick="exportLog();return false;">
                            <img
                                src="<?= $_style['icons_save'] ?>"
                                align="absmiddle"
                            /> <?= lang('export_event_log') ?>
                        </a>
                    </td>
                    <td nowrap="nowrap">
                        <table border="0" style="float:right">
                            <tr>
                                <td><?= lang('search') ?> </td>
                                <td>
                                    <input class="searchtext" name="search" type="text" size="15"
                                        value="<?= $query ?>"/>
                                </td>
                                <td>
                                    <a class="primary" href="#" title="<?= lang('search') ?>"
                                        onclick="searchResource();return false;">
                                        <img src="<?= style('icons_save') ?>"/>
                                        <?= lang('go') ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="#" title="<?= $_lang['reset'] ?>"
                                        onclick="resetSearch();return false;">
                                        <img src="<?= style('icons_refresh') ?>" style="display:inline;"/>
                                    </a>
                                </td>
                                <td>
                                    <a href="#" title="<?= $_lang['list_mode'] ?>" onclick="changeListMode();return false;">
                                        <img
                                            src="<?= style('icons_table') ?>"
                                            style="display:inline;"/>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div>
            <?php
            $field = "el.id, el.type, el.createdon, el.source, el.eventid,IFNULL(wu.username,mu.username) as 'username'";
            $from = '[+prefix+]event_log el';
            $from .= ' LEFT JOIN [+prefix+]manager_users mu ON mu.id=el.user AND el.usertype=0';
            $from .= ' LEFT JOIN [+prefix+]web_users wu ON wu.id=el.user AND el.usertype=1';
            $where = '';
            if ($search) {
                if (is_numeric($search)) {
                    $where = "(eventid='{$search}') OR ";
                }
                $where .= "(source LIKE '%{$search}%') OR (description LIKE '%{$search}%')";
            }
            $orderby = 'el.id DESC';
            $ds = db()->select($field, $from, $where, $orderby);
            include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
            $grd = new DataGrid('', $ds, $number_of_results); // set page size to 0 t show all items
            $grd->noRecordMsg = $_lang['no_records_found'];
            $grd->cssClass = "grid";
            $grd->columnHeaderClass = "gridHeader";
            $grd->itemClass = "gridItem";
            $grd->altItemClass = "gridAltItem";
            $grd->fields = "id,type,source,createdon,username";
            $grd->columns = sprintf(
                '%s, %s, %s, %s, %s',
                $_lang['event_id'], $_lang['type'], $_lang['source'], $_lang['date'], $_lang['sysinfo_userid']
            );
            $grd->colWidths = "20,34,,150";
            $grd->columnHeaderStyle = 'text-align:center;';
            $grd->colAligns = "right,center,,,center,center";
            $icon_path = manager_style_image_path('icons');
            $grd->colTypes = sprintf(
                '||template:<a class="gridRowIcon" href="#" onclick="return showContentMenu([+id+],event);" title="%s"><img src="%sevent[+type+].png" /></a>' .
                '||template:<a href="index.php?a=115&id=[+id+]" title="%s">[+source+]</a>||date: %s',
                $_lang['click_to_context'],
                $icon_path,
                $_lang['click_to_view_details'],
                $modx->toDateFormat(null, 'formatOnly') . ' %H:%M:%S'
            );
            if ($listmode == '1') {
                $grd->pageSize = 0;
            }
            if (anyv('op') == 'reset') {
                $grd->pageNumber = 1;
            }
            // render grid
            echo $grd->render();
            ?>
        </div>
    </div>
    <iframe name="fileDownloader" width="1" height="1" style="display:none; width:1px; height:1px;"></iframe>
</form>
