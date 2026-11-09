// 単純なオーバーレイ型ダイアログ2つ: 移動先フォルダ選択・プレビュー拡大。
// どちらもEvoShellのモーダルではなく、filebrowser.js自身が描画する軽量オーバーレイ。
// フォーカスの移動・Tabの循環・Escで閉じる・フォーカスを戻す、は overlay.js の openOverlay に任せる。

import { escapeHtml, formatSize, formatDate, joinPath, folderIcon, renderBreadcrumb, withVersion } from './utils.js';
import { t } from './i18n.js';
import { openOverlay, restoreFocus } from './overlay.js';
import { apiUrl, fetchJson, loadFailureMessage } from './api.js';

/**
 * 移動先フォルダ選択ダイアログ。既存のlist APIを再利用したミニブラウザ(フォルダのみ)。
 *
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl
 * @param {HTMLElement} ctx.panelEl - ダイアログ本体(Tabを循環させる範囲)
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
    var closeOverlay = null;

    function folderRowHtml(name) {
        return '' +
            '<button type="button" class="evo-fb-item evo-fb-item-folder" data-name="' + escapeHtml(name) + '">' +
            '  <span class="evo-fb-thumb evo-fb-thumb-folder" aria-hidden="true">' + folderIcon(40) + '</span>' +
            '  <span class="evo-fb-name">' + escapeHtml(name) + '</span>' +
            '</button>';
    }

    function load() {
        // 押したボタンはパンくずと一覧の描き直しで DOM から消える。ダイアログ内にフォーカスがあったかを先に覚える
        var hadFocus = ctx.panelEl.contains(document.activeElement);
        renderBreadcrumb(ctx.pathEl, escapeHtml(ctx.config.rootLabel), moveFolder, function (path) {
            moveFolder = path;
            load();
        });
        ctx.gridEl.innerHTML = '<div class="evo-fb-empty">' + escapeHtml(t('fb_loading')) + '</div>';
        // 描き直しでフォーカスが body に落ちたら、今いるフォルダ(パンくずの最後)へ戻す
        if (hadFocus && document.activeElement === document.body) {
            var crumbs = ctx.pathEl.querySelectorAll('.evo-fb-crumb');
            restoreFocus(crumbs[crumbs.length - 1]);
        }

        fetchJson(apiUrl(ctx.config, { action: 'list', folder: moveFolder }))
            .then(function (data) {
                if (data.folders.length === 0) {
                    ctx.gridEl.innerHTML = '<div class="evo-fb-empty">' + escapeHtml(t('fb_no_subfolders')) + '</div>';
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
            })
            .catch(function (err) {
                ctx.gridEl.innerHTML = '<div class="evo-fb-empty evo-fb-error">' + escapeHtml(loadFailureMessage(err)) + '</div>';
            });
    }

    function open(currentFolder) {
        moveFolder = currentFolder;
        sourceFolder = currentFolder;
        if (closeOverlay) {
            closeOverlay(false);
        }
        // パンくずを描いてから開く(初期フォーカスは中の最初の要素)
        load();
        closeOverlay = openOverlay(ctx.overlayEl, ctx.panelEl, null);
    }

    // 閉じて、開く前にフォーカスのあった要素へ戻す。Escは openOverlay が同じ動きで閉じる
    function closeAndRestore() {
        if (closeOverlay) {
            var fn = closeOverlay;
            closeOverlay = null;
            fn(true);
        }
    }

    ctx.cancelBtn.addEventListener('click', closeAndRestore);

    ctx.confirmBtn.addEventListener('click', function () {
        closeAndRestore();
        ctx.onConfirm(moveFolder, ctx.getSelection(), sourceFolder);
    });

    return { open: open };
}

/**
 * ファイルのプレビュー拡大ダイアログ。実寸URL・寸法・サイズを表示する。
 * 画像の編集のほかに足すボタン(ピッカーの「このファイルを選択」など)は、入口の JS が getActions() で決める。
 *
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl
 * @param {HTMLElement} ctx.panelEl - ダイアログ本体(Tabを循環させる範囲)
 * @param {HTMLElement} ctx.imgEl
 * @param {HTMLElement} [ctx.videoEl] - 動画のプレーヤー(一覧の応答の media が video のファイルだけ出す)
 * @param {HTMLElement} [ctx.audioEl] - 音声のプレーヤー(media が audio のファイルだけ出す)
 * @param {HTMLElement} ctx.metaEl
 * @param {HTMLElement} ctx.closeBtn
 * @param {HTMLElement} ctx.actionsEl
 * @param {HTMLElement} ctx.editBtn - 画像の編集を開くボタン(編集できるファイルのときだけ出す)
 * @param {(file: object) => Array<{label: string, primary?: boolean, onSelect: () => void}>} ctx.getActions - 足すボタン。押すとプレビューを閉じてから onSelect を呼ぶ
 * @param {(file: object) => boolean} ctx.canEdit
 * @param {(file: object) => void} ctx.onEdit - 編集のダイアログを開く。プレビューは開いたまま(編集をやめたら戻れる)
 */
