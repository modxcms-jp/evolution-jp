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
