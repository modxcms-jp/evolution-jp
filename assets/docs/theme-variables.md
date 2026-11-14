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
- 一覧の操作の帯（`.data-table-selection-dock`。[data-table.md](data-table.md)）のボタンは `.actionButtons` の中の `button` なので、`common/forms.css` の一般のボタンの規則が当たる（右上のボタン群と同じ見た目）。帯の地色・枠・影・角は、右上のボタン群（`#actions`）と同じ規則（`common/selection-dock.css`）が、テーマの `--actions-*` の変数で決める（下の「操作ボタンの帯」。ファイルブラウザの選択の帯も同じクラス）
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

編集画面の欄の名前（`.mutate-field-title`）・説明（`.comment`・`p.comment`）・テキスト欄（`form textarea`）の大きさは `forms.css`（層の中）が、日付の欄（`input.DatePicker`）・TV の欄の地（`#tv_body`）・読み取り専用の欄（`[readonly]`・`.readonly`）は `theme-base.css`（層の外。テーマの入力欄の規則と詳細度で張り合うため）が決める。テーマは下の変数で色とアイコンを決め、これらの規則を書かない。

| 変数 | 意味 | 既定値 |
| --- | --- | --- |
| `--mutate-field-title-color` | 欄の名前の文字の色（太さは `bold`） | `--color-warning` |
| `--comment-color` / `--comment-p-color` | 説明の文字の色（`p.comment` は `.9em`） | `--gray-500` / `--comment-color` |
| `--datepicker-icon` / `--datepicker-icon-focus` | 日付の欄のカレンダーのアイコン（`url()`）。ふだんと入力中 | `common/images/misc/date-icon.png` / `date-icon-focus.png` |
| `--readonly-bg` | 読み取り専用の欄の地 | `#eee` |

## ページの見出し

画面の一番上の見出しの帯（`h1`）は、`manager/media/style/common/theme-base.css`（`@layer evo-base`）の規則が描く。文字の大きさ（`1.25em`）・太さ（`bold`）・行の高さ（`1.4`）・字間（`.05em`）・下の余白（12px）・枠の太さ（1px）は全テーマ共通で、テーマは変えない。テーマは `:root` で下の変数の値を決め、`h1` の規則を書かない。

| 変数 | 意味 | 既定値（theme-base.css） |
| --- | --- | --- |
| `--page-title-color` | 文字の色 | 親から受け継ぐ |
| `--page-title-bg` | 地 | `transparent` |
| `--page-title-border-color` | 枠の色 | `transparent` |
| `--page-title-radius` / `--page-title-padding` / `--page-title-shadow` | 角・内側の余白・影 | `4px` / `8px` / なし |
| `--page-title-draft-bg` / `--page-title-draft-border-color` | 下書きを編集している画面の帯（`h1.draft`）の地と枠の色 | `#f4e2c3` / `--page-title-border-color` |

## 上のメニュー

上のメニュー（`manager/frames/menu.php` の `#Navcontainer`・`#nav`・`ul.subnav`）と右上のユーザー情報（`#topbar`・`#supplementalNav`）は、`manager/media/style/common/topmenu.css`（`@layer evo-components`）が描く。上の余白（12px）・左右の余白（16px）・項目の左右の余白（16px）・項目の間・サブメニューの帯の位置は全テーマ共通。テーマは `#topMenu`（または `:root`）で下の変数の値を決め、`#nav`・`#supplementalNav` の規則を書かない。ユーザー情報の項目の間（12px）と縦の位置（上のメニューの項目と同じ高さ）も全テーマ共通。一部のテーマにしかない飾り（すりガラスの効果、文字の影など）は、同じセレクタの規則をテーマに書く。メニューの段の高さは、`menu.php` の `fitMenuHeight()` がサブメニューの帯の下端に合わせる。

型（タブ・ピル・下線）は、角と枠の色の組み合わせで決める。タブの型は、選んでいる項目の下の枠を透明にし、帯を項目に 1px 重ねて、サブメニューの帯とつなげる。下線の型は、`--topnav-underline-width` を太くし、乗せたときと選んでいるときに下の枠だけに色を付ける。

