// 右ペインのファイル/フォルダグリッド。一覧描画・選択(クリック/Ctrl/Shift/矩形選択)・
// D&D移動のドラッグソース/ドロップ先バインドを担う。

import { escapeHtml, formatSize, formatDate, joinPath, folderIcon, fileIcon, ICON_RENAME, ICON_DELETE, ICON_PREVIEW } from './utils.js';
import { t } from './i18n.js';
import { bindMoveDropTarget, bindFileDragSource } from './dnd.js';
import { apiUrl, loadFailureMessage } from './api.js';
import { createMarquee } from './marquee.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.gridEl
 * @param {HTMLElement} ctx.dropzoneEl - 矩形選択を受け付ける領域(gridElの親)
 * @param {HTMLElement} ctx.selectionBarEl
 * @param {HTMLElement} ctx.selectionCountEl
 * @param {object} ctx.state
 * @param {object} ctx.config
 * @param {(path: string) => void} ctx.onNavigate - フォルダへ移動(joinPath済みのpathを渡す)
 * @param {(name: string) => void} ctx.openPreview
 * @param {(name: string) => void} ctx.openFile - ダブルクリックで開く(入口の behaviors.openFile が決める)
 * @param {(name: string) => void} ctx.renameEntry
 * @param {(name: string, isFolder: boolean) => void} ctx.deleteEntry
 * @param {(dest: string, names: string[], sourceFolder: string) => void} ctx.onDropMove
 * @param {(count: number) => void} ctx.onSelectionChange - 選択バーを更新するたびに、選択の件数を通知する
 * @param {(name: string) => boolean} ctx.isCut - 切り取り中のファイルか(一覧で薄く表示する)
 * @param {(key: string) => void} ctx.onSort - 一覧表示の見出しを押したとき
 */
