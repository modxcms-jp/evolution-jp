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
        // 本文の文書がこの中にあるとして選んでいた行なら、読み込んだあとで選び直す
        var wasSelected = !!moreNode.querySelector('.treeNodeSelected');
        moreNode.insertAdjacentHTML('beforebegin', response);
        moreNode.parentNode.removeChild(moreNode);
        if (wasSelected) {
            selectPaneDocument();
        }
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
        selectPaneDocument();
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

// セッションの開閉状態でノードを読み込む（tree.php の部品の初期化で呼ぶ）。
// 保存のあとの読み直しもここを通るので、本文で開いている文書を選び直す
function restoreTree() {
    rpcNode = document.getElementById('treeRoot');
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "2"
    }, function (response) {
        rpcLoadData(response);
        revealPaneDocument();
    });
}

// 本文で開いている文書（#mainPane の [data-tree-document]。親は根から順に [data-tree-parents]）を返す
function paneDocument() {
    var pane = document.getElementById('mainPane');
    var el = pane ? pane.querySelector('[data-tree-document]') : null;
    if (!el) {
        return null;
    }
    return {
        id: parseInt(el.getAttribute('data-tree-document'), 10),
        parents: (el.getAttribute('data-tree-parents') || '').split(',').map(function (v) {
            return parseInt(v, 10);
        }).filter(function (v) {
            return v > 0;
        })
    };
}

// 本文で開いている文書をツリーで選ぶ。閉じている親のフォルダは根の側から順に開く（開閉状態も保存される）。
// リソース一覧など、ツリーの外から編集画面を開いたときのため。ツリーから開いたときは、もう見えているので選ぶだけになる。
// 親がツリーに無い（権限で見えない・「残り N 件を表示」で省略されている）ときは、そこで止める
function revealPaneDocument() {
    var root = document.getElementById('treeRoot');
    var doc = paneDocument();
    if (!doc || !root || !root.loaded) {
        return;
    }
    // 親を開くのを待つ間に別の画面へ移ったら、移った先の選択を上書きしないよう打ち切る
    function stillCurrent() {
        var cur = paneDocument();
        return cur !== null && cur.id === doc.id;
    }
    doc.parents.reduce(function (chain, pid) {
        return chain.then(function (ok) {
            var box = document.getElementById('c' + pid);
            var sign = document.getElementById('s' + pid);
            if (!ok || !stillCurrent() || !box || !sign || box.dataset.loading === '1') {
                return false;
            }
            if (box.style.display === 'block' && box.dataset.closing !== '1') {
                return true;
            }
            return toggleNode(sign, treeNodeIndent(box), pid, 0, 0);
        });
    }, Promise.resolve(true)).then(function () {
        if (stillCurrent()) {
            selectPaneDocument();
            return;
        }
        // 移った先の文書の処理は、ここで読み込んでいた親（読み込み中）で止まっていることがある。
        // 読み込みが済んだので、移った先の文書についてやり直す（文書の画面でなければ何もしない）
        revealPaneDocument();
    });
}

// 親を開かずに、見えていれば選ぶ（並べ替え・すべて展開など、ツリーを描き直したとき）
// 文書が「残り N 件を表示」で省かれているときは、開いている直接の親の中のその行を選んだ見た目にして、
// この中にあることだけを示す（続きを読み込むかは利用者が決める。数千件のフォルダでツリーを重くしない）
function selectPaneDocument() {
    var doc = paneDocument();
    if (!doc || selectTreeNode(doc.id)) {
        return;
    }
    var pid = doc.parents[doc.parents.length - 1];
    var box = pid ? document.getElementById('c' + pid) : null;
    if (!box || box.style.display !== 'block') {
        return;
    }
    var more = box.querySelector(':scope > .moreNode');
    var label = more ? more.querySelector('.emptyNode') : null;
    if (!label) {
        return;
    }
    clearTreeSelection();
    label.classList.add('treeNodeSelected');
    scrollTreeRowIntoView(more);
}

// フォルダの子を取るときの indent（nodes.php の getNodes() が根を 1、その子のフォルダを 2 として数える）
function treeNodeIndent(box) {
    var indent = 2;
    for (var el = box.parentNode; el && el.id !== 'treeRoot'; el = el.parentNode) {
        if (el.classList && el.classList.contains('tree-children')) {
            indent++;
        }
    }
    return indent;
}

// 文書のノードを選んだ見た目にし、ツリーの中で見えていなければスクロールして見せる。ノードが無ければ false
function selectTreeNode(id) {
    var node = document.getElementById('node' + id);
    var row = node ? node.querySelector(':scope > .tree-row') : null;
    var span = row ? row.querySelector(':scope > span') : null;
    if (!span) {
        return false;
    }
    setSelected(span);
    scrollTreeRowIntoView(row);
    return true;
}

// ツリーの行が見えていなければ、縦にスクロールして見せる。
// scrollIntoView() は使わない（上端に貼り付いたボタンの帯 .tree-toolbar の下に隠れる・横のスクロールまで動く）
function scrollTreeRowIntoView(row) {
    var pane = document.getElementById('treePane');
    if (!pane) {
        return;
    }
    var bar = pane.querySelector('.tree-toolbar');
    var paneRect = pane.getBoundingClientRect();
    var rowRect = row.getBoundingClientRect();
    var top = paneRect.top + (bar ? bar.offsetHeight : 0);
    if (rowRect.top < top) {
        pane.scrollTop -= top - rowRect.top;
    } else if (rowRect.bottom > paneRect.bottom) {
        pane.scrollTop += rowRect.bottom - paneRect.bottom;
    }
}

// 選んだ見た目を外す。「残り N 件を表示」の行（emptyNode）は、自分の見た目を残す
function clearTreeSelection() {
    document.querySelectorAll('span.treeNodeSelected').forEach(function (el) {
        el.classList.remove('treeNodeSelected');
        if (!el.classList.contains('emptyNode')) {
            el.classList.add('treeNode');
        }
    });
}

function setSelected(elSel) {
    clearTreeSelection();
    elSel.className = "treeNodeSelected";
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
    }, function (response) {
        rpcLoadData(response);
        selectPaneDocument();
    });
}

function toggleDeletedNodes() {
    var button = document.getElementById('treeToggleDeleted');
    if (!button) {
        return;
    }
    var show = button.getAttribute('aria-pressed') !== 'true';
    button.setAttribute('aria-pressed', show ? 'true' : 'false');
    button.classList.toggle('is-active', show);

    rpcNode = document.getElementById('treeRoot');
    loadTreeRequest({
        "a": "1",
        "f": "nodes",
        "indent": "1",
        "parent": "0",
        "expandAll": "2",
        "tree_show_deleted": show ? "1" : "0"
    }, function (response) {
        rpcLoadData(response);
        selectPaneDocument();
    });
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

// シェルで画面を移ったとき・ページ全体を読んだときに、本文の文書をツリーで選ぶ。
// ツリーの部品を読み直すとこのファイルも読み直されるので、登録は 1 回だけにする
if (!window.treeRevealBound) {
    window.treeRevealBound = true;
    document.addEventListener('evoshell:load', function () {
        revealPaneDocument();
    });
    document.addEventListener('DOMContentLoaded', function () {
        revealPaneDocument();
    });
}