| 変数 | 意味 | 既定値（topmenu.css） |
| --- | --- | --- |
| `--topnav-item-height` / `--subnav-height` / `--topnav-padding-top` | 項目の高さ・サブメニューの項目の高さ・上の余白。細いメニューの RevoStyle だけが小さくする | `32px` / `32px` / `12px` |
| `--topnav-link-color` / `--topnav-item-color` | メニューの文字の色・項目の文字の色 | `#e1f1f4` / `--topnav-link-color` |
| `--topnav-item-radius` / `--topnav-item-font-weight` / `--topnav-underline-width` | 項目の角・文字の太さ・下の枠の太さ（サブメニューの項目にも使う。既定ではサブメニューの項目は下の枠を持たない） | `6px 6px 0 0` / `normal` / `1px` |
| `--topnav-item-border-width` / `--topnav-subnav-overlap` | 項目の上と左右の枠の太さ・サブメニューの帯を項目に重ねる幅。下線の型は、どちらも 0 にする（下線の端を四角にし、帯の上の枠に重ねない） | `1px` / `1px` |
| `--topnav-link-hover-color` / `--topnav-link-hover-bg` / `--topnav-link-hover-border-color` | 乗せたときの文字の色・地・枠の色 | `#fff` / `#195a70` / `--topnav-border` |
| `--topnav-link-active-bg` / `--topnav-link-active-border-color` / `--topnav-link-active-font-weight` | 選んでいる項目の地・枠の色（上・左右・下）・文字の太さ | `#1b2e37` / `--topnav-border --topnav-link-active-bg transparent` / `--topnav-item-font-weight` |
| `--topnav-subnav-bg` / `--topnav-subnav-border-color` / `--topnav-subnav-shadow` | サブメニューの帯の地・上と下の枠の色・影 | `--topnav-link-active-bg` / `--topnav-border` / なし |
| `--topnav-sublink-color` / `--topnav-sublink-radius` / `--topnav-sublink-font-weight` | サブメニューの項目の文字の色・角・文字の太さ | `#f1fbfc` / `999px` / `normal` |
| `--topnav-sublink-hover-bg` / `--topnav-sublink-hover-color` / `--topnav-sublink-hover-border-color` | サブメニューの項目に乗せたとき（と表示中の画面の項目）の地・文字の色・下の枠の色 | `#184753` / `--topnav-link-hover-color` / `transparent` |
| `--topnav-sublink-pressed-color` | サブメニューの項目を押しているときの文字の色 | `#d9edf1` |
| `--supplemental-nav-bg` / `--supplemental-nav-border-color` / `--supplemental-nav-shadow` / `--supplemental-nav-padding-x` | 右上のユーザー情報（`#supplementalNav`）の地・枠の色・影・左右の余白。地を持たないテーマは、左右の余白を 0 にする | 半透明の暗いグラデーション / `transparent` / なし / `18px` |
| `--supplemental-nav-color` | ユーザー情報の文字（版番号など、リンクでない文字）の色 | `--topnav-link-color` |
| `--supplemental-nav-link-color` / `--supplemental-nav-link-font-weight` / `--supplemental-nav-link-hover-color` | ユーザー情報のリンクの色・太さ・乗せたときの色 | `#aab1b9` / `bold` / `#fff` |

既定値は RevoClassic の値。

## ツリー

ツリーの欄（`manager/frames/tree.php`・`nodes.php`）の見た目は、`manager/media/style/common/tree.css`（`@layer evo-components`）が描く。行の並びと字下げ、行の枠の太さは `common/shell.css` が決める。欄の余白・書体・行の高さは全テーマ共通。テーマは `:root` で下の変数の値を決め、ツリーの規則を書かない。

| 変数 | 意味 | 既定値（tree.css） |
| --- | --- | --- |
| `--tree-bg` / `--tree-shadow` | 欄（`div.treeframebody`）の地・影 | `#f7f7f7` / なし |
| `--tree-pane-border` | 欄と本文の境目（`shell.css` の `#treePane` の右の枠） | `3px solid #f7f7f7` |
| `--tree-node-color` | 行の名前の文字の色 | `#000` |
| `--tree-node-hover-bg` / `--tree-node-hover-border-color` / `--tree-node-hover-color` / `--tree-node-radius` | 乗せたときの地・枠の色・文字の色・角 | `#fff` / `#ccc` / `--tree-node-color` / `0` |
| `--tree-node-selected-bg` / `--tree-node-selected-border-color` / `--tree-node-selected-color` | 選んでいる行の地・枠の色・文字の色 | 乗せたときと同じ |
| `--tree-published-color` / `--tree-unpublished-color` / `--tree-deleted-color` / `--tree-empty-color` / `--tree-not-in-menu-color` | 行の状態の文字の色（公開・非公開・削除済み・子の無いフォルダ・メニューに出さない） | `#222` / `#b68282` / `#a52a2a` / `#aaa` / `#777` |
| `--tree-floater-border-color` | 並べ替えの枠（`#floater`）の枠の色 | `#ccc` |

