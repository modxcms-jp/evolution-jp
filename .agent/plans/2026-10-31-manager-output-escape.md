# ExecPlan: 管理画面で保存された値を出力直前でエスケープする（Issue #895）

## Purpose / Big Picture

管理画面に出す「保存された値」（設定値、ロール名・グループ名、要素の名前や説明、リソースのタイトル、ユーザー名、ファイル名など）を、出力する文脈に合ったヘルパー（HTML は `hsc()`、JS は `js_literal()`）に出力の直前で通す。AGENTS.md の「出力のエスケープ」にそろえる。これで、`&`・`<`・`"`・`'` を含む値を保存しても画面が崩れず、編集画面を開いて何も変えずに保存し直しても値が変わらなくなる。あわせて、値をエスケープしない HTML 組み立てヘルパー（`html_tag()`、`form_text_tag()`）の扱いを一つに決める。

Issue: #895

## Progress

- [ ] (2026-10-31) M0: Decision Log の「要確認」（D1・D3・D4）をユーザーに確かめて確定する
- [ ] M1: 設定画面とヘルパーの方針（`html_tag()` の説明、`form_text_tag()`・`Form::text()` の廃止）
- [ ] M2: 管理画面の枠（ログイン・ロックアウト・ダッシュボード・メニュー・ツリー）とサイト名
- [ ] M3: リソースの編集・情報・子リソース一覧
- [ ] M4: 要素（一覧・編集画面・テンプレート変数）
- [ ] M5: ユーザー・ロール・権限
- [ ] M6: ファイル管理・レポート・ログ
- [ ] M7: 横断の確認（main との比較、PHP 7.4 の構文確認、security-lint）

## Surprises & Discoveries

- 2026-10-31: Issue の対象のうち次は、今の main ですでに出力直前のエスケープになっている。M1〜M6 では触らず、二重エスケープを足さないよう確認だけする。`manager/actions/tool/export_site.static.php`（セッションから戻す値はすべて `hsc()`）、要素の編集画面のカテゴリー名（`mutate_snippet.dynamic.php:362`、`mutate_plugin.dynamic.php:717`、`mutate_tmplvars.dynamic.php:562`、`mutate_templates.dynamic.php:181`、`mutate_htmlsnippet.dynamic.php:313`）、プラグイン・スニペットの「パラメーターの取り込み」のモジュール名（`mutate_plugin.dynamic.php:575`、`mutate_snippet.dynamic.php:446`）、要素の名前（各編集画面の `name` 欄）、リソースの編集画面のタイトル・説明などの入力欄（`mutate_content/fields.php`）。
- 2026-10-31: #895 の洗い出しで追加で見つかった箇所のうち、次は今の main で対処済みだった（確認だけ）。リソースツリーのタイトルと URL（`manager/frames/nodes.php:190, 193`。`hsc(js_literal())`）、ツリーから渡るタイトルの受け取り（`move_document.dynamic.php:95-104`、`mutate_content` の `setParent()`（`manager/media/style/common/jscripts.tpl:202-215`）、`import_site.static.php:16-20`。いずれも `textContent`）、移動画面の一覧（`move_document.dynamic.php:83`）、ダッシュボードの最近編集したリソースとユーザー名（`manager/media/style/common/welcome.php:149, 197-199`）、システム情報（`sysinfo.static.php:234-235`）、リソース情報の作成者・編集者（`document_data.static.php:294, 302`）、権限画面のユーザー名（`access_permissions.dynamic.php:114`、`web_access_permissions.dynamic.php:117`）、ユーザー編集の見出し（`mutate_user/tpl/form/tab_general.php:27`）、ログの項目名・ユーザー名（`logging.static.php:112-138, 351-352`）、`makeUrl()` の戻り値（`document_data.static.php:176`、`mutate_content/functions.php:721` → `jscripts.tpl:79`、`site_schedule.static.php:167-171`）、ファイル管理のパンくず・パス・フォルダ名・ファイル名・画像の表示（`files.dynamic.php`）。ファイル管理の新規フォルダ名は `evo()->sanitizeFilename()`（`document.parser.class.inc.php:6160`）で、`"`・`<`・`>`・制御文字などを `_` に置き換えてから作っている（`files.dynamic.php:306`）。
- 2026-10-31: 同じく追加で見つかった箇所のうち、まだエスケープしていないものが残っている。ダッシュボードのロール名（`welcome.php:150` の `[+name+]`）、子リソース一覧の「表示」ボタン（`resources_list.static.php:179-180`。`makeUrl()` を JS の `'…'` へ）、ロックアウト画面の戻り先（`manager.lockout.inc.php:29, 31` → `manager.lockout.tpl:25`）、ファイル管理の最上位へのリンク（`files.dynamic.php:200-201` の `filemanager_path`）とダウンロードのリンク（`files.dynamic.php:589` の `webstart_path()`）。
- 2026-10-31: `makeUrl()`（`document.parser.class.inc.php:4299`）は、エイリアスを `xhtml_urls` が有効なときしか `urlencode()` しない。エイリアスは保存時に `stripAlias()`（既定は `strip_tags()` だけ）を通るだけなので、`'`・`"`・`&` が残りうる。参照（ウェブリンク）のリソースは、内容の URL をそのまま返す。戻り値は出力側で必ずエスケープする。
- 2026-10-31: インストーラは要素の説明を `<strong>版</strong> 説明` の形で保存する（`install/setup.info.php:207-212` の `add_version_strings()`）。同梱の要素はすべて説明に `<strong>` を含む。要素一覧（`resources.static.php:141`）が `strip_tags()` で `<b><strong>` だけ残しているのはこのため。モジュール一覧（`modules.static.php:161`）とリソースセレクタ（`resource_selector.static.php:222`）は、説明の列だけ HTML として出すと決めてある（コメントあり）。Issue は説明もエスケープの対象に挙げており、扱いが食い違う。D3 で決める。
- 2026-10-31: `form_text_tag()` と同じ作り（値に `"` があれば `'…'` で囲むだけ）の `Form::text()` がもう一つある（`mutate_settings/functions.inc.php:223-236`、呼び出しは `tab4_manager_settings.inc.php:48` の `manager_docs_orderby`）。
- 2026-10-31: `html_tag()`（`manager/includes/helpers.php:294` → `document.parser.class.inc.php:6471`）の呼び出し元は約 60 か所。属性に DB の値を入れる 13 か所はすべて呼び出し側で `hsc()` 済み（`mutate_content/fields.php:10, 24, 57, 71, 76, 120, 200, 240, 261, 490, 700` ほか）。中身（第 3 引数）には入れ子の `html_tag()` の結果や言語文字列（HTML を含む）を渡している。
- 2026-10-31: 言語ファイル（`manager/includes/lang/japanese-utf8.inc.php`）には、実体参照やタグを含む行が 110 ある。言語文字列を受け取る関数（ダッシュボードの `get_icon()`、メニューの `item()` など）の中でエスケープすると二重になる。
- 2026-10-31: `document_data.static.php:107-109` は、DB から読んだリソースの全列を代入時に一括で `hsc()` している（AGENTS.md で避ける書き方）。`entity()` の出力に `hsc()` を足すと二重になる。
- 2026-10-31: テンプレート変数の選択肢（ドロップダウン・リスト・チェックボックス・ラジオ）のラベルは、`@SELECT` などのバインディングの結果からも作られる（`ProcessTVCommand()` → `ParseInputOptions()`）。ラベルにはリソースのタイトルなど、要素の編集者以外が書いた値も入りうる。
- 2026-10-31: ロックアウト画面のテンプレートは設定 `manager_lockout_tpl` で、ログイン画面は `manager_login_tpl` で差し替えられる。リソース編集の `jscripts.tpl` は `file_get_contents()` で固定のパスから読み（`mutate_content/functions.php:741`）、差し替えられない。
- 2026-10-31: PHP 7.4 の `htmlspecialchars()` の既定フラグは `ENT_COMPAT | ENT_HTML401`（`'` を変換しない）。8.1 から `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401`。`hsc()` は版によらず `ENT_QUOTES | ENT_SUBSTITUTE`。
- 2026-10-31: `nodes.php:191` の `$ph['nodetitle']`（`addslashes()`）はどのテンプレートでも使われていない。`getAlt()`（`nodes.php:598-612`）は `addslashes()` のあとに `htmlspecialchars()` しており、エイリアスに `'` があるとツールチップにバックスラッシュが出る。

