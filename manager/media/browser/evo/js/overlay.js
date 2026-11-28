// ファイルブラウザのダイアログの共通の振る舞い。外側の要素は <dialog> で、showModal() で最前面(top layer)に出す。
// ファイルブラウザがシェルのモーダルの中にあっても、ウィンドウ全体に重なり、見出しの帯をつかんでウィンドウのどこへでも動かせる。
// Tab の循環と背後の操作の無効化はブラウザに任せる。ここで持つのは、開いたときのフォーカス・Esc と見出しの ×(.evo-titlebar-close)で
// そのダイアログだけを閉じる・閉じたらフォーカスを戻す、の 3 つ。
// 管理画面のモーダルが閉じられたとき(evoshell:modalclose)と、メインの領域が別の画面へ差し替わるとき(evoshell:unload)は、開いているダイアログを閉じる。
// プレビューの上に編集のダイアログが重なるように、複数が同時に開くことがある(後から開いたものが上。Esc は上のものだけが受ける)。

/**
 * フォーカスを戻す(スクロールさせない)。右クリックメニューとダイアログで共通。
 * 戻せるのは、まだ画面にあって、フォーカスできる要素だけ
 *
 * @param {HTMLElement|null} el
 */
export function restoreFocus(el) {
    if (el && el.isConnected && typeof el.focus === 'function') {
        el.focus({ preventScroll: true });
    }
}

var FOCUSABLE = 'button, input, select, textarea, a[href], [tabindex], video[controls], audio[controls]';

// 画面に出ていて、フォーカスできる要素(開いたときの最初のフォーカスを決めるため)
function focusableIn(panelEl) {
    return Array.from(panelEl.querySelectorAll(FOCUSABLE)).filter(function (el) {
        return !el.disabled && el.tabIndex >= 0 && el.getClientRects().length > 0;
    });
}

/**
 * ダイアログを開く。
 *
 * @param {HTMLDialogElement} overlayEl - 外側の <dialog>
 * @param {HTMLElement} panelEl - ダイアログ本体(見出しの帯と中身)
 * @param {HTMLElement|null} initialEl - 開いたときにフォーカスする要素(省略時は × を除く中の最初の要素)
 * @param {{onEscape?: () => void, onClose?: () => void}} [options] - onEscape: Esc と × の処理(省略時はフォーカスを戻して閉じる)。
 *   onClose: 閉じたときに 1 度だけ呼ぶ後始末
 * @returns {(restoreFocusToOpener?: boolean) => void} 閉じる関数。true を渡すと、開く前にフォーカスのあった要素へ戻す
 */
export function openOverlay(overlayEl, panelEl, initialEl, options) {
    var onEscape = options && options.onEscape;
    var onClose = options && options.onClose;
    var returnEl = document.activeElement;
    var closeBtn = panelEl.querySelector('.evo-titlebar-close');
    var closed = false;

    function close(restoreFocusToOpener) {
        if (closed) {
            return;
        }
        closed = true;
        overlayEl.removeEventListener('keydown', onKeydown);
        overlayEl.removeEventListener('cancel', onCancel);
        overlayEl.removeEventListener('close', onNativeClose);
        if (closeBtn) {
            closeBtn.removeEventListener('click', dismiss);
        }
        document.removeEventListener('evoshell:modalclose', onScreenGone);
        document.removeEventListener('evoshell:unload', onScreenGone);
        if (overlayEl.open) {
            overlayEl.close();
        }
        if (restoreFocusToOpener === true) {
            restoreFocus(returnEl);
        }
        returnEl = null;
        if (onClose) {
            onClose();
        }
    }

    // モーダルごと、またはメインの領域ごと閉じられるときは、フォーカスを戻す先も無いので、閉じるだけ
    function onScreenGone() {
        close(false);
    }

    // Esc と見出しの × で閉じる
    function dismiss() {
        if (onEscape) {
            onEscape();
        } else {
            close(true);
        }
    }

    // 管理画面のモーダル(shell.js が document で Esc を受ける)まで閉じないよう、ここで止める。
    // 既定の動き(cancel から閉じる)も止め、閉じるかは dismiss() に任せる(テキストの編集は未保存の確認をする)
    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            dismiss();
        }
    }

    // Esc 以外の閉じる操作(端末の戻るなど)も同じ扱い
    function onCancel(e) {
        e.preventDefault();
        dismiss();
    }

    // ブラウザが cancel を無視して閉じたときも、後始末をそろえる
    function onNativeClose() {
        close(false);
    }

    overlayEl.addEventListener('keydown', onKeydown);
    overlayEl.addEventListener('cancel', onCancel);
    overlayEl.addEventListener('close', onNativeClose);
    if (closeBtn) {
        closeBtn.addEventListener('click', dismiss);
    }
    document.addEventListener('evoshell:modalclose', onScreenGone);
    document.addEventListener('evoshell:unload', onScreenGone);
    panelEl.style.translate = '';
    overlayEl.showModal();
    var focusEl = initialEl || focusableIn(panelEl).filter(function (el) { return el !== closeBtn; })[0] || panelEl;
    if (focusEl === panelEl && !panelEl.hasAttribute('tabindex')) {
        panelEl.setAttribute('tabindex', '-1');
    }
    focusEl.focus({ preventScroll: true });

    return close;
}
