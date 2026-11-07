# ExecPlan: 表の部品（DataGrid・MakeTable）を廃止し、DataTable を作って載せ替える

## Purpose / Big Picture

管理画面とサイト側で「行の集まりを、ページ送り付きの HTML の表にする」部品を `DataTable` 1 つにまとめる。新しい部品はエスケープが既定で有効で、`style` 属性やインラインの `onclick` を出さず、ページ送りは必要な行だけを DB から取る。古い `DataGrid`・`DataSetPager`・`MakeTable` は消す。利用者から見ると、各一覧の表示と操作（ページ送り・並べ替え・選択・右クリックのメニュー）は main と同じに保ったまま、表の HTML が意味に合った形（`thead`・`th scope`・`aria-sort`・`aria-current`）になり、DB の値がそのまま HTML として出ることがなくなる。

Issue: #1087（ロードマップ「表の部品（DataGrid・MakeTable）を作り直す」）

到達条件（ロードマップの DoD と同じ）:

- 行の配列と列の定義を受け取る新しい部品 `DataTable`（`manager/includes/controls/data_table.class.php`）があり、エスケープが既定で有効になっている
- 管理画面とサイト側の利用箇所（TV の出力ウィジェット `datagrid`、修飾子 `:datagrid`、`db()->getHTMLGrid()`）が新しい部品を使っている
- `manager/includes/controls/datagrid.class.php`・`manager/includes/controls/datasetpager.class.php`・`manager/includes/extenders/ex_maketable.php`・`manager/includes/extenders/maketable.class.php` と `loadExtension('MakeTable')` の分岐が無い

## Progress

- [x] (2026-11-07) 段階 1: `DataTable` を作り、子リソースの一覧を移して `MakeTable` を外す（ブランチ `refactor/1087-data-table-stage1`）。実装・検証（main と比較、7 テーマ）・自己レビュー（高・中なし）まで完了。右クリックのメニューの戻り先で並べ替えを保つため範囲を広げた（D19）。PR のマージ待ち
- [x] (2026-11-07、ブランチ `refactor/1087-data-table-stage2`) 段階 2: 管理画面の `DataGrid` の利用箇所（ユーザー管理・Web ユーザー管理・モジュール・リソースセレクタ・モジュールの依存関係 2 画面・DocManager）を移す。実装・検証（main と比較、PHP 7.4 でも画面を開いた）・自己レビュー（高・中なし）まで完了。段階 1 のブランチの上に積み、PR のマージ待ち（D20）
- [ ] 段階 3: TV の出力ウィジェット `datagrid`・修飾子 `:datagrid`・`db()->getHTMLGrid()` を載せ替え、`DataGrid`・`DataSetPager` を外す

## Surprises & Discoveries

- 2026-11-07（計画時、コードを読んだ限り）: `DataGrid::render()` は `header` を指定すると `$o` を `<div class="gridheader">…</div>` で始め、`<table>` の開始タグを出さない（`datagrid.class.php:515-519`）。TV の出力ウィジェットで「Header Text」を入れると表が壊れる。段階 3 で main を観測して確かめ、新しい部品では起きないことを確かめる。
- 2026-11-07（同）: `DataGrid` は区切り文字のテキストを `preg_split('@[' . $cdelim . "\n]@")` で分けている（`datagrid.class.php:421`）。正規表現の文字クラスなので、区切り文字 `||` は `|` 1 文字ずつ、`::` は `:` 1 文字ずつで分かれ、値の中の `|`・`:` でも列がずれる。行も改行ではなく列の数で `array_chunk()` している。段階 3 で行ごと・区切り文字の文字列ごとに分ける形に直す（D15）。
- 2026-11-07（同）: TV の値が `@SELECT` バインディング（DB の結果）で、表示パラメータに `flds` が無いとき、`DataGrid` は `fields` を `''` として `explode()` し、列の値を数字のキーで探すので、セルが空になる（`datagrid.class.php:326-330, 141-148`）。`flds` はウィジェットの設定画面に無い項目なので、画面から設定したサイトでは `@SELECT` の表のセルが空のはず。段階 3 で main を観測して確かめる。
- 2026-11-07（同）: `DataSetPager` は、ページ番号を管理画面の画面状態（`$_PAGE['vs']['dg1_dpgn']`、実体は `$_SESSION['mgrPageViewSDATA']`）に保存する。ユーザー管理・Web ユーザー管理・モジュールの一覧は `manager()->initPageViewState()` を呼ぶので、編集画面から一覧へ戻ると前のページが開く。段階 2 でこの動きを呼び出し側で保つ（D9）。
- 2026-11-07（同）: `MakeTable::renderPagingNavigation()` は `<div id="pagination">` を出し、子リソースの一覧は表の上と下に 2 回出すので、同じ id が 2 つある。範囲外のページ（`page=999`）では、見出しも行も無い `<table>` とページ送りだけが出る。
- 2026-11-07（同）: 子リソースの一覧は `media/script/tablesort.js` を読み込んでいるが、表の `th` に `sortable` の class が無いので何もしていない。
- 2026-11-07（同）: ユーザー管理・Web ユーザー管理は、性別・ブロックの表示文言を `IF(mua.gender=1,'{$_lang['user_male']}', …)` の形で SQL に埋め込んでいる（言語の文字列をエスケープせずに SQL に入れている）。段階 2 で書式の関数に移すと SQL から消える。
- 2026-11-07（同）: DocManager の 3 つの表は `colEscapes` を指定しておらず、テンプレート名・説明・文書グループ名がエスケープされずに出る。テンプレートの表の下には「空のテンプレート（id 0）」の行だけを別の `<table>` で `style` 属性付きで書いている（`templates.tpl`）。
- 2026-11-07（同）: `manager/includes/paginate.inc.php` の `Paging` は、管理ログ（`report/logging.static.php`）とメッセージ（`permission/messages.static.php`）が使う 3 つ目のページ送り（引数 `int_cur_position`）。どちらも SQL の `LIMIT` で取っている。表は手書きで、`tablesort.js` でブラウザ側の並べ替えをしている。2026-11-07 の main でメッセージ機能が外され、利用者は管理ログだけになった（段階 1 のブランチへ main を取り込んだときに確認）。Issue の対象（DataGrid・MakeTable）の外なので、この Issue では移さず、`assets/docs/core-issues.md` に後続の候補として記録した（D11）。
- 2026-11-07（同）: `manager/includes/migrations/`・`install/`・同梱のスニペットとプラグインに `DataGrid`・`MakeTable`・`getHTMLGrid` の利用は無い。`.agent/` の過去の ExecPlan と `assets/docs/troubleshooting/solved-issues.md` の言及は記録なので変えない（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md` は未完了の計画なので段階 3 で直す）。
- 2026-11-07（段階 1 の検証）: main の `delete_content.processor.php` は `$page` を作っていたのに戻り先に使っておらず、2 ページ目から削除すると 1 ページ目に戻っていた（公開・非公開・復元は `page` を残す）。D19 で、4 つの処理とも `page` と並べ替えを残すようにそろえた。
- 2026-11-07（段階 1 の検証）: 右クリックのメニューの戻り先に並べ替えを付けても、処理側の PHP が戻り先を決めているので、画面の JS だけを直しても残らなかった（D19 の発端）。複製（`a=94`）と移動（`a=51`）は main でも戻り先に `page` を引き継いでおらず、段階 1 では変えていない。
- 2026-11-07（段階 1 の自己レビュー）: 一覧の `get_jscript()` が `document` に付けるメニューを閉じるリスナーは、シェルで一覧を開き直すたびに積み重なっていた（もとからある問題）。触ったファイルなので、動きを変えずに 1 回だけ登録する形に直した（`window.evoChildListMenuClose`）。同じ形の積み重なりが `modules.static.php:120`・`user_management.static.php:88`・`web_user_management.static.php:93` にもあり、段階 2 で同じように直す（印を画面ごとにするか、共通の 1 つにまとめて子リソースの一覧もそれに寄せるかは段階 2 の着手時に決める）。
- 2026-11-07（段階 2 の検証）: リスナーを表より前の `<script>` で付けると、シェルの遷移では表があるので動くが、全体の読み込み（F5・URL の直打ち）では表がまだ無く付かず、メニューが開かなかった（ユーザー管理・Web ユーザー管理・モジュール）。シェルの遷移だけの検証では見落とすので、画面の JS は全体の読み込みとシェルの遷移の両方で確かめる。
- 2026-11-07（段階 2 の検証）: `<script>` を `#mainPane` の最後に置くと、`#mainPane > :last-child { margin-bottom: 0 }` が最後のブロックに当たらず、下に 12px 残った（モジュールの一覧、4 テーマ）。`theme-spacing.md` の `<style>` の注意と同じことが `<script>` にも当たる（D21 (1)）。
- 2026-11-07（段階 2 の検証）: main のリソースセレクタは 0 件で `restoreChkBoxes()` が JS エラーになっていた。触ったファイルなので直した。main のユーザー管理は、リセットの直後にページ送りの URL に `op=reset` が残って 2 ページ目へ進めなかったが、`DataTableLinks` に移したことで起きなくなった。
- 2026-11-07（段階 2 の検証）: DocManager を開いたあとシェルで別の画面へ移ると、`assets/modules/docmanager/js/docmanager.js:104` の `hideInteraction` が `null.style` を読んでエラーになる。main でも同じで、#1087 で触っていないファイルなので直していない。
- 2026-11-07（段階 2 の自己レビュー）: main と同じ動きの問題が 2 つある。リソースセレクタの「挿入」は今のページのチェックだけを集め、ほかのページで選んだものは捨てられる。ユーザー管理・Web ユーザー管理・モジュールで検索すると、覚えたページ番号のまま開く。どちらも直すと動きが変わるので、#1087 では扱わない（Issue の候補）。
- 2026-11-07（段階 3 の検証、main の観測）: `head` を入れた TV のページは、PHP 8.4 の main では 500 になった（`datagrid.class.php:516` の `Undefined variable $o`。`<table>` の開始タグも代入されない）。`cdelim=||` は `[||\n]` の文字クラスで分かれ、`a|x` が 2 つのセルになり、行も列の数の `array_chunk` で決まるのでセルが次の行へずれた（3 行が 7 行になる）。`@SELECT` で `flds` が無いと、見出しは出るがセルが全部空だった。ほかに、main の `getHTMLGrid()` は `noRecordMsg` を渡さないと PHP 8.4 で 500（`mysqli.inc.php:1079` の `Undefined array key`）、`integer` 型に数でない値で TypeError の 500、`ploc` が空だと 2 ページ目へ行けない、修飾子は 20 行で切ってページ送りを出す、1 行だけのタブ区切りは分かれない、template 型は値をエスケープしない、ページ送りのリンクに `id` が残る、だった。段階 3 でどれも起きなくなった。
- 2026-11-07（段階 3 の検証）: 計画の確認 9 は「保存すると捨てた項目が `display_params` から消える」としていたが、設定画面は保存済みのキーをそのまま書き戻すので残る（main も同じ）。D1 のとおり読まれないだけで無害なので、期待のほうを直した（D22 (8)）。

