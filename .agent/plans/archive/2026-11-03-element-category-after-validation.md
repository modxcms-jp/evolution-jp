# ExecPlan: 要素の保存で、新しいカテゴリを名前の確かめのあとに作る（Issue #1000）

## Purpose / Big Picture

要素（テンプレート・チャンク・スニペット・プラグイン・モジュール・テンプレート変数）の編集画面で「新しいカテゴリ」の名前を入れて保存したとき、名前の重複や予約名で差し戻されたら、カテゴリを DB に作らないようにする。差し戻した編集画面では、カテゴリの選択が「>> カテゴリー作成」のまま、入れた名前が見える欄に戻る。保存し直せば、そのときに 1 回だけカテゴリが作られる。あわせて、引用符などを含むカテゴリ名で、保存のたびに同じ名前のカテゴリが増える不具合も直す。

Issue: #1000

## Progress

- [x] M1（2026-11-03）: カテゴリを決める処理を `ManagerAPI::resolveElementCategory()` にまとめ、6 つの保存処理で名前の確かめのあとに呼ぶ。Docker で main と比べ、M1 の受け入れ表の全行（6 種類 × 85 件）、整数の形でない `categoryid`、名前 `"0"`（D2）、`10` と `1e1`（D4）、スニペットのイベントで処理を止めた場合を確かめた。自己レビューの低 3 件は対応済み（`to_intvar()`、スニペットの呼ぶ位置、D3 の互換の書き方）
- [x] M2（2026-11-03）: 差し戻した編集画面で「>> カテゴリー作成」と名前を戻し、カテゴリの選択肢と新しいカテゴリの欄の切り替えを共通にする。Docker で main と比べ、M2 の受け入れ表の全行（差し戻しの 13 画面、通常の画面 18 通り、プラグインの docblock、シェルの遷移）と、M1 の正常な保存 36 通りを確かめた。検証でテンプレート変数の画面だけ JS が効かない不具合（`form.elements` の上書き）を見つけて直し、自己レビューの中 1 件（docblock に `@modx_category` が無いとき入力中の名前が消える）・低 3 件も対応済み

## Surprises & Discoveries

計画の時点で、コードを読んでわかったこと（実装で裏を取る）。

