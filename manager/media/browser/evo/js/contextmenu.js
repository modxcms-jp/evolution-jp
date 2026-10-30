// 右クリックメニュー。DOM を 1 つだけ作って使い回し、項目の中身は呼び出し側が決める。
// 閉じる条件: Esc・メニュー外のクリック・スクロール・リサイズ・モーダルを閉じたとき。

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
    ctx.rootEl.appendChild(menuEl);

    var returnFocusEl = null;

    function buttons() {
        return Array.from(menuEl.querySelectorAll('[role="menuitem"]'));
    }

    function isOpen() {
        return !menuEl.hidden;
    }

    function close() {
        if (!isOpen()) {
            return;
        }
        menuEl.hidden = true;
        menuEl.textContent = '';
        document.removeEventListener('mousedown', onOutside, true);
        document.removeEventListener('keydown', onKeydown, true);
        window.removeEventListener('resize', close);
        document.removeEventListener('evoshell:modalclose', close);
        ctx.scrollEl.removeEventListener('scroll', close);
        var target = returnFocusEl;
        returnFocusEl = null;
        if (target && target.isConnected && typeof target.focus === 'function') {
            target.focus();
        }
    }

    function onOutside(e) {
        if (!menuEl.contains(e.target)) {
            close();
        }
    }

    function onKeydown(e) {
        var items = buttons();
        var index = items.indexOf(document.activeElement);
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            close();
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
            close();
        }
        // Enter / Space はフォーカス中の <button> の click が処理する
    }

    /**
     * @param {number} x - 画面上の X(clientX)
     * @param {number} y - 画面上の Y(clientY)
     * @param {{label: string, onSelect: () => void, danger?: boolean}[]} items
     */
    function open(x, y, items) {
        close();
        if (items.length === 0) {
            return;
        }
        returnFocusEl = document.activeElement;
        items.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'evo-fb-contextmenu-item' + (item.danger ? ' evo-fb-contextmenu-danger' : '');
            btn.setAttribute('role', 'menuitem');
            btn.textContent = item.label;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                // フォーカスを戻す先が操作で消える場合があるため、先に閉じてから実行する
                close();
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
        window.addEventListener('resize', close);
        document.addEventListener('evoshell:modalclose', close);
        ctx.scrollEl.addEventListener('scroll', close);
    }

    return { open: open, close: close, isOpen: isOpen };
}