## Decision Log

- 2026-11-07 / 本人 D1: TV の出力ウィジェット `datagrid` は「意味のある設定だけ引き継ぐ」。保存済みの表示パラメータは DB を書き換えず（マイグレーションなし）、新しい部品が読む項目だけを読み、ほかは無視する。修飾子 `:datagrid` は残し、新しい部品で描く。項目ごとの扱い（planner が調べて決めた分を含む）:
  - 残す: `cols`（列名）・`cdelim`（区切り文字）・`cwrap`（囲み文字）・`enc`（ファイルから読むときの文字コード）・`detecthead`（見出し行の検出）・`psize`（ページサイズ）・`head`・`foot`（見出し・末尾のテキスト）・`tblc`・`itmc`・`aitmc`・`chdrc`・`pclass`（class）・`egmsg`（空のときの文言）・`flds`（`@SELECT` のとき出す列。画面には無いが手で書いた設定を読む）
  - `ctype`（列の型）は残す。値の書式（数値の桁区切り・日付の形・真偽の記号・テンプレート）で、見た目の指定ではないため。型は今と同じ `integer`・`float`・`currency`・`date`・`boolean`・`template`・`description`。ただし型ごとに付いていた `align`（右寄せ・中央寄せ）は出さない
  - `ploc`（ページ送りの位置）は上下の部分だけ残す。`top`・`bottom`・`both` は「表のどちら側に出すか」という構造の指定で意味がある。`-left`・`-right` は `<div align>` を出すための見た目の指定なので捨てる。保存済みの `top-right` などは先頭の語で読む（`top-right`→上、`both-left`→上下）。設定画面の選択肢は `top,bottom,both` にする
  - 捨てる: `tbls`・`itms`・`aitms`・`chdrs`・`pstyle`（`style` 属性）、`ccolor`（`bgcolor`）、`cwidth`（`width`）、`cpad`・`cspace`（`cellpadding`・`cellspacing`）、`calign`（`align`）。見た目はサイトの CSS（`tblc` などの class や `td:nth-child()`）で書く
