// mm_synch_fields の画面側の処理。PHP から mmSynchFields.link() で、同期する欄の CSS セレクタを渡される
var mmSynchFields = {
    // selectors: 同期する欄の CSS セレクタの配列。入力した欄の値を、ほかのセレクタの欄すべてに写す
    link: function (selectors) {
        const groups = selectors.map(function (selector) { return MM.all(selector); });
        groups.forEach(function (source, i) {
            groups.forEach(function (target, j) {
                if (i === j) { return; }
                source.forEach(function (el) {
                    el.addEventListener('keyup', function () {
                        MM.setValue(target, this.value);
                    });
                });
            });
        });
    }
};
