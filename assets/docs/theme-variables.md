# テーマの色の変数

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が持つ、共通の色の変数の一覧と書き方。同梱テーマも外部のテーマも、これに従う。

共通部（`manager/media/style/common/shell.css`、`manager/media/script/evo-dialog.js`、ファイルブラウザ、日付ピッカー）は、テーマの中身を知らない。テーマが `:root` で定義する下の変数だけを頼りに色を決める。変数の名前と意味が、テーマと共通部の約束（契約）になる。

色の変数のほかに、送信中の見た目を選ぶ変数（`--evo-submitting-style`）もこの文書で扱う（「送信中の見た目」の節を参照）。

余白の変数（`--space-*`）と、ブロックの縦の間の決まりは [theme-spacing.md](theme-spacing.md) を参照する。

## 変数の一覧

すべてのテーマが `:root` で定義する。

| 変数 | 役割 | 共通部での参照 |
| --- | --- | --- |
| `--font-size-base` | 本文の文字の大きさ（`body` の `font-size`）。`75%` のようなブラウザの既定に掛ける割合では書かない | テーマの `body` が読む |
| `--color-primary` | 主な操作の色。主ボタンの地の単色 | evo-dialog.js の OK ボタンの地と枠・フォーカスの枠、filebrowser.css の主ボタン・選択行・選択項目、datepicker.css の選択日 |
| `--color-primary-start` / `--color-primary-end` | 主な操作の色を 2 色で描くときの組。グラデーションの始点・終点、または地と枠。使い方はテーマが決める | shell.css のモーダルの見出し、filebrowser.css の主ボタンのホバー |
| `--color-primary-hover-start` / `--color-primary-hover-end` | 同じ組の、ホバー時の色 | 共通部は参照しない（テーマの主ボタンが使う） |
| `--color-primary-active-start` / `--color-primary-active-end` | 同じ組の、押したときの色 | 共通部は参照しない（テーマの主ボタンが使う） |
| `--color-text-inverse` | 主な操作の色の上に置く文字の色 | shell.css のモーダルの見出し、evo-dialog.js の OK の文字、filebrowser.css・datepicker.css の選択の上の文字 |
| `--color-surface` | 面（パネル・ダイアログ・入力欄）の地の色 | shell.css、selection-dock.css（`.selection-dock` の背景の color-mix）、sections.css（箱の中の `legend` の地）、filebrowser.css、datepicker.css |
| `--color-section-header-text` | セクションの見出しの帯（`.sectionHeader`）の文字の色 | sections.css |
| `--color-section-bg` | セクションの箱（`.sectionBody`）の地の色 | sections.css（`--section-body-bg` の既定） |
| `--color-text-primary` | 本文の文字の色 | shell.css（設定画面の小見出し `.settings-section`、表の選択の帯 `.data-table-selection-body`）、selection-dock.css（操作の帯 `.selection-dock` の文字と背景の color-mix）、filebrowser.css、datepicker.css |
| `--color-text-muted` | 補助的な文字の色 | shell.css（検索画面 `.search-*`）、filebrowser.css、datepicker.css |
| `--color-border-default` | 通常の枠の色 | sections.css（箱の中の `legend` の枠）、filebrowser.css、datepicker.css |
| `--color-border-light` | 薄い枠・区切り線の色 | shell.css（設定画面の小見出しの下線、表の選択の帯の枠、検索画面の区切り線）、selection-dock.css（操作の帯 `.selection-dock` の枠）、filebrowser.css、datepicker.css |
| `--color-link` | リンクの色 | filebrowser.css、datepicker.css |
| `--color-error` | エラー・削除の色 | selection-dock.css（操作の帯 `.selection-dock` の削除ボタン）、filebrowser.css、datepicker.css |