- 引用符などを含む新しいカテゴリ名で、保存のたびに同じ名前のカテゴリが増える。テンプレート・スニペット・プラグイン・モジュール・テンプレート変数の保存処理は `manager()->checkCategory(db()->escape(postv('newcategory')))` で探すが、`checkCategory()` は DB から読んだ生の名前と PHP で比べる（SQL に埋め込まない）。そのため `O'Reilly` は `O\'Reilly` と比べられて見つからず、`newCategory()` が毎回新しい行を作る。チャンクだけは生の値を渡していて正しい。根拠: `manager/includes/extenders/ex_managerapi.php` の `checkCategory()`（`$row['category'] == $newCat`）と、各 processor のカテゴリの処理。
- `checkCategory()` の比べ方が `==`（緩い比較）で、`10` と `1e1` のように数として読める名前どうしが同じとみなされる（PHP 7.4 と 8 のどちらでも、両方が数の文字列なら数として比べる）。
- 名前が空のときは、どの保存処理も `requireElementName()` がカテゴリの処理より先に差し戻すので、カテゴリは作られない（Issue の「空」は今も起きない）。カテゴリが先に作られるのは、名前の重複（チャンクを除く 5 種類。チャンクは今も `$field` を確かめのあとで組むので作らない）と予約名（テンプレート変数）のとき。モジュールの更新（`a=108`）は名前の重複を確かめていないので、差し戻しは空のときだけ。プラグインは有効なときだけ重複を確かめる。
- 差し戻した画面で、新しいカテゴリの名前を戻すのはテンプレートとチャンクだけ（欄は `display:none` で隠れたまま）。プラグイン・スニペット・モジュール・テンプレート変数は `value=""` で、入れた名前が消える。選択は `category = -1` が戻るが、`-1` の選択肢に `selected` を付ける処理が無いので「カテゴリーなし」に見える。
- プラグインの画面は、コード欄の docblock の `@modx_category` から新しいカテゴリの欄へ名前を入れるが、欄は隠れたままで選択も「>> カテゴリー作成」にしない（見えない値が保存される）。
- 範囲外として残すもの（この ExecPlan では直さない）: `categories.category` は `varchar(45)` で、`maxlength` を迂回した長い名前は、DB の設定によって切り詰められる（毎回別のカテゴリになる）か挿入に失敗する（カテゴリなしになる）。`getCategories()` は名前に `stripslashes()` をかけるので、`\` を含む名前が選択肢で欠けて見える。モジュールの更新で名前の重複を確かめていない（PR #998 の備考）。テンプレートのロックの切り替えでカテゴリの `select` が無効になる件は #1001。

M1 の実装と検証でわかったこと（2026-11-03）。

- 保存を止める仕組みはイベントに無い。`stopPropagation()` は後続のプラグインを止めるだけで、保存処理は `invokeEvent()` の戻り値を見ない。プラグインが `exit` で止めた場合、main はスニペットなどでカテゴリだけが残るが、このブランチでは残らない。
- モジュールの新規保存が、まれに `Data too long for column 'guid'` で失敗する（main でも起きる。フォームの `createGUID()` が作る値の長さの問題とみられる）。この ExecPlan の範囲外。
- 保存処理は関数の外で読み込まれるので、`$category` などはグローバル変数になる（D3 の互換の注意）。

## Decision Log

- D1（2026-11-02、planner）カテゴリを決める処理は `ManagerAPI` に `resolveElementCategory($categoryId, $newCategory): int` として 1 か所に置き、6 つの保存処理（チャンクも）はこれを呼ぶだけにする。理由: 今は同じ分岐が 6 か所に写されていて、5 か所が引用符の扱いを誤っている（Surprises の 1 つ目）。チャンクの `entity('category')` の形にそろえるだけでは、分岐の写しが 6 つ残る（SSOT・DRY）。カテゴリの読み書き（`newCategory()`・`checkCategory()`・`getCategories()`）がすでに `ManagerAPI` にあり、要素の保存の補助（`requireElementName()`・`redirectToElementForm()`）も同じクラスにある。引数は呼び出し側から渡し、メソッドの中でリクエストを読まない（`requireElementName()` と同じ形。入力を変えて確かめやすい）。代替案: チャンクの形（processor ごとの `entity()`）にそろえる → 分岐の写しが残るので採らない。
- D2（2026-11-02、planner）`resolveElementCategory()` の動き: 新しいカテゴリ名を `normalizeElementName()` で前後の空白（全角スペースを含む）を落とし、空でなければ同じ名前のカテゴリを `checkCategory()` に生の名前で探し、無ければ `newCategory()` で作ってその id を返す。名前が空なら `categoryid` を `to_intvar()` で読んで返す（0 以下・整数の形でない値・配列は 0。main の `post_intvar('categoryid')` と同じ扱い。2026-11-03 の自己レビューで素の `(int)` から改めた）。新しいカテゴリ名が `categoryid` より優先する、という今の決まりは変えない（プラグインの docblock からの自動入力や、外から POST する作りが、`categoryid` を `-1` にしないで名前だけ送る可能性があるため）。前後の空白を落とすのは、要素の名前と同じ正規化に合わせ、` foo` と `foo` が別のカテゴリにならないようにするため。文字列でない値（配列で送られた等）は空とみなす（`normalizeElementName()` の決まり）。今は `"0"` を `empty()` で空とみなしているが、`"0"` も 1 つの名前として扱う（正規化のあとの空文字だけを空とする）。
- D3（2026-11-02、planner）各保存処理で `resolveElementCategory()` を呼ぶ位置は、その処理の差し戻し（名前の重複・予約名）の確かめをすべて通ったあと、DB への insert・update の直前にする。新規と更新で確かめの場所が違う処理（テンプレート・スニペット・プラグイン・モジュール・テンプレート変数）は、`switch` の各 `case` の中で呼ぶ（スニペットは計画では `switch` の前としていたが、イベントが処理を止めたときや `mode` が不正なときにカテゴリが残るので、2026-11-03 の自己レビューで `case` の中へ移した）。`OnBefore*FormSave` イベントを名前の確かめより先に出す今の順は変えない（イベントの意味が変わるため。Issue の範囲外）。これで、カテゴリはイベントのあとに作られる。互換の注意: 保存処理は関数の外で読み込まれるので、`$category`・`$field['category']` はグローバル変数になる。main ではイベントの時点で、テンプレートの `$categoryid`・`$field['category']`、スニペットの `$categoryid`、プラグイン・モジュール・テンプレート変数の `$category` が決まっていた（チャンクは main でもイベントのあとに決めていたので変わらない。テンプレートの `$categoryid` は変数ごと無くなる）が、このあとはイベントの時点では決まっていない。逆に、イベントの中で `$_POST['categoryid']`・`$_POST['newcategory']` を書き換えると保存に反映される。これらに頼るプラグインはまず無いと見て、PR の備考に書く。
- D4（2026-11-02、planner）`checkCategory()` の比べ方を `(string)` どうしの `===` にする。理由: 緩い比較では数として読める別の名前が同じとみなされる。比べ方を SQL の `WHERE` に移すことはしない（DB の照合順序によって大文字・小文字や末尾の空白を同じとみなすようになり、今の「PHP で厳密に比べる」動きが変わるため）。
- D5（2026-11-02、planner）プラグインとテンプレート変数の保存処理の `compact()` は、カテゴリの値を組み立て直すのに合わせて、同じキーの配列リテラルに書き直す。AGENTS.md が `compact()` を禁じていて、カテゴリの行を触るついでに直せるため（項目と値は変えない）。M1 の中で別のコミットにする。
- D6（2026-11-02、planner）security-lint（`.github/scripts/security-lint.php`）の `SAFE_FUNCS` に `resolveElementCategory` を足す。理由: 保存処理は `postv()` の値をこのメソッドに渡し、戻り値を `db()->insert()` の配列に入れる。戻り値は整数なので安全だが、一覧に無いと「リクエストの値を DB へ渡している」と検出される。`newCategory`・`checkCategory` が同じ理由で一覧に入っている（既存の作法にそろえる）。この 2 つは公開のメソッドとして残るので、一覧からは外さない。
- D7（2026-11-02、planner）差し戻した画面のカテゴリの選択肢は、`ManagerAPI` に `elementCategoryOptions($selectedId): string` を置いて 6 画面で使う。「カテゴリーなし」（0）・既存のカテゴリ・「>> カテゴリー作成」（-1）の `<option>` を返し、`$selectedId` と一致するものに `selected` を付ける（-1 のときは「>> カテゴリー作成」）。名前は出力の直前で `hsc()` を通す。理由: 今は 6 画面が同じ選択肢の組み立てを別々の書き方で持ち、「-1 を選ぶ」決まりを 6 か所に足すことになるため（SSOT・DRY）。表の行（`th`・`td`・幅の指定）は画面ごとに違うので、行ごとまとめることはしない（見た目を変えないため。KISS）。
- D8（2026-11-02、planner）新しいカテゴリの欄の表示・非表示は、共通の JS `manager/media/script/element-category.js`（`EvoElementCategory`）にまとめる。選択が `-1` なら欄を出し、それ以外なら隠して名前を空にする。この判定を、画面を開いたとき（差し戻しで `-1` が選ばれていれば欄が出る）と `change` のときの両方で行う。PHP 側は欄に `style="display:none;"` を付けたまま出し、出すかどうかは JS だけが決める（決まりを 1 か所に置く）。欄の行は `input[name="newcategory"]` の `closest('tr')` で見つけ、綴りの誤った `id="newcategry"` には頼らない（ID は外のプラグインが使っている可能性があるので、変えずに残す）。トップレベルは `var` で定義し、状態は呼ぶたびに作り直す（シェルの画面遷移で同じ window に読み直されるため。`assets/docs/jquery-migration.md` の決まり）。プラグインの docblock からの自動入力も、この JS を通して「>> カテゴリー作成」を選び、欄を出す。docblock に `@modx_category` が無いときは、カテゴリの選択にも欄にも触れない（2026-11-03 の自己レビューで追加。main はこの経路で欄の名前に触れておらず、触れると入力中の名前が消えるため）。フォームの中の欄は `form.elements` ではなく `form.querySelector('[name="…"]')` で探す（2026-11-03 の検証で追加。テンプレート変数の画面には `name="elements"` の欄があり、`form.elements` がその欄で上書きされるため）。
- D9（2026-11-02、planner）6 画面とも、新しいカテゴリの欄の値を差し戻しの値（`newcategory`）で戻す（出力の直前で `hsc()`）。チャンクの選択の値は `entity('categoryid', entity('category'))` にする（差し戻しとエディタの切り替えの POST では送った `categoryid`、ふだんは DB の `category`）。今の `postv('categoryid')` を画面の中で読む書き方はやめる。
- D10（2026-11-02、planner）PR は 1 つにし、M1 で下書きの PR を作って `Refs #1000`、M2 で `Closes #1000` にする。M1 だけでは差し戻した画面の見た目は直らないが、M1 だけの状態で後退するところは無い（今も差し戻した画面から保存し直すと、プラグインなどでは新しいカテゴリが失われる）。
- 要相談の点は無い。DB のスキーマは変えない。互換に関わるのは D2（`"0"` を名前として扱う・前後の空白を落とす）と D4（数として読める名前の比べ方）だけで、どちらも今の動きが誤っている場合に限られる。

