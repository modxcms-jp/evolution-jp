// ピッカーの入口(browser.php が読む)。振る舞い(behaviors)を作って filebrowser.js の registerEntry() に渡す。
// モーダル再オープン時は shell.js が script を再実行しないため、開始の関数を入口の登録表(window.EvoFileBrowserEntries.picker)に置き、断片内のインライン script から呼び直す。

import { registerEntry } from '../filebrowser.js';
import { t } from './i18n.js';

// ファイルブラウザは3通りの埋め込まれ方をし、それぞれ選択結果の返し方が異なる。
// 上から順に判定し、該当する経路だけを実行する:
//   1. EvoShellモーダル断片(TV入力・ユーザー写真・モジュールアイコン等)
//      → shell.jsが公開するグローバルフックを直接呼ぶ
//   2. chromeless(QuickManager等)のポップアップウィンドウ
//      → window.opener.SetUrl(旧mcpuk互換の規約)
//   3. TinyMCEのeditor.windowManager.openUrl()によるiframeダイアログ
//      → postMessageでmcpuk-picker.jsのonMessageハンドラへ返す
function pickFile(path) {
    if (typeof window.EvoFileBrowserPick === 'function') {
        window.EvoFileBrowserPick(path);
        return;
    }
    if (window.opener && typeof window.opener.SetUrl === 'function') {
        window.opener.SetUrl(path);
        window.close();
        return;
    }
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ mceAction: 'evoFbPick', url: path }, window.location.origin);
        return;
    }
    // eslint-disable-next-line no-console
    console.log('[filebrowser] picked', path);
}

var behaviors = {
    itemActions: ['preview'],
    openFile: function (entry) {
        pickFile(entry.path);
    },
    previewActions: function (entry) {
        return [{
            label: t('fb_pick_this_file'),
            primary: true,
            onSelect: function () { pickFile(entry.path); }
        }];
    },
    // 右クリックメニューの先頭に「このファイルを選択」を足す
    contextMenuItems: function (entry) {
        if (!entry) {
            return [];
        }
        return [{ label: t('fb_pick_this_file'), onSelect: function () { pickFile(entry.path); } }];
    },
    // サーバーは同じ名前を別の名前(名前(1).拡張子)で保存するので、上書きの確認は起きない
    confirmOverwrite: function () {
        return false;
    }
};

registerEntry('picker', behaviors);