export function createPreview(ctx) {
    var currentFile = null;
    var closeOverlay = null;

    // 画像を出すのは、サーバーが画像と決めたファイル(一覧の応答の image)だけ(.php を GET で実行したり、大きなファイルを丸ごと読んだりしない)
    ctx.imgEl.addEventListener('error', function () {
        ctx.imgEl.hidden = true;
    });

    // 動画・音声は公開 URL をそのままプレーヤーへ渡す(一覧の応答の media が video・audio のものだけ)。閉じるときは止めて src を外す
    function setPlayer(el, file, kind) {
        if (!el) {
            return;
        }
        if (file.url && file.media === kind) {
            el.hidden = false;
            el.src = withVersion(file.url, file.mtime);
            return;
        }
        stopPlayer(el);
    }

    function stopPlayer(el) {
        if (!el) {
            return;
        }
        el.pause();
        el.removeAttribute('src');
        el.load();
        el.hidden = true;
    }

    function renderActions(file) {
        ctx.actionsEl.textContent = '';
        ctx.getActions(file).forEach(function (action) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'evo-fb-btn' + (action.primary ? ' evo-fb-btn-primary' : '');
            btn.textContent = action.label;
            btn.addEventListener('click', function () {
                closeWith(false);
                action.onSelect();
            });
            ctx.actionsEl.appendChild(btn);
        });
    }

    function open(file) {
        currentFile = file;
        // 上書きしたあとに古い画像がキャッシュから出ないよう、更新時刻を付けて読む
        if (file.url && file.image) {
            ctx.imgEl.hidden = false;
            ctx.imgEl.src = withVersion(file.url, file.mtime);
        } else {
            ctx.imgEl.hidden = true;
            ctx.imgEl.removeAttribute('src');
        }
        setPlayer(ctx.videoEl, file, 'video');
        setPlayer(ctx.audioEl, file, 'audio');
        ctx.editBtn.hidden = !ctx.canEdit(file);
        renderActions(file);
        ctx.imgEl.alt = file.name;
        var dims = file.width ? (file.width + ' × ' + file.height + 'px') : '';
        ctx.metaEl.innerHTML = '' +
            '<div class="evo-fb-preview-name">' + escapeHtml(file.name) + '</div>' +
            '<div>' + [dims, formatSize(file.size), formatDate(file.mtime)].filter(Boolean).join(' / ') + '</div>' +
            (file.path ? '<div class="evo-fb-preview-url">' + escapeHtml(file.path) + '</div>' : '');
        if (closeOverlay) {
            closeOverlay(false);
        }
        closeOverlay = openOverlay(ctx.overlayEl, ctx.panelEl, ctx.closeBtn, { onEscape: closeAndRestore });
    }

    // 閉じる。restoreFocusToOpener が true のときだけ、開く前にフォーカスのあった要素へ戻す
    function closeWith(restoreFocusToOpener) {
        if (!closeOverlay) {
            return;
        }
        var fn = closeOverlay;
        closeOverlay = null;
        stopPlayer(ctx.videoEl);
        stopPlayer(ctx.audioEl);
        fn(restoreFocusToOpener);
    }

    function closeAndRestore() {
        closeWith(true);
    }

    // 外から呼ぶ close は、フォーカスを戻さない(呼び出し側が閉じたあとにフォーカスを決める)
    function close() {
        closeWith(false);
    }

    ctx.closeBtn.addEventListener('click', closeAndRestore);

    ctx.editBtn.addEventListener('click', function () {
        if (currentFile) {
            ctx.onEdit(currentFile);
        }
    });

    return { open: open, close: close };
}
