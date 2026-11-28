// ファイルブラウザの構成ルート。状態(state)を保持し、js/*.js の各モジュールへ必要な参照とコールバックだけを渡して結線する。
// 入口は js/picker.js と js/manage.js で、名前(entry)と振る舞い(behaviors)を渡して registerEntry() を呼ぶ。
// ここには範囲を見て分ける処理を書かない(違いは behaviors と、サーバーが作る設定の値だけで表す)。
//
// 入口の登録表: window.EvoFileBrowserEntries[entry] に開始の関数を置く。EvoShell は ES モジュールを同じ URL では 2 度目に実行しないので、
// 断片の最後のインラインの <script> が、断片の設定の entry で該当の入口を呼び直す。
//
// behaviors(host は { root, config, state, preview, reload, download(names) }):
//   openFile(entry, host)          ダブルクリックと Enter で動く。ピッカーは選んで閉じる、管理モードは表示する
//   itemActions                    ファイルの行のアイコンになる操作の種類の配列('open' | 'preview' | 'download')。種類ごとの定義は ITEM_ACTIONS。
//                                  行のアイコンはここから作り、grid.js は入口を判定しない。'open'・'preview' は右クリックと選択の帯にも出る
//                                  (その並びと条件は SELECTION_ACTIONS)。ダウンロードは itemActions に無い入口でも、右クリックと帯に共通で出す
//   previewActions(entry, host)    プレビューに足すボタンの配列 [{ label, primary, onSelect }]。複製・削除はどの入口でも共通に後ろへ足す
//   pickFile(entry, host)          選んで返す入口(ピッカー)だけが持つ。選択の帯と右クリックメニューの先頭に「このファイルを選択」(1 件のとき)を出す。持たない入口には出ない
//   contextMenuItems(entry, host)  右クリックメニューに足す項目。entry があれば(ファイル 1 件)先頭に、null なら(何も無い所)最後に足す
//   confirmOverwrite(names, host)  サーバーが同じ名前があると返したとき、保存する名前(names)を並べて上書きしてよいか確かめる。
//                                  true なら、そのファイルだけを上書きで送り直す
//
// 外の JS 向けのイベント(#evoFileBrowser から bubbles: true の CustomEvent で出す。detail.scope は入口の名前 picker / manage):
//   evofb:navigate          一覧を読み終えたとき。detail は { scope, folder }(folder はルートからの相対パス)
//   evofb:selectionchange   選択が変わったとき。detail は { scope, folder, names }(names は選んだファイルの名前)。同じ選択では出さない

import { escapeHtml, joinPath, renderBreadcrumb, ICON_OPEN, ICON_PREVIEW, ICON_DOWNLOAD } from './js/utils.js';
import { setMessages, t } from './js/i18n.js';
import { apiUrl, failureMessage, fetchJson, postForm } from './js/api.js';
import { bindMoveDropTarget } from './js/dnd.js';
import { createTree } from './js/tree.js';
import { createGrid } from './js/grid.js';
import { createUpload } from './js/upload.js';
import { createMoveDialog, createPreview } from './js/dialogs.js';
import { createContextMenu } from './js/contextmenu.js';
import { createDownload } from './js/download.js';
import { createImageEditor } from './js/imageEditor.js';
import { createPrefs } from './js/prefs.js';

// ファイルの行の操作の種類ごとの定義。label は言語キー、run は (ファイル, behaviors, host) で動く
var ITEM_ACTIONS = {
    open: { label: 'open', icon: ICON_OPEN, run: function (file, behaviors, host) { behaviors.openFile(file, host); } },
    preview: { label: 'preview', icon: ICON_PREVIEW, run: function (file, behaviors, host) { host.preview.open(file); } },
    download: { label: 'fb_download', icon: ICON_DOWNLOAD, run: function (file, behaviors, host) { host.download([file.name]); } }
};

// 入口の JS が呼ぶ。登録表に開始の関数を置き、今のページの画面を一度だけ初期化する
export function registerEntry(entry, behaviors) {
    function start() {
        // メインの領域とモーダルに同時に出ていても(id が重なる)、この入口の画面を見つけて初期化する
        document.querySelectorAll('#evoFileBrowser').forEach(function (root) {
            init(root, entry, behaviors);
        });
    }
    window.EvoFileBrowserEntries = window.EvoFileBrowserEntries || {};
    window.EvoFileBrowserEntries[entry] = start;
    start();
}