## Decision Log

- 2026-10-31 / planner（提案。要確認）D1: `html_tag()` は今のまま値をエスケープせず、呼び出し側で値ごとに `hsc()` する。ヘルパーと `DocumentParser::html_tag()` の docblock に「属性値も中身もエスケープしない」と書く。根拠: 中身は入れ子の HTML を受け取る設計で、ヘルパー側ではエスケープできない。属性だけヘルパー側でエスケープすると、属性と中身で規則が分かれる。属性に DB の値を入れる 13 か所はすでに呼び出し側で `hsc()` しており、ヘルパー側に足すと全箇所を同時に直さない限り二重になる。`evo()->html_tag()` は公開メソッドなので、外部のスニペットやプラグインが `hsc()` 済みの値を渡していれば黙って二重になる（AGENTS.md の DB の取り決め「値を内部で無害化する方式は既存 API に足さない」と同じ理由）。代替案 B は「属性値だけヘルパー側で `hsc()` し、呼び出し側の `hsc()` を同じ PR で外す」。新しく書く呼び出しが自然に安全になる利点はあるが、外部の呼び出しの二重エスケープは避けられないので採らない。`input_text_tag()`・`textarea_tag()`・`select_tag()`・`img_tag()` も `html_tag()` を通るので同じ扱いにする。
- 2026-10-31 / planner D2: `form_text_tag()`・`Form::text()`・`join_attr()` は削除し、呼び出し 2 か所を `form_text()`（#885 で `hsc()` 済み）に置き換える。根拠: 呼び出しは各 1 か所で、同じ役目の `form_text()` がある（DRY）。`'…'` で囲む回避は、値に `'` と `"` の両方があると崩れる。`form_text()` が付ける `maxlength="255"` は、`site_name` と `manager_docs_orderby` の用途では問題にならない。`form_text()` は `evo()->config()` から読み、設定画面では `settings()`（`functions.inc.php:171`）が `evo()->config` をシステム設定で置き換えているので、表示する値は今と同じになる。
- 2026-10-31 / planner（提案。要確認）D3: 要素の説明の表示。選択肢は 3 つ。A: 説明は要素の編集者が書く信頼できる HTML として扱い、今のまま出す（`resources.static.php:338` の未加工の出力だけ `:141` と同じ `strip_tags()` にそろえる）。根拠は、スニペット・プラグイン・テンプレート変数（`@EVAL`）の編集者はもともと PHP を実行できること、モジュール一覧などで HTML として出すと決めてあること。B: 説明はテキストとして `hsc()` し、属性の無い `<b>`・`</b>`・`<strong>`・`</strong>` だけを戻す 1 つの関数を作り、説明を一覧で出すすべての箇所（`resources.static.php` の 2 か所、`modules.static.php`、`resource_selector.static.php`、リソース編集のテンプレート変数の説明 `mutate_content/fields.php:361`）で使う。インストーラが付ける `<strong>版</strong>` はそのまま太字で出る。C: すべて `hsc()` だけ。同梱の要素の説明に `<strong>` の文字が見えるようになる。推奨は B。Issue の意図（出力直前でそろえる）に沿い、今の見た目を保ち、説明を出す箇所の規則を一つにできる（SSOT）。A を選ぶなら、要素の説明を Issue の範囲から外し、Issue にその旨を書く。B の関数の置き場所は `manager/includes/helpers.php`（管理画面と DataGrid の両方から使うため）。名前の案は `element_description_html($text)`。
- 2026-10-31 / planner（提案。要確認）D4: テンプレート変数の選択肢のラベル（`document.parser.subparser.trait.php:1655, 1698, 1726`）は、ドロップダウン・リスト・チェックボックス・ラジオのすべてで `hsc()` する。根拠: ラベルは `@SELECT` などで要素の編集者以外が書いた値からも作られる。`<option>` の中身はもともとタグを書けない。代替案は「チェックボックスとラジオのラベルだけ HTML のまま」で、ラベルに HTML を書いているサイトの見た目は保てるが、規則が分かれ、`@SELECT` の値を守れない。`isSelected()` の比較にはエスケープ前のラベルを使い、出力にだけ `hsc()` を通す。
- 2026-10-31 / planner D5: 差し替えできるテンプレート（`login.tpl`・`welcome.tpl`・`manager.lockout.tpl`）へ渡すプレースホルダーは、HTML に入れる前提で `hsc()` 済みの値にする。同梱の `manager.lockout.tpl` で `[+homeurl+]` を JS の `'…'` に入れている所は、`data-href="[+homeurl+]"` を付けたボタンから `this.dataset.href` を読む形に書き換える。根拠: プレースホルダーに `js_literal()` の値を入れると、差し替えたテンプレートが `'[+homeurl+]'` と書いていた場合に引用符が二重になって飛び先が壊れる。`hsc()` の値なら、JS の中に書いたままのテンプレートでも安全側に倒れ、`&` などを含む URL 以外は今までどおり動く。
- 2026-10-31 / planner D6: 差し替えできない `jscripts.tpl` は、プレースホルダーに `js_literal()` の値を入れ、テンプレート側の `'…'` を外す（`preview_url` と同じ形）。`change_url_suffix()` の `innerHTML` は `textContent` にする。
- 2026-10-31 / planner D7: 数値として使う設定値は `(int)` にする（`default_template`、`preview_mode`）。文字列としてエスケープするより意図が明確で、値の形が崩れていても JS が壊れない。
- 2026-10-31 / planner D8: 言語文字列を受け取る関数（`welcome.static.php` の `get_icon()`、`menu.php` の `item()`）の中ではエスケープせず、DB の値を渡す呼び出し側で `hsc()` する（言語文字列は HTML を含むため。Surprises を参照）。
- 2026-10-31 / planner D9: 触る行にある `htmlspecialchars()` の直書きは `hsc()` に置き換える（PHP 7.4 で `'` を変換しないため）。触らない行は今回の範囲に入れない。
- 2026-10-31 / planner D10: `document_data.static.php` の代入時の一括 `hsc()` は今回動かさない。今は安全で、`entity()` の出力 30 か所余りを出力直前の形へ移すのは別の整理になる。今回は一括エスケープを通らないテンプレート名（`:221`）だけに `hsc()` を足す。
- 2026-10-31 / planner D11: 範囲外にするもの。言語ファイルの文字列（コアが書く HTML）、`$_style` の値・`MODX_*` の定数・`manager_style_image_path()` など、コードが決める値。`<head>` へ出す `manager_inline_style`（`tree.php:33` など。HTML を差し込む設定として意図した出力）。ただし設定画面の `textarea` での表示（`tab4_manager_settings.inc.php:30`）は範囲に入れる。
- 2026-10-31 / planner D12: PR はマイルストーンごとに 1 本に分ける（Issue の対応案どおり、画面のまとまりごと）。M1 は D1・D2 の方針を含むので最初に出す。M2〜M6 は互いに依存しないが、D3・D4 の決定を待つのは M3・M4 だけ。