ファイルブラウザは、ほかに `--color-background-page`、`--color-success`、`--color-text-subtle`、`--gray-50`、`--gray-100` も（shell.css の検索画面も `--color-success`・`--gray-100` を）既定値付きで読む。テーマが定義すればそれに従い、無ければ既定値で表示される。

## 送信中の見た目

色の変数とは別に、テーマは送信中（`EvoShell.submit`）の見た目を `:root` の `--evo-submitting-style` で選べる。共通部（`shell.css`）が、押したボタンにだけアニメーションを出す。

| 値 | 見た目 |
| --- | --- |
| `spinner` | 文字の右で輪が回る |
| `stripes` | ボタンの上を斜線が横へ流れる |
| `dots` | 文字の右で 3 つの点が順に弾む |

- 未指定、またはこの 3 つ以外の値は、アニメーションなし。送信中のボタンは今までどおり半透明になるだけ
- アニメーションが付くのは押したボタン 1 つだけ。ほかの送信ボタンと `.actionButtons` のボタンは、半透明のまま
- `<input type="submit">` / `<input type="image">` は疑似要素を持てないので、値によらず明滅（半透明との往復）になる
- 色はテーマの変数ではなく、ボタンの文字色（`currentColor`）に従う。文字色はボタンごとの地の色に必ず合うため。テーマ側で色を書かない。ボタンの `background` も上書きしない
- `prefers-reduced-motion: reduce` の環境では、アニメーションを止めて半透明だけにする
- 押したボタンが決まらない送信（見えない送信ボタンを使う Enter、スクリプトからの送信など）は、半透明だけになる
- 同梱テーマの割り当て: `spinner` は AuroraFlow・SoftSlate、`stripes` は RubberWhite・RevoClassic、`dots` は SakuraFlow・AromaHop
- RevoStyle は凍結のため未指定（見た目は変わらない。[theme-spacing.md](theme-spacing.md) の「対象外のテーマ」を参照）

## 書き方

- 主ボタンの地・枠・文字の色（`--button-primary-*` の値）は、`--color-primary*` と `--color-text-inverse` だけで書く。色を直接（`#xxx`、`rgb()`）書かない。主ボタンの規則そのものは `common/forms.css` にあり、テーマは書かない（「フォーム部品」を参照）
- 一覧の操作の帯（`.data-table-selection-dock`。[data-table.md](data-table.md)）のボタンは `.actionButtons` の中の `button` なので、`common/forms.css` の一般のボタンの規則が当たる（右上のボタン群と同じ見た目）。帯の地色・枠・影・角は、`#actions` の規則に `.selection-dock:not(.is-empty)` を足して同じ値にする（外部のテーマはこれでそろう。ファイルブラウザの選択の帯も同じクラス）
- `.actionButtons` のボタンの `:focus-visible` の枠（`outline: 2px solid` と `outline-offset: 2px`、色はテーマのフォーカス色か本文の色。帯の地が灰色のテーマでも 3:1 以上になる色にする。リンク色は灰色の地で 3:1 を下回るテーマがある）は、`outline: none` を持つテーマが必ず足す。帯の上下の余白（8px 以上）が枠の 4px を切らない
- テーマ固有の別名（`--color-accent-green-*` など）を作って主ボタンの色にしない。別名を挟むと、共通部と同じ色の根拠がなくなる
- 主な操作の色を 2 色で描くか単色で描くかは、テーマが決める。`--color-primary` と `-start` / `-end` の両方を必ず定義し、共通部がどちらを読んでも主ボタンと同じ系統の色になるようにする
- 共通部は、変数を既定値付き（`var(--color-primary, #375665)`）で参照する。テーマが読み込まれない画面や、変数が足りない外部のテーマでも崩れないようにするため。共通部に色を直接書かない
- 契約の変数に無い中間の面の色（コード欄の上の帯 `.code-editor-header` の背景など）は、`color-mix(in srgb, <変数> N%, <変数>)` で契約の変数から作ってよい（例: `color-mix(in srgb, var(--color-text-primary) 6%, var(--color-surface))`）。新しい変数は足さない。`color-mix()` に対応しないブラウザ（Chrome 110 以前、Safari 16.1 以前など）では、その背景が透明になる。許容する（枠と余白は残り、表示は崩れない）
- コード欄の帯の右側の文字（`.code-editor-options`）は、まず `--color-text-muted` を使う。帯の背景（上の `color-mix`）に対して 4.5:1 に足りないテーマは、`--color-text-primary` や、それと `--color-surface` の `color-mix` に替える（RevoStyle は凍結のため `#707070` のまま。[theme-spacing.md](theme-spacing.md) の「対象外のテーマ」を参照。AromaHop は `--color-text-primary`、RubberWhite は `color-mix(in srgb, var(--color-text-primary) 75%, var(--color-surface))`。`color-mix()` に対応しないブラウザでは宣言が捨てられ、親の文字色になる。濃くなる側なので許容する）
- どの枠の変数（`--color-border-default`・`--color-border-light`）を使うかは、テーマが決める。隣の部品の枠とつなげて見せるときは、つながる方に近い方を選ぶ（例: AromaHop の `.code-editor-header` は、真下のテキスト欄の緑の枠に近い `--color-border-light`）
- 影（`box-shadow`・`text-shadow` の `rgba()` など）は対象外。テーマが好きな色で書いてよい

