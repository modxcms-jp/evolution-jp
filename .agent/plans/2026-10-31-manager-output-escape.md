# ExecPlan: 管理画面で保存された値を出力直前でエスケープする（Issue #895）

## Purpose / Big Picture

管理画面に出す「保存された値」（設定値、ロール名・グループ名、要素の名前や説明、リソースのタイトル、ユーザー名、ファイル名など）を、出力する文脈に合ったヘルパー（HTML は `hsc()`、JS は `js_literal()`）に出力の直前で通す。AGENTS.md の「出力のエスケープ」にそろえる。これで、`&`・`<`・`"`・`'` を含む値を保存しても画面が崩れず、編集画面を開いて何も変えずに保存し直しても値が変わらなくなる。あわせて、値をエスケープしない HTML 組み立てヘルパー（`html_tag()`、`form_text_tag()`）の扱いを一つに決める。

Issue: #895

## Progress

- [x] (2026-11-01) M0: Decision Log の「要確認」（D1・D3・D4）をユーザーに確かめて確定する（D1 は呼び出し側で `hsc()`、D3 は B、D4 はすべて `hsc()`。どれも推奨どおり）
- [x] (2026-11-01) M1: 設定画面とヘルパーの方針（`html_tag()` の説明、`form_text_tag()`・`Form::text()` の廃止）。自己レビューの指摘で `site_unavailable_message`・`default_template` の比較・`emailsender` の文言・ファイル管理タブの `site_url` も直した
- [x] (2026-11-02) M2: 管理画面の枠（ログイン・ロックアウト・ダッシュボード・メニュー・ツリー）とサイト名。自己レビューの指摘で、メニューの `mail_check_timeperiod` とツリーの `site_start` を `(int)` にし（D7。menu.php 冒頭の「空なら 0」の 3 行は不要になり削除）、インポート画面のファイル名・フォルダ名・エラー文も `hsc()` にした
- [x] (2026-11-03) M3: リソースの編集・情報・子リソース一覧。自己レビューの指摘で、子リソース一覧の JS の `site_start`、編集画面の `upload_maxsize`・`docParent`・`docIsFolder` も `(int)` にし（D7）、`element_description_html()` は対になったタグだけを戻す形にした
- [x] (2026-11-04) M4: 要素（一覧・編集画面・テンプレート変数）。手順 2 は DataGrid の `description` 型で行い（D13）、DocManager・QuickManager のテンプレート変数の説明もそろえた（D14）。#1003（説明欄の `&amp;`）もここで閉じる。自己レビューの指摘で、テンプレート変数の入力タイプの選択肢・イベントのグループ名・隠しフィールドの id も直した
- [x] (2026-11-04) M4b: 要素の編集画面のプロパティの設定表（JS で組み立てる所。D15）。プラグイン・スニペット・モジュールは共通の JS `element-properties.js` にまとめ、テンプレート変数はその画面の中で DOM で組み立てた（D16）。検証で見つかったシェル遷移での再評価の後退と、自己レビューの指摘（選択欄の幅・背景の指定）も直した
- [x] (2026-11-05) M5: ユーザー・ロール・権限。差し戻しで送信値がそのまま出る id やログイン回数なども `(int)` にした。自己レビューの指摘で、管理ユーザーの編集画面の写真選択の JS（`mutate_user/tpl/javascript.php`）とエディター名の選択肢も直した
- [x] (2026-11-05) M6: ファイル管理・レポート・ログ
- [x] (2026-11-05) M7: 横断の確認（main との比較、PHP 7.4 の構文確認、security-lint）。PHP 7.4 の検証環境でも実際に動かした

## Surprises & Discoveries

