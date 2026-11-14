# 表の部品（DataTable）

「行の配列」と「列の定義」から、ページ送り付きの HTML の表を作る部品の決まり。エスケープが既定で有効で、`style` 属性やインラインの `onclick` を出さない。

## 置き場所と使い方

`manager/includes/controls/data_table.class.php` に 4 つのクラスがある。1 つのファイルを include すれば使える（管理画面・サイト側のどちらからでも）。

    include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

| クラス | 役割 |
| --- | --- |
| `DataTable` | 行の配列と列の定義から表を描く |
| `DataTablePager` | 件数からページ数・`offset()`・`limit()` を計算し、ページ送りを描く |
| `DataTableLinks` | リンクの URL と、今の状態（ページ・並べ替え）を持つ。URL は `url()` の 1 か所で作る |
| `DataTableSettings` | サイト側の表の入口。保存済みの設定の文字列から表を作る（下の「サイト側の表」） |

描く処理（`DataTable::render()`・`DataTablePager::render()`）は、引数だけから HTML を作り、`evo()`・`db()`・リクエスト・グローバル変数に触れない（`DataTableSettings` だけは `evo()`・`db()`・`getv()` を使う）。リクエストのページ番号と並べ替えは `DataTableLinks::fromRequest()` が型つきヘルパー（`get_intvar()`・`get_choicevar()`。`request-input.md`）で読む。

ページ送りは、件数を数える → `DataTablePager` → 本体の SQL に `LIMIT` を付けて、そのページの行だけを取る、の順で行う。全件を取ってから切り出さない（サイト側の表の `DataTableSettings` は、値や DB の結果がすでに手元にあるので、配列を切り出す）。`perPage` が 0 以下のときは全件を 1 ページにし、`limit()` は 0 を返すので、SQL に `LIMIT` を付けない。

    $links = DataTableLinks::fromRequest('index.php', ['a' => 120], ['id', 'pagetitle']);
    $pager = new DataTablePager($total, $perPage, $links->page());
    // ... LIMIT に sprintf('%d,%d', $pager->offset(), $pager->limit()) を使って $rows を取る
    $table = new DataTable($columns, ['id' => 'children']);
    $nav = $pager->render($links, ['label' => lang('pagination_label')]);
    echo $nav . $table->render($rows, $links) . $nav;

古いページ送り `Paging`（`paginate.inc.php`）とブラウザ側の並べ替え `tablesort.js` は消えた。表とページ送りは `DataTable`・`DataTablePager` を使い、並べ替えは `DataTableLinks` のサーバー側で行う。

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
| `tableClass` | `grid` | `table` の class（空なら class 属性を出さない） |
| `headerClass` | `''` | `th` に足す class |
| `caption` / `captionHtml` | なし / false | `caption`。`captionHtml` が真なら HTML のまま |
| `emptyMessage` | `''` | 0 件のときの文言（エスケープ） |
| `showHeader` | true | `thead` を出すか |
| `selection` | なし | 表の上に「選んだ件数」と「この一覧のすべてを選択」の帯を出す（下の「選択の帯」） |
| `rowAttributes` | なし | `function (array $row): array`。属性名 => 値（値はエスケープ。`style`・`width`・`align`・`bgcolor`・`on*` は無視。`class` も返せる） |

`DataTable::render(array $rows, ?DataTableLinks $links = null)` に `DataTableLinks` を渡すと、`sort` のある列の見出しが並べ替えのリンクになり、今の列に `aria-sort` が付く。並べ替えのリンクではページが 1 に戻る。

## 選択の帯

`options['selection']` に `name`（行のチェックボックスの name。見出しの `data-select-all` と同じ）・`ids`（この一覧の全ページ分の値の配列）・`labels`・`max`（省略可）を渡すと、表を包む `div` の中の表の前に、帯の外枠（`div.data-table-selection`、`role="status"`）を出す。外枠は常に出ていて中身は空で、`data-table.js` が選んだ件数に応じて中身（`.data-table-selection-body`）だけを出し入れする（空の外枠から出すと、読み上げに告知されないことがあるため）。`name` が空・`ids` が空・`labels` の 5 つのどれかが無いときは、外枠も出さない。表は `form` の中に置く（「この一覧のすべて」で足す hidden の置き場所のため。`form` の外では、そのリンクを出さない）。

    'selection' => [
        'name' => 'batch[]',
        'ids' => $allIds, // 整数に直して出す
        'max' => 900,     // 省略可。ids の件数がこれを超えるときは、全件の値を出さない
        'labels' => [
            'selected' => lang('data_table_selected'),                   // 「{n} 件を選択しています。」
            'page' => lang('data_table_selected_page'),                  // 「このページの {n} 件すべてを選択しています。」
            'all' => lang('data_table_select_all_in_list'),              // 「この一覧の {total} 件すべてを選択」（リンク）
            'allSelected' => lang('data_table_selected_all_in_list'),    // 「この一覧の {total} 件すべてを選択しています。」
            'clear' => lang('data_table_clear_selection'),               // 「選択を解除」（リンク）
        ],
    ],

