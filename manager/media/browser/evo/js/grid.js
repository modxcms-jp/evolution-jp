// 右ペインのファイル/フォルダグリッド。一覧描画・選択(クリック/Ctrl/Shift/矩形選択)・
// D&D移動のドラッグソース/ドロップ先バインドを担う。

import { escapeHtml, formatSize, formatDate, joinPath, folderIcon, fileIcon, ICON_RENAME, ICON_DELETE, ICON_PREVIEW } from './utils.js';
import { bindMoveDropTarget, bindFileDragSource } from './dnd.js';
import { apiUrl } from './api.js';
import { createMarquee } from './marquee.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.gridEl
 * @param {HTMLElement} ctx.dropzoneEl - 矩形選択を受け付ける領域(gridElの親)
 * @param {HTMLElement} ctx.selectionBarEl
 * @param {HTMLElement} ctx.selectionCountEl
 * @param {object} ctx.state - type/folder/selection/rawFolders/rawFiles/search/sortKey/sortDir/view
 * @param {object} ctx.config
 * @param {(path: string) => void} ctx.onNavigate - フォルダへ移動(joinPath済みのpathを渡す)
 * @param {(name: string) => void} ctx.openPreview
 * @param {(path: string) => void} ctx.pickFile
 * @param {(name: string) => void} ctx.renameEntry
 * @param {(name: string, isFolder: boolean) => void} ctx.deleteEntry
 * @param {(dest: string, names: string[], sourceFolder: string) => void} ctx.onDropMove
 */