- 2026-10-31: Issue の対象のうち次は、今の main ですでに出力直前のエスケープになっている。M1〜M6 では触らず、二重エスケープを足さないよう確認だけする。`manager/actions/tool/export_site.static.php`（セッションから戻す値はすべて `hsc()`）、要素の編集画面のカテゴリー名（`mutate_snippet.dynamic.php:362`、`mutate_plugin.dynamic.php:717`、`mutate_tmplvars.dynamic.php:562`、`mutate_templates.dynamic.php:181`、`mutate_htmlsnippet.dynamic.php:313`）、プラグイン・スニペットの「パラメーターの取り込み」のモジュール名（`mutate_plugin.dynamic.php:575`、`mutate_snippet.dynamic.php:446`）、要素の名前（各編集画面の `name` 欄）、リソースの編集画面のタイトル・説明などの入力欄（`mutate_content/fields.php`）。
- 2026-10-31: #895 の洗い出しで追加で見つかった箇所のうち、次は今の main で対処済みだった（確認だけ）。リソースツリーのタイトルと URL（`manager/frames/nodes.php:190, 193`。`hsc(js_literal())`）、ツリーから渡るタイトルの受け取り（`move_document.dynamic.php:95-104`、`mutate_content` の `setParent()`（`manager/media/style/common/jscripts.tpl:202-215`）、`import_site.static.php:16-20`。いずれも `textContent`）、移動画面の一覧（`move_document.dynamic.php:83`）、ダッシュボードの最近編集したリソースとユーザー名（`manager/media/style/common/welcome.php:149, 197-199`）、システム情報（`sysinfo.static.php:234-235`）、リソース情報の作成者・編集者（`document_data.static.php:294, 302`）、権限画面のユーザー名（`access_permissions.dynamic.php:114`、`web_access_permissions.dynamic.php:117`）、ユーザー編集の見出し（`mutate_user/tpl/form/tab_general.php:27`）、ログの項目名・ユーザー名（`logging.static.php:112-138, 351-352`）、`makeUrl()` の戻り値（`document_data.static.php:176`、`mutate_content/functions.php:721` → `jscripts.tpl:79`、`site_schedule.static.php:167-171`）、ファイル管理のパンくず・パス・フォルダ名・ファイル名・画像の表示（`files.dynamic.php`）。ファイル管理の新規フォルダ名は `evo()->sanitizeFilename()`（`document.parser.class.inc.php:6160`）で、`"`・`<`・`>`・制御文字などを `_` に置き換えてから作っている（`files.dynamic.php:306`）。
- 2026-10-31: 同じく追加で見つかった箇所のうち、まだエスケープしていないものが残っている。ダッシュボードのロール名（`welcome.php:150` の `[+name+]`）、子リソース一覧の「表示」ボタン（`resources_list.static.php:179-180`。`makeUrl()` を JS の `'…'` へ）、ロックアウト画面の戻り先（`manager.lockout.inc.php:29, 31` → `manager.lockout.tpl:25`）、ファイル管理の最上位へのリンク（`files.dynamic.php:200-201` の `filemanager_path`）とダウンロードのリンク（`files.dynamic.php:589` の `webstart_path()`）。（2026-11-05: どれも M2・M3・M6 で直した）
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