export function createGrid(ctx) {
    var gridEl = ctx.gridEl;
    var state = ctx.state;

    // Shift+クリック範囲選択の起点(直前にクリックしたファイル名)
    var lastClickedFile = null;

    function itemActionsHtml(isFolder) {
        var preview = isFolder ? '' :
            '  <button type="button" class="evo-fb-icon-btn" data-role="preview" title="' + escapeHtml(t('preview')) + '">' + ICON_PREVIEW + '</button>';
        return '' +
            '<span class="evo-fb-item-actions">' +
            preview +
            '  <button type="button" class="evo-fb-icon-btn" data-role="rename" title="' + escapeHtml(t('fb_rename')) + '">' + ICON_RENAME + '</button>' +
            '  <button type="button" class="evo-fb-icon-btn evo-fb-icon-danger" data-role="delete" title="' + escapeHtml(t('delete')) + '">' + ICON_DELETE + '</button>' +
            '</span>';
    }

    var LIST_COLUMNS = [
        { key: 'name', label: 'fb_col_name' },
        { key: 'mtime', label: 'fb_col_mtime' },
        { key: 'type', label: 'fb_col_type' },
        { key: 'size', label: 'fb_col_size' }
    ];

    function extensionOf(name) {
        var dot = name.lastIndexOf('.');
        return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
    }

    function typeLabel(entry, isFolder) {
        if (isFolder) {
            return t('fb_type_folder');
        }
        var ext = extensionOf(entry.name);
        return ext ? t('fb_type_file', ext.toUpperCase()) : t('fb_type_file_noext');
    }

    function listMetaHtml(entry, isFolder) {
        if (state.view !== 'list') {
            return '';
        }
        return '<span class="evo-fb-meta">' +
            '<span class="evo-fb-meta-date">' + (entry.mtime ? formatDate(entry.mtime) : '') + '</span>' +
            '<span class="evo-fb-meta-type">' + escapeHtml(typeLabel(entry, isFolder)) + '</span>' +
            '<span class="evo-fb-meta-size">' + (isFolder ? '' : formatSize(entry.size)) + '</span>' +
            '</span>';
    }

    function listHeadHtml() {
        return '<div class="evo-fb-list-head" role="row">' + LIST_COLUMNS.map(function (col) {
            var active = state.sortKey === col.key;
            var ariaSort = active ? (state.sortDir === 'asc' ? 'ascending' : 'descending') : 'none';
            var arrow = active ? (state.sortDir === 'asc' ? ' ▲' : ' ▼') : '';
            return '<button type="button" class="evo-fb-list-col evo-fb-list-col-' + col.key + (active ? ' is-active' : '') + '"' +
                ' data-sort="' + col.key + '" role="columnheader" aria-sort="' + ariaSort + '">' +
                escapeHtml(t(col.label)) + '<span class="evo-fb-list-sort" aria-hidden="true">' + arrow + '</span></button>';
        }).join('') + '</div>';
    }

    function folderItemHtml(folder) {
        return '' +
            '<div class="evo-fb-item evo-fb-item-folder" data-name="' + escapeHtml(folder.name) + '" data-kind="folder">' +
            '  <button type="button" class="evo-fb-item-main">' +
            '    <span class="evo-fb-thumb evo-fb-thumb-folder" aria-hidden="true">' + folderIcon(40) + '</span>' +
            '    <span class="evo-fb-name">' + escapeHtml(folder.name) + '</span>' +
            listMetaHtml(folder, true) +
            '  </button>' +
            itemActionsHtml(true) +
            '</div>';
    }

    function thumbSrc(file) {
        if (file.thumb === 'api') {
            return apiUrl(ctx.config, { action: 'thumb', folder: state.folder, file: file.name, v: file.mtime });
        }
        return file.thumb;
    }

    function fileItemHtml(file) {
        var src = thumbSrc(file);
        var thumb = src
            ? '<img class="evo-fb-thumb-img" src="' + escapeHtml(src) + '" alt="" loading="lazy">'
            : fileIcon(40);
        var meta = listMetaHtml(file, false);

        return '' +
            '<div class="evo-fb-item evo-fb-item-file' + (state.selection.has(file.name) ? ' is-checked' : '') + (ctx.isCut(file.name) ? ' is-cut' : '') + '" draggable="true" data-name="' + escapeHtml(file.name) + '" data-kind="file">' +
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
        // dblclick の前に click が 2 回続くため、1 回目の予約を残すと、ファイルを開いたあとにプレビューが重なって開く
        cancelPendingPreview();
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
        ctx.selectionCountEl.textContent = t('fb_selected_count', count);
        ctx.onSelectionChange(count);
    }

    // state.selectionをグリッドDOM(ハイライト)へ反映する
    function updateSelectionDom() {
        gridEl.querySelectorAll('.evo-fb-item-file').forEach(function (item) {
            item.classList.toggle('is-checked', state.selection.has(item.getAttribute('data-name')));
        });
        updateSelectionBar();
    }

    // 切り取り中の表示を、クリップボードの今の状態に合わせる
    function updateCutDom() {
        gridEl.querySelectorAll('.evo-fb-item-file').forEach(function (item) {
            item.classList.toggle('is-cut', ctx.isCut(item.getAttribute('data-name')));
        });
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

                // サムネイルは選択に関与せず、常にプレビュー(拡大と、入口の JS が足すボタン)を開く。
                // ダブルクリックはファイルを開くショートカット(ピッカーなら選択の確定)。プレビューは即座に開くと
                // オーバーレイが2回目のクリックを奪いdblclickが成立しなくなるため、
                // 一呼吸おいてから開く(ダブルクリック時はcancelPendingPreviewで打ち消す)
                thumbBtn.addEventListener('click', function () {
                    cancelPendingRename();
                    schedulePendingPreview(name);
                });
                thumbBtn.addEventListener('dblclick', function () {
                    cancelPendingRename();
                    cancelPendingPreview();
                    ctx.openFile(name);
                });

                // エクスプローラ準拠: ファイル名クリック=選択、Ctrl/Cmd+クリック=追加選択、
                // Shift+クリック=直前の選択位置から範囲選択、ダブルクリック=ファイルを開く、
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
                        // ダブルクリックと区別するため、一呼吸おく
                        schedulePendingRename(item, name);
                    }
                });
                nameBtn.addEventListener('dblclick', function () {
                    cancelPendingRename();
                    ctx.openFile(name);
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
                return ((a[key] || 0) - (b[key] || 0)) * dir;
            }
            if (key === 'type') {
                var byType = extensionOf(a.name).localeCompare(extensionOf(b.name), 'ja');
                if (byType !== 0) {
                    return byType * dir;
                }
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
            gridEl.innerHTML = '<div class="evo-fb-empty">' + escapeHtml(t(state.search ? 'fb_no_match' : 'fb_empty_folder')) + '</div>';
            return;
        }

        gridEl.innerHTML = (state.view === 'list' ? listHeadHtml() : '') +
            data.folders.map(folderItemHtml).join('') + data.files.map(fileItemHtml).join('');
        gridEl.querySelectorAll('.evo-fb-list-head [data-sort]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ctx.onSort(btn.getAttribute('data-sort'));
            });
        });
        bindItemEvents();
    }

    function renderError(err) {
        gridEl.innerHTML = '<div class="evo-fb-empty evo-fb-error">' + escapeHtml(loadFailureMessage(err)) + '</div>';
    }

    function renderLoading() {
        gridEl.innerHTML = '<div class="evo-fb-empty">' + escapeHtml(t('fb_loading')) + '</div>';
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
        updateCutDom: updateCutDom,
        updateSelectionBar: updateSelectionBar
    };
}