## Outcomes & Retrospective

（2026-11-03）M1・M2 とも完了。要素の保存で、名前の重複・予約名で差し戻したときに新しいカテゴリが作られなくなり、差し戻した画面では「>> カテゴリー作成」と入れた名前がそのまま戻るようになった。カテゴリを決める処理（`resolveElementCategory()`）、選択肢（`elementCategoryOptions()`）、欄の切り替え（`element-category.js`）がそれぞれ 1 か所にまとまり、6 種類の要素で同じ動きになった。あわせて、引用符などを含む名前で同じカテゴリが増える不具合と、差し戻した画面から保存し直すとプラグイン・スニペット・モジュール・テンプレート変数で新しいカテゴリが失われる不具合も直った。

学び: 画面の JS で `form.elements` を使うと、フォームに `name="elements"` の欄がある画面（テンプレート変数）で上書きされる。共通の JS を複数の画面で使うときは、欄を名前で `querySelector` する。静的な確認とスタブでは見つからず、6 画面すべてを実際に操作する検証で見つかった。

残したもの（範囲外）: Surprises & Discoveries の「範囲外として残すもの」と、モジュールの新規保存がまれに `guid` の長さで失敗する件。

## Context and Orientation

用語:

- 要素: テンプレート・チャンク（HTML の断片）・スニペット（PHP のコード）・プラグイン・モジュール・テンプレート変数（TV。リソースに足す入力項目）の 6 種類。管理画面の「エレメント管理」で編集する。
- カテゴリ: 要素を分類する名前。`[+prefix+]categories`（列は `id`・`category`、`category` は `varchar(45)`）に入り、各要素の `category` 列が id を持つ。0 は「カテゴリーなし」。
- 新しいカテゴリ: 編集画面のカテゴリの選択で「>> カテゴリー作成」（値 `-1`）を選ぶと出る欄（`newcategory`）。保存のときに、この名前のカテゴリが無ければ作る。
- processor: フォームの送信を受けて DB に書く PHP（`manager/processors/`）。
- 差し戻し: 保存処理が名前の空・重複・予約名を見つけたとき、入力をセッションに残して編集画面へ戻すこと。`ManagerAPI::redirectToElementForm()`（`manager/includes/extenders/ex_managerapi.php`）が行い、編集画面は `restoreElementFormValues()` で入力を重ねて表示する。このとき `categoryid` は `category` に写され、`newcategory` もそのまま残る。
- シェル: 管理画面の画面遷移の仕組み。遷移のたびに画面の `<script>` を同じ window で読み直す。