- 2026-11-07 / 本人 D2: 新しい部品は `manager/includes/controls/data_table.class.php` の `DataTable` クラス。管理画面とサイト側の両方から include して使う。
- 2026-11-07 / 本人 D3: Issue の「進める順番」の 3 段階を、1 段階 1 PR にする。段階 1 のブランチは `refactor/1087-data-table-stage1`。
- 2026-11-07 / planner D4: `data_table.class.php` に、表を描く `DataTable`、ページ送りを計算して描く `DataTablePager`、リンクの URL と今の状態（ページ・並べ替え）を持つ `DataTableLinks` を置く。段階 3 で、保存済みの設定の文字列から表を作る `DataTableSettings` を足す。管理画面とサイト側の両方が 1 つのファイルを include するだけで使えるようにするため（D2）。代替案はクラスごとにファイルを分ける形（include が増え、片方だけ読み忘れる恐れがある）。
- 2026-11-07 / planner D5: 描く処理（`DataTable::render()`・`DataTablePager::render()`）は、引数だけから HTML を作り、`evo()`・`db()`・リクエスト・グローバル変数に触れない。リクエストのページ番号と並べ替えは `DataTableLinks::fromRequest()` が型つきヘルパー（`get_intvar('page')`・`get_choicevar('orderby', <並べ替えのキー>, '')`・`get_choicevar('orderdir', ['asc', 'desc'], 'asc')`）で読む。main の `MakeTable` と同じ読み方（`get_intvar('page')`）で、それより緩くしない。`helpers.php` だけを読めば単体で動かせるので、Docker の `php` で入出力を確かめられる。
- 2026-11-07 / planner D6: 見た目は今のテーマの class に任せる。表は `class="grid"`、行は `gridItem`・`gridAltItem` を交互に `<tr>` に付ける（子リソースの一覧の今の形。`DataGrid` は `<td>` に付けていたが、テーマの規則は `.gridItem` の単独のセレクタなのでどちらにも効く）。`width`・`align`・`bgcolor`・`style` は出さない。今 `width`・`align` で狭めたり中央に寄せたりしている列は、列の定義の `class` に `is-shrink`（幅を中身に合わせる）・`is-center`（中央寄せ）を付け、7 つのテーマの `.grid` の節に規則を足す。代替案は行の色分けを `tbody tr:nth-child(even)` に移す形（7 テーマの見た目の規則を書き換えることになり、この Issue の目的から外れる。将来テーマ横断で行う）。
- 2026-11-07 / planner D7: ページ送りは `<nav class="pagination" aria-label="…"><ul><li>…</li></ul></nav>` で出し、今のページのリンクに `aria-current="page"` を付ける。同じ id が 2 つ出る問題をなくすため、id は出さない。7 つのテーマの `#pagination` を `.pagination` に、`li.currentPage a` を `a[aria-current="page"]` に置き換え、`assets/docs/theme-spacing.md` の `#pagination` の記述も直す。RevoStyle は凍結テーマだが、セレクタの名前を変えるだけで見た目を変えないので対象に含める。`.pagination` は詳細度が (0,1,0) に下がり、最後の子の規則 (0,2,0) に負けるので、`#pagination:last-child` の特例の規則は要らなくなる（消してよい。消すときはコメントも直す）。
- 2026-11-07 / planner D8: 引数の名前は、管理画面は `page`・`orderby`・`orderdir`（`MakeTable` と同じ）。サイト側の TV の表は 1 ページに複数あり得るので `{id}_page`（`id` は `tvProcessor()` が作る `tv{TV 名}`）。範囲外のページ（0、総ページ数より大きい）は 1 ページ目にする（`DataSetPager` と同じ。`MakeTable` の「空の表」は不具合なので引き継がない）。番号のリンクは今のページを中心に最大 9 個（`MakeTable` と同じ）、前後に「先頭」「<」「>」「最後」を置き、行けないものは `<span>` で出す。総ページ数が 2 未満なら何も出さない。
- 2026-11-07 / planner D9: ユーザー管理・Web ユーザー管理・モジュールの一覧で、前に見たページを画面状態に覚える動き（Surprises 参照）は、呼び出し側で保つ。`page` が無ければ `$_PAGE['vs']['page']` を使い、読んだページを書き戻す。`op=reset`（検索のリセット）では 1 ページ目に戻す。`DataTable` 自身は画面状態に触れない（D5）。
- 2026-11-07 / planner D10（2026-11-07 本人確認済み）: 並べ替えのリンクは、段階 1 で子リソースの一覧の「ID」「タイトル」「公開日」「編集日」に付ける。`orderby` が無いときは今と同じ `manager_docs_orderby` の順で、`aria-sort` は付けない。段階 2 の画面は今と同じ固定の順のままにし、並べ替えのリンクは付けない（YAGNI）。
- 2026-11-07 / planner D11（2026-11-07 本人確認済み）: `manager/includes/paginate.inc.php` の `Paging` と、その利用者（管理ログ。メッセージの画面は main で外された）はこの Issue では移さない。新しく書くページ送りは `DataTablePager` だけを使い、`Paging` を使うコードを増やさない。移し替えは `assets/docs/core-issues.md` の「表とページ送りの組み方が DataTable の外に残る」に記録した後続の候補で行う。
- 2026-11-07 / planner D12: JS は Vanilla JS で、インラインの `onclick` を出さない。表に共通の動き（見出しの「すべて選択」のチェックボックス）は新しい `manager/media/script/data-table.js` が `document` へのイベントの委譲で扱う。シェルは画面を開き直すたびにスクリプトを動かし直すので、`window.EvoDataTable` があれば登録しない形で二重の登録を防ぐ。画面ごとの動き（右クリックのメニューを開く、DocManager のテンプレートの選択）は、各画面のインラインスクリプトが表を包む要素（`data-table` 属性を持つ `div`）にリスナーを付ける。要素は画面の差し替えで消えるので、リスナーが積み重ならない。右クリックのメニューの部品（`controls/contextmenu.php`）自体の古い作りは対象外。
- 2026-11-07 / planner D13（2026-11-07 本人確認済み）: サイト側（TV・修飾子・`getHTMLGrid()`）のセルの値も既定でエスケープする。今は `colEscapes` を指定していないので、テキストや DB の値の HTML がそのまま出ている（サイトの見た目が変わりうる）。`ctype` の `template` は、テンプレートの文字列（設定した人が書いた HTML）はそのまま、差し込む `[+value+]`・`[+列名+]` の値はエスケープする（今の `colEscapes` 有効時の `strtr()` と同じ形。サイトのプレースホルダーには触れない）。`head` は表の `<caption>` に、`foot` は表の後ろの `<div class="gridfooter">` に出す。どちらも設定した人が書いたものなので HTML のまま出す（ほかのウィジェットの設定と同じ扱い）。`head` の位置が `<div class="gridheader">`（表の上）から `<caption>` に変わるので、サイトの CSS によっては見た目が変わる。
- 2026-11-07 / planner D14（2026-11-07 本人確認済み）: 修飾子 `:datagrid` は、見出し行なし・ページ送りなし（全件）で描く。今は `DataGrid` の既定のページサイズ 20 でページ送りが出るが、修飾子には設定の手段が無く、ID をグローバルの数で振らないと引数が重なるため。区切り文字の判定（1 行目にタブがあればタブ、無ければカンマ）は今と同じ。
- 2026-11-07 / planner D15: 区切り文字のテキストは、改行（`\r\n`・`\r` も `\n` にそろえる）で行に分け、空の行を飛ばし、各行を区切り文字の文字列で `explode()` し、各セルの前後から囲み文字を `trim()` で除く。`cdelim` の `tab` はタブ文字。ファイルのパスとして読む条件（1 行で、`enable_bindings` が 1 で、`evo()->resolveBindFilePath()` を通り、拡張子が `.php` でない）と、`enc` による文字コードの変換は今の `DataGrid::setDataSource()` と同じ。
- 2026-11-07 / planner D16: TV の出力ウィジェット・修飾子・`getHTMLGrid()` の 3 つは、それぞれの設定のキーを共通の形に読み替えて `DataTableSettings::render()` に渡す。列名・型の文字列の分け方（`||` があれば `||`、無ければ `,`）と型の書式を 1 か所に置くため（SSOT）。`getHTMLGrid()` は DoD のとおり DBAPI に残し、中身だけ載せ替える。`params` のキーは今と同じ名前を受け、`style` 系（`columnHeaderStyle`・`cssStyle`・`itemStyle`・`altItemStyle`・`pagerStyle`）と `colWidths`・`colAligns`・`colColors`・`colWraps`・`cellPadding`・`cellSpacing` は無視する。無いキーは警告を出さずに既定値にする。
- 2026-11-07 / planner D17: 新しい言語のキーを 2 つ足す（`english.inc.php`・`japanese-utf8.inc.php`）。`pagination_label`（ページ送りの `nav` の `aria-label`。「ページ送り」/「Pagination」）と `select_all`（「すべて選択」のチェックボックスの `aria-label`。「すべて選択」/「Select all」）。ページ送りの「先頭」「最後」は今の `pagination_table_first`・`pagination_table_last` を使う。`DataTablePager` は言語を読まず、文言を引数で受ける（D5）。
- 2026-11-07 / planner D18: DocManager のテンプレートの表は、別の `<table>` で書いている「空のテンプレート（id 0）」の行を行の配列の先頭に入れ、1 つの表にする（`style` 属性の表を消す）。テンプレート名と文書グループ名はエスケープし、説明は管理画面のほかの要素の説明と同じ `element_description_html()` を通す。
- 2026-11-07 / 本人 D19（段階 1 の検証で範囲を広げた）: 子リソースの一覧を並べ替えたまま右クリックのメニューで公開・非公開・削除・復元をすると、戻った一覧で並べ替えが外れていた（処理側が `page` だけを引き継いでいた）。一覧の状態（ページ・並べ替え）の読み取り、並べ替えのキーと SQL の列名の対応、戻り先の URL の組み立てを `manager/includes/controls/child_document_list_state.class.php` の `ChildDocumentListState` 1 か所にまとめ、一覧の画面（`resources_list.static.php`）と 4 つの処理（`manager/processors/document/{publish,unpublish,delete,undelete}_content.processor.php`）の両方がそれを使う。決まり: 一覧ごとの状態は、汎用の `DataTable`・`DataTableLinks` に一覧固有の知識（許可する並べ替えのキー、`a=120` など）を入れず、その一覧専用のクラス 1 か所に置き、画面と処理の両方から使う。クラスの名前は Document 系にする（Resource にしない）。段階 2 で画面状態を覚える一覧（D9）も、同じ形で専用のクラスにするかを着手時に決める。
- 2026-11-07 / メイン D20（段階 2 の着手時）: 本人の指示で、段階 1 の PR（#1160）をマージせずに段階 2 へ進む。段階 2 のブランチは段階 1 のブランチから作り、PR の base を段階 1 のブランチにする（段階 1 を採用しないときは両方を閉じる）。D19 で段階 2 に残した 2 点はこう決めた: (1) メニューを閉じる `document` の `click` リスナーの印は、画面ごとにせず共通の `window.evoContextMenuClose` 1 つにし、子リソースの一覧（段階 1 の `window.evoChildListMenuClose`）もこれに寄せる。閉じる処理の中身（`contextm` の `visibility` を戻す）がどの画面も同じで、印を分けると同じ処理が画面の数だけ登録されるため。(2) ユーザー管理・Web ユーザー管理・モジュールの一覧には専用の状態のクラスを作らない。前に見たページは画面状態（D9）で覚えるので、処理から一覧へ戻ってもページが残り、戻り先の URL に状態を載せる必要が無いため。
- 2026-11-07 / メイン D21（段階 2 の実装・検証・自己レビューで決めたこと）: (1) リスナーの書き方: 画面ごとの表のリスナーは、画面の最初の `<script>` の中で `Evo.ready()` に包み、表を包む要素（`data-table` 属性の `div`）に付ける。`<script>` を表の後ろや入れ物の末尾に置くと、全体の読み込みで表がまだ無い・入れ物の `:last-child` や `:has(+ X)` の余白の規則がずれる、のどちらかが起きるため。段階 1 の子リソースの一覧もこの形にそろえた。シェルの外の別窓（リソースセレクタ）は `evo.js` を読まないので `document` への委譲で受ける（`assets/docs/data-table.md` に例外として書いた）。(2) 余白: ページ送りの前の間を作る規則の親を `form` から `:is(.sectionBody, .tab-page, form)` に広げた（6 テーマ。RevoStyle は凍結テーマなので変えない）。ボタン列の前・コード欄の前の規則と同じ親の集合にそろえるため。3 画面の表とページ送りを包んでいた素の `div` は外した（`.sectionBody` の直下に並べて標準の親に載せるため。素の `div` を親に足すと規則が広がりすぎる）。モジュールの一覧は `div#actions` を説明の段落の前へ移した（段落の次がページ送りになり規則が当たる。ボタンの位置は 7 テーマで main と一致）。(3) Concrete Steps と違えた点: ユーザー管理の性別は一覧に出ない列なので `format` に移さず SQL から外した（手順 1）。リソースセレクタの選択の変更は `document.selector` ではなく `document` で受ける（別窓でスクリプトがフォームより先に読まれるため、手順 4）。DocManager の 3 つの表の共通部分を `radioTable()` にまとめた（手順 7）。(4) 画面状態（D9）は、全件表示と 0 件のときは書き戻さない（main の `DataSetPager` と同じ）。(6) ついでの修正（触ったファイルの、動きを変えずに直せるもとからの問題）: DocManager の作者の選択肢（`renderChangeAuthors()`）の値と名前をエスケープした。リソースセレクタの `saveSelection()` は 0 件のとき何もしない（`restoreChkBoxes()` と同じ）。モジュールの一覧とリソースセレクタの検索語のエスケープを、変数の宣言から WHERE を組む直前へ移した。確認のダイアログなど JS に入る言語の文字列は、main に #1169 で `js_literal(stripcslashes(lang(...)))` に替える直しが横断で入ったので、段階 2 では独自に直さず、main を取り込んでその形に合わせた（一度入れた独自の直しは取り消した）。 (5) 書き込んだファイルは計画の 9 件から、6 テーマの `style.css`・`theme-spacing.md`・`data-table.md`・段階 1 の `resources_list.static.php` に広がった（上の (1)(2) のため）。
- 2026-11-07 / メイン D22（段階 3 の実装と検証で決めたこと）: (1) ページ送りの位置（`ploc`）が空なら上に出す（旧実装は出さず、2 ページ目以降へ行けなかった）。(2) 数値・日付の型で数として読めない値は、書式を当てずにエスケープして出す（旧実装は PHP 8 で TypeError）。`float:0` は小数 0 桁。(3) 修飾子 `:datagrid` は 1 行だけの値でも 1 行目のタブを見て分ける。(4) サイト側の表の class（`tblc`・`cssClass`・`tableClass`）が空なら class 属性を出さない（旧と同じ。管理画面の `grid` を付けない）。ページ送りの class の既定は `pager`。(5) `getHTMLGrid()` は DB の結果を `getRow()` で配列にし、`fields` が無ければ 1 行目のキーを列にする。表の class は実コードの `cssClass` を読み、docblock の `tableClass` も受ける。(6) 囲み文字（`cwrap`）は区切り文字のテキストのセルだけに当てる。(7) template 型は行のすべてのキーを置き換え（表示しない列も）、差し込む値はエスケープする。サイトのプレースホルダーは、TV の出力がこのあとページの解析にかかるのでそこで展開される。(8) 設定画面で保存し直しても、捨てた項目は `display_params` に残る（D1。読まれないので無害）。保存済みの古い `ploc`（`both-left` など）は先頭の語で読むが、設定画面の選択肢（`top,bottom,both`）に無いので画面では先頭の `top` に見える（保存しても元の値が残り、サイトでは元の値どおりに出る）。直すと設定画面の list の描き方を変えることになるので、記録にとどめた。(9) サイトの CSS に影響する変化: 行の class（`itmc`・`aitmc`、`getHTMLGrid()` の `itemClass`・`altItemClass`）は `tr` に付く（旧は `td`）。修飾子 `:datagrid` の `td` には旧の既定の `class="cell"`・`"altCell"` が付かない。列名（`cols`・`columns`・`first line` の見出し）もエスケープする（旧は HTML のまま）。(10) ページ送りの引数名は、PHP が GET の引数名の空白・`.`・`[` を `_` に置き換えるのに合わせてそろえる（TV 名に空白や `.` があっても 2 ページ目へ進める）。`getHTMLGrid()` は `id` を受け、1 ページに複数置くときは別の `id` を渡す（既定 `grid`）。

## Outcomes & Retrospective

## Context and Orientation

用語:

