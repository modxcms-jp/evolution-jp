// ファイルブラウザのエントリ(構成ルート)。状態(state)を保持し、
// js/*.js の各モジュールへ必要な参照とコールバックだけを渡して結線する。
// モーダル再オープン時はshell.jsがscriptを再実行しないため、初期化を
// グローバル関数として公開し、断片内のインラインscriptから毎回呼び直す。

import { escapeHtml, joinPath, renderBreadcrumb } from './js/utils.js';
import { setMessages, t } from './js/i18n.js';
import { apiUrl, fetchJson, postForm } from './js/api.js';
import { bindMoveDropTarget } from './js/dnd.js';
import { createTree } from './js/tree.js';
import { createGrid } from './js/grid.js';
import { createUpload } from './js/upload.js';
import { createMoveDialog, createPreview } from './js/dialogs.js';
import { createContextMenu } from './js/contextmenu.js';

function init() {
    var root = document.getElementById('evoFileBrowser');
    if (!root || root.dataset.fbInitialized) {
        return;
    }
    root.dataset.fbInitialized = '1';

    // EvoShellモーダル内で開いている場合、モーダルを広げる(:has()未対応ブラウザ対策でJSでクラス付与)。
    // closeModal()側のevoshell:modalcloseで剥がし、次に別種のモーダルが開いても幅へ影響しないようにする
    var shellModalEl = document.getElementById('evoShellModal');
    if (shellModalEl && shellModalEl.contains(root)) {
        shellModalEl.classList.add('evo-fb-modal-wide');
        document.addEventListener('evoshell:modalclose', function () {
            shellModalEl.classList.remove('evo-fb-modal-wide');
        }, { once: true });
    }

    var config = JSON.parse(root.getAttribute('data-config') || '{}');
    setMessages(config.lang || {});
    var initialFolder = typeof config.initialFolder === 'string' ? config.initialFolder : '';
    var initialSelect = typeof config.initialSelect === 'string' ? config.initialSelect : '';
    var initialFallbackTried = false;
    var isInitialLoad = true;

    var state = {
        type: config.type,
        folder: initialFolder,
        rawFolders: [],
        rawFiles: [],
        search: '',
        sortKey: 'name',
        sortDir: 'asc',
        view: localStorage.getItem('evoFbView') || 'grid',
        selection: new Set()
    };

    var pathEl = root.querySelector('[data-role="path"]');
    var treeEl = root.querySelector('[data-role="tree"]');
    var gridEl = root.querySelector('[data-role="grid"]');
    var dropzoneEl = root.querySelector('[data-role="dropzone"]');
    var searchEl = root.querySelector('[data-role="search"]');
    var sortEl = root.querySelector('[data-role="sort"]');
    var viewToggleBtn = root.querySelector('[data-role="view-toggle"]');

    function findFile(name) {
        return state.rawFiles.find(function (f) { return f.name === name; });
    }

    // ファイルブラウザは3通りの埋め込まれ方をし、それぞれ選択結果の返し方が異なる。
    // 上から順に判定し、該当する経路だけを実行する:
    //   1. EvoShellモーダル断片(TV入力・ユーザー写真・モジュールアイコン等)
    //      → shell.jsが公開するグローバルフックを直接呼ぶ
    //   2. chromeless(QuickManager等)のポップアップウィンドウ
    //      → window.opener.SetUrl(旧mcpuk互換の規約)
    //   3. TinyMCEのeditor.windowManager.openUrl()によるiframeダイアログ
    //      → postMessageでmcpuk-picker.jsのonMessageハンドラへ返す
    function pickFile(path) {
        if (typeof window.EvoFileBrowserPick === 'function') {
            window.EvoFileBrowserPick(path);
            return;
        }
        if (window.opener && typeof window.opener.SetUrl === 'function') {
            window.opener.SetUrl(path);
            window.close();
            return;
        }
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ mceAction: 'evoFbPick', url: path }, window.location.origin);
            return;
        }
        // eslint-disable-next-line no-console
        console.log('[filebrowser] picked', path);
    }

    function moveFilesTo(dest, names, sourceFolder) {
        if (dest === sourceFolder || names.length === 0) {
            return;
        }
        postForm(config, state, 'move', { 'files[]': names, dest: dest }, sourceFolder)
            .then(function (result) {
                if (result.errors && result.errors.length) {
                    window.alert(t('fb_move_partial_failed') + '\n' + result.errors.map(function (err) {
                        return err.name + ': ' + err.message;
                    }).join('\n'));
                }
                load();
            })
            .catch(function (err) {
                window.alert(t('fb_move_failed', err.message));
            });
    }

    function performRename(name, newName) {
        if (!newName || newName === name) {
            return;
        }
        postForm(config, state, 'rename', { target: name, newName: newName })
            .then(load)
            .catch(function (err) {
                window.alert(t('fb_rename_failed', err.message));
            });
    }

    function renameEntry(name) {
        performRename(name, window.prompt(t('fb_rename_prompt'), name));
    }

    function deleteEntry(name, isFolder) {
        if (!window.confirm(t(isFolder ? 'fb_confirm_delete_folder' : 'fb_confirm_delete_file', name))) {
            return;
        }
        var fields = isFolder ? { 'folders[]': [name] } : { 'files[]': [name] };
        postForm(config, state, 'delete', fields)
            .then(load)
            .catch(function (err) {
                window.alert(t('fb_delete_failed', err.message));
            });
    }

    var grid = createGrid({
        gridEl: gridEl,
        dropzoneEl: dropzoneEl,
        selectionBarEl: root.querySelector('[data-role="selectionbar"]'),
        selectionCountEl: root.querySelector('[data-role="selection-count"]'),
        state: state,
        config: config,
        onNavigate: navigateTo,
        openPreview: function (name) {
            var file = findFile(name);
            if (file) {
                preview.open(file);
            }
        },
        pickFile: pickFile,
        renameEntry: renameEntry,
        renameInline: performRename,
        deleteEntry: deleteEntry,
        onDropMove: moveFilesTo
    });

    var tree = createTree({
        treeEl: treeEl,
        type: state.type,
        fetchLight: function (path) {
            return fetchJson(apiUrl(config, { action: 'list', type: state.type, folder: path, light: '1' }));
        },
        getCurrentFolder: function () { return state.folder; },
        onNavigate: navigateTo,
        onSelectInPlace: function (name) {
            state.selection.clear();
            state.selection.add(name);
            grid.updateSelectionDom();
        },
        onPickFile: pickFile,
        onDropMove: moveFilesTo
    });

    var moveDialog = createMoveDialog({
        overlayEl: root.querySelector('[data-role="move-overlay"]'),
        pathEl: root.querySelector('[data-role="move-path"]'),
        gridEl: root.querySelector('[data-role="move-grid"]'),
        cancelBtn: root.querySelector('[data-role="move-cancel"]'),
        confirmBtn: root.querySelector('[data-role="move-confirm"]'),
        state: state,
        config: config,
        getSelection: function () { return Array.from(state.selection); },
        onConfirm: moveFilesTo
    });

    var preview = createPreview({
        overlayEl: root.querySelector('[data-role="preview-overlay"]'),
        imgEl: root.querySelector('[data-role="preview-img"]'),
        metaEl: root.querySelector('[data-role="preview-meta"]'),
        closeBtn: root.querySelector('[data-role="preview-close"]'),
        pickBtn: root.querySelector('[data-role="preview-pick"]'),
        onPick: pickFile
    });

    createUpload({
        uploadsEl: root.querySelector('[data-role="uploads"]'),
        fileInputEl: root.querySelector('[data-role="file-input"]'),
        uploadBtn: root.querySelector('[data-action="upload"]'),
        dropzoneEl: dropzoneEl,
        state: state,
        config: config,
        onDone: load
    });

    function navigateTo(path, selectName) {
        state.folder = path;
        load(selectName);
    }

    function renderPath() {
        renderBreadcrumb(pathEl, escapeHtml(state.type), state.folder, navigateTo);
        // パンくずもエクスプローラ同様にドロップ先にする(上位フォルダへの移動)
        pathEl.querySelectorAll('.evo-fb-crumb').forEach(function (btn) {
            bindMoveDropTarget(btn, function () {
                return btn.getAttribute('data-path');
            }, moveFilesTo);
        });
    }

    function load(selectName) {
        var allowInitialFallback = isInitialLoad;
        isInitialLoad = false;
        grid.renderLoading();
        renderPath();
        state.selection.clear();
        grid.updateSelectionBar();

        fetchJson(apiUrl(config, { action: 'list', type: state.type, folder: state.folder }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (data) {
                state.rawFolders = data.folders;
                state.rawFiles = data.files;
                // ツリーのファイルクリック等からの遷移時、対象ファイルを選択状態にする
                if (selectName && findFile(selectName)) {
                    state.selection.add(selectName);
                }
                grid.renderList();
                grid.updateSelectionBar();
                tree.syncWithList(state.folder, data.folders, data.files);
                tree.revealPath(state.folder);
            })
            .catch(function (err) {
                if (allowInitialFallback && !initialFallbackTried && initialFolder !== '') {
                    initialFallbackTried = true;
                    navigateTo('', initialSelect);
                    return;
                }
                grid.renderError(err.message);
            });
    }

    // --- 検索・ソート・表示切替 ---

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            state.search = searchEl.value;
            grid.renderList();
        });
    }

    if (sortEl) {
        sortEl.value = state.sortKey;
        sortEl.addEventListener('change', function () {
            state.sortKey = sortEl.value;
            grid.renderList();
        });
    }

    if (viewToggleBtn) {
        viewToggleBtn.addEventListener('click', function () {
            state.view = state.view === 'grid' ? 'list' : 'grid';
            localStorage.setItem('evoFbView', state.view);
            grid.renderList();
        });
    }

    // --- フォルダ作成 ---

    function createFolder() {
        var name = window.prompt(t('fb_mkdir_prompt'));
        if (!name) {
            return;
        }
        postForm(config, state, 'mkdir', { name: name })
            .then(load)
            .catch(function (err) {
                window.alert(t('fb_mkdir_failed', err.message));
            });
    }

    var mkdirBtn = root.querySelector('[data-action="mkdir"]');
    if (mkdirBtn) {
        mkdirBtn.addEventListener('click', createFolder);
    }

    // --- 一括操作 ---

    root.querySelector('[data-action="clear-selection"]').addEventListener('click', function () {
        state.selection.clear();
        grid.updateSelectionDom();
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
            .then(load)
            .catch(function (err) {
                window.alert(t('fb_delete_failed', err.message));
            });
    }

    function bulkMove() {
        if (state.selection.size === 0) {
            return;
        }
        moveDialog.open(state.folder);
    }

    root.querySelector('[data-action="bulk-delete"]').addEventListener('click', bulkDelete);
    root.querySelector('[data-action="bulk-move"]').addEventListener('click', bulkMove);

    // --- 右クリックメニュー ---
    // 項目は既存の処理(ボタンと同じ関数)を呼ぶだけ。対象は右クリックした所で決める。

    var contextMenu = createContextMenu({ rootEl: root, scrollEl: dropzoneEl });
    var uploadBtn = root.querySelector('[data-action="upload"]');

    function contextMenuItems(itemEl) {
        if (!itemEl) {
            return [
                { label: t('fb_new_folder'), onSelect: createFolder },
                { label: t('fb_upload'), onSelect: function () { uploadBtn.click(); } }
            ];
        }
        var name = itemEl.getAttribute('data-name');
        if (itemEl.getAttribute('data-kind') === 'folder') {
            return [
                { label: t('open'), onSelect: function () { navigateTo(joinPath(state.folder, name)); } },
                { label: t('fb_rename'), onSelect: function () { renameEntry(name); } },
                { label: t('delete'), danger: true, onSelect: function () { deleteEntry(name, true); } }
            ];
        }
        // 選択に入っていないファイルは、エクスプローラと同じく選択をそのファイルだけにする
        if (!state.selection.has(name)) {
            state.selection.clear();
            state.selection.add(name);
            grid.updateSelectionDom();
        }
        if (state.selection.size > 1) {
            return [
                { label: t('move'), onSelect: bulkMove },
                { label: t('delete'), danger: true, onSelect: bulkDelete }
            ];
        }
        var file = findFile(name);
        var items = [{ label: t('fb_pick_this_file'), onSelect: function () { pickFile(itemEl.getAttribute('data-path')); } }];
        if (file) {
            items.push({ label: t('preview'), onSelect: function () { preview.open(file); } });
        }
        items.push(
            { label: t('fb_rename'), onSelect: function () { renameEntry(name); } },
            { label: t('move'), onSelect: bulkMove },
            { label: t('delete'), danger: true, onSelect: function () { deleteEntry(name, false); } }
        );
        return items;
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
        contextMenu.open(x, y, contextMenuItems(itemEl), itemEl ? itemEl.getAttribute('data-name') : t('fb_context_menu'));
    });

    tree.renderRoot();
    load(initialSelect);
}

window.EvoFileBrowserInit = init;
init();
