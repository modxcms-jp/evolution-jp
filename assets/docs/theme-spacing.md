# テーマの縦の余白

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が、`#mainPane` の中で縦に積むブロックの上下の間をどう決めるかの約束。同梱テーマも外部のテーマも、これに従う。色の変数は [theme-variables.md](theme-variables.md) を参照する。

対象は、ページの見出し（`h1`）・セクション（`.section`、見出しの帯 `.sectionHeader`、本体 `.sectionBody`）・操作ボタン列（`ul.actionButtons`）・一覧の表・タブの組（`.tab-pane`）など、`#mainPane` の中で縦に並ぶ「ブロック」の縦の間。

対象外:

- ツリー・上のメニュー・ログイン画面・右クリックメニュー・モーダル（親の選択の `parent_picker` など）・ファイルブラウザ
- スクロールする箱やリストの項目の間（システムログの項目 `.system-log-entry`、並べ替えリスト `ul.sortableList li`）。ブロックの間ではなく部品の内側の余白で、画面や部品の規則が決める
- `window.close()` で閉じる別窓の画面（リソースの選択 a=84、`resource_selector`）。縦に積むブロックが `#mainPane` に無い
- PHP の `phpinfo()` の出力（a=200、`.phpinfoReport` の中）。HTML は PHP が決め、ブロックの間を決まりで揃えられない
- 部品の内側（ボタン・入力欄・表のセル）の余白
- 横の余白
- ブロックの内側の余白（`.sectionBody` や `.tab-page` の `padding`）の大きさ。これはテーマのデザインとして自由に決めてよい

## 段の約束

RevoStyle を除くすべてのテーマが、`:root` で `--space-1` 〜 `--space-8` を 4px 刻み（`--space-N` = 4px × N）で定義する。

| 変数 | 値 |
| --- | --- |
| `--space-1` | 4px |
| `--space-2` | 8px |
| `--space-3` | 12px |
| `--space-4` | 16px |
| `--space-5` | 20px |
| `--space-6` | 24px |
| `--space-7` | 28px |
| `--space-8` | 32px |

共通部（`manager/media/style/common/shell.css`）は、`--space-2`・`--space-3`・`--space-4` を既定値付き（`var(--space-2, 8px)`）で読む。`#mainPane` の内側の余白は `var(--shell-main-padding, var(--space-2, 8px))` で、どのテーマも `--shell-main-padding` を定義しない。段の名前と値が、テーマと共通部の約束になる。

## 決まりの表

「縦の間」は、縦に並んだ 2 つのブロックのうち、上のブロックの下端（枠を含む）から下のブロックの上端（枠を含む）までの距離。

