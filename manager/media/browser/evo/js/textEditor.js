// テキストファイルの表示・編集ダイアログ(管理モードだけ。js/manage.js だけが読む)。
// overlay.js のダイアログに、エレメントの編集画面と同じ形のコードの欄(div.code-editor と textarea.phptextarea)を出し、テーマの CSS をそのまま使う。
// 中身は textarea の value、ファイル名は textContent で入れる(innerHTML を使わない)。
// 保存は saveText へ「開いたときの中身の hash」を付けて送り、今のファイルと違えば(ほかで書き換えられていれば)サーバーが 409 で断る。
// 書き換えたら window.documentDirty = true にする(EvoShell が画面を離れるときに確かめる)。保存・閉じたら false に戻す。

import { t } from './i18n.js';
import { apiUrl, failureMessage, fetchJson, loadFailureMessage, postForm } from './api.js';
import { openOverlay } from './overlay.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl - BrowserView::manageExtras() が作るダイアログの外側の要素
 * @param {object} ctx.config
 * @param {() => void} ctx.onChanged - 保存してから閉じたとき(一覧を読み直してもらう)
 */
export function createTextEditor(ctx) {
    var overlayEl = ctx.overlayEl;
    function el(role) {
        return overlayEl.querySelector('[data-role="' + role + '"]');
    }
    var panelEl = el('text-panel');
    var nameEl = el('text-name');
    var areaEl = el('text-area');
    var wrapEl = el('text-wrap');
    var statusEl = el('text-status');
    var errorEl = el('text-error');
    var closeBtn = el('text-close');
    var saveBtn = el('text-save');

    var closeOverlay = null;
    var target = '';
    var folder = '';
    // 開いたときの(サーバーの)中身の hash と、textarea に入れた直後の値(変更の有無を見分ける基準)
    var hash = '';
    var loadedValue = '';
    var loaded = false;
    var readOnly = false;
    var busy = false;
    var saved = false;
    // 開くたびに増やす。古い読み込みの完了を無視するため
    var generation = 0;

    function showError(message) {
        errorEl.textContent = message;
        errorEl.hidden = !message;
    }

    function setDirty(dirty) {
        window.documentDirty = dirty;
    }

    function isDirty() {
        return loaded && !readOnly && areaEl.value !== loadedValue;
    }

    function updateButtons() {
        saveBtn.disabled = !loaded || readOnly || busy;
    }

    function setBusy(value) {
        busy = value;
        updateButtons();
    }

    // 後始末。overlay.js が閉じたときに 1 度だけ呼ぶ(外から閉じられたときに状態が残ると、次に開けなくなる)
    function release() {
        generation++;
        closeOverlay = null;
        loaded = false;
        readOnly = false;
        busy = false;
        saved = false;
        areaEl.value = '';
        loadedValue = '';
        hash = '';
        nameEl.textContent = '';
        statusEl.textContent = '';
        showError('');
        setDirty(false);
    }

    function close(restoreFocus) {
        if (!closeOverlay) {
            return;
        }
        var changed = saved;
        closeOverlay(restoreFocus);
        if (changed) {
            ctx.onChanged();
        }
    }

    // 変更があるまま閉じるときは確かめる。保存の通信中は閉じない(閉じても保存は進み、結果を受ける先が無くなるため)
    function requestClose() {
        if (busy) {
            return;
        }
        if (isDirty() && !window.confirm(t('fb_text_discard_confirm'))) {
            return;
        }
        close(true);
    }

    function open(name, folderPath) {
        if (closeOverlay) {
            return;
        }
        target = name;
        folder = folderPath;
        var current = ++generation;
        nameEl.textContent = name;
        areaEl.value = '';
        areaEl.readOnly = true;
        loaded = false;
        statusEl.textContent = t('fb_text_loading');
        showError('');
        updateButtons();
        closeOverlay = openOverlay(overlayEl, panelEl, areaEl, { onEscape: requestClose, onClose: release });

        fetchJson(apiUrl(ctx.config, { action: 'readText', folder: folder, file: name }))
            .then(function (data) {
                if (generation !== current) {
                    return;
                }
                areaEl.value = data.content;
                // textarea は改行を LF にそろえるので、入れた後の値を基準にする
                loadedValue = areaEl.value;
                hash = data.hash;
                readOnly = !!data.readOnly;
                areaEl.readOnly = readOnly;
                areaEl.wrap = wrapEl.checked ? 'soft' : 'off';
                loaded = true;
                var reasons = {
                    link: t('fb_err_text_link'),
                    permission: t('fb_text_readonly_write'),
                    encoding: t('fb_text_readonly_encoding', data.encoding)
                };
                statusEl.textContent = reasons[data.readOnlyReason] || '';
                updateButtons();
                areaEl.focus({ preventScroll: true });
            })
            .catch(function (err) {
                if (generation !== current) {
                    return;
                }
                statusEl.textContent = '';
                showError(err && err.fromApi ? err.message : loadFailureMessage(err));
            });
    }

    function save() {
        if (!loaded || readOnly || busy) {
            return;
        }
        var content = areaEl.value;
        var current = generation;
        showError('');
        statusEl.textContent = t('fb_text_saving');
        setBusy(true);
        postForm(ctx.config, null, 'saveText', { target: target, content: content, hash: hash }, folder)
            .then(function (result) {
                if (generation !== current) {
                    return;
                }
                hash = result.hash;
                loadedValue = content;
                saved = true;
                setDirty(isDirty());
                statusEl.textContent = t('fb_text_saved');
                setBusy(false);
            })
            .catch(function (err) {
                if (generation !== current) {
                    return;
                }
                statusEl.textContent = '';
                showError(failureMessage(err, 'fb_text_save_failed'));
                setBusy(false);
                saveBtn.focus({ preventScroll: true });
            });
    }

    areaEl.addEventListener('input', function () {
        setDirty(isDirty());
        if (statusEl.textContent === t('fb_text_saved')) {
            statusEl.textContent = '';
        }
    });
    wrapEl.addEventListener('change', function () {
        areaEl.wrap = wrapEl.checked ? 'soft' : 'off';
    });
    closeBtn.addEventListener('click', requestClose);
    saveBtn.addEventListener('click', save);
    // Ctrl/Cmd + S で保存する(ブラウザの「ページを保存」を出さない)
    areaEl.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            save();
        }
    });

    return { open: open };
}
