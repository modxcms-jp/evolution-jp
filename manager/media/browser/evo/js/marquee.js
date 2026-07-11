// エクスプローラ風のラバーバンド(矩形)選択。
// container内の空白部分をドラッグすると矩形が伸び、交差したアイテムが選択される。
// アイテム自体(draggable="true")やボタン・入力要素の上で開始した場合は発火しない
// (ファイル移動のD&Dやフォームコントロール操作と競合しないため)。

/**
 * @param {object} options
 * @param {HTMLElement} options.container - 矩形選択を受け付ける領域(position:relativeが必要)
 * @param {() => HTMLElement[]} options.getItems - 選択候補アイテムの取得
 * @param {(item: HTMLElement) => string} options.getName - アイテムの識別名
 * @param {(e: MouseEvent) => boolean} options.isAdditiveKey - 追加選択キー(Ctrl/Cmd)判定
 * @param {() => string[]} options.getSelected - 現在の選択中の名前一覧(追加選択の基点)
 * @param {(names: string[]) => void} options.onChange - 選択内容が変わるたびに呼ばれる
 */
export function createMarquee(options) {
    var container = options.container;
    var marqueeEl = document.createElement('div');
    marqueeEl.className = 'evo-fb-marquee';
    marqueeEl.hidden = true;
    container.appendChild(marqueeEl);

    var active = false;
    var startX = 0;
    var startY = 0;
    var additive = false;
    var baseNames = [];

    function pointToContent(e) {
        var rect = container.getBoundingClientRect();
        return {
            x: e.clientX - rect.left + container.scrollLeft,
            y: e.clientY - rect.top + container.scrollTop
        };
    }

    function itemContentRect(item) {
        var rect = item.getBoundingClientRect();
        var contRect = container.getBoundingClientRect();
        return {
            left: rect.left - contRect.left + container.scrollLeft,
            top: rect.top - contRect.top + container.scrollTop,
            right: rect.right - contRect.left + container.scrollLeft,
            bottom: rect.bottom - contRect.top + container.scrollTop
        };
    }

    function intersects(a, b) {
        return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
    }

    function showRect(x, y, w, h) {
        marqueeEl.style.left = x + 'px';
        marqueeEl.style.top = y + 'px';
        marqueeEl.style.width = w + 'px';
        marqueeEl.style.height = h + 'px';
        marqueeEl.hidden = false;
    }

    function onMove(e) {
        if (!active) {
            return;
        }
        var p = pointToContent(e);
        var x = Math.min(startX, p.x);
        var y = Math.min(startY, p.y);
        var w = Math.abs(p.x - startX);
        var h = Math.abs(p.y - startY);
        showRect(x, y, w, h);

        var rect = { left: x, top: y, right: x + w, bottom: y + h };
        var names = additive ? baseNames.slice() : [];
        options.getItems().forEach(function (item) {
            if (intersects(rect, itemContentRect(item))) {
                var name = options.getName(item);
                if (names.indexOf(name) === -1) {
                    names.push(name);
                }
            }
        });
        options.onChange(names);
    }

    function onUp() {
        if (!active) {
            return;
        }
        active = false;
        marqueeEl.hidden = true;
        container.classList.remove('evo-fb-marquee-active');
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }

    container.addEventListener('mousedown', function (e) {
        if (e.button !== 0 || e.target.closest('.evo-fb-item, button, input, select, a')) {
            return;
        }
        active = true;
        additive = options.isAdditiveKey(e);
        baseNames = additive ? options.getSelected() : [];
        var p = pointToContent(e);
        startX = p.x;
        startY = p.y;
        showRect(startX, startY, 0, 0);
        // 空白クリックのみ(ドラッグなし)の場合は選択解除として扱う
        options.onChange(additive ? baseNames.slice() : []);
        // ここでpreventDefault()すると、ブラウザ標準のフォーカス移動(blur)まで
        // 抑止され、リネーム中の入力欄が確定/解除されなくなる。ドラッグ中の
        // テキスト選択防止はCSS(user-select:none)側で行う
        container.classList.add('evo-fb-marquee-active');
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
}
