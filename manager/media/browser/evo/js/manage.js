// 管理モードの入口(メニューの「ファイル管理」の画面が読む)。サイトのファイルを管理する用途の振る舞い(behaviors)を作って、
// filebrowser.js の registerEntry() に渡す。ファイルを「選んで閉じる」ことはしない。
// 断片をメインの領域へ差し込むとき、shell.jsがscriptを再実行しないため、開始の関数を入口の登録表
// (window.EvoFileBrowserEntries.manage)に置き、断片内のインラインscriptから毎回呼び直す(ピッカーと同じ)。

import { registerEntry } from '../filebrowser.js';
import { t } from './i18n.js';

var behaviors = {
    // ダブルクリック: プレビューを開く(テキストの表示・編集は M3 で足す)
    openFile: function (entry, host) {
        host.preview.open(entry);
    },
    // プレビューに足すボタンは無い
    previewActions: function () {
        return [];
    },
    // 右クリックメニューに足す項目は、まだ無い(表示・編集・展開・新しいファイルは M3 で足す)
    contextMenuItems: function () {
        return [];
    },
    // サーバーは、overwrite=1 が付かない限り同じ名前のファイルを上書きしない。サーバーが保存する名前(無害化した後)を並べて確かめる
    confirmOverwrite: function (names) {
        return window.confirm(t('fb_confirm_overwrite', names.join(', ')));
    }
};

registerEntry('manage', behaviors);