## フォーム部品

入力欄・セレクト・ボタンの見た目は、共通部の `manager/media/style/common/forms.css` が 1 か所で決める。テーマは `:root` で下の変数の値を決めるだけにし、同じ性質を書いた規則（`form input[type=text]`、`form select`、`.actionButtons :is(a, button)` の地や枠など）を持たない。

- 土台（`@layer evo-base`）: 素の `input`（文字を入れる種類。`text`・`password`・`search`・`email`・`url`・`number`・`date` など）・`select`・`textarea`。目印のクラスは要らない。`form` の中かどうかも問わない
- 部品（`@layer evo-components`）: ボタン。単独で置くボタンは `class="button"`、主ボタンは `class="button primary"`。`.actionButtons` の中の `a`・`button` も同じボタンとして扱い、その中の `#Button1 a`、`li.primary a`、`a.primary`、`a.default` も主ボタンになる
- 素の `button` は素の見た目のまま（アイコンだけのボタンなど、テーマの見た目にしたくないものがあるため）。テーマは `button` に幅や見た目を付けない
- 層に入れた規則は、層に入れていない規則に必ず負ける。テーマの素の要素の規則（`a` の色・下線など）は `@layer evo-base { ... }` に入れる。入れないと、ボタンとして書いた `a` の文字の色まで上書きする
- 寸法（高さ・角・余白）・地・枠・影・動きは変数で決める。テーマが変数で表せない飾りを足すとき（`.actionButtons a.disabled`、下書きの `.opendraft a` など）は、寸法を書かない
- `.actionButtons` の中の並べ方（`float: left`）は、テーマの規則に残す

