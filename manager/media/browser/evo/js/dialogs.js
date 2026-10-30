// 単純なオーバーレイ型ダイアログ2つ: 移動先フォルダ選択・プレビュー拡大。
// どちらもEvoShellのモーダルではなく、filebrowser.js自身が描画する軽量オーバーレイ。

import { escapeHtml, formatSize, formatDate, joinPath, folderIcon, renderBreadcrumb } from './utils.js';
import { apiUrl, fetchJson } from './api.js';

/**
 * 移動先フォルダ選択ダイアログ。既存のlist APIを再利用したミニブラウザ(フォルダのみ)。
 *
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl
 * @param {HTMLElement} ctx.pathEl
 * @param {HTMLElement} ctx.gridEl
 * @param {HTMLElement} ctx.cancelBtn
 * @param {HTMLElement} ctx.confirmBtn
 * @param {object} ctx.state
 * @param {object} ctx.config
 * @param {() => string[]} ctx.getSelection
 * @param {(dest: string, names: string[], sourceFolder: string) => void} ctx.onConfirm
 */
export function createMoveDialog(ctx) {
    var moveFolder = '';
    var sourceFolder = '';

    function folderRowHtml(name) {
        return '' +
            '<button type="button" class="evo-fb-item evo-fb-item-folder" data-name="' + escapeHtml(name) + '">' +
            '  <span class="evo-fb-thumb evo-fb-thumb-folder" aria-hidden="true">' + folderIcon(40) + '</span>' +
            '  <span class="evo-fb-name">' + escapeHtml(name) + '</span>' +
            '</button>';
    }

    function load() {
        renderBreadcrumb(ctx.pathEl, escapeHtml(ctx.state.type), moveFolder, function (path) {
            moveFolder = path;
            load();
        });
        ctx.gridEl.innerHTML = '<div class="evo-fb-empty">読み込み中...</div>';

        fetchJson(apiUrl(ctx.config, { action: 'list', type: ctx.state.type, folder: moveFolder }))
            .then(function (data) {
                if (data.folders.length === 0) {
                    ctx.gridEl.innerHTML = '<div class="evo-fb-empty">サブフォルダはありません</div>';
                    return;
                }
                ctx.gridEl.innerHTML = data.folders.map(function (folder) {
                    return folderRowHtml(folder.name);
                }).join('');
                ctx.gridEl.querySelectorAll('.evo-fb-item-folder').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        moveFolder = joinPath(moveFolder, btn.getAttribute('data-name'));
                        load();
                    });
                });
            });
    }

    function open(currentFolder) {
        moveFolder = currentFolder;
        sourceFolder = currentFolder;
        ctx.overlayEl.hidden = false;
        load();
    }

    ctx.cancelBtn.addEventListener('click', function () {
        ctx.overlayEl.hidden = true;
    });

    ctx.confirmBtn.addEventListener('click', function () {
        ctx.overlayEl.hidden = true;
        ctx.onConfirm(moveFolder, ctx.getSelection(), sourceFolder);
    });

    return { open: open };
}

/**
 * ファイルのプレビュー拡大ダイアログ。実寸URL・寸法・サイズを表示する。
 *
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl
 * @param {HTMLElement} ctx.imgEl
 * @param {HTMLElement} ctx.metaEl
 * @param {HTMLElement} ctx.closeBtn
 * @param {HTMLElement} ctx.pickBtn
 * @param {(path: string) => void} ctx.onPick
 */
export function createPreview(ctx) {
    var currentPath = '';

    function open(file) {
        ctx.imgEl.src = file.url;
        ctx.imgEl.alt = file.name;
        var dims = file.width ? (file.width + ' × ' + file.height + 'px') : '';
        ctx.metaEl.innerHTML = '' +
            '<div class="evo-fb-preview-name">' + escapeHtml(file.name) + '</div>' +
            '<div>' + [dims, formatSize(file.size), formatDate(file.mtime)].filter(Boolean).join(' / ') + '</div>' +
            '<div class="evo-fb-preview-url">' + escapeHtml(file.path) + '</div>';
        currentPath = file.path;
        ctx.overlayEl.hidden = false;
    }

    ctx.closeBtn.addEventListener('click', function () {
        ctx.overlayEl.hidden = true;
    });

    ctx.pickBtn.addEventListener('click', function () {
        ctx.overlayEl.hidden = true;
        ctx.onPick(currentPath);
    });

    return { open: open };
}
