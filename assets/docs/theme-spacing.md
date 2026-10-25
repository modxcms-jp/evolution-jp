# テーマの縦の余白

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が、`#mainPane` の中で縦に積むブロックの上下の間をどう決めるかの約束。同梱テーマも外部のテーマも、これに従う。色の変数は [theme-variables.md](theme-variables.md) を参照する。

対象は、ページの見出し（`h1`）・セクション（`.section`、見出しの帯 `.sectionHeader`、本体 `.sectionBody`）・操作ボタン列（`ul.actionButtons`）・一覧の表・タブの組（`.tab-pane`）など、`#mainPane` の中で縦に並ぶ「ブロック」の縦の間。

対象外:

- ツリー・上のメニュー・ログイン画面・右クリックメニュー・モーダル・ファイルブラウザ
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
| ブロックとブロックの間 | `--space-3`（12px） | `h1` → `.section`、`ul.actionButtons` → 次のブロック、`.section` → `.section`、`.tab-page .sectionHeader` → `.sectionBody`（タブの中の見出し）、表など → 次の `.sectionHeader`（タブの中）、システムログ（a=114）の検索フォーム（`.system-log-toolbar`）と概要（`.system-log-summary`）の下、`.sectionBody` の直下のブロック（SoftSlate・RubberWhite の `.sectionBody > :is(div, table, fieldset)`）の下、サブリソース一覧のパンくず（`.resources-topic-path`）の下、移動ボタン（`.resources-batch-actions`）の上（直前のブロックの `margin-bottom` で作る） |
| タブとパネルを離して見せる作り（丸いタブ）の、タブ列とパネルの間 | `--space-2`（8px） | `.dynamic-tab-pane-control .tab-row` → `.tab-page` |
| 入れ物の最初の子の上・最後の子の下 | 外側の `margin` を足さない（0）。間は入れ物の内側の `padding` だけで作る。入れ物は `#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form` | `#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form` の最初と最後の子 |
| 本文の流れの要素（リセット規則の対象）の間 | 本文の文字の大きさ × 0.5（`margin-bottom` だけ）。入れ物の最初の子の上・最後の子の下は 0 | `p`、`ul`、`ol`、`h2`〜`h6`、`pre`、`blockquote`、`dl`、`address` |

### 書き方

- 間は、上のブロックの `margin-bottom` で作る。下のブロックの `margin-top` と重ねない（margin の相殺で、値が読めなくなるため）
- 入れ物（`#mainPane`、`.section`、`.sectionBody`、`.tab-page`、`fieldset`、`form`）の最初の子の上と最後の子の下は 0 にする。入れ物の `padding` に加えて、外側の `margin` が積まれないようにするため。ブロックとリセット規則の対象は `margin-top` を持たないので、最初の子の上は 0 になる。最後の子の下は、`:is(.section, .sectionBody, .tab-page, fieldset, form) > :last-child { margin-bottom: 0 }` と `#mainPane > :last-child { margin-bottom: 0 }` で 0 にする
- 下のブロックの前の間は、前の兄弟の `margin-bottom` を `:has(+ X)` で作ってよい。`:has` を使う規則は別の規則に分け、未対応のブラウザで一緒に捨てられないようにする。間に非表示の要素が挟まると当たらない（`:last-child` と同じ限界）
- `.section` は入れ物であり、ブロックでもある。中の最後の子の下は 0 にし、`.section` 自身の下は次のブロックとの間（`--space-3`）にする。`.section` どうしの間は `.section` の `margin-bottom` で作り、本体（`.section .sectionBody`）の `margin-bottom` は 0 にする
- 最後の子を 0 にする規則の詳細度は (0,2,0)（`#mainPane` 側は (1,1,0)）。これより詳細度が高い規則（`#pagination` など）と、後ろに書いた規則の `margin-bottom` は 0 にならないので、そのブロックで別に 0 にする。限界として、後ろに `display: none` の兄弟が続くときは、非表示の要素が最後の子になるので、見えている最後のブロックの `margin-bottom` は残る（CSS には「見えている最後の子」を選ぶ方法がない）。画面の HTML は、非表示の要素を入れ物の最後に置かないようにする
- リセット規則（`h1, h2, h3, h4, h5, h6, p, pre, blockquote, ul, ol, dl, address`。7 テーマすべてが持つ）は、`margin-bottom` だけを本文の文字の大きさ × 0.5 にする。`margin-top` は持たない
- ページの見出し（`#mainPane > h1`）と操作ボタン列（`ul.actionButtons`）は、リセット規則の対象でも、ブロックとして扱う。決まりの段（`--space-3` など）で上書きする
- 位置を `top` でずらして間を作らない。`position: relative` と `z-index` は、重なりの順のために使ってよい
- 間を、ブロック自身の `padding-bottom` で作らない（`ul.actionButtons { padding-bottom: 10px }` など）。箱の端が間の分だけ広がり、決まりの値が読めなくなる
- 余白の px を直書きしない。`var(--space-N)` で書く。`margin: 0 0 12px 0` のような一括の書き方は、横の値を巻き込むので、縦だけ（`margin-bottom` など）を書く
- テーマが持つテンプレート（`welcome.tpl` など）と、画面の HTML（`manager/actions/` の PHP やテンプレート）には、`style` 属性や埋め込みの `<style>` で、ブロックの間の余白を書かない。`style.css` から揃えられなくなる

