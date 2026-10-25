# テーマの縦の余白

管理画面テーマ（`manager/media/style/<テーマ名>/style.css`）が、`#mainPane` の中で縦に積むブロックの上下の間をどう決めるかの約束。同梱テーマも外部のテーマも、これに従う。色の変数は [theme-variables.md](theme-variables.md) を参照する。

> 状態: 案（Issue #917）。段の数値は 6 テーマの計測から出した案で、確認を経て確定する。

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
| ブロックとブロックの間 | `--space-3`（12px） | `h1` → `.section`、`ul.actionButtons` → 次のブロック、`.sectionHeader` → `.sectionBody`（帯と本体を離して見せるテーマ） |
| 丸いタブのタブとパネルの間 | `--space-2`（8px） | `.dynamic-tab-pane-control .tab-row` → `.tab-page`（SakuraFlow・AromaHop） |
| 入れ物の最初の子の上・最後の子の下 | 外側の `margin` を足さない（0）。間は入れ物の内側の `padding` だけで作る | `.sectionBody`、`.tab-page`、`fieldset`、`form` の最初と最後の子 |
| 本文の段落 `p` | 本文のリズムとして 0.5em（12px の本文で 6px）。ブロックの間の決まりの対象外 | `p` → 表・ボタン列・タブの組 |

### 書き方

- 間は、上のブロックの `margin-bottom` で作る。下のブロックの `margin-top` と重ねない（margin の相殺で、値が読めなくなるため）
- 入れ物の最後の子の `margin-bottom` は 0 にする（`:last-child`）。タブの組などの下に、入れ物の `padding` に加えて外側の `margin` が積まれないようにする
- 位置を `top` でずらして間を作らない。`position: relative` と `z-index` は、重なりの順のために使ってよい
- 間を、ブロック自身の `padding-bottom` で作らない（`ul.actionButtons { padding-bottom: 10px }` など）。箱の端が間の分だけ広がり、決まりの値が読めなくなる
- 余白の px を直書きしない。`var(--space-N)` で書く。`margin: 0 0 12px 0` のような一括の書き方は、横の値を巻き込むので、縦だけを書く（`margin-block-end` など）
- 画面の HTML が `style` 属性で持つ余白（例: サブリソース一覧のパンくずとページャの 10px）は、テーマからは直せないので決まりの対象外にする。テーマの規則で打ち消さない

## 例外

下の例外だけを、決まりと違う値のまま残す。

| 例外 | テーマ | 理由 |
| --- | --- | --- |
| フォルダ型のタブとパネルの枠をつなげる負の `top`（`.dynamic-tab-pane-control .tab-page`） | RevoClassic（-4px）、RubberWhite（-2px） | タブの下端とパネルの上端の枠を重ねて「つなげた組」にするため |
| タブの枠をパネルに重ねる負の `margin-bottom`（タブ → パネルの間 0） | AuroraFlow、SoftSlate | 同上。タブがパネルの上に載って見えるデザイン |
| 枠でつながる組の間 0（`.sectionHeader` → `.sectionBody`） | AuroraFlow、RevoClassic、RubberWhite、SoftSlate | 見出しの帯と本体を、1 つの枠として見せるため |
| 幅 1100px 以下の `#mainPane` の `padding-bottom: 72px` | SoftSlate | 操作ボタン群 `#actions` が右下に移るので、最後の項目が隠れないようにするため |

## 対象外のテーマ

RevoStyle は、旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない。

RevoStyle の `--space-*` は、ほかのテーマと刻みが違う（`--space-0` 〜 `--space-14`。`--space-1` が 2px、`--space-2` が 3px、`--space-8` が 10px）。共通部は `--space-2` を 8px のつもりで読むので、RevoStyle の `#mainPane` の内側の余白は 3px で表示される。この値は今のまま変えない。

## 新しいテーマを作るとき

1. `:root` に `--space-1` 〜 `--space-8` を、上の表の値で定義する
2. ブロックの間を、決まりの表の段の `margin-bottom` で書く。入れ物の最初と最後の子には外側の `margin` を足さない
3. `grep -nE "^\s+top:" style.css` で、`.tab-page` を `top` でずらしていないか確かめる（例外のフォルダ型を除く）
4. 管理画面の主な画面（ダッシュボード、サブリソース一覧、リソースの編集、システム設定、アクセス権限など）を開き、ページの端・見出し・ボタン列・表・タブとパネルの間が、決まりの表の値になっているか、ブラウザの開発者ツールで確かめる

同梱テーマの例は `manager/media/style/RevoClassic/style.css`、`manager/media/style/SoftSlate/style.css` など。
