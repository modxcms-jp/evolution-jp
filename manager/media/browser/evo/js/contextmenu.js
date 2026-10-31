// 右クリックメニュー。DOM を 1 つだけ作って使い回し、項目の中身は呼び出し側が決める。
// 閉じる条件: Esc・メニュー外のクリック・スクロール・リサイズ・モーダルを閉じたとき。

import { restoreFocus } from './overlay.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.rootEl - メニューの DOM を置く要素
 * @param {HTMLElement} ctx.scrollEl - スクロールで閉じる対象(一覧)
 */
export function createContextMenu(ctx) {
    var menuEl = document.createElement('div');
    menuEl.className = 'evo-fb-contextmenu';
    menuEl.setAttribute('role', 'menu');
    menuEl.hidden = true;
    // メニューの上での右クリック・Shift+F10 で、ブラウザ標準のメニューを重ねない
    menuEl.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });
    ctx.rootEl.appendChild(menuEl);

    var returnFocusEl = null;
    var openedScrollTop = 0;
    var openedScrollLeft = 0;

    function buttons() {
        return Array.from(menuEl.querySelectorAll('[role="menuitem"]'));
    }

    function isOpen() {
        return !menuEl.hidden;
    }

    // restoreFocusToTarget: Esc・Tab・項目選択のときだけ、右クリックした要素へフォーカスを戻す
    function close(restoreFocusToTarget) {
        if (!isOpen()) {
            return;
        }
        menuEl.hidden = true;
        menuEl.textContent = '';
        document.removeEventListener('mousedown', onOutside, true);
        document.removeEventListener('keydown', onKeydown, true);
        window.removeEventListener('resize', closeQuietly);
        document.removeEventListener('evoshell:modalclose', closeQuietly);
        ctx.scrollEl.removeEventListener('scroll', onScroll);
        var target = returnFocusEl;
        returnFocusEl = null;
        if (restoreFocusToTarget === true) {
            restoreFocus(target);
        }
    }

    function closeQuietly() {
        close(false);
    }

    // フォーカス移動に伴う遅れたスクロールで閉じないよう、位置が変わったときだけ閉じる
    function onScroll() {
        if (ctx.scrollEl.scrollTop !== openedScrollTop || ctx.scrollEl.scrollLeft !== openedScrollLeft) {
            close(false);
        }
    }

    function onOutside(e) {
        if (!menuEl.contains(e.target)) {
            close(false);
        }
    }

    function onKeydown(e) {
        var items = buttons();
        var index = items.indexOf(document.activeElement);
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            close(true);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[(index + 1) % items.length].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[(index - 1 + items.length) % items.length].focus();
        } else if (e.key === 'Home') {
            e.preventDefault();
            items[0].focus();
        } else if (e.key === 'End') {
            e.preventDefault();
            items[items.length - 1].focus();
        } else if (e.key === 'Tab') {
            e.preventDefault();
            close(true);
        }
        // Enter / Space はフォーカス中の <button> の click が処理する
    }

    /**
     * @param {number} x - 画面上の X(clientX)
     * @param {number} y - 画面上の Y(clientY)
     * @param {{label: string, onSelect: () => void, danger?: boolean}[]} items
     * @param {string} label - メニューの aria-label(右クリックした対象の名前など)
     */
    function open(x, y, items, label) {
        close(false);
        if (items.length === 0) {
            return;
        }
        returnFocusEl = document.activeElement;
        menuEl.setAttribute('aria-label', label || '');
        items.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'evo-fb-contextmenu-item' + (item.danger ? ' evo-fb-contextmenu-danger' : '');
            btn.setAttribute('role', 'menuitem');
            btn.textContent = item.label;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                // 項目の処理(prompt やダイアログ)のフォーカス移動を邪魔しないよう、先に閉じて戻す
                close(true);
                item.onSelect();
            });
            menuEl.appendChild(btn);
        });

        menuEl.style.left = '0px';
        menuEl.style.top = '0px';
        menuEl.hidden = false;
        // 画面の端からはみ出すときは内側に寄せる
        var rect = menuEl.getBoundingClientRect();
        var left = Math.max(0, Math.min(x, window.innerWidth - rect.width));
        var top = Math.max(0, Math.min(y, window.innerHeight - rect.height));
        menuEl.style.left = left + 'px';
        menuEl.style.top = top + 'px';

        buttons()[0].focus();
        document.addEventListener('mousedown', onOutside, true);
        document.addEventListener('keydown', onKeydown, true);
        openedScrollTop = ctx.scrollEl.scrollTop;
        openedScrollLeft = ctx.scrollEl.scrollLeft;
        window.addEventListener('resize', closeQuietly);
        document.addEventListener('evoshell:modalclose', closeQuietly);
        ctx.scrollEl.addEventListener('scroll', onScroll);
    }

    return { open: open, close: closeQuietly, isOpen: isOpen };
}