## Outcomes & Retrospective

## Context and Orientation

管理画面の PHP は `manager/actions/`（画面）、`manager/frames/`（ツリー・メニューの枠）、`manager/includes/`（共通処理）、`manager/media/style/`（テーマのテンプレート）にある。値の出力に使うヘルパーは `manager/includes/helpers.php` にある。

- `hsc($value)`（`helpers.php:147`）: HTML の本文・属性値に入れる値を `htmlspecialchars(…, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` で変換する。`&`→`&amp;`、`<`→`&lt;`、`>`→`&gt;`、`"`→`&quot;`、`'`→`&#039;`。
- `js_literal($value)`（`helpers.php:101`）: JS の文字列リテラル（`"…"` の引用符付き）を返す。`<`・`>`・`&`・`'`・`"` は `<` などに変わるので、`<script>` の中にそのまま書ける。HTML の属性（`onclick="…"` など）の中に書くときは、さらに `hsc()` で囲む（`hsc(js_literal($v))`）。
- `parseText($tpl, $ph)` / `evo()->parseText()` / `evo()->setPlaceholder()`: `[+名前+]` を値で置き換えるだけで、エスケープしない。値は置き換える前に文脈に合わせて変換しておく。
- 二重エスケープ: すでに `hsc()` を通した値にもう一度 `hsc()` を通すと、`&amp;amp;` のように画面に実体参照の文字が見える。直す前に、その値がどこで作られ、途中で `hsc()` を通っていないかを必ず確かめる。保存の処理（`manager/processors/`）は、設定・ロール・要素・リソースのどれも値をエスケープせずに保存している（`save_settings.processor.php:265`、`processors/permission/`、要素の各 processor を確認済み）ので、DB の値は「生のまま」と考えてよい。