- 子リソースの一覧: 管理画面でフォルダを開いたときの、子のリソース（文書）の一覧（`index.php?a=120&id=<フォルダの id>`）。
- シェル（EvoShell）: 管理画面の外枠。`manager/media/script/shell.js` が、`index.php?a=…` へのリンクのクリックとフォームの送信を横取りし、中身（`#mainPane`）だけを取り替える。差し替えた中の `<script>` は動かし直される。
- 画面状態（view state）: `manager()->initPageViewState()` が用意する `$_PAGE['vs']`。実体はセッションで、同じ画面の番号（`a`）の間だけ残る。
- TV の出力ウィジェット: テンプレート変数（TV）の値をサイトに出すときの形。`datagrid` は値（区切り文字のテキスト・ファイル・`@SELECT` の結果）を表にする。設定は `site_tmplvars.display_params` に `&cols=…&psize=…` の形で保存されている。
- 修飾子 `:datagrid`: `[*tv名:datagrid*]` のように書くと、値の区切り文字のテキストを表にする。

今の利用箇所（行番号は 2026-11-07 の main、`4a87ecffa`）:

| 部品 | 利用箇所 | 段階 |
| --- | --- | --- |
| `MakeTable`（`extenders/ex_maketable.php`、`$modx->table`） | `manager/actions/document/resources_list.static.php:122-144`（子リソースの一覧）。読み込みは `document.parser.class.inc.php:256-259` の `loadExtension('maketable')` と、ex_maketable.php を読むだけの `extenders/maketable.class.php` | 1 |
| `DataGrid` | `manager/actions/permission/user_management.static.php:167`（ユーザー管理 a=75） | 2 |
| 〃 | `manager/actions/permission/web_user_management.static.php:155`（Web ユーザー管理 a=99） | 2 |
| 〃 | `manager/actions/element/modules.static.php:154`（モジュール a=106） | 2 |
| 〃 | `manager/actions/element/resource_selector.static.php:209`（リソースセレクタ a=84。シェルの外の別窓） | 2 |
| 〃 | `manager/actions/element/mutate_module.dynamic.php:389`（モジュールの編集 a=108 の依存関係のタブ） | 2 |
| 〃 | `manager/actions/element/mutate_module_resources.dynamic.php:227`（モジュールの依存関係の管理 a=113） | 2 |
| 〃 | `assets/modules/docmanager/classes/dm_frontend.class.php:10, 26, 47, 67`（DocManager のテンプレート・TV・文書グループの表） | 2 |
| 〃 | `manager/includes/docvars/outputfilter/datagrid.inc.php`（TV の出力ウィジェット。`document.parser.class.inc.php:5486-5508` の `tvProcessor()` から include） | 3 |
| 〃 | `manager/includes/extenders/ex_modifiers.php:1355-1368`（修飾子 `:datagrid`） | 3 |
| 〃 | `manager/includes/extenders/dbapi/mysqli.inc.php:1041-1103`（`getHTMLGrid()`。本体からの呼び出しは無い） | 3 |
| ウィジェットの設定項目 | `manager/actions/element/mutate_tmplvars.dynamic.php:117`（`widgetParams['datagrid']`） | 3 |

洗い出しのコマンド（どの段階の後も、残りを確かめるのに使う）:

    grep -rn -i "datagrid\|DataSetPager\|getHTMLGrid\|MakeTable\|maketable\|->table->" manager assets install --include=*.php --include=*.tpl --include=*.js | grep -v "/tinymce"

DocumentParser（`manager/includes/document.parser.class.inc.php`）への影響:

- 段階 1: `loadExtension()` の `case 'maketable':` を消す（`loadExtension('MakeTable')` は `false` を返すようになる）。`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` は変えない。
- 段階 3: `tvProcessor()` 自体は変えない。`tvProcessor()` が include する `docvars/outputfilter/datagrid.inc.php` と、修飾子（`ex_modifiers.php`）の中身が変わる。どちらも `parseDocumentSource()` の中の、文書の変数・TV の置き換え（`mergeDocumentContent()`）と修飾子の処理で呼ばれる。`executeParser()`・`prepareResponse()`・`postProcess()` は変えない。
- キャッシュ: 段階 1・2 は管理画面の出力だけでページキャッシュに関わらない。段階 3 の TV の表はページキャッシュに入る。ページキャッシュの鍵は `genQsHash()`（`document.parser.class.inc.php:521`）がクエリの文字列から作るので、ページ送りの引数ごとに別のキャッシュになる（今の `dpgn{id}` と同じ。引数名が `{id}_page` に変わるだけ）。確認の前後に `php evo cache:clear` を流す。

テーマ: 管理画面のテーマは `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RevoStyle,RubberWhite,SakuraFlow,SoftSlate}/style.css` の 7 つ。表は `.grid`・`.gridHeader`・`.gridItem`・`.gridAltItem`、ページ送りは `#pagination`（`ul`・`li`・`a`・`span`、今のページは `li.currentPage a`）で書かれている。色の変数は `assets/docs/theme-variables.md`、縦の余白は `assets/docs/theme-spacing.md`。RevoStyle は凍結テーマで、見た目を変える整理の対象外（D7 の扱い）。

Issue の「古い作りの例」が解消される段階:

| 古い作り | 解消する段階 |
| --- | --- |
| 列の設定を文字列に詰める（`colTypes`・`colEscapes`） | 管理画面は段階 1（子リソース）・段階 2（DataGrid の各画面）で列の定義の配列に置き換える。サイト側の保存済みの設定の文字列は段階 3 で `DataTableSettings` が 1 か所で列の定義に読み替え、部品の中に文字列の設定は残らない |
| エスケープの既定が「しない」 | 段階 1 で既定が「する」の部品を入れ、各利用箇所は移した段階で既定のエスケープに乗る。段階 3 で `DataGrid` が消える |
| `style` 属性の既定・セルの `onclick` | 段階 1（子リソースのアイコン・タイトル・すべて選択）、段階 2（各一覧のアイコン・選択の `onclick`、DocManager の `style` 付きの表）、段階 3（`DataGrid` の既定の `style`・`DataSetPager` の `style` が部品ごと消える） |
| 全件を取ってからページを切り出す（SQL に `LIMIT` が無い） | 段階 2（ユーザー管理・Web ユーザー管理・モジュール・リソースセレクタを件数の問い合わせと `LIMIT`・`OFFSET` に替える）。段階 3 のサイト側は、値がテキストか利用者の書いた `@SELECT` で全件が手元にあるので、配列を切り出す |
| ページ送りの実装が 2 つ | 段階 1 で `DataTablePager` を入れて `MakeTable` の分を消し、段階 3 で `DataSetPager` を消す（`Paging` は D11） |
| グローバル変数 `$__DataGridCnt` | 段階 3（`DataGrid`・`DataSetPager` ごと消える）。新しい部品は id を引数で受け、グローバル変数を使わない |
| `DataGrid` が PHP の配列を受け取れない | 段階 1（`DataTable` は行の配列を受け取る） |

## Plan of Work

`DataTable` は「行の配列」と「列の定義」を受け取って描くだけにし、DB の結果やテキストを配列にするのは呼び出す側が行う。ページ送りは、呼び出し側が先に件数を数え、`DataTablePager` が決めた `offset()`・`limit()` で SQL に `LIMIT` を付けて、そのページの行だけを取る。並べ替えは、URL の `orderby` を列の定義の `sort` のキーの中から選び（ホワイトリスト）、呼び出し側が自分の SQL の列名に対応づける。ページ送りと並べ替えのリンクの URL は `DataTableLinks::url()` の 1 か所で作る。

段階 1 は、部品と、その最初の利用者（子リソースの一覧）と、テーマの約束（`.pagination`・`is-shrink`・`is-center`）と、部品の決まりの文書（`assets/docs/data-table.md`）をそろえる。ここで部品の形が固まるので、段階 2・3 は利用箇所を移すだけになる。段階 2 は管理画面の 7 画面（DocManager を含む）、段階 3 はサイト側の 3 つと古い部品の削除。段階ごとに 1 PR にし（D3）、PR は前の段階をマージしてから、マージ前なら前の段階のブランチを base にして積む。

部品の決まりは `assets/docs/data-table.md` を正本にする（列の定義のキー、エスケープの規則、テーマが持つ class、JS の書き方、URL の引数）。AGENTS.md のドキュメントマップに 1 行足す。

## Concrete Steps

作業はリポジトリのルート（作業ツリー）で行う。`DC` は `docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml` を表す（並走するときは `EVO_VALIDATE_NAME`・`EVO_BIND_IP` を先頭に付ける。`.claude/skills/adaptive-validation-runner/references/environment.md`）。コンテナの中のリポジトリは `/var/www/html`。

### 段階 1: DataTable を作り、子リソースの一覧を移して MakeTable を外す

ブランチ: `refactor/1087-data-table-stage1`（作成済み）

書き込むファイル（18 件）:

- 新規: `manager/includes/controls/data_table.class.php`、`manager/media/script/data-table.js`、`assets/docs/data-table.md`
- 変更: `manager/actions/document/resources_list.static.php`、`manager/includes/document.parser.class.inc.php`、`manager/includes/lang/english.inc.php`、`manager/includes/lang/japanese-utf8.inc.php`、`manager/media/style/AromaHop/style.css`、`manager/media/style/AuroraFlow/style.css`、`manager/media/style/RevoClassic/style.css`、`manager/media/style/RevoStyle/style.css`、`manager/media/style/RubberWhite/style.css`、`manager/media/style/SakuraFlow/style.css`、`manager/media/style/SoftSlate/style.css`、`assets/docs/theme-spacing.md`、`AGENTS.md`（ドキュメントマップに `data-table.md` の 1 行）
- 削除: `manager/includes/extenders/ex_maketable.php`、`manager/includes/extenders/maketable.class.php`
- 検証で広げた分（D19）: 新規 `manager/includes/controls/child_document_list_state.class.php`、変更 `manager/processors/document/publish_content.processor.php`・`unpublish_content.processor.php`・`delete_content.processor.php`・`undelete_content.processor.php`、`assets/docs/core-issues.md`（後続の候補の記述を data-table.md に合わせる）