既定値は RevoStyle・SoftSlate の値。

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

見出しの帯（`.sectionHeader`）と本体の箱（`.sectionBody`）は、`manager/media/style/common/sections.css`（`@layer evo-components`）が描く。`.section` の下の間（12px）、本体の下の間（12px）、見出しの帯と legend の文字の太さ（`bold`）、本体の中の小見出し（`.sectionBody h3`。`1.1em`・`bold`・下の間 12px）は全テーマ共通。テーマは `:root` で下の変数の値を決め、セクションの規則を書かない。

- 見出しの帯の型は 2 つある。地と枠の付いた帯（RevoClassic・AuroraFlow・SoftSlate・RubberWhite・RevoStyle）と、枠も地も持たない文字だけの見出し（AromaHop・SakuraFlow。`--section-header-padding: 0`・`--section-header-bg: transparent`。タブの中では文字を大きくして下に間を空ける）
- 帯と本体の区切りは、帯の下の枠か本体の上の枠のどちらかで作る。RevoClassic・RevoStyle は帯の下の枠を消す（`--section-header-border-bottom: none`）。AuroraFlow・SoftSlate は帯のすぐ後の本体の上の枠を消し、角を下だけ丸める（`--section-joined-body-*`）。RubberWhite は本体の上の枠をいつも消す（`--section-body-border-top: none`）
- `--color-section-*` は色の契約（上の「変数の一覧」）。箱の地（`--section-body-bg`）の既定は `--color-section-bg` なので、単色の地のテーマは `--color-section-bg` だけを決めればよい。`.section` の中の本体だけ別の地にするときは `--section-box-bg` を使う
- 一部のテーマにしかない飾り（RubberWhite のタブの中の見出しと本体の枠の色）は、テーマの規則に書く

| 変数 | 意味 | 既定値（sections.css） |
| --- | --- | --- |
| `--section-header-padding` / `--section-header-bg` / `--section-header-shadow` / `--section-header-color` / `--section-header-text-shadow` | 見出しの帯の余白・地・影・文字の色・文字の影 | `8px 16px` / `--color-section-header-end` / `0 1px 0 --color-surface inset` / `--color-section-header-text` / なし |
| `--section-header-border` / `--section-header-border-bottom` / `--section-header-radius` | 見出しの帯の枠・下の枠・角 | `1px solid #ddd` / `--section-header-border` / `8px 8px 0 0` |
| `--tab-section-header-gap` / `--tab-section-header-font-size` / `--tab-section-header-bg` | タブの中の見出しの下の間・文字の大きさ・地 | `0` / `1em` / `--section-header-bg` |
| `--section-body-bg` / `--section-body-border` / `--section-body-border-top` / `--section-body-padding` / `--section-body-radius` / `--section-body-shadow` | 箱（`.section .sectionBody`・`.tab-page .sectionBody`）の地・枠・上の枠・内側の余白・角・影 | `--color-section-bg`（無ければ `#f7f7f7`） / `1px solid #ccc` / `--section-body-border` / `16px` / `0` / なし |
| `--section-box-bg` | `.section` の中の箱の地 | `--section-body-bg` |
| `--tab-section-body-shadow` | タブの中の箱の影 | `--section-body-shadow` |
| `--section-joined-body-border-top` / `--section-joined-body-radius` | 見出しの帯のすぐ後の箱の上の枠・角 | `--section-body-border-top` / `--section-body-radius` |
| `--section-h3-color` | 本体の中の小見出しの文字の色 | 親から受け継ぐ |
| `--section-fieldset-padding` | 箱の中の `fieldset` の内側の余白 | `16px` |
| `--section-legend-padding` / `--section-legend-shadow` / `--section-legend-radius` | 箱の中の `legend` の余白・影・角 | `4px 16px` / `1px 1px 3px #ccc` / `3px` |

既定値は RevoClassic の値。

## 操作ボタンの帯

右上の操作ボタンの帯（`#actions`）の位置・余白と、中のボタン列の余白は、`manager/media/style/common/action-buttons.css`（`@layer evo-components`）が決める。シェルの中の位置は `shell.css` が決める。帯の地・枠・影・角は、一覧の操作の帯（`.selection-dock:not(.is-empty)`）と同じ規則で、`common/selection-dock.css`（層の外。`.selection-dock` の既定の見た目に勝つため）が決める。テーマは `:root` で下の変数の値を決め、帯の規則を書かない。一部のテーマにしかない飾り（RubberWhite の帯のすりガラス、RevoStyle の下書きを開くボタンのグラデーション）は、テーマの規則に書く。