このリポジトリでは、DB から読んだ値・設定値・セッションの値を「保存された値」と呼ぶ。言語ファイルの文字列とコードが決める値は対象にしない（D11）。

DocumentParser（`manager/includes/document.parser.class.inc.php`）への影響: `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` は変えない。変えるのは管理画面から呼ばれる `html_tag()` の docblock（M1）と、リソース編集画面でテンプレート変数の入力欄を作る `renderFormElement()` 系の private メソッド（`traits/document.parser.subparser.trait.php`、M3）だけ。キャッシュ: ページキャッシュには関わらない（管理画面の出力だけ）。設定値を DB で書き換えて確かめるときは、設定のキャッシュを `php evo cache:clear` で消す。

前提: PHP 7.4 以上で動くこと（PHP 8.0 以降で入った関数・構文を新しく使わない。既存の `str_contains()` は `manager/includes/helpers.php:72` の polyfill で 7.4 でも動く）。

## Plan of Work

方針は「出力の直前で、出力する文脈に合ったヘルパーを通す」の一つだけ。値ごとに次の 4 つから選ぶ。

1. HTML の本文・属性値: `hsc($v)`
2. `<script>` の中の JS の値: `js_literal($v)`（テンプレート側の `'…'` を外す）
3. HTML の属性の中の JS（`onclick` など）: `hsc(js_literal($v))`
4. JS で DOM に入れる値: `innerHTML` をやめて `textContent` にする

各マイルストーンでは、Concrete Steps に挙げた行を直したうえで、そのファイルの中を `grep -n "<?=" <file>` と `grep -n "echo\|sprintf\|parseText" <file>` で見直し、同じ形の取りこぼしがあれば同じマイルストーンで直す（見つけたものは Surprises に足す）。値が `hsc()` 済みか、整数か、言語文字列かを一つずつ確かめ、二重エスケープを作らない。

ヘルパー（`html_tag()`）の扱いは D1、`form_text_tag()` の扱いは D2 を正とする。新しい共通関数は D3 で B を選んだときの 1 つだけにする。

## Concrete Steps

行番号は 2026-10-31 の main（fbe24daad）のもの。作業は Issue の作業ブランチ（`fix/895-manager-output-escape`）で行い、マイルストーンごとにコミットと PR を分ける。

### M1: 設定画面とヘルパーの方針

書き込むファイル: `manager/includes/helpers.php`、`manager/includes/document.parser.class.inc.php`（docblock だけ）、`manager/actions/tool/mutate_settings/functions.inc.php`、`tab1_site_settings.inc.php`、`tab1_doc_settings.inc.php`、`tab3_user_settings.inc.php`、`tab4_manager_settings.inc.php`（いずれも `manager/actions/tool/mutate_settings/` の下）。計 7 ファイル。

1. `helpers.php:294` の `html_tag()` と `document.parser.class.inc.php:6471` の `html_tag()` に docblock を足す。「属性値も中身もエスケープしない。DB や設定の値は呼び出し側で `hsc()` してから渡す。中身には HTML を渡せる」（D1）。
2. `functions.inc.php:70-79` の `form_text_tag()`、`:81-101` の `join_attr()`、`:223-236` の `class Form` を削除する（D2）。削除の前に `grep -rn "form_text_tag\|join_attr\|Form::" manager assets install` で、ほかに呼び出しが無いことを確かめる（2026-10-31 時点は各 1 か所）。
3. `tab1_site_settings.inc.php:30` を `form_text('site_name')` に、`:40` の `site_slogan` を `hsc(config('site_slogan'))` にする。
4. `tab4_manager_settings.inc.php:48-53` の `Form::text(…)` を `form_text('manager_docs_orderby', 255, 'style="width:500px;"')` に、`:30` の `manager_inline_style` を `hsc(config('manager_inline_style'))` にする。
5. `functions.inc.php:150-153` の `get_role_list()`: `$ph['name']` を `hsc()` する（ロール名）。
6. `tab1_doc_settings.inc.php`: `:9` の `config('default_template')` を `(int)` にする（D7）。`:41` のテンプレート名を `hsc($template['name'])`、`:46` の `optgroup` の `label` を `hsc($category)`、`:75` の `$savedTmpName`（`sprintf(lang('template_reset_specific'), …)` に入るテンプレート名）を `hsc()` する。`$category` は「カテゴリーなし」のとき言語文字列 `no_category` になるが、日本語・英語とも実体参照やタグを含まない平文（`japanese-utf8.inc.php:583`）なので、`hsc()` を通しても表示は変わらない。
7. `tab3_user_settings.inc.php`: `:118` の `parseText()` に渡す `emailsender` を `hsc()`、`:291`・`:312`・`:330` の各メール本文の `textarea` を `hsc($signupemail_message)` などにする。これらの変数は `mutate_settings.dynamic.php:26-27` の `settings()` → `extract()` から来る生の値で、入力エラーで戻ったときは送信した値（`$_POST`）が重なる。

期待される観測結果: 設定画面の「サイト名」「スローガン」「メール本文」などに `&`・`<`・`"`・`'` を含む値を入れて保存し、画面を開き直すと、入力欄に同じ文字列がそのまま見える。何も変えずにもう一度保存しても、DB の値が変わらない。

