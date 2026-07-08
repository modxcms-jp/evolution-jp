// return window dimensions in array
function getWindowDimension() {
    return {
        width: window.innerWidth,
        height: window.innerHeight
    };
}

function resizeTree() {

    var tree = document.getElementById('treeHolder');
    if (!tree) return;

    // シェルレイアウトではツリー領域(#treePane)のサイズをCSSに任せる
    var pane = document.getElementById('treePane');
    if (pane) {
        tree.style.width = '';
        tree.style.height = '';
        tree.style.overflow = '';
        return;
    }

    // 旧フルページ表示(a=1&f=tree直アクセス)時はウィンドウ基準で計算する
    var win = getWindowDimension();
    tree.style.width = (win['width'] - 20) + 'px';
    tree.style.height = (win['height'] - tree.offsetTop - 6) + 'px';
    tree.style.overflow = 'auto';
}

function getScrollY() {
    return window.pageYOffset;
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

function loadTreeRequest(params, callback) {
    fetch(buildTreeRequestUrl(params), { credentials: 'same-origin' })
        .then(function (response) {
            return response.text();
        })
        .then(callback);
}

function sendTreeRequest(url) {
    fetch(url, { credentials: 'same-origin' });
}

function rpcLoadData(response) {
    if (rpcNode == null) {
        return;
    }
    rpcNode.innerHTML = response;
    rpcNode.style.display = 'block';
    rpcNode.loaded = true;
    // シェルではメニューと同一document。フレーム時代のtop.mainMenu.documentは不要
    var elm = document.getElementById('buildText');
    if (elm) {
        elm.innerHTML = '';
        elm.style.display = 'none';
    }
    // check if bin is full
    if (rpcNode.id === 'treeRoot') {
        if (document.getElementById('binFull')) {
            showBinFull();
        } else {
            showBinEmpty();
        }
    }

    // check if our payload contains the login form :)
    if (document.getElementById('mx_loginbox')) {
        // yep! the seession has timed out
        rpcNode.innerHTML = '';
        top.location = 'index.php';
    }
}

function expandTree() {
    rpcNode = document.getElementById('treeRoot');
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "1"
    }, rpcLoadData);
}

function collapseTree() {
    rpcNode = document.getElementById('treeRoot');
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "0"
    }, rpcLoadData);
}

// new function used in body onload
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
