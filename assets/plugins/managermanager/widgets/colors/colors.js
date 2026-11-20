// mm_widget_colors の画面側の処理。PHP から mmColors.attach() で、欄の id と既定の色を渡される
var mmColors = {
    // #rgb / #rrggbb -> #rrggbb、それ以外は ''
    toHex6: function (value) {
        const m = value.trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (!m) {
            return '';
        }
        let hex = m[1].toLowerCase();
        if (hex.length === 3) {
            hex = hex.replace(/(.)/g, '$1$1');
        }
        return '#' + hex;
    },
    // 欄（id）の隣に色の選択を足す。欄が空なら defaultValue を入れる
    attach: function (id, defaultValue) {
        const self = this;
        const text = document.getElementById(id);
        if (!text) {
            return;
        }
        const picker = document.createElement('input');
        picker.type = 'color';
        picker.id = 'colorpicker' + id;
        picker.style.marginLeft = '4px';
        picker.style.verticalAlign = 'middle';
        text.style.backgroundImage = 'none';
        text.after(picker);
        if (text.value === '') {
            text.value = defaultValue;
        }
        const syncPicker = function () {
            const hex = self.toHex6(text.value);
            if (hex) {
                picker.value = hex;
            }
        };
        syncPicker();
        text.addEventListener('input', syncPicker);
        text.addEventListener('change', syncPicker);
        picker.addEventListener('input', function () {
            text.value = picker.value;
            // mark the document as dirty, or the value wont be saved
            text.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
};
