// 管理モードの入口(メニューの「ファイル管理」の画面が読む)。サイトのファイルを管理する用途の振る舞い(behaviors)を作って、
// filebrowser.js の registerEntry() に渡す。ファイルを「選んで閉じる」ことはしない。
// 断片をメインの領域へ差し込むとき、shell.jsがscriptを再実行しないため、開始の関数を入口の登録表
// (window.EvoFileBrowserEntries.manage)に置き、断片内のインラインscriptから毎回呼び直す(ピッカーと同じ)。
// この入口だけが、テキストの表示・編集(js/textEditor.js)・新しいファイル・zip の展開を足す(共通の部品には足さない)。

import { registerEntry } from '../filebrowser.js';
import { t } from './i18n.js';
import { failureMessage, postForm } from './api.js';
import { createTextEditor } from './textEditor.js';

function extensionOf(name) {
    var dot = String(name).lastIndexOf('.');
    return dot === -1 ? '' : String(name).slice(dot + 1).toLowerCase();
}

// テキストとして開けるファイルか。拡張子の一覧(サーバーの BrowserConfig::TEXT_EXTENSIONS)は設定で受ける。
// .htaccess のような拡張子だけの名前は、最後のドットより後ろ(htaccess)が一覧にあれば開ける(サーバーと同じ照合)
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
            // 保存したあとは、一覧の更新時刻とサイズが変わっている
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
            // 作ったファイルは、そのままテキストのダイアログで開く
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

var behaviors = {
    // ダブルクリックと Enter: テキストならテキストのダイアログ、それ以外はプレビュー
    openFile: function (entry, host) {
        if (isTextFile(host.config, entry.name)) {
            editorFor(host).open(entry.name, host.state.folder);
            return;
        }
        host.preview.open(entry);
    },
    // プレビューに足すボタンは無い
    previewActions: function () {
        return [];
    },
    // 右クリックメニューに足す項目。ファイル 1 件には「表示・編集」(テキスト)と「展開」(.zip)を、何も無い所には「新しいファイル」を足す。
    // 書き込めないフォルダでは、作る・展開する項目は出さない(サーバーも断る)
    contextMenuItems: function (entry, host) {
        var items = [];
        if (!entry) {
            if (host.state.writable) {
                items.push({ label: t('fb_new_file'), onSelect: function () { createFile(host); } });
            }
            return items;
        }
        if (isTextFile(host.config, entry.name)) {
            items.push({ label: t('fb_text_open'), onSelect: function () { editorFor(host).open(entry.name, host.state.folder); } });
        }
        if (extensionOf(entry.name) === 'zip' && host.state.writable) {
            items.push({ label: t('fb_unzip'), onSelect: function () { unzipFile(host, entry.name); } });
        }
        return items;
    },
    // サーバーは、overwrite=1 が付かない限り同じ名前のファイルを上書きしない。サーバーが保存する名前(無害化した後)を並べて確かめる
    confirmOverwrite: function (names) {
        return window.confirm(t('fb_confirm_overwrite', names.join(', ')));
    }
};

registerEntry('manage', behaviors);
