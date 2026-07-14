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

    function normalizePathSegment(segment) {
        try {
            return decodeURIComponent(segment).trim();
        } catch (e) {
            return segment.trim();
        }
    }

    function splitCurrentPath(currentValue, type) {
        if (!currentValue && currentValue !== 0) {
            return null;
        }

        var raw = String(currentValue).trim();
        if (!raw) {
            return null;
        }

        var pathname = raw;
        try {
            pathname = new URL(raw, global.location.href).pathname;
        } catch (e) {
            pathname = raw.split('#')[0].split('?')[0];
        }

        var normalizedType = String(type || '').toLowerCase();
        var segments = pathname
            .split('/')
            .map(function (segment) {
                return normalizePathSegment(segment);
            })
            .filter(Boolean);
        var typeIndex = segments.indexOf(normalizedType);

        if (typeIndex === -1 || typeIndex >= segments.length - 1) {
            return null;
        }

        return {
            folder: segments.slice(typeIndex + 1, -1).join('/'),
            select: segments[segments.length - 1]
        };
    }

    function withInitialSelection(url, currentValue) {
        if (!url) {
            return url;
        }

        var resolved = new URL(url, global.location.href);
        var type = resolved.searchParams.get('type') || resolved.searchParams.get('Type') || 'images';
        var currentPath = splitCurrentPath(currentValue, type);

        if (currentPath && currentPath.folder) {
            resolved.searchParams.set('folder', currentPath.folder);
        }
        if (currentPath && currentPath.select) {
            resolved.searchParams.set('select', currentPath.select);
        }

        return resolved.href;
    }

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

    global.evoOpenFilePicker = function (url, currentValue) {
        var pickerUrl = withInitialSelection(url, currentValue);

        if (evoShellAvailable()) {
            global.EvoShell.openFilePicker(pickerUrl, function (pickedUrl) {
                if (typeof global.SetUrl === 'function') {
                    global.SetUrl(pickedUrl);
                }
            });
            return;
        }
        openPopupWindow(pickerUrl, screen.width * 0.7, screen.height * 0.7);
    };
})(window);