- 2026-11-01: M1 の自己レビューで、同じ設定画面に取りこぼしが見つかった。`tab1_site_settings.inc.php` の `site_unavailable_message`（`textarea`）と、`tab6_filemanager_settings.inc.php` の `rb_base_url` の前に出す `$site_url`。どちらも M1 で `hsc()` にした（`tab6` は M1 の書き込むファイルに足した）。また `default_template` を `(int)` にしたため、JS の比較も数で行うようにした（保存値が空のとき「(blank)」に戻すとリセット欄が隠れるように）。
- 2026-11-01: M1 の検証で、修正前は設定画面の値を開いて何も変えずに保存し直すと、`site_name` と `manager_docs_orderby` が途中で切れて保存されることを確かめた（`form_text()` に置き換えて直った）。
- 2026-11-01: 設定画面の言語文字列を `value` 属性に `addslashes()` で出している箇所が複数ある（`tab1_site_settings.inc.php`・`tab3_user_settings.inc.php`）。D11 で言語文字列は範囲外としたので触っていない。属性の文脈に `addslashes()` は合わないので、別の Issue の候補。
- 2026-11-02: M2 の検証で、修正前はサイト名に `"` や `'` があるとツリーの根の `onclick` が壊れ（`addslashes()` は HTML の属性を守れない）、JS のエラーで「移動」や「親の選択」で根を選べないことを確かめた。修正後は選べる。
- 2026-11-02: M2 の自己レビューで、`mail_check_timeperiod`（メニュー）と `site_start`（ツリー）を JS に生のまま出していたことが分かった。どちらも保存時に検証していないので、数でない値で JS が止まる（修正前は `abc` で pageerror 4 件を確かめた）。D7 の対象に足して `(int)` にした。
- 2026-11-02: `import_site.static.php` は、ファイル名・フォルダ名・DB のエラー文をそのまま出していた（M2 で `hsc()` にした）。同じファイルで、`description`・`alias` を `db()->escape()` を通さずに `db()->insert()` に渡しており、ファイル名に `'` があるとインポートが SQL の構文エラーで止まる（修正前から。出力のエスケープとは別なので、別の Issue の候補）。
- 2026-11-02: ツリーの `$ph['nodetitle']` を削除したので、`OnManagerNodePrerender`・`OnManagerNodeRender` のプラグインに渡る値から `nodetitle` が消えた。同梱のテンプレートとプラグインは使っていない。
- 2026-11-02: `manager_theme` は、`menu.php`・`tree.php`・`header.inc.php`・`welcome.static.php`・`manager.lockout.inc.php`・`accesscontrol-not-mgr.inc.php`・`document.parser.subparser.trait.php` でエスケープせずに出し、`resource_selector.static.php` だけが `hsc()` している。ユーザーごとの設定でも上書きでき、パスにも使うので、保存時の検証と出力時の `hsc()` をそろえる別の Issue の候補（M2 では触らない）。
- 2026-11-02: M2 の手順 4 のテーマの一覧には SoftSlate が抜けていた。SoftSlate の `login.tpl` も `[+site_name+]` を HTML の本文と `alt` 属性にしか使っておらず、テンプレートを変える必要はない。
- 2026-11-03: M3 の検証で、修正前はテンプレート変数の説明に書いた `<script>` がリソースの編集画面で実行されること、`imanager_url`・`friendly_url_suffix` に `'` があると編集画面の JS が壊れ（`imanager_url is not defined` など）、画像の挿入やサフィックスの切り替えが動かないことを確かめた。子リソース一覧の「表示」は、エイリアスの `'` は `makeUrl()` が `%27` にするので壊れず、`friendly_url_suffix` に `'` があると壊れる。修正後はどれも動く。
- 2026-11-03: M3 の自己レビューで、子リソース一覧の JS の `site_start`（修正前は `abc` で「非公開」の操作が JS のエラー）、編集画面の `upload_maxsize`（`"` で属性が壊れる。ユーザーごとの設定でも上書きできる）、`docParent`・`docIsFolder`（入力の差し戻しで送信値が重なる）を生のまま出していたことが分かった。D7 の対象に足して `(int)` にした。同じ `upload_maxsize` は `files.dynamic.php:402` でも生のまま出しているので、M6 の手順に足した。
- 2026-11-03: `element_description_html()` を最初は開きタグ・閉じタグを 1 つずつ戻す形にしたが、閉じ忘れの `<strong>` が後ろの `<li>` まで太字を広げうる（M4 の要素一覧は `<li>` の形）ので、対になったものだけを戻す形にした。
- 2026-11-03: テンプレート変数の説明は、DocManager の `assets/modules/docmanager/tv.ajax.php:59` と QuickManager の `assets/plugins/qm/inc/edit_tv.inc.php:134` では `hsc()` だけで出しており、D3 の B の規則と食い違う（`<strong>` が文字で出る）。選択肢のラベル（D4）はこの 2 つも `renderFormElement()` を通るので M3 でそろった。説明を M4 か M7 で範囲に入れるかは、着手時に決める。（2026-11-04: M4 に入れた。D14）
- 2026-11-04: M4 の検証で、修正前は要素の一覧で説明の `<script>` が実行され、閉じ忘れの `<strong>` が隣の行まで太字にしていたこと、モジュール・プラグイン・スニペットの編集画面を何も変えずに保存すると、guid・アイコン・リソースファイルが `"` や `&` で切れ、プロパティの `</textarea>` 以降が消えることを確かめた。修正後はどれも起きない。DataGrid のほかの利用者（ユーザー一覧・Web ユーザー一覧・モジュール一覧・DocManager・テンプレート変数の datagrid 出力）の表は、修正前と同じ HTML だった。
- 2026-11-04: `mutate_tmplvars.dynamic.php` の入力タイプの選択肢を組み立てる所は、`$row` を DB の読み込みと選択肢の配列の両方に使っている。`input:` の要素が 1 件でもあると、ループを出たとき `$row === false` に配列として書き込むので、PHP 8.1 以降で非推奨の警告が出る（修正前から。出力のエスケープとは別）。
- 2026-11-04: `system_eventnames` のイベント名に `'` があると、キャッシュの `siteCache.idx.php` が壊れて管理画面が 500 になる（DB を直接書いたときだけ。キャッシュ生成側の問題で、範囲外）。
- 2026-11-04: `mutate_module_resources.dynamic.php:213` の隠しフィールドの id は `$content['id']` のまま（DB から読んだ整数なので害はない。ほかの 4 画面は `(int)`）。
- 2026-11-04: M4b の検証で、修正前はプロパティの説明に `<img src=x onerror=…>` を入れると設定表で実行され、値の `"` で入力欄が切れることを確かめた。修正後は実行されず、値も文字のまま保たれる。
- 2026-11-04: 修正前のスニペット・モジュールの `list-multi` は、選択肢が二重に出て、選んだ値（`ar[3]`）を保てずに書き戻していた（プラグインは正しかった）。共通の JS にまとめて直った。実行時の `parseProperties()`（`document.parser.class.inc.php`）が `ar[3]` を見るのは `list` だけなので、スニペットやモジュールが受け取る値は変わらない。
- 2026-11-04: `menu` の選択値（`;b`）は、3 画面とも書き戻らない（修正前から。M4b では変えていない。実行時にも `menu` の選択値は使われない）。`if (currentParams[key] == ar[2])` の行は、配列と文字列の比較で成り立たないので消した。
- 2026-11-04: M4b の検証で、管理画面のシェルが画面を移るとき、同じ window で `<script src>` を読み直すことが分かった。最初は共通の JS を `const` で宣言していたので、2 回目以降に二重宣言のエラーになり、前の画面の設定（見出し・余白）も持ち越した。`tabpane.js` と同じく `var` への代入にし、`configure()` を既定値から作り直して直した。この決まりを `assets/docs/jquery-migration.md` に書いた。
- 2026-11-05: M5 の検証で、修正前はロールの編集画面を何も変えずに保存すると名前が `E&<b>` で切れること、ユーザーごとの設定（`editor_css_path`・`rb_base_dir` など）を 1 項目変えて保存すると、その値が `"` で切れて保存されること、入力エラーで差し戻した画面で値が切れて戻ることを確かめた。ユーザー編集とプロフィールの保存は、画面で変えた項目だけを送る（`changed_settings`）ので、何も変えずに保存したときは修正前も値が変わらない。修正後はどの経路でも値のまま保たれる。
- 2026-11-05: 管理ユーザーの編集画面の写真選択の JS（`mutate_user/tpl/javascript.php`）は、M5 の書き込むファイルに入っていなかったが、手順 6 と同じ形だったので M5 で直した。`tab_profile.php`・`mutate_user/functions.php`・`tpl/form.php`・`tpl/changed_settings.php`、ユーザー一覧（DataGrid の `colEscapes` 済み）、`messages.static.php` は対処済みか、出すのが言語文字列・コードの値・DB の整数だけだった。
- 2026-11-05: 範囲外で見つけたもの（別の Issue の候補）: ユーザーの保存の処理（`manager/processors/permission/save_web_user.processor.php`・`save_user.functions.php`）は、保存後の画面でユーザー名とパスワードを、`<strong>%s</strong>` を含む言語文字列 `password_msg` に生のまま入れている。同じファイルの `href="<?= $stayUrl ?>"` も `postv('stay')` を生のまま含む。どれも送信した本人の値だが、出力の直前でエスケープしていない。
- 2026-11-05: `selected()`（`mutate_user/functions.php`・`mutate_user_pf.dynamic.php`）は、前に空白を付けずに `selected` を返すので、`<option value="X"selected>` になる（ブラウザは解釈する。修正前から。出力のエスケープとは別）。
- 2026-11-05: M6 の検証で、修正前はファイル管理の場所（`filemanager_path`）に `&`・`'` があると最上位へのリンクが壊れて最上位を開けず、`"` があるとダウンロードのリンクが壊れることを確かめた。修正後はどれも動く。管理ログの `message` は、`logHandler` が `db()->escape()` だけで保存し、同梱の書き込み元（`getAction()`・サイトのエクスポート・ログイン・ファイル管理・DocManager）の文言は実体参照もタグも含まないので、一覧での `hsc()` は二重にならない。
- 2026-11-05: 範囲外で見つけたもの（別の Issue の候補。どれも M6 より前から）: ファイル管理は、`filemanager_path` の末尾の `/` の有無の前提が箇所ごとに混ざっている。末尾に `/` がないと、パンくずとダウンロードのパスが 1 文字ずれる（`$len` の計算）。末尾に `/` があると、最上位を開いても Top がリンクのままになる（`rtrim($startpath, '/') == config('filemanager_path')`）。全体の設定は保存時に `/` を付けるが、ユーザーごとの設定は付けない。また `webstart_path()` はファイルシステムのパスを URL エンコードせずに返すので、ディレクトリ名に `#`・`?`・`%`・空白があると、画像の表示とダウンロードの URL が切れる。