1. `data_table.class.php` に `DataTable`・`DataTablePager`・`DataTableLinks` を書く（形は「Interfaces and Dependencies」）。PHP 7.4 で動く書き方にする（`match`・名前付き引数・nullsafe 演算子・`str_contains()`・union 型を使わない）。描く処理は `evo()`・`db()`・リクエスト・グローバル変数に触れない（D5）。出力の規則:
   - `<div class="data-table" data-table="{id}">` で包み、中に `<table class="{tableClass}">`。`caption` があれば `<caption>`。`showHeader` が真なら `<thead><tr><th scope="col" class="…">見出し</th>…</tr></thead>`。`<tbody>` に行。
   - セルの値: `format` があればその戻り値、無ければ `$row[$key]`（無い・null・配列・オブジェクトは空文字）。`html` が偽（既定）なら `hsc()` してから出す。見出しも `labelHtml` が偽なら `hsc()`。属性の値（class・`rowAttributes`・`attributes`）は常に `hsc()`。
   - 並べ替え: 列に `sort` があり `DataTableLinks` が渡されたら、見出しを `<a href="…">` にする。今の `orderby` がその列なら `th` に `aria-sort="ascending|descending"` を付け、リンクは向きを反転する。ほかの列のリンクは `asc`。並べ替えのリンクではページを 1 に戻す（`page` を付けない）。
   - 行が 0 件なら、`<tbody><tr><td colspan="{列の数}" class="data-table-empty">{emptyMessage}</td></tr></tbody>`（列が 1 つなら `colspan` を付けない）。
   - `style`・`width`・`align`・`bgcolor`・`on*` の属性は出さない。
2. `DataTablePager` は、`perPage <= 0` なら全件 1 ページ（`limit()` は 0）。`page()` は範囲外を 1 にする（D8）。`render()` は D7・D8 の形で、URL は `DataTableLinks::url(['page' => n])`。1 ページ目へのリンクも `page=1` を付ける（`MakeTable` と同じ）。
3. `DataTableLinks::url()` は、`baseUrl` に `?` が含まれていれば `&`、無ければ `?` でつなぎ、`params`（呼び出し側が渡す、残したい引数）→ 今の並べ替え（`orderby` が空でなければ `orderby`・`orderdir`）→ 上書きの順に重ねた配列を `http_build_query($q, '', '&')` にする。HTML に入れるときの `hsc()` は描く側が行う。
4. `data-table.js` を書く。`window.EvoDataTable` があれば何もしない。`document` の `change` を委譲で受け、`input[type=checkbox][data-select-all]` が変わったら、同じ `.data-table` の中の `input[type=checkbox][name="{data-select-all の値}"]` の `checked` をそろえる。
5. `resources_list.static.php` を移す:
   - 件数（今の `:47-52`）を数えたあと、`$perPage = (int) evo()->config('number_of_results') ?: 20;`、`$links = ChildDocumentListState::links($id);`（中で `DataTableLinks::fromRequest()` を `SORT_COLUMNS` のキーで呼ぶ。D19）、`$pager = new DataTablePager($numRecords, $perPage, $links->page());` を作り、本体の問い合わせ（`:72-88`）の `LIMIT` を `sprintf('%d,%d', $pager->offset(), $pager->limit())` にする。
   - 並べ替え（D10・D19）: `ChildDocumentListState::orderBy($links) ?? transformOrderBy(…)` を `ORDER BY` にする。`orderBy()` は並べ替え中なら `SORT_COLUMNS` で列名に直した `"{列} {ASC|DESC}, sc.id DESC"`、そうでなければ null を返す。
   - 右クリックのメニューの戻り先（D19）: `get_jscript()` は `ChildDocumentListState::returnQuery()` を `js_literal()` で埋め込み、4 つの処理は `ChildDocumentListState::returnUrl($pid)` で戻り先を作る。
   - 行（`:94-120`）は、今の各 `get*()` 関数が作る HTML を `html => true` の列で渡す。チェックボックスの列は `<input type="checkbox" name="batch[]" value="{id}">`（`class` は `is-shrink`）、ID の列はエスケープの既定のまま（`is-shrink`、`sort => 'id'`）、タイトルの列（`sort => 'pagetitle'`）、公開日（`sort => 'publishedon'`）、編集日（`sort => 'editedon'`）、状態。見出しのチェックボックスは `labelHtml => true` で `<input type="checkbox" data-select-all="batch[]" aria-label="{hsc(lang('select_all'))}">`。
   - `getIcon()`・`getTitle()` の `onclick`・`oncontextmenu` を消し、アイコンの `<img>` と タイトルの `<span>` に `data-row-menu="{id}"` を付ける（`id` は整数）。`$tpl`（`:95`）の `div.title`・`a` の構造は変えない。
   - `DataTable` の options は `['id' => 'children', 'tableClass' => 'grid', 'rowClasses' => ['gridItem', 'gridAltItem']]`。出力は `$nav . $table . $nav`（`$nav = $pager->render($links, ['label' => lang('pagination_label'), 'first' => lang('pagination_table_first'), 'last' => lang('pagination_table_last')])`）。
   - 画面のスクリプト（`:205-214` の `selectAll()` と、`get_jscript()` の中）: `selectAll()` を消し、`<script src="media/script/data-table.js"></script>` を読む。`tablesort.js` の読み込み（`:158`）を消す（Surprises）。`get_jscript()` に、`document.querySelector('[data-table="children"]')` があれば、その要素の `click` と `contextmenu` を受け、`e.target.closest('[data-row-menu]')` があれば `showContentMenu(Number(el.dataset.rowMenu), e)` を呼んで `preventDefault()` する処理を足す（D12）。
   - `evo()->loadExtension('MakeTable')`・`evo()->table->…` を消す。`include_once(MODX_CORE_PATH . 'controls/data_table.class.php');` を足す。
6. `document.parser.class.inc.php:256-259` の `case 'maketable':` を消し、`ex_maketable.php`・`maketable.class.php` を `git rm` する。
7. 言語に `pagination_label`・`select_all` を足す（D17）。足す前に `grep -n "pagination_label\|'select_all'\|\"select_all\"" manager/includes/lang/*.php` で同じキーが無いことを確かめる。
8. 7 つのテーマの `style.css`: `#pagination` を `.pagination` に、`#pagination li.currentPage a` を `.pagination a[aria-current="page"]` に置き換える（`form > :has(+ #pagination)` も `form > :has(+ .pagination)` に）。`.pagination:last-child` の特例は D7 のとおり要らなくなるので、消してコメントを直す。`.grid` の節に `.grid .is-shrink { width: 1%; white-space: nowrap; }` と `.grid .is-center { text-align: center; }` を足す。`assets/docs/theme-spacing.md` の `#pagination` の記述（57 行・96 行付近）を `.pagination` に合わせて直す。
9. `assets/docs/data-table.md` を書く: 目的、置き場所と include の仕方、列の定義と options のキー（「Interfaces and Dependencies」と同じ内容。こちらを正本にし、ExecPlan からは参照する）、エスケープの規則（既定はエスケープ、`html`・`labelHtml` を付けた列は呼び出し側が中の値をエスケープする）、URL の引数（D8）、テーマが持つ class（`.grid`・`.gridItem`・`.gridAltItem`・`.pagination`・`.is-shrink`・`.is-center`・`.data-table-empty`）、JS の書き方（D12）、ページ送りは件数→`DataTablePager`→`LIMIT` の順で取ること、`Paging`（`paginate.inc.php`）を新しく使わないこと。AGENTS.md のドキュメントマップに「`data-table.md` | 表の部品」を足す。
10. 単体の確認用のスクリプトを `temp/data_table_check.php`（コミットしない）に書き、`helpers.php` と `data_table.class.php` だけを読んで、下の「段階 1 の確認」の 1 の項目を `PASS`/`FAIL` で出す。

        DC exec -w /var/www/html app php temp/data_table_check.php
        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php temp/data_table_check.php

   期待: すべて `PASS`。

推奨コミット:

- `feat(controls): 行の配列と列の定義から表を描く DataTable を追加`（`data_table.class.php`・`data-table.js`・言語・テーマ・`data-table.md`・`theme-spacing.md`・AGENTS.md）
- `refactor(document): 子リソースの一覧を DataTable に移して MakeTable を外す`（`resources_list.static.php`・`document.parser.class.inc.php`・2 ファイルの削除）

### 段階 2: 管理画面の DataGrid の利用箇所を移す

ブランチ: `refactor/1087-data-table-stage2`（段階 1 のマージ後に main から。マージ前なら段階 1 のブランチから）

書き込むファイル（9 件。部品に足りない所が見つかったときだけ `data_table.class.php` を足して 10 件）:

- `manager/actions/permission/user_management.static.php`、`manager/actions/permission/web_user_management.static.php`、`manager/actions/element/modules.static.php`、`manager/actions/element/resource_selector.static.php`、`manager/actions/element/mutate_module.dynamic.php`、`manager/actions/element/mutate_module_resources.dynamic.php`、`assets/modules/docmanager/classes/dm_frontend.class.php`、`assets/modules/docmanager/templates/templates.tpl`、`assets/modules/docmanager/templates/main.tpl`
- （必要なときだけ）`manager/includes/controls/data_table.class.php`。足したら `assets/docs/data-table.md` も直す（そのときは 11 件）

共通の形: `include_once(MODX_CORE_PATH . 'controls/data_table.class.php');` → 件数を `db()->getValue(db()->select('COUNT(…)', $from, $where))` で数える → `DataTablePager` → 本体を `LIMIT` 付きで取って `db()->getRows()`（またはループ）で配列にする → `DataTable`。`listmode=1`（全件表示）のときは `perPage` を 0 にして `LIMIT` を付けない。

