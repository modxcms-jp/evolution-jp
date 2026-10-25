# テーマの縦の余白

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が、`#mainPane` の中で縦に積むブロックの上下の間をどう決めるかの約束。同梱テーマも外部のテーマも、これに従う。色の変数は [theme-variables.md](theme-variables.md) を参照する。

> 状態: 案（Issue #917）。決まりの表の値は 6 テーマの計測から出した案で、確認を経て確定する。確認中の点は「確認中の点」にまとめた。

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
| ブロックとブロックの間 | `--space-3`（12px） | `h1` → `.section`、`ul.actionButtons` → 次のブロック、`.sectionHeader` → `.sectionBody`（帯と本体を離して見せる作り） |
| タブとパネルを離して見せる作り（丸いタブ）の、タブ列とパネルの間 | `--space-2`（8px） | `.dynamic-tab-pane-control .tab-row` → `.tab-page` |
| 入れ物の最初の子の上・最後の子の下 | 外側の `margin` を足さない（0）。間は入れ物の内側の `padding` だけで作る | `.sectionBody`、`.tab-page`、`fieldset`、`form` の最初と最後の子 |
| 本文の流れの要素（リセット規則の対象） | 確認中（「確認中の点」の 1） | `p`、`ul`、`ol`、`h2`〜`h6` など。`ul.actionButtons` も `ul` として当たる |

### 書き方

- 間は、上のブロックの `margin-bottom` で作る。下のブロックの `margin-top` と重ねない（margin の相殺で、値が読めなくなるため）
- 位置を `top` でずらして間を作らない。`position: relative` と `z-index` は、重なりの順のために使ってよい
- 間を、ブロック自身の `padding-bottom` で作らない（`ul.actionButtons { padding-bottom: 10px }` など）。箱の端が間の分だけ広がり、決まりの値が読めなくなる
- 余白の px を直書きしない。`var(--space-N)` で書く。`margin: 0 0 12px 0` のような一括の書き方は、横の値を巻き込むので、縦だけ（`margin-bottom` など）を書く
- テーマが持つテンプレート（`welcome.tpl` など）には、`style` 属性で余白を書かない。`style.css` から揃えられなくなる
- 画面の HTML（`manager/actions/` の PHP やテンプレート）が `style` 属性で持つ余白は、テーマからは直せないので決まりの対象外にする。テーマの規則で打ち消さない。例: サブリソース一覧（a=120）のパンくずの下（`manager/actions/document/resources_list.static.php` の 552 行の `margin-bottom:10px`）と、移動ボタンを包む要素の上（同 147 行の `margin-top:10px`）

## 確認中の点

1. リセット規則の対象の扱い
   - 7 テーマすべてが、`h1, h2, h3, h4, h5, h6, p, pre, blockquote, ul, ol, dl, address { margin: .5em 0; }` というリセット規則を持つ。`ul.actionButtons` も `ul` としてこの規則に当たる。0.5em は「本文の文字の大きさ × 0.5」で、SakuraFlow・AromaHop は `body` が 13px
   - 案 A: リセット規則の対象はまとめて決まりの対象外にする。「入れ物の最初・最後の子は 0」と「`margin-bottom` だけで間を作る」は、ブロック要素（`.section`、`.sectionBody`、`.tab-pane` など）にだけ当てる
   - 案 B: リセット規則を `margin-bottom` だけ（`.5em`）にし、`:first-child` / `:last-child` で 0 にする。決まりが `p` などにも及ぶ
2. 枠のない文字だけの見出しから本体までの間
   - SakuraFlow・AromaHop の `.section` の中の見出し（枠も背景も無い `.sectionHeader`）から `.sectionBody` までの間は、今 0（SakuraFlow・AromaHop とも a=70 の 3 か所と a=100 で実測）。タブの中（`.tab-page .sectionHeader`）は `margin-bottom: 12px` で 12px
   - 文字だけの見出し → 本体を 0 のままにするか、`--space-3` にするかを確認する

## 例外

下の例外だけを、決まりと違う値のまま残す。テーマ名ではなく、作りの型で決める。

| 例外 | 条件（作り） | 同梱の例 | 理由 |
| --- | --- | --- | --- |
| つなげて見せる組は 0 | 上のブロックの枠や下線を下のブロックと共有して、1 つに見せる組。タブ列とパネル、見出しの帯と本体。つなげる方法は、(1) `.tab-page` の負の `top`、(2) タブ列の下線（`.tab-row` の `border-bottom`）をパネルの上辺にして、`.tab-page` の `border-top` を消す、(3) 帯と本体で枠を共有する、のいずれか | (1) RevoClassic（-4px）、RubberWhite（-2px）。(2) AuroraFlow、SoftSlate。(3) AuroraFlow・RevoClassic・RubberWhite・SoftSlate の `.sectionHeader` → `.sectionBody` | 間を空けると、つながって見える枠が切れるため |
| 負の `top` の分の広がり | 上の (1) の作り | RevoClassic、RubberWhite | `top` は描く位置だけをずらし、箱は元の場所に残る。タブの組の次のブロックまでの間は、`top` の絶対値の分だけ広く測れる。許容する |
| 狭い画面の `#actions` 用の余白 | 幅 1100px 以下で操作ボタン群 `#actions` を右下に移すとき、`#mainPane` の `padding-bottom` に 72px を足す | SoftSlate | 最後の項目がボタン群に隠れないようにするため |

## 対象外のテーマ

RevoStyle は、旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない。

RevoStyle の `--space-*` は、ほかのテーマと刻みが違う（`--space-0` 〜 `--space-14`。`--space-1` が 2px、`--space-2` が 3px、`--space-8` が 10px）。共通部は `--space-2` を 8px のつもりで読むので、RevoStyle の `#mainPane` の内側の余白は 3px で表示される。この値は今のまま変えない。

## 新しいテーマを作るとき

1. `:root` に `--space-1` 〜 `--space-8` を、上の表の値で定義する
2. ブロックの間を、決まりの表の段の `margin-bottom` で書く。入れ物の最初と最後の子には外側の `margin` を足さない
3. `grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" style.css | grep -E "^[0-9]+-\s+top:"` で、`.tab-page` を `top` でずらしていないか確かめる（出てよいのは、例外の負の値か `0` だけ）
4. 管理画面の主な画面（ダッシュボード、サブリソース一覧、リソースの編集、システム設定、アクセス権限など）を開き、ページの端・見出し・ボタン列・表・タブとパネルの間が、決まりの表の値になっているか、ブラウザの開発者ツールで確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`manager/media/style/SoftSlate/style.css` など。
