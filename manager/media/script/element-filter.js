/**
 * エレメントの一覧の絞り込み。
 * <input type="search" data-element-filter="リストの id"> に入れた語で、リストの li[data-filter-text] を表示・非表示にする。
 * 空白で区切った語はすべて含むもの（AND）。全角・半角と大文字・小文字は区別しない。
 * カテゴリーの名前に一致したときは、そのカテゴリーの項目をすべて出す。
 * 0 件のときは「リストの id-empty」の要素を出す。
 */
(function () {
    'use strict';

    function normalize(text) {
        return (text || '').normalize('NFKC').toLowerCase();
    }

    function matchesAll(text, terms) {
        return terms.every(function (term) {
            return text.indexOf(term) !== -1;
        });
    }

    function setup(input) {
        var list = document.getElementById(input.getAttribute('data-element-filter'));
        if (!list) {
            return;
        }
        var empty = document.getElementById(list.id + '-empty');
        var groups = Array.prototype.map.call(list.querySelectorAll(':scope > li'), function (group) {
            return {
                el: group,
                text: normalize(group.getAttribute('data-category')),
                items: Array.prototype.map.call(group.querySelectorAll('li[data-filter-text]'), function (item) {
                    return { el: item, text: normalize(item.getAttribute('data-filter-text')) };
                })
            };
        });

        function apply() {
            var terms = normalize(input.value).split(/\s+/).filter(Boolean);
            var total = 0;
            groups.forEach(function (group) {
                var groupHit = terms.length > 0 && matchesAll(group.text, terms);
                var shown = 0;
                group.items.forEach(function (item) {
                    var show = terms.length === 0 || groupHit || matchesAll(item.text, terms);
                    item.el.hidden = !show;
                    if (show) {
                        shown++;
                    }
                });
                group.el.hidden = shown === 0;
                total += shown;
            });
            if (empty) {
                empty.hidden = total !== 0;
            }
        }

        input.addEventListener('input', apply);
        // ブラウザが戻る操作で入力欄の値を復元したときにも合わせる
        apply();
    }

    function init() {
        document.querySelectorAll('input[data-element-filter]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
