// DataTable の共通の動き（assets/docs/data-table.md）。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoDataTable) {
    window.EvoDataTable = true;
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
}
