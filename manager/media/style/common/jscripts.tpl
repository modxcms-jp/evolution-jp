<script type="text/javascript">

    var imanager_url = '[+imanager_url+]';
    var fmanager_url = '[+fmanager_url+]';
    var prevWin;
    var docMode = '[+docMode+]';

    // save tree folder state
    if (parent.tree) parent.tree.saveFolderState();

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
            document.location.href = 'index.php?a=132&id=[+id+]';
        });
        onClick('opendraft', function () {
            document.location.href = 'index.php?a=131&id=[+id+]';
        });
        onClick('delete', function () {
            if (confirm("[+lang_confirm_delete_resource+]"))
                EvoCsrf.post("index.php?id=[+id+]&a=6");
        });
        onClick('undelete', function () {
            if (confirm("[+lang_confirm_undelete+]"))
                EvoCsrf.post("index.php?id=[+id+]&a=63");
        });
        onClick('deletedraft', function () {
            if (confirm("[+lang_confirm_delete_draft_resource+]")) {
                documentDirty = false;
                document.mutate.a.value = 130;
                submitMutate();
            }
        });
        onClick('move', function () {
            document.location.href = "index.php?id=[+id+]&a=51";
        });
        onClick('duplicate', function () {
            if (confirm("[+lang_confirm_resource_duplicate+]"))
                EvoCsrf.post("index.php?id=[+id+]&a=94");
        });

        onClick('preview', function (e) {
            // 従来の return false と同じく、既定の動作と伝播を止める
            e.preventDefault();
            e.stopPropagation();
            if (prevWin && !prevWin.closed) {
                prevWin.close();
            }
            
            var previewUrl = [+preview_url+];
            // z=manprev パラメータを追加してプレビューモードを有効化
            previewUrl += (previewUrl.indexOf('?') === -1 ? '?' : '&') + 'z=manprev';
            
            prevWin = window.open(previewUrl, 'prevWin');
            var pmode = [+preview_mode+];
            if (pmode == 1) {
                // プレビュー送信後は元の状態へ戻す。target が残ると以後の保存が
                // シェルを経由しないネイティブ送信になり、別タブへ遷移してしまう
                var mutate = document.getElementById('mutate');
                if (mutate) {
                    var orgAction = mutate.getAttribute('action');
                    var orgTarget = mutate.getAttribute('target');
                    mutate.setAttribute('action', previewUrl);
                    mutate.setAttribute('target', 'prevWin');
                    mutate.requestSubmit();
                    mutate.setAttribute('action', orgAction);
                    if (orgTarget === null) {
                        mutate.removeAttribute('target');
                    } else {
                        mutate.setAttribute('target', orgTarget);
                    }
                }
            }
        });
        onClick('cancel', function () {
            var docIsFolder = '[+docIsFolder+]';
            var docParent = '[+docParent+]';
            if (docMode === 'draft') document.location.href = 'index.php?a=3&id=' + '[+id+]';
            else if (docIsFolder == 1) document.location.href = 'index.php?a=120&id=' + '[+id+]';
            else if (docParent != 0) document.location.href = 'index.php?a=120&id=' + '[+docParent+]';
            else document.location.href = 'index.php?a=2';
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
            if (confirm("[+lang_confirm_reset_pub_dates+]") === true) {
                document.mutate.pub_date.value = '';
                document.mutate.unpub_date.value = '';
            }
        }
        documentDirty = true;
    }

    var allowParentSelection = false;
    var allowLinkSelection = false;

    function enableLinkSelection(b) {
        parent.tree.ca = "link";
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
            window.location.href = "index.php?a=3&id=" + lId;
        } else {
            documentDirty = true;
            document.getElementById('field_weblink').value = lId;
            document.images["llock"].src = "[+style_tree_folder+]";
            allowLinkSelection = false;
        }
    }

    function enableParentSelection(b) {
        parent.tree.ca = "parent";
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
            window.location.href = "index.php?a=3&id=" + pId;
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
        const tdoc = parent.tree.document;
        let pn = tdoc.getElementById("node" + pId);
        if (!pn) return false;
        if (pn.id.substr(4) === id) {
            alert("[+lang_illegal_parent_self+]");
            return false;
        } else {
            while (pn.getAttribute("p") > 0) {
                pId = pn.getAttribute("p");
                pn = tdoc.getElementById("node" + pId);
                if (pn.id.substr(4) === id) {
                    alert("[+lang_illegal_parent_child+]");
                    return false;
                }
            }
        }
        return true;
    }

    function change_url_suffix() {
        var a = document.getElementById("field_alias");
        var s = document.getElementById("url_suffix");
        if (0 < a.value.indexOf('.')) s.innerHTML = '';
        else s.innerHTML = '[+suffix+]';
    }

</script>
<script type="text/javascript" src="media/browser/browser.js"></script>
