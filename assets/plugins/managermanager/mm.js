/**
 * ManagerManager の出力用の小さな部品（window.MM）
 *
 * MM の出力用。自作のウィジェットからも使える。
 * x には要素・配列・NodeList・セレクタの文字列・null のどれでも渡せる。
 * 要素が無ければ何もしない（ルールが指す項目がテンプレートに無いことはよくあるため）。
 * jQuery の代わりの汎用ライブラリにはしない。MM の出力が使う形だけを置く。
 */
(function () {
    'use strict';

    // x を要素の配列にする
    function all(x, root) {
        if (!x) {
            return [];
        }
        if (typeof x === 'string') {
            return Array.from((root || document).querySelectorAll(x));
        }
        if (x.nodeType === 1) {
            return [x];
        }
        if (typeof x.length === 'number') {
            return Array.from(x).filter(function (el) {
                return el && el.nodeType === 1;
            });
        }
        return [];
    }

    function each(x, fn) {
        const els = all(x);
        els.forEach(fn);
        return els;
    }

    window.MM = {
        all: all,

        // jQuery の .parents(sel) と同じく、一致する祖先をすべて返す（自身は含まない）。
        // 並びも jQuery と同じく文書の逆順（要素 1 つなら近い順）
        parents: function (x, sel) {
            const found = [];
            all(x).forEach(function (el) {
                let p = el.parentElement;
                while (p) {
                    if ((!sel || p.matches(sel)) && found.indexOf(p) === -1) {
                        found.push(p);
                    }
                    p = p.parentElement;
                }
            });
            return found.sort(function (a, b) {
                return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? 1 : -1;
            });
        },

        // 表の行なども扱うので hidden 属性ではなく display で出し分ける
        hide: function (x) {
            return each(x, function (el) {
                el.style.display = 'none';
            });
        },

        show: function (x) {
            return each(x, function (el) {
                el.style.display = '';
            });
        },

        setValue: function (x, value) {
            return each(x, function (el) {
                el.value = value;
            });
        },

        setChecked: function (x, checked) {
            return each(x, function (el) {
                el.checked = !!checked;
            });
        },

        html: function (x, html) {
            return each(x, function (el) {
                el.innerHTML = html;
            });
        },

        // jQuery の .trigger() は addEventListener のリスナーを呼ばないので、素のイベントを起こす
        fire: function (x, type) {
            return each(x, function (el) {
                el.dispatchEvent(new Event(type, { bubbles: true }));
            });
        },

        addStyle: function (cssText) {
            const style = document.createElement('style');
            style.textContent = cssText;
            document.head.appendChild(style);
            return style;
        }
    };
})();
