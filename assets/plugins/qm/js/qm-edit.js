/**
 * QuickManager の編集画面（公開側のページから iframe で開く、文書の編集フォーム）のスクリプト。jQuery には依存しない
 *
 * QMEdit.init({controls: {save, cancel, cancelUrl}, hide: [...]}) を、フォームの中から 1 回呼ぶ。
 * - 本体の操作ボタンの帯（#actions）の中身を、保存・キャンセルに入れ替える（帯の地・余白・位置はテーマのまま）。
 *   アイコンは付けない（どのテーマも .actionButtons a img を隠す）
 * - hide の各要素で指定した要素を隠す（無い要素は飛ばす。表の行も扱うので hidden ではなく display を none に）
 *     {sel: CSS セレクタ}              最初の 1 つ
 *     {sel: CSS セレクタ, nth: n}      n 番目（0 始まり）の 1 つ
 *     {sel: CSS セレクタ, all: true}   すべて
 *     {field: 欄の name}               その欄が入っている行（data-field の付いた行）
 * - 準備ができたら body を表示する（フォームの head でいったん隠している）
 */
window.QMEdit = (function () {
    'use strict';

    function hide(els) {
        Array.prototype.forEach.call(els, function (el) {
            if (el) {
                el.style.display = 'none';
            }
        });
    }

    function target(rule) {
        if (rule.field !== undefined) {
            return Array.prototype.map.call(
                document.querySelectorAll('[name="' + rule.field + '"]'),
                function (el) {
                    return Evo.fieldRow(el);
                }
            );
        }
        if (rule.all) {
            return document.querySelectorAll(rule.sel);
        }
        if (rule.nth !== undefined) {
            return [document.querySelectorAll(rule.sel)[rule.nth]];
        }
        return [document.querySelector(rule.sel)];
    }

    function button(label, id, onclick) {
        const a = document.createElement('a');
        a.href = '#';
        if (id) {
            a.id = id;
        }
        a.textContent = label;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            onclick();
        });
        const li = document.createElement('li');
        li.appendChild(a);
        return li;
    }

    function init(options) {
        Evo.ready(function () {
            const actions = document.getElementById('actions');
            if (actions) {
                const controls = options.controls;
                const ul = document.createElement('ul');
                ul.className = 'actionButtons';
                const save = button(controls.save, '', function () {
                    window.documentDirty = false;
                    window.gotosave = true;
                    document.mutate.save.click();
                });
                save.className = 'primary';
                ul.appendChild(save);
                ul.appendChild(button(controls.cancel, 'cancel', function () {
                    parent.location.href = controls.cancelUrl;
                }));
                actions.replaceChildren(ul);
            }
            (options.hide || []).forEach(function (rule) {
                hide(target(rule));
            });
            document.body.style.display = 'block';
        });
    }

    return {init: init};
})();