| 変数 | 意味 | 既定値 |
| --- | --- | --- |
| `--actions-bg` / `--actions-border` / `--actions-shadow` / `--actions-radius` | 帯の地・枠（`border` に入れる値）・影・角 | `rgba(60, 60, 60, .2)` / `0` / なし / `--radius-full` |
| `--actions-and-color` | 「保存して」と次の動作の選択の間の「＋」の文字の色 | `#3b454f` |
| `--actions-disabled-color` | 使えないボタン（`a.disabled`）の文字の色 | `--gray-700` |

既定値は RevoClassic の値。

## タブ

タブ（tabpane.js の部品 `.dynamic-tab-pane-control`）は、`manager/media/style/common/tabs.css`（`@layer evo-components`）が描く。並び（折り返す `flex`）・重なり・文字の大きさ（`1em`）・誤りの印の太さは全テーマ共通。型と色は、テーマが `:root` で下の変数の値を決め、タブの規則を書かない。同梱テーマには、上だけ角丸のフォルダの見出しの型（RevoClassic・RevoStyle・RubberWhite・SoftSlate）、下線の型（AuroraFlow）、丸い錠剤の型（AromaHop・SakuraFlow）がある。一部のテーマにしかない飾り（SakuraFlow の本体のすりガラス）は、テーマの規則に書く。

フォルダの見出しの型は、タブを `--tab-top` で下へずらし、本体を `--tab-page-top` で上へずらして、選んでいるタブと本体の枠をつなげる（[theme-spacing.md](theme-spacing.md) の「例外」）。下線の型は、タブ列の下線（`--tab-row-border`）を本体の上辺にする。錠剤の型は、タブ列と本体の間を `--tab-row-gap` で空ける。

| 変数 | 意味 | 既定値（tabs.css） |
| --- | --- | --- |
| `--tab-row-gap` / `--tab-row-border` | タブ列の下の間・下線 | `0` / なし |
| `--tab-top` / `--tab-margin` / `--tab-padding` | タブを下へずらす量・外側の余白・内側の余白 | `1px` / `4px 4px 1px 0` / `8px 12px` |
| `--tab-border-width` / `--tab-border-color` / `--tab-radius` | タブの枠の太さ（下線の型は `0 0 2px`、錠剤の型は `0`）・枠の色・角 | `1px` / `--color-secondary-border` / `8px 8px 0 0` |
| `--tab-bg` / `--tab-color` / `--tab-font-weight` / `--tab-text-shadow` | タブの地・文字の色・太さ・文字の影 | `--color-secondary-start` / `--color-secondary-text` / `normal` / なし |
| `--tab-hover-bg` / `--tab-hover-color` / `--tab-hover-border-color` | 乗せたときの地・文字の色・枠の色 | `--color-secondary-hover-start` から `-end` へのグラデーション / `--color-secondary-hover-text` / `--tab-border-color` |
| `--tab-selected-bg` / `--tab-selected-color` / `--tab-selected-border-width` / `--tab-selected-border-color` / `--tab-selected-font-weight` / `--tab-selected-shadow` | 選んでいるタブの地・文字の色・枠の太さ・枠の色・太さ・影 | `--color-surface` / `--gray-900` / `--tab-border-width` / `--color-primary-hover-end #b7c2c7 --color-surface #b7c2c7` / `--tab-font-weight` / なし |
| `--tab-selected-hover-bg` / `--tab-selected-hover-color` / `--tab-selected-hover-border-color` | 選んでいるタブに乗せたとき | 選んでいるときと同じ |
| `--tab-alert-color` / `--tab-selected-alert-color` | 入力に誤りがあるタブの印の色 | `--color-error-light` / `--tab-alert-color` |
| `--tab-page-top` / `--tab-page-padding` / `--tab-page-bg` / `--tab-page-border` / `--tab-page-radius` / `--tab-page-shadow` | 本体（`.tab-page`）を上へずらす量・内側の余白（`.sectionBody` の中か外かに関係なく、この値）・地・枠・角・影 | `-4px` / `16px 16px 20px` / `--color-surface` / なし / `0` / なし |
| `--tab-page-border-top` | 本体の上の枠。下線の型は、タブ列の下線を上の辺にするので `none` にする | `--tab-page-border` |

既定値は RevoClassic の値。

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

## 素の要素