| 間の種類 | 段 | 例（セレクタ） |
| --- | --- | --- |
| ページの端と最初のブロック | 共通部の `#mainPane` の `padding`（`--space-2`）だけ。テーマは足さない（最初のブロックの上の `margin` は 0） | `#mainPane > h1`、`#mainPane > form`、ダッシュボードの外枠 |
| ページの端と最後のブロック | `#mainPane` の最後の子の下は 0（共通部の `padding` だけ） | `#mainPane > :last-child` |
| ブロックとブロックの間 | `--space-3`（12px） | `h1` → `.section`、`ul.actionButtons` → 次のブロック、`.section` → `.section`、ボタン列の前（`:is(.sectionBody, .tab-page, form) > :has(+ :is(ul, div).actionButtons)`）、コード欄の前（`:is(.sectionBody, .tab-page, form) > :has(+ .code-editor)`）、ページ送りの前（`:is(.sectionBody, .tab-page, form) > :has(+ .pagination)`）、`.tab-page .sectionHeader` → `.sectionBody`（タブの中の見出し）、表など → 次の `.sectionHeader`（タブの中）、システムログ（a=114）の検索フォーム（`.system-log-toolbar`）と概要（`.system-log-summary`）の下、`.sectionBody` の直下のブロック（`.sectionBody > :is(div, table, fieldset)`）の下、本体の中の小見出し（`.sectionBody h3`）の下、サブリソース一覧の上の 2 行（`.resources-list-head`・`.resources-list-toolbar`）の下と、表と下のページ送り（`.resources-list-pager-bottom`）の間（`#mutate > :has(+ .resources-list-pager-bottom)`）。この 3 つは `common/shell.css` の規則で、テーマには書かない。パンくずの `.resources-topic-path` の下の余白は、この画面では `common/shell.css` が行の中で 0 にするので効かない、移動ボタン（`.resources-batch-actions`）の上（直前のブロックの `margin-bottom` で作る） |
| タブとパネルを離して見せる作り（丸いタブ）の、タブ列とパネルの間 | `--space-2`（8px） | `.dynamic-tab-pane-control .tab-row` → `.tab-page` |
| 設定画面（`EvoSettingsForm`）のタブの中で、表と次の小見出しの間 | `--space-5`（20px） | `.tab-page > :has(+ .settings-section)`（直前の表の `margin-bottom`） |
| 入れ物の最初の子の上・最後の子の下 | 外側の `margin` を足さない（0）。間は入れ物の内側の `padding` だけで作る。入れ物は `#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form` | `#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form` の最初と最後の子 |
| 本文の流れの要素（リセット規則の対象）の間 | 本文の文字の大きさ × 0.5（`margin-bottom` だけ）。入れ物の最初の子の上・最後の子の下は 0（`.sectionBody h3` はブロックの間に従う） | `p`、`ul`、`ol`、`h2`〜`h6`、`pre`、`blockquote`、`dl`、`address` |

### 書き方

- `.sectionBody` の直下のブロックの間は、`.sectionBody > :is(div, table, fieldset)` の `margin-bottom` で作る。`form` は含めない。末尾の `<style>` の前では `:last-child` に当たらず、`form` の下の `margin` が 0 にならないため
- 間は、上のブロックの `margin-bottom` で作る。下のブロックの `margin-top` と重ねない（margin の相殺で、値が読めなくなるため）。`margin-top` で作ってよいのは次の 1 つだけ
  - `.tab-pane > ul.actionButtons`（ログ a=13 のように、タブの組の直後のボタン列）。最後のタブのパネルが非表示のことがあり、前の兄弟の `margin-bottom`（`:has(+ ul)`）が当たらないため。6 テーマとも `.tab-page` に `margin-bottom` が無く、`.tab-pane` は下の `:is(...)` の親に入っていないので、前のブロックの `margin-bottom` と相殺しない