1. `user_management.static.php`（`:136-212`）: 列は ユーザー名（アイコンの `<a class="gridRowIcon" href="#" data-row-menu="{id}" title="…"><img …></a>` と、`<span class="{published|unpublished}"><a href="index.php?a=12&amp;id={id}" title="…">{hsc(username)}</a></span>` を `html => true` の `format` で作る）・氏名・メール・ロール・最終ログイン（今の `date:` と同じ `evo()->mb_strftime(evo()->toDateFormat(null, 'formatOnly') . ' %H:%M', …)`。空なら `-`）・ログイン回数・ブロック（`blocked` から `lang('yes')` か `-` を作る）。SQL の `IF(…'{$_lang[…]}'…)` の 3 つ（性別・ブロック・class）は `format` に移して SQL から消す（Surprises）。検索と権限の `WHERE` は今のまま。ページは D9 の画面状態で保つ（`$page = get_intvar('page') ?: (int) array_get($_PAGE, 'vs.page', 1)`、`op=reset` なら 1。`new DataTableLinks('index.php', $params, $page)` で作り、`$_PAGE['vs']['page'] = $pager->page()`）。URL に残す引数は `a=75` と、空でなければ `search`。右クリックのメニューは、表を包む要素へのリスナーで `showContentMenu()` を呼ぶ（D12、段階 1 と同じ形）。メニューを閉じる `document` の `click` リスナー（`:88`）は、段階 1 の子リソースの一覧と同じく 1 回だけ登録する形にする（2・3 の画面も同じ。Surprises）。
2. `web_user_management.static.php`（`:146-170`）: 1 と同じ形。列は アイコン（`is-shrink is-center`、`data-row-menu`）・ユーザー名（`a=88` へのリンク）・氏名・メール・性別（`is-center`。`gender` から `lang('user_male')`/`lang('user_female')`/`-`）・ブロック（`is-center`。`blocked=1` または `blockeduntil > 今` なら `lang('yes')`）。`a=99`。`$number_of_results`（`manager/index.php:53` の `extract()` から来る）は `(int) evo()->config('number_of_results') ?: 20` に替える。
3. `modules.static.php`（`:141-170`）: 列は アイコン（`is-shrink is-center`。`<a class="gridRowIcon" href="#" data-row-menu="{id}" title="…"><img src="{hsc(icon)}" width="32" height="32" alt=""></a>`。`icon` が空ならテーマのモジュールのアイコン）・名前（`a=108` へのリンク）・説明（`element_description_html()`、`html => true`）・ロック・無効（`is-center`、`lang('yes')` か `-`）。SQL の `IF(…'{$yes}'…)`・`IF(icon<>''…)` も `format` に移す。`a=106`、`search`。
4. `resource_selector.static.php`（`:203-220`）: 列は 名前（`<label><input type="checkbox|radio" name="id[]" value="{id}"> {hsc(name)}</label>`）・説明（要素なら `element_description_html()`、文書（`rt=doc`）なら既定のエスケープ。今の `colEscapes` の分け方と同じ）。`onclick='setCheckbox(this)'` を消し、フォーム `document.selector` の `change` を受けて `e.target.name === 'id[]'` なら `setCheckbox(e.target)` を呼ぶ。件数と `LIMIT`: `:42-80` の `$sql` を、`SELECT … FROM … WHERE …` の `FROM` 以降と列に分け、`COUNT(*)` と本体の 2 つにする（`ORDER BY` は本体だけ）。URL に残す引数は `a=84`・`rt`・`sm`・`cb`・空でなければ `search`・`listmode`（今のフォームの隠し項目と同じ）。この画面はシェルの外の別窓で、テーマの `style.css` だけを読むので、ページ送りは普通のリンクで動く。
5. `mutate_module.dynamic.php`（`:368-399`）: ページ送りなし（`DataTablePager` を使わない）。列は 名前・種類（どちらも既定のエスケープ）。`ORDER BY` が `$where` に入っている今の書き方は変えない。
6. `mutate_module_resources.dynamic.php`（`:212-238`）: ページ送りなし。列は 名前（`<label><input type="checkbox" name="depid[]" value="{id}"> {hsc(name)}</label>`）・種類。`colWidths = '200'` は捨てる。
7. DocManager（`dm_frontend.class.php`）: コンストラクタの `include(…datagrid.class.php)` を `include_once(MODX_CORE_PATH . 'controls/data_table.class.php')` に替える。3 つの表はページ送りなし。テンプレートの表は行の先頭に id 0 の行（名前は `DM_tpl_blank_template`（「テンプレート無し」）、説明は空）を入れ（D18）、`templates.tpl` の別の `<table>`（`:5-12`）を消す。TV の表のラジオは `onclick="loadTemplateVars(…)"` を消して `data-template-id="{id}"` にし、`main.tpl` のスクリプトで `Evo.ready()` のあとに `document.templatevariables` の `change` を受けて `loadTemplateVars(e.target.dataset.templateId)` を呼ぶ。列の幅（`5%,5%,40%,50%`）は捨て、ラジオと ID の列に `is-shrink` を付ける。テンプレート名・文書グループ名は既定のエスケープ、説明は `element_description_html()`（D18）。DocManager の CSS はテーマの `.grid` を使う（モジュールはシェルの中に出る）。

推奨コミット（画面のまとまりごと）:

- `refactor(users): ユーザー管理と Web ユーザー管理の一覧を DataTable に移す`
- `refactor(modules): モジュールの一覧・依存関係・リソースセレクタを DataTable に移す`
- `refactor(docmanager): DocManager の表を DataTable に移す`

### 段階 3: サイト側を載せ替え、DataGrid と DataSetPager を外す

ブランチ: `refactor/1087-data-table-stage3`（段階 2 と同じ決め方）

書き込むファイル（9 件）:

- 変更: `manager/includes/controls/data_table.class.php`（`DataTableSettings` を足す）、`manager/includes/docvars/outputfilter/datagrid.inc.php`、`manager/includes/extenders/ex_modifiers.php`、`manager/includes/extenders/dbapi/mysqli.inc.php`、`manager/actions/element/mutate_tmplvars.dynamic.php`、`assets/docs/data-table.md`、`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`（`datagrid.class.php` の言及を、消えたことに合わせて直す）
- 削除: `manager/includes/controls/datagrid.class.php`、`manager/includes/controls/datasetpager.class.php`

1. 先に main で、Surprises の 3 つ（`head` で表が壊れる、`||` の区切り、`@SELECT` で `flds` 無しのセルが空）を観測し、結果を Surprises に書く（下の「段階 3 の確認」の準備のデータを使う）。
2. `DataTableSettings::render($source, array $settings, string $id): string` を書く（D13〜D16）。`$source` は DB の結果か文字列。`$settings` のキー: `columns`（列名の文字列）・`fields`（`@SELECT` のときの列のキーの文字列）・`types`（型の文字列）・`delimiter`・`wrapper`・`encoding`・`detectHeader`（`'first line'` か それ以外）・`pageSize`・`pagerLocation`（`top`・`bottom`・`both`。先頭の語で読む）・`header`・`footer`・`tableClass`・`itemClass`・`altItemClass`・`headerClass`・`pagerClass`（既定 `pager`）・`emptyMessage`。流れ:
   - DB の結果なら `db()->getRow()` で配列にする。列のキーは `fields` があればそれ、無ければ 1 行目のキー。文字列なら D15 のとおりファイルを読み、行に分ける。`detectHeader` が `first line` なら 1 行目を列名にする。列名が無ければ `showHeader => false`。
   - 列の定義を作る。列名・型の文字列は `||` を含めば `||`、無ければ `,` で分ける（今と同じ）。型 `integer`→`number_format()`、`float:N`→`number_format(値, N|2)`、`currency:N`→`'$' . number_format(…)`、`date:形`→`evo()->mb_strftime(形 ?: '%A %d, %B %Y', 時刻)`（空なら `-`）、`boolean`→`&bull;` か空（`html => true`）、`description`→`element_description_html()`（`html => true`）、`template:文字列`→`strtr(文字列, ['[+value+]' => hsc(値), '[+列のキー+]' => hsc(その列の値) …])`（`html => true`）。それ以外はエスケープの既定。
   - ページ送り: `pageSize > 0` なら `DataTablePager(count($rows), pageSize, get_intvar("{$id}_page"))` で配列を切り出す。リンクは `DataTableLinks(evo()->makeUrl(evo()->documentIdentifier), <今の getv() から id と {id}_page を除いたもの>, …)`、引数名 `{id}_page`。管理画面のプレビューなどで `isFrontend()` が偽のときは `index.php` を基にする（今の `DataSetPager` と同じ分け方）。
   - 出力: `<div class="gridwrap">` の中に、`pagerLocation` に応じたページ送り（`class` は `pagerClass`。サイト側では管理画面の言語を読まないので、文言は `DataTablePager` の既定の記号）、表（`caption` に `header`、HTML のまま）、ページ送り、`<div class="gridfooter">{footer}</div>`。