| 変数 | 意味 | 既定値（forms.css） |
| --- | --- | --- |
| `--control-height` | 入力欄・セレクト・ボタン（`input[type=submit\|button\|reset]` を含む）の高さ（`min-height`）。テーマは決めない。forms.css が `body` で「本文の 1 行の高さ ＋ 上下の余白 × 2 ＋ 枠 2px」を 4px の目盛りに切り上げて決める（本文 12px・行の高さ 1.5 なら 28px） | 計算 |
| `--control-padding-y` | 高さの計算に使う上下の余白 | `4px` |
| `--control-padding-x` | 入力欄・セレクトの左右の余白 | `8px` |
| `--control-radius` | 入力欄・セレクトの角 | `3px` |
| `--control-border` | 入力欄・セレクトの枠（`border` の値） | `1px solid #999` |
| `--control-bg` / `--control-color` | 入力欄・セレクトの地（`background` の値）と文字の色 | `#fff` / 親の色 |
| `--control-shadow` / `--control-transition` | 入力欄・セレクトの影と動き | なし |
| `--control-focus-border-color` / `--control-focus-bg` / `--control-focus-shadow` | フォーカスしたときの枠の色・地・影 | `green` / `--control-bg` / なし |
| `--button-padding-x` / `--button-radius` | ボタンの左右の余白と角 | `12px` / `4px` |
| `--button-font-size` / `--button-font-weight` | ボタンの文字の大きさと太さ | `1em` / `700` |
| `--button-color` / `--button-bg` | 一般のボタンの文字の色と地（`background` の値。グラデーションも可） | `#444` / `--color-neutral-button-start` |
| `--button-border-width` / `--button-border-color` | 一般のボタンの枠 | `0` / `transparent` |
| `--button-shadow` / `--button-backdrop-filter` / `--button-transition` | 一般のボタンの影・背景のぼかし・動き | なし |
| `--button-hover-color` / `--button-hover-bg` / `--button-hover-border-color` / `--button-hover-shadow` / `--button-hover-transform` | ホバーしたとき | 文字・枠・影は通常と同じ、地は `--color-neutral-button-hover-end`、動きなし |
| `--button-active-bg` / `--button-active-shadow` / `--button-active-transform` | 押したとき | 地は `--color-neutral-button-active-start`、影は通常と同じ、動きなし |
| `--button-focus-ring-color` | キーボードで選んだときの枠（`outline`）の色 | `--color-text-primary` |
| `--button-primary-color` / `--button-primary-bg` / `--button-primary-border-color` / `--button-primary-shadow` | 主ボタン | `--color-text-inverse` / `--color-primary` / `transparent` / なし |
| `--button-primary-font-weight` | 主ボタンの文字の太さ | `--button-font-weight` |
| `--button-primary-hover-bg` / `--button-primary-hover-border-color` / `--button-primary-hover-shadow` | 主ボタンをホバーしたとき | `--color-primary-hover-start` / 通常と同じ / 通常と同じ |
| `--button-primary-active-bg` / `--button-primary-active-border-color` / `--button-primary-active-shadow` | 主ボタンを押したとき | `--color-primary-active-start` / 通常と同じ / 通常と同じ |

`input[type=submit|button|reset]` も、`.button` と同じ部品として、この変数で描く（主ボタンの `.primary` は付けない）。テーマは `input` のボタンだけの規則を書かない。

## ページの見出し

画面の一番上の見出しの帯（`h1`）は、`manager/media/style/common/theme-base.css`（`@layer evo-base`）の規則が描く。文字の大きさ（`1.25em`）・太さ（`bold`）・行の高さ（`1.4`）・字間（`.05em`）・下の余白（12px）・枠の太さ（1px）は全テーマ共通で、テーマは変えない。テーマは `:root` で下の変数の値を決め、`h1` の規則を書かない。

| 変数 | 意味 | 既定値（theme-base.css） |
| --- | --- | --- |
| `--page-title-color` | 文字の色 | 親から受け継ぐ |
| `--page-title-bg` | 地 | `transparent` |
| `--page-title-border-color` | 枠の色 | `transparent` |
| `--page-title-radius` / `--page-title-padding` / `--page-title-shadow` | 角・内側の余白・影 | `4px` / `8px` / なし |
| `--page-title-draft-bg` / `--page-title-draft-border-color` | 下書きを編集している画面の帯（`h1.draft`）の地と枠の色 | `#f4e2c3` / `--page-title-border-color` |

## 右クリックメニュー

ツリーの右クリックメニュー（`#mx_contextmenu`、`manager/frames/tree.php`）と、一覧の操作メニュー（`.contextMenu`、`manager/includes/controls/contextmenu.php`）は、`manager/media/style/common/context-menu.css`（`@layer evo-components`）が同じ見た目で描く。幅（ツリーは 180px、一覧は項目が折り返さない幅）・行の高さ（`1.4`）・余白・アイコンの薄さは全テーマ共通。テーマは `:root` で下の変数の値を決め、右クリックメニューの規則を書かない。

