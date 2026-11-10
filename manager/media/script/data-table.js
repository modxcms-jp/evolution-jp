// DataTable の共通の動き（assets/docs/data-table.md）。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoDataTable) {
    window.EvoDataTable = {};
    (function () {

        // 選択の帯（DataTable の options['selection']）。ほかのページの値は、同じ name の hidden として form に足す
        const extraAttr = 'data-selection-extra';

        function selectionRows(bar) {
            const table = bar.closest('.data-table');
            const name = bar.dataset.selection;
            return table ? Array.from(table.querySelectorAll('input[type="checkbox"]')).filter(function (c) {
                return c.name === name && !c.hasAttribute('data-select-all');
            }) : [];
        }

        function clearExtra(bar) {
            const form = bar.closest('form');
            if (form) {
                form.querySelectorAll('input[' + extraAttr + ']').forEach(function (el) {
                    if (el.name === bar.dataset.selection) {
                        el.remove();
                    }
                });
            }
        }

        function fill(text, n, total) {
            return text.split('{n}').join(n).split('{total}').join(total);
        }

        function actionLink(label, action) {
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = label;
            a.setAttribute('data-selection-action', action);
            return a;
        }

        // 全件の値が付いていて（件数が多すぎるときは DataTable が付けない）、足す先の form があるときだけ選べる
        function canSelectAll(bar) {
            return !!bar.closest('form') && (bar.dataset.allIds || '[]') !== '[]';
        }

        // 一括操作のボタンの欄（同じ form の中の data-selection-actions="<name>"）。あれば帯と一緒に
        // 「操作の帯」（.data-table-selection-dock）にまとめ、選んだときだけ表の下端に貼り付けて出す
        function actionsFor(bar) {
            const form = bar.closest('form');
            if (!form) {
                return null;
            }
            return Array.from(form.querySelectorAll('[data-selection-actions]')).find(function (el) {
                return el.getAttribute('data-selection-actions') === bar.dataset.selection;
            }) || null;
        }

        function dockFor(bar) {
            const parent = bar.parentElement;
            if (parent && parent.classList.contains('data-table-selection-dock')) {
                return parent;
            }
            const actions = actionsFor(bar);
            if (!actions) {
                return null;
            }
            const dock = document.createElement('div');
            dock.className = 'data-table-selection-dock selection-dock';
            // 下端に貼り付けるので、表の直後（表を包む div の中の末尾）に置く。bar（外枠）も一緒に移す
            const table = bar.closest('.data-table');
            if (!table) {
                return null;
            }
            table.append(dock);
            dock.append(bar, actions);
            return dock;
        }

        // bar は常に出ている role="status" の外枠。中身だけを出し入れして、読み上げに告知させる。
        // 操作の帯の中でも bar は描いたままにし、0 件のときは帯の class（is-empty）で見た目だけを消す
        function renderSelection(bar) {
            const rows = selectionRows(bar);
            const checked = rows.filter(function (c) { return c.checked; }).length;
            const total = Number(bar.dataset.total) || 0;
            const d = bar.dataset;
            const dock = dockFor(bar);
            bar.textContent = '';
            if (dock) {
                dock.classList.toggle('is-empty', checked === 0);
            }
            if (checked === 0) {
                return;
            }
            const body = document.createElement('div');
            body.className = 'data-table-selection-body';
            const text = document.createElement('span');
            body.append(text);
            if (bar.hasAttribute('data-all-selected')) {
                text.textContent = fill(d.labelAllSelected, checked, total);
                body.append(' ', actionLink(d.labelClear, 'clear'));
            } else if (checked === rows.length && total > rows.length) {
                text.textContent = fill(d.labelPage, checked, total);
                if (canSelectAll(bar)) {
                    body.append(' ', actionLink(fill(d.labelAll, checked, total), 'all'));
                }
            } else {
                text.textContent = fill(d.labelSelected, checked, total);
            }
            // 操作の帯では、いつでも選択を解除できるようにする（すべてを選んだときは上で足している）
            if (dock && !bar.hasAttribute('data-all-selected')) {
                body.append(' ', actionLink(d.labelClear, 'clear'));
            }
            bar.append(body);
        }

        // 画面を開くたびに（シェルが同じ document でスクリプトを動かし直しても）使える、選択の帯の描き直し
        window.EvoDataTable.renderSelections = function () {
            document.querySelectorAll('.data-table-selection[data-selection]').forEach(renderSelection);
        };

        // 「この一覧のすべて」: ids に含まれるこのページの行だけを選び（含まれない行は外す。見られない子など、
        // 一括操作の権限の確認で全体が止まるものを送らない）、ほかのページの値を hidden として足す
        function selectAllInList(bar) {
            const form = bar.closest('form');
            let ids = [];
            try { ids = JSON.parse(bar.dataset.allIds || '[]'); } catch (err) { ids = []; }
            const inList = new Set(ids.map(String));
            const onPage = new Set();
            selectionRows(bar).forEach(function (c) {
                c.checked = inList.has(c.value);
                if (c.checked) {
                    onPage.add(c.value);
                }
            });
            const head = bar.closest('.data-table').querySelector('input[data-select-all]');
            if (head) {
                head.checked = true;
            }
            clearExtra(bar);
            if (form) {
                ids.forEach(function (id) {
                    if (onPage.has(String(id))) {
                        return;
                    }
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = bar.dataset.selection;
                    input.value = String(id);
                    input.setAttribute(extraAttr, '');
                    form.appendChild(input);
                });
            }
            bar.setAttribute('data-all-selected', '');
            renderSelection(bar);
        }

        function clearSelection(bar) {
            selectionRows(bar).forEach(function (c) { c.checked = false; });
            const head = bar.closest('.data-table').querySelector('input[data-select-all]');
            if (head) {
                head.checked = false;
            }
            clearExtra(bar);
            bar.removeAttribute('data-all-selected');
            renderSelection(bar);
        }

        document.addEventListener('change', function (e) {
            const box = e.target;
            if (!(box instanceof HTMLInputElement) || box.type !== 'checkbox' || !box.hasAttribute('data-select-all')) {
                return;
            }
            const table = box.closest('.data-table');
            if (!table) {
                return;
            }
            const name = box.getAttribute('data-select-all');
            table.querySelectorAll('input[type="checkbox"]').forEach(function (c) {
                if (c !== box && c.name === name) {
                    c.checked = box.checked;
                }
            });
        });

        // 上の change より後に登録するので、行の切り替えが終わってから数える
        document.addEventListener('change', function (e) {
            const box = e.target;
            const table = box instanceof HTMLInputElement && box.type === 'checkbox' ? box.closest('.data-table') : null;
            const bar = table ? table.querySelector('[data-selection]') : null;
            if (!bar) {
                return;
            }
            // すべてを選んだあとで 1 つでも変えたら、ほかのページの分は外して件数の表示に戻る
            if (bar.hasAttribute('data-all-selected')) {
                bar.removeAttribute('data-all-selected');
                clearExtra(bar);
            }
            renderSelection(bar);
        });

        document.addEventListener('click', function (e) {
            const link = e.target instanceof Element ? e.target.closest('[data-selection-action]') : null;
            const bar = link ? link.closest('[data-selection]') : null;
            if (!bar) {
                return;
            }
            e.preventDefault();
            if (link.getAttribute('data-selection-action') === 'all') {
                selectAllInList(bar);
            } else {
                clearSelection(bar);
            }
        });
    })();
}

// 画面を開くたびに、選択の帯を今のチェックの状態で描く（操作の帯は、何も選んでいなければ見た目を消す）。
// このスクリプトは表より前で読まれることがあるので、組み立てが終わってから描く（何度描いても同じ）。
// 古い版（window.EvoDataTable が true）が残っているときは、描き直しの関数が無いので何もしない
if (typeof window.EvoDataTable.renderSelections === 'function') {
    if (window.Evo && typeof window.Evo.ready === 'function') {
        window.Evo.ready(window.EvoDataTable.renderSelections);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.EvoDataTable.renderSelections, {once: true});
    } else {
        setTimeout(window.EvoDataTable.renderSelections, 0);
    }
}
