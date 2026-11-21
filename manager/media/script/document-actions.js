// 文書の操作（編集・移動・複製・削除）と、子リソースの一覧の行メニュー。
// document_data.static.php と resources_list.static.php が使う。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoDocumentActions) {
    window.EvoDocumentActions = {};
    (function () {
        // 一覧の行メニューの設定（resources_list.static.php が initChildMenu() で渡す）
        let menuConfig = null;
        let selectedId = 0;

        function documentUrl(a, id, suffix) {
            return 'index.php?a=' + a + '&id=' + id + (suffix || '');
        }

        // 確認の文言があれば EvoDialog で確かめ、OK のときだけ run を呼ぶ。文言が無ければそのまま呼ぶ
        function confirmThen(message, run) {
            if (!message) {
                run();
                return;
            }
            // 言語ファイルの文言は「\n\n」で始まるものがあるので、先頭の改行は落とす
            EvoDialog.confirm(message.replace(/^\n+/, ''), '', function (ok) {
                if (ok) {
                    run();
                }
            });
        }

        function menuElement() {
            return menuConfig ? document.getElementById(menuConfig.menuId) : null;
        }

        function hideMenu() {
            const menu = menuElement();
            if (menu) {
                menu.style.visibility = 'hidden';
            }
        }

        function showMenu(id, e) {
            const menu = menuElement();
            if (!menu) {
                return;
            }
            selectedId = id;
            // 右から左に書く言語では、メニューを左へずらす
            const offsetX = menuConfig.rtl ? -190 : 0;
            menu.style.left = (e.pageX + offsetX + 10) + 'px';
            menu.style.top = (e.pageY - 150) + 'px';
            menu.style.visibility = 'visible';
            // 同じクリックが document に届いて、すぐ閉じないようにする
            e.stopPropagation();
        }

        // 画面の上の操作ボタン（data-doc-action="edit|move|duplicate|delete"）
        const buttonActions = {
            edit: function (id) {
                EvoShell.go(documentUrl(27, id));
            },
            move: function (id) {
                EvoShell.go(documentUrl(51, id));
            },
            duplicate: function (id, message) {
                confirmThen(message, function () {
                    EvoCsrf.post(documentUrl(94, id));
                });
            },
            delete: function (id, message) {
                confirmThen(message, function () {
                    EvoCsrf.post(documentUrl(6, id));
                });
            }
        };

        // 戻り先（今のページ・並べ替え・検索語）を残して POST する操作。キーは action の番号、値は確認の文言の名前
        const postMenuActions = {
            94: 'duplicate',
            61: 'publish',
            62: 'unpublish',
            6: 'delete',
            63: 'undelete'
        };

        // 一覧の行メニューの項目（resources_list.static.php の getContextMenu() が onclick から呼ぶ）
        window.EvoDocumentActions.menu = function (a) {
            if (!menuConfig) {
                return;
            }
            const id = selectedId;
            if (postMenuActions[a]) {
                if (a === 62 && id === menuConfig.siteStart) {
                    EvoDialog.alert(menuConfig.siteStartMessage, '', null, {icon: 'warning'});
                    return;
                }
                confirmThen(menuConfig.confirm[postMenuActions[a]], function () {
                    EvoCsrf.post(documentUrl(a, id, menuConfig.returnQuery));
                });
                return;
            }
            switch (a) {
                case 27: // edit
                case 3:  // view
                    EvoShell.go(documentUrl(a, id));
                    break;
                case 51: // move
                    EvoShell.go(documentUrl(a, id, menuConfig.returnQuery));
                    break;
                case 4:  // new document
                case 72: // new weblink
                    EvoShell.go('index.php?a=' + a + '&pid=' + id);
                    break;
            }
        };

        // 表は画面の差し替えで消えるので、画面ごとに呼ばれて新しい表に登録する
        window.EvoDocumentActions.initChildMenu = function (config) {
            menuConfig = config;
            Evo.ready(function () {
                const table = document.querySelector('[data-table="children"]');
                if (!table || table.hasAttribute('data-row-menu-bound')) {
                    return;
                }
                table.setAttribute('data-row-menu-bound', '');
                table.addEventListener('click', function (e) {
                    const el = e.target.closest('[data-row-menu]');
                    // タイトルのクリックはリンクとして動かす。メニューはアイコンのクリックで開く
                    if (el && el.tagName === 'IMG') {
                        showMenu(Number(el.dataset.rowMenu), e);
                        e.preventDefault();
                    }
                });
                table.addEventListener('contextmenu', function (e) {
                    const el = e.target.closest('[data-row-menu]');
                    if (el) {
                        showMenu(Number(el.dataset.rowMenu), e);
                        e.preventDefault();
                    }
                });
            });
        };

        // 確認が要る送信ボタン（button[data-submit-confirm]）。確かめてから、同じボタンで送信し直す
        document.addEventListener('click', function (e) {
            const button = e.target.closest('button[data-submit-confirm]');
            if (!button || !button.form) {
                return;
            }
            e.preventDefault();
            confirmThen(button.dataset.submitConfirm, function () {
                button.form.requestSubmit(button);
            });
        });

        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-doc-action]');
            if (el && buttonActions[el.dataset.docAction]) {
                e.preventDefault();
                buttonActions[el.dataset.docAction](Number(el.dataset.docId), el.dataset.confirm || '');
                return;
            }
            hideMenu();
        });
    })();
}
