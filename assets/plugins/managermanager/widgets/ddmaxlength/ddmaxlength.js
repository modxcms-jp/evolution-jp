/**
 * ddMaxLength 1.1（素の JS）
 *
 * 入力欄の残りの文字数を表示する。jquery.ddmaxlength-1.0 の $.fn.ddMaxLength を素の JS にしたもの。
 * field は要素（または MM.all に渡せるもの）。要素が無ければ何もしない。
 */
function ddMaxLength(field, options) {
    'use strict';

    const o = Object.assign({
        max: 150,
        containerSelector: false,
        parentSelector: false,
        errorClass: 'maxLenghtError',
        warningClass: 'maxLenghtError',
        canWriteError: true,
        minProcent: 7
    }, options);
    const min = o.max / 100 * o.minProcent;

    // 残りの文字数を書き、少なければ class を付ける
    function update(containers, length) {
        const rest = o.max - length;
        containers.forEach(function (c) {
            if (rest < 0 || rest < min) {
                c.classList.add(o.errorClass);
                c.classList.remove(o.warningClass);
            } else if (rest < min * 2) {
                c.classList.add(o.warningClass);
                c.classList.remove(o.errorClass);
            } else {
                c.classList.remove(o.warningClass);
                c.classList.remove(o.errorClass);
            }
            c.textContent = rest;
        });
    }

    MM.all(field).forEach(function (el) {
        let containers = [];
        if (o.containerSelector) {
            const parent = o.parentSelector
                ? (el.parentElement ? el.parentElement.closest(o.parentSelector) : null)
                : el.parentElement;
            containers = parent ? MM.all(o.containerSelector, parent) : [];
        }
        update(containers, el.value.length);
        if (!o.canWriteError) {
            el.value = el.value.substring(0, o.max);
        }
        el.addEventListener('keyup', function () {
            if (!o.canWriteError && el.value.length > o.max) {
                el.value = el.value.substring(0, o.max);
                return;
            }
            update(containers, el.value.length);
        });
    });
}

// mm_ddMaxLength の画面側の処理。PHP から attach() と guardSubmit(超過の文言) を呼ぶ
var mmDdMaxLength = {
    // 欄に残りの文字数の表示を付ける。fieldIds: 欄の id の配列、max: 最大の文字数
    attach: function (fieldIds, max) {
        fieldIds.forEach(function (id) {
            MM.all('#' + id).forEach(function (field) {
                field.classList.add('ddMaxLengthField');
                field.parentElement.insertAdjacentHTML('beforeend', '<div class="ddMaxLengthCount"><span></span></div>');
                ddMaxLength(field, {
                    max: max,
                    containerSelector: 'div.ddMaxLengthCount span',
                    warningClass: 'maxLenghtWarning'
                });
            });
        });
    },
    // 欄の行の見出し
    titles: function (fields) {
        const found = [];
        fields.forEach(function (field) {
            const title = Evo.fieldPart(field, 'title');
            if (title && found.indexOf(title) === -1) { found.push(title); }
        });
        return found;
    },
    // 残りが負の欄があれば保存を止める（以前の return false と同じく、既定の動作と伝播の両方を止める）
    // message: 保存を止めるときに出す文言（言語ファイルの文言。HTML として出す）
    guardSubmit: function (message) {
        const self = this;
        const form = document.getElementById('mutate');
        if (!form) { return; }
        form.addEventListener('submit', function (e) {
            const ddErrors = [];
            MM.all('div.ddMaxLengthCount span').forEach(function (span) {
                const box = span.parentElement ? span.parentElement.closest('.ddMaxLengthCount') : null;
                const fields = box && box.parentElement ? MM.all('.ddMaxLengthField', box.parentElement) : [];
                if (parseInt(span.textContent) < 0) {
                    fields.forEach(function (field) {
                        field.classList.add('maxLenghtErrorField');
                        field.addEventListener('focus', function () {
                            fields.forEach(function (f) { f.classList.remove('maxLenghtErrorField'); });
                        });
                    });
                    ddErrors.push(self.titles(fields).map(function (t) { return t.textContent; }).join(''));
                }
            });

            if (ddErrors.length > 0) {
                const names = ddErrors.map(function (name) { return ' - ' + Evo.escapeHtml(name); });
                EvoDialog.alert(message + '\n\n' + names.join('\n'), '', null, {icon: 'warning'});
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }
};