export function createGrid(ctx) {
    var gridEl = ctx.gridEl;
    var state = ctx.state;

    // Shift+クリック範囲選択の起点(直前にクリックしたファイル名)
    var lastClickedFile = null;

    function itemActionsHtml(isFolder) {
        var preview = isFolder ? '' :
            '  <button type="button" class="evo-fb-icon-btn" data-role="preview" title="プレビュー">' + ICON_PREVIEW + '</button>';
        return '' +
            '<span class="evo-fb-item-actions">' +
            preview +
            '  <button type="button" class="evo-fb-icon-btn" data-role="rename" title="名前を変更">' + ICON_RENAME + '</button>' +
            '  <button type="button" class="evo-fb-icon-btn evo-fb-icon-danger" data-role="delete" title="削除">' + ICON_DELETE + '</button>' +
            '</span>';
    }

    function folderItemHtml(folder) {
        return '' +
            '<div class="evo-fb-item evo-fb-item-folder" data-name="' + escapeHtml(folder.name) + '" data-kind="folder">' +
            '  <button type="button" class="evo-fb-item-main">' +
            '    <span class="evo-fb-thumb evo-fb-thumb-folder" aria-hidden="true">' + folderIcon(40) + '</span>' +
            '    <span class="evo-fb-name">' + escapeHtml(folder.name) + '</span>' +
            '  </button>' +
            itemActionsHtml(true) +
            '</div>';
    }

    function thumbSrc(file) {
        if (file.thumb === 'api') {
            return apiUrl(ctx.config, { action: 'thumb', type: state.type, folder: state.folder, file: file.name });
        }
        return file.thumb;
    }

    function fileItemHtml(file) {
        var src = thumbSrc(file);
        var thumb = src
            ? '<img class="evo-fb-thumb-img" src="' + escapeHtml(src) + '" alt="" loading="lazy">'
            : fileIcon(40);
        var meta = state.view === 'list'
            ? '<span class="evo-fb-meta"><span class="evo-fb-meta-size">' + formatSize(file.size) + '</span><span class="evo-fb-meta-date">' + formatDate(file.mtime) + '</span></span>'
            : '';

        return '' +
            '<div class="evo-fb-item evo-fb-item-file' + (state.selection.has(file.name) ? ' is-checked' : '') + '" draggable="true" data-name="' + escapeHtml(file.name) + '" data-path="' + escapeHtml(file.path) + '" data-kind="file">' +
            '  <button type="button" class="evo-fb-item-thumb-btn" data-role="thumb">' +
            '    <span class="evo-fb-thumb" aria-hidden="true">' + thumb + '</span>' +
            '  </button>' +
            '  <button type="button" class="evo-fb-item-name-btn" data-role="name">' +
            '    <span class="evo-fb-name">' + escapeHtml(file.name) + '</span>' +
            meta +
            '  </button>' +
            itemActionsHtml(false) +
            '</div>';
    }

    function selectRange(fromName, toName) {
        var visible = Array.prototype.map.call(
            gridEl.querySelectorAll('.evo-fb-item-file'),
            function (el) { return el.getAttribute('data-name'); }
        );
        var from = visible.indexOf(fromName);
        var to = visible.indexOf(toName);
        if (from === -1 || to === -1) {
            state.selection.clear();
            state.selection.add(toName);
            return;
        }
        var start = Math.min(from, to);
        var end = Math.max(from, to);
        state.selection.clear();
        for (var i = start; i <= end; i++) {
            state.selection.add(visible[i]);
        }
    }

    // サムネイルクリック→プレビューも、ダブルクリック検知のため一呼吸おく
    var pendingPreviewTimer = null;

    function cancelPendingPreview() {
        if (pendingPreviewTimer) {
            clearTimeout(pendingPreviewTimer);
            pendingPreviewTimer = null;
        }
    }

    function schedulePendingPreview(name) {
        pendingPreviewTimer = setTimeout(function () {
            pendingPreviewTimer = null;
            ctx.openPreview(name);
        }, 250);
    }

    // --- インライン名前変更(エクスプローラの「選択済みをもう一度クリック」相当) ---

    var pendingRenameTimer = null;

    function cancelPendingRename() {
        if (pendingRenameTimer) {
            clearTimeout(pendingRenameTimer);
            pendingRenameTimer = null;
        }
    }

    function schedulePendingRename(item, name) {
        pendingRenameTimer = setTimeout(function () {
            pendingRenameTimer = null;
            startInlineRename(item, name);
        }, 450);
    }

    function startInlineRename(item, name) {
        var nameEl = item.querySelector('.evo-fb-name');
        if (!nameEl || item.querySelector('.evo-fb-rename-input')) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'evo-fb-rename-input';
        input.value = name;
        nameEl.replaceWith(input);
        input.focus();

        var dot = name.lastIndexOf('.');
        if (dot > 0) {
            input.setSelectionRange(0, dot);
        } else {
            input.select();
        }

        var settled = false;
        function restoreLabel() {
            var span = document.createElement('span');
            span.className = 'evo-fb-name';
            span.textContent = name;
            input.replaceWith(span);
        }
        function settle() {
            if (settled) {
                return;
            }
            settled = true;
            var newName = input.value.trim();
            if (newName && newName !== name) {
                ctx.renameInline(name, newName);
            } else {
                restoreLabel();
            }
        }
        input.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        input.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Enter') {
                e.preventDefault();
                settle();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                settled = true;
                restoreLabel();
            }
        });
        input.addEventListener('blur', settle);
    }

    function updateSelectionBar() {
        var count = state.selection.size;
        ctx.selectionBarEl.hidden = count === 0;
        ctx.selectionCountEl.textContent = count + '件選択中';
    }

    // state.selectionをグリッドDOM(ハイライト)へ反映する
    function updateSelectionDom() {
        gridEl.querySelectorAll('.evo-fb-item-file').forEach(function (item) {
            item.classList.toggle('is-checked', state.selection.has(item.getAttribute('data-name')));
        });
        updateSelectionBar();
    }

    function bindItemEvents() {
        gridEl.querySelectorAll('.evo-fb-item').forEach(function (item) {
            var isFolder = item.getAttribute('data-kind') === 'folder';
            var name = item.getAttribute('data-name');

            if (isFolder) {
                item.querySelector('.evo-fb-item-main').addEventListener('click', function () {
                    ctx.onNavigate(joinPath(state.folder, name));
                });
                bindMoveDropTarget(item, function () { return joinPath(state.folder, name); }, ctx.onDropMove);
            } else {
                bindFileDragSource(
                    item,
                    function () { return state.selection.has(name) ? Array.from(state.selection) : [name]; },
                    function () { return state.folder; }
                );

                var thumbBtn = item.querySelector('[data-role="thumb"]');
                var nameBtn = item.querySelector('[data-role="name"]');

                // サムネイルは選択に関与せず、常にプレビュー(拡大+「このファイルを選択」)を開く。
                // ダブルクリックは選択確定のショートカット。プレビューは即座に開くと
                // オーバーレイが2回目のクリックを奪いdblclickが成立しなくなるため、
                // 一呼吸おいてから開く(ダブルクリック時はcancelPendingPreviewで打ち消す)
                thumbBtn.addEventListener('click', function () {
                    cancelPendingRename();
                    schedulePendingPreview(name);
                });
                thumbBtn.addEventListener('dblclick', function () {
                    cancelPendingRename();
                    cancelPendingPreview();
                    ctx.pickFile(item.getAttribute('data-path'));
                });

                // エクスプローラ準拠: ファイル名クリック=選択、Ctrl/Cmd+クリック=追加選択、
                // Shift+クリック=直前の選択位置から範囲選択、ダブルクリック=選択確定、
                // 単独選択中の項目を修飾キーなしでもう一度クリック=名前をインライン編集
                nameBtn.addEventListener('click', function (e) {
                    cancelPendingRename();
                    var wasSoleSelected = !e.shiftKey && !e.ctrlKey && !e.metaKey
                        && state.selection.size === 1 && state.selection.has(name);

                    if (e.shiftKey && lastClickedFile) {
                        selectRange(lastClickedFile, name);
                    } else if (e.ctrlKey || e.metaKey) {
                        if (state.selection.has(name)) {
                            state.selection.delete(name);
                        } else {
                            state.selection.add(name);
                        }
                    } else {
                        state.selection.clear();
                        state.selection.add(name);
                    }
                    lastClickedFile = name;
                    updateSelectionDom();

                    if (wasSoleSelected) {
                        // ダブルクリック(選択確定)と区別するため、一呼吸おいてから編集を開始する
                        schedulePendingRename(item, name);
                    }
                });
                nameBtn.addEventListener('dblclick', function () {
                    cancelPendingRename();
                    ctx.pickFile(item.getAttribute('data-path'));
                });

                item.querySelector('[data-role="preview"]').addEventListener('click', function (e) {
                    e.stopPropagation();
                    ctx.openPreview(name);
                });
            }

            item.querySelector('[data-role="rename"]').addEventListener('click', function (e) {
                e.stopPropagation();
                ctx.renameEntry(name, isFolder);
            });
            item.querySelector('[data-role="delete"]').addEventListener('click', function (e) {
                e.stopPropagation();
                ctx.deleteEntry(name, isFolder);
            });
        });
    }

    function applyFilterAndSort() {
        var term = state.search.trim().toLowerCase();

        var folders = state.rawFolders.filter(function (f) {
            return !term || f.name.toLowerCase().indexOf(term) !== -1;
        });
        var files = state.rawFiles.filter(function (f) {
            return !term || f.name.toLowerCase().indexOf(term) !== -1;
        });

        var dir = state.sortDir === 'asc' ? 1 : -1;
        var key = state.sortKey;
        function cmp(a, b) {
            if (key === 'mtime' || key === 'size') {
                return (a[key] - b[key]) * dir;
            }
            return a.name.localeCompare(b.name, 'ja') * dir;
        }
        folders = folders.slice().sort(cmp);
        files = files.slice().sort(cmp);

        return { folders: folders, files: files };
    }

    function renderList() {
        var data = applyFilterAndSort();
        gridEl.classList.toggle('evo-fb-grid--list', state.view === 'list');

        if (data.folders.length === 0 && data.files.length === 0) {
            gridEl.innerHTML = '<div class="evo-fb-empty">' + (state.search ? '一致するファイルがありません' : 'このフォルダは空です') + '</div>';
            return;
        }

        gridEl.innerHTML = data.folders.map(folderItemHtml).join('') + data.files.map(fileItemHtml).join('');
        bindItemEvents();
    }

    function renderError(message) {
        gridEl.innerHTML = '<div class="evo-fb-empty evo-fb-error">読み込みに失敗しました: ' + escapeHtml(message) + '</div>';
    }

    function renderLoading() {
        gridEl.innerHTML = '<div class="evo-fb-empty">読み込み中...</div>';
    }

    createMarquee({
        container: ctx.dropzoneEl,
        getItems: function () { return gridEl.querySelectorAll('.evo-fb-item-file'); },
        getName: function (item) { return item.getAttribute('data-name'); },
        isAdditiveKey: function (e) { return e.ctrlKey || e.metaKey; },
        getSelected: function () { return Array.from(state.selection); },
        onChange: function (names) {
            state.selection = new Set(names);
            updateSelectionDom();
        }
    });

    return {
        renderList: renderList,
        renderLoading: renderLoading,
        renderError: renderError,
        updateSelectionDom: updateSelectionDom,
        updateSelectionBar: updateSelectionBar
    };
}