## 例外

下の例外だけを、決まりと違う値のまま残す。テーマ名ではなく、作りの型で決める。

| 例外 | 条件（作り） | 同梱の例 | 理由 |
| --- | --- | --- | --- |
| つなげて見せる組は 0 | 上のブロックの枠や下線を下のブロックと共有して、1 つに見せる組。タブ列とパネル、見出しの帯と本体。つなげる方法は、(1) `.tab-page` の負の `top`、(2) タブ列の下線（`.tab-row` の `border-bottom`）をパネルの上辺にして、`.tab-page` の `border-top` を消す、(3) 帯と本体で枠を共有する、のいずれか | (1) RevoClassic（-4px）、RubberWhite（-2px）。(2) AuroraFlow、SoftSlate。(3) AuroraFlow・RevoClassic・RubberWhite・SoftSlate の `.sectionHeader` → `.sectionBody` | 間を空けると、つながって見える枠が切れるため |
| `.section` の中の、枠の無い文字だけの見出しから本体までは 0 | `.section` の直下の `.sectionHeader` が枠も背景も持たず、文字だけで本体とひと続きに見える作り（見出しと本体を 1 つの組として見せる）。本体の `padding` が文字との間を作る | SakuraFlow、AromaHop の `.section > .sectionHeader` → `.sectionBody` | 見出しと本体の間を、本体の `padding` に任せるため。タブの中の見出し（`.tab-page .sectionHeader`）はこの例外ではなく、ブロックの間（`--space-3`）にする |
| 負の `top` の分の広がり | 上の (1) の作り | RevoClassic、RubberWhite | `top` は描く位置だけをずらし、箱は元の場所に残る。タブの組の次のブロックまでの間は、`top` の絶対値の分だけ広く測れる。許容する |
| 狭い画面の `#actions` 用の余白 | 幅 1100px 以下で操作ボタン群 `#actions` を右下に移すとき、`#mainPane` の `padding-bottom` に 72px を足す | SoftSlate | 最後の項目がボタン群に隠れないようにするため |

## 対象外のテーマ

RevoStyle は、旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない。

RevoStyle の `--space-*` は、ほかのテーマと刻みが違う（`--space-0` 〜 `--space-14`。`--space-1` が 2px、`--space-2` が 3px、`--space-8` が 10px）。共通部は `--space-2` を 8px のつもりで読むので、RevoStyle の `#mainPane` の内側の余白は 3px で表示される。この値は今のまま変えない。

画面の PHP から直書きの余白を消した分（`.resources-topic-path` の下・`.resources-batch-actions` の上・`.system-log-toolbar` と `.system-log-summary` の下の 10px）は、RevoStyle の `style.css` の末尾に、同じ値（px）で保つ規則を足してある。見た目を変えないための規則で、手本にしない。

## 新しいテーマを作るとき

1. `:root` に `--space-1` 〜 `--space-8` を、上の表の値で定義する
2. ブロックの間を、決まりの表の段の `margin-bottom` で書く。入れ物の最初と最後の子には外側の `margin` を足さない
3. `grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" style.css | grep -E "^[0-9]+-\s+top:"` で、`.tab-page` を `top` でずらしていないか確かめる（出てよいのは、例外の負の値か `0` だけ）
4. 管理画面の主な画面（ダッシュボード、サブリソース一覧、リソースの編集、システム設定、アクセス権限など）を開き、ページの端・見出し・ボタン列・表・タブとパネルの間が、決まりの表の値になっているか、ブラウザの開発者ツールで確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`manager/media/style/SoftSlate/style.css` など。
