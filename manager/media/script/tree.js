// ツリー領域(#treePane)のサイズはシェルのCSSが決める。tree.resizeTree() を呼ぶ外部のコードのために名前だけ残す
function resizeTree() {
}

function hideMenu() {
    if (_rc) return false;
    var contextMenu = document.getElementById('mx_contextmenu');
    if (contextMenu) {
        contextMenu.style.visibility = 'hidden';
    }
}

function hideSorter() {
    currSorterState = "none";
    var floater = document.getElementById('floater');
    if (floater) {
        floater.style.display = currSorterState;
    }
}

function buildTreeRequestUrl(params) {
    var query = Object.keys(params).map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');

    return 'index.php?' + query;
}

function loadTreeRequest(params, callback, onError) {
    fetch(buildTreeRequestUrl(params), { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('tree request failed: HTTP ' + response.status);
            }
            return response.text();
        })
        .then(callback)
        .catch(function (error) {
            console.error(error);
            if (onError) {
                onError(error);
            }
        });
}

function sendTreeRequest(url) {
    fetch(url, { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('tree request failed: HTTP ' + response.status);
            }
        })
        .catch(function (error) {
            console.error(error);
        });
}

// 件数超過で省略したリソースの続きを読み込み、「残り N 件を表示」ノードと置き換える
function loadMoreNodes(el, indent, parent, offset) {
    var moreNode = el.parentNode;
    if (moreNode.loading) {
        return;
    }
    moreNode.loading = true;
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": indent,
        "parent": parent,
        "offset": offset
    }, function (response) {
        if (response.indexOf('mx_loginbox') !== -1) {
            // セッション切れ
            top.location = 'index.php';
            return;
        }
        moreNode.insertAdjacentHTML('beforebegin', response);
        moreNode.parentNode.removeChild(moreNode);
    }, function () {
        // 失敗したらもう一度押せるようにする
        moreNode.loading = false;
    });
}

// node: 差し込み先。省略時は直前に指定された rpcNode（子の取得中に別の読み込みが重なっても取り違えないよう、子の取得は node を渡す）
function rpcLoadData(response, node) {
    var target = node || rpcNode;
    if (target == null) {
        return;
    }
    target.innerHTML = response;
    target.style.display = 'block';
    target.loaded = true;
    // シェルではメニューと同一document。フレーム時代のtop.mainMenu.documentは不要
    var elm = document.getElementById('buildText');
    if (elm) {
        elm.innerHTML = '';
        elm.style.display = 'none';
    }
    // check if bin is full
    if (target.id === 'treeRoot') {
        if (document.getElementById('binFull')) {
            showBinFull();
        } else {
            showBinEmpty();
        }
    }

    // check if our payload contains the login form :)
    if (document.getElementById('mx_loginbox')) {
        // yep! the seession has timed out
        target.innerHTML = '';
        top.location = 'index.php';
    }
}

// すべて展開・折りたたむ: 描き直したあと、開閉状態（openedArray）をそろえてセッションへ保存する。
// 保存しないと、次に開閉状態を送るとき（編集画面を開くなど）に古い状態で上書きされ、ツリーを読み直すと元に戻る
function expandTree() {
    var root = document.getElementById('treeRoot');
    rpcNode = root;
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "1"
    }, function (response) {
        rpcLoadData(response, root);
        var state = root ? root.querySelector('[data-tree-opened]') : null;
        if (!state) {
            return;
        }
        resetFolderState();
        state.getAttribute('data-tree-opened').split(',').forEach(function (id) {
            if (id) {
                openedArray[parseInt(id, 10)] = 1;
            }
        });
        state.parentNode.removeChild(state);
        saveFolderState();
    });
}

function collapseTree() {
    var root = document.getElementById('treeRoot');
    rpcNode = root;
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "0"
    }, function (response) {
        rpcLoadData(response, root);
        if (response.indexOf('mx_loginbox') !== -1) {
            // セッション切れ（rpcLoadData がログイン画面へ移す）
            return;
        }
        resetFolderState();
        saveFolderState();
    });
}

function resetFolderState() {
    for (var key in openedArray) {
        openedArray[key] = 0;
    }
}

// セッションの開閉状態でノードを読み込む（tree.php の部品の初期化で呼ぶ）
function restoreTree() {
    rpcNode = document.getElementById('treeRoot');
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "2"
    }, rpcLoadData);
}

function setSelected(elSel) {
    var all = document.getElementsByTagName("SPAN");
    var l = all.length;

    for (var i = 0; i < l; i++) {
        el = all[i];
        cn = el.className;
        if (cn === "treeNodeSelected") {
            el.className = "treeNode";
        }
    }
    elSel.className = "treeNodeSelected";
}

function setHoverClass(el, dir) {
    if (el.className !== "treeNodeSelected") {
        if (dir == 1) {
            el.className = "treeNodeHover";
        } else {
            el.className = "treeNode";
        }
    }
}

// set Context Node State
function setCNS(n, b) {
    if (b == 1) {
        n.style.backgroundColor = "beige";
    } else {
        n.style.backgroundColor = "";
    }
}

function updateTree() {
    rpcNode = document.getElementById('treeRoot');
    var dt = document.sortFrm.dt.value;
    var t_sortby = document.sortFrm.sortby.value;
    var t_sortdir = document.sortFrm.sortdir.value;

    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "2",
        "dt": dt,
        "tree_sortby": t_sortby,
        "tree_sortdir": t_sortdir
    }, rpcLoadData);
}

//Raymond: added getFolderState,saveFolderState
function getFolderState() {
    if (openedArray == [0]) {
        return "&opened=";
    }
    oarray = "&opened=";
    for (key in openedArray) {
        if (openedArray[key] == 1) {
            oarray += key + "|";
        }
    }
    return oarray.replace(/\|$/, '');
}

function saveFolderState() {
    var folderState = getFolderState();
    var url = 'index.php?a=1&f=nodes&savestateonly=1' + folderState;
    sendTreeRequest(url);
}

function showSorter(event) {
    if (event && typeof event.stopPropagation === 'function') {
        event.stopPropagation();
    }

    if (currSorterState === "none") {
        currSorterState = "block";
        document.getElementById('floater').style.display = currSorterState;
    } else {
        hideSorter();
    }
}

function initTreeSorterHandlers() {
    document.addEventListener('click', function (event) {
        var floater = document.getElementById('floater');
        if (!floater || currSorterState === "none") {
            return;
        }
        if (floater.contains(event.target)) {
            return;
        }
        hideSorter();
    });

    var floater = document.getElementById('floater');
    if (floater) {
        floater.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTreeSorterHandlers, { once: true });
} else {
    initTreeSorterHandlers();
}
