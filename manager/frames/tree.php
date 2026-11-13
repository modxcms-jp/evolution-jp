<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$esc_request = db()->escape(anyv());

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

// シェルの部品（header.inc.php が描く。再読み込みは shell.js の reloadPartial）。単独のページとしては出さない
?>
    <script>
        // 旧フレーム参照(top.tree / parent.tree)の互換API。状態(ca)とツリー操作の窓口
        window.tree = window.tree || {};
        tree.ca = "open";

        // preload images
        var i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_page')) ?>;
        i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_globe')) ?>;
        i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_minusnode')) ?>;
        i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_plusnode')) ?>;
        i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_folderopen')) ?>;
        i = new Image(18, 18);
        i.src = <?= js_literal(style('tree_folder')) ?>;


        var rpcNode = null;
        var selectedObject = 0;
        var selectedObjectDeleted = 0;
        var selectedObjectName = "";
        var _rc = 0; // added to fix onclick body event from closing ctx menu

        var openedArray = [];
        <?php
        if (!sessionv('openedArray')) {
            if(config('allowed_parents')) {
                $_SESSION['openedArray'] = manager()->allowedParentAncestorIds();
            } else {
                $_SESSION['openedArray'] = [];
            }
        }

        if (sessionv('openedArray')) {
            foreach (sessionv('openedArray') as $i=>$v) {
                $v = (int)$v;
                if(!$v) {
                    continue;
                }
                echo sprintf("openedArray[%d] = 1;\n", $v);
            }
        }
        ?>

        function showPopup(id, title, pub, del, draft, e) {
            var x, y
            var mnu = document.getElementById('mx_contextmenu');
            var permpub = <?= manager()->can('document.publish') ? 1 : 0 ?>;
            var permdel = <?= manager()->can('document.delete') ? 1 : 0 ?>;
            // 下書きのある文書の「編集」の出し分けだけが使う。公開・非公開の項目の権限（permpub）とは別で、publish_document の有無そのもの
            var hasPublishPermission = <?= evo()->hasPermission('publish_document') ? 'true' : 'false' ?>;
            // 権限や設定によって出ない項目もあるため、あるときだけ切り替える
            function showItem(id, shown) {
                var item = document.getElementById(id);
                if (item) item.style.display = shown ? '' : 'none';
            }
            if (draft == 1) {
                showItem('itemcreateDraft', false);
                showItem('itemeditDraft', true);
            } else {
                showItem('itemcreateDraft', true);
                showItem('itemeditDraft', false);
            }
            if (permpub == 1) {
                showItem('item61', true);
                showItem('item62', true);
                if (pub == 1) showItem('item61', false);
                else showItem('item62', false);
            }
            if (!hasPublishPermission) {
                if (draft == 1) showItem('item27', false);
                else showItem('item27', true);
            }

            if (permdel == 1) {
                showItem('item6', true);
                showItem('item63', true);
                showItem('item64', true);
                if (del == 1) {
                    showItem('item6', false);
                    showItem('item61', false);
                    showItem('item62', false);
                } else {
                    showItem('item63', false);
                    showItem('item64', false);
                }
            }
            // position:fixed のためビューポート座標をそのまま使う
            x = e.clientX > 0 ? e.clientX : e.pageX;
            y = e.clientY > 0 ? e.clientY : e.pageY;
            var viewportHeight = window.innerHeight;
            if (y + mnu.offsetHeight > viewportHeight) {
                // make sure context menu is within viewport
                y = Math.max(0, viewportHeight - mnu.offsetHeight - 5);
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
            var mnu = document.getElementById('mx_contextmenu');
            var nameHolder = document.getElementById('nameHolder');
            if (mnu) {
                mnu.style.left = x + 'px'; //offset menu to the left if rtl is selected
                mnu.style.top = y + 'px';
            }
            if (nameHolder) nameHolder.textContent = selectedObjectName;

            if (mnu) mnu.style.visibility = 'visible';
            _rc = 1;
            setTimeout("_rc = 0;", 100);
        }

        // 子の行の入れ物(#c<id>)を高さ 0 との間で短く広げ縮めする。
        // 終わったら height / opacity / overflow を外し、今までと同じ素の状態に戻す。
        var TREE_SLIDE_MS = 180;
        function slideTreeBox(box, open, fromEmpty) {
            var reduce = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            // 途中から向きを変えるので、動いている最中の値を先に測ってから止める
            var visible = box.style.display === 'block';
            // fromEmpty: 取得したばかりの行を、高さ 0 から広げる
            var from = visible && !fromEmpty ? box.getBoundingClientRect().height : 0;
            var fromOpacity = visible && !fromEmpty ? parseFloat(getComputedStyle(box).opacity) : 0;
            if (box.slideCancel) box.slideCancel();

            function reset() {
                box.classList.remove('treeSlide');
                box.style.height = '';
                box.style.opacity = '';
                box.style.overflow = '';
                delete box.slideCancel;
            }
            function finish() {
                if (box.slideCancel) box.slideCancel();
                if (!open) box.style.display = 'none';
                delete box.dataset.closing;
            }

            if (open) {
                delete box.dataset.closing;
                box.style.display = 'block';
            } else {
                box.dataset.closing = '1';
            }

            var to = open ? box.scrollHeight : 0;
            if (reduce || (from === 0 && to === 0)) {
                finish();
                return;
            }

            var timer = null;
            function onEnd(e) {
                if (e.target === box && e.propertyName === 'height') finish();
            }
            box.slideCancel = function () {
                clearTimeout(timer);
                box.removeEventListener('transitionend', onEnd);
                reset();
            };

            box.style.overflow = 'hidden';
            box.style.height = from + 'px';
            box.style.opacity = fromOpacity;
            void box.offsetHeight; // 開始の状態を確定させてから目的の値へ
            box.classList.add('treeSlide');
            box.style.height = to + 'px';
            box.style.opacity = open ? 1 : 0;
            box.addEventListener('transitionend', onEnd);
            // transitionend が来なかったときの保険
            timer = setTimeout(finish, TREE_SLIDE_MS + 120);
        }

        function toggleNode(node, indent, id, expandAll, privatenode) {
            privatenode = (!privatenode || privatenode == '0') ? '0' : '1';
            var box = document.getElementById('c' + id);
            var rpcNodeText;

            var signImg = document.getElementById('s' + id);
            var folderImg = document.getElementById('f' + id);

            if (box.style.display === 'block' && box.dataset.closing !== '1') {
                // collapse
                signImg.src = <?= js_literal(style('tree_plusnode')) ?>;
                openedArray[id] = 0;
                slideTreeBox(box, false);
            } else {
                // expand
                signImg.src = <?= js_literal(style('tree_minusnode')) ?>;

                rpcNodeText = box.innerHTML;
                openedArray[id] = 1;

                if (box.dataset.loading === '1') {
                    // 取得中に開き直したときは、取得を重ねず、応答を待つ
                    box.style.display = 'block';
                } else if (rpcNodeText == '') {
                    box.dataset.loading = '1';
                    var folderState = getFolderState();
                    var params = new URLSearchParams({
                        "a": "1",
                        "f": "nodes",
                        "indent": indent,
                        "parent": id,
                        "expandAll": expandAll + folderState
                    });
                    // 失敗時は従来どおり何もしない
                    Evo.fetch('index.php?' + params.toString())
                        .then(function (response) {
                            return response.ok ? response.text() : null;
                        })
                        .then(function (html) {
                            delete box.dataset.loading;
                            if (html === null) return;
                            // 取得中に別の操作があっても、この行へ差し込む
                            rpcLoadData(html, box);
                            // 取得中に閉じられていたら、閉じたままにする
                            if (openedArray[id] != 1) {
                                box.style.display = 'none';
                                return;
                            }
                            slideTreeBox(box, true, true);
                        })
                        .catch(function () { delete box.dataset.loading; });
                    box.style.display = 'block';
                } else {
                    slideTreeBox(box, true);
                }
            }

            Evo.fetch(
                'index.php?a=1&f=nodes&savestateonly=1' + getFolderState()
            ).catch(function () {});
        }

        function emptyTrash() {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_empty_trash'))) ?>) == true) {
                EvoCsrf.post("index.php?a=64");
            }
        }

        currSorterState = "none";

        function treeAction(id, name) {
            if (tree.ca == "move") {
                try {
                    main.setMoveValue(id, name);
                } catch (oException) {
                    alert(<?= js_literal(stripcslashes(lang('unable_set_parent'))) ?>);
                }
            }
            if (tree.ca == "open" || tree.ca == "docinfo" || tree.ca == "doclist") {
                if (id == 0) {
                    // do nothing?
                    EvoShell.navigate("index.php?a=120");
                } else if (tree.ca == "docinfo") {
                    EvoShell.navigate("index.php?a=3&id=" + id);
                } else if (tree.ca == "doclist") {
                    EvoShell.navigate("index.php?a=120&id=" + id);
                } else {
                    EvoShell.navigate("index.php?a=27&id=" + id);
                }
            }
            if (tree.ca == "parent") {
                try {
                    main.setParent(id, name);
                } catch (oException) {
                    alert(<?= js_literal(stripcslashes(lang('unable_set_parent'))) ?>);
                }
            }
            if (tree.ca == "link") {
                try {
                    main.setLink(id);
                } catch (oException) {
                    alert(<?= js_literal(stripcslashes(lang('unable_set_link'))) ?>);
                }
            }
        }

        // show state of recycle bin
        function showBinFull() {
            var a = document.getElementById('treeEmptyTrash');
            var title = <?= js_literal(stripcslashes(lang('empty_recycle_bin'))) ?>;
            if (a) {
                if (!a.setAttribute) a.title = title;
                else a.setAttribute('title', title);
                a.innerHTML = <?= js_literal(style('empty_recycle_bin')) ?>;
                a.className = 'treeButton';
                a.onclick = emptyTrash;
            }
        }

        function showBinEmpty() {
            var a = document.getElementById('treeEmptyTrash');
            var title = <?= js_literal(stripcslashes(lang('empty_recycle_bin_empty'))) ?>;
            if (a) {
                if (!a.setAttribute) a.title = title;
                else a.setAttribute('title', title);
                a.innerHTML = <?= js_literal(style('empty_recycle_bin_empty')) ?>;
                a.className = 'treeButtonDisabled';
                a.onclick = '';
            }
        }

    </script>
    <script src="<?= manager_asset('media/script/tree.js') ?>"></script>