対象のコード（行番号は 2026-11-02 の HEAD `863d1754c` のもの）:

| 種類 | 保存処理（アクション） | カテゴリの処理 | 差し戻しの確かめ | 編集画面 |
|---|---|---|---|---|
| テンプレート | `manager/processors/template/save_template.processor.php`（新規 19・更新 16、送信 20） | 24-36 行、`$field['category']` 43 行 | 重複 57-66（新規）・103-112（更新） | `manager/actions/element/mutate_templates.dynamic.php`（選択 170-186、欄 189-199、change 361-373、値は `template()`） |
| チャンク | `manager/processors/chunk/save_htmlsnippet.processor.php`（77・78、送信 79） | `entity('category')` 58-71 行 | 重複 87-100・143-150 | `mutate_htmlsnippet.dynamic.php`（選択 306-317、欄 320-327、change 403-415、値は `entity()`） |
| スニペット | `manager/processors/snippet/save_snippet.processor.php`（23・22、送信 24） | 41-53 行 | 重複 56-70（`switch` の前で新規・更新とも） | `mutate_snippet.dynamic.php`（選択 200-211、欄 214-224、change 374-386、`entity()`） |
| プラグイン | `manager/processors/plugin/save_plugin.processor.php`（101・102、送信 103） | 34-46 行、`compact()` 73・125 行 | 重複（有効なときだけ）58-70・111-122 | `mutate_plugin.dynamic.php`（選択 551-567、欄 570-580、change 96-108、docblock の自動入力 156-163・281-297、`entity()`） |
| モジュール | `manager/processors/module/save_module.processor.php`（107・108、送信 109） | 29-42 行 | 重複 54-63（新規だけ） | `mutate_module.dynamic.php`（選択 273-284、欄 287-294、change 83-95、値は `content()`） |
| TV | `manager/processors/tmplvars/save_tmplvars.processor.php`（300・301、送信 302） | 27-39 行、`compact()` 70・134 行 | 重複・予約名 52-67・116-131 | `mutate_tmplvars.dynamic.php`（選択 590-601、欄 604-610、change 753-765、`entity()`） |

カテゴリの読み書きは `manager/includes/extenders/ex_managerapi.php` の `newCategory()`（410 行。中で `db()->escape()` してから insert する。呼び出し側は生の名前を渡す）、`checkCategory()`（424 行。全カテゴリを読み、PHP で名前を比べる。生の名前を渡すのが正しい）、`getCategories()`（446 行）。

DocumentParser（`manager/includes/document.parser.class.inc.php`）の `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` には影響しない（管理画面の保存と表示だけの変更）。キャッシュは、保存処理が今までどおり `clearCache()` するので、扱いを変えない。

## Plan of Work

