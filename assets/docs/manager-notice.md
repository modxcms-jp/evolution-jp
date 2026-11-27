# 管理画面の知らせ（トースト）

保存・複製・移動のあとなど、操作を止めずに短く知らせるときは、共通の部品 `EvoNotice`（`manager/media/script/evo-notice.js`）で出す。`manager/actions/header.inc.php` が全画面で読み込むので、画面ごとに読み込まなくてよい。引数の細かい決まりは、`evo-notice.js` の先頭の docblock を正本とする。見た目は `manager/media/style/common/notice.css`。

## ダイアログとの使い分け

| 知らせたいこと | 使う部品 |
| --- | --- |
| 操作が済んだこと（保存しました・複製しました） | `EvoNotice`（数秒で消える） |
| 続ける前に読ませる・選ばせること（削除の確認、保存できなかったこと） | `EvoDialog`（`manager-dialog.md`） |
| プラグインのエラー | これまでどおりシステムの知らせ（`SystemAlertMsgQueque` → `ManagerAPI::sysAlert()`） |

保存できなかったことを `EvoNotice` で知らせない（数秒で消えると、保存できたと思われる）。

## サーバー側から出す

画面を移る（リダイレクトする）処理は、移る前に `manager()->notice($message, $options)`（`manager/includes/extenders/ex_managerapi.php`）で積む。セッションに置かれ、次に描く画面の後処理 `evoRenderPaneFooterExtras()`（`manager/includes/helpers.php`）が `EvoNotice.show()` の呼び出しを出す。ページ全体の応答でも、シェルの部分の応答（`X-Evo-Pane`）でも同じ通り道を通る。管理画面ごと読み直す画面（`refresh=page`。グローバル設定の保存のあとの待ちの画面）では出さずに残し、読み直したあとの画面で出す。

- `$message` は文字として出す（HTML にしない）。文言は言語ファイルに置く（キーは `notice_*`）
- `$options`: `type`（`success`・`info`・`warning`）、`sticky`（閉じるまで残す）、`links`（`[['href' => 管理画面の URL, 'label' => 文言]]`。シェルの中で開く）

保存の処理は、知らせを自分で組み立てない。

- 要素・ユーザーなど、行き先を `EvoFormFlow` の表で決める保存は `EvoFormFlow::redirectAfterSave($kind, $id, $afterSave, $extra)`（「保存しました。」を積んで `afterSaveUrl()` へ移る）
- 行き先を自分で決める保存（文書・グローバル設定・ロール・プロフィール）は、移る前に `EvoFormFlow::noticeSaved()`

## 画面の JS から出す

`EvoNotice.show(message, {type, sticky, links})`。文言は `js_literal(lang(...))` で JS へ渡す（JS に直書きしない）。

## 置き場所と読み上げ

- 本文の欄の右上、保存などのボタンの並び（`#actions`）のすぐ下の `#evoNotice` に、新しいものを下へ積む。押したボタンの近くに出して見落とされにくくし、子リソースの一覧の下に貼り付く操作の帯（`data-table.md`）とも重ねない。縦の位置は、並びの高さがテーマで違い、並びの無い画面もあるため、`evo-notice.js` が並びの下端を測って `--evo-notice-top` に入れる（並びが無ければ本文の欄の上端の下）。シェルが本文を差し替えたとき・窓の大きさが変わったときも測り直す。シェルが `#mainPane` を差し替えても残すため body の直下に置き、`shell.css` の body 直下の規則の例外に入れてある（`manager-shell.md`）
- 1 件ずつ `role="status"`（`warning` は `role="alert"`）を付け、読み上げに届ける
- 消える知らせは 3 秒で消える（「保存しました。」のような短い文に合わせた長さ）。乗せている間・中にフォーカスがある間は消さない。どの知らせも × で閉じられる
- 動きを減らす設定（`prefers-reduced-motion`）では、出し入れの動きを付けない

## 見た目

全テーマ共通（`@layer evo-components`）。テーマは `:root` の色の変数を決めるだけで、この部品の規則は書かない。成功は `.okmsg` と同じ `--color-success-bg`・`--okmsg-border-color`、`info` は `--color-primary`、`warning` は `--color-warning` を、左の帯と枠に使う（`theme-variables.md`）。余白は `--space-*`（`theme-spacing.md`）。
