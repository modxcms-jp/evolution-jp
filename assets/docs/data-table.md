# 表の部品（DataTable）

「行の配列」と「列の定義」から、ページ送り付きの HTML の表を作る部品の決まり。エスケープが既定で有効で、`style` 属性やインラインの `onclick` を出さない。

## 置き場所と使い方

`manager/includes/controls/data_table.class.php` に 3 つのクラスがある。1 つのファイルを include すれば使える（管理画面・サイト側のどちらからでも）。

    include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

| クラス | 役割 |
| --- | --- |
| `DataTable` | 行の配列と列の定義から表を描く |
| `DataTablePager` | 件数からページ数・`offset()`・`limit()` を計算し、ページ送りを描く |
| `DataTableLinks` | リンクの URL と、今の状態（ページ・並べ替え）を持つ。URL は `url()` の 1 か所で作る |

描く処理（`DataTable::render()`・`DataTablePager::render()`）は、引数だけから HTML を作り、`evo()`・`db()`・リクエスト・グローバル変数に触れない。リクエストのページ番号と並べ替えは `DataTableLinks::fromRequest()` が型つきヘルパー（`get_intvar()`・`get_choicevar()`。`request-input.md`）で読む。

ページ送りは、件数を数える → `DataTablePager` → 本体の SQL に `LIMIT` を付けて、そのページの行だけを取る、の順で行う。全件を取ってから切り出さない。`perPage` が 0 以下のときは全件を 1 ページにし、`limit()` は 0 を返すので、SQL に `LIMIT` を付けない。

    $links = DataTableLinks::fromRequest('index.php', ['a' => 120], ['id', 'pagetitle']);
    $pager = new DataTablePager($total, $perPage, $links->page());
    // ... LIMIT に sprintf('%d,%d', $pager->offset(), $pager->limit()) を使って $rows を取る
    $table = new DataTable($columns, ['id' => 'children', 'rowClasses' => ['gridItem', 'gridAltItem']]);
    $nav = $pager->render($links, ['label' => lang('pagination_label')]);
    echo $nav . $table->render($rows, $links) . $nav;

`manager/includes/paginate.inc.php` の `Paging` と `manager/media/script/tablesort.js` は新しく使わない（後続の候補は `core-issues.md`）。

## 列の定義

`DataTable::__construct(array $columns, array $options = [])` の `$columns` の各要素（連想配列）。

| キー | 型 | 内容 |
| --- | --- | --- |
| `key` | string | 行の配列のキー（必須） |
| `label` | string | 見出し。既定はエスケープ |
| `labelHtml` | bool | 見出しを HTML のまま出す（既定 false） |
| `format` | callable | `function ($value, array $row): string`。戻り値をセルの値にする |
| `html` | bool | セルを HTML のまま出す（既定 false） |
| `class` | string | `th` と `td` に付ける class（`is-shrink`・`is-center` など） |
| `sort` | string | 並べ替えのキー（URL の `orderby` の値）。無ければ並べ替えない |

## options

| キー | 既定 | 内容 |
| --- | --- | --- |
| `id` | `''` | 表を包む `div` の `data-table` の値 |
| `tableClass` | `grid` | `table` の class |
| `rowClasses` | `[]` | 行（`tr`）に順に付ける class |
| `headerClass` | `''` | `th` に足す class |
| `caption` / `captionHtml` | なし / false | `caption`。`captionHtml` が真なら HTML のまま |
| `emptyMessage` | `''` | 0 件のときの文言（エスケープ） |
| `showHeader` | true | `thead` を出すか |
| `rowAttributes` | なし | `function (array $row): array`。属性名 => 値（値はエスケープ。`style`・`width`・`align`・`bgcolor`・`on*` は無視。`class` を返したときは `rowClasses` の値と 1 つの `class` 属性にまとめる） |

`DataTable::render(array $rows, ?DataTableLinks $links = null)` に `DataTableLinks` を渡すと、`sort` のある列の見出しが並べ替えのリンクになり、今の列に `aria-sort` が付く。並べ替えのリンクではページが 1 に戻る。

## エスケープ

- 既定ではセルも見出しもエスケープする（`hsc()`）。値が null・配列・オブジェクトなら空文字。
- `html => true`（`labelHtml => true`）の列は、`format` の戻り値をそのまま出す。中に入れる DB の値などのエスケープは、`format` を書く側が行う。
- 属性の値（class・`rowAttributes`）は常にエスケープする。
- `style`・`width`・`align`・`bgcolor`・`on*` の属性は出さない。見た目はテーマの class に任せる。

## URL の引数

| 引数 | 内容 |
| --- | --- |
| `page` | ページ番号。範囲外（0・総ページ数より大きい）は 1 ページ目 |
| `orderby` | 並べ替えのキー。`sort` のキーにあるものだけ受ける（ホワイトリスト）。呼び出し側が自分の SQL の列名に対応づける |
| `orderdir` | `asc` か `desc`。既定は `asc` |

ページ番号の引数名は `DataTableLinks` の `pageParam` で替えられる（1 画面に表が複数あるとき）。ページ送りは、今のページを中心に最大 9 個の番号と、前後の「先頭」「<」「>」「最後」を出す。行けないものは `<span>`。総ページ数が 2 未満なら何も出さない。

## テーマが持つ class

| class | 内容 |
| --- | --- |
| `.grid` | 表 |
| `.gridItem`・`.gridAltItem` | 行の色分け（`rowClasses` で交互に付ける） |
| `.pagination` | ページ送りの `nav`。今のページは `a[aria-current="page"]` |
| `.is-shrink` | 幅を中身に合わせる列 |
| `.is-center` | 中央寄せの列 |
| `.data-table-empty` | 0 件のときのセル |

並べ替えている列の見出しは `th[aria-sort="ascending|descending"]` になる。テーマは `.grid th[aria-sort] a::after` で向きの印（昇順 ▲・降順 ▼）を出す。印は飾りなので `content: "▲" / ""` の形で読み上げから外し（向きは `aria-sort` が伝える）、色は `--color-primary`、間は `--space-1` を使う。凍結テーマの RevoStyle は持たない。

外部のテーマはこの一覧を写す。

## JS の書き方

- インラインの `onclick` を出さない。Vanilla JS で書く。
- 表に共通の動き（見出しの「すべて選択」のチェックボックス `input[data-select-all="<name>"]`）は `manager/media/script/data-table.js` が `document` へのイベントの委譲で扱う。シェルは画面を開き直すたびにスクリプトを動かし直すので、`window.EvoDataTable` があれば登録しない。
- 画面ごとの動き（右クリックのメニューを開くなど）は、各画面のスクリプトが表を包む要素（`[data-table="<id>"]`）にリスナーを付ける。要素は画面の差し替えで消えるので、リスナーが積み重ならない。行のアイコンやタイトルには `data-row-menu="<id>"` を付け、リスナーは `e.target.closest('[data-row-menu]')` で受ける。

## 戻り先で状態を引き継ぐ

一覧の操作（公開・削除など）のあとで一覧へ戻すときは、今のページと並べ替えを引き継ぐ。引数の読み方（`orderby` の許可リストなど）を画面ごとに書かず、一覧ごとに 1 か所へまとめ、画面と processor の両方がそれを使う。例は子リソースの一覧の `manager/includes/controls/child_document_list_state.class.php`（`ChildDocumentListState`）。