推奨コミット: `fix(manager): 設定画面で保存された値を出力直前でエスケープする`（docblock と `form_text_tag()` の削除は同じコミットでよい。分けるなら `refactor(manager): form_text_tag と Form::text を form_text にまとめる` を先に）

### M2: 管理画面の枠とサイト名

書き込むファイル: `manager/frames/tree.php`、`manager/frames/nodes.php`、`manager/frames/menu.php`、`manager/includes/accesscontrol-not-mgr.inc.php`、`manager/includes/manager.lockout.inc.php`、`manager/media/style/common/manager.lockout.tpl`、`manager/actions/main/welcome.static.php`、`manager/media/style/common/welcome.php`、`manager/actions/tool/import_site.static.php`。計 9 ファイル。

1. `tree.php:435`: `onclick="treeAction(0, <?= hsc(js_literal($site_name)) ?>);"`、`<b><?= hsc($site_name) ?></b>` にする（`addslashes()` をやめる）。`$site_name` は `manager/index.php:54` の `extract($modx->config)` から来る生の値。受け取る `setParent()`・`setMoveValue()` は `textContent` で表示する（確認済み）。
2. `nodes.php`: `:191` の使われていない `$ph['nodetitle']` を削除する。`:610` の `addslashes($alt)` を削除し、`:611` を `hsc($alt)` にする。`$ph['pagetitle']`・`$ph['url']`（`:190, 193`）と `getNodeTitle()`（`:515` で `hsc()` 済み）は触らない。
3. `menu.php`: `:415` のモジュール名を `item(hsc($content['name']), …)` にする（配列のキーは生の名前のままでよい）。`:363` の `config("site_url")` を `hsc(config('site_url'))` にする（`item()` の中ではエスケープしない。D8）。`:341` の `$versionTitle` は `htmlspecialchars(…, ENT_QUOTES, …)` 済みなので触らない。
4. `accesscontrol-not-mgr.inc.php:56`: `hsc(evo()->config('site_name'))` にする。各テーマの `login.tpl`（`common`・`AromaHop`・`AuroraFlow`・`RevoClassic`・`RevoStyle`・`RubberWhite`・`SakuraFlow`）の `[+site_name+]` はどれも HTML の本文か `alt` 属性なので、テンプレートは変えない（D5）。
5. `manager.lockout.inc.php`: `:16` の `site_name` を `hsc()`、`:29`・`:31` の `homeurl` を `hsc(evo()->makeUrl(…))` にする。`manager.lockout.tpl:24-26` の `gotoHome()` を削除し、`:48` のボタンを `<input type="button" id="submitButton" value="[+home+]" data-href="[+homeurl+]" onclick="top.location = this.dataset.href; return false;"/>` の形にする（D5）。
6. `welcome.static.php:45` の `site_name` を `hsc($site_name)`、`:191` を `get_icon(hsc($content['name']), $action, hsc($content['icon']), hsc($content['name']))` にする（D8）。`get_icon()` の `href` に入る `$action` はコードが作る値なので触らない。
7. `welcome.php:150`: ロール名 `$ph['name']` を `hsc(sessionv('mgrPermissions.name'))` にする。`:149` の `UserName` は `hsc()` 済み。
8. `import_site.static.php:50`: `hsc(evo()->config('site_name'))` にする。

期待される観測結果: サイト名に `&`・`<`・`"`・`'` を含む値を入れても、ログイン画面・ダッシュボード・ツリーの根・リソースのインポート画面に同じ文字列が見える。ツリーの根をクリックして「移動」「親の選択」をすると、移動先の表示にサイト名がそのまま出る。ロックアウト画面の「ホーム」ボタンで戻り先へ移る。

推奨コミット: `fix(manager): ログイン・ダッシュボード・ツリーで設定値とロール名をエスケープする`

### M3: リソースの編集・情報・子リソース一覧

書き込むファイル: `manager/actions/document/mutate_content/functions.php`、`manager/actions/document/mutate_content/fields.php`、`manager/media/style/common/jscripts.tpl`、`manager/includes/traits/document.parser.subparser.trait.php`、`manager/actions/document/document_data.static.php`、`manager/actions/document/resources_list.static.php`、D3 で B のとき `manager/includes/helpers.php`。計 6〜7 ファイル。D3・D4 の決定後に着手する。

1. `functions.php:719-720` の `imanager_url`・`fmanager_url` を `js_literal(config(…))` にし、`jscripts.tpl:3-4` を `var imanager_url = [+imanager_url+];` の形にする（D6）。`:722` の `preview_mode` を `(int)`（D7）、`:738` の `suffix` を `js_literal(config('friendly_url_suffix'))` にし、`jscripts.tpl:259` を `else s.textContent = [+suffix+];`、`:258` の `s.innerHTML = ''` も `textContent` にそろえる。
2. `functions.php:277`: 文書グループ名を `hsc($row['name'])` にする（`html_tag()` の中身。D1）。
3. `fields.php:90`: URL の末尾（`friendly_url_suffix`）を `hsc(config('friendly_url_suffix'))` にする。`:361` のテンプレート変数の説明は D3 に従う（B なら `element_description_html($tv['description'])`、C なら `hsc()`、A なら変えない）。`:360` の `caption` は `hsc()` 済み。
4. `document.parser.subparser.trait.php`: `:1655`（ドロップダウン・リスト）、`:1698`（チェックボックス）、`:1726`（ラジオ）の `'label' => $label` を `evo()->hsc($label)` にする（D4）。`isSelected()` に渡す `$label` はエスケープ前のまま。
5. `document_data.static.php:221`: テンプレート名 `$templatename` を `hsc($templatename)` にする。`entity()` の出力には足さない（D10。`:107-109` で一括 `hsc()` 済み）。
6. `resources_list.static.php:179-180`: `$prev = sprintf("window.open(%s,'previeWin')", hsc(js_literal(evo()->makeUrl($id))));` にする（`sprintf($tpl, …)` の `onclick="%s"` に入るので `hsc(js_literal())`）。`$id == 0` のとき `makeUrl(0)` はサイトの開始リソースの URL になる（今と同じ）。
7. D3 で B のとき: `helpers.php` に `element_description_html($text)` を足す。中身は `hsc()` のあと、`&lt;b&gt;`・`&lt;/b&gt;`・`&lt;strong&gt;`・`&lt;/strong&gt;`（大文字小文字を問わない）だけを元のタグに戻す。属性付きのタグは戻さない。docblock に「要素の説明を一覧に出すとき用。ほかのタグは文字として出る」と書く。

