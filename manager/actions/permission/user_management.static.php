<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_user')) {
    alert()->setError(3);
    alert()->dumpError();
}

global $_PAGE, $_style;
manager()->initPageViewState();
// get and save search string
if (anyv('op') == 'reset') {
    $query = '';
    $_PAGE['vs']['search'] = '';
} else {
    $query = anyv('search') ?: array_get($_PAGE, 'vs.search');
    $keyword = db()->escape($query);
    $_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = anyv('listmode', array_get($_PAGE, 'vs.lm'));
$_PAGE['vs']['lm'] = $listmode;

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu('cntxm', 150);
$cm->addItem($_lang['edit'], 'js:menuAction(1)', $_style['icons_edit_document'],
    (!evo()->hasPermission('edit_user') ? 1 : 0));
$cm->addItem($_lang['delete'], 'js:menuAction(2)', $_style['icons_delete'],
    (!evo()->hasPermission('delete_user') ? 1 : 0));
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
            case 1:		// edit
                window.location.href = 'index.php?a=12&id=' + id;
                break;
            case 2:		// delete
                if (confirm("<?= $_lang['confirm_delete_user'] ?>")) {
                    window.location.href = 'index.php?a=33&id=' + id;
                }
                break;
        }
    }

    document.addEvent('click', function () {
        contextm.style.visibility = "hidden";
    });
</script>
<form name="resource" method="post">
    <input type="hidden" name="id" value="<?= $id ?? '' ?>"/>
    <input type="hidden" name="listmode" value="<?= $listmode ?? '' ?>"/>
    <input type="hidden" name="op" value=""/>

    <h1><?= $_lang['user_management_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#"
                                            onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                        alt="icons_cancel"
                        src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

    <div class="sectionBody">
        <p><?= $_lang['user_management_msg'] ?></p>
        <div class="actionButtons">
            <table border="0" style="width:100%">
                <tr>
                    <?php if (evo()->hasPermission('new_user')): ?>
                        <td><a href="index.php?a=11" class="default"><img
                                    src="<?= $_style["icons_add"] ?>"/> <?= $_lang['new_user'] ?></a>
                        </td>
                    <?php endif; ?>
                    <td nowrap="nowrap">
                        <table border="0" style="float:right">
                            <tr>
                                <td><?= $_lang["search"] ?></td>
                                <td><input class="searchtext" name="search" type="text" size="15"
                                           value="<?= $query ?>"/></td>
                                <td><a href="#" class="default" title="<?= $_lang["search"] ?>"
                                       onclick="searchResource();return false;"><?= $_lang['go'] ?></a></td>
                                <td><a href="#" title="<?= $_lang["reset"] ?>"
                                       onclick="resetSearch();return false;"><img
                                            src="<?= $_style['icons_refresh'] ?>" style="display:inline;"/></a>
                                </td>
                                <td><a href="#" title="<?= $_lang["list_mode"] ?>"
                                       onclick="changeListMode();return false;"><img
                                            src="<?= $_style['icons_table'] ?>"
                                            style="display:inline;"/></a></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div>
            <?php
            $tbl_manager_users = evo()->getFullTableName('manager_users');
            $tbl_user_attributes = evo()->getFullTableName('user_attributes');
            $tbl_user_roles = evo()->getFullTableName('user_roles');
            $field = 'mu.id,mu.username,roles.name AS rolename,mua.fullname,mua.email,mua.thislogin,mua.logincount';
            $field .= ",IF(mua.gender=1,'{$_lang['user_male']}',IF(mua.gender=2,'{$_lang['user_female']}','-')) AS gender";
            $field .= ",IF(mua.blocked,'{$_lang['yes']}','-') as blocked";
            $field .= ",IF(mua.blocked,'unpublished','published') as class";
            $from = "{$tbl_manager_users} AS mu";
            $from .= " INNER JOIN {$tbl_user_attributes} AS mua ON mua.internalKey=mu.id";
            $from .= " LEFT JOIN {$tbl_user_roles} AS roles ON mua.role=roles.id";
            if ($_SESSION['mgrRole'] == 1) {
                if ($keyword) {
                    $where = "(mu.username LIKE '" . $keyword . "%') OR (mua.fullname LIKE '%" . $keyword . "%') OR (mua.email LIKE '" . $keyword . "%')";
                } else {
                    $where = '';
                }
            } else {
                if ($keyword) {
                    $where = "((mu.username LIKE '{$keyword}%') OR (mua.fullname LIKE '%{$keyword}%') OR (mua.email LIKE '{$keyword}%')) AND mua.role != 1";
                } else {
                    $where = 'mua.role != 1';
                }
            }
            $orderby = 'mua.blocked ASC, mua.thislogin DESC';
            $ds = db()->select($field, $from, $where, $orderby);

            include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');

            $grd = new DataGrid('', $ds, $modx->config['number_of_results']); // set page size to 0 t show all items
            $grd->noRecordMsg = $_lang['no_records_found'];
            $grd->cssClass = 'grid';
            $grd->itemClass = 'gridItem';
            $grd->altItemClass = 'gridAltItem';
            $grd->fields = 'username,fullname,email,rolename,thislogin,logincount,blocked';
            $grd->colAligns = 'left,left,left,left,left,left,left';
            $grd->columns = join(',', array(
                $_lang['username'],
                $_lang['user_full_name'],
                $_lang['email'],
                $_lang['role'],
                $_lang['login_button'],
                $_lang['user_logincount'],
                $_lang['user_block']
            ));
            $grd->colTypes = join('||', array(
                sprintf('template:<a class="gridRowIcon" href="#" onclick="return showContentMenu([+id+],event);" title="%s"><img src="' . $_style['icons_user'] . '" /></a><span class="[+class+]"><a href="index.php?a=12&id=[+id+]" title="%s">[+value+]</a></span>',
                    $_lang['click_to_context'], $_lang['click_to_edit_title']),
                '[+fullname+]',
                '[+email+]',
                '[+rolename+]',
                'date: ' . $modx->toDateFormat(null, 'formatOnly') . ' %H:%M',
                '[+logincount+]',
                '[+blocked+]'
            ));
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