| 変数 | 意味 | 既定値（context-menu.css） |
| --- | --- | --- |
| `--context-menu-bg` / `--context-menu-border-color` / `--context-menu-radius` / `--context-menu-shadow` | メニューの地・枠の色（4 辺を分けて書いてよい）・角・影 | `--color-surface` / `--color-border-light #dcdcdc #dcdcdc --color-border-light` / `0` / `--shadow-xl` |
| `--context-menu-title-bg` / `--context-menu-title-color` / `--context-menu-title-text-shadow` | ツリーのメニューの見出し（選んだ項目の名前）の地・文字の色・文字の影 | `--color-primary-start` から `--color-primary-end` へのグラデーション / `--color-surface` / `0 -1px 0 var(--gray-900)` |
| `--context-menu-item-color` / `--context-menu-item-radius` | 項目の文字の色・角（見出しの角も同じ） | `--gray-900` / `0` |
| `--context-menu-item-hover-bg` / `--context-menu-item-hover-color` / `--context-menu-item-hover-border-color` | 乗せたときの地・文字の色・枠の色 | `#dde4eb` / `--color-text-primary` / `#fdfdfd #fdfdfd #dcdcdc` |
| `--context-menu-item-disabled-color` | 使えない項目の文字の色 | `#787878` |
| `--context-menu-separator` | 区切り線（`background` に入れるので、グラデーションも書ける） | `silver` |

既定値は RevoClassic の値。

## セクション

見出しの帯（`.sectionHeader`）と本体の箱（`.sectionBody`）の見た目は、`manager/media/style/common/sections.css`（`@layer evo-components`）が決める。テーマは `:root` で下の変数の値を決める。

- `--color-section-*` は色の契約（上の「変数の一覧」）、`--section-*` はセクションの部品の変数。見出しの帯の文字色は `--color-section-header-text` で決める。箱の地（`--section-body-bg`）の既定は `--color-section-bg` なので、単色の地のテーマは `--color-section-bg` だけを決めればよい
- 多くのテーマで同じ値の性質（見出しの帯と legend の文字の太さ `bold`、legend の地 `--color-surface` と枠 `1px solid var(--color-border-default)`）は、sections.css に値で書く。違う値にしたいテーマは、同じセレクタの規則をテーマに書いて上書きする（層の外なので必ず勝つ）
- 一部のテーマにしかない性質（見出しの帯の地・余白・影、箱の角・影など）は、テーマの規則に書く
- `.sectionBody h3` は、まだこの決まりの外（テーマの規則のまま）。層に入れると、テーマの `h3` の規則に負けるため

| 変数 | 意味 | 既定値（sections.css） |
| --- | --- | --- |
| `--section-header-border` / `--section-header-radius` | 見出しの帯の枠と角 | `1px solid #ddd` / `8px 8px 0 0` |
| `--section-body-bg` / `--section-body-border` / `--section-body-padding` | 箱（`.section .sectionBody`・`.tab-page .sectionBody`）の地・枠・内側の余白 | `--color-section-bg`（無ければ `#f7f7f7`） / `1px solid #ccc` / `16px` |
| `--section-fieldset-padding` | 箱の中の `fieldset` の内側の余白 | `16px` |
| `--section-legend-padding` / `--section-legend-shadow` / `--section-legend-radius` | 箱の中の `legend` の余白・影・角 | `4px 16px` / `1px 1px 3px #ccc` / `3px` |

既定値は RevoClassic の値。

## タブ