素の要素（`body`・`ul`・`li`・`hr`・`thead`・`button`・`img`・`optgroup`・`h2` など）の規則は、`common/theme-base.css`（主に `@layer evo-base`）が決める。本文の書体・文字の大きさ・行の高さ（`1.5`）、リストの余白、`button` の余白、`hr` の太さは全テーマ共通。行の高さは、入力欄とボタンの高さ（`--control-height`）の計算の元にもなる。テーマは `:root` で下の変数の値を決め、素の要素の規則を書かない（AromaHop・SakuraFlow の `h2` のような、そのテーマだけの見た目は除く）。

| 変数 | 意味 | 既定値（theme-base.css） |
| --- | --- | --- |
| `--page-bg` | 本文（`body`）の地。グラデーションも書ける | `--color-background-page` |
| `--body-line-height` | 本文の行の高さ。同梱テーマはすべて既定値 | `1.5` |
| `--hr-color` | 区切り線（`hr`）の色 | `--gray-700` |
| `--thead-color` / `--thead-bg` / `--thead-td-color` / `--thead-td-border-color` | 表の見出しの行の文字の色・地・セルの文字の色・セルの上の線の色 | `--color-thead-text` / `--color-thead-bg` / `--thead-color` / `--color-surface` |
| `--list-marker-image` | `li` の印の画像（`none` なら黒丸だけ） | `common/images/misc/li.gif` |
| `--optgroup-color` | 選択肢のグループの見出しの文字の色（地は付けない。選んでいる項目の灰色の地と見分けるため） | `--color-text-muted` |

## そのほかの部品

上のどの部品にも入らない小さな規則（状態表示・読み込み中の表示・検索の帯・設定の表・区切り・状態を色で示す文字・ダッシュボードのボタン）は `common/theme-base.css`、表（`.grid`）の角と影は `common/tables.css` が描く。大きさと余白は全テーマ共通で、テーマは `:root` で下の変数の値を決める。

| 変数 | 意味 | 既定値 |
| --- | --- | --- |
| `--grid-border-collapse` / `--grid-radius` / `--grid-shadow` | 表の枠の重ね方・角・影（角を丸めるときは `separate`） | `collapse` / `0` / なし |
| `--statusbar-text-color` | 上のメニューの右の状態表示（`#buildText`・`#workText`）の文字の色 | `#fff` |
| `--preloader-radius` / `--preloader-border-color` | 読み込み中の表示の角・枠の色 | `12px` / `--gray-500` |
| `--searchbar-bg` / `--searchbar-border-color` / `--searchbar-radius` | リソースの選択の検索の帯の地・枠の色・角 | `#dbdbdb` / `silver` / `0` |
| `--settings-th-color` / `--fieldset-tab-page-border-color` | 設定の表の見出しの列の文字の色・`fieldset.tab-page` の枠の色 | `#555` / `--gray-200` |
| `--split-display` | 編集画面の区切りの線を見せるか（見せないテーマは `none`） | `block` |
| `--okmsg-border-color` | 成功のメッセージ（`.okmsg`）の枠の色（地は `--color-success-bg`） | `--color-success-light` |
| `--overhead-warning-color` / `--access-private-color` / `--label-disabled-color` / `--resource-description-color` / `--tree-protected-color` / `--disabled-plugin-color` | 状態を色で示す文字の色 | `#990033` / `#821517` / `#ccc` / `#777` / `#77d` / `#aaa` |
| `--dashboard-link-color` / `--dashboard-link-hover-color` | ダッシュボードのボタンの名前の色・乗せたときの色 | `--gray-900` / `gray` |

## 新しいテーマを作るとき

1. `:root` に上の表の変数をすべて定義する
2. 共通の CSS（`common/forms.css`・`common/theme-base.css`・`common/sections.css`・`common/tabs.css`・`common/tables.css`・`common/action-buttons.css`・`common/context-menu.css`・`common/topmenu.css`・`common/tree.css`）にある規則は、テーマに書かない（値を変えたいときの上書きは除く）。共通の CSS はテーマより先に読まれる（`manager_theme_stylesheets()`）。`theme-base.css` の規則のうち、素の要素の規則（`h1` など）は土台の層、ほかは層の外にある。層の外の規則をテーマで上書きするときは、同じか高い詳細度で書く
3. 主ボタンの規則を、`--color-primary*` と `--color-text-inverse` だけで書く
4. `grep -nE "color-primary|color-text-inverse" style.css` で、定義と主ボタンでの参照がそろっているか確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`SoftSlate/style.css` など。
