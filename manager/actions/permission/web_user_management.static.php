<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_web_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

manager()->initPageViewState();

global $_PAGE;

if (anyv('op') == 'reset') {
    $query = '';
    $_PAGE['vs']['search'] = '';
} else {
    $query = anyv('search') ?: array_get($_PAGE, 'vs.search');
    $_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = anyv('listmode', array_get($_PAGE, 'vs.lm'));
$_PAGE['vs']['lm'] = $listmode;


// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
$cm->addItem(
    $_lang["edit"],
    "js:menuAction(1)",
    "media/style/{$manager_theme}/images/icons/logging.gif",
    (!evo()->hasPermission('edit_user') ? 1 : 0)
);
$cm->addItem(
    $_lang["delete"],
    "js:menuAction(2)",
    "media/style/{$manager_theme}/images/icons/delete.gif",
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
                    window.location.href = 'index.php?a=90&id=' + id;
                }
                break;
        }
    }

    document.addEventListener('click', function() {
        contextm.style.visibility = "hidden";
    });
</script>
<form name="resource" method="post">
    <input type="hidden" name="id" value="<?= $id ?? '' ?>" />
    <input type="hidden" name="listmode" value="<?= $listmode ?>" />
    <input type="hidden" name="op" value="" />

    <h1><?= $_lang['web_user_management_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#"
                    onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                        alt="icons_cancel"
                        src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <div class="sectionBody">
        <p><?= $_lang['web_user_management_msg'] ?></p>
        <div class="actionButtons">
            <table border="0" style="width:100%">
                <tr>
                    <td><a class="default" href="index.php?a=87"><img
                                src="<?= $_style["icons_add"] ?>" /> <?= $_lang['new_web_user'] ?></a>
                    </td>
                    <td nowrap="nowrap">
                        <table border="0" style="float:right">
                            <tr>
                                <td><?= $_lang["search"] ?></td>
                                <td>
                                    <input class="searchtext" name="search" type="text" size="15"
                                        value="<?= anyv('search') ?>" />
                                </td>
                                <td>
                                    <a class="default" href="#" title="<?= $_lang["search"] ?>"
                                        onclick="searchResource();return false;"><?= $_lang["go"] ?></a>
                                </td>
                                <td><a href="#" title="<?= $_lang["reset"] ?>"
                                        onclick="resetSearch();return false;"><img
                                            src="<?= $_style['icons_refresh'] ?>" style="display:inline;" /></a>
                                </td>
                                <td><a href="#" title="<?= $_lang["list_mode"] ?>"
                                        onclick="changeListMode();return false;"><img
                                            src="<?= $_style['icons_table'] ?>"
                                            style="display:inline;" /></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div>
        <?php
        $sql = "SELECT wu.id,wu.username,wua.fullname,wua.email,IF(wua.gender=1,'" . $_lang['user_male'] . "',IF(wua.gender=2,'" . $_lang['user_female'] . "','-')) as 'gender',IF(wua.blocked,'" . $_lang['yes'] . "','-') as 'blocked'" .
            "FROM " . evo()->getFullTableName("web_users") . " wu " .
            "INNER JOIN " . evo()->getFullTableName("web_user_attributes") . " wua ON wua.internalKey=wu.id " .
            (anyv('search') ? " WHERE (wu.username LIKE '" . db()->escape('search') . "%') OR (wua.fullname LIKE '%" . db()->escape('search') . "%') OR (wua.email LIKE '" . db()->escape('search') . "%')" : "") . " " .
            "ORDER BY username";
            $ds = db()->query($sql);
            include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
            $grd = new DataGrid('', $ds, $number_of_results); // set page size to 0 t show all items
            $grd->noRecordMsg = $_lang["no_records_found"];
            $grd->cssClass = "grid";
            $grd->columnHeaderClass = "gridHeader";
            $grd->itemClass = "gridItem";
            $grd->altItemClass = "gridAltItem";
            $grd->fields = "id,username,fullname,email,gender,blocked";
            $grd->columns = $_lang["icon"] . " ," . $_lang["username"] . " ," . $_lang["user_full_name"] . " ," . $_lang["email"] . " ," . $_lang["user_gender"] . " ," . $_lang["user_block"];
            $grd->colWidths = "34,,,,40,34";
            $grd->colAligns = "center,,,,center,center";
            $grd->colTypes = 'template:<a class="gridRowIcon" href="#" onclick="return showContentMenu([+id+],event);" title="' . $_lang["click_to_context"] . '"><img src="' . $_style['icons_user'] . '" /></a>||template:<a href="index.php?a=88&id=[+id+]" title="' . $_lang["click_to_edit_title"] . '">[+value+]</a>';
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
</form>