文言は呼び出し側が言語ファイルから渡す（`DataTable` は言語を読まない）。`{n}`（選んだ件数）と `{total}`（`ids` の件数）は `data-table.js` が置き換える。動きは次のとおり。

| 操作 | 帯の中身 |
| --- | --- |
| 選んだ行が 0 件 | 空 |
| 1 件以上選ぶ | `selected` |
| そのページの行がすべてそろい、`ids` の件数がそのページの行より多い | `page`。全件の値が付いていて、表が `form` の中にあるときは、リンク `all` も出す |
| リンク `all` を押す | `allSelected` ＋リンク `clear`。このページの行は `ids` に含まれるものだけを選び（含まれない行は外す）、ほかのページの値を、`name` と同じ名前の hidden として `form` に足す |
| すべてを選んだあとで、チェックボックスを 1 つでも変える | ほかのページの hidden（`name` が同じものだけ）を外し、そのときの選び方に応じた表示（0 件なら空、ページの全件がそろえば `page`、それ以外は `selected`）に戻る |

`max` は、全件の値を hidden で送ると、件数が `max_input_vars`（PHP の既定 1000）を超えたぶんを PHP が黙って切り捨て、一括操作に一部しか届かないために置く。`ids` の件数が `max` を超えるときは `data-all-ids` を空にして出さず、リンク `all` を出さない（ページの件数の帯だけになる）。呼び出し側は `ini_get('max_input_vars')` から、フォームのほかの値の数と余裕を引いた値を渡す（子リソースの一覧は 50 を引く）。

受ける側（一括操作）は、今までどおり `name` の配列を受けるだけで変えない。全件の値は data 属性（`data-all-ids`）に JSON で入れる。件数が多い一覧で HTML が大きくなる欠点はあるが、「親の id と絞り込みの条件」を送って受ける側で直す方式は、一括操作ごとに絞り込みの組み直しと権限の確認が要るので採らない。`ids` は呼び出し側が、一覧の件数を数える問い合わせの結果から、一覧に見えるものだけで作る（例: 子リソースの一覧は `isContainAllowed()` を通す）。

### 操作の帯

