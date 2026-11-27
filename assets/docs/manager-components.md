# 管理画面の共通の部品

管理画面の画面を作る・直すときに使う共通の部品の一覧と、新しい部品を足すときの決まり。部品ごとの使い方は、それぞれの資料（資料が無い部品は先頭の docblock）を正本とする。部品を足すときに横断で守る決まりは、この資料を正本とする。この資料は入口で、各部品の使い方を書き写さない。

## 今ある部品

作る前に、ここにあるものを使えないか確かめる。足りないときは、部品のほうを広げる（画面の中で作らない）。

| 用途 | 部品 | 置き場所 | 資料 |
| --- | --- | --- | --- |
| 画面の差し替え・モーダルで別の画面を開く・保存の送信 | `EvoShell` | `manager/media/script/shell.js`・`manager/media/style/common/shell.css` | `manager-shell.md` |
| 知らせる・確かめる・入力を求める（操作を止める） | `EvoDialog` | `manager/media/script/evo-dialog.js` | `manager-dialog.md` |
| 済んだことを短く知らせる（操作を止めない） | `EvoNotice`・`manager()->notice()` | `manager/media/script/evo-notice.js`・`manager/media/style/common/notice.css` | `manager-notice.md` |
| 説明の吹き出し | `EvoTooltip` | `manager/media/script/evo-tooltip.js` | 先頭の docblock |
| 文書の削除・公開などの確認と実行（入口が共通で使う） | `EvoDocumentActions` | `manager/media/script/document-actions.js` | `manager-shell.md`（文書の操作の設定） |
| 表・ページ送り・並べ替え・一括の選択 | `DataTable`・`EvoDataTable` | `manager/includes/controls/data_table.class.php`・`manager/media/script/data-table.js`・`manager/media/style/common/selection-dock.css` | `data-table.md` |
| 一覧の表・ページ送りの見た目（`.grid`・`.pagination`） | 共通の CSS | `manager/media/style/common/tables.css` | `theme-variables.md`（表・一覧） |
| セクションの箱（`.sectionHeader`・`.sectionBody`） | 共通の CSS | `manager/media/style/common/sections.css` | `theme-variables.md`（セクション） |
| 操作ボタンの帯（`#actions`・`.actionButtons`） | 共通の CSS | `manager/media/style/common/action-buttons.css` | `theme-variables.md`（操作ボタンの帯） |
| ボタン（`.button`）・入力欄 | 共通の CSS | `manager/media/style/common/forms.css` | `theme-variables.md`（フォーム部品） |
| 編集画面の行（見出しと値） | `EvoFieldRow` | `manager/includes/field_row.class.inc.php`・`manager/media/style/common/field_row.tpl` | `manager-form-row.md` |
| 右クリック・行の操作のメニュー | `ContextMenu`（PHP。`addItem()` で項目を足す）・`EvoContextMenu`（JS。表示と非表示） | `manager/includes/controls/contextmenu.php`・`manager/media/script/context-menu.js`・`manager/media/style/common/context-menu.css` | `theme-variables.md`（右クリックメニュー） |
| タブ | `WebFXTabPane` | `manager/media/script/tabpane.js`・`manager/media/style/common/tabs.css` | `tabpane.js` の先頭の docblock・`theme-variables.md`（タブ） |
| 行の並べ替え（`data-sortable`） | `MODXSortable` | `manager/media/script/dragdrop-sort.js` | `dragdrop-sort.js` の先頭近くのコメント |
| 日付選び（`class="DatePicker"`） | `EvoDatePicker` | `manager/media/script/datepicker/datepicker.js`・`manager/media/script/datepicker/datepicker.css`（`loadDatePicker()` が読み込む） | 先頭のコメント |
| ファイルピッカーを開く | `window.evoOpenFilePicker` | `manager/media/browser/evo-file-picker.js` | 先頭のコメント |
| パスワードの欄（表示の切り替え・自動生成・決まりの確かめ） | `EvoPasswordField` | `manager/media/script/password-field.js` | `password-policy.md` |
| 保存後の行き先・差し戻し先 | `EvoFormFlow` | `manager/includes/form_flow.class.inc.php` | 先頭の docblock |
| POST・AJAX への CSRF トークンの付与（画面で足さない） | `EvoCsrf` | `manager/media/script/csrf.js` | 先頭の docblock |
| 素の JS の共通の関数（`Evo.ready()`・`Evo.fetch()`・`Evo.escapeHtml()` など） | `Evo` | `manager/media/script/evo.js` | `jquery-migration.md`（`escapeHtml` は `evo.js` を見る） |

画面ごとの JS（`move-document.js`・`publish-draft.js`・`config-check.js` など）は、その画面のためのもので、ここには載せない。要素（テンプレート・チャンク・スニペットなど）の編集画面だけで共有している `element-category.js`・`element-lock.js`・`element-properties.js`（`EvoElement*`）も同じ扱いで、使い方は各ファイルの先頭の docblock を見る。