- 入れ物（`#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form`）の最初の子の上と最後の子の下は 0 にする。入れ物の `padding` に加えて、外側の `margin` が積まれないようにするため。ブロックとリセット規則の対象は、上の例外を除いて `margin-top` を持たないので、最初の子の上は 0 になる。最後の子の下は、`:is(.section, .sectionBody, .tab-page, fieldset, form) > :last-child { margin-bottom: 0 }` と `#mainPane > :last-child { margin-bottom: 0 }` で 0 にする
- 下のブロックの前の間は、前の兄弟の `margin-bottom` を `:has(+ X)` で作ってよい。`:has` を使う規則は別の規則に分け、未対応のブラウザで一緒に捨てられないようにする。間に非表示の要素が挟まると当たらない（`:last-child` と同じ限界）。`<style>` や `<script>` も挟まると当たらないので、画面は `:has(+ X)` で間を作る組（前のブロックと X）の間に `<style>`・`<script>` を置かない（入れ物の最後にも置かない。`:last-child` の規則が当たらなくなる）（`bkmanager` の `<style>` は `<p>` と `<form>` の間にあるが、その組は `:has(+ X)` で間を作らないので影響しない）。`:has()` に対応しないブラウザでは、この規則で作る間（コード欄の前、ボタン列の前など）が 0 になる。許容する（表示は崩れず、ブロックが詰まるだけ）
- 画面ごとの規則の親は `:is(.sectionBody, .tab-page, form)` にする（`:is(.sectionBody, .tab-page, form) > :has(+ :is(ul, div).actionButtons)`、`:is(.sectionBody, .tab-page, form) > :has(+ .code-editor)`、`:is(.sectionBody, .tab-page, form) > :has(+ .pagination)`）。`#mainPane` の直下は親に入れない。直下のボタン列は `div#actions` の中にあり、前のブロック（`h1`・`.section`）は自分の `margin-bottom` で間を持っているため（例: `manager/actions/report/site_schedule.static.php` は `#mainPane` の直下に `h1` → `div#actions` → `.section` を並べる）。`form` の直下に `div#actions` を置く画面（例: `mutate_module_resources.dynamic.php`）も、`div#actions` に `.actionButtons` が付いていないので `:has(+ :is(ul, div).actionButtons)` に当たらず、問題は出ない。表のセルの中、`.section`・`fieldset` の直下には対象のボタン列やコード欄が無いため、これらも親に入れない
- `.section` は入れ物であり、ブロックでもある。中の最後の子の下は 0 にし、`.section` 自身の下は次のブロックとの間（`--space-3`）にする。`.section` どうしの間は `.section` の `margin-bottom` で作り、本体（`.section .sectionBody`）の `margin-bottom` は 0 にする
- 最後の子を 0 にする規則の詳細度は (0,2,0)（`#mainPane` 側は (1,1,0)）。これより詳細度が高い規則（id セレクタを使う規則など）と、後ろに書いた同じ詳細度の規則の `margin-bottom` は 0 にならない（後ろにあっても詳細度が低い `.sectionBody > :is(div, table, fieldset)` の (0,1,1) は 0 になる）ので、そのブロックで別に 0 にする。限界として、後ろに `display: none` の兄弟が続くときは、非表示の要素が最後の子になるので、見えている最後のブロックの `margin-bottom` は残る（CSS には「見えている最後の子」を選ぶ方法がない）。画面の HTML は、非表示の要素を入れ物の最後に置かないようにする
- リセット規則（`h1, h2, h3, h4, h5, h6, p, pre, blockquote, ul, ol, dl, address`。7 テーマすべてが持つ）は、`margin-bottom` だけを本文の文字の大きさ × 0.5 にする。`margin-top` は持たない
- ページの見出し（`#mainPane > h1`）と操作ボタン列（`ul.actionButtons`）は、リセット規則の対象でも、ブロックとして扱う。決まりの段（`--space-3` など）で上書きする
- 位置を `top` でずらして間を作らない。`position: relative` と `z-index` は、重なりの順のために使ってよい
- 間を、ブロック自身の `padding-bottom` で作らない（`ul.actionButtons { padding-bottom: 10px }` など）。箱の端が間の分だけ広がり、決まりの値が読めなくなる
- 余白の px を直書きしない。`var(--space-N)` で書く。`margin` を一括で書くときは、横の値を元のまま保つ。縦だけを変えるなら `margin-bottom` などを使う
- テーマが持つテンプレート（`welcome.tpl` など）と、画面の HTML（`manager/actions/` の PHP やテンプレート）には、`style` 属性や埋め込みの `<style>` で、ブロックの間の余白を書かない。`style.css` から揃えられなくなる
- 画面が埋め込む `<style>` は、画面の部品の class か ID に範囲を絞る。`h3 { … }` のように要素だけで書かない。シェル化で管理画面は 1 つの文書になる。`shell.js`（`manager/media/script/shell.js`、`hoistStylesheets`）が `<head>` へ持ち上げるのは `link[rel=stylesheet]` だけで、埋め込みの `<style>` は画面を切り替えると消えるが、表示中は同じ文書のツリー・メニュー・ヘッダーにも効くため
- 画面とテーマの約束として、次の class をテーマが受ける。画面は余白を書かず、class だけを持つ
  - `.dynamic-tab-pane-control .tab-row`: タブ列。テーマは `display: flex; flex-wrap: wrap` にして、タブ（`.tab`）を折り返せる作りにする（タブが増えたり名前が長かったりして幅を超えても、次の行に回るだけで、段違いにも重なりにもならない）。flex の子には `float` が効かないので、タブに `float` を書かない。タブ列を `display: flow-root` で包んでいたテーマ（下線や `margin-bottom` を持つ型）も、`flow-root` を `flex` に置き換える。RevoStyle は凍結のため対象外（タブは `float: left` のまま。タブが折り返すと段違いになることがあるが、変えない）。タブの左右の余白は、グローバル設定の 9 タブが 1 段に収まるよう、6 テーマとも 1 段階詰めてある（`--space-4` だった AuroraFlow・RevoClassic は `--space-3`、`--space-5` だった AromaHop・RubberWhite・SakuraFlow は `--space-4`、16px だった SoftSlate は 12px）。リソースの編集画面などほかの画面のタブも同じ余白になる
  - `.settings-section`: 設定画面（`EvoSettingsForm`）のタブの中の小見出し（`h3`）。規則は共通部（`shell.css`）に 1 つだけあり、色は `--color-text-primary`・`--color-border-light`、余白は `--space-1`・`--space-3`・`--space-5` を既定値付きで読む。テーマは規則を足さない（RevoStyle は既定値のまま）。直前の表との間は、表の `margin-bottom`（`.tab-page > :has(+ .settings-section)`）で作る
  - `.code-editor`: コード欄（帯とテキスト欄）の入れ物。テンプレート・スニペット・チャンク・モジュール・プラグインの編集画面。前のブロックの下を `:has(+ .code-editor)` で作る
  - `.code-editor-header`: コード欄の上の帯。内側の余白（`padding`）はテーマが決める。画面は float を使わず class だけを付ける。テーマは帯を `display: flex; flex-wrap: wrap; justify-content: space-between` にして、左の `.code-editor-title`（太字）と右の `.code-editor-options` を並べる（狭い幅では右側が次の行に折り返る）。縦の揃えは既定（stretch）のまま、`.code-editor-title`・`.code-editor-options` に背景や枠を付けない。`overflow: hidden` は要らない。色（右側の文字を含む）と枠は [theme-variables.md](theme-variables.md) の「書き方」に従う

