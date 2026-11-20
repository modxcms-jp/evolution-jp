// 右クリックメニュー（manager/includes/controls/contextmenu.php の ContextMenu）の部品。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoContextMenu) {
    window.EvoContextMenu = {
        get: function (id) {
            return document.getElementById(id);
        },
        hide: function (id) {
            const menu = window.EvoContextMenu.get(id);
            if (menu) {
                menu.style.visibility = 'hidden';
            }
        }
    };
}