M1 では、カテゴリを決める処理を `ManagerAPI::resolveElementCategory()` にまとめ（D1・D2）、6 つの保存処理の、差し戻しの確かめをすべて通ったあとで呼ぶ（D3）。チャンクは今も正しい順なので、`entity('category')` の中身をこの呼び出しに置き換えるだけにする。`checkCategory()` は厳密な比較にする（D4）。security-lint の一覧に新しいメソッドを足す（D6）。プラグインと TV の `compact()` を配列に書き直す（D5、別コミット）。M1 が終わると、差し戻しでカテゴリが作られなくなり、引用符を含む名前で同じカテゴリが増えなくなる。画面の見た目はまだ変わらない。

M2 では、差し戻した画面で「>> カテゴリー作成」と入れた名前が見えるようにする。選択肢は `ManagerAPI::elementCategoryOptions()` に（D7）、欄の表示の切り替えは `manager/media/script/element-category.js` にまとめ（D8）、6 画面の写しを置き換える。欄の値を差し戻しの値で戻す（D9）。プラグインの docblock からの自動入力も、この JS を通す。

## Concrete Steps

作業ディレクトリは `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-1000`（ブランチ `fix/1000-element-category-after-validation`）。PHP の構文の確かめには `php:7.4-cli` の公式イメージを使う（README の動作要件の下限。AGENTS.md の「検証環境」）。

### M1: カテゴリを決める処理をまとめ、名前の確かめのあとで呼ぶ

書き込むファイル:

- `manager/includes/extenders/ex_managerapi.php`
- `manager/processors/template/save_template.processor.php`
- `manager/processors/chunk/save_htmlsnippet.processor.php`
- `manager/processors/snippet/save_snippet.processor.php`
- `manager/processors/plugin/save_plugin.processor.php`
- `manager/processors/module/save_module.processor.php`
- `manager/processors/tmplvars/save_tmplvars.processor.php`
- `.github/scripts/security-lint.php`

手順:

1. `ex_managerapi.php` の `checkCategory()` のあとに `resolveElementCategory($categoryId, $newCategory): int` を足す。DocBlock に「要素の保存で使うカテゴリの id を返す。新しいカテゴリ名が空でなければ、同じ名前を探し、無ければ作る。名前の確かめ（空・重複・予約名）をすべて通ったあと、insert・update の直前に呼ぶ（差し戻しでカテゴリを残さないため）」と書く。中身は D2 のとおり（`normalizeElementName()` → `checkCategory()` に生の名前 → 無ければ `newCategory()`、戻り値は `(int)`。名前が空なら `to_intvar($categoryId)`）。
2. 同じファイルの `checkCategory()` の比較を `(string)$row['category'] === (string)$newCat` にする（D4）。
3. 各保存処理から、今のカテゴリの分岐（`//Kyle Jaebker - added category support` の塊、チャンクは `entity()` の `category` の分岐）を消し、次の位置で `manager()->resolveElementCategory(postv('categoryid'), postv('newcategory'))` を呼ぶ。
   - テンプレート: `switch` の前の `$field['category'] = $categoryid;` を消し、`case '19'` と `case '16'` の重複の確かめのあと、`db()->insert()`・`db()->update()` の前で `$field['category'] = …` を入れる。
   - チャンク: `entity()` の `if ($key === 'category')` の中身を、この呼び出しを返すだけにする（`$field` は確かめのあとで組んでいるので、位置は変えない）。
   - スニペット: 重複の確かめ（`switch` の前）のあと、`case '23'`・`case '22'` の中の `OnBeforeSnipFormSave` のあと、`$field` を組む前で `$categoryid = …` とする（D3）。
   - プラグイン: 各 `case` の `if ($disabled == '0') { … }` のあと、`$f` を組む前で `$category = …` とする。
   - モジュール: `case '107'` は重複の確かめのあと、`case '108'` は `$f` を組む前で `$category = …` とする。
   - TV: 各 `case` の予約名の確かめのあと、`$field` を組む前で `$category = …` とする。
4. `.github/scripts/security-lint.php` の `SAFE_FUNCS` の `'newCategory', 'checkCategory',` のあとに `'resolveElementCategory',` を足す。
5. コミット（推奨）: `fix(manager): 要素の保存で新しいカテゴリを名前の確かめのあとに作る`（手順 1〜4）。
6. プラグインの `compact('name', 'description', 'plugincode', 'disabled', 'moduleguid', 'locked', 'properties', 'category')`（2 か所）と、TV の `compact(explode(',', 'name,description,caption,type,elements,default_text,display,display_params,rank,locked,category'))`（2 か所）を、同じキー・同じ変数の配列リテラルに書き直す（D5）。
7. コミット（推奨）: `refactor(manager): プラグインと TV の保存の項目を compact() から配列に書き直す`。

