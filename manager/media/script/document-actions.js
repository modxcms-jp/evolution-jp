// 文書の操作（編集・移動・複製・削除・公開・非公開・削除の取り消し）の確認と、子リソースの一覧の行メニュー。
// header.inc.php が全画面で読み込む。ツリーの右クリックメニュー・投稿画面・リソースの画面・一覧の行メニュー・一覧の一括操作が、
// 確認とトップページなどの警告を operations の表から引く（入口ごとに確認の文言や if を持たない）。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoDocumentActions) {
    window.EvoDocumentActions = {};
    (function () {
        // header.inc.php が ex_docapi.php の actionConfig() の値を入れる。
        // ids: 設定名 => その設定のリソースの ID / lang: 言語キー => 文言
        window.EvoDocumentActions.config = {ids: {}, lang: {}};

        // 操作ごとの定義の表。
        //   confirm: 1 件の確認の文言のキー
        //   batch: 一括の確認の文言のキー（%s に選んだ件数を入れる。無い操作は一括に使わない）
        //   children: 子があるときに確認へ足す行のキー
        //   guards: 設定名 => 警告の文言のキー。その設定のリソースが対象に含まれるときは、確認の前に警告で止める
        const operations = {
            delete: {
                confirm: 'confirm_delete_resource',
                batch: 'confirm_batch_delete',
                children: 'confirm_delete_resource_children',
                guards: {
                    site_start: 'cannot_delete_site_start',
                    error_page: 'cannot_delete_error_page',
                    site_unavailable_page: 'cannot_delete_site_unavailable_page'
                }
            },
            publish: {confirm: 'confirm_publish', batch: 'confirm_batch_publish'},
            unpublish: {
                confirm: 'confirm_unpublish',
                batch: 'confirm_batch_unpublish',
                guards: {site_start: 'cannot_unpublish_site_start'}
            },
            duplicate: {confirm: 'confirm_resource_duplicate'},
            undelete: {confirm: 'confirm_undelete'}
        };

        // 一覧の行メニューの設定（resources_list.static.php が initChildMenu() で渡す）
        let menuConfig = null;
        let selectedId = 0;

        function documentUrl(a, id, suffix) {
            return 'index.php?a=' + a + '&id=' + id + (suffix || '');
        }

        function text(key) {
            // 言語ファイルの文言は「\n\n」で始まるものがあるので、先頭の改行は落とす
            return String(window.EvoDocumentActions.config.lang[key] || '').replace(/^\n+/, '');
        }

        // 操作 name を対象の ids（配列）へ行う前の確認。警告に当たれば警告で止め、確認で OK のときだけ run を呼ぶ。
        // opts: batch（一括の文言を使う）・hasChildren（対象に子のあるリソースがあるか。children の行を足す）・
        //   title（1 件のときの、確認の先頭に出すリソース名。利用者のデータなのでここでエスケープする）
        window.EvoDocumentActions.run = function (name, ids, run, opts) {
            const op = operations[name];
            const settingIds = window.EvoDocumentActions.config.ids;
            const batch = !!(opts && opts.batch);
            const hasChildren = !!(opts && opts.hasChildren);
            const title = opts && opts.title;
            for (let i = 0; i < ids.length; i++) {
                const setting = Object.keys(op.guards || {}).find(function (key) {
                    return settingIds[key] === ids[i];
                });
                if (setting) {
                    // 一括のときは、どのリソースが当たったか分かるように ID を添える
                    EvoDialog.alert((batch ? 'ID: ' + ids[i] + '\n' : '') + text(op.guards[setting]), '', null, {icon: 'warning'});
                    return;
                }
            }
            let message = batch ? text(op.batch).replace('%s', ids.length) : text(op.confirm);
            if (hasChildren && op.children) {
                message += '\n' + text(op.children);
            }
            if (title) {
                message = "'" + Evo.escapeHtml(title) + "'\n\n" + message;
            }
            EvoDialog.confirm(message, '', function (ok) {
                if (ok) {
                    run();
                }
            });
        };

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

        // 画面の上の操作ボタン（data-doc-action="edit|move|duplicate|delete"）。
        // 確認が要るもの（duplicate・delete）は operations の表を引く。delete は data-has-children で子の有無を受ける
        const buttonActions = {
            edit: function (id) {
                EvoShell.go(documentUrl(27, id));
            },
            move: function (id) {
                EvoShell.go(documentUrl(51, id));
            },
            duplicate: function (id) {
                window.EvoDocumentActions.run('duplicate', [id], function () {
                    EvoCsrf.post(documentUrl(94, id));
                });
            },
            delete: function (id, data) {
                window.EvoDocumentActions.run('delete', [id], function () {
                    EvoCsrf.post(documentUrl(6, id));
                }, {hasChildren: data.hasChildren === '1'});
            }
        };

        // 戻り先（今のページ・並べ替え・検索語）を残して POST する操作。キーは action の番号、値は operations の名前
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
                window.EvoDocumentActions.run(
                    postMenuActions[a],
                    [id],
                    function () {
                        EvoCsrf.post(documentUrl(a, id, menuConfig.returnQuery));
                    },
                    {hasChildren: menuConfig.withChildren.indexOf(id) !== -1}
                );
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

        // 一覧の一括操作のボタン（button[data-batch-op="<operations の名前>"]）。確かめてから、同じボタンで送信し直す。
        // 選んだ ID は、チェックした行と、ほかのページの分の hidden（data-table.js）の batch[]
        document.addEventListener('click', function (e) {
            const button = e.target.closest('button[data-batch-op]');
            if (!button || !button.form || !menuConfig) {
                return;
            }
            e.preventDefault();
            const ids = Array.from(button.form.querySelectorAll('input[name="batch[]"]'))
                .filter(function (el) {
                    return el.type !== 'checkbox' || el.checked;
                })
                .map(function (el) {
                    return Number(el.value);
                });
            window.EvoDocumentActions.run(
                button.dataset.batchOp,
                ids,
                function () {
                    button.form.requestSubmit(button);
                },
                {
                    batch: true,
                    hasChildren: ids.some(function (id) {
                        return menuConfig.withChildren.indexOf(id) !== -1;
                    })
                }
            );
        });

        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-doc-action]');
            if (el && buttonActions[el.dataset.docAction]) {
                e.preventDefault();
                buttonActions[el.dataset.docAction](Number(el.dataset.docId), el.dataset);
                return;
            }
            hideMenu();
        });
    })();
}
