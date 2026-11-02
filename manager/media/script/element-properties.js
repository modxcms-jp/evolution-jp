/**
 * 要素（プラグイン・スニペット・モジュール）の編集画面にある、
 * プロパティ（textarea[name=properties]）の設定表を組み立てる。
 *
 * 説明・値・リストの項目は textContent / value で DOM に入れる（HTML の文字列にしない）。
 * 書き戻す形式は `&key=desc;type;value`（`=` は %3D、`&` は %26）。
 *
 * 使い方: EvoElementProperties.configure({...}) のあと、show(ctrl) を呼ぶ。
 *   table:     設定表の table 要素に付ける属性（{style: '…', width: '300', …}）
 *   head:      見出しの文字列の配列。null なら見出しなし
 *   stacked:   true なら 1 列（説明の下に入力欄）、false なら 説明 | 入力欄 の 2 列
 *   rowStyle:  stacked のときの入力欄の div に付ける style
 *   cellStyle: 2 列のときの td に付ける style
 */
// var への代入で定義する。シェルの画面遷移で再評価されるため（assets/docs/jquery-migration.md を参照）
var EvoElementProperties = (function () {
    'use strict';

    const DEFAULTS = {table: {}, head: null, stacked: true, rowStyle: '', cellStyle: ''};

    const state = {
        params: {},
        options: Object.assign({}, DEFAULTS)
    };

    function encode(s) {
        return String(s).replace(/=/g, '%3D').replace(/&/g, '%26');
    }

    function decode(s) {
        return String(s).replace(/%3D/g, '=').replace(/%26/g, '&');
    }

    function trim(s) {
        return String(s).replace(/^\s|\s$/, '');
    }

    function configure(options) {
        state.options = Object.assign({}, DEFAULTS, options || {});
    }

    function getForm(ctrl) {
        return ctrl ? ctrl.form : document.forms['mutate'];
    }

    function implode() {
        let s = '';
        Object.keys(state.params).forEach(function (p) {
            if (!state.params[p]) return;
            const v = state.params[p].join(';');
            if (!v) return;
            if (s) s += ' ';
            s += '&' + p + '=' + encode(v);
        });
        document.forms['mutate'].properties.value = s;
    }

    function createOptions(select, items, isSelected) {
        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item;
            option.textContent = item;
            option.selected = isSelected(item);
            select.appendChild(option);
        });
    }

    function createControl(key, dt, ar, value) {
        let ctrl;
        let items;
        switch (dt) {
            case 'menu':
            case 'list': {
                items = String(ar[2]).split(',');
                const selectedValue = ar[3];
                ctrl = document.createElement('select');
                ctrl.style.width = '168px';
                if (dt === 'list') ctrl.size = items.length;
                createOptions(ctrl, items, function (item) {
                    return item == selectedValue;
                });
                break;
            }
            case 'list-multi': {
                items = String(ar[2]).split(',');
                const selectedValues = (typeof ar[3] !== 'undefined' ? trim(ar[3]) : '').split(',');
                ctrl = document.createElement('select');
                ctrl.size = items.length;
                ctrl.multiple = true;
                ctrl.style.width = '168px';
                createOptions(ctrl, items, function (item) {
                    return selectedValues.indexOf(item) !== -1;
                });
                break;
            }
            case 'textarea':
                ctrl = document.createElement('textarea');
                ctrl.className = 'phptextarea';
                ctrl.cols = 50;
                ctrl.rows = 4;
                ctrl.value = value;
                break;
            default: // int, string
                ctrl = document.createElement('input');
                ctrl.type = 'text';
                ctrl.size = 30;
                ctrl.value = value;
                break;
        }
        ctrl.name = 'prop_' + key;
        ctrl.id = 'prop_' + key;
        ctrl.addEventListener('change', function () {
            set(key, dt, ctrl);
        });
        return ctrl;
    }

    function createTable(rows) {
        const opt = state.options;
        const table = document.createElement('table');
        Object.keys(opt.table).forEach(function (name) {
            table.setAttribute(name, opt.table[name]);
        });
        if (opt.head) {
            const tr = document.createElement('tr');
            opt.head.forEach(function (label) {
                const td = document.createElement('td');
                td.textContent = label;
                tr.appendChild(td);
            });
            const thead = document.createElement('thead');
            thead.appendChild(tr);
            table.appendChild(thead);
        }
        rows.forEach(function (row) {
            const tr = document.createElement('tr');
            if (opt.stacked) {
                const td = document.createElement('td');
                const desc = document.createElement('div');
                desc.textContent = row.desc;
                const box = document.createElement('div');
                if (opt.rowStyle) box.setAttribute('style', opt.rowStyle);
                box.appendChild(row.ctrl);
                td.appendChild(desc);
                td.appendChild(box);
                tr.appendChild(td);
            } else {
                const tdDesc = document.createElement('td');
                const tdCtrl = document.createElement('td');
                if (opt.cellStyle) {
                    tdDesc.setAttribute('style', opt.cellStyle);
                    tdCtrl.setAttribute('style', opt.cellStyle);
                }
                tdDesc.textContent = row.desc;
                tdCtrl.appendChild(row.ctrl);
                tr.appendChild(tdDesc);
                tr.appendChild(tdCtrl);
            }
            table.appendChild(tr);
        });
        return table;
    }

    function show(ctrl) {
        state.params = {};
        const f = getForm(ctrl);
        if (!f) return;

        const tr = document.getElementById('displayparamrow');
        const dp = f.properties.value ? f.properties.value.split('&') : [];
        if (!dp.length) {
            tr.style.display = 'none';
            implode();
            return;
        }

        const rows = [];
        dp.forEach(function (line) {
            const pair = trim(line).split('=');
            const key = pair[0];
            const ar = String(pair[1]).split(';');
            const desc = ar[0];
            const dt = ar[1];
            const value = decode(ar[2] ? ar[2] : '');

            // 後で書き戻すために値を覚えておく
            if (key && (dt === 'list' || dt === 'list-multi')) state.params[key] = [desc, dt, value, ar[3]];
            else if (key) state.params[key] = [desc, dt, value];

            if (dt) rows.push({desc: desc, ctrl: createControl(key, dt, ar, value)});
        });

        const box = document.getElementById('displayparams');
        box.replaceChildren(createTable(rows));
        tr.style.display = '';
        implode();
    }

    function set(key, dt, ctrl) {
        if (!ctrl || !state.params[key]) return;
        let v;
        switch (dt) {
            case 'int':
                ctrl.value = parseInt(ctrl.value);
                if (isNaN(ctrl.value)) ctrl.value = 0;
                v = ctrl.value;
                break;
            case 'menu':
            case 'list':
                state.params[key][3] = ctrl.options[ctrl.selectedIndex].value;
                implode();
                return;
            case 'list-multi':
                state.params[key][3] = Array.from(ctrl.options).filter(function (o) {
                    return o.selected;
                }).map(function (o) {
                    return o.value;
                }).toString();
                implode();
                return;
            default:
                v = ctrl.value + '';
                break;
        }
        state.params[key][2] = v;
        implode();
    }

    return {
        configure: configure,
        show: show,
        set: set,
        // 保存済みの値（プラグインの「パラメーターの取り込み」が参照する）
        get params() {
            return state.params;
        }
    };
})();