3. `datagrid.inc.php` を、表示パラメータ（D1 の「残す」キー）を共通のキーに読み替えて `DataTableSettings::render($value, $settings, $id)` を返す形に書き直す（`$id` は `tvProcessor()` の `"tv{$name}"`）。捨てるキーは読まない。
4. `ex_modifiers.php:1355-1368`: `DataTableSettings::render(trim($value), ['delimiter' => <1 行目にタブがあれば "\t"、無ければ ','>, 'pageSize' => 0], 'mdf')` にする（D14）。
5. `mysqli.inc.php:1041-1103` の `getHTMLGrid()`: docblock を残すキーだけに直し、`$params` を `??` で読んで共通のキーに読み替え、`DataTableSettings::render($dsq, $settings, 'grid')` を返す（D16）。
6. `mutate_tmplvars.dynamic.php:117` の `widgetParams['datagrid']` を、D1 の「残す」項目だけにする: `&cdelim=Column Delimiter;list;%2C,tab,||,:: &cwrap=Column Wrapper;string;" &enc=Src Encode;list;utf-8,sjis-win,sjis,eucjp-win,euc-jp,jis,auto &detecthead=Detect Header;list;first line,none;first line &cols=Column Names;string &ctype=Column Types;string &psize=Page Size;int;100 &ploc=Pager Location;list;top,bottom,both;top &pclass=Pager Class;string &head=Header Text;string &foot=Footer Text;string &tblc=Grid Class;string &itmc=Item Class;string &aitmc=Alt Item Class;string &chdrc=Column Header Class;string &egmsg=Empty message;string;No records found`。
7. `datagrid.class.php`・`datasetpager.class.php` を `git rm` する。`Context and Orientation` の洗い出しのコマンドで、`docvars/outputfilter/datagrid.inc.php`・`mutate_tmplvars.dynamic.php` の `datagrid`（ウィジェット名）・`document.parser.class.inc.php` の `'datagrid'`（ウィジェット名）・`ex_modifiers.php` の `case 'datagrid'` 以外が出ないことを確かめる。
8. `assets/docs/data-table.md` に「サイト側の表（TV の出力ウィジェット・修飾子・`getHTMLGrid()`）」の節を足す: 読む設定と無視する設定（D1・D16）、エスケープ（D13）、ページ送りの引数名（D8）。`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md` の `datagrid.class.php` の言及（27・239・254 行付近）を、#1087 で消えたことに合わせて直す。

推奨コミット:

- `refactor(tv): TV の出力ウィジェットと修飾子の datagrid を DataTable で描く`
- `refactor(db): getHTMLGrid を DataTable で描き、DataGrid と DataSetPager を外す`

## Validation and Acceptance

検証は `adaptive-validation-runner` スキルに従い、Docker の検証環境（PHP 8.4・MariaDB 11）で行う。PHP 7.4 は構文と単体の確認スクリプトを `php:7.4-cli` で流して押さえる（README の動作要件。main は PHP 7.4 以上）。準備:

    DC up -d --build
    NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor
    NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/smoke.js

確認用の値は `E&<b>"q"'s</b>`（エスケープが要る文字をすべて含み、二重エスケープは `&amp;amp;` で見分けられる）。ページ送りを見やすくするため、`number_of_results` を 3 にする（確認の後で 20 に戻す）:

    DC exec app php evo db:query "UPDATE modx_system_settings SET setting_value='3' WHERE setting_name='number_of_results'"
    DC exec app php evo cache:clear

画面の HTML は Playwright（ヘッドレス）で管理者としてログインし、`page.request.get()` の応答本文で見る（管理画面には `Accept-Language` を付ける）。動き（クリック・シェルの遷移・戻る）は画面を操作して見る。すべての画面で、ページの JS のエラー（`page.on('pageerror')`）が 0 件であること。同じスクリプトを main と作業ブランチで流し、「確認 × main × 作業ブランチ」の表にして PR の確認方法に書く（ブランチを切り替えたら数秒待つ。OPcache）。

各段階で共通に確かめること:

- 変えた PHP について `docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l <file>` が `No syntax errors detected`。JS は `node --check <file>`。
- `DC exec app php .github/scripts/security-lint.php` が `0 violations`。
- `smoke.js` が全 PASS。コンテナのログ（`DC logs app`）とシステムログ（`DC exec app php evo log:tail --lines=50`）に PHP の警告・エラーが無い。
- 移した表の HTML に、`style=`・`width=`・`align=`・`bgcolor=`・` on`（`onclick` など）の属性が無い。`<thead>` があり（見出しのある表）、`th` に `scope="col"` がある。`id="pagination"` が無い。
- テーマ: 7 テーマそれぞれで移した画面のスクリーンショットを main と並べ、表の地の色・行の色分け・見出し・ページ送りのボタンの色と余白が同じ（`getComputedStyle()` で、ページ送りの `a` の `background`・`border`・`padding` と、`nav` の `margin-top`・`margin-bottom` が main の `#pagination` と同じ値）。テーマはユーザー設定かシステム設定の `manager_theme` を替えて `cache:clear`。

### 段階 1 の確認（子リソースの一覧）

準備: サンプルサイトにフォルダを 1 つ作り、子を 8 件作る（うち 1 件はタイトルを確認用の値に、1 件は未公開、1 件は `privatemgr=1` で文書グループに入れる）。空のフォルダも 1 つ作る。画面か `db:query` の `INSERT` で作り、`cache:clear` する。

| 確認 | 期待（作業ブランチ） | main との関係 |
| --- | --- | --- |
| 1. 単体（`temp/data_table_check.php`。8.4 と 7.4） | 既定でセルと見出しがエスケープされる。`html => true` の列だけそのまま出る。null・配列の値は空。0 件で `emptyMessage` が `colspan` 付きで出る。並べ替え中の列だけに `aria-sort`、リンクは向きが反転。`DataTablePager(10, 3, 99)->page()` が 1、`offset()` が 0、`pageCount()` が 4。総ページ 1 のとき `render()` が空。`baseUrl` に `?` があると `&` でつなぐ。出力に `style=` が無い | 新規 |
| 2. 1 ページ目（`a=120&id=<フォルダ>`） | 3 行。ページ送りが表の上と下に出て、1 が `aria-current="page"`。列と見出しの文言・行の中身（アイコン・状態のアイコン・タイトル・説明・公開日・編集日・状態）が main と同じ。確認用の値のタイトルはエスケープされて見える（`&amp;amp;` は無い） | 同じ（ページ送りの HTML の形だけ違う） |
| 3. 2 ページ目・最後・範囲外（`page=2`・`page=3`・`page=999`・`page=abc`） | それぞれ 4〜6 件目・7〜8 件目・1 ページ目・1 ページ目 | `page=999` は main では空の表（Surprises）。それ以外は同じ |
| 4. 並べ替え | 「タイトル」の見出しをクリックすると `orderby=pagetitle&orderdir=asc` で昇順、もう一度で降順。`th` の `aria-sort` が `ascending`→`descending`。並べ替えたままページ送りを押すと `orderby`・`orderdir` が残る。`orderby=foo`（キーに無い値）は今の既定の順 | 新規（D10） |
| 5. 空のフォルダ | `resources_in_container_no`（「サブリソースはありません。」）の `<p>` だけ（表とページ送りは出ない） | 同じ |
| 6. すべて選択・移動 | 見出しのチェックボックスで、そのページの行のチェックがすべて入り、外すとすべて外れる。2 件選んで「チェックしたものを移動」で移動の画面（`a=51`）へ進む | 同じ |
| 7. 右クリックのメニュー | アイコンのクリックとタイトルの右クリックで、その行の id のメニューが開き、「編集」で `a=27&id=<その id>` へシェルの中で移る。メニューの外のクリックで閉じる | 同じ |
| 8. シェルの遷移 | ページ送りと並べ替えのリンクがシェルの中で開き（全体の読み込みにならない）、アドレスバーの URL が変わる。ブラウザの「戻る」で前のページに戻る。別の画面へ移ってから戻り、すべて選択を押しても 1 回だけ切り替わる（リスナーが二重にならない） | 同じ（最後は新規） |
| 9. 権限 | `editor`（ロール 3）で開くと、`privatemgr=1` の子が main と同じ扱い（`tree_show_protected` が 0 なら出ない）。件数とページ数も main と同じ。`view_document` の無いロールでは main と同じエラー | 同じ |
| 10. 残り | 洗い出しのコマンドで `MakeTable`・`maketable`・`->table->` が出ない | — |

### 段階 2 の確認

準備: 管理ユーザーを 7 人（うち 1 人の氏名を確認用の値、1 人をブロック）、Web ユーザーを 7 人（同様）、モジュールを 4 つ以上（1 つの説明を `<strong>x</strong> E&<b>"q"'s</b>`）、テンプレートの名前と説明に確認用の値を入れる。`db:query` で作ってよい。

| 確認 | 期待（作業ブランチ） | main との関係 |
| --- | --- | --- |
| 1. ユーザー管理（a=75）の各ページ | 3 件ずつ。列・文言・並び（ブロックが後、最終ログインの新しい順）・日付の形が main と同じ。氏名の確認用の値はエスケープされる。本体の問い合わせに `LIMIT` が付いていることをコードで確かめ、件数とページ数が main と同じ | 同じ |
| 2. 検索・リセット・全件表示 | 検索でしぼった結果のページ送りに `search` が残る。リセットで 1 ページ目と全件に戻る。全件表示（一覧の切り替えのアイコン）でページ送りが消えて全件 | 同じ |
| 3. 前のページを覚える | 2 ページ目でユーザーを開いて戻る（「キャンセル」かメニューの「ユーザー管理」）と 2 ページ目 | 同じ（D9） |
| 4. 右クリックのメニュー | アイコンで開き、「編集」で `a=12&id=…`、「削除」で確認のダイアログ | 同じ |
| 5. Web ユーザー管理（a=99） | 1〜4 と同じ（編集は `a=88`）。ブロックは `blockeduntil` が未来のユーザーも「はい」 | 同じ |
| 6. モジュール（a=106） | 1〜4 と同じ。アイコンが中央。説明の `<strong>x</strong>` は太字、確認用の値は文字で見える。ロック・無効の列が中央 | 同じ |
| 7. リソースセレクタ（a=84、モジュールの依存関係の管理から開く） | 種類（スニペット・プラグイン）ごとに 3 件ずつのページ送り。ページを移ってもチェックが保たれ（`window.opener.chkBoxArray`）、「挿入」で選んだものが依存関係に加わる。検索・全件表示が main と同じ | 同じ |
| 8. モジュールの依存関係（a=108 のタブ・a=113） | 全件が出る。a=113 でチェックして「削除」すると外れる。0 件のとき `no_records_found`（「データがありません。」）の文言 | 同じ |
| 9. DocManager（モジュールの実行 a=112） | テンプレートの表の先頭が「空のテンプレート（id 0）」で、別の表は無い。テンプレート名と説明がエスケープされ（説明の `<strong>` は太字）、TV のタブでテンプレートを選ぶと TV の一覧が読み込まれる。文書グループの表が main と同じ件数 | テンプレート名・説明のエスケープと空のテンプレートの行の位置が違う（D18） |
| 10. 権限 | `editor` で a=75・a=99・a=106・a=84 を開くと main と同じエラー（権限なし） | 同じ |