## Decision Log

- 2026-10-31 / planner（2026-11-01 にユーザーが推奨どおり確定）D1: `html_tag()` は今のまま値をエスケープせず、呼び出し側で値ごとに `hsc()` する。ヘルパーと `DocumentParser::html_tag()` の docblock に「属性値も中身もエスケープしない」と書く。根拠: 中身は入れ子の HTML を受け取る設計で、ヘルパー側ではエスケープできない。属性だけヘルパー側でエスケープすると、属性と中身で規則が分かれる。属性に DB の値を入れる 13 か所はすでに呼び出し側で `hsc()` しており、ヘルパー側に足すと全箇所を同時に直さない限り二重になる。`evo()->html_tag()` は公開メソッドなので、外部のスニペットやプラグインが `hsc()` 済みの値を渡していれば黙って二重になる（AGENTS.md の DB の取り決め「値を内部で無害化する方式は既存 API に足さない」と同じ理由）。代替案 B は「属性値だけヘルパー側で `hsc()` し、呼び出し側の `hsc()` を同じ PR で外す」。新しく書く呼び出しが自然に安全になる利点はあるが、外部の呼び出しの二重エスケープは避けられないので採らない。`input_text_tag()`・`textarea_tag()`・`select_tag()`・`img_tag()` も `html_tag()` を通るので同じ扱いにする。
- 2026-10-31 / planner D2: `form_text_tag()`・`Form::text()`・`join_attr()` は削除し、呼び出し 2 か所を `form_text()`（#885 で `hsc()` 済み）に置き換える。根拠: 呼び出しは各 1 か所で、同じ役目の `form_text()` がある（DRY）。`'…'` で囲む回避は、値に `'` と `"` の両方があると崩れる。`form_text()` が付ける `maxlength="255"` は、`site_name` と `manager_docs_orderby` の用途では問題にならない。`form_text()` は `evo()->config()` から読み、設定画面では `settings()`（`functions.inc.php:171`）が `evo()->config` をシステム設定で置き換えているので、表示する値は今と同じになる。
- 2026-10-31 / planner（2026-11-01 にユーザーが B に確定）D3: 要素の説明の表示。選択肢は 3 つ。A: 説明は要素の編集者が書く信頼できる HTML として扱い、今のまま出す（`resources.static.php:338` の未加工の出力だけ `:141` と同じ `strip_tags()` にそろえる）。根拠は、スニペット・プラグイン・テンプレート変数（`@EVAL`）の編集者はもともと PHP を実行できること、モジュール一覧などで HTML として出すと決めてあること。B: 説明はテキストとして `hsc()` し、属性の無い `<b>`・`</b>`・`<strong>`・`</strong>` だけを戻す 1 つの関数を作り、説明を一覧で出すすべての箇所（`resources.static.php` の 2 か所、`modules.static.php`、`resource_selector.static.php`、リソース編集のテンプレート変数の説明 `mutate_content/fields.php:361`）で使う。インストーラが付ける `<strong>版</strong>` はそのまま太字で出る。C: すべて `hsc()` だけ。同梱の要素の説明に `<strong>` の文字が見えるようになる。推奨は B。Issue の意図（出力直前でそろえる）に沿い、今の見た目を保ち、説明を出す箇所の規則を一つにできる（SSOT）。A を選ぶなら、要素の説明を Issue の範囲から外し、Issue にその旨を書く。B の関数の置き場所は `manager/includes/helpers.php`（管理画面と DataGrid の両方から使うため）。名前の案は `element_description_html($text)`。
- 2026-10-31 / planner（2026-11-01 にユーザーが推奨どおり確定）D4: テンプレート変数の選択肢のラベル（`document.parser.subparser.trait.php:1655, 1698, 1726`）は、ドロップダウン・リスト・チェックボックス・ラジオのすべてで `hsc()` する。根拠: ラベルは `@SELECT` などで要素の編集者以外が書いた値からも作られる。`<option>` の中身はもともとタグを書けない。代替案は「チェックボックスとラジオのラベルだけ HTML のまま」で、ラベルに HTML を書いているサイトの見た目は保てるが、規則が分かれ、`@SELECT` の値を守れない。`isSelected()` の比較にはエスケープ前のラベルを使い、出力にだけ `hsc()` を通す。
- 2026-10-31 / planner D5: 差し替えできるテンプレート（`login.tpl`・`welcome.tpl`・`manager.lockout.tpl`）へ渡すプレースホルダーは、HTML に入れる前提で `hsc()` 済みの値にする。同梱の `manager.lockout.tpl` で `[+homeurl+]` を JS の `'…'` に入れている所は、`data-href="[+homeurl+]"` を付けたボタンから `this.dataset.href` を読む形に書き換える。根拠: プレースホルダーに `js_literal()` の値を入れると、差し替えたテンプレートが `'[+homeurl+]'` と書いていた場合に引用符が二重になって飛び先が壊れる。`hsc()` の値なら、JS の中に書いたままのテンプレートでも安全側に倒れ、`&` などを含む URL 以外は今までどおり動く。
- 2026-10-31 / planner D6: 差し替えできない `jscripts.tpl` は、プレースホルダーに `js_literal()` の値を入れ、テンプレート側の `'…'` を外す（`preview_url` と同じ形）。`change_url_suffix()` の `innerHTML` は `textContent` にする。
- 2026-10-31 / planner D7: 数値として使う設定値は `(int)` にする（`default_template`、`preview_mode`）。文字列としてエスケープするより意図が明確で、値の形が崩れていても JS が壊れない。
- 2026-10-31 / planner D8: 言語文字列を受け取る関数（`welcome.static.php` の `get_icon()`、`menu.php` の `item()`）の中ではエスケープせず、DB の値を渡す呼び出し側で `hsc()` する（言語文字列は HTML を含むため。Surprises を参照）。
- 2026-10-31 / planner D9: 触る行にある `htmlspecialchars()` の直書きは `hsc()` に置き換える（PHP 7.4 で `'` を変換しないため）。触らない行は今回の範囲に入れない。
- 2026-10-31 / planner D10: `document_data.static.php` の代入時の一括 `hsc()` は今回動かさない。今は安全で、`entity()` の出力 30 か所余りを出力直前の形へ移すのは別の整理になる。今回は一括エスケープを通らないテンプレート名（`:221`）だけに `hsc()` を足す。
- 2026-10-31 / planner D11: 範囲外にするもの。言語ファイルの文字列（コアが書く HTML）、`$_style` の値・`MODX_*` の定数・`manager_style_image_path()` など、コードが決める値。`<head>` へ出す `manager_inline_style`（`tree.php:33` など。HTML を差し込む設定として意図した出力）。ただし設定画面の `textarea` での表示（`tab4_manager_settings.inc.php:30`）は範囲に入れる。
- 2026-10-31 / planner D12: PR はマイルストーンごとに 1 本に分ける（Issue の対応案どおり、画面のまとまりごと）。M1 は D1・D2 の方針を含むので最初に出す。M2〜M6 は互いに依存しないが、D3・D4 の決定を待つのは M3・M4 だけ。
- 2026-11-04 / メイン（ユーザーが選択）D13: M4 の手順 2 は、DataGrid が DB の結果か区切り文字の文字列しか受け付けず、変換済みの配列を渡せないと分かった。DataGrid の列の種類に `description` を足し、`formatColumnValue()` で `element_description_html()` を通す形にした。代替案は「説明の列を `colEscapes` で `hsc()` だけにする」（同梱の要素の `<strong>版</strong>` がこの 2 画面だけ文字で出て D3 と食い違う）と「M4 では触らず後で決める」。列の種類なら呼び出し側は `colTypes` を書くだけで、説明の規則が全画面で 1 つになる（SSOT）。`description` の列は `colEscapes` を無視する（二重にしないため）。
- 2026-11-04 / メイン D14: DocManager と QuickManager のテンプレート変数の説明（M3 の Surprises）は、M4 に入れて `element_description_html()` にそろえた。QuickManager はフロントで動くが、`document.parser.class.inc.php` → `initialize.inc.php` → `helpers.php` と読み込まれるので使える。
- 2026-11-04 / メイン D15: M4 の自己レビューで、プロパティの設定表を JS で組み立てるときに保存された値を `innerHTML` に生で入れていると分かった（4 画面）。M4 には入れず、M4b に分けた。4 画面の JS をまとめて書き換え、共通にするかの判断も伴うため。M4 で悪くなった所ではない（M4 の前は `</textarea>` で入力欄ごと抜けられたが、M4 でふさいだ）。プロパティを書けるのは要素を編集できる人で、もともと PHP を実行できる。
- 2026-11-04 / メイン D16: M4b では、プラグイン・スニペット・モジュールの `showParameters()`・`setParameter()`・`implodeParameters()`・`encode`・`decode`・`currentParams` を、共通の JS `manager/media/script/element-properties.js`（グローバル `EvoElementProperties`。`configure`・`show`・`set`・`params`）にまとめた。3 画面はほぼ同じ実装で、違い（表の属性・見出し・1 列か 2 列か・style）は `configure` に渡す。基準はプラグインの実装（`list-multi` に対応し、入力欄に id を付けている）。テンプレート変数の画面は、保存先が `params`、値の形が `&key=value`、`encode` の対象が広い、`widgetParams`・`lastmod`・`resetParameters` があるなど作りが違うので共通にせず、その画面の中で DOM で組み立てた。組み立ては `createElement`・`textContent`・`value`・`addEventListener` だけで、エスケープ用の JS の関数は作っていない。消したグローバル関数・変数（`setParameter`・`implodeParameters`・`encode`・`decode`・`currentParams`）は、同梱のコードからは使われておらず、互換のための別名は残さない。選択欄の幅は、元のスニペット・モジュールに合わせて全種類 168px にそろえた。

