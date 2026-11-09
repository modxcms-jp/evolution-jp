// 管理モードの入口(メニューの「ファイル管理」)。振る舞い(behaviors)を作って filebrowser.js の registerEntry() に渡す。ファイルを「選んで閉じる」ことはしない。
// 開始の関数は入口の登録表 window.EvoFileBrowserEntries.manage に置き、断片内のインライン script から呼び直す(shell.js が script を再実行しないため。ピッカーと同じ)。
// テキストの表示・編集・新しいファイル・zip の展開・プレビューのダウンロードを足すのはこの入口だけ(共通の部品には足さない)。

import { registerEntry } from '../filebrowser.js';
import { t } from './i18n.js';
import { apiUrl, failureMessage, fetchJson, loadFailureMessage, postForm } from './api.js';
import { createTextEditor } from './textEditor.js';

function extensionOf(name) {
    var dot = String(name).lastIndexOf('.');
    return dot === -1 ? '' : String(name).slice(dot + 1).toLowerCase();
}

// 最初からテキストエディターで開くか。拡張子の一覧(サーバーの BrowserConfig::TEXT_EXTENSIONS)は設定で受け、
// .htaccess のような名前は最後のドットより後ろ(htaccess)で照合する(サーバーと同じ)。
// 一覧に無いファイルも、openFile がサーバーに中身を確かめさせて、テキストなら開く
function isTextFile(config, name) {
    return (config.textExtensions || []).indexOf(extensionOf(name)) !== -1;
}

// 画面(host)ごとに 1 つのテキストのダイアログ。画面が差し替わっても、古い画面のものを使わない
var editors = new WeakMap();

function editorFor(host) {
    var editor = editors.get(host);
    if (!editor) {
        editor = createTextEditor({
            overlayEl: host.root.querySelector('[data-role="text-overlay"]'),
            config: host.config,
            onChanged: function () { host.reload(); }
        });
        editors.set(host, editor);
    }
    return editor;
}

function createFile(host) {
    var name = window.prompt(t('fb_new_file_prompt'));
    if (!name) {
        return;
    }
    var folder = host.state.folder;
    postForm(host.config, host.state, 'newfile', { name: name }, folder)
        .then(function (result) {
            host.reload();
            editorFor(host).open(result.name, folder);
        })
        .catch(function (err) {
            window.alert(failureMessage(err, 'fb_new_file_failed'));
        });
}

function unzipFile(host, name) {
    if (!window.confirm(t('fb_confirm_unzip', name))) {
        return;
    }
    postForm(host.config, host.state, 'unzip', { target: name }, host.state.folder)
        .then(function (result) {
            var lines = [t('fb_unzip_done', result.extracted)];
            if (result.failed && result.failed.length) {
                lines.push('', t('fb_unzip_entry_failed'), result.failed.join('\n'));
            }
            if (result.skipped && result.skipped.length) {
                lines.push('', t('fb_unzip_skipped'), result.skipped.join('\n'));
            }
            window.alert(lines.join('\n'));
            host.reload();
        })
        .catch(function (err) {
            window.alert(failureMessage(err, 'fb_unzip_failed'));
        });
}

// 拡張子の一覧に無いファイルを開く。サーバー(readText)が中身をテキストと認めればエディターへ、
// 認めない(テキストでない not_text・大きすぎる too_large)ときはエラーを出さずにプレビュー(情報とダウンロード)を出す。
// 権限・見つからない・読めないなどのほかの失敗は、通常の読み込みの失敗として知らせる。
// 画像・動画・音声は、プレビューで見せるので確かめない
function openByContent(entry, host) {
    if (entry.image || entry.media) {
        host.preview.open(entry);
        return;
    }
    var folder = host.state.folder;
    fetchJson(apiUrl(host.config, { action: 'readText', folder: folder, file: entry.name }))
        .then(function () {
            editorFor(host).open(entry.name, folder);
        })
        .catch(function (err) {
            if (err && (err.code === 'not_text' || err.code === 'too_large')) {
                host.preview.open(entry);
                return;
            }
            window.alert(loadFailureMessage(err));
        });
}

var behaviors = {
    openFile: function (entry, host) {
        if (isTextFile(host.config, entry.name)) {
            editorFor(host).open(entry.name, host.state.folder);
            return;
        }
        openByContent(entry, host);
    },
    previewActions: function (entry, host) {
        return [{ label: t('fb_download'), onSelect: function () { host.download([entry.name]); } }];
    },
    // 右クリックメニューに足す項目(ファイルには「表示・編集」「展開」、何も無い所には「新しいファイル」)。書き込めないフォルダでは作る・展開する項目は出さない
    contextMenuItems: function (entry, host) {
        var items = [];
        if (!entry) {
            if (host.state.writable) {
                items.push({ label: t('fb_new_file'), onSelect: function () { createFile(host); } });
            }
            return items;
        }
        // 画像・動画・音声はプレビューで見せるので出さない。ほかはすべて出し、テキストでなければ情報とダウンロードのプレビューになる
        if (!entry.image && !entry.media) {
            items.push({ label: t('fb_text_open'), onSelect: function () { behaviors.openFile(entry, host); } });
        }
        if (extensionOf(entry.name) === 'zip' && host.state.writable) {
            items.push({ label: t('fb_unzip'), onSelect: function () { unzipFile(host, entry.name); } });
        }
        return items;
    },
    // サーバーは overwrite=1 が付かない限り上書きしない。保存される名前(無害化した後)を並べて確かめる
    confirmOverwrite: function (names) {
        return window.confirm(t('fb_confirm_overwrite', names.join(', ')));
    }
};

registerEntry('manage', behaviors);