同じ `form` の中に `data-selection-actions="<selection の name>"` を付けた要素（一括操作のボタンの欄）があると、`data-table.js` が帯とその欄を 1 つの「操作の帯」（`div.data-table-selection-dock`）にまとめる。表の直後（表を包む `div` の末尾。ページ送りの前）に置き、画面の下端に貼り付く（`position: sticky; bottom: 0`）。帯の外枠（`role="status"`）は 0 件のときも描いたままで、帯に `is-empty` を付けて枠・余白・影とボタンの欄だけを消し、1 件以上選んだときに見せる（空の外枠から出さないため）。帯の中には、すべてを選んだとき以外でも常に「選択を解除」のリンクを出す。ボタンの欄の中身と送り先は呼び出し側が決める。ボタンは `<ul class="actionButtons"><li><button type="submit" formaction="…">` で並べる（送り先を変えるのでリンクにはできない）。見た目は右上のボタン群と同じにするため、各テーマの `.actionButtons :is(a, button)` の規則（通常・`:hover`・`:active`・`:focus-visible`）に任せ、新しい見た目の規則は足さない。`li.is-danger` は右寄せで、文字と枠を警告色にして区別する。帯の見た目は、表の部品に固有のもの（sticky・`is-empty`・中の帯）を除いて、共通のクラス `.selection-dock` が持つ（`manager/media/style/common/selection-dock.css`）。ファイルブラウザ（ファイル管理・ファイルピッカー）の選択の帯（`.evo-fb-selectionbar`）も同じクラスを使い、出し方だけが違う（a=120 は選んだときだけ出して下端に貼り付ける、ファイルブラウザは一覧の下に場所を取っておき、選んでいないときは `visibility: hidden` で見えなくするだけにして、出し入れで一覧の位置も高さも変えない。1 行に固定し、狭い幅では横にスクロールする。下端に重ねないのは、見えている最下行のダブルクリックの 2 回目が帯に当たるため）。この CSS はシェルの外（ファイルピッカーの単独のページ。`shell.css` を読まない）でも効くよう `shell.css` とは別にして、シェルの `header.inc.php` と `BrowserView::standalonePage()` が、テーマの `style.css` の後ろで読み込む。詳細度は `.selection-dock` を前に付けて、テーマの `ul.actionButtons`・`.actionButtons li`・`.actionButtons :is(a, button)`・`button` の規則（どれも (0,1,1) 以下）に勝たせる（`!important` は使わない）。押せないボタン（`button:disabled`。ファイルブラウザで zip を作れない環境の、2 件以上のダウンロードなど）は薄くし、`cursor: not-allowed`（包む `li` に付ける）にする。テーマの `.actionButtons` には無効の見た目が無いため、ここで持つ。帯の地色・枠・影・角は、各テーマの `#actions` の規則に `.selection-dock:not(.is-empty)` を足して同じ値にする（値は 1 か所）。RevoClassic・AromaHop・SakuraFlow は `#actions` に枠も影も無いので、共有の規則に `border: 0; box-shadow: none;` を書いてそろえる。`selection-dock.css` の既定の枠・影が残るのは、共有の規則を持たないテーマ（外部のテーマ・RevoStyle）だけ。半透明の地色でも下の表が透けて読めなくならないよう、帯の後ろは白で塗りつぶす（`backdrop-filter`。白に決め打ちなので、暗い地のテーマはテーマ側で上書きする）。帯の中の文言・リンク・削除の文字は、地色に対して 4.5:1 以上になるよう本文色・警告色を濃くし、リンクは下線で区別する。RevoStyle（凍結テーマ）は対象外で（ファイルブラウザの選択の帯も同じ例外）、帯の地色・枠・影は `selection-dock.css` の既定、ボタンはテーマの素の `button` の見た目になる（テーマの操作ボタン群の見た目にはならない）。シェルの右上のボタン群（`#actions`）は隠さず、常に出す。帯は下端に貼り付くので、右上のボタン群とは重ならない。欄が無い一覧は、これまでどおり帯だけを出す。表の部品に固有の出し方は `shell.css` の `.data-table-selection-dock`。

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

`DataTableLinks::url($overrides)` は、上書きの値が `null` の引数を URL から外す（絞り込みの解除は `q` とページを外し、並べ替えは残す。並べ替えの解除は `orderby`・`orderdir`・ページを外す）。

ページ番号の引数名は `DataTableLinks` の `pageParam` で替えられる（1 画面に表が複数あるとき）。ページ送りは、今のページを中心に最大 9 個の番号と、前後の「先頭」「<」「>」「最後」を出す。行けないものは `<span>`。総ページ数が 2 未満なら何も出さない。

## テーマが持つ class

| class | 内容 |
| --- | --- |
| `.grid` | 表 |
| `.pagination` | ページ送りの `nav`。今のページは `a[aria-current="page"]` |
| `.is-shrink` | 幅を中身に合わせる列 |
| `.is-center` | 中央寄せの列 |
| `.is-end` | 右寄せ（行の終わりの側に寄せる）の列 |
| `.data-table-empty` | 0 件のときのセル |

並べ替えている列の見出しは `th[aria-sort="ascending|descending"]` になる。テーマは `.grid th[aria-sort] a::after` で向きの印（昇順 ▲・降順 ▼）を出す。印は飾りなので `content: "▲" / ""` の形で読み上げから外し（向きは `aria-sort` が伝える）、色は `--color-primary`、間は `--space-1` を使う。凍結テーマの RevoStyle は持たない。

外部のテーマはこの一覧を写す。

## JS の書き方