## Outcomes & Retrospective

2026-11-05（M7 の時点）:

- 管理画面の保存された値を、出力の直前で文脈に合ったヘルパーに通す形に、M1〜M6 でそろえた。PR は M1 #1009、M2 #1015、M3 #1018、M4 #1022（#1003 もあわせて閉じた）、M4b #1024、M5 #1027、M6・M7 は最後の PR（`Closes #895`）。
- 修正前と修正後の比較（各段階で Docker の PHP 8.4・MariaDB 11、Playwright）: 修正前は、設定値・ロール名・要素の名前や説明・プロパティ・ユーザーの設定・ファイル名などで生の値が出て、`<script>` が実行される画面（テンプレート変数の説明・要素の一覧・プロパティの設定表）や、何も変えずに保存すると値が切れる画面（設定・ロール・モジュールなど）があった。修正後はどの段階も検証項目がすべて PASS。
- M7 の横断の確認: M1〜M6 で変えた PHP 47 件は `php:7.4-cli` で `php -l` が通り、JS 2 件は `node --check` が通った。security-lint は `0 violations`。PHP 7.4.33 の検証環境（`php:7.4-apache`）でインストール・`smoke.js`（6/6）・各段階の確認スクリプトを流し、PHP 8.4 のときと同じ判定だった。コンテナのログに PHP の警告・エラーは出なかった。
- 計画から変わったこと: M4 の手順 2 は DataGrid に列の種類 `description` を足す形にした（D13）。DocManager・QuickManager の説明を M4 に入れた（D14）。プロパティの設定表を M4b に分け、共通の JS にまとめた（D15・D16）。各段階の自己レビューで、数として使う設定値（`site_start`・`mail_check_timeperiod`・`upload_maxsize` など）の `(int)` や、差し戻しで送信値が出る id の `(int)` を足した。
- 学び: 出力のエスケープの漏れは、「画面を開いて何も変えずに保存し直すと値が変わる」形で表に出ることが多く、検証の項目として効いた。管理画面のシェルは画面の JS を同じ window で読み直すので、共通の JS は `var` か `window` への代入で定義し、状態を作り直す（`assets/docs/jquery-migration.md`）。
- 残したもの（別の Issue の候補。Surprises に詳細）: インポートでファイル名に `'` があると SQL の構文エラーになる件、`manager_theme` の出力、ユーザーの保存後の画面のユーザー名・パスワード・`stay` の URL、ファイル管理の `filemanager_path` の末尾の `/` と `webstart_path()` の URL エンコード、イベント名に `'` があるとキャッシュが壊れる件、テンプレート変数の入力タイプの選択肢の `$row` の使い回し、`selected()` の空白、設定画面の言語文字列の `addslashes()`。

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
7. D3 で B のとき: `helpers.php` に `element_description_html(?string $text): string` を足す。中身は `hsc((string) $text)` のあと、対になった属性なしの `<b>…</b>`・`<strong>…</strong>`（大文字小文字を問わない）だけを元のタグに戻す。入れ子も戻るよう、変化がなくなるまで最大 10 回繰り返す（それより深い入れ子の内側は文字のまま）。対の取れない開きタグ・閉じタグと、属性付きのタグは文字として出る。交差した入れ子（`<b><strong>x</b></strong>`）はそのまま戻すが、どのタグも対になっていて、ブラウザが組み直すので説明の外へ太字は漏れない。docblock に「要素の説明を管理画面に出すとき用」と書く。