タブ（tabpane.js の部品 `.dynamic-tab-pane-control`）の重なりと並び（`position`・`z-index`・`white-space`・`cursor` など）は、`manager/media/style/common/tabs.css`（`@layer evo-components`）が決める。形（角・枠・地・余白・文字色）はテーマの規則で決める。同梱テーマには、丸い錠剤の形（AromaHop・SakuraFlow）と、上だけ角丸のフォルダの見出しの形（ほかのテーマ）がある。

| 変数 | 意味 | 既定値（tabs.css） |
| --- | --- | --- |
| `--tab-page-padding` | タブの中（`.tab-page`）の内側の余白。`.sectionBody` の中か外かに関係なく、この値になる | `16px 16px 20px` |

## 表・一覧

一覧の表（`.grid`）、ページ送り（`.pagination`）、コード欄の上の帯（`.code-editor-header`）の見た目は、`manager/media/style/common/tables.css`（`@layer evo-components`）が決める。テーマは `:root` で下の変数の値を決める。

- 余白は値で書く（RevoStyle は `--space-*` の値が違うため、`var(--space-2)` と書くと値が変わる）
- 一部のテーマにしかない性質（表の角・影・`border-collapse`、見出しの行の下の枠、ページ送りの角など）は、テーマの規則に書く
- 並べ替えの印（`.grid th[aria-sort] a::after`）も tables.css が決める。色は `--color-primary`
- ページ送りの普段の地（`--pagination-bg`）と、今のページの地（`--pagination-active-bg`）は、どちらも tables.css が決める。普段の地だけをテーマに残すと、層の外の規則が勝ち、今のページの地が出なくなる

| 変数 | 意味 | 既定値（tables.css） |
| --- | --- | --- |
| `--grid-bg` / `--grid-border` | 表と行の地、表の枠 | `--color-surface`（無ければ `#fff`） / `1px solid silver` |
| `--grid-cell-padding` | セルの内側の余白 | `8px` |
| `--color-grid-header-text` / `--grid-header-weight` / `--grid-header-bg` | 見出しの行の文字の色・太さ・地（グラデーションも可） | `--gray-900`（無ければ `#333`） / `bold` / `#d2d2d2` |
| `--grid-alt-bg` / `--grid-hover-bg` | 1 行おきの行（`tbody > tr:nth-child(even)`）の地、行の操作のアイコン（`.gridRowIcon`）に乗ったときの地 | `#eeeeee` / `#f5f5dc` |
| `--color-pagination-text` / `--color-pagination-border` / `--pagination-bg` | ページ送りの文字の色・枠の色・地 | `--gray-900`（無ければ `#333`） / `--color-row-highlight`（無ければ `#D9E7C2`） / `none` |
| `--color-pagination-disabled` | 押せないページ（`span`）の文字の色 | `--color-row-highlight`（無ければ `#D9E7C2`） |
| `--color-pagination-active-text` / `--pagination-active-bg` / `--color-pagination-active-border` | 今のページと、乗ったときの文字の色・地・枠の色 | `--color-surface`（無ければ `#fff`） / `#7fa53d` / `#567959` |

既定値は RevoClassic の値。

## 新しいテーマを作るとき

1. `:root` に上の表の変数をすべて定義する
2. 共通の CSS（`common/forms.css`・`common/theme-base.css`・`common/sections.css`・`common/tabs.css`・`common/tables.css`・`common/action-buttons.css`・`common/context-menu.css`）にある規則は、テーマに書かない（値を変えたいときの上書きは除く）。共通の CSS はテーマより先に読まれる（`manager_theme_stylesheets()`）。`theme-base.css` の規則のうち、素の要素の規則（`h1` など）は土台の層、ほかは層の外にある。層の外の規則をテーマで上書きするときは、同じか高い詳細度で書く
3. 主ボタンの規則を、`--color-primary*` と `--color-text-inverse` だけで書く
4. `grep -nE "color-primary|color-text-inverse" style.css` で、定義と主ボタンでの参照がそろっているか確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`SoftSlate/style.css` など。