期待される観測結果: エイリアスやテンプレート変数の選択肢に `&`・`<`・`"`・`'` を含む値があっても、リソースの編集画面・情報画面・子リソース一覧が崩れず、プレビューや「表示」ボタンが正しい URL を開く。エイリアスの入力でサフィックスの表示が切り替わる。テンプレート変数の選択肢を選んで保存し、開き直すと選んだ状態が保たれる。

推奨コミット: `fix(manager): リソースの編集・情報画面で設定値と選択肢のラベルをエスケープする`

### M4: 要素（一覧・編集画面・テンプレート変数）

書き込むファイル: `manager/actions/element/resources.static.php`、`mutate_module.dynamic.php`、`mutate_plugin.dynamic.php`、`mutate_snippet.dynamic.php`、`mutate_templates.dynamic.php`、`mutate_tmplvars.dynamic.php`、`mutate_module_resources.dynamic.php`、D3 で B のとき `modules.static.php`・`resource_selector.static.php`（いずれも `manager/actions/element/` の下）。計 7〜9 ファイル。D3 の決定後に着手する。

1. `resources.static.php`: `:141` と `:338` の説明を D3 に従ってそろえる（B なら両方 `element_description_html()`。`:338` は `' - ' .` の後ろに通す）。`:319`・`:321` のカテゴリー名を `hsc($v['category'])` にする（`:130` の一覧と同じ形）。`:321` の `deleteCategory('…')` に入る `catid` は整数なので `(int)` にする。`:336` の `htmlspecialchars($v['name'], ENT_QUOTES, …)` は `hsc()` にそろえる（D9）。
2. D3 で B のとき: `modules.static.php:160-161` と `resource_selector.static.php:221-222` で、説明の列を `element_description_html()` で変換した配列を DataGrid に渡す（DataGrid は配列も受け取る。`datagrid.class.php` の `render()`）。説明の列の `colEscapes` は空のまま（変換済みの HTML を出すため）。
3. `mutate_module.dynamic.php`: `:446` の説明、`:452` のアイコン、`:465` のリソースファイル、`:486` のプロパティを `hsc(content(…))` にする。`:528` の `guid` を `hsc()`。`:637` のユーザーグループ名を `hsc($row['name'])`。`:302` の `"<?= $base_url ?>manager/…"` を `js_literal($base_url . 'manager/media/browser/evo/browser.php?type=images')` にする（M5 の同じ形とそろえる）。
4. `mutate_plugin.dynamic.php`: `:742` の説明と `:606` のプロパティを `hsc(entity(…))`、`:575` の `guid` を `hsc()`（`value='…'` を `value="…"` にそろえる）、`:679-682` のイベント名（`id`・`for` 属性と本文）を `hsc($row['name'])` にする。
5. `mutate_snippet.dynamic.php`: `:387` の説明と `:471` のプロパティを `hsc(entity(…))`、`:446` の `guid` を `hsc()` にする。`:344` の `$code` は `:336-338` で `hsc()` 済みなので触らない。
6. `mutate_templates.dynamic.php:262-264`: テンプレート変数の名前を `hsc($row['name'])`、説明（`$row['desc']`）を D3 に従ってそろえる（B なら `element_description_html()`）。
7. `mutate_tmplvars.dynamic.php`: `:537` のテンプレート名を `hsc($row['templatename'])`、`:655` の文書グループ名を `hsc($row['name'])` にする。
8. `mutate_module_resources.dynamic.php:223`: モジュール名を `hsc($content['name'])` にする。依存関係の一覧（`:255-257`）は `colEscapes` 済み。

期待される観測結果: 要素の名前・説明・カテゴリー名・プロパティに `&`・`<`・`"`・`'` を含む値を入れて保存しても、要素の一覧と各編集画面が崩れず、何も変えずに保存し直しても値が変わらない。同梱の要素の説明の版番号の表示は D3 の選択どおり（B なら太字のまま）。

推奨コミット: `fix(manager): 要素の一覧と編集画面で保存された値をエスケープする`

### M5: ユーザー・ロール・権限

書き込むファイル: `manager/actions/permission/role_management.static.php`、`mutate_role.dynamic.php`、`access_permissions.dynamic.php`、`web_access_permissions.dynamic.php`、`mutate_web_user.dynamic.php`、`mutate_user_pf.dynamic.php`、`mutate_user/tpl/form/tab_access.php`、`mutate_user/tpl/form/tab_general.php`、`mutate_user/tpl/form/tab_page5.php`、`mutate_user/tpl/form/tab_page7.php`、`mutate_user/tpl/form/tab_settings.php`（いずれも `manager/actions/permission/` の下）。計 11 ファイル。