<aside id="treePane">
    <script>
        // フレーム時代のbody onclick相当。コンテキストメニューを閉じる
        if (!window.__treeHideMenuBound) {
            window.__treeHideMenuBound = true;
            document.addEventListener('click', function () { hideMenu(); });
        }
    </script>
<?php
// invoke OnTreePrerender event
$evtOut = evo()->invokeEvent('OnManagerTreeInit', $esc_request);
if (is_array($evtOut)) {
    echo implode("\n", $evtOut);
}
?>
<div class="treeframebody">
    <div id="treeSplitter"></div>

    <div class="tree-toolbar">
        <table id="treeMenu" width="100%" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <table cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td><a href="#" class="treeButton" id="treeExpand" onclick="expandTree();"
                                    title="<?= lang('expand_tree') ?>"><?= style('expand_tree') ?></a>
                            </td>
                            <td><a href="#" class="treeButton" id="treeCollapse" onclick="collapseTree();"
                                    title="<?= lang('collapse_tree') ?>"><?= style('collapse_tree') ?></a>
                            </td>
                            <?php if (manager()->canCreateDocumentUnder(0)) { ?>
                                <td><a href="#" class="treeButton" id="treeNewResource"
                                        onclick="EvoShell.navigate('index.php?a=4');return false;"
                                        title="<?= lang('add_resource') ?>"><?= style('add_doc_tree') ?></a>
                                </td>
                                <td><a href="#" class="treeButton" id="treeNewWeblink"
                                        onclick="EvoShell.navigate('index.php?a=72');return false;"
                                        title="<?= lang('add_weblink') ?>"><?= style('add_weblink_tree') ?></a>
                                </td>
                            <?php } ?>
                            <td><a href="#" class="treeButton" id="treeRefresh" onclick="top.mainMenu.reloadtree();"
                                    title="<?= lang('refresh_tree') ?>"><?= style('refresh_tree') ?></a>
                            </td>
                            <td><a href="#" class="treeButton" id="treeSort" onclick="showSorter(event);return false;"
                                    title="<?= lang('sort_tree') ?>"><?= style('sort_tree') ?></a></td>
                            <?php if (evo()->hasPermission('empty_trash')) { ?>
                                <td><a href="#" id="treeEmptyTrash" class="treeButtonDisabled"
                                        title="<?= lang('empty_recycle_bin_empty') ?>"><?= style('empty_recycle_bin_empty') ?></a>
                                </td>
                            <?php } ?>
                        </tr>
                    </table>
                </td>
                <td align="right">
                    <table cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td><a href="#" class="treeButton" id="treeHide" onclick="top.mainMenu.hideTreeFrame();"
                                    title="<?= lang('hide_tree') ?>"><?= style('hide_tree') ?></a></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div id="floater">
            <button
                type="button"
                class="tree-sorter-close"
                onclick="hideSorter();return false;"
                aria-label="<?= htmlspecialchars(lang('close'), ENT_QUOTES, $modx_manager_charset) ?>"
                title="<?= htmlspecialchars(lang('close'), ENT_QUOTES, $modx_manager_charset) ?>"
            >×</button>
            <form name="sortFrm" id="sortFrm" action="menu.php">
                <table style="width:100%;border:none;padding:0;margin:0">
                    <tr>
                        <td style="padding-left: 10px;padding-top: 1px;" colspan="2">
                            <select name="sortby" style="font-size: 12px;">
                                <option
                                    value="isfolder"
                                    <?= isSelectedTreeSortby('isfolder') ?>
                                ><?= lang('folder') ?></option>
                                <option
                                    value="pagetitle"
                                    <?= isSelectedTreeSortby('pagetitle') ?>
                                ><?= lang('pagetitle') ?></option>
                                <option
                                    value="id"
                                    <?= isSelectedTreeSortby('id') ?>
                                ><?= lang('id') ?></option>
                                <option
                                    value="menuindex"
                                    <?= isSelectedTreeSortby('menuindex') ?>
                                ><?= lang('resource_opt_menu_index') ?></option>
                                <option
                                    value="createdon"
                                    <?= isSelectedTreeSortby('createdon') ?>
                                ><?= lang('createdon') ?></option>
                                <option
                                    value="editedon"
                                    <?= isSelectedTreeSortby('editedon') ?>
                                ><?= lang('editedon') ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td width="99%" style="padding-left: 10px;padding-top: 1px;">
                            <select name="sortdir" style="font-size: 12px;">
                                <option
                                    value="DESC"
                                    <?= isSelectedTreeSortDir('DESC') ?>
                                ><?= lang('sort_desc') ?></option>
                                <option
                                    value="ASC"
                                    <?= isSelectedTreeSortDir('ASC') ?>
                                ><?= lang('sort_asc') ?></option>
                            </select>
                            <input type="hidden" name="dt" value="<?= hsc(request_strvar('dt')) ?>"/>
                        </td>
                        <td width="1%"><a
                            href="#"
                            class="treeButton"
                            id="button7"
                            style="text-align:right"
                            onclick="updateTree();hideSorter();"
                            title="<?= lang('sort_tree') ?>"
                        ><?= lang('sort_tree') ?></a>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>

    <div id="treeHolder">
        <?php
        // invoke OnTreeRender event
        $evtOut = evo()->invokeEvent('OnManagerTreePrerender', $esc_request);
        if (is_array($evtOut)) {
            echo implode("\n", $evtOut);
        }
        ?>
        <div><?= style('tree_showtree') ?>&nbsp;<span class="rootNode"
                                                                onclick="treeAction(0, <?= hsc(js_literal(config('site_name'))) ?>);"><b><?= hsc(config('site_name')) ?></b></span>
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
            document.querySelectorAll('.treeNodeSelected').forEach(function (el) {
                el.classList.remove('treeNodeSelected');
            });
            var node = document.getElementById('node' + doc_id);
            var span = node ? node.querySelector('span') : null;
            if (span) span.className = 'treeNodeSelected';
        }

        // Context menu stuff
        function menuHandler(action) {
            switch (action) {
                case '3' : // view
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=3&id=" + itemToChange);
                    break;
                case '27' : // edit
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=27&id=" + itemToChange);
                    break;
                case 'createDraft' : // createt draft
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=132&id=" + itemToChange);
                    break
                case 'editDraft' : // edit draft
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=131&id=" + itemToChange);
                    break
                case '4' : // new Resource
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=4&pid=" + itemToChange);
                    break;
                case '51' : // move
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=51&id=" + itemToChange);
                    break;
                case '72' : // new Weblink
                    setActiveFromContextMenu(itemToChange);
                    EvoShell.navigate("index.php?a=72&pid=" + itemToChange);
                    break;
                case '94' : // duplicate
                    if (confirm(<?= js_literal(stripcslashes(lang('confirm_resource_duplicate'))) ?>) == true) {
                        setActiveFromContextMenu(itemToChange);
                        EvoCsrf.post("index.php?a=94&id=" + itemToChange);
                    }
                    break;
                case '6' : // delete
                    if (selectedObjectDeleted == 0) {
                        if (confirm("'" + selectedObjectName + "'\n\n" + <?= js_literal(stripcslashes(lang('confirm_delete_resource'))) ?>) == true) {
                            EvoCsrf.post("index.php?a=6&id=" + itemToChange);
                        }
                    } else {
                        alert("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('already_deleted'))) ?>);
                    }
                    break;
                case '63' : // undelete
                    if (selectedObjectDeleted == 0) {
                        alert("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('not_deleted'))) ?>);
                    } else {
                        if (confirm("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('confirm_undelete'))) ?>) == true) {
                            EvoCsrf.post("index.php?a=63&id=" + itemToChange);
                        }
                    }
                    break;
                case '64' : // delete
                    if (selectedObjectDeleted == 1) {
                        if (confirm("'" + selectedObjectName + "'\n\n" + <?= js_literal(stripcslashes(lang('confirm_delete_resource'))) ?>) == true) {
                            EvoCsrf.post("index.php?a=64&id=" + itemToChange);
                        }
                    } else {
                        alert("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('already_deleted'))) ?>);
                    }
                    break;
                case '61' : // publish
                    if (confirm("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('confirm_publish'))) ?>) == true) {
                        setActiveFromContextMenu(itemToChange);
                        EvoCsrf.post("index.php?a=61&id=" + itemToChange);
                    }
                    break;
                case '62' : // unpublish
                    if (itemToChange != <?= (int) config('site_start') ?>) {
                        if (confirm("'" + selectedObjectName + "' " + <?= js_literal(stripcslashes(lang('confirm_unpublish'))) ?>) == true) {
                            setActiveFromContextMenu(itemToChange);
                            EvoCsrf.post("index.php?a=62&id=" + itemToChange);
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
                    EvoShell.navigate("index.php?a=120&id=" + itemToChange);
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
<script>
    // 旧フレーム参照(top.tree.X)互換のためツリー操作関数を公開する
    window.tree = window.tree || {};
    Object.assign(window.tree, {
        saveFolderState: saveFolderState,
        restoreTree: restoreTree,
        resizeTree: resizeTree,
        expandTree: expandTree,
        collapseTree: collapseTree,
        updateTree: updateTree,
        reload: function () { EvoShell.reloadTree(); }
    });
    // ノードを読み込む。全体の読み込みでも、シェルの部分再取得（#treePane内のscriptだけが実行される）でも、ここで1回だけ行う
    restoreTree();
</script>
</aside>
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
    if (!manager()->can('document.edit')) {
        return '';
    }
    $ph['action'] = '27';
    $ph['img'] = style('icons_edit_document');
    $ph['text'] = lang('edit_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemCreateDraft()
{
    if (!config('enable_draft')) {
        return '';
    }

    $ph['action'] = 'createDraft';
    $ph['img'] = style('icons_new_document');
    $ph['text'] = lang("create_draft");
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemEditDraft()
{
    if (!config('enable_draft')) {
        return '';
    }

    $ph['action'] = 'editDraft';
    $ph['img'] = style('icons_edit_document');
    $ph['text'] = lang("edit_draft");
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDocList()
{
    if (!evo()->hasPermission('view_document')) {
        return '';
    }
    $ph['action'] = '120';
    $ph['img'] = style('icons_table');
    $ph['text'] = lang('view_child_resources_in_container');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemNewDoc()
{
    if (!manager()->can('document.create')) {
        return '';
    }
    $ph['action'] = '4';
    $ph['img'] = style('icons_new_document');
    $ph['text'] = lang('create_resource_here');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemMoveDoc()
{
    if (!manager()->can('document.move')) {
        return '';
    }
    $ph['action'] = '51';
    $ph['img'] = style('icons_move_document');
    $ph['text'] = lang('move_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDuplicateDoc()
{
    if (!manager()->can('document.duplicate')) {
        return '';
    }
    $ph['action'] = '94';
    $ph['img'] = style('icons_resource_duplicate');
    $ph['text'] = lang('resource_duplicate');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator1()
{
    if (
        manager()->can('document.edit')
        || manager()->can('document.create')
        || manager()->can('document.move')
        || manager()->can('document.duplicate')
    ) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemPubDoc()
{
    if (!manager()->can('document.publish')) {
        return '';
    }
    $ph['action'] = '61';
    $ph['img'] = style('icons_publish_document');
    $ph['text'] = lang('publish_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemUnPubDoc()
{
    if (!manager()->can('document.publish')) {
        return '';
    }
    $ph['action'] = '62';
    $ph['img'] = style('icons_unpublish_resource');
    $ph['text'] = lang('unpublish_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDelDoc()
{
    if (!manager()->can('document.delete')) {
        return '';
    }
    $ph['action'] = '6';
    $ph['img'] = style('icons_delete');
    $ph['text'] = lang('delete_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemUndelDoc()
{
    if (!manager()->can('document.delete')) {
        return '';
    }
    $ph['action'] = '63';
    $ph['img'] = style('icons_undelete_resource');
    $ph['text'] = lang('undelete_resource');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemDelDocComplete()
{
    if (!evo()->hasPermission('empty_trash')) {
        return '';
    }
    $ph['action'] = '64';
    $ph['img'] = style('icons_delete_complete');
    $ph['text'] = lang('delete_resource_complete');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator2()
{
    if (manager()->can('document.publish') || manager()->can('document.delete')) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemWebLink()
{
    if (!manager()->can('document.create')) {
        return '';
    }
    $ph['action'] = '72';
    $ph['img'] = style('icons_weblink');
    $ph['text'] = lang('create_weblink_here');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemSeperator3()
{
    if (manager()->can('document.create')) {
        return '<div class="seperator"></div>';
    }

    return '';
}

function itemDocInfo()
{
    if (!evo()->hasPermission('view_document')) {
        return '';
    }
    $ph['action'] = '3';
    $ph['img'] = style('icons_information');
    $ph['text'] = lang('resource_overview');
    return evo()->parseText(tplMenuItem(), $ph);
}

function itemViewPage()
{
    $ph['action'] = 'pv';
    $ph['img'] = style('icons_information');
    $ph['text'] = lang('preview_resource');
    return evo()->parseText(tplMenuItem(), $ph);
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
