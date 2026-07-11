/*
 * ファイルブラウザ呼び出しの共通ヘルパー。
 *
 * シェル内(EvoShell)はモーダルで、chromeless(QuickManager等のiframe埋め込み)は
 * 従来どおりポップアップウィンドウで開く。以前はこの判定とポップアップ実装が
 * browser.js・mutate_module.dynamic.php・mutate_user_pf.dynamic.php・
 * mutate_user/tpl/javascript.php・mutate_web_user.dynamic.php の5箇所に
 * 個別にコピーされていたため、判定条件を変える際に5箇所同時修正が必要だった。
 * このファイルへ集約し、各呼び出し元は window.evoOpenFilePicker(url) を呼ぶだけにする。
 *
 * 選択結果は各ページが個別に定義する window.SetUrl(url) 経由で受け取る(後方互換)。
 */
(function (global) {
    'use strict';

    function evoShellAvailable() {
        return !!(
            global.EvoShell
            && typeof global.EvoShell.openFilePicker === 'function'
            && global.document.body.classList.contains('evo-shell')
        );
    }

    function openPopupWindow(url, width, height) {
        var iLeft = (screen.width - width) / 2;
        var iTop = (screen.height - height) / 2;
        var sOptions = 'toolbar=no,status=no,resizable=yes,dependent=yes'
            + ',width=' + width
            + ',height=' + height
            + ',left=' + iLeft
            + ',top=' + iTop;
        return global.open(url, 'FCKBrowseWindow', sOptions);
    }

    global.evoOpenFilePicker = function (url) {
        if (evoShellAvailable()) {
            global.EvoShell.openFilePicker(url, function (pickedUrl) {
                if (typeof global.SetUrl === 'function') {
                    global.SetUrl(pickedUrl);
                }
            });
            return;
        }
        openPopupWindow(url, screen.width * 0.7, screen.height * 0.7);
    };
})(window);
