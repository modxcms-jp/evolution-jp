<script type="text/javascript">

    var imanager_url = [+imanager_url+];
    var fmanager_url = [+fmanager_url+];
    var prevWin;
    var docMode = '[+docMode+]';

    // ツリーの開閉状態を保存する。ツリーが無い画面（QuickManager の編集など）では何もしない
    if (window.tree && typeof window.tree.saveFolderState === 'function') {
        window.tree.saveFolderState();
    }

    Evo.ready(function () {
        // 要素が無い画面（下書きなど）もあるため、あるときだけ結び付ける
        function onClick(id, fn) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', fn);
        }
        function submitMutate() {
            var form = document.getElementById('mutate');
            if (form) form.requestSubmit();
        }
        function setMutateValue(name, value) {
            var input = document.querySelector('#mutate input[name="' + name + '"]');
            if (input) input.value = value == null ? '' : value;
        }
        function bindClearDate(id) {
            var input = document.getElementById(id);
            var link = input ? input.nextElementSibling : null;
            if (!link || !link.matches('a')) return;
            link.addEventListener('click', function () {
                input.value = '';
                documentDirty = true;
            });
        }

        document.querySelectorAll('#save a').forEach(function (el) {
            el.addEventListener('click', function () {
                documentDirty = false;
                gotosave = true;
                submitMutate();
            });
        });
        onClick('createdraft', function () {
            EvoShell.go('index.php?a=132&id=[+id+]');
        });
        onClick('opendraft', function () {
            EvoShell.go('index.php?a=131&id=[+id+]');
        });
        onClick('delete', function () {
            if (confirm([+lang_confirm_delete_resource+]))
                EvoCsrf.post("index.php?id=[+id+]&a=6");
        });
        onClick('undelete', function () {
            if (confirm([+lang_confirm_undelete+]))
                EvoCsrf.post("index.php?id=[+id+]&a=63");
        });
        onClick('deletedraft', function () {
            if (confirm([+lang_confirm_delete_draft_resource+])) {
                documentDirty = false;
                document.mutate.a.value = 130;
                submitMutate();
            }
        });
        onClick('move', function () {
            EvoShell.go("index.php?id=[+id+]&a=51");
        });
        onClick('duplicate', function () {
            if (confirm([+lang_confirm_resource_duplicate+]))
                EvoCsrf.post("index.php?id=[+id+]&a=94");
        });

        // 前の画面（シェルの遷移では同じ window で JS が読み直される）の自動更新の後始末
        if (typeof window.evoPreviewCleanup === 'function') window.evoPreviewCleanup();
        window.evoPreviewCleanup = null;

        // プレビューの状態は画面ごと（この関数の中）に持つ。別の文書へ移ったら持ち越さない
        var previewKey = null;      // 最後に受け取った下書きのキー（次の送信で使い回す）
        var previewWin = null;      // この画面から開いたプレビューの窓
        var autoOn = false;         // 窓を開いた後の自動更新が有効か
        var autoTimer = null;
        var autoSending = false;
        var autoPending = false;    // 送信中に変更があった
        var AUTO_DELAY = 1500;
        var previewMutate = document.getElementById('mutate');

        // 編集中の内容を a=309 へ送る。{url, changed（キーが新しくなったか）} を解決する
        function postPreviewDraft() {
            // エディタの内容をフォームへ書き戻す（保存の submit でも同じ書き戻しが走る）
            if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
                window.tinymce.triggerSave();
            }
            var fd = new FormData(previewMutate);
            fd.set('a', 309);
            if (previewKey) fd.set('preview_key', previewKey);
            // a は本文だけに入れる。URL にも付けると manager/index.php が GET と POST の両方にある a を拒否する
            return Evo.post('index.php', fd)
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.url) throw new Error('no url');
                    var key = data.key || null;
                    var changed = !key || key !== previewKey;
                    previewKey = key;
                    return { url: data.url, changed: changed };
                });
        }

        function stopAuto() {
            autoOn = false;
            autoPending = false;
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = null;
        }

        function previewWinOpen() {
            try {
                return !!previewWin && !previewWin.closed;
            } catch (err) {
                return false;
            }
        }

        function reloadPreview(result) {
            if (result.changed) {
                previewWin.location.href = result.url;
                return;
            }
            try {
                // URL が同じなら再読み込み（スクロールの位置はブラウザが保つ）
                previewWin.location.reload();
            } catch (err) {
                // 別のオリジンで location に触れないとき
                previewWin.location.href = result.url;
            }
        }

        function scheduleAuto() {
            if (!autoOn) return;
            if (!previewMutate.isConnected || !previewWinOpen()) {
                stopAuto();
                return;
            }
            if (autoSending) {
                autoPending = true;
                return;
            }
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = setTimeout(sendAuto, AUTO_DELAY);
        }

        function sendAuto() {
            autoTimer = null;
            if (!autoOn) return;
            if (!previewMutate.isConnected || !previewWinOpen()) {
                stopAuto();
                return;
            }
            autoSending = true;
            autoPending = false;
            postPreviewDraft()
                .then(function (result) {
                    autoSending = false;
                    if (!autoOn || !previewMutate.isConnected || !previewWinOpen()) {
                        stopAuto();
                        return;
                    }
                    reloadPreview(result);
                    if (autoPending) {
                        autoPending = false;
                        scheduleAuto();
                    }
                })
                .catch(function (err) {
                    // 入力のたびに出さないよう alert は出さない。手動の「プレビュー」で通常どおり知らせる
                    autoSending = false;
                    autoPending = false;
                    console.warn('preview auto update failed', err);
                });
        }

        // 編集の変更を拾う（自動更新が有効なときだけ送られる）。プレビューを開ける編集画面だけで結び付ける
        if ([+preview_mode+] == 1 && previewMutate && '[+id+]' && '[+id+]' !== '0') {
            ['input', 'change', 'ddmultiple:change'].forEach(function (type) {
                previewMutate.addEventListener(type, scheduleAuto);
            });
            // 保存（や他の送信）で画面が移るので、自動更新を止める
            previewMutate.addEventListener('submit', stopAuto);

            var bindEditor = function (ed) {
                if (!ed || ed.evoPreviewBound || typeof ed.on !== 'function') return;
                ed.evoPreviewBound = true;
                ed.on('input change undo redo', scheduleAuto);
            };
            var onAddEditor = function (ev) {
                bindEditor(ev && ev.editor);
            };
            var tiny = window.tinymce;
            if (tiny && typeof tiny.on === 'function') {
                (tiny.editors || []).forEach(bindEditor);
                tiny.on('AddEditor', onAddEditor);
            }
            window.evoPreviewCleanup = function () {
                stopAuto();
                if (tiny && typeof tiny.off === 'function') tiny.off('AddEditor', onAddEditor);
            };
        }

        onClick('preview', function (e) {
            // 従来の return false と同じく、既定の動作と伝播を止める
            e.preventDefault();
            e.stopPropagation();
            // 同じ名前の窓が開いていれば window.open がそれを返すので、閉じずに使い回す
            var previewUrl = [+preview_url+];
            var mutate = previewMutate;
            var pmode = [+preview_mode+];
            if (pmode != 1 || !mutate || !'[+id+]' || '[+id+]' === '0') {
                // 保存済みの内容を見る（新規作成のときもここ）。自動更新はしない。mgr_preview（管理画面からのプレビュー）=1 で保存済みの内容を見る
                previewUrl += (previewUrl.indexOf('?') === -1 ? '?' : '&') + 'mgr_preview=1';
                prevWin = window.open(previewUrl, 'prevWin');
                if (!prevWin) {
                    alert([+lang_error_occurred+]);
                    return;
                }
                prevWin.focus();
                return;
            }

            // 編集中の内容を見る。ポップアップブロックを避けるため、窓はクリックの時点で先に開く
            prevWin = window.open('', 'prevWin');
            if (!prevWin) {
                // ポップアップがブロックされた
                alert([+lang_error_occurred+]);
                return;
            }
            var win = prevWin;
            previewWin = win;
            // 手動の送信と自動の送信がぶつからないよう、待っている自動の送信は取り消す
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = null;
            autoPending = false;
            // 使い回しの窓の中身（前のプレビュー）は消さない。何も表示していない新しい窓だけ、失敗や画面の移動で閉じる
            var closeBlank = function () {
                try {
                    if (!win.closed && win.location.href === 'about:blank') win.close();
                } catch (err) {
                    // 読めない窓は触らない
                }
            };
            // セッション切れでログインへ移るとき（Evo.fetch は Promise を解決しない）、空の窓を残さない
            window.addEventListener('pagehide', closeBlank, { once: true });
            var failed = function () {
                window.removeEventListener('pagehide', closeBlank);
                closeBlank();
                alert([+lang_error_occurred+]);
            };
            postPreviewDraft()
                .then(function (result) {
                    window.removeEventListener('pagehide', closeBlank);
                    win.location.href = result.url;
                    win.focus();
                    // 窓が開いている間は、編集の変更を自動で反映する
                    autoOn = true;
                })
                .catch(failed);
        });
        onClick('cancel', function () {
            var docIsFolder = '[+docIsFolder+]';
            var docParent = '[+docParent+]';
            if (docMode === 'draft') EvoShell.go('index.php?a=3&id=' + '[+id+]');
            else if (docIsFolder == 1) EvoShell.go('index.php?a=120&id=' + '[+id+]');
            else if (docParent != 0) EvoShell.go('index.php?a=120&id=' + '[+docParent+]');
            else EvoShell.go('index.php?a=2');
        });
        bindClearDate('pub_date');
        bindClearDate('unpub_date');

        var fieldTemplate = document.getElementById('field_template');
        var whichEditor = document.getElementById('which_editor');
        curTemplate = fieldTemplate ? fieldTemplate.value : undefined;
        if (fieldTemplate) {
            fieldTemplate.addEventListener('change', function () {
                newTemplate = fieldTemplate.value;
                if (curTemplate !== newTemplate) {
                    documentDirty = false;
                    setMutateValue('a', [+action+]);
                    setMutateValue('newtemplate', newTemplate);
                    submitMutate();
                }
            });
        }
        if (whichEditor) {
            whichEditor.addEventListener('change', function () {
                newTemplate = fieldTemplate ? fieldTemplate.value : undefined;
                newEditor = whichEditor.value;
                documentDirty = false;
                setMutateValue('a', [+action+]);
                setMutateValue('newtemplate', newTemplate);
                whichEditor.value = newEditor;
                submitMutate();
            });
        }
    });

    function changestate(element) {
        currval = eval(element).value;
        if (currval == 1) {
            eval(element).value = 0;
        } else {
            eval(element).value = 1;
        }
        documentDirty = true;
    }

    function resetpubdate() {
        if (document.mutate.pub_date.value !== '' || document.mutate.unpub_date.value !== '') {
            if (confirm([+lang_confirm_reset_pub_dates+]) === true) {
                document.mutate.pub_date.value = '';
                document.mutate.unpub_date.value = '';
            }
        }
        documentDirty = true;
    }

    var allowParentSelection = false;
    var allowLinkSelection = false;

    // 親・リンクはツリーのクリックで選ぶ。ツリーが無い画面（QuickManager の編集など）では何もしない
    function enableLinkSelection(b) {
        if (!window.tree) {
            return;
        }
        window.tree.ca = "link";
        var closed = "[+style_tree_folder+]";
        var opened = "[+style_icons_set_parent+]";
        if (b) {
            document.images["llock"].src = opened;
            allowLinkSelection = true;
        } else {
            document.images["llock"].src = closed;
            allowLinkSelection = false;
        }
    }

    function setLink(lId) {
        if (!allowLinkSelection) {
            EvoShell.go("index.php?a=3&id=" + lId);
        } else {
            documentDirty = true;
            document.getElementById('field_weblink').value = lId;
            document.images["llock"].src = "[+style_tree_folder+]";
            allowLinkSelection = false;
        }
    }

    function enableParentSelection(b) {
        if (!window.tree) {
            return;
        }
        window.tree.ca = "parent";
        var opened = "[+style_icons_set_parent+]";
        var closed = "[+style_tree_folder+]";
        if (b) {
            document.images["plock"].src = opened;
            allowParentSelection = true;
        } else {
            document.images["plock"].src = closed;
            allowParentSelection = false;
        }
    }

    function setParent(pId, pName) {
        if (!allowParentSelection) {
            EvoShell.go("index.php?a=3&id=" + pId);
        } else {
            if (pId === 0 || checkParentChildRelation(pId, pName)) {
                documentDirty = true;
                document.mutate.parent.value = pId;
                var elm = document.getElementById('parentName');
                if (elm) {
                    elm.textContent = pId + " (" + pName + ")";
                }
            }
        }
    }

    // ツリーで省略されたリソースも親にできるよう、タイトルか ID で検索して選ぶ。
    // 自分自身と子孫はダイアログ側で選べないようにしている
    function openParentPicker() {
        EvoShell.openPicker(
            'index.php?a=135&id=' + encodeURIComponent(document.mutate.id.value),
            function (picked) {
                documentDirty = true;
                document.mutate.parent.value = picked.id;
                var elm = document.getElementById('parentName');
                if (elm) {
                    elm.textContent = picked.id + " (" + picked.pagetitle + ")";
                }
            }
        );
    }

    // check if the selected parent is a child of this document
    function checkParentChildRelation(pId, pName) {
        const id = document.mutate.id.value;
        const tdoc = document; // シェルではツリーと同一document
        let pn = tdoc.getElementById("node" + pId);
        if (!pn) return false;
        if (pn.id.substr(4) === id) {
            alert([+lang_illegal_parent_self+]);
            return false;
        } else {
            while (pn.getAttribute("p") > 0) {
                pId = pn.getAttribute("p");
                pn = tdoc.getElementById("node" + pId);
                if (!pn) break;
                if (pn.id.substr(4) === id) {
                    alert([+lang_illegal_parent_child+]);
                    return false;
                }
            }
        }
        return true;
    }

    function change_url_suffix() {
        var a = document.getElementById("field_alias");
        var s = document.getElementById("url_suffix");
        if (0 < a.value.indexOf('.')) s.textContent = '';
        else s.textContent = [+suffix+];
    }

</script>
<script type="text/javascript" src="media/browser/browser.js"></script>