確かめるコマンドと期待する結果:

    docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c 'for f in manager/includes/extenders/ex_managerapi.php manager/processors/*/save_*.processor.php .github/scripts/security-lint.php; do php -l "$f"; done'
    → すべて "No syntax errors detected"
    docker run --rm -v "$PWD":/app -w /app php:7.4-cli php .github/scripts/security-lint.php
    → "0 violations"
    grep -rn "checkCategory(db()->escape" manager/processors
    → 何も出ない
    grep -n "compact(" manager/processors/plugin/save_plugin.processor.php manager/processors/tmplvars/save_tmplvars.processor.php
    → 何も出ない

画面と DB での確かめは「Validation and Acceptance」の M1 の表を、main と比べて行う。

### M2: 差し戻した画面で「>> カテゴリー作成」と名前を戻す

書き込むファイル:

- `manager/includes/extenders/ex_managerapi.php`
- `manager/media/script/element-category.js`（新規）
- `manager/actions/element/mutate_templates.dynamic.php`
- `manager/actions/element/mutate_htmlsnippet.dynamic.php`
- `manager/actions/element/mutate_snippet.dynamic.php`
- `manager/actions/element/mutate_plugin.dynamic.php`
- `manager/actions/element/mutate_module.dynamic.php`
- `manager/actions/element/mutate_tmplvars.dynamic.php`

手順:

1. `ex_managerapi.php` の `getCategories()` のあとに `elementCategoryOptions($selectedId): string` を足す（D7）。`<option value="0">`（`lang('no_category')`）、`getCategories()` の各カテゴリ、`<option value="-1">&gt;&gt; ` + `lang('new_category')` の順に組み、`(int)$selectedId` と値が一致するものに ` selected="selected"` を付ける。id は `(int)`、名前と文言は `hsc()` を出力の直前で通す。
2. `manager/media/script/element-category.js` を作る。先頭のコメントに用途と使い方を書き、`var EvoElementCategory = (function () { … })();` で次の 3 つを出す（D8）。
   - `init(form)`: `form.querySelector('[name="categoryid"]')` の `change` で `sync(form)` を呼ぶようにし、すぐに 1 回 `sync(form)` する（欄は `form.elements` ではなく名前で探す。D8）。
   - `sync(form)`: 選択が `-1` なら `newcategory` の欄の行（`closest('tr')`）を出し、それ以外なら隠して欄を空にする。
   - `useNew(form, name)`: 選択を `-1` にし、欄に `name` を入れて `sync(form)` する。
   要素が無いときは何もしない（素の JS では `null` への操作が例外になる）。
3. 6 つの編集画面で、次を行う。
   - カテゴリの `<select>` の中身（`<option value="0">` から `<option value="-1">` まで）を `<?= manager()->elementCategoryOptions(<値>) ?>` に置き換える。`<値>` は、テンプレート `template('category')`、チャンク `entity('categoryid', entity('category'))`（D9）、スニペット・プラグイン・TV `entity('category')`、モジュール `content('category')`。
   - 新しいカテゴリの欄の `value` を `<?= hsc(<アクセサ>('newcategory', '')) ?>` にする（テンプレート・チャンクはすでにこの形。残り 4 画面を直す）。行の `style="display:none;"` と `id="newcategry"` は残す。
   - 画面の `<script src="media/script/element-properties.js?…">` と同じ書き方（`filemtime()` を付ける）で `element-category.js` を読み込み、今の `select[name="categoryid"]` の `change` の処理を消して、同じ場所で `EvoElementCategory.init(document.forms['mutate'])` を呼ぶ。
   - プラグインの docblock の自動入力を次の形にする。`src == ''` の分岐では「カテゴリーなし」にして `EvoElementCategory.sync(form)`。`@modx_category` があれば、既存と一致したときはそれを選んで `sync(form)`、一致しなければ `EvoElementCategory.useNew(form, modx_category)`。`@modx_category` が無ければ選択にも欄にも触れない（D8）。
   - 各画面の、使わなくなった `$ds = manager()->getCategories()` の行と、ローカルの `selected()` が使われなくなったらその関数も消す（ほかで使っていれば残す）。
4. コミット（推奨）: 2 つに分けるなら `refactor(manager): 要素の編集画面のカテゴリの選択肢と新しいカテゴリの欄の切り替えを共通にする`（選択肢のメソッドと JS、6 画面の置き換え）と `fix(manager): 差し戻した要素の画面で新しいカテゴリの選択と名前を戻す`（欄の値の復元）。分けにくければ後者 1 つにまとめる。

確かめるコマンドと期待する結果:

    docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c 'for f in manager/includes/extenders/ex_managerapi.php manager/actions/element/mutate_*.dynamic.php; do php -l "$f"; done'
    → すべて "No syntax errors detected"
    docker run --rm -v "$PWD":/app -w /app php:7.4-cli php .github/scripts/security-lint.php
    → "0 violations"
    grep -rn "getElementById('newcategry')" manager/actions/element
    → 何も出ない（change の写しが残っていない）
    grep -rln "element-category.js" manager/actions/element | wc -l
    → 6