期待される観測結果: エイリアスやテンプレート変数の選択肢に `&`・`<`・`"`・`'` を含む値があっても、リソースの編集画面・情報画面・子リソース一覧が崩れず、プレビューや「表示」ボタンが正しい URL を開く。エイリアスの入力でサフィックスの表示が切り替わる。テンプレート変数の選択肢を選んで保存し、開き直すと選んだ状態が保たれる。

推奨コミット: `fix(manager): リソースの編集・情報画面で設定値と選択肢のラベルをエスケープする`

### M4: 要素（一覧・編集画面・テンプレート変数）

書き込むファイル: `manager/actions/element/resources.static.php`、`mutate_module.dynamic.php`、`mutate_plugin.dynamic.php`、`mutate_snippet.dynamic.php`、`mutate_templates.dynamic.php`、`mutate_tmplvars.dynamic.php`、`mutate_module_resources.dynamic.php`、`modules.static.php`、`resource_selector.static.php`（いずれも `manager/actions/element/` の下）、`manager/includes/controls/datagrid.class.php`（D13）、`assets/modules/docmanager/tv.ajax.php`・`assets/plugins/qm/inc/edit_tv.inc.php`（D14）。計 12 ファイル。

1. `resources.static.php`: `:141` と `:338` の説明を D3 に従ってそろえる（B なら両方 `element_description_html()`。`:338` は `' - ' .` の後ろに通す）。`:319`・`:321` のカテゴリー名を `hsc($v['category'])` にする（`:130` の一覧と同じ形）。`:321` の `deleteCategory('…')` に入る `catid` は整数なので `(int)` にする。`:336` の `htmlspecialchars($v['name'], ENT_QUOTES, …)` は `hsc()` にそろえる（D9）。
2. `datagrid.class.php` の `formatColumnValue()` に列の種類 `description` を足し、`element_description_html()` を通す（D13。`colEscapes` は無視する）。`modules.static.php` と `resource_selector.static.php`（要素のとき。文書（`rt=doc`）のときは説明の列にロングタイトルが入るので、従来どおり全部エスケープ）の説明の列の `colTypes` を `description` にする。
3. `mutate_module.dynamic.php`: `:446` の説明、`:452` のアイコン、`:465` のリソースファイル、`:486` のプロパティを `hsc(content(…))` にする。`:528` の `guid` を `hsc()`。`:637` のユーザーグループ名を `hsc($row['name'])`。`:302` の `"<?= $base_url ?>manager/…"` を `js_literal($base_url . 'manager/media/browser/evo/browser.php?type=images')` にする（M5 の同じ形とそろえる）。
4. `mutate_plugin.dynamic.php`: `:742` の説明と `:606` のプロパティを `hsc(entity(…))`、`:575` の `guid` を `hsc()`（`value='…'` を `value="…"` にそろえる）、`:679-682` のイベント名（`id`・`for` 属性と本文）を `hsc($row['name'])` にする。
5. `mutate_snippet.dynamic.php`: `:387` の説明と `:471` のプロパティを `hsc(entity(…))`、`:446` の `guid` を `hsc()` にする。`:344` の `$code` は `:336-338` で `hsc()` 済みなので触らない。
6. `mutate_templates.dynamic.php:262-264`: テンプレート変数の名前を `hsc($row['name'])`、説明（`$row['desc']`）を D3 に従ってそろえる（B なら `element_description_html()`）。
7. `mutate_tmplvars.dynamic.php`: `:537` のテンプレート名を `hsc($row['templatename'])`、`:655` の文書グループ名を `hsc($row['name'])` にする。
8. `mutate_module_resources.dynamic.php:223`: モジュール名を `hsc($content['name'])` にする。依存関係の一覧（`:255-257`）は `colEscapes` 済み。
9. `tv.ajax.php`（DocManager）と `edit_tv.inc.php`（QuickManager）のテンプレート変数の説明を、`hsc()` から `element_description_html()` にする（D14）。
10. 自己レビューで足したもの: `mutate_tmplvars.dynamic.php` の入力タイプの選択肢（名前が `input:` で始まるスニペット・プラグインの名前）を `hsc()`、`mutate_plugin.dynamic.php` のイベントのグループ名を `hsc()`、module・plugin・snippet・tmplvars の隠しフィールドの id を `(int)`。

