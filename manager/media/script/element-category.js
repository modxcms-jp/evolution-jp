/**
 * 要素（テンプレート・チャンク・スニペット・プラグイン・モジュール・テンプレート変数）の編集画面で、
 * カテゴリの選択（select[name=categoryid]）と、新しいカテゴリの欄（input[name=newcategory]）の表示を連動させる。
 *
 * 選択が「>> カテゴリー作成」(-1) のときだけ欄の行を出し、ほかを選ぶと隠して名前を空にする。
 * 欄の行は input[name=newcategory] の closest('tr')。出すかどうかは、この JS だけが決める
 * （PHP は欄の行に display:none を付けたまま出す。差し戻しで -1 が選ばれていれば、init() で出る）。
 *
 * 使い方: EvoElementCategory.init(document.forms['mutate']);
 *   sync(form):         選択に合わせて欄の表示を決める
 *   useNew(form, name): 選択を -1 にして、欄に name を入れる
 */
// var への代入で定義する。シェルの画面遷移で再評価されるため（assets/docs/jquery-migration.md を参照）
var EvoElementCategory = (function () {
    'use strict';

    const NEW_CATEGORY = '-1';

    function fields(form) {
        // form.elements は使わない。フォームに name="elements" の欄（TV の編集画面など）があると、
        // form.elements がその欄に上書きされるため、名前で querySelector する
        if (!form || !form.querySelector) return null;
        const select = form.querySelector('[name="categoryid"]');
        const input = form.querySelector('[name="newcategory"]');
        if (!select || !input) return null;
        return {select: select, input: input, row: input.closest('tr')};
    }

    function sync(form) {
        const f = fields(form);
        if (!f) return;
        if (f.select.value === NEW_CATEGORY) {
            if (f.row) f.row.style.display = '';
            return;
        }
        if (f.row) f.row.style.display = 'none';
        f.input.value = '';
    }

    function useNew(form, name) {
        const f = fields(form);
        if (!f) return;
        f.select.value = NEW_CATEGORY;
        f.input.value = name;
        sync(form);
    }

    function init(form) {
        const f = fields(form);
        if (!f) return;
        // 画面の再表示で同じ form に二重に登録しない（form は新しい要素だが、念のため一度だけ）
        if (!f.select.dataset.evoCategoryBound) {
            f.select.dataset.evoCategoryBound = '1';
            f.select.addEventListener('change', function () {
                sync(form);
            });
        }
        sync(form);
    }

    return {init: init, sync: sync, useNew: useNew};
})();