## Validation and Acceptance

検証は adaptive-validation-runner（`.claude/skills/adaptive-validation-runner/SKILL.md`）の Docker の検証環境で行う。修正前は `origin/main` の別のワークツリー（または `git archive` の展開。`.claude/skills/exec-plan-runner/SKILL.md` の「作業場所」）を、別の `EVO_VALIDATE_NAME`・`EVO_BIND_IP` で立て、同じ初期 DB・同じ手順の Playwright（ヘッドレス）で比べる。並走中のセッション（#895）の環境と名前・アドレスが重ならないことを、起動の前に `references/environment.md` の「並走するとき」で確かめる。保存は画面のフォームを `requestSubmit()` で送る（CSRF のトークンを含めるため）。DB は `docker exec -w /var/www/html <名前>-app php evo db:query "SELECT id,category FROM modx_categories ORDER BY id"` などで見る。

前準備: 6 種類の要素それぞれに、名前 `dup-<種類>` の要素を 1 つずつ作っておく（重複の相手）。カテゴリ名には、ふつうの名前 `cat-<種類>-<条件>` と、引用符などを含む名前 `O'Reilly "q" <b>&\x`（45 文字以内）を使う。

### M1 の受け入れ（DB で見る）

各行の「カテゴリの行数」は、保存の前後での `modx_categories` の行数の増え方。

| 条件 | 対象 | main | このブランチ |
|---|---|---|---|
| 新規で名前を `dup-<種類>` にし、新しいカテゴリを入れて保存 | 6 種類 | 差し戻し・カテゴリ +1（チャンクだけ +0。今も確かめのあとで作るため） | 差し戻し・カテゴリ +0 |
| 更新で名前を別の要素の名前にし、新しいカテゴリを入れて保存 | テンプレート・チャンク・スニペット・プラグイン（有効）・TV | 差し戻し・+1（チャンクだけ +0） | 差し戻し・+0 |
| TV の名前を予約名（例 `id`）にし、新しいカテゴリを入れて保存（新規・更新） | TV | 差し戻し・+1 | 差し戻し・+0 |
| 名前を空（全角スペースだけを含む）にし、新しいカテゴリを入れて保存（新規・更新） | 6 種類 | 差し戻し・+0 | 差し戻し・+0（変わらない） |
| 正しい名前と新しいカテゴリで保存（新規・更新） | 6 種類 | 保存・+1・要素の `category` が新しい id | 同じ |
| 既存のカテゴリを選んで保存、カテゴリなしで保存 | 6 種類 | 保存・+0・`category` が選んだ id / 0 | 同じ |
| 引用符などを含む名前で、2 つの要素を続けて保存 | 6 種類 | テンプレート・スニペット・プラグイン・モジュール・TV は 2 回とも +1（同じ名前の行が 2 つ）。チャンクは 1 回目だけ +1 | どれも 1 回目だけ +1、2 つめの要素も同じ id。DB の名前は入れたとおり（`\` が足されていない） |
| 名前の前後に空白（半角・全角）を付けて既存のカテゴリ名を入れて保存 | 1 種類で可 | +1（空白付きの別のカテゴリ） | +0・既存の id |
| 無効のプラグインを、有効なプラグインと同じ名前で、新しいカテゴリを入れて保存 | プラグイン | 保存・+1 | 同じ（無効なら重複を確かめない今の決まりのまま） |

### M2 の受け入れ（画面で見る）

| 条件 | main | このブランチ |
|---|---|---|
| M1 の表の差し戻し（6 種類の新規、5 種類の更新、TV の予約名）のあとの編集画面 | 選択は「カテゴリーなし」に見え、新しいカテゴリの欄は隠れている（テンプレート・チャンクは値が入ったまま、ほかは空） | 選択は「>> カテゴリー作成」、欄が見え、入れた名前が入っている（引用符などを含む名前も、`&amp;` や `\` が足されずにそのまま） |
| 差し戻した画面で名前だけを直して保存 | プラグイン・スニペット・モジュール・TV は新しいカテゴリが失われる | 保存され、カテゴリが +1、要素の `category` がその id |
| 差し戻した画面で既存のカテゴリを選び直す | — | 欄が隠れて空になり、保存すると選んだ id・カテゴリ +0 |
| 通常の編集画面で「>> カテゴリー作成」を選ぶ / ほかを選ぶ | 欄が出る / 隠れて空になる | 同じ |
| 通常の編集画面を開いたとき（新規・既存のカテゴリ付き・カテゴリなし） | 選択が DB の値、欄は隠れている | 同じ |
| プラグインのコード欄に `@modx_category` が既存の名前の docblock を貼る / 無い名前の docblock を貼る / 空にする | 既存は選ばれる / 無い名前は欄が隠れたまま値だけ入る / 選択が外れる | 既存は選ばれて欄が隠れる / 「>> カテゴリー作成」が選ばれ欄に名前が見える / 「カテゴリーなし」で欄が隠れて空 |
| プラグインで「>> カテゴリー作成」と名前を入れた画面（通常・差し戻し）で、`@modx_category` の無い docblock があるコード欄から離れる | 名前が残り、保存するとその名前のカテゴリ | 同じ（選択と欄に触れない） |
| シェルでチャンク → プラグイン → チャンクの編集画面へ移り、カテゴリの選択を切り替える | — | 切り替えが効き、`pageerror` とコンソールのエラーが 0 件 |
| 選択肢の並びと文字 | — | main と同じ（「カテゴリーなし」、既存のカテゴリの名前順、「>> カテゴリー作成」）。名前の `<`・`&`・`"`・`'` がそのまま文字として見える（`\` は `getCategories()` の `stripslashes()` で欠ける。main と同じで範囲外） |

