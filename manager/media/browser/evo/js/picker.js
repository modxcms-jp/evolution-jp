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
function embedKind() {
    if (typeof window.EvoFileBrowserPick === 'function') {
        return 'shell';
    }
    if (window.opener && typeof window.opener.SetUrl === 'function') {
        return 'popup';
    }
    if (window.parent && window.parent !== window) {
        return 'iframe';
    }
    return '';
}

function pickFile(path) {
    var kind = embedKind();
    if (kind === 'shell') {
        window.EvoFileBrowserPick(path);
    } else if (kind === 'popup') {
        window.opener.SetUrl(path);
        window.close();
    } else if (kind === 'iframe') {
        window.parent.postMessage({ mceAction: 'evoFbPick', url: path }, window.location.origin);
    } else {
        // eslint-disable-next-line no-console
        console.log('[filebrowser] picked', path);
    }
}

// 何も選ばずにピッカーを閉じる(画像編集を取り消したとき)
function closePicker() {
    var kind = embedKind();
    if (kind === 'shell') {
        window.EvoShell.closeModal();
    } else if (kind === 'popup') {
        window.close();
    } else if (kind === 'iframe') {
        window.parent.postMessage({ mceAction: 'evoFbClose' }, window.location.origin);
    }
}

var behaviors = {
    itemActions: ['preview'],
    openFile: function (entry) {
        pickFile(entry.path);
    },
    // 選択の帯と右クリックメニューの「このファイルを選択」(filebrowser.js の SELECTION_ACTIONS が、この関数があるときだけ先頭に置く)
    pickFile: function (entry) {
        pickFile(entry.path);
    },
    previewActions: function (entry) {
        return [{
            label: t('fb_pick_this_file'),
            primary: true,
            onSelect: function () { pickFile(entry.path); }
        }];
    },
    // 「このファイルを選択」は SELECTION_ACTIONS が右クリックにも出すので、ここでは足さない
    contextMenuItems: function () {
        return [];
    },
    // 画像編集を開いた状態で起動したとき(edit=1)の終わり方。保存したファイルを選んだものとして返す / 取り消したら閉じる
    editDone: function (entry) {
        pickFile(entry.path);
    },
    editCancel: closePicker,
    // サーバーは同じ名前を別の名前(名前(1).拡張子)で保存するので、上書きの確認は起きない
    confirmOverwrite: function () {
        return false;
    }
};

registerEntry('picker', behaviors);