1. `role_management.static.php`: `:52`・`:58` のロール名を `hsc($row['name'])`、`:59` の説明を `hsc($row['description'])` にする。
2. `mutate_role.dynamic.php`: `:140` を `hsc(role('name'))`、`:145` を `hsc(role('description'))` にする。
3. `access_permissions.dynamic.php` と `web_access_permissions.dynamic.php`: グループ名の選択肢（`:17`・`:28` と `:19`・`:30`）、グループ名の見出し（`:244` と `:257`）、文書グループ名（`:257` と `:270`）を `hsc()` する。名前の変更欄の `htmlspecialchars($row['name'])`（`:97`・`:175` と `:100`・`:183`）と文書のタイトル（`access_permissions.dynamic.php:191`）は `hsc()` にそろえる（D9）。
4. `tab_access.php:34`: `$ph['name'] = hsc($row['name'])`。`tab_general.php:204`: `html_tag()` の中身を `hsc($row['name'])` にする（D1）。`mutate_web_user.dynamic.php:661`: `str_replace('[+name+]', hsc($row['name']), $echo)`。
5. ユーザーごとの設定の入力欄: `tab_page5.php:46`（`manager_login_startup`）・`:107`（`editor_css_path`）、`tab_page7.php:15`・`:29`・`:43`（アップロードを許す拡張子）・`:68`（`rb_base_dir`）・`:76`（`rb_base_url`）、`tab_settings.php:106`（`allowed_ip`）を `hsc()` にする。`tab_page7.php:7` の `htmlspecialchars()` は `hsc()` にそろえる。値は `user()`（`mutate_user/functions.php:24`）や `$user` の生の値。
6. `mutate_user_pf.dynamic.php`: `:401`（`manager_inline_style`）と `:469`（`editor_css_path`）を `hsc(setting(…))` にする。`:132`・`:137` の `$base_url` を `js_literal()` にする（`:137` は `document.images['iphoto'].src = <?= js_literal($base_url) ?> + url;`）。`mutate_web_user.dynamic.php:204`・`:209` も同じ形にする。

期待される観測結果: ロール名・グループ名・ユーザーの設定値に `&`・`<`・`"`・`'` を含む値を入れても、ロール管理・権限・ユーザー編集・プロフィールの各画面が崩れず、何も変えずに保存し直しても値が変わらない。プロフィールの写真の選択が今までどおり動く。

推奨コミット: `fix(manager): ロール・権限・ユーザー設定の画面で保存された値をエスケープする`

### M6: ファイル管理・レポート・ログ

書き込むファイル: `manager/actions/element/files.dynamic.php`、`manager/actions/report/logging.static.php`。計 2 ファイル。このまとまりの #895 の洗い出しで追加で見つかった箇所は、ほとんど今の main で対処済み（Surprises）。ここでは残りを直し、対処済みの箇所の挙動を確かめる。

1. `files.dynamic.php:199-202`: 最上位へのリンクを `sprintf('<a href="%s">Top</a> / ', hsc('index.php?a=31&mode=drill&path=' . urlencode(config('filemanager_path'))))` にする（閉じタグの `</b>` の誤りも直す）。
2. `files.dynamic.php:587-596`: ダウンロードのリンクの `href` を `hsc(webstart_path() . implode(…))` にする。
3. `files.dynamic.php:110`: キャンセルの `document.location.href='index.php?<?= $href ?>'` を `document.location.href=<?= hsc(js_literal('index.php?' . $href)) ?>` にそろえる（`$href` は `urlencode()` 済みで今も壊れないが、属性の中の JS の書き方を一つにする）。
4. `logging.static.php:343` の `[+message+]` に入る `$row['message']` を、`:351` の `itemname` と並べて `hsc()` する（ログの文言の多くはコードが決めるが、呼び出し元から渡る文言もあるため）。

期待される観測結果: ファイル管理で、`&` や `'` を含む名前のフォルダ・ファイル（アップロードや FTP で置いたもの）を表示・移動・ダウンロード・削除できる。管理ログの一覧が崩れない。

推奨コミット: `fix(manager): ファイル管理とログの画面で残っていた値をエスケープする`

### M7: 横断の確認

書き込むファイル: なし（確認だけ。直す所が見つかれば、そのまとまりのマイルストーンへ戻って直す）。

1. M1〜M6 で変えたファイルについて、PHP 7.4 の構文を確かめる。リポジトリのルートで、ファイルごとに:

        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l <file>

   期待: `No syntax errors detected in <file>` がすべてのファイルで出る。
2. security-lint を流す（検証環境の app コンテナで）:

        docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml exec app php .github/scripts/security-lint.php

   期待: `0 violations`。
3. 「Validation and Acceptance」の確認スクリプトを main と作業ブランチで流し、結果を「画面 × main × 作業ブランチ」の表にして PR の確認方法と Outcomes に残す。

## Validation and Acceptance

検証は `adaptive-validation-runner` スキル（`.claude/skills/adaptive-validation-runner/SKILL.md`、`references/environment.md`）に従い、Docker の検証環境で行う。標準の版（PHP 8.4・MariaDB 11）で動作を確かめ、PHP 7.4 は M7 の構文確認で押さえる。

準備:

    docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build
    NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor
    NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/smoke.js

並走するセッションがあり得るときは、`EVO_VALIDATE_NAME` と `EVO_BIND_IP` を各コマンドの先頭に付ける（`references/environment.md` の「並走するとき」）。以下、`DC` は上の `docker compose -f … -f …` を表す。

