# テーマの色の変数

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が持つ、共通の色の変数の一覧と書き方。同梱テーマも外部のテーマも、これに従う。

共通部（`manager/media/style/common/shell.css`、`manager/media/script/evo-dialog.js`、ファイルブラウザ、日付ピッカー）は、テーマの中身を知らない。テーマが `:root` で定義する下の変数だけを頼りに色を決める。変数の名前と意味が、テーマと共通部の約束（契約）になる。

## 変数の一覧

すべてのテーマが `:root` で定義する。

| 変数 | 役割 | 共通部での参照 |
| --- | --- | --- |
| `--color-primary` | 主な操作の色。主ボタンの地の単色 | evo-dialog.js の OK ボタンの地と枠・フォーカスの枠、filebrowser.css の主ボタン・選択行・選択項目、datepicker.css の選択日 |
| `--color-primary-start` / `--color-primary-end` | 主な操作の色を 2 色で描くときの組。グラデーションの始点・終点、または地と枠。使い方はテーマが決める | shell.css のモーダルの見出し、filebrowser.css の主ボタンのホバー |
| `--color-primary-hover-start` / `--color-primary-hover-end` | 同じ組の、ホバー時の色 | 共通部は参照しない（テーマの主ボタンが使う） |
| `--color-primary-active-start` / `--color-primary-active-end` | 同じ組の、押したときの色 | 共通部は参照しない（テーマの主ボタンが使う） |
| `--color-text-inverse` | 主な操作の色の上に置く文字の色 | shell.css のモーダルの見出し、evo-dialog.js の OK の文字、filebrowser.css・datepicker.css の選択の上の文字 |
| `--color-surface` | 面（パネル・ダイアログ・入力欄）の地の色 | shell.css、filebrowser.css、datepicker.css |
| `--color-text-primary` | 本文の文字の色 | filebrowser.css、datepicker.css |
| `--color-text-muted` | 補助的な文字の色 | filebrowser.css、datepicker.css |
| `--color-border-default` | 通常の枠の色 | filebrowser.css、datepicker.css |
| `--color-border-light` | 薄い枠・区切り線の色 | filebrowser.css、datepicker.css |
| `--color-link` | リンクの色 | filebrowser.css、datepicker.css |
| `--color-error` | エラー・削除の色 | filebrowser.css、datepicker.css |

ファイルブラウザは、ほかに `--color-background-page`、`--color-success`、`--color-text-subtle` も既定値付きで読む。テーマが定義すればそれに従い、無ければ既定値で表示される。

## 書き方

- 主ボタン（`.actionButtons #Button1 a`、`li.primary a`、`a.primary`、`a.default` と、それぞれの `:hover` / `:active`）の地・枠・文字の色は、`--color-primary*` と `--color-text-inverse` だけで書く。色を直接（`#xxx`、`rgb()`）書かない
- テーマ固有の別名（`--color-accent-green-*` など）を作って主ボタンの色にしない。別名を挟むと、共通部と同じ色の根拠がなくなる
- 主な操作の色を 2 色で描くか単色で描くかは、テーマが決める。`--color-primary` と `-start` / `-end` の両方を必ず定義し、共通部がどちらを読んでも主ボタンと同じ系統の色になるようにする
- 共通部は、変数を既定値付き（`var(--color-primary, #375665)`）で参照する。テーマが読み込まれない画面や、変数が足りない外部のテーマでも崩れないようにするため。共通部に色を直接書かない
- 影（`box-shadow`・`text-shadow` の `rgba()` など）は対象外。テーマが好きな色で書いてよい

## 新しいテーマを作るとき

1. `:root` に上の表の変数をすべて定義する
2. 主ボタンの規則を、`--color-primary*` と `--color-text-inverse` だけで書く
3. `grep -nE "color-primary|color-text-inverse" style.css` で、定義と主ボタンでの参照がそろっているか確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`SoftSlate/style.css` など。
