# 管理画面の知らせ（トースト）

保存・複製・移動のあとなど、操作を止めずに短く知らせるときは、共通の部品 `EvoNotice`（`manager/media/script/evo-notice.js`）で出す。`manager/actions/header.inc.php` が全画面で読み込むので、画面ごとに読み込まなくてよい。部品は「文を 1 つ受けて、3 秒で消える成功の知らせ」だけで、`EvoNotice.show(message)` で出す（引数は `evo-notice.js` の先頭の docblock）。見た目は `manager/media/style/common/notice.css`。

## ダイアログとの使い分け

| 知らせたいこと | 使う部品 |
| --- | --- |
| 操作が済んだこと（保存しました・複製しました） | `EvoNotice`（3 秒で消える） |
| 続ける前に読ませる・選ばせること（削除の確認）、読ませたいこと、失敗（保存できなかったこと） | `EvoDialog`（`manager-dialog.md`） |
| プラグインのエラー | これまでどおりシステムの知らせ（`SystemAlertMsgQueque` → `ManagerAPI::sysAlert()`） |

保存できなかったことや、読ませたい説明を `EvoNotice` で知らせない（数秒で消えると、読めない・保存できたと思われる）。警告・案内の型、閉じるまで残す指定、知らせの中のリンクは持たない。

## サーバー側から出す

画面を移る（リダイレクトする）処理は、移る前に `manager()->notice($message)`（`manager/includes/extenders/ex_managerapi.php`）で積む。セッションに置かれ、次に描く画面の後処理 `evoRenderPaneFooterExtras()`（`manager/includes/helpers.php`）が `EvoNotice.show()` の呼び出しを出す。ページ全体の応答でも、シェルの部分の応答（`X-Evo-Pane`）でも同じ通り道を通る。管理画面ごと読み直す画面（`refresh=page`。グローバル設定の保存のあとの待ちの画面）では出さずに残し、読み直したあとの画面で出す。

`$message` は文字として出す（HTML にしない）。文言は言語ファイルに置く（キーは `notice_*`）。シェルの部品（ツリー・メニュー。`a=1`）の再取得の応答でも読まずに残す。

保存の処理は、知らせを自分で組み立てない。

- 要素・ユーザーなど、行き先を `EvoFormFlow` の表で決める保存は `EvoFormFlow::redirectAfterSave($kind, $id, $afterSave, $extra)`（「保存しました。」を積んで `afterSaveUrl()` へ移る）
- 行き先を自分で決める保存（文書・下書き・グローバル設定・プロフィール）は、移る前に `EvoFormFlow::noticeSaved()`

## 画面の JS から出す

`EvoNotice.show(message)`。文言は `js_literal(lang(...))` で JS へ渡す（JS に直書きしない）。

## 置き場所と読み上げ

- 本文の欄の右上、保存などのボタンの並び（`#actions`）のすぐ下の `#evoNotice` に、新しいものを下へ積む。押したボタンの近くに出して見落とされにくくし、子リソースの一覧の下に貼り付く操作の帯（`data-table.md`）とも重ねない。縦の位置は、並びの高さがテーマで違い、並びの無い画面もあるため、`evo-notice.js` が並びの下端を測って `--evo-notice-top` に入れる（並びが無ければ本文の欄の上端の下）。シェルが本文を差し替えたとき・窓の大きさが変わったときも測り直す。シェルが `#mainPane` を差し替えても残すため body の直下に置き、`shell.css` の body 直下の規則の例外に入れてある（`manager-shell.md`）
- `#evoNotice` を読み込み時に空のまま作って `role="status"`（`aria-live="polite"`）を付け、中の知らせには role を付けない。初めて出すときは、領域を作った次のフレームで中身を入れる（空の領域が先にあるほうが、追加を読み上げに拾われる）
- 3 秒で消える（「保存しました。」のような短い文に合わせた長さ）。乗せている間・中にフォーカスがある間は消さない。× で閉じられる
- 動きを減らす設定（`prefers-reduced-motion`）では、出し入れの動きを付けない

## 見た目

全テーマ共通（`@layer evo-components`）。テーマは `:root` の色の変数を決めるだけで、この部品の規則は書かない。地は `.okmsg` と同じ `--color-success-bg`、左の帯と枠は `--okmsg-border-color`（`theme-variables.md`）。地の色が半透明のテーマがあるため、不透明な白の上に重ねる。余白は `--space-*`（`theme-spacing.md`）。