## 例外

下の例外だけを、決まりと違う値のまま残す。テーマ名ではなく、作りの型で決める。

| 例外 | 条件（作り） | 同梱の例 | 理由 |
| --- | --- | --- | --- |
| つなげて見せる組は 0 | 上のブロックの枠や下線を下のブロックと共有して、1 つに見せる組。タブ列とパネル、見出しの帯と本体。つなげる方法は、(1) `.tab-page` の負の `top`、(2) タブ列の下線（`.tab-row` の `border-bottom`）をパネルの上辺にして、`.tab-page` の `border-top` を消す、(3) 帯と本体で枠を共有する、のいずれか | (1) RevoClassic（-4px）、RubberWhite（-2px）。(2) AuroraFlow、SoftSlate。(3) AuroraFlow・RevoClassic・RubberWhite・SoftSlate の `.sectionHeader` → `.sectionBody` | 間を空けると、つながって見える枠が切れるため |
| `.section` の中の、枠の無い文字だけの見出しから本体までは 0 | `.section` の直下の `.sectionHeader` が枠も背景も持たず、文字だけで本体とひと続きに見える作り（見出しと本体を 1 つの組として見せる）。本体の `padding` が文字との間を作る | SakuraFlow、AromaHop の `.section > .sectionHeader` → `.sectionBody` | 見出しと本体の間を、本体の `padding` に任せるため。タブの中の見出し（`.tab-page .sectionHeader`）はこの例外ではなく、ブロックの間（`--space-3`）にする |
| 負の `top` の分の広がり | 上の (1) の作り | RevoClassic、RubberWhite | `top` は描く位置だけをずらし、箱は元の場所に残る。タブの組の次のブロックまでの間は、`top` の絶対値の分だけ広く測れる。許容する |

