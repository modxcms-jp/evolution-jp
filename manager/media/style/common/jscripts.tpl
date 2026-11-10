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
        var autoSending = false;    // 送信中（手動・自動とも）
        var autoPending = false;    // 送信中に変更があった
        var lastSentSig = null;     // 直前に成功した送信の内容（同じ内容を自動で重ねて送らない）
        var sendGuard = null;
        var sendSeq = 0;            // 送信の番号。遅れて返った古い送信の結果で、今の状態を戻さないために使う
        var previewComposing = false; // 変換中か（sendAuto が見る）
        var manualPending = null;   // 送信中に押された手動の「プレビュー」（{win, hide}）。今の送信が終わったら 1 本だけ送る
        var scheduleAutoAfterInit = false;
        var AUTO_DELAY = 1500;
        var AUTO_TIMEOUT = 30000;   // Evo.fetch はログインへ移るときに解決しないので、送信中の印を戻す
        var previewMutate = document.getElementById('mutate');
        var previewBaseUrl = [+preview_url+];
        var previewable = [+preview_mode+] == 1 && !!previewMutate && !!'[+id+]' && '[+id+]' !== '0';

        // 保存などでシェルが画面を読み直しても、開いたままのこの文書のプレビューの窓は自動更新を続ける
        if (previewable && prevWin) {
            try {
                if (!prevWin.closed) {
                    var cur = new URL(prevWin.location.href);
                    var base = new URL(previewBaseUrl, location.href);
                    var same = cur.origin === base.origin && cur.pathname === base.pathname && cur.searchParams.has('mgr_preview');
                    base.searchParams.forEach(function (v, k) {
                        if (k !== 'mgr_preview' && cur.searchParams.get(k) !== v) same = false;
                    });
                    if (same) {
                        previewWin = prevWin;
                        autoOn = true;
                        // 窓が見ている下書きのキーを引き継ぐ（サーバーがセッションの持ち主と文書を確かめる）。保存のたびに新しいキーを作らず、最初の更新も再読み込みになる
                        var curKey = cur.searchParams.get('mgr_preview');
                        if (curKey && curKey !== '1') previewKey = curKey;
                        // 待ちの間に保存したときも、窓を保存した内容へ更新する（lastSentSig は空なので 1 回送られる）
                        scheduleAutoAfterInit = true;
                    }
                }
            } catch (err) {
                // 別のオリジンで読めないときは引き継がない（もう一度「プレビュー」で再開）
            }
        }

        // 編集中の内容を送る形にする。sig は File を除いた内容（キーを入れる前）で、同じ内容かの判定に使う
        function buildPreviewForm() {
            // エディタの内容をフォームへ書き戻す（保存の submit でも同じ書き戻しが走る）
            if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
                window.tinymce.triggerSave();
            }
            // 下書きは postv() だけを使い $_FILES を見ないので、File は送らない
            var fd = new FormData();
            var pairs = [];
            new FormData(previewMutate).forEach(function (value, name) {
                if (typeof value !== 'string') return;
                pairs.push([name, value]);
                fd.append(name, value);
            });
            var sig = JSON.stringify(pairs);
            fd.set('a', 309);
            if (previewKey) fd.set('preview_key', previewKey);
            return { fd: fd, sig: sig };
        }

        // 編集中の内容を a=309 へ送る。{url, key, sig} を解決する。previewKey は古い応答で書き換えないよう、呼ぶ側が番号を確かめてから更新する
        function postPreviewDraft(built) {
            // a は本文だけに入れる。URL にも付けると manager/index.php が GET と POST の両方にある a を拒否する
            return Evo.post('index.php', built.fd)
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.url) throw new Error('no url');
                    return { url: data.url, key: data.key || null, sig: built.sig };
                });
        }

        function beginSending() {
            var seq = ++sendSeq;
            autoSending = true;
            autoPending = false;
            if (sendGuard) clearTimeout(sendGuard);
            sendGuard = setTimeout(function () {
                sendGuard = null;
                if (seq === sendSeq) {
                    autoSending = false;
                    flushManual();
                }
            }, AUTO_TIMEOUT);
            return seq;
        }

        // 最新の送信の終わりのときだけ印を戻す。古い送信の終わりは無視する
        function endSending(seq) {
            if (seq !== sendSeq) return;
            autoSending = false;
            if (sendGuard) clearTimeout(sendGuard);
            sendGuard = null;
        }

        function stopAuto() {
            autoOn = false;
            autoPending = false;
            if (manualPending) window.removeEventListener('pagehide', manualPending.hide);
            manualPending = null;
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = null;
        }

        // 何も表示していない新しい窓だけ閉じる。使い回しの窓の中身（前のプレビュー）は消さない
        function closeBlank(win) {
            try {
                if (!win.closed && win.location.href === 'about:blank') win.close();
            } catch (err) {
                // 読めない窓は触らない
            }
        }

        // 保留した手動の送信があれば始める。始めたら true
        function flushManual() {
            if (!manualPending) return false;
            var pending = manualPending;
            manualPending = null;
            sendManual(pending.win, pending.hide);
            return true;
        }

        function previewWinOpen() {
            try {
                return !!previewWin && !previewWin.closed;
            } catch (err) {
                return false;
            }
        }

        // 窓に結果を出す。URL が同じなら再読み込み（スクロールの位置をブラウザが保つ）
        function showPreview(win, result) {
            var current = null;
            try {
                current = win.location.href;
            } catch (err) {
                // 別のオリジンで読めない
            }
            if (current !== null) {
                if (current === 'about:blank' || current !== new URL(result.url, location.href).href) {
                    win.location.href = result.url;
                } else {
                    win.location.reload();
                }
                return;
            }
            if (result.changed) {
                win.location.href = result.url;
                return;
            }
            try {
                win.location.reload();
            } catch (err) {
                win.location.href = result.url;
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
            // 変換中は送らない（確定の compositionend で scheduleAuto が呼ばれる）
            if (previewComposing) return;
            var built = buildPreviewForm();
            // 直前に送った内容と同じなら送らない
            if (built.sig === lastSentSig) return;
            var seq = beginSending();
            postPreviewDraft(built)
                .then(function (result) {
                    // 後の送信が始まっていれば、この古い結果は使わない
                    if (seq !== sendSeq) return;
                    endSending(seq);
                    lastSentSig = built.sig;
                    result.changed = !result.key || result.key !== previewKey;
                    previewKey = result.key;
                    if (!autoOn || !previewMutate.isConnected || !previewWinOpen()) {
                        stopAuto();
                        return;
                    }
                    showPreview(previewWin, result);
                    if (flushManual()) return;
                    if (autoPending) {
                        autoPending = false;
                        scheduleAuto();
                    }
                })
                .catch(function (err) {
                    // 入力のたびに出さないよう alert は出さない。手動の「プレビュー」で通常どおり知らせる
                    endSending(seq);
                    if (seq === sendSeq) autoPending = false;
                    console.warn('preview auto update failed', err);
                    if (seq === sendSeq) flushManual();
                });
        }

        // 手動の送信。手動は内容が同じでも必ず送る（下書きの期限の延長と窓を開くため）。失敗は従来どおり知らせる
        function sendManual(win, hide) {
            var seq = beginSending();
            var failed = function () {
                window.removeEventListener('pagehide', hide);
                if (seq !== sendSeq) return;
                endSending(seq);
                // 続けて手動の送信があるときは窓を閉じない
                if (!manualPending) closeBlank(win);
                alert([+lang_error_occurred+]);
                flushManual();
            };
            postPreviewDraft(buildPreviewForm())
                .then(function (result) {
                    window.removeEventListener('pagehide', hide);
                    // 後の送信が始まっていれば、この古い結果は使わない
                    if (seq !== sendSeq) return;
                    endSending(seq);
                    lastSentSig = result.sig;
                    result.changed = !result.key || result.key !== previewKey;
                    previewKey = result.key;
                    showPreview(win, result);
                    win.focus();
                    if (flushManual()) return;
                    // 送信中に入った変更を 1 回送る
                    if (autoPending) {
                        autoPending = false;
                        scheduleAuto();
                    }
                })
                .catch(failed);
        }

        // 保存後に窓を引き継いだときは更新を 1 回予約する（引き継がなかったときは何もしない）
        if (previewable && scheduleAutoAfterInit) scheduleAuto();

        // 編集の変更を拾う（自動更新が有効なときだけ送られる）。プレビューを開ける編集画面だけで結び付ける
        if (previewable) {
            // 日本語などの変換中は送らない。Chrome の順序は compositionstart, compositionupdate, input(isComposing) ×N, compositionend で、
            // 確定時の input も isComposing のままで compositionend の後に input は来ない。そのため印を持ち、compositionend で送る
            // 確定してすぐ次の変換を始めたとき、前の確定で待っている送信は取り消す。compositionend が来ないまま残らないよう、focusout でも戻す
            previewMutate.addEventListener('compositionstart', function () {
                previewComposing = true;
                if (autoTimer) clearTimeout(autoTimer);
                autoTimer = null;
            });
            previewMutate.addEventListener('compositionend', function () {
                previewComposing = false;
                scheduleAuto();
            });
            previewMutate.addEventListener('focusout', function () {
                previewComposing = false;
            });
            ['input', 'change', 'ddmultiple:change'].forEach(function (type) {
                previewMutate.addEventListener(type, function (e) {
                    if (previewComposing || e.isComposing) return;
                    scheduleAuto();
                });
            });
            // 保存（や他の送信）で画面が移るので、自動更新を止める
            previewMutate.addEventListener('submit', stopAuto);

            // リッチテキストのエディタは変更を元の欄の input イベントで知らせる決まりなので、上の委譲で拾える
            window.evoPreviewCleanup = stopAuto;
        }

        onClick('preview', function (e) {
            // 従来の return false と同じく、既定の動作と伝播を止める
            e.preventDefault();
            e.stopPropagation();
            // 同じ名前の窓が開いていれば window.open がそれを返すので、閉じずに使い回す
            var previewUrl = previewBaseUrl;
            if (!previewable) {
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
            autoOn = true;
            // 手動の送信と自動の送信がぶつからないよう、待っている自動の送信は取り消す
            if (autoTimer) clearTimeout(autoTimer);
            autoTimer = null;
            // セッション切れでログインへ移るとき（Evo.fetch は Promise を解決しない）、空の窓を残さない
            var hide = function () {
                closeBlank(win);
            };
            window.addEventListener('pagehide', hide, { once: true });
            // 送信中は直列にする。同じ下書きのキーへ古い送信が後から着いて上書きしないよう、今の送信が終わってから送る（保留中の押下は 1 本にまとめる）
            if (autoSending) {
                if (manualPending) window.removeEventListener('pagehide', manualPending.hide);
                manualPending = { win: win, hide: hide };
                return;
            }
            sendManual(win, hide);
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
<script type="text/javascript" src="media/browser/browser.js?[+browser_js_ver+]"></script>