- インラインの `onclick` を出さない。Vanilla JS で書く。
- 表に共通の動き（見出しの「すべて選択」のチェックボックス `input[data-select-all="<name>"]`、選択の帯）は `manager/media/script/data-table.js` が `document` へのイベントの委譲で扱う。シェルは画面を開き直すたびにスクリプトを動かし直すので、`window.EvoDataTable` があれば登録しない。`window.EvoDataTable` はオブジェクトで、選択の帯を描き直す `renderSelections()` を持つ。これは登録の有無にかかわらず、画面を開くたびに末尾から呼ぶ（最初の版の関数が残っていても、そのときの DOM を描ける）。
- 画面ごとの動き（右クリックのメニューを開くなど）は、各画面のスクリプトが表を包む要素（`[data-table="<id>"]`）にリスナーを付ける。要素は画面の差し替えで消えるので、リスナーが積み重ならない。行のアイコンやタイトルには `data-row-menu="<id>"` を付け、リスナーは `e.target.closest('[data-row-menu]')` で受ける。
- 画面ごとのリスナーは、画面のインラインの `<script>` の中で `Evo.ready()` に包んで付け、その `<script>` は表より前に置く。表の後ろや入れ物の末尾には置かない（入れ物の `:last-child` や `:has(+ X)` の余白の規則がずれるため）。全体の読み込みでは表より前のスクリプトが先に動くので、`Evo.ready()` で表ができてから登録する。
- シェルの外の別窓の画面（リソースセレクタなど）は `evo.js` を読まないので、`document` への委譲で受けてよい。

## 戻り先で状態を引き継ぐ

