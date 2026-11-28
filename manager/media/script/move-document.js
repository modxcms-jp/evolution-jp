// 文書の移動の画面（move_document.dynamic.php）の操作。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
// 画面ごとの処理は EvoMoveDocument.init() が行い、文言は form の data 属性から読む。
if (!window.EvoMoveDocument) {
    window.EvoMoveDocument = {};
    (function () {
        function form() {
            return document.querySelector('[data-move-form]');
        }

        function showParent(pId, pName) {
            const f = form();
            f.elements.new_parent.value = pId;
            const label = document.getElementById('parentName');
            const id = document.createElement('b');
            id.textContent = pId;
            label.textContent = f.dataset.labelNewParent + ': ';
            label.append(id, ' (' + pName + ')');
        }

        // 選んだ移動先が、移動するリソース（複数移動ではそのどれか）自身か子孫なら止める。
        // ツリーのノードは親を独自の属性 p に持つので getAttribute で読む
        function checkParentChildRelation(pId) {
            const f = form();
            const ids = f.elements.id.value.split(',');
            let pn = document.getElementById('node' + pId);
            if (!pn) {
                return false;
            }
            if (ids.includes(pn.id.substr(4))) {
                EvoDialog.alert(f.dataset.illegalParentSelf, '', null, {icon: 'warning'});
                return false;
            }
            while (pn.getAttribute('p') > 0) {
                pn = document.getElementById('node' + pn.getAttribute('p'));
                if (!pn) {
                    break;
                }
                if (ids.includes(pn.id.substr(4))) {
                    EvoDialog.alert(f.dataset.illegalParentChild, '', null, {icon: 'warning'});
                    return false;
                }
            }
            return true;
        }

        // ツリーのクリックから呼ばれる（tree.php の treeAction が main.setMoveValue を呼ぶ）
        function setMoveValue(pId, pName) {
            const f = form();
            if (!f) {
                return;
            }
            if (pId === 0 && f.dataset.rootAllowed !== '1') {
                EvoDialog.alert(f.dataset.parentDenied, '', null, {icon: 'warning'});
                return;
            }
            if (pId === 0 || checkParentChildRelation(pId)) {
                showParent(pId, pName);
            }
        }

        // ツリーで省略されたリソースも移動先にできるよう、タイトルか ID で検索して選ぶ。
        // 移動するリソース自身と子孫はダイアログ側で選べないようにしている
        function openParentPicker() {
            const f = form();
            EvoShell.openPicker(
                'index.php?a=135&id=' + encodeURIComponent(f.elements.id.value),
                function (picked) {
                    showParent(picked.id, picked.pagetitle);
                }
            );
        }

        // 画面を開くたびに呼ぶ。ツリーを移動先の選択に切り替える。
        // DocManager も同名の setMoveValue を window に置くので、戻ってきたときに設定し直す
        window.EvoMoveDocument.init = function () {
            window.setMoveValue = setMoveValue;
            top.mainMenu.defaultTreeFrame();
            parent.tree.ca = 'move';
        };

        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-move-action]');
            const f = form();
            if (!el || !f) {
                return;
            }
            e.preventDefault();
            switch (el.dataset.moveAction) {
                case 'save':
                    f.submit();
                    break;
                case 'cancel':
                    EvoShell.go(el.dataset.moveUrl, {discard: true});
                    break;
                case 'picker':
                    openParentPicker();
                    break;
            }
        });
    })();
}