## 新しい部品を足すとき

1. **本当に新しい部品が要るか**を上の一覧で確かめる。一画面だけの部品は作らない（AGENTS.md の「既存パターンの評価と改善」）。作るなら、ほかの画面からも同じ呼び方で使える形にする
2. **JS** は `manager/media/script/<名前>.js` に 1 部品 1 ファイル。ファイル名は小文字のハイフンつなぎ（`evo-dialog.js`・`data-table.js` と同じ）。外から使う名前は `window.Evo<名前>` の 1 つだけにし、中は即時関数で閉じる。jQuery は使わない。シェルの画面遷移で同じ JS が読み直されるので、トップレベルは `var` か `window` への代入で定義し、全画面で読む部品は `if (!window.EvoX)` で 2 回目を何もしない形にする（`assets/docs/jquery-migration.md`・`assets/docs/manager-shell.md` の「組み立て」）。使い方（引数・戻り値）は先頭の docblock に書き、これを正本にする
3. **CSS** は `manager/media/style/common/<名前>.css`（小文字のハイフンつなぎ）に書き、`@layer evo-components` に入れる（テーマ・画面の規則に負けるように。`manager/media/style/common/forms.css` の先頭の説明）。色はテーマの変数（`assets/docs/theme-variables.md`）から選ぶ。余白は、部品の内側も含めて、既定値つきの `var(--space-2, 8px)` の形で読む（値は `assets/docs/theme-spacing.md` の表。この資料はブロックの縦の間が対象で、部品の内側の余白は対象外なので、値だけを借りる）。新しい変数は、テーマごとに変えたいときだけ足し、`theme-variables.md` に書く。JS の中に CSS を持たない。例外は 2 つ
    - テーマの CSS を通らない画面（ログイン・単独のページ）でも出す部品は、JS が CSS を足してよい（`EvoDialog`。`assets/docs/manager-dialog.md`）
    - テーマの規則に勝たせる必要があるときは、層の外に置く（`manager/media/style/common/selection-dock.css` の先頭のコメント・`theme-variables.md`）
4. **読み込み**は、CSS は `manager_theme_stylesheets()`（`manager/includes/helpers.php`）の一覧に足す。テーマより先に読めて、ヘッダを通らない単独のページにも効く。全画面で使う JS は `manager/actions/header.inc.php`、一部の画面だけで使う JS は、使う画面で読む。どちらも URL には `manager_asset()` で版を付ける（`assets/docs/manager-shell.md` の「JS・CSS の読み込みと版」）。`header.inc.php` が自分で読む CSS は、`shell.css`・`selection-dock.css`・`notice.css` の 3 つに限る
5. **置き場所**は `#mainPane` の中。body の直下に置くしかないとき（シェルが `#mainPane` を差し替えても残す、など）だけ、`manager/media/style/common/shell.css` の body 直下の規則の例外に足し、理由をコメントと `manager-shell.md` に書く
6. **文言**は言語ファイル（日本語と英語）に置き、`js_literal(lang(...))` で JS へ渡す。書き方は `assets/docs/ui-wording.md`
7. **出力のエスケープ**は出す直前で、部品が持つ（呼び出し側に任せない）。文字として受ける引数は部品がエスケープする。HTML を受ける引数は、呼び出し側でのエスケープが要ることを docblock に書く（`EvoDialog` の `message`）
8. **読み上げとキーボード**: 状態の知らせは `role="status"` の領域（急ぎは `role="alert"`）、閉じるボタンには `aria-label`。Esc・フォーカスの扱いは `EvoDialog` にそろえる。動きは `prefers-reduced-motion` で止める
9. **資料**: docblock に収まらない使い分け・決まりがあるときだけ、`assets/docs/<名前>.md` を作り（使い分け・決まり・置き場所）、この資料の一覧と AGENTS.md の「ドキュメントマップ」に足す。収まるときは docblock を正本にして、この資料の一覧に足す
10. **シェルの中と外**で確かめる（シェルの部分の応答・モーダル・単独のページ）。ファイルピッカーの単独のページは `header.inc.php` を通らず、読むのは `manager/media/browser/evo/lib/BrowserView.php` の `standalonePage()` に並べたものだけ（`shell.css` のほか `evo.js`・`csrf.js`・`evo-notice.js` なども無い）

## 今の部品で、決まりとそろっていないもの

- `EvoTooltip` は CSS を JS の中に持ち、テーマの変数を使っていない（上の 3.）。ログインや単独のページでは読まない（`header.inc.php` だけが読む）ので、`EvoDialog` のような例外の理由は当たらない。直すときは `manager/media/style/common/tooltip.css` へ移す
- `EvoDatePicker` の CSS（`manager/media/script/datepicker/datepicker.css`）は JS の隣にあり、`@layer evo-components` に入っていない（上の 3.）
