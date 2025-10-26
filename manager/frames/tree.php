<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';
$esc_request = db()->escape($_REQUEST);

$resourceTreeNodeName = config('resource_tree_node_name', '');
$fieldtype = is_string($resourceTreeNodeName) && strpos($resourceTreeNodeName, 'edon') !== false
    ? 'date'
    : 'str';
if (!sessionv('tree_sortby')) {
    $_SESSION['tree_sortby'] = tree_sortby_default(
        config('resource_tree_sortby_default','menuindex')
    );
}
if (!sessionv('tree_sortdir')) {
    $_SESSION['tree_sortdir'] = tree_sortdir_default(
        config('resource_tree_sortdir_default','ASC')
    );
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html <?= ($modx_textdir === 'rtl' ? 'dir="rtl" lang="' : 'lang="') . $mxla . '" xml:lang="' . $mxla . '"' ?>>
<head>
    <title>Document Tree</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= $modx_manager_charset ?>"/>
    <link rel="stylesheet"
            href="media/style/<?= $manager_theme ?>/style.css?<?= $modx_version ?>"/>
    <?= config('manager_inline_style') ?>
    <script src="media/script/jquery/jquery.min.js"></script>
    <script>
        jQuery(function () {
            resizeTree();
            restoreTree();
            jQuery(window).resize(function () {
                resizeTree();
            });
        });

        // preload images
        var i = new Image(18, 18);
        i.src = "<?= $_style["tree_page"]?>";
        i = new Image(18, 18);
        i.src = "<?= $_style["tree_globe"]?>";
        i = new Image(18, 18);
        i.src = "<?= $_style["tree_minusnode"]?>";
        i = new Image(18, 18);
        i.src = "<?= $_style["tree_plusnode"]?>";
        i = new Image(18, 18);
        i.src = "<?= $_style["tree_folderopen"]?>";
        i = new Image(18, 18);
        i.src = "<?= $_style["tree_folder"]?>";


        var rpcNode = null;
        var ca = "open";
        var selectedObject = 0;
        var selectedObjectDeleted = 0;
        var selectedObjectName = "";
        var _rc = 0; // added to fix onclick body event from closing ctx menu

        var openedArray = [];
        <?php
        if (!sessionv('openedArray')) {
            if(config('allowed_parents')) {
                $_SESSION['openedArray'] = openedArray(
                    config('allowed_parents')
                ) ?: [];
            } else {
                $_SESSION['openedArray'] = [];
            }
        }

        if (sessionv('openedArray')) {
            foreach (sessionv('openedArray') as $i=>$v) {
                if(!$v) {
                    continue;
                }
                echo sprintf("openedArray[%s] = 1;\n", $v);
            }
        }
        ?>

        function showPopup(id, title, pub, del, draft, e) {
            var x, y
            var mnu = document.getElementById('mx_contextmenu');
            var permpub = <?= evo()->hasPermission('publish_document') ? 1 : 0 ?>;
            var permdel = <?= evo()->hasPermission('delete_document') ? 1 : 0 ?>;
            if (draft == 1) {
                jQuery('#itemcreateDraft').hide();
                jQuery('#itemeditDraft').show();
            } else {
                jQuery('#itemcreateDraft').show();
                jQuery('#itemeditDraft').hide();
            }
            if (permpub == 1) {
                jQuery('#item61').show();
                jQuery('#item62').show();
                if (pub == 1) jQuery('#item61').hide();
                else jQuery('#item62').hide();
            } else {
                if (jQuery('#item51') != null) jQuery('#item51').hide();

                if (draft == 1) jQuery('#item27').hide();
                else jQuery('#item27').show();
            }

            if (permdel == 1) {
                jQuery('#item6').show();
                jQuery('#item63').show();
                if (jQuery('#item64') != null) jQuery('#item64').show();
                if (del == 1) {
                    jQuery('#item6').hide();
                    jQuery('#item61').hide();
                    jQuery('#item62').hide();
                } else {
                    jQuery('#item63').hide();
                    if (document.getElementById('item64') != null) jQuery('#item64').hide();
                }
            }
            var bodyHeight = parseInt(document.body.offsetHeight);
            x = e.clientX > 0 ? e.clientX : e.pageX;
            y = e.clientY > 0 ? e.clientY : e.pageY;
            y = getScrollY() + (y / 2);
            if (y + mnu.offsetHeight > bodyHeight) {
                // make sure context menu is within frame
                y = mnu.offsetHeight - bodyHeight + 5;
            }
            itemToChange = id;
            selectedObjectName = title;
            dopopup(x + 5, y);
            e.cancelBubble = true;
            return false;
        }

        function dopopup(x, y) {
            if (selectedObjectName.length > 20) {
                selectedObjectName = selectedObjectName.substr(0, 20) + "...";
            }
            x = x<?= $modx_textdir === 'rtl' ? '-190' : '';?>;
            jQuery('#mx_contextmenu').css('left', x); //offset menu to the left if rtl is selected
            jQuery('#mx_contextmenu').css('top', y);
            jQuery("#nameHolder").text(selectedObjectName);

            jQuery('#mx_contextmenu').css('visibility', 'visible');
            _rc = 1;
            setTimeout("_rc = 0;", 100);
        }

        function toggleNode(node, indent, id, expandAll, privatenode) {
            privatenode = (!privatenode || privatenode == '0') ? '0' : '1';
            rpcNode = document.getElementById('c' + id);
            var rpcNodeText;

            var signImg = document.getElementById('s' + id);
            var folderImg = document.getElementById('f' + id);

            if (rpcNode.style.display === 'block') {
                // collapse
                signImg.src = '<?= $_style["tree_plusnode"] ?>';
                //rpcNode.innerHTML = '';
                jQuery(rpcNode).hide(100);
                openedArray[id] = 0;
            } else {
                // expand
                signImg.src = '<?= $_style["tree_minusnode"] ?>';

                rpcNodeText = rpcNode.innerHTML;
                openedArray[id] = 1;

                if (rpcNodeText == '') {
                    var folderState = getFolderState();
                    jQuery.get('index.php', {
                        "a": "1",
                        "f": "nodes",
                        "indent": indent,
                        "parent": id,
                        "expandAll": expandAll + folderState
                    }, rpcLoadData);
                    jQuery(rpcNode).show(100);
                } else {
                    jQuery(rpcNode).show(100);
                }
            }

            jQuery.get(
                'index.php?a=1&f=nodes&savestateonly=1' + getFolderState()
            );
        }

        function emptyTrash() {
            if (confirm("<?= $_lang['confirm_empty_trash'] ?>") == true) {
                top.main.document.location.href = "index.php?a=64";
            }
        }

        currSorterState = "none";

        function treeAction(id, name) {
            if (ca == "move") {
                try {
                    parent.main.setMoveValue(id, name);
                } catch (oException) {
                    alert('<?= $_lang['unable_set_parent'] ?>');
                }
            }
            if (ca == "open" || ca == "docinfo" || ca == "doclist" || ca == "") {
                <?php $action = (!empty(config('tree_page_click')) ? config('tree_page_click') : '27'); ?>
                if (id == 0) {
                    // do nothing?
                    parent.main.location.href = "index.php?a=120";
                } else if (ca == "docinfo") {
                    parent.main.location.href = "index.php?a=3&id=" + id;
                } else if (ca == "doclist") {
                    parent.main.location.href = "index.php?a=120&id=" + id;
                } else if (ca == "open") {
                    parent.main.location.href = "index.php?a=27&id=" + id;
                } else {
                    // parent.main.location.href="index.php?a=3&id=" + id + getFolderState(); //just added the getvar &opened=
                    parent.main.location.href = "index.php?a=<?= $action ?>&id=" + id; // edit as default action
                }
            }
            if (ca == "parent") {
                try {
                    parent.main.setParent(id, name);
                } catch (oException) {
                    alert('<?= $_lang['unable_set_parent'] ?>');
                }
            }
            if (ca == "link") {
                try {
                    parent.main.setLink(id);
                } catch (oException) {
                    alert('<?= $_lang['unable_set_link'] ?>');
                }
            }
        }

        // show state of recycle bin
        function showBinFull() {
            var a = document.getElementById('Button10');
            var title = '<?= $_lang['empty_recycle_bin'] ?>';
            if (a) {
                if (!a.setAttribute) a.title = title;
                else a.setAttribute('title', title);
                a.innerHTML = '<?= $_style['empty_recycle_bin'] ?>';
                a.className = 'treeButton';
                a.onclick = emptyTrash;
            }
        }

        function showBinEmpty() {
            var a = document.getElementById('Button10');
            var title = '<?= addslashes($_lang['empty_recycle_bin_empty']) ?>';
            if (a) {
                if (!a.setAttribute) a.title = title;
                else a.setAttribute('title', title);
                a.innerHTML = '<?= $_style['empty_recycle_bin_empty'] ?>';
                a.className = 'treeButtonDisabled';
                a.onclick = '';
            }
        }

    </script>
    <script src="media/script/tree.js"></script>
</head>
<body onclick="hideMenu();" class="<?= $modx_textdir === 'rtl' ? ' rtl' : '' ?>">
<?php
// invoke OnTreePrerender event
$evtOut = evo()->invokeEvent('OnManagerTreeInit', $esc_request);
if (is_array($evtOut)) {
    echo implode("\n", $evtOut);
}
?>
<div class="treeframebody">
    <div id="treeSplitter"></div>

    <table id="treeMenu" width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td><a href="#" class="treeButton" id="Button1" onclick="expandTree();"
                                title="<?= $_lang['expand_tree'] ?>"><?= $_style['expand_tree'] ?></a>
                        </td>
                        <td><a href="#" class="treeButton" id="Button2" onclick="collapseTree();"
                                title="<?= $_lang['collapse_tree'] ?>"><?= $_style['collapse_tree'] ?></a>
                        </td>
                        <?php if (evo()->hasPermission('new_document') && isAllowroot()) { ?>
                            <td><a href="#" class="treeButton" id="Button3a"
                                    onclick="top.main.document.location.href='index.php?a=4';"
                                    title="<?= $_lang['add_resource'] ?>"><?= $_style['add_doc_tree'] ?></a>
                            </td>
                            <td><a href="#" class="treeButton" id="Button3c"
                                    onclick="top.main.document.location.href='index.php?a=72';"
                                    title="<?= $_lang['add_weblink'] ?>"><?= $_style['add_weblink_tree'] ?></a>
                            </td>
                        <?php } ?>
                        <td><a href="#" class="treeButton" id="Button4" onclick="top.mainMenu.reloadtree();"
                                title="<?= $_lang['refresh_tree'] ?>"><?= $_style['refresh_tree'] ?></a>
                        </td>
                        <td><a href="#" class="treeButton" id="Button5" onclick="showSorter();"
                                title="<?= $_lang['sort_tree'] ?>"><?= $_style['sort_tree'] ?></a></td>
                        <?php if (evo()->hasPermission('empty_trash')) { ?>
                            <td><a href="#" id="Button10" class="treeButtonDisabled"
                                    title="<?= $_lang['empty_recycle_bin_empty'] ?>"><?= $_style['empty_recycle_bin_empty'] ?></a>
                            </td>
                        <?php } ?>
                    </tr>
                </table>
            </td>
            <td align="right">
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td><a href="#" class="treeButton" id="Button6" onclick="top.mainMenu.hideTreeFrame();"
                                title="<?= $_lang['hide_tree'] ?>"><?= $_style['hide_tree'] ?></a></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div id="floater">
        <form name="sortFrm" id="sortFrm" action="menu.php">
            <table style="width:100%;border:none;padding:0;margin:0">
                <tr>
                    <td style="padding-left: 10px;padding-top: 1px;" colspan="2">
                        <select name="sortby" style="font-size: 12px;">
                            <option
                                value="isfolder"
                                <?= isSelectedTreeSortby('isfolder') ?>
                            ><?= $_lang['folder'] ?></option>
                            <option
                                value="pagetitle"
                                <?= isSelectedTreeSortby('pagetitle') ?>
                            ><?= $_lang['pagetitle'] ?></option>
                            <option
                                value="id"
                                <?= isSelectedTreeSortby('id') ?>
                            ><?= $_lang['id'] ?></option>
                            <option
                                value="menuindex"
                                <?= isSelectedTreeSortby('menuindex') ?>
                            ><?= $_lang['resource_opt_menu_index'] ?></option>
                            <option
                                value="createdon"
                                <?= isSelectedTreeSortby('createdon') ?>
                            ><?= $_lang['createdon'] ?></option>
                            <option
                                value="editedon"
                                <?= isSelectedTreeSortby('editedon') ?>
                            ><?= $_lang['editedon'] ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td width="99%" style="padding-left: 10px;padding-top: 1px;">
                        <select name="sortdir" style="font-size: 12px;">
                            <option
                                value="DESC"
                                <?= isSelectedTreeSortDir('DESC') ?>
                            ><?= $_lang['sort_desc'] ?></option>
                            <option
                                value="ASC"
                                <?= isSelectedTreeSortDir('ASC') ?>
                            ><?= $_lang['sort_asc'] ?></option>
                        </select>
                        <input type="hidden" name="dt" value="<?= htmlspecialchars(anyv('dt', '')) ?>"/>
                    </td>
                    <td width="1%"><a
                        href="#"
                        class="treeButton"
                        id="button7"
                        style="text-align:right"
                        onclick="updateTree();showSorter();"
                        title="<?= $_lang['sort_tree'] ?>"
                    ><?= $_lang['sort_tree'] ?></a>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <div id="treeHolder">
        <?php
        // invoke OnTreeRender event
        $evtOut = evo()->invokeEvent('OnManagerTreePrerender', $esc_request);
        if (is_array($evtOut)) {
            echo implode("\n", $evtOut);
        }
        ?>
        <div><?= $_style['tree_showtree'] ?>&nbsp;<span class="rootNode"
                                                                onclick="treeAction(0, '<?= addslashes($site_name) ?>');"><b><?= $site_name ?></b></span>
            <div id="treeRoot"></div>
        </div>
        <?php
        // invoke OnTreeRender event
        $evtOut = evo()->invokeEvent('OnManagerTreeRender', $esc_request);
        if (is_array($evtOut)) {
            echo implode("\n", $evtOut);
        }
        ?>
    </div>

    <script type="text/javascript">
        // Set 'treeNodeSelected' class on document node when editing via Context Menu
        function setActiveFromContextMenu(doc_id) {
            jQuery('.treeNodeSelected').removeClass('treeNodeSelected');
            jQuery('#node' + doc_id + ' span:first').prop('class', 'treeNodeSelected');
        }

        // Context menu stuff
        function menuHandler(action) {
            switch (action) {
                case '3' : // view
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=3&id=" + itemToChange;
                    break;
                case '27' : // edit
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=27&id=" + itemToChange;
                    break;
                case 'createDraft' : // createt draft
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=132&id=" + itemToChange;
                    break
                case 'editDraft' : // edit draft
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=131&id=" + itemToChange;
                    break
                case '4' : // new Resource
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=4&pid=" + itemToChange;
                    break;
                case '51' : // move
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=51&id=" + itemToChange;
                    break;
                case '72' : // new Weblink
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=72&pid=" + itemToChange;
                    break;
                case '94' : // duplicate
                    if (confirm("<?= $_lang['confirm_resource_duplicate'] ?>") == true) {
                        setActiveFromContextMenu(itemToChange);
                        top.main.document.location.href = "index.php?a=94&id=" + itemToChange;
                    }
                    break;
                case '6' : // delete
                    if (selectedObjectDeleted == 0) {
                        if (confirm("'" + selectedObjectName + "'\n\n<?= $_lang['confirm_delete_resource'] ?>") == true) {
                            top.main.document.location.href = "index.php?a=6&id=" + itemToChange;
                        }
                    } else {
                        alert("'" + selectedObjectName + "' <?= $_lang['already_deleted'] ?>");
                    }
                    break;
                case '63' : // undelete
                    if (selectedObjectDeleted == 0) {
                        alert("'" + selectedObjectName + "' <?= $_lang['not_deleted'] ?>");
                    } else {
                        if (confirm("'" + selectedObjectName + "' <?= $_lang['confirm_undelete'] ?>") == true) {
                            top.main.document.location.href = "index.php?a=63&id=" + itemToChange;
                        }
                    }
                    break;
                case '64' : // delete
                    if (selectedObjectDeleted == 1) {
                        if (confirm("'" + selectedObjectName + "'\n\n<?= $_lang['confirm_delete_resource'] ?>") == true) {
                            top.main.document.location.href = "index.php?a=64&id=" + itemToChange;
                        }
                    } else {
                        alert("'" + selectedObjectName + "' <?= $_lang['already_deleted'] ?>");
                    }
                    break;
                case '61' : // publish
                    if (confirm("'" + selectedObjectName + "' <?= $_lang['confirm_publish'] ?>") == true) {
                        setActiveFromContextMenu(itemToChange);
                        top.main.document.location.href = "index.php?a=61&id=" + itemToChange;
                    }
                    break;
                case '62' : // unpublish
                    if (itemToChange != <?= config('site_start') ?>) {
                        if (confirm("'" + selectedObjectName + "' <?= $_lang['confirm_unpublish'] ?>") == true) {
                            setActiveFromContextMenu(itemToChange);
                            top.main.document.location.href = "index.php?a=62&id=" + itemToChange;
                        }
                    } else {
                        alert('Document is linked to site_start variable and cannot be unpublished!');
                    }
                    break;
                case 'pv' : // preview
                    setActiveFromContextMenu(itemToChange);
                    window.open(selectedObjectUrl, 'previeWin'); //re-use 'new' window
                    break;
                case '120' : // resources list
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href = "index.php?a=120&id=" + itemToChange;
                    break;

                default :
                    alert('Unknown operation command.');
            }
        }

    </script>
    <?php
    function getTplCtxMenu()
    {
        $tpl = <<< EOT
<!-- Contextual Menu Popup Code -->
<div id="mx_contextmenu" onselectstart="return false;">
    <div id="nameHolder">&nbsp;</div>
		[+itemEditDoc+]
		[+itemDocList+]
		[+itemNewDoc+]
		[+itemMoveDoc+]
		[+itemDuplicateDoc+]
		[+=========1+]
		[+itemPubDoc+][+itemUnPubDoc+]
		[+itemDelDoc+][+itemUndelDoc+][+itemDelDocComplete+]
		[+=========2+]
		[+itemWebLink+]
		[+=========3+]
		[+itemCreateDraft+]
		[+itemEditDraft+]
		[+itemDocInfo+]
		[+itemViewPage+]
	</div>
</div>
EOT;
        return $tpl;
    }

    $ph = [];
    $ph['itemEditDoc'] = itemEditDoc(); // edit
    $ph['itemDocList'] = itemDocList(); // Resource list
    $ph['itemNewDoc'] = itemNewDoc(); // new Resource
    $ph['itemMoveDoc'] = itemMoveDoc(); // move
    $ph['itemDuplicateDoc'] = itemDuplicateDoc(); // duplicate
    $ph['=========1'] = itemSeperator1();
    $ph['itemPubDoc'] = itemPubDoc(); // publish
    $ph['itemUnPubDoc'] = itemUnPubDoc(); // unpublish
    $ph['itemDelDoc'] = itemDelDoc(); // delete
    $ph['itemUndelDoc'] = itemUndelDoc(); // undelete
    $ph['itemDelDocComplete'] = itemDelDocComplete(); // undelete
    $ph['=========2'] = itemSeperator2();
    $ph['itemWebLink'] = itemWebLink(); //  new Weblink
    $ph['=========3'] = itemSeperator3();
    $ph['itemCreateDraft'] = itemCreateDraft(); // create draft
    $ph['itemEditDraft'] = itemEditDraft(); // edit draft
    $ph['itemDocInfo'] = itemDocInfo(); // undelete
    $ph['itemViewPage'] = itemViewPage(); // preview

    $tpl = getTplCtxMenu();
    echo evo()->parseText($tpl, $ph);

    ?>
</body>
</html>
<?php
function select($cond = false)
{
    return ($cond) ? ' selected="selected"' : '';
}

function tplMenuItem()
{
    return <<< EOT
<div class="menuLink" id="item[+action+]" onclick="menuHandler('[+action+]'); hideMenu();">
	<img src="[+img+]" />[+text+]
</div>
EOT;
}

function itemEditDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('edit_document')) {
        return '';
    }
    $ph['action'] = '27';
    $ph['img'] = $_style['icons_edit_document'];
    $ph['text'] = $_lang['edit_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemCreateDraft()
{
    global $_style, $_lang;

    if (!config('enable_draft')) {
        return '';
    }

    $ph['action'] = 'createDraft';
    $ph['img'] = $_style['icons_new_document'];
    $ph['text'] = $_lang["create_draft"];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemEditDraft()
{
    global $_style, $_lang;

    if (!config('enable_draft')) {
        return '';
    }

    $ph['action'] = 'editDraft';
    $ph['img'] = $_style['icons_edit_document'];
    $ph['text'] = $_lang["edit_draft"];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDocList()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('view_document')) {
        return '';
    }
    $ph['action'] = '120';
    $ph['img'] = $_style['icons_table'];
    $ph['text'] = $_lang['view_child_resources_in_container'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemNewDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('new_document')) {
        return '';
    }
    $ph['action'] = '4';
    $ph['img'] = $_style['icons_new_document'];
    $ph['text'] = $_lang['create_resource_here'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemMoveDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('move_document')) {
        return '';
    }
    if (!evo()->hasPermission('save_document')) {
        return '';
    }
    $ph['action'] = '51';
    $ph['img'] = $_style['icons_move_document'];
    $ph['text'] = $_lang['move_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDuplicateDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('new_document') || !evo()->hasPermission('save_document')) {
        return '';
    }
    $ph['action'] = '94';
    $ph['img'] = $_style['icons_resource_duplicate'];
    $ph['text'] = $_lang['resource_duplicate'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator1()
{
    if (evo()->hasPermission('edit_document') || evo()->hasPermission('new_document') || evo()->hasPermission('save_document')) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemPubDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('publish_document')) {
        return '';
    }
    $ph['action'] = '61';
    $ph['img'] = $_style['icons_publish_document'];
    $ph['text'] = $_lang['publish_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemUnPubDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('publish_document')) {
        return '';
    }
    $ph['action'] = '62';
    $ph['img'] = $_style['icons_unpublish_resource'];
    $ph['text'] = $_lang['unpublish_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDelDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('delete_document')) {
        return '';
    }
    $ph['action'] = '6';
    $ph['img'] = $_style['icons_delete'];
    $ph['text'] = $_lang['delete_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemUndelDoc()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('delete_document')) {
        return '';
    }
    $ph['action'] = '63';
    $ph['img'] = $_style['icons_undelete_resource'];
    $ph['text'] = $_lang['undelete_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDelDocComplete()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('empty_trash')) {
        return '';
    }
    $ph['action'] = '64';
    $ph['img'] = $_style['icons_delete_complete'];
    $ph['text'] = $_lang['delete_resource_complete'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator2()
{
    if (evo()->hasPermission('publish_document') || evo()->hasPermission('delete_document')) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemWebLink()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('new_document')) {
        return '';
    }
    $ph['action'] = '72';
    $ph['img'] = $_style['icons_weblink'];
    $ph['text'] = $_lang['create_weblink_here'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator3()
{
    if (evo()->hasPermission('new_document')) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemDocInfo()
{
    global $_style, $_lang;

    if (!evo()->hasPermission('view_document')) {
        return '';
    }
    $ph['action'] = '3';
    $ph['img'] = $_style['icons_information'];
    $ph['text'] = $_lang['resource_overview'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemViewPage()
{
    global $_style, $_lang;

    $ph['action'] = 'pv';
    $ph['img'] = $_style['icons_information'];
    $ph['text'] = $_lang['preview_resource'];
    return evo()->parseText(tplMenuItem(), $ph);
}

function isAllowroot()
{
    if (evo()->hasPermission('save_role')) {
        return 1;
    }
    if (config('udperms_allowroot')) {
        return 1;
    }

    return 0;
}

function openedArray($allowed_parents)
{
    $allowed_parents = explode(',', $allowed_parents);
    $openedArray = [];
    foreach ($allowed_parents as $allowed_parent) {
        $_ = evo()->getParentIds($allowed_parent);
        if (!$_) {
            continue;
        }
        // $openedArray[] = $allowed_parent;
        foreach ($_ as $v) {
            $openedArray[] = $v;
        }
    }
    return $openedArray;
}

function isSelectedTreeSortby($name) {
    if($name === 'menuindex' && !sessionv('tree_sortby')) {
        return 'selected';
    }
    if(sessionv('tree_sortby')!==$name) {
        return null;
    }
    return 'selected';
}

function isSelectedTreeSortDir($direction) {
    if($direction === 'ASC' && !sessionv('tree_sortdir')) {
        return 'selected';
    }
    if(sessionv('tree_sortdir')!==$direction) {
        return null;
    }
    return 'selected';
}

function tree_sortby_default($field_name) {
    $names = ['isfolder','pagetitle','id','menuindex','createdon','editedon'];
    return in_array($field_name, $names)
        ? $field_name
        : 'menuindex';
}

function tree_sortdir_default($field_name) {
    $names  = [
        'isfolder'  => 'DESC',
        'pagetitle' => 'ASC',
        'id'        => 'ASC',
        'menuindex' => 'ASC',
        'createdon' => 'DESC',
        'editedon'  => 'DESC',
    ];
    return isset($names[$field_name]) ? $names[$field_name] : 'ASC';
}