// entry は、画面(data-config の entry)を作った入口の名前。違う入口の behaviors では初期化しない
function init(root, entry, behaviors) {
    if (!root || root.dataset.fbInitialized) {
        return;
    }
    var config = JSON.parse(root.getAttribute('data-config') || '{}');
    if (config.entry !== entry) {
        return;
    }
    root.dataset.fbInitialized = entry;

    // EvoShellモーダル内で開いている場合、モーダルを広げる(:has()未対応ブラウザ対策でJSでクラス付与)。
    // closeModal()側のevoshell:modalcloseで剥がし、次に別種のモーダルが開いても幅へ影響しないようにする
    var shellModalEl = document.getElementById('evoShellModal');
    if (shellModalEl && shellModalEl.contains(root)) {
        shellModalEl.classList.add('evo-fb-modal-wide');
        document.addEventListener('evoshell:modalclose', function () {
            shellModalEl.classList.remove('evo-fb-modal-wide');
        }, { once: true });
    }

    setMessages(config.lang || {});
    var initialFolder = typeof config.initialFolder === 'string' ? config.initialFolder : '';
    var initialSelect = typeof config.initialSelect === 'string' ? config.initialSelect : '';
    // edit=1 で開いたとき、最初の一覧だけで画像編集を開く。editFromPicker は、その編集の終わりを入口へ返すかどうか
    var initialEdit = config.initialEdit === true && initialSelect !== '';
    var editFromPicker = false;
    var initialFallbackTried = false;
    var isInitialLoad = true;
    // 一覧を一度でも出したか。開いたとき（最初の一覧）は、選んだファイルへのスクロールをスムーズにしない
    var hasShownList = false;
    // 最後に頼んだ一覧の番号。遅れて届いた前の一覧の応答は、番号が違うので捨てる
    var loadSeq = 0;

    var prefs = createPrefs(config.entry, config.defaults);

    var state = {
        folder: initialFolder,
        rawFolders: [],
        rawFiles: [],
        search: '',
        sortKey: 'name',
        sortDir: 'asc',
        view: prefs.get('view'),
        selection: new Set(),
        writable: true,
        treeOpen: prefs.get('tree') === 'open'
    };
    var lastSelectionKey = '[]';

    var pathEl = root.querySelector('[data-role="path"]');
    var treeEl = root.querySelector('[data-role="tree"]');
    var gridEl = root.querySelector('[data-role="grid"]');
    var dropzoneEl = root.querySelector('[data-role="dropzone"]');
    var searchEl = root.querySelector('[data-role="search"]');
    var sortEl = root.querySelector('[data-role="sort"]');
    var viewToggleBtn = root.querySelector('[data-role="view-toggle"]');
    var mkdirBtn = root.querySelector('[data-action="mkdir"]');
    var uploadBtn = root.querySelector('[data-action="upload"]');
    var noticeEl = root.querySelector('[data-role="notice"]');
    var treeToggleBtn = root.querySelector('[data-action="toggle-tree"]');

    // preview は作ったあとに入れる
    var host = { root: root, config: config, state: state, preview: null, reload: function () { load(); } };

    function emit(type, detail) {
        detail.scope = config.entry;
        root.dispatchEvent(new CustomEvent(type, { bubbles: true, detail: detail }));
    }

    function notifySelection() {
        var names = Array.from(state.selection);
        var key = JSON.stringify(names);
        if (key === lastSelectionKey) {
            return;
        }
        lastSelectionKey = key;
        emit('evofb:selectionchange', { folder: state.folder, names: names });
    }

    function findFile(name) {
        return state.rawFiles.find(function (f) { return f.name === name; });
    }

    // 一部が失敗したときの表示(移動・コピーで共通)
    function alertPartialErrors(titleKey, errors) {
        if (errors && errors.length) {
            window.alert(t(titleKey) + '\n' + errors.map(function (err) {
                return err.name + ': ' + err.message;
            }).join('\n'));
        }
    }

    // onDone(result): 通信が成功したときに、一覧を読み直す前に呼ぶ
    function moveFilesTo(dest, names, sourceFolder, onDone) {
        if (dest === sourceFolder || names.length === 0) {
            return;
        }
        postForm(config, state, 'move', { 'files[]': names, dest: dest }, sourceFolder)
            .then(function (result) {
                alertPartialErrors('fb_move_partial_failed', result.errors);
                if (onDone) {
                    onDone(result);
                }
                load();
            })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_move_failed'));
            });
    }

    // 応答が届くまでに別のフォルダーへ移っていたら、後のフォルダーの同名のものを選ばない
    function nameIfStillIn(folder, name) {
        return state.folder === folder ? name : undefined;
    }

    function performRename(name, newName) {
        if (!newName || newName === name) {
            return;
        }
        var folder = state.folder;
        postForm(config, state, 'rename', { target: name, newName: newName })
            .then(function (result) { load(nameIfStillIn(folder, (result && result.name) || newName), { instant: true }); })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_rename_failed'));
                // その場の入力欄が残らないよう、元の名前を選んだまま読み直す
                load(nameIfStillIn(folder, name), { instant: true });
            });
    }

    function renameEntry(name) {
        performRename(name, window.prompt(t('fb_rename_prompt'), name));
    }

    // 選んでいる 1 件の名前を変えられるか(帯のボタン・F2 の共通の条件)
    function canRenameSelection() {
        return state.selection.size === 1 && state.writable;
    }

    // 一覧に描かれているファイルはその場の入力で、描かれていない(検索で絞られた等の)ときは確認の入力欄で名前を変える
    function startRenameFor(name) {
        if (!grid.startRename(name)) {
            renameEntry(name);
        }
    }

    // 消せたら true で終わる(確かめて取りやめたとき・失敗したときは false)
    function deleteEntry(name, isFolder) {
        if (!window.confirm(t(isFolder ? 'fb_confirm_delete_folder' : 'fb_confirm_delete_file', name))) {
            return Promise.resolve(false);
        }
        var fields = isFolder ? { 'folders[]': [name] } : { 'files[]': [name] };
        return postForm(config, state, 'delete', fields)
            .then(function (result) {
                alertPartialErrors('fb_delete_partial_failed', result.errors);
                load();
                return (result.deleted || []).indexOf(name) !== -1;
            })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_delete_failed'));
                return false;
            });
    }

    // 同じフォルダへコピーする(サーバーが 名前(1).拡張子 のように空いている名前を付ける)。できたら一覧を読み直して、複製を選ぶ
    function duplicateEntry(name) {
        var folder = state.folder;
        postForm(config, state, 'copy', { 'files[]': [name], dest: folder })
            .then(function (result) {
                alertPartialErrors('fb_copy_partial_failed', result.errors);
                var copied = (result.copied || [])[0];
                load(copied ? nameIfStillIn(folder, copied.name) : undefined, { instant: true });
            })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_copy_failed'));
            });
    }

    // クリップボード: { mode: 'copy' | 'cut', folder, names }。フォルダを移っても残り、モーダルを閉じると状態ごと消える
    var clipboard = null;
    var pasteBtn = root.querySelector('[data-action="paste"]');
    var selectionBarEl = root.querySelector('[data-role="selectionbar"]');
    var selectionActionsEl = root.querySelector('[data-role="selection-actions"]');

    function isCutName(name) {
        return !!clipboard && clipboard.mode === 'cut' && clipboard.folder === state.folder && clipboard.names.indexOf(name) !== -1;
    }

    function updateClipboardUi() {
        pasteBtn.hidden = !canPasteTo(state.folder);
        grid.updateCutDom();
    }

    function applyWritable() {
        mkdirBtn.disabled = !state.writable;
        // フォルダーが書けないとき、またはアップロードの権限が無いとき(理由は別々の注意で出す)
        uploadBtn.disabled = !state.writable || !config.canUpload;
        pasteBtn.disabled = !state.writable;
        noticeEl.hidden = state.writable;
    }

    // 切り取ったファイルが別の方法で動いたり消えたりしたときは、一覧に無い名前を外す
    function pruneClipboard() {
        if (!clipboard || clipboard.folder !== state.folder) {
            return;
        }
        clipboard.names = clipboard.names.filter(function (n) { return !!findFile(n); });
        if (clipboard.names.length === 0) {
            clipboard = null;
        }
    }

    function setClipboard(mode) {
        if (state.selection.size === 0) {
            return;
        }
        clipboard = { mode: mode, folder: state.folder, names: Array.from(state.selection) };
        updateClipboardUi();
    }

    // 切り取りは、元のフォルダと同じ所へは貼れない
    function canPasteTo(dest) {
        return !!clipboard && !(clipboard.mode === 'cut' && clipboard.folder === dest);
    }

    function pasteTo(dest) {
        if (!canPasteTo(dest) || (dest === state.folder && !state.writable)) {
            return;
        }
        var clip = clipboard;
        if (clip.mode === 'cut') {
            moveFilesTo(dest, clip.names, clip.folder, function (result) {
                // 移せたものだけクリップボードから外す(失敗したものは残して貼り直せる)
                var moved = result.moved || [];
                clip.names = clip.names.filter(function (n) { return moved.indexOf(n) === -1; });
                if (clip.names.length === 0 && clipboard === clip) {
                    clipboard = null;
                }
            });
            return;
        }
        postForm(config, state, 'copy', { 'files[]': clip.names, dest: dest }, clip.folder)
            .then(function (result) {
                alertPartialErrors('fb_copy_partial_failed', result.errors);
                load();
            })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_copy_failed'));
            });
    }

    var itemActionKinds = behaviors.itemActions || [];
    var grid = createGrid({
        gridEl: gridEl,
        dropzoneEl: dropzoneEl,
        selectionCountEl: root.querySelector('[data-role="selection-count"]'),
        state: state,
        config: config,
        onNavigate: navigateTo,
        onSort: function (key) {
            setSort(key, state.sortKey === key && state.sortDir === 'asc' ? 'desc' : 'asc');
        },
        openFile: function (name) {
            var file = findFile(name);
            if (file) {
                behaviors.openFile(file, host);
            }
        },
        itemActions: itemActionKinds.map(function (kind) {
            return { kind: kind, label: t(ITEM_ACTIONS[kind].label), icon: ITEM_ACTIONS[kind].icon };
        }),
        runItemAction: function (kind, name) {
            var file = findFile(name);
            if (file) {
                ITEM_ACTIONS[kind].run(file, behaviors, host);
            }
        },
        renameEntry: renameEntry,
        renameInline: performRename,
        deleteEntry: deleteEntry,
        onDropMove: moveFilesTo,
        onSelectionChange: function (count) {
            // 選択が無いときは帯を visibility で隠す(場所は取ったままなので、出し入れで一覧の位置も高さも変わらない)
            selectionBarEl.classList.toggle('is-hidden', count === 0);
            // 1 件のときは件数を見せない(読み上げ用の文字は残す。filebrowser.css)
            selectionBarEl.classList.toggle('is-single', count === 1);
            updateSelectionActions();
            notifySelection();
        },
        isCut: isCutName
    });

    var tree = createTree({
        treeEl: treeEl,
        rootLabel: config.rootLabel,
        fetchFolders: function (path) {
            return fetchJson(apiUrl(config, { action: 'list', folder: path, foldersOnly: '1' }));
        },
        getCurrentFolder: function () { return state.folder; },
        onNavigate: navigateTo,
        onDropMove: moveFilesTo
    });

    var moveDialog = createMoveDialog({
        overlayEl: root.querySelector('[data-role="move-overlay"]'),
        panelEl: root.querySelector('[data-role="move-panel"]'),
        pathEl: root.querySelector('[data-role="move-path"]'),
        gridEl: root.querySelector('[data-role="move-grid"]'),
        cancelBtn: root.querySelector('[data-role="move-cancel"]'),
        confirmBtn: root.querySelector('[data-role="move-confirm"]'),
        state: state,
        config: config,
        getSelection: function () { return Array.from(state.selection); },
        onConfirm: moveFilesTo
    });

    var imageEditor = createImageEditor({
        overlayEl: root.querySelector('[data-role="edit-overlay"]'),
        state: state,
        config: config,
        onSaved: function (name) {
            var fromPicker = editFromPicker;
            editFromPicker = false;
            // 保存したファイルが変わったので、プレビューは閉じて一覧を読み直し、保存した名前を選ぶ
            preview.close();
            dropzoneEl.focus({ preventScroll: true });
            // フォルダは移っていないので、保存した画像の位置はすぐ出す
            load(name, {
                instant: true,
                onLoaded: fromPicker && behaviors.editDone ? function () {
                    var saved = findFile(name);
                    if (saved) {
                        behaviors.editDone(saved);
                    }
                } : null
            });
        },
        onCancel: function () {
            var fromPicker = editFromPicker;
            editFromPicker = false;
            if (fromPicker && behaviors.editCancel) {
                behaviors.editCancel();
            }
        }
    });

    var preview = createPreview({
        overlayEl: root.querySelector('[data-role="preview-overlay"]'),
        panelEl: root.querySelector('[data-role="preview-panel"]'),
        imgEl: root.querySelector('[data-role="preview-img"]'),
        videoEl: root.querySelector('[data-role="preview-video"]'),
        audioEl: root.querySelector('[data-role="preview-audio"]'),
        metaEl: root.querySelector('[data-role="preview-meta"]'),
        closeBtn: root.querySelector('[data-role="preview-close"]'),
        actionsEl: root.querySelector('[data-role="preview-actions"]'),
        editBtn: root.querySelector('[data-role="preview-edit"]'),
        // 入口ごとのボタンのあとに、複製と削除を足す(書き込めないフォルダでは押せない)。閉じたあとは一覧へフォーカスを戻す。
        // 削除は確かめる間もプレビューを開いたままにし、通信中はボタンを押せなくする。消せたとき、まだそのファイルを開いていれば閉じる
        getActions: function (file) {
            return behaviors.previewActions(file, host).concat([
                {
                    label: t('fb_duplicate'),
                    disabled: !state.writable,
                    onSelect: function () {
                        dropzoneEl.focus({ preventScroll: true });
                        duplicateEntry(file.name);
                    }
                },
                {
                    label: t('fb_rename'),
                    disabled: !state.writable,
                    onSelect: function () {
                        dropzoneEl.focus({ preventScroll: true });
                        startRenameFor(file.name);
                    }
                },
                {
                    label: t('delete'),
                    danger: true,
                    keepOpen: true,
                    disabled: !state.writable,
                    onSelect: function (button) {
                        button.disabled = true;
                        deleteEntry(file.name, false).then(function (deleted) {
                            if (!deleted) {
                                button.disabled = false;
                                return;
                            }
                            if (preview.isShowing(file)) {
                                preview.close();
                                dropzoneEl.focus({ preventScroll: true });
                            }
                        });
                    }
                }
            ]);
        },
        canEdit: imageEditor.canEdit,
        onEdit: imageEditor.open
    });
    host.preview = preview;

    createUpload({
        uploadsEl: root.querySelector('[data-role="uploads"]'),
        fileInputEl: root.querySelector('[data-role="file-input"]'),
        uploadBtn: uploadBtn,
        dropzoneEl: dropzoneEl,
        state: state,
        config: config,
        confirmOverwrite: function (names) {
            return behaviors.confirmOverwrite(names, host);
        },
        onDone: load
    });

    var downloader = createDownload({
        uploadsEl: root.querySelector('[data-role="uploads"]'),
        state: state,
        config: config
    });

    // 入口の JS が、プレビューのボタンなどから同じダウンロードを使う
    host.download = function (names) { downloader.download(names); };

    // zip を作れない環境では、1 件だけならそのまま送れるが、2 件以上は送れない
    function canDownload(count) {
        return count > 0 && (count === 1 || !!config.zip);
    }

    function bulkDownload() {
        if (canDownload(state.selection.size)) {
            downloader.download(Array.from(state.selection));
        }
    }

    function navigateTo(path, selectName) {
        state.folder = path;
        load(selectName);
    }

    function renderPath() {
        renderBreadcrumb(pathEl, escapeHtml(config.rootLabel), state.folder, navigateTo);
        // ルートでは出さない(戻る先が無く、ウェブサイトのトップページにパンくずを出さないのと同じ)。移動先のダイアログのパンくずはルートでも出す
        pathEl.hidden = state.folder === '';
        // パンくずもエクスプローラ同様にドロップ先にする(上位フォルダへの移動)
        pathEl.querySelectorAll('.evo-fb-crumb').forEach(function (btn) {
            bindMoveDropTarget(btn, function () {
                return btn.getAttribute('data-path');
            }, moveFilesTo);
        });
    }

    // 選んだ状態のファイルを、一覧の真ん中あたりまでスクロールする。
    // scrollIntoView はモーダルの外の画面まで動かすことがあるので、一覧の入れ物だけを動かす。
    // 開いたときと、フォルダを移らない読み直し（画像の保存のあと）は、すぐその位置を出す。
    // 開いたあとの移動（ツリーのファイルのクリックなど）は、どこへ移ったかを目で追えるようにスムーズにする。
    // 動きを減らす設定のときは、いつもすぐ移る
    function scrollToSelected(smooth) {
        var item = gridEl.querySelector('.evo-fb-item.is-checked');
        if (!item) {
            return;
        }
        var boxRect = dropzoneEl.getBoundingClientRect();
        var itemRect = item.getBoundingClientRect();
        var offset = itemRect.top - boxRect.top - (dropzoneEl.clientHeight - itemRect.height) / 2;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        dropzoneEl.scrollTo({
            top: dropzoneEl.scrollTop + offset,
            behavior: (smooth && !reduceMotion) ? 'smooth' : 'auto'
        });
    }

    // edit=1 のとき、select のファイルの画像編集を開く。開けないときは、一覧のまま理由を出す
    function openInitialEdit(file) {
        if (!file) {
            window.alert(t('fb_edit_open_not_found'));
        } else if (!imageEditor.canEdit(file)) {
            window.alert(t('fb_edit_open_not_editable'));
        } else {
            editFromPicker = true;
            imageEditor.open(file);
        }
    }

    function load(selectName, options) {
        var instant = !!(options && options.instant);
        var allowInitialFallback = isInitialLoad;
        var openEditAfter = isInitialLoad && initialEdit && selectName === initialSelect;
        isInitialLoad = false;
        // 描き直すとフォーカスが body に落ちるので、ファイルブラウザの中にあったときだけ覚えておき、後で戻す
        var hadFocus = root.contains(document.activeElement);
        var seq = ++loadSeq;
        // 読み込み中は前のフォルダーの項目を残さない(名前で探す操作が、後のフォルダーの同名のものに効かないように)
        state.rawFolders = [];
        state.rawFiles = [];
        grid.renderLoading();
        renderPath();
        state.selection.clear();
        grid.updateSelectionBar();

        fetchJson(apiUrl(config, { action: 'list', folder: state.folder }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (data) {
                if (seq !== loadSeq) {
                    return;
                }
                state.rawFolders = data.folders;
                state.rawFiles = data.files;
                state.writable = data.writable !== false;
                applyWritable();
                // ツリーのファイルクリック等からの遷移時、対象ファイルを選択状態にする
                var selected = !!(selectName && findFile(selectName));
                if (selected) {
                    state.selection.add(selectName);
                }
                grid.renderList();
                grid.updateSelectionBar();
                if (selected) {
                    scrollToSelected(hasShownList && !instant);
                }
                hasShownList = true;
                emit('evofb:navigate', { folder: state.folder });
                pruneClipboard();
                updateClipboardUi();
                if (hadFocus && document.activeElement === document.body) {
                    var focusEl = gridEl.querySelector('.evo-fb-item.is-checked [data-role="name"]') || dropzoneEl;
                    focusEl.focus({ preventScroll: true });
                }
                tree.syncWithList(state.folder, data.folders);
                tree.revealPath(state.folder);
                if (openEditAfter) {
                    openInitialEdit(selected ? findFile(selectName) : null);
                }
                if (options && options.onLoaded) {
                    options.onLoaded();
                }
            })
            .catch(function (err) {
                if (seq !== loadSeq) {
                    return;
                }
                if (allowInitialFallback && !initialFallbackTried && initialFolder !== '') {
                    initialFallbackTried = true;
                    // 初期フォルダーが無いとき。根に同じ名前のファイルがあっても、別のファイルを編集させないので編集は開かず、理由だけ出す
                    state.folder = '';
                    load(initialSelect, {
                        onLoaded: initialEdit ? function () { window.alert(t('fb_edit_open_not_found')); } : null
                    });
                    return;
                }
                grid.renderError(err);
            });
    }

    // --- 検索・ソート・表示切替 ---

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            state.search = searchEl.value;
            grid.renderList();
        });
    }

    function setSort(key, dir) {
        state.sortKey = key;
        state.sortDir = dir;
        if (sortEl) {
            sortEl.value = key;
        }
        grid.renderList();
    }

    if (sortEl) {
        sortEl.value = state.sortKey;
        sortEl.addEventListener('change', function () {
            setSort(sortEl.value, 'asc');
        });
    }

    if (viewToggleBtn) {
        viewToggleBtn.addEventListener('click', function () {
            state.view = state.view === 'grid' ? 'list' : 'grid';
            prefs.set('view', state.view);
            grid.renderList();
        });
    }

    function applyTreeOpen() {
        treeEl.hidden = !state.treeOpen;
        treeToggleBtn.setAttribute('aria-expanded', state.treeOpen ? 'true' : 'false');
    }

    treeToggleBtn.addEventListener('click', function () {
        state.treeOpen = !state.treeOpen;
        prefs.set('tree', state.treeOpen ? 'open' : 'closed');
        applyTreeOpen();
    });
    applyTreeOpen();

    // --- フォルダ作成 ---

    function createFolder() {
        if (!state.writable) {
            return;
        }
        var name = window.prompt(t('fb_mkdir_prompt'));
        if (!name) {
            return;
        }
        postForm(config, state, 'mkdir', { name: name })
            .then(function () { load(); })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_mkdir_failed'));
            });
    }

    mkdirBtn.addEventListener('click', createFolder);

    // --- 一括操作 ---

    root.querySelector('[data-action="clear-selection"]').addEventListener('click', function () {
        state.selection.clear();
        grid.updateSelectionDom();
        // 押したボタン自身が無効になってフォーカスが body に落ちるので、一覧へ戻す(Ctrl+C などのキー操作を続けられるように)
        dropzoneEl.focus({ preventScroll: true });
    });

    function bulkDelete() {
        var names = Array.from(state.selection);
        if (names.length === 0) {
            return;
        }
        if (!window.confirm((names.length === 1 ? t('fb_confirm_delete_file', names[0]) : t('fb_confirm_delete_files', names.length)))) {
            return;
        }
        postForm(config, state, 'delete', { 'files[]': names })
            .then(function (result) {
                alertPartialErrors('fb_delete_partial_failed', result.errors);
                load();
            })
            .catch(function (err) {
                window.alert(failureMessage(err, 'fb_delete_failed'));
            });
    }

    function bulkMove() {
        if (state.selection.size === 0) {
            return;
        }
        moveDialog.open(state.folder);
    }

    pasteBtn.addEventListener('click', function () { pasteTo(state.folder); });

    // 何も無い所をクリックしたときも、キーボード操作(Ctrl+C・X・V)を受けられるよう一覧へフォーカスする
    dropzoneEl.addEventListener('click', function (e) {
        if (!e.target.closest('button, input, select, textarea, .evo-fb-item')) {
            dropzoneEl.focus({ preventScroll: true });
        }
    });

    // Ctrl/Cmd + C・X・V。#evoFileBrowser の中にフォーカスがあるときだけ受ける
    // (リスナーは root に付けるので、モーダルを閉じて root が消えれば一緒に消える)
    root.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey || e.defaultPrevented) {
            return;
        }
        var key = e.key.toLowerCase();
        if (key !== 'c' && key !== 'x' && key !== 'v') {
            return;
        }
        // 入力欄での通常のコピー・貼り付けや、選択した文字のコピーを横取りしない
        if (e.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        if (root.querySelector('.evo-fb-overlay[open]') || contextMenu.isOpen()) {
            return;
        }
        var selection = window.getSelection();
        if (selection && selection.toString() !== '') {
            return;
        }
        if (key === 'v') {
            if (!canPasteTo(state.folder)) {
                return;
            }
            e.preventDefault();
            pasteTo(state.folder);
            return;
        }
        if (state.selection.size === 0) {
            return;
        }
        e.preventDefault();
        setClipboard(key === 'c' ? 'copy' : 'cut');
    });

    // F2: 選んでいる 1 件の名前の変更を始める(入力欄・ダイアログ・右クリックメニューが開いているときは受けない)
    root.addEventListener('keydown', function (e) {
        if (e.key !== 'F2' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || e.defaultPrevented || e.isComposing) {
            return;
        }
        if (!canRenameSelection() || e.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        if (root.querySelector('.evo-fb-overlay[open]') || contextMenu.isOpen()) {
            return;
        }
        e.preventDefault();
        startRenameFor(Array.from(state.selection)[0]);
    });

    // Enter: 一覧でファイルを 1 件選んでいるときに、ダブルクリックと同じく openFile を呼ぶ。
    // フォーカスが一覧そのものか、選んでいるファイルの名前・サムネイルのボタンにあるときだけ受ける。
    // 入力欄・ダイアログ・右クリックメニューが開いているとき、ほかのボタンでは動かさない(Enter でそのボタンを押す)
    root.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || e.defaultPrevented || e.isComposing) {
            return;
        }
        if (state.selection.size !== 1 || e.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        if (root.querySelector('.evo-fb-overlay[open]') || contextMenu.isOpen()) {
            return;
        }
        var name = Array.from(state.selection)[0];
        if (e.target !== dropzoneEl) {
            var itemBtn = e.target.closest('[data-role="name"], [data-role="thumb"]');
            var itemEl = itemBtn && itemBtn.closest('.evo-fb-item-file');
            if (!itemEl || itemEl.getAttribute('data-name') !== name) {
                return;
            }
        }
        var file = findFile(name);
        if (!file) {
            return;
        }
        // フォーカスのあるボタンを Enter で押したことにはしない(名前の変更の開始などが重ならないように)
        e.preventDefault();
        behaviors.openFile(file, host);
    });

    // --- 選択に対する操作の表 ---
    // 右クリックメニューと下の選択の帯は、どちらもこの表から作る(並び・文言・出す条件・実行がここだけにある)。
    // files は選んでいるファイル。single は 1 件だけのときの操作、when は出す条件、enabled は押せる条件(省くと常に押せる)。
    // 帯もメニューも、出す条件と押せる条件を両方満たす操作だけを並べる(actionsFor)。並ぶボタンが選択で変わっても帯の高さが動かないよう、
    // 帯はいちばん長くなる状態(全部のボタン)の高さを予約する(reserveSelectionBarHeight)。
    // open・preview は入口の itemActions に入っているときだけ出す(入口ごとの内訳は behaviors が決める)。
    // pick は選んで返す入口(behaviors.pickFile があるピッカー)だけ、先頭に置く(primary は帯で目立たせるボタン)
    var pickActions = typeof behaviors.pickFile === 'function' ? [{
        id: 'pick', label: 'fb_pick_this_file', single: true, primary: true,
        run: function (files) { behaviors.pickFile(files[0], host); }
    }] : [];
    var SELECTION_ACTIONS = pickActions.concat([
        {
            id: 'open', label: ITEM_ACTIONS.open.label, single: true,
            when: function () { return itemActionKinds.indexOf('open') !== -1; },
            run: function (files) { ITEM_ACTIONS.open.run(files[0], behaviors, host); }
        },
        {
            id: 'preview', label: ITEM_ACTIONS.preview.label, single: true,
            when: function () { return itemActionKinds.indexOf('preview') !== -1; },
            run: function (files) { ITEM_ACTIONS.preview.run(files[0], behaviors, host); }
        },
        {
            id: 'edit', label: 'fb_edit', single: true,
            when: function (files) { return files.every(imageEditor.canEdit); },
            run: function (files) { imageEditor.open(files[0]); }
        },
        {
            id: 'download', label: ITEM_ACTIONS.download.label,
            enabled: function (files) { return canDownload(files.length); },
            run: bulkDownload
        },
        { id: 'copy', label: 'fb_copy', run: function () { setClipboard('copy'); } },
        { id: 'cut', label: 'fb_cut', enabled: isWritable, run: function () { setClipboard('cut'); } },
        {
            id: 'rename', label: 'fb_rename', single: true,
            enabled: canRenameSelection,
            run: function (files) { startRenameFor(files[0].name); }
        },
        { id: 'move', label: 'move', enabled: isWritable, run: bulkMove },
        { id: 'delete', label: 'delete', danger: true, enabled: isWritable, run: bulkDelete }
    ]);

    // 書き込めないフォルダでは、コピー以外(切り取り・名前を変更・移動・削除)を押せなくする
    function isWritable() {
        return state.writable;
    }

    function selectedFiles() {
        return Array.from(state.selection).map(findFile).filter(Boolean);
    }

    // 出す条件(when)と押せる条件(single・enabled)の両方を満たす操作。メニュー・帯・帯のボタンの実行がこの判定を使う
    function isActionAvailable(action, files) {
        return files.length > 0
            && (!action.when || action.when(files))
            && (!action.single || files.length === 1)
            && (!action.enabled || action.enabled(files));
    }

    // メニューに出す操作
    function actionsFor(files) {
        return SELECTION_ACTIONS.filter(function (action) {
            return isActionAvailable(action, files);
        });
    }

    // 帯のボタンは入口の画面を作るときに 1 度だけ並べ、選択が変わるたびに出し入れだけを更新する
    var selectionActionBtns = SELECTION_ACTIONS.map(function (action) {
        var li = document.createElement('li');
        var btn = document.createElement('button');
        li.classList.toggle('is-danger', !!action.danger);
        btn.type = 'button';
        btn.setAttribute('data-action', 'selection-' + action.id);
        btn.textContent = t(action.label);
        if (action.primary) {
            btn.classList.add('button', 'primary');
        }
        btn.addEventListener('click', function () {
            var files = selectedFiles();
            if (isActionAvailable(action, files)) {
                action.run(files);
            }
        });
        li.appendChild(btn);
        selectionActionsEl.appendChild(li);
        return btn;
    });

    function updateSelectionActions() {
        // 帯のボタンを並べる前(grid の初期化中)に呼ばれることがある
        if (!selectionActionBtns) {
            return;
        }
        var files = selectedFiles();
        SELECTION_ACTIONS.forEach(function (action, i) {
            selectionActionBtns[i].parentNode.hidden = !isActionAvailable(action, files);
        });
    }

    // 並ぶボタンが選択で変わると、狭い幅では横スクロールバーの有無が変わり、帯の高さが動く。
    // そこで、帯がいちばん長くなる状態(全部のボタン・件数あり)の高さを測って min-height にする。
    // 帯を包む欄・ボタンの欄の大きさが変わったら(幅・フォントの読み込み・テーマの切り替え・非表示のモーダルが見えたとき)測り直す。
    // 帯自身は監視しない(測り直しで帯の高さを書き換えるので、同じ配信の中で大きさが変わって ResizeObserver の loop エラーになる)。
    // 帯の高さは、包む欄(高さは親の flex で決まる)にもボタンの欄(横幅は中身で決まる)にも入らない。observe() は登録のときにも 1 回呼ぶ
    // 非表示で 0 のときは予約しない(次に大きさが変わったとき測る)
    function reserveSelectionBarHeight() {
        var hidden = selectionActionBtns.map(function (btn) { return btn.parentNode.hidden; });
        var single = selectionBarEl.classList.contains('is-single');
        selectionActionBtns.forEach(function (btn) { btn.parentNode.hidden = false; });
        selectionBarEl.classList.remove('is-single');
        selectionBarEl.style.minHeight = '';
        var height = selectionBarEl.offsetHeight;
        selectionActionBtns.forEach(function (btn, i) { btn.parentNode.hidden = hidden[i]; });
        selectionBarEl.classList.toggle('is-single', single);
        if (height) {
            selectionBarEl.style.minHeight = height + 'px';
        }
    }
    var barObserver = new ResizeObserver(reserveSelectionBarHeight);
    barObserver.observe(selectionBarEl.parentNode);
    barObserver.observe(selectionActionsEl);

    // --- 右クリックメニュー ---
    // 項目は既存の処理(ボタンと同じ関数)を呼ぶだけ。対象は右クリックした所で決める。

    var contextMenu = createContextMenu({ rootEl: root, scrollEl: dropzoneEl });

    function contextMenuItems(itemEl) {
        if (!itemEl) {
            var folderItems = [];
            if (state.writable) {
                folderItems.push({ label: t('fb_new_folder'), onSelect: createFolder });
                if (config.canUpload) {
                    folderItems.push({ label: t('fb_upload'), onSelect: function () { uploadBtn.click(); } });
                }
                if (canPasteTo(state.folder)) {
                    folderItems.push({ label: t('fb_paste'), onSelect: function () { pasteTo(state.folder); } });
                }
            }
            return folderItems.concat(behaviors.contextMenuItems(null, host));
        }
        var name = itemEl.getAttribute('data-name');
        if (itemEl.getAttribute('data-kind') === 'folder') {
            var subFolder = joinPath(state.folder, name);
            var subItems = [{ label: t('open'), onSelect: function () { navigateTo(subFolder); } }];
            if (canPasteTo(subFolder)) {
                subItems.push({ label: t('fb_paste'), onSelect: function () { pasteTo(subFolder); } });
            }
            subItems.push(
                { label: t('fb_rename'), onSelect: function () { renameEntry(name); } },
                { label: t('delete'), danger: true, onSelect: function () { deleteEntry(name, true); } }
            );
            return subItems;
        }
        // 選択に入っていないファイルは、エクスプローラと同じく選択をそのファイルだけにする
        if (!state.selection.has(name)) {
            state.selection.clear();
            state.selection.add(name);
            grid.updateSelectionDom();
        }
        var file = findFile(name);
        var items = state.selection.size === 1 && file ? behaviors.contextMenuItems(file, host).slice() : [];
        return items.concat(actionsFor(selectedFiles()).map(function (action) {
            return { label: t(action.label), danger: action.danger, onSelect: function () { action.run(selectedFiles()); } };
        }));
    }

    dropzoneEl.addEventListener('contextmenu', function (e) {
        // 名前変更中の入力欄などでは、ブラウザ標準のメニュー(コピー・貼り付け)を残す
        if (e.target.closest('input, select, textarea')) {
            return;
        }
        e.preventDefault();
        var itemEl = e.target.closest('.evo-fb-item');
        var x = e.clientX;
        var y = e.clientY;
        // メニューキー・Shift+F10 のときは座標が無いので、フォーカス中の要素の位置に出す
        if (e.button !== 2 && x === 0 && y === 0) {
            var focused = document.activeElement;
            var anchor = focused && dropzoneEl.contains(focused) ? focused : null;
            itemEl = anchor ? anchor.closest('.evo-fb-item') : null;
            var rect = (anchor || dropzoneEl).getBoundingClientRect();
            x = rect.left + 8;
            y = rect.top + 8;
        }
        var items = contextMenuItems(itemEl);
        if (items.length === 0) {
            return;
        }
        var label = t('fb_context_menu');
        if (itemEl) {
            var isMulti = itemEl.getAttribute('data-kind') === 'file' && state.selection.size > 1;
            label = isMulti ? t('fb_selected_count', state.selection.size) : itemEl.getAttribute('data-name');
        }
        contextMenu.open(x, y, items, label);
    });

    tree.renderRoot();
    load(initialSelect);
}