### 段階 3 の確認（サイト側）

準備: テンプレート変数 `dgtest`（入力は textarea、出力のウィジェットは `datagrid`）を作り、サンプルのテンプレートに割り当てる。表示パラメータを 3 通りで保存して試す。

- A: 画面から `cdelim=,`・`detecthead=first line`・`psize=2`・`ploc=both`・`tblc=mytable`・`head=<b>見出し</b>`・`foot=末尾`・`ctype=||integer||date:%Y-%m-%d`
- B: 古い設定を `db:query` で直接入れる（`&tbls=color:red&itms=color:blue&cwidth=100&calign=right&ploc=top-right&psize=2&cdelim=%2C`）
- C: `cdelim=||`（区切り文字が 2 文字）

値は `名前,数,日付` の見出し行と 5 行（1 行の名前を確認用の値）。`@SELECT id,pagetitle FROM [+prefix+]site_content LIMIT 5` の値の TV `dgsql` も作る。修飾子は `[*dgtext:datagrid*]`（出力のウィジェットなしの TV `dgtext`）、`getHTMLGrid()` はスニペット `dgsnip`（`return db()->getHTMLGrid('SELECT id,pagetitle FROM ' . evo()->getFullTableName('site_content'), ['columns' => 'ID,Title', 'fields' => 'id,pagetitle', 'pageSize' => 2]);`）をページに置く。変えるたびに `cache:clear`。

| 確認 | 期待（作業ブランチ） | main との関係 |
| --- | --- | --- |
| 1. A のページ（`curl -s <ページの URL>`） | `<table class="mytable">` に `<caption><b>見出し</b></caption>`・`<thead>` の 3 列・2 行。数が桁区切り、日付が `%Y-%m-%d`。確認用の値はエスケープ。上下にページ送り、`?tvdgtest_page=2` で 3〜4 行目、`page=999` で 1 ページ目。末尾の `<div class="gridfooter">` | main は表が壊れる（Surprises）。エスケープ・ページの引数名が違う |
| 2. ページキャッシュ | 同じ URL を 2 回取って同じ本文。`?tvdgtest_page=2` は 1 ページ目と違う本文（別のキャッシュ） | 同じ（引数名だけ違う） |
| 3. B のページ | `style=`・`width=`・`align=` が出ない。ページ送りは表の上だけ | 見た目の指定が消える（D1） |
| 4. C のページ | `||` で列が分かれ、値の中の `|` 1 文字では分かれない | main は `|` 1 文字ずつで分かれる（Surprises） |
| 5. 空の値 | `egmsg` があれば `data-table-empty` のセルに文言、空なら何も出ない | 同じ |
| 6. `@SELECT`（`dgsql`） | 見出しが `id`・`pagetitle`、セルに値 | main はセルが空（Surprises） |
| 7. 修飾子 `:datagrid` | 見出しなし・ページ送りなしで全行。タブ区切りの値もタブで分かれる | 21 行以上でページ送りが出ない（D14） |
| 8. `getHTMLGrid()` | 見出し `ID`・`Title`、2 行ずつのページ送り。PHP の警告が出ない | main は無いキーで警告が出る |
| 9. ウィジェットの設定画面（a=301） | `datagrid` を選ぶと D1 の「残す」項目だけが出る。B の TV を開いて保存しても、捨てた項目は `display_params` に残るが読まれない（D1 のとおり DB は書き換えない。設定画面は保存済みのキーをそのまま書き戻す） | 項目が減る。保存の動きは同じ |
| 10. 残り | `datagrid.class.php`・`datasetpager.class.php` が無く、洗い出しのコマンドの結果が Concrete Steps 7 のものだけ | — |

完了の条件（ExecPlan 全体）: 3 段階の表のすべてが期待どおりで、各段階の共通の確認が通り、Purpose の DoD の 3 項目を満たす。

## Idempotence and Recovery

- 段階ごとにブランチと PR を分けるので、途中で止めても、マージ済みの段階はそのまま残る。再開するときは Progress と、各段階の推奨コミットの済んだ所から続ける。
- 段階 1 のマージ前に段階 2 を始めるときは、段階 1 のブランチを base にしたブランチを切り、段階 1 のマージ後に main へ rebase する（PR の base も main に替える）。
- 検証で変えた設定（`number_of_results`・`manager_theme`）は確認の後で元に戻し、`cache:clear` する。作ったテスト用のリソース・ユーザー・モジュール・TV・スニペットは、検証環境ごと片付けるか削除する（`adaptive-validation-runner` の「後片付け」）。
- `temp/data_table_check.php` はコミットしない（`temp/` に置く）。
- DB のスキーマも保存済みの値も変えないので、どの段階も PR を戻すだけで元に戻る。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1087
- ブランチ: 段階 1 `refactor/1087-data-table-stage1`（作成済み、PR はまだ無い）。段階 2・3 は `refactor/1087-data-table-stage2`・`refactor/1087-data-table-stage3` の予定（`gh issue develop` で Issue に紐づけず、前の PR のマージ後に `origin/main` から作る。紐づけると途中の段階の PR のマージで Issue が閉じるため）
- PR: 1 段階を 1 PR にする（D3）。段階 1: #1160。段階 2: #1198（base は段階 1 のブランチ。D20）。段階 3 の PR 番号は作成時にここへ書く
- 関連: `assets/docs/core-issues.md` の「表とページ送りの組み方が DataTable の外に残る」（D11 の後続の候補）、`.agent/plans/archive/2026-11-03-manager-output-escape.md`（D3: 要素の説明の扱い、D13: DataGrid の `description` 型）、`assets/docs/request-input.md`（型つきヘルパー）

## Interfaces and Dependencies

部品の形（段階 1 で決め、正本は `assets/docs/data-table.md`）:

    final class DataTable
        __construct(array $columns, array $options = [])
        render(array $rows, ?DataTableLinks $links = null): string

    列の定義（$columns の各要素、連想配列）:
        key        string   行の配列のキー（必須）
        label      string   見出し。既定はエスケープ
        labelHtml  bool     見出しを HTML のまま出す（既定 false）
        format     callable|null  function ($value, array $row): string
        html       bool     セルを HTML のまま出す（既定 false。中の値のエスケープは呼び出し側）
        class      string   th と td に付ける class（例 'is-shrink'、'is-center'）
        sort       string|null  並べ替えのキー（URL の orderby の値）。null なら並べ替えない

    $options:
        id            string    包む div の data-table の値（既定 ''）
        tableClass    string    既定 'grid'
        rowClasses    string[]  行に順に付ける class（既定 []）
        headerClass   string    th に足す class（既定 ''）
        caption       string    caption（既定なし）
        captionHtml   bool      caption を HTML のまま出す（既定 false）
        emptyMessage  string    0 件のときの文言（既定 ''、エスケープ）
        showHeader    bool      既定 true
        rowAttributes callable|null  function (array $row): array  属性名 => 値（値はエスケープ。class は rowClasses の値と 1 つの class 属性にまとめる。on* は出さない）

    final class DataTablePager
        __construct(int $total, int $perPage, int $requestedPage)
        page(): int        1..pageCount() に収めたページ
        pageCount(): int
        offset(): int
        limit(): int       0 は制限なし（perPage <= 0）
        render(DataTableLinks $links, array $options = []): string
            options: class（既定 'pagination'）, label（nav の aria-label、既定 ''）, first（既定 '«'）, last（既定 '»'）, prev（既定 '<'）, next（既定 '>'）, window（既定 9）
            文言はエスケープして出す

    final class DataTableLinks
        __construct(string $baseUrl, array $params = [], int $page = 1, string $orderby = '', string $orderdir = 'asc', string $pageParam = 'page')
        static fromRequest(string $baseUrl, array $params, array $sortKeys, string $pageParam = 'page'): self
            get_intvar($pageParam)・get_choicevar('orderby', $sortKeys, '')・get_choicevar('orderdir', ['asc', 'desc'], 'asc') で読む
        page(): int / orderby(): string / orderdir(): string
        url(array $overrides = []): string   エスケープ前の URL

    子リソースの一覧の状態（段階 1、D19。manager/includes/controls/child_document_list_state.class.php）:
    final class ChildDocumentListState
        SORT_COLUMNS                 並べ替えのキー => SQL の列名（許可リストはこのキー）
        static links(int $parent): DataTableLinks
        static orderBy(DataTableLinks $links): ?string   並べ替え中でなければ null
        static returnUrl(int $parent): string            処理（publish・unpublish・delete・undelete）の戻り先
        static returnQuery(): string                     一覧の JS が処理へ渡す &page=…&orderby=…&orderdir=…

    段階 3 で足す:
    final class DataTableSettings
        static render($source, array $settings, string $id): string   （キーは段階 3 の手順 2）

依存:

- `manager/includes/helpers.php`: `hsc()`（描く処理）、`get_intvar()`・`get_choicevar()`（`DataTableLinks::fromRequest()`）、`lang()`・`element_description_html()`・`evo()`・`db()`・`getv()`（呼び出し側と `DataTableSettings`）。
- テーマ: `.grid`・`.gridItem`・`.gridAltItem`・`.pagination`・`.is-shrink`・`.is-center` を 7 テーマが持つ（D6・D7）。外部のテーマは `data-table.md` の class の一覧を写す。
- シェル: ページ送り・並べ替えのリンクは `index.php?a=…` の相対の URL なので、`shell.js` のリンクの委譲でシェルの中の遷移になる。リソースセレクタ（a=84）はシェルの外。
- 右クリックのメニュー: 各画面の `showContentMenu(id, event)` と `controls/contextmenu.php` をそのまま使う（D12）。
- 外部のスニペット・プラグインが `$modx->table`・`DataGrid`・`DataSetPager` を直接使っている場合の互換は考えない（ロードマップの非対象）。`db()->getHTMLGrid()` は残る。
