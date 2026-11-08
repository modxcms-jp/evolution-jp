// DataTable の共通の動き（assets/docs/data-table.md）。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoDataTable) {
    window.EvoDataTable = true;
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

        // bar は常に出ている role="status" の外枠。中身だけを出し入れして、読み上げに告知させる
        function renderSelection(bar) {
            const rows = selectionRows(bar);
            const checked = rows.filter(function (c) { return c.checked; }).length;
            const total = Number(bar.dataset.total) || 0;
            const d = bar.dataset;
            bar.textContent = '';
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
            bar.append(body);
        }

        // 「この一覧のすべて」: このページの行を選び、ほかのページの値を hidden として足す
        function selectAllInList(bar) {
            const form = bar.closest('form');
            let ids = [];
            try { ids = JSON.parse(bar.dataset.allIds || '[]'); } catch (err) { ids = []; }
            const onPage = new Set();
            selectionRows(bar).forEach(function (c) { c.checked = true; onPage.add(c.value); });
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
