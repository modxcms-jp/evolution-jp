// 管理モードの入口(メニューの「ファイル管理」の画面が読む)。サイトのファイルを管理する用途の振る舞い(behaviors)を作って、
// filebrowser.js の init() に渡す。ファイルを「選んで閉じる」ことはしない。
// 断片をメインの領域へ差し込むとき、shell.jsがscriptを再実行しないため、初期化を
// グローバル関数として公開し、断片内のインラインscriptから毎回呼び直す(ピッカーと同じ)。

import { init } from '../filebrowser.js';
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
    // 同じ名前のファイルがあればサーバーは上書きする(旧ファイル管理と同じ)ので、送る前に名前を並べて確かめる
    beforeUpload: function (files, existingNames) {
        var overwritten = files
            .map(function (file) { return file.name; })
            .filter(function (name) { return existingNames.indexOf(name) !== -1; });
        if (overwritten.length === 0) {
            return true;
        }
        return window.confirm(t('fb_confirm_overwrite', overwritten.join(', ')));
    }
};

function start() {
    init(document.getElementById('evoFileBrowser'), behaviors);
}

window.EvoFileBrowserInit = start;
start();
