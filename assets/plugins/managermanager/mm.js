/**
 * ManagerManager の出力用の小さな部品（window.MM）
 *
 * MM の出力用。自作のウィジェットからも使える。
 * x には要素・配列・NodeList・セレクタの文字列・null のどれでも渡せる。
 * 要素が無ければ何もしない（ルールが指す項目がテンプレートに無いことはよくあるため）。
 * 汎用の DOM ライブラリにはしない。MM の出力が使う形だけを置く。
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

        // sel に一致する祖先をすべて返す（自身は含まない）。
        // 並びは文書の逆順（要素 1 つなら近い順）
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

        // 欄（x）が入っている編集画面の行（data-field の付いた行）。同じ行は 1 つにまとめる
        fieldRows: function (x) {
            const rows = [];
            all(x).forEach(function (el) {
                const row = window.Evo.fieldRow(el);
                if (row && rows.indexOf(row) === -1) {
                    rows.push(row);
                }
            });
            return rows;
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

        // addEventListener のリスナー（管理画面の未保存の検知など）に届くよう、素のイベントを起こす
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
        },

        // 行を隠し、次の tr の中に td[colspan="2"] があれば、その td の親の tr（区切りの行）も隠す
        hideRows: function (x) {
            all(x).forEach(function (tr) {
                MM.hide(tr);
                const n = tr.nextElementSibling;
                if (n && n.matches('tr')) {
                    n.querySelectorAll('td[colspan="2"]').forEach(function (td) {
                        if (td.parentElement.matches('tr')) {
                            MM.hide(td.parentElement);
                        }
                    });
                }
            });
        },

        // 欄の兄弟の img.tooltip（説明のアイコン）
        siblingIcons: function (x) {
            const icons = [];
            all(x).forEach(function (el) {
                Array.from(el.parentNode.children).forEach(function (c) {
                    if (c !== el && c.matches('img.tooltip')) {
                        icons.push(c);
                    }
                });
            });
            return icons;
        },

        // 説明の部分の中の img.tooltip
        innerIcons: function (x) {
            const icons = [];
            all(x).forEach(function (help) {
                help.querySelectorAll('img.tooltip').forEach(function (c) {
                    icons.push(c);
                });
            });
            return icons;
        },

        // 説明のアイコンの id・title・alt（プロパティなので生の文字列）と data-powertip（属性なので HTML のエスケープ済みの文字列）を変える
        setHelp: function (icons, id, text, tipHtml) {
            icons.forEach(function (c) {
                c.id = id;
                c.title = text;
                c.alt = text;
                c.setAttribute('data-powertip', tipHtml);
            });
        },

        // ルールの実行後に毎回行う整理（OnDocFormRender.inc.php の mm_run の最後）
        tidyUp: function () {
            // General tab table container is too narrow for receiving TVs -- make it a bit wider
            all('div#tabGeneral table').forEach(function (table) {
                table.width = '100%';
            });

            // if template variables containers are empty, remove their section
            if (all('div.tmplvars input, div.tmplvars select, div.tmplvars textarea, div.tmplvars button').length === 0) {
                all('div.tmplvars').forEach(function (el) {
                    MM.hide(el); // Still contains an empty table and some dividers
                    const prev = el.previousElementSibling;
                    if (prev && prev.tagName === 'DIV') {
                        MM.hide(prev);
                    }
                });
            }

            // If template category is empty, hide the optgroup
            all('#field_template optgroup').forEach(function (group) {
                const visible = Array.from(group.querySelectorAll('option')).some(function (option) {
                    return getComputedStyle(option).display !== 'none';
                });
                if (!visible) {
                    MM.hide(group);
                }
            });
        },

        // ルールの中の includeCss() で登録された CSS を head に足す（重複は足さない）
        loadCss: function (hrefs) {
            hrefs.forEach(function (href) {
                const exists = Array.from(document.head.querySelectorAll('link[rel=stylesheet]')).some(function (link) {
                    return link.getAttribute('href') === href;
                });
                if (!exists) {
                    document.head.appendChild(Object.assign(document.createElement('link'), {rel: 'stylesheet', type: 'text/css', href: href}));
                }
            });
        }
    };
})();