期待される観測結果: 要素の名前・説明・カテゴリー名・プロパティに `&`・`<`・`"`・`'` を含む値を入れて保存しても、要素の一覧と各編集画面が崩れず、何も変えずに保存し直しても値が変わらない。同梱の要素の説明の版番号の表示は D3 の選択どおり（B なら太字のまま）。

推奨コミット: `fix(manager): 要素の一覧と編集画面で保存された値をエスケープする`

### M4b: 要素の編集画面のプロパティの設定表

書き込むファイル: `manager/actions/element/` の `mutate_plugin.dynamic.php`・`mutate_snippet.dynamic.php`・`mutate_module.dynamic.php`・`mutate_tmplvars.dynamic.php`、新しく作る共通の JS `manager/media/script/element-properties.js`（D16）。検証と自己レビューで、`manager/media/script/tabpane.js`（コメントだけ）と `assets/docs/jquery-migration.md`（シェルで読み直す JS の決まり）も足した。

1. 4 画面の `showParameters()` は、プロパティの説明（`desc`）・値（`value`）・リストの項目（`ls[i]`）を文字列の連結で HTML にして `innerHTML` に入れている（`mutate_tmplvars` は `&` と `"` だけ置き換え、`<` は置き換えていない）。`onchange="setParameter('" + key + "', …)"` は、HTML の属性の中に JS の文字列がある二重の文脈になっている。
2. エスケープ用の JS の関数は作らず、`createElement` と `textContent`・`value`・`addEventListener` で組み立てて、エスケープを要らなくする（Plan of Work の 4）。4 画面がほぼ同じ実装なら、1 つの共通の JS にまとめる（SSOT）。