## 対象外のテーマ

RevoStyle は、旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない。

RevoStyle の `--space-*` は、ほかのテーマと刻みが違う（`--space-0` 〜 `--space-14`。`--space-1` が 2px、`--space-2` が 3px、`--space-8` が 10px）。共通部は `--space-2` を 8px のつもりで読むので、RevoStyle の `#mainPane` の内側の余白は 3px で表示される。この値は今のまま変えない。

画面の PHP から直書きの余白を消した分（`.resources-batch-actions` の上・`.system-log-toolbar` と `.system-log-summary` の下の 10px）は、RevoStyle の `style.css` の末尾に、同じ値（px）で保つ規則を足してある。見た目を変えないための規則で、手本にしない。RevoStyle の `.resources-topic-path { margin-bottom: 10px; }` も同じ経緯で残っているが、サブリソース一覧では `common/shell.css` の `.resources-list-head` の規則が 0 にする（テーマのファイルは直さない）。

#924 で足した分も、同じ値で保つ。システム情報（本体の `padding: 10px 20px`、表の下 20px）、バックアップ（ボタン列の上下 10px）、インポート（`#importSite` の `margin: 8px`）、ログ（ボタン列の上 12px）、エクスポート（`#exportNotice`・`#exportResult dl`）、コード欄の帯の上 5px、テンプレート名の下 10px、TV の一覧の下のボタン列の上 15px、下書きの公開（下 12px）。これらも手本にしない。

#973 で画面の直書きを消したコード欄の帯（`.code-editor-header`）も、`padding`・色・枠を同じ値で保つ。#1004 で足した中の文字（`.code-editor-title` の太字、`.code-editor-options` の `#707070`。帯の背景に対して 4.5:1 に届かないが、凍結のため変えない）も同じ。これも手本にしない。

## 新しいテーマを作るとき

1. `:root` に `--space-1` 〜 `--space-8` を、上の表の値で定義する
2. ブロックの間を、決まりの表の段の `margin-bottom` で書く。入れ物の最初と最後の子には外側の `margin` を足さない。同梱テーマの `/* 縦の余白の決まり */` の規則の並び（最後の子の規則、`.tab-page > :has(+ .sectionHeader)`、`.pagination`、`.resources-topic-path`、`.system-log-toolbar`・`.system-log-summary`、`form > :has(+ .resources-batch-actions)`・`:is(.sectionBody, .tab-page, form) > :has(+ .pagination)`、`.sectionBody > :is(div, table, fieldset)`、`:is(.sectionBody, .tab-page, form) > :has(+ :is(ul, div).actionButtons)`、`:is(.sectionBody, .tab-page, form) > :has(+ .code-editor)`、`.tab-pane > ul.actionButtons`。ほかに、決まりの表の例のセレクタ）を写す。`.code-editor` は画面が付ける class なので、外部のテーマも規則を持たないと、その間が 0 になる。コード欄の上の帯 `.code-editor-header` の規則（`display: flex`・`flex-wrap: wrap`・`justify-content: space-between`・`padding`・背景・枠）と、中の `.code-editor-title`・`.code-editor-options` の規則も写す。無いと帯の飾りが消え、左右の文字が並ばない
3. `grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" style.css | grep -E "^[0-9]+-\s+top:"` で、`.tab-page` を `top` でずらしていないか確かめる（出てよいのは、例外の負の値か `0` だけ）
4. 管理画面の主な画面（ダッシュボード、サブリソース一覧、リソースの編集、システム設定、アクセス権限など）を開き、ページの端・見出し・ボタン列・表・タブとパネルの間が、決まりの表の値になっているか、ブラウザの開発者ツールで確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`manager/media/style/SoftSlate/style.css` など。
