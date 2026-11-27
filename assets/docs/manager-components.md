# 管理画面の共通の部品

管理画面の画面を作る・直すときに使う共通の部品の一覧と、新しい部品を足すときの決まり。部品ごとの使い方は、それぞれの資料を正本とする。この資料は入口で、決まりを書き写さない。

## 今ある部品

作る前に、ここにあるものを使えないか確かめる。足りないときは、部品のほうを広げる（画面の中で作らない）。

| 用途 | 部品 | 置き場所 | 資料 |
| --- | --- | --- | --- |
| 画面の差し替え・モーダルで別の画面を開く・保存の送信 | `EvoShell` | `manager/media/script/shell.js`・`manager/media/style/common/shell.css` | `manager-shell.md` |
| 知らせる・確かめる・入力を求める（操作を止める） | `EvoDialog` | `manager/media/script/evo-dialog.js` | `manager-dialog.md` |
| 済んだことを短く知らせる（操作を止めない） | `EvoNotice`・`manager()->notice()` | `manager/media/script/evo-notice.js`・`manager/media/style/common/notice.css` | `manager-notice.md` |
| 説明の吹き出し | `EvoTooltip` | `manager/media/script/evo-tooltip.js` | 先頭の docblock |
| 表・ページ送り・並べ替え・一括の選択 | `DataTable`・`EvoDataTable` | `manager/includes/controls/data_table.class.php`・`manager/media/script/data-table.js`・`manager/media/style/common/selection-dock.css` | `data-table.md` |
| 編集画面の行（見出しと値） | `EvoFieldRow` | `manager/includes/field_row.class.inc.php`・`manager/media/style/common/field_row.tpl` | `manager-form-row.md` |
| 右クリック・行の操作のメニュー | `EvoContextMenu` | `manager/includes/controls/contextmenu.php`・`manager/media/script/context-menu.js`・`manager/media/style/common/context-menu.css` | `theme-variables.md`（右クリックメニュー） |
| タブ | tabpane | `manager/media/script/tabpane.js`・`manager/media/style/common/tabs.css` | `theme-variables.md`（タブ） |
| パスワードの欄（表示の切り替え・自動生成・決まりの確かめ） | `EvoPasswordField` | `manager/media/script/password-field.js` | `password-policy.md` |
| 保存後の行き先・差し戻し先 | `EvoFormFlow` | `manager/includes/form_flow.class.inc.php` | 先頭の docblock |
| POST・AJAX への CSRF トークンの付与（画面で足さない） | `EvoCsrf` | `manager/media/script/csrf.js` | 先頭の docblock |
| 素の JS の共通の関数（`Evo.ready()`・`Evo.escapeHtml()` など） | `Evo` | `manager/media/script/evo.js` | `jquery-migration.md` |

画面ごとの JS（`document-actions.js`・`move-document.js`・`publish-draft.js`・`config-check.js` など）は、その画面のためのもので、ここには載せない。

## 新しい部品を足すとき

1. **本当に新しい部品が要るか**を上の一覧で確かめる。一画面だけの部品は作らない（AGENTS.md の「既存パターンの評価と改善」）。作るなら、ほかの画面からも同じ呼び方で使える形にする
2. **JS** は `manager/media/script/<名前>.js` に 1 部品 1 ファイル。外から使う名前は `window.Evo<名前>` の 1 つだけにし、中は即時関数で閉じる。jQuery は使わない。使い方（引数・戻り値）は先頭の docblock に書き、これを正本にする
3. **CSS** は `manager/media/style/common/<名前>.css` に書き、`@layer evo-components` に入れる（テーマ・画面の規則に負けるように。`forms.css` の先頭の説明）。色はテーマの変数（`theme-variables.md`）、余白は `--space-*`（`theme-spacing.md`）から選ぶ。新しい変数は、テーマごとに変えたいときだけ足し、`theme-variables.md` に書く。JS の中に CSS を持たない
4. **読み込み**は `manager/actions/header.inc.php` で全画面に。URL には `manager_asset()` で版を付ける（`manager-shell.md` の「JS・CSS の読み込みと版」）
5. **置き場所**は `#mainPane` の中。body の直下に置くしかないとき（シェルが `#mainPane` を差し替えても残す、など）だけ、`shell.css` の body 直下の規則の例外に足し、理由をコメントと `manager-shell.md` に書く
6. **文言**は言語ファイル（日本語と英語）に置き、`js_literal(lang(...))` で JS へ渡す。書き方は `ui-wording.md`
7. **出力のエスケープ**は出す直前で、部品が持つ（呼び出し側に任せない）。HTML を受ける引数は、受けることを docblock に書く
8. **読み上げとキーボード**: 状態の知らせは `role="status"` の領域（急ぎは `role="alert"`）、閉じるボタンには `aria-label`。Esc・フォーカスの扱いは `EvoDialog` にそろえる。動きは `prefers-reduced-motion` で止める
9. **資料**: `assets/docs/<名前>.md` を作り（使い分け・決まり・置き場所）、この資料の一覧と AGENTS.md の「ドキュメントマップ」に足す
10. **シェルの中と外**で確かめる（シェルの部分の応答・モーダル・単独のページ。ファイルピッカーの単独のページは `shell.css` を読まない）

## 今の部品で、決まりとそろっていないもの

- `EvoTooltip` は CSS を JS の中に持ち、テーマの変数を使っていない（上の 3.）。直すときに `manager/media/style/common/tooltip.css` へ移す
