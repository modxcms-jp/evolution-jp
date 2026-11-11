# 管理画面のダイアログ

管理画面で、知らせる・確かめる・入力を求めるダイアログは、共通の部品 `EvoDialog`（`manager/media/script/evo-dialog.js`）で出す。`manager/actions/header.inc.php` が全画面で読み込むので、画面ごとに読み込まなくてよい。引数と戻り値の細かい決まりは、`evo-dialog.js` の先頭の docblock を正本とする。

## 決まり

- 新しく書くコードでは、画面ごとに自前の `<dialog>`・モーダル（オーバーレイの `div` など）を作らない。見た目（テーマの色の変数）・Esc とフォーカスの扱い・シェルとの重なりを `EvoDialog` がそろえて持つ。
- `window.alert()`・`confirm()`・`prompt()` も新しくは使わない（見た目がそろわず、ボタンの文言が言語に合わない）。本体に残っている呼び出しの置き換えは、その画面を直すときに行う（この決まりのためだけに一括では直さない）。
- 文言は言語ファイルに置き、`js_literal(lang(...))` で JS へ渡す。JS に日本語や英語を直書きしない（例: `manager/actions/tool/export_site.static.php` の `$exportJsKeys`）。
- 別の管理画面をモーダルの中で開くのは、ダイアログではなくシェルのモーダル（`EvoShell.openModal()`・リンクの `data-modal`。`manager/media/script/shell.js`）。
- 例外: 公開側の QuickManager（`assets/plugins/qm/js/qm.js`）は、編集画面を iframe で開く自前の `<dialog>` を持つ。公開側では `evo-dialog.js` を読み込まないため。

## 呼び出し

| 呼び出し | 用途 | callback に渡す値 |
| --- | --- | --- |
| `EvoDialog.alert(message, title, callback, options)` | 知らせる（ボタンは OK だけ） | true（Esc も OK と同じ） |
| `EvoDialog.confirm(message, title, callback)` | 確かめる | OK で true、キャンセル・Esc で false |
| `EvoDialog.prompt(message, value, title, callback)` | 1 行を入力させる | OK で入力値、キャンセル・Esc で null |
| `EvoDialog.form(message, fields, title, callback, options)` | 欄を複数並べて入力させる | OK で `{name: 値}`、キャンセル・Esc で null |

- `message` は HTML として出し、改行は `<br />` にする。利用者のデータ（ページタイトルなど）を入れるときは、呼び出し側でエスケープする。`title` は文字列として出す（空文字なら見出しを出さない）。
- `options`（`alert`・`form`）: `{icon: 'warning'}` でメッセージの左に警告アイコンを出す。`okLabel` で、そのダイアログだけ OK ボタンの文言を変える。
- `form` の `fields` は `[{name, label, type, value}]`。`type` は `text`（既定）・`password` など。Enter はどの欄でも OK（IME の確定の Enter は除く）。入力値はブラウザに保存させないよう `autocomplete` を付ける（パスワード欄は `new-password`。ブラウザによっては保存の提案を完全には止められない）。
- ボタンの文言は `manager/actions/header.inc.php` が言語に合わせる（`EvoDialog.okLabel`・`EvoDialog.cancelLabel`）。
- 新しいダイアログを開くと、開いているダイアログは閉じる（コールバックは呼ばない）。順に聞くときは、前のダイアログのコールバックの中で次を開く。
- 待ちたいときは Promise で包む（例: `manager/actions/tool/export_site.static.php` の `askBasicAuth`）。

## jquery.alerts の名前

`jAlert`・`jConfirm`・`jPrompt` は、jquery.alerts と同じ名前と引数で `EvoDialog` の `alert`・`confirm`・`prompt` を呼ぶ（外部のアドオンが呼んでいるため残す）。本体の新しいコードは `EvoDialog.*` を呼ぶ。置き換えの表は `jquery-migration.md` の「よく使う呼び出しの置き換え」。

## 置き場所

ダイアログは `document.body` の直下に足し、`showModal()` で最前面に出す。シェルの画面では、body 直下の要素は幅 0 の重ね置きになる決まりがあり（`manager-shell.md` の「body 直下の要素」）、`.evo-dialog` はその対象から外してある。自前の要素を body 直下に足さない理由もそこに書いている。