期待される観測結果: プロパティの説明・値・リストの項目に `&`・`<`・`"`・`'`・`</textarea>`・`<img src=x onerror=…>` を入れても、設定表が崩れず、何も実行されない。値に `"` があっても入力欄が崩れず、何も変えずに保存し直しても値が変わらない（修正前は `value="' + value + '"` で崩れる）。

推奨コミット: `fix(manager): 要素の編集画面のプロパティの設定表を DOM で組み立てる`

### M5: ユーザー・ロール・権限

書き込むファイル: `manager/actions/permission/role_management.static.php`、`mutate_role.dynamic.php`、`access_permissions.dynamic.php`、`web_access_permissions.dynamic.php`、`mutate_web_user.dynamic.php`、`mutate_user_pf.dynamic.php`、`mutate_user/tpl/form/tab_access.php`、`mutate_user/tpl/form/tab_general.php`、`mutate_user/tpl/form/tab_page5.php`、`mutate_user/tpl/form/tab_page7.php`、`mutate_user/tpl/form/tab_settings.php`（いずれも `manager/actions/permission/` の下）。計 11 ファイル。自己レビューで `mutate_user/tpl/javascript.php` を足した（計 12 ファイル）。

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
5. `files.dynamic.php:402` の `MAX_FILE_SIZE` の `value` に入る `evo()->config('upload_maxsize', …)` を `(int)` にする（D7。M3 でリソースの編集画面の同じ値を `(int)` にしたのとそろえる）。

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
- ブランチ: `fix/895-manager-output-escape`（ワークツリー `.claude/worktrees/issue-895`）。PR はマイルストーンごとに 1 本（D12）
- PR: M1 は #1009、M2 は #1015、M3 は #1018、M4 は #1022（#1003 もあわせて閉じた。どれもマージ済み）。#1009 のマージで、ブランチの Issue との紐づけにより #895 が閉じたので開き直した。ブランチはその後に作り直したので、Issue とは紐づいていない（M2 以降の PR は `Refs #895` にし、M7 の最後の PR だけ `Closes #895` にする）
- 関連: #885（設定画面の `form_text()`）、#891（設定画面とリソース編集画面の一部の欄）
- 出力のエスケープの取り決め: `AGENTS.md` の「補助ルール」→「出力のエスケープ」
- 既存のエスケープの例: `manager/includes/error.class.inc.php:53-63`（`js_literal(hsc())` の使い分けの説明）、`manager/actions/document/move_document.dynamic.php:95-104`（`textContent` で組み立てる例）、`manager/frames/nodes.php:190, 193`（`hsc(js_literal())` の例）
- DataGrid の列のエスケープ: `manager/includes/controls/datagrid.class.php:41`（`colEscapes`）、`:191-213`

## Interfaces and Dependencies

- `hsc()`・`js_literal()`（`manager/includes/helpers.php`）を使う。新しく足すのは、D3 で B を選んだときの `element_description_html(?string $text): string`（`manager/includes/helpers.php`。M3 で追加済み。対になった属性なしの b・strong だけを戻す）だけ。
- 削除する関数: `form_text_tag()`・`join_attr()`・`Form::text()`（`manager/actions/tool/mutate_settings/functions.inc.php`）。設定画面の中だけで使われていて、外部から呼ばれる前提のものではない。
- 振る舞いが変わる公開の部分: `DocumentParser::renderFormElement()` が作るテンプレート変数の選択肢のラベルがエスケープされる（D4。ラベルに HTML を書いていたサイトでは、タグが文字として見える）。差し替えたログイン・ダッシュボード・ロックアウトのテンプレートに渡る `[+site_name+]`・`[+homeurl+]` が `hsc()` 済みの値になる（D5）。`html_tag()` の振る舞いは変えない（D1）。DataGrid の列の種類に `description` が加わる（D13）。テンプレート変数の出力ウィジェット datagrid の `ctype` や `db()->getHTMLGrid()` の `colTypes` で `description` を指定していた場合は、`colEscapes` に関係なく `element_description_html()` を通るようになる（安全側の変化。同梱の利用者はいない）。
- PHP 7.4 以上。外部のライブラリは使わない。