完了条件: 上の 2 つの表で「このブランチ」の列がすべてそのとおりになり、M1・M2 の「確かめるコマンド」の期待どおりで、変えた PHP がすべて `php:7.4-cli` の `php -l` を通ること。

## Idempotence and Recovery

どの手順も、同じ内容で書き直すだけなので、繰り返しても結果は変わらない。中断したら、ブランチ `fix/1000-element-category-after-validation` の最後のコミットと Progress を見て、未完了のマイルストーンの手順 1 から読み直す（済んだ手順は差分で確かめて飛ばす）。M1 の途中で止まったときは、`grep -rn "Kyle Jaebker - added category support" manager/processors` で、まだ置き換えていない保存処理がわかる。M2 の途中は `grep -rln "element-category.js" manager/actions/element` で、置き換え済みの画面がわかる。検証環境は `EVO_VALIDATE_NAME=<名前> EVO_BIND_IP=<アドレス> docker compose … down -v --rmi local` で片付け、作り直せる（DB はインストールからやり直す）。

## Artifacts and Notes

- Issue: #1000（関連: #976・PR #998 の差し戻しの整理、#1001 テンプレートのロックでカテゴリの選択が送られない件、#895 の M4・M4b で同じ編集画面が変わった）
- ブランチ: `fix/1000-element-category-after-validation`（`gh issue develop` で #1000 に紐づけ済み）
- ワークツリー: `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-1000`（作成時の HEAD `863d1754c`）
- PR: #1028（下書き。D10。M1 で作成して `Refs #1000`、M2 で `Closes #1000` に改める）
- 想定するコミット: M1 は 2 つ（`fix(manager): 要素の保存で新しいカテゴリを名前の確かめのあとに作る`、`refactor(manager): プラグインと TV の保存の項目を compact() から配列に書き直す`）。M2 は 1〜2 つ（Concrete Steps の M2 手順 4）。ExecPlan の記録はメインがコミットする（`docs(plans): …`）。

## Interfaces and Dependencies

新しく足すもの:

- `ManagerAPI::resolveElementCategory($categoryId, $newCategory): int` — `$categoryId` は送られた `categoryid`（文字列でよい）、`$newCategory` は送られた `newcategory`（生の値。エスケープしない）。新しいカテゴリを作ることがある。名前の確かめのあとに呼ぶ。
- `ManagerAPI::elementCategoryOptions($selectedId): string` — カテゴリの `<select>` の `<option>` 一式（エスケープ済みの HTML）。`$selectedId` が -1 なら「>> カテゴリー作成」を選ぶ。
- `manager/media/script/element-category.js` の `EvoElementCategory.init(form)`・`sync(form)`・`useNew(form, name)` — `form` は `name="categoryid"` の `select` と `name="newcategory"` の `input` を持つフォーム（6 画面とも `document.forms['mutate']`）。

変えるもの: `ManagerAPI::checkCategory()` の比べ方を厳密にする（D4）。`newCategory()`・`checkCategory()`・`getCategories()` の引数と戻り値は変えない。

イベント: `OnBefore*FormSave`・`On*FormSave`・`OnTempFormRender` などの出す位置と渡す値は変えない。カテゴリが作られるのは `OnBefore*FormSave` のあとになる（D3）。

並走との関係: #895 の M5〜M7 は `manager/actions/permission/`・`files.dynamic.php`・`logging.static.php` だけを変えるので重ならない。#1001 は `mutate_templates.dynamic.php` のロックの切り替えを直すので、#1000 の M2 と同じファイルに触れる。先にマージした側に合わせて、あとの側が取り込み直す。
