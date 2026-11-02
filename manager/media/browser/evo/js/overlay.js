// オーバーレイ型ダイアログの共通の振る舞い: フォーカスの移動・Tab の循環・Esc でそのダイアログだけを閉じる・
// 閉じたらフォーカスを戻す。リスナーは開いている間だけ付け、閉じたら必ず外す。
// 編集・プレビュー・移動のダイアログで使う。
// プレビューの上に編集のダイアログが重なるように、複数が同時に開くことがある。
// keydown は「開いているダイアログのスタック」の一番上だけが扱う(下のものは Esc でも Tab でも反応しない)。

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

var FOCUSABLE = 'button, input, select, textarea, a[href], [tabindex]';

// 画面に出ていて、フォーカスできる要素(Tab の循環の端を決めるため)
function focusableIn(panelEl) {
    return Array.from(panelEl.querySelectorAll(FOCUSABLE)).filter(function (el) {
        return !el.disabled && el.tabIndex >= 0 && el.getClientRects().length > 0;
    });
}

// 開いているダイアログのスタック(末尾が一番上)。keydown の listener は 1 つだけ、スタックが空でない間だけ付ける
var stack = [];

function onDocumentKeydown(e) {
    var top = stack[stack.length - 1];
    if (top) {
        top.onKeydown(e);
    }
}

function pushEntry(entry) {
    if (stack.length === 0) {
        document.addEventListener('keydown', onDocumentKeydown, true);
    }
    stack.push(entry);
}

// 途中のダイアログが先に閉じられても、他のものの順序は変えない
function removeEntry(entry) {
    var i = stack.indexOf(entry);
    if (i !== -1) {
        stack.splice(i, 1);
    }
    if (stack.length === 0) {
        document.removeEventListener('keydown', onDocumentKeydown, true);
    }
}

/**
 * ダイアログを開く。
 *
 * @param {HTMLElement} overlayEl - 表示を切り替える外側の要素
 * @param {HTMLElement} panelEl - ダイアログ本体(Tab を循環させる範囲)
 * @param {HTMLElement|null} initialEl - 開いたときにフォーカスする要素(省略時は中の最初の要素)
 * @param {{onEscape?: () => void}} [options] - onEscape: Esc が押されたときの処理(省略時は、フォーカスを戻して閉じる)
 * @returns {(restoreFocusToOpener?: boolean) => void} 閉じる関数。true を渡すと、開く前にフォーカスのあった要素へ戻す
 */
export function openOverlay(overlayEl, panelEl, initialEl, options) {
    var onEscape = options && options.onEscape;
    var returnEl = document.activeElement;
    var closed = false;

    function close(restoreFocusToOpener) {
        if (closed) {
            return;
        }
        closed = true;
        overlayEl.hidden = true;
        removeEntry(entry);
        document.removeEventListener('evoshell:modalclose', onModalClose);
        if (restoreFocusToOpener === true) {
            restoreFocus(returnEl);
        }
        returnEl = null;
    }

    // 管理画面のモーダルごと閉じられるときは、フォーカスを戻す先も無いので、リスナーだけ外す
    function onModalClose() {
        close(false);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            // 管理画面のモーダル(shell.js)まで閉じないよう、ここで止める
            e.preventDefault();
            e.stopPropagation();
            if (onEscape) {
                onEscape();
            } else {
                close(true);
            }
            return;
        }
        if (e.key !== 'Tab') {
            return;
        }
        var items = focusableIn(panelEl);
        if (items.length === 0) {
            e.preventDefault();
            return;
        }
        var first = items[0];
        var last = items[items.length - 1];
        var active = document.activeElement;
        if (!panelEl.contains(active)) {
            // 背景のクリックなどでフォーカスが外へ出たときは、中へ戻す
            e.preventDefault();
            (e.shiftKey ? last : first).focus();
        } else if (e.shiftKey && active === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
        }
    }

    var entry = { onKeydown: onKeydown };
    overlayEl.hidden = false;
    var focusEl = initialEl || focusableIn(panelEl)[0] || panelEl;
    if (focusEl === panelEl && !panelEl.hasAttribute('tabindex')) {
        panelEl.setAttribute('tabindex', '-1');
    }
    focusEl.focus({ preventScroll: true });
    pushEntry(entry);
    document.addEventListener('evoshell:modalclose', onModalClose);

    return close;
}