一覧の操作（公開・削除など）のあとで一覧へ戻すときは、今のページと並べ替えを引き継ぐ。引数の読み方（`orderby` の許可リストなど）を画面ごとに書かず、一覧ごとに 1 か所へまとめ、画面と processor の両方がそれを使う。例は子リソースの一覧の `manager/includes/controls/child_document_list_state.class.php`（`ChildDocumentListState`）。子リソースの一覧は、並べ替えに加えて検索語 `q`（`get_strvar('q')`、100 文字まで）も URL に持ち、ページ送り・並べ替え・処理のあとの戻り先へ引き継ぐ。絞り込みは `pagetitle`・`longtitle`・`alias`・`content`（本文。HTML のタグ名にも一致する）の部分一致（数字だけなら `id` の一致も。数字だけのときは `ChildDocumentListState::prependIdMatch()` が ID の一致した文書を先頭に並べる。LIKE の `%`・`_`・`\` は無害化してから `db()->escape()`）。表の上の検索欄（GET のフォームで、ページ番号は送らない）と「既定の並び順に戻す」（`orderby`・`orderdir`・ページを外し、`q` は残す）は、表を包む POST のフォームの外に置く。

ユーザー管理・Web ユーザー管理・モジュールの一覧は、検索語（`search`）・ページ番号（`page`）・一覧モード（`listmode`）を URL だけで決め、画面状態（セッション）には覚えない。ページ送りのリンクは検索語を引き継ぎ、ユーザー管理・Web ユーザー管理の検索フォームはページ番号を送らない（検索すると 1 ページ目。モジュールの一覧には検索フォームが無い）。編集画面のキャンセルなど、パラメータなしで開いた一覧は絞り込みなしの 1 ページ目になる。検索した一覧へはブラウザの「戻る」で戻る（例: `manager/actions/permission/user_management.static.php`）。この 3 つの一覧は、削除・複製・保存などの処理のあとも、絞り込みなしの 1 ページ目に戻る（上の「今のページと並べ替えを引き継ぐ」の例外）。処理のあとの戻り先の URL に状態を載せる必要がある一覧だけが、専用の状態のクラス（`ChildDocumentListState`）を持つ。

## サイト側の表（TV の出力ウィジェット・修飾子・`getHTMLGrid()`）

3 つとも `DataTableSettings::render($source, array $settings, string $id)` で描く。`$source` は DB の結果か、区切り文字のテキスト（1 行のファイルのパスも可）。それぞれの設定のキーを `$settings` の共通のキーに読み替えるのは呼び出し側の役目で、列名・型の文字列の分け方と型の書式はこの 1 か所にある。

| 入口 | `$id` | 設定 |
| --- | --- | --- |
| TV の出力ウィジェット `datagrid`（`manager/includes/docvars/outputfilter/datagrid.inc.php`） | `tv{TV 名}` | 表示パラメータ（下の表） |
| 修飾子 `:datagrid`（`manager/includes/extenders/ex_modifiers.php`） | `mdf` | 見出し行なし・ページ送りなし（全件）。1 行目にタブがあればタブ区切り、無ければカンマ区切り |
| `db()->getHTMLGrid($dsq, $params)`（`manager/includes/extenders/dbapi/mysqli.inc.php`） | `$params['id']`（既定 `grid`） | `$params`（`columns`・`fields`・`colTypes`・`header`・`footer`・`pageSize`・`pagerLocation`・`id`（既定 `grid`）・`cssClass`（`tableClass`）・`columnHeaderClass`・`pagerClass`・`noRecordMsg`）。無いキーは既定値 |

### 読む設定と無視する設定

保存済みの表示パラメータ（`display_params`）は DB を書き換えない。読むのは意味のある設定だけで、見た目の指定（`style`・`width`・`align`・`bgcolor`・`cellpadding`・`cellspacing`）は読まない。見た目はサイトの CSS（`tblc` などの class、`td:nth-child()`）で書く。

| 読む（表示パラメータ → 共通のキー） | 内容 |
| --- | --- |
| `cols` → `columns` | 列名 |
| `flds` → `fields` | `@SELECT` のとき出す列のキー（無ければ結果の最初の行のキー） |
| `ctype` → `types` | 列の型 |
| `cdelim` → `delimiter` | 区切り文字（`tab` はタブ。行ごと・区切り文字の文字列で分ける。既定 `,`） |
| `cwrap` → `wrapper` | 各セルの前後から除く囲み文字 |
| `enc` → `encoding` | ファイルから読むときの文字コード |
| `detecthead` → `detectHeader` | `first line` なら 1 行目を列名にする |
| `psize` → `pageSize` | 1 ページの行数（0 は全件） |
| `ploc` → `pagerLocation` | `top`・`bottom`・`both`（先頭の語で読む。`top-right` は `top`。空は `top`）。`-left`・`-right` は無視 |
| `head` → `header` | 表の `<caption>`（HTML のまま） |
| `foot` → `footer` | 表の後ろの `<div class="gridfooter">`（HTML のまま） |
| `tblc`・`chdrc`・`pclass` | 表・見出しのセル・ページ送りの class（ページ送りの既定は `pager`。表は空なら class を付けない。`grid` は管理画面のテーマの class） |
| `egmsg` → `emptyMessage` | 0 件のときの文言 |

読まない: `tbls`・`itms`・`aitms`・`chdrs`・`pstyle`（`style`）、`ccolor`（`bgcolor`）、`cwidth`（`width`）、`cpad`・`cspace`、`calign`（`align`）。ウィジェットの設定画面にも出さない。

型（`ctype`）は `||` があれば `||`、無ければ `,` で分け、列の順に当てる。`integer`・`float[:桁]`・`currency[:桁]`・`date[:形]`・`boolean`・`description`・`template:文字列`。数として読めない値は書式を当てずにエスケープして出す。右寄せ・中央寄せの `align` は出さない。

### エスケープ

セルと見出し（`cols`・`columns`・`first line` の見出し行）は既定でエスケープする。`template` 型は、テンプレートの文字列（設定した人が書いた HTML）はそのままで、差し込む `[+value+]`・`[+列のキー+]` の値だけをエスケープする。`head`・`foot` は設定した人が書いたものなので HTML のまま出す。

### ページ送り

ページ番号の引数名は `{$id}_page`（TV なら `tvdgtest_page`）。TV は TV 名ごと、`getHTMLGrid()` は `id` ごとに別になる。同じものを 1 ページに複数置くとページ番号を共有する。引数名は PHP が書き換えない形にする（空白・`.`・`[` を `_` にする）。リンクを作る側と読む側でこの名前を使う。置き換えた後に同じ名前になる TV どうし（`a.b` と `a_b` など）は、1 ページに並べるとページ番号を共有する。リンクは今のリクエストの引数のうち、`{$id}_page` とサイト側の `id` 以外を残す（`id` は `makeUrl()` が作る）。ページキャッシュは `id` 以外の引数のハッシュ（`genQsHash()`）で分かれるので、ページ送りの引数付きの URL は別のキャッシュになる。管理画面の言語は読まず、ページ送りの文言は `DataTablePager` の既定の記号（`«`・`<`・`>`・`»`）。

### サイトの CSS に影響する変化

- 行に交互の class を付ける指定（`itmc`・`aitmc`、`getHTMLGrid()` の `itemClass`・`altItemClass`）は無くなった。行を 1 行おきに色分けするときは、サイトの CSS で `tbody > tr:nth-child(even)` のように書く。
- 修飾子 `:datagrid` の `td` に、旧の既定の `class="cell"`・`class="altCell"` は付かない。
- 列名（`cols`・`columns`・`first line` の見出し）もエスケープする（旧は HTML のまま）。