確かめに使う値（以下「確認用の値」）は `E&<b>"q"'s</b>` とする。エスケープが要る `&`・`<`・`>`・`"`・`'` をすべて含み、二重エスケープは `&amp;amp;` として見分けられる。

1. 値を入れる: 設定値は画面（`a=17`）で入れて保存するか、DB で入れてキャッシュを消す。

        DC exec app php evo db:query "UPDATE modx_system_settings SET setting_value='E&<b>\"q\"''s</b>' WHERE setting_name='site_name'"
        DC exec app php evo cache:clear

   ロール名・グループ名・要素の名前と説明・カテゴリー名・テンプレート変数の選択肢（`elements` 列に `E&<b>"q"'s</b>==1||普通==2`）・リソースのタイトルとエイリアス・ユーザーの設定値も、同じように画面か `db:query` で入れる。ファイル管理は、`assets/files/` の下に確認用の値に近い名前（`/` を含まない `E&'s"q"` など）のファイルとフォルダをコンテナの中で作る（`DC exec app sh -c "…"`）。
2. 画面の HTML を見る: Playwright（ヘッドレス）で管理者としてログインし、`page.request.get()` で各画面（`index.php?a=…`）の応答本文をそのまま取る（`page.content()` は DOM を書き直した結果なので使わない）。管理画面への要求には `Accept-Language` を付ける。
   期待: 本文に `E&amp;&lt;b&gt;&quot;q&quot;&#039;s&lt;/b&gt;`（HTML の文脈）か、`<b>` を含む JS のリテラル（JS の文脈）が出る。生の `<b>"q"'s</b>` と、`&amp;amp;`・`&amp;lt;`・`&amp;quot;`・`&amp;#039;` は出ない。D3 で B を選んだときは、要素の説明に入れた `<strong>x</strong>` は `<strong>x</strong>` のまま、`<strong class="a">x</strong>` は文字として出る。
3. 画面の動きを見る（Playwright）: ツリーの根をクリックして「移動」の移動先の表示（`#parentName` の `textContent`）が確認用の値を含むこと、リソース編集画面でエイリアスを変えたときにサフィックスの表示が変わること、プレビュー・「表示」ボタンが開く URL、ロックアウト画面の「ホーム」ボタンの遷移先、テンプレート変数の選択肢を選んで保存した状態、を確かめる。ページの JS のエラー（`page.on('pageerror')`）が 0 件であること。
4. 保存し直しても値が変わらない: 確認用の値を入れた画面（設定・ロール・要素・ユーザー設定・リソース）を開き、何も変えずに保存する。その後 `DC exec app php evo db:query "SELECT …"` で、値が確認用の値と一致すること（`&amp;` などに変わっていないこと）を確かめる。
5. main との比較: 同じスクリプトを main（修正前）と作業ブランチ（修正後）で流す。期待は、修正前は 2 か 4 で FAIL する画面があり、修正後はすべて PASS。ブランチを切り替えたら数秒待つ（OPcache）。
6. スモークテスト（`smoke.js`）が修正後も全 PASS すること。

完了の条件: M1〜M6 で直した画面のすべてで 2〜4 が PASS し、M7 の構文確認と security-lint が通り、`smoke.js` が全 PASS する。

## Idempotence and Recovery

変更は出力の書き方だけで、DB やファイルの構造は変えないので、どのマイルストーンも何度やり直してもよい。途中で中断したら、`git status` と `git diff` で直し終えたファイルを確かめ、Progress のマイルストーンの続きから再開する。確認用の値を入れた DB は、検証環境を `references/environment.md` の「後片付け」で消すか、`setup-site.js --reinstall` で入れ直す。検証環境のインストールで作られる `manager/includes/config.inc.php` と、`.htaccess` の名前の変化は、同じ文書の「既知の落とし穴」に従って戻す。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/895
- 関連: #885（設定画面の `form_text()`）、#891（設定画面とリソース編集画面の一部の欄）
- 出力のエスケープの取り決め: `AGENTS.md` の「補助ルール」→「出力のエスケープ」
- 既存のエスケープの例: `manager/includes/error.class.inc.php:53-63`（`js_literal(hsc())` の使い分けの説明）、`manager/actions/document/move_document.dynamic.php:95-104`（`textContent` で組み立てる例）、`manager/frames/nodes.php:190, 193`（`hsc(js_literal())` の例）
- DataGrid の列のエスケープ: `manager/includes/controls/datagrid.class.php:41`（`colEscapes`）、`:191-213`

## Interfaces and Dependencies

- `hsc()`・`js_literal()`（`manager/includes/helpers.php`）を使う。新しく足すのは、D3 で B を選んだときの `element_description_html(string $text): string`（`manager/includes/helpers.php`）だけ。
- 削除する関数: `form_text_tag()`・`join_attr()`・`Form::text()`（`manager/actions/tool/mutate_settings/functions.inc.php`）。設定画面の中だけで使われていて、外部から呼ばれる前提のものではない。
- 振る舞いが変わる公開の部分: `DocumentParser::renderFormElement()` が作るテンプレート変数の選択肢のラベルがエスケープされる（D4。ラベルに HTML を書いていたサイトでは、タグが文字として見える）。差し替えたログイン・ダッシュボード・ロックアウトのテンプレートに渡る `[+site_name+]`・`[+homeurl+]` が `hsc()` 済みの値になる（D5）。`html_tag()` の振る舞いは変えない（D1）。
- PHP 7.4 以上。外部のライブラリは使わない。
