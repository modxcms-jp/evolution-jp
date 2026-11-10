# ExecPlan: GET と POST の両方を読むヘルパーを、実際の入口に合わせて get_ / post_ に絞る（Issue #1070）

## Purpose / Big Picture

管理画面・同梱の部品・インストーラが受け取るリクエスト値を、その値が実際に届く場所（URL のクエリなら GET、フォームの本文なら POST）から読むようにする。コードを読めば「この値はどこから来るか」が分かり、別の場所に同じ名前の値を置いて処理を食い違わせる余地がなくなる。GET と POST の両方を読むのは本当に両方から届く値だけにし、その型つきヘルパーの名前を生の値の `anyv()` とそろえて `any_*var()` にする。

Issue: #1070

## Progress

- [ ] (2026-11-10) M0: 全呼び出しの判定表を作り、修正前（main）の動きを記録する（コードは変えない）
- [ ] M1: `any_*var()` を足し、`abort_if_invalid_id()` が値を受け取る形にし、決まり（`assets/docs/request-input.md`・AGENTS.md）と security-lint をそろえる（動きは変えない）
- [ ] M2: 入口と共通の処理（`manager/index.php`・`manager/ajax.php`・`manager/includes/`・`manager/frames/`・`manager/actions/header.inc.php`。15 ファイル・30 か所）
- [ ] M3: 文書の画面（`manager/actions/document/`。7 ファイル・67 か所）
- [ ] M4: エレメントとユーザー・権限の画面（`manager/actions/element/`・`manager/actions/permission/`。14 ファイル・61 か所）
- [ ] M5: 処理（`manager/processors/`。13 ファイル・40 か所）
- [ ] M6: ファイルブラウザ（`manager/media/`。3 ファイル・15 か所）
- [ ] M7: 同梱のプラグイン・スニペットとインストーラ（`assets/`・`install/`。12 ファイル・24 か所）
- [ ] M8: `request_*var()` を消し、文書・lint を仕上げ、PHP 7.4 を含めて横断で確かめる

## Surprises & Discoveries

計画時（2026-11-10、main 6ef1f76d3 で調査）に分かったこと:

- Issue に書いた「`anyv()` は `$_REQUEST` を読むので cookie が混ざる」は、すでに解消している。`anyv()` は 08bf5f763（#1073、2026-11-07 に閉じた）から `array_replace_recursive($_GET, $_POST)` を読み、cookie を含まない（`manager/includes/helpers.php` の `anyv()`）。本体で `$_REQUEST` を読むのは、テンプレートの `[[$_REQUEST[...]]]` のタグ（`DocumentParser::getSuperGlobalSource()`。これも `anyv()` を返す）だけで、ほかは起動時の無害化（`sanitizeVars()`）と、プラグインのために `$_REQUEST['refurl']` を書く 1 か所（`manager/includes/traits/document.parser.subparser.trait.php`）。cookie が混ざらないことは、この計画では各段階の回帰の確認（Validation の V3）として残す。
- Issue とロードマップにある `evo()->input_any()` は、#1059 の M6b で消えている。対象は `anyv()` と `request_*var()` と、`anyv()` を中で読む `abort_if_invalid_id()` の 3 つ。
- 呼び出しの数は Issue の「300 か所ほど」より少ない。2026-11-10 の main で、`helpers.php` の定義とコメントの行を除いて 64 ファイル・237 か所（`anyv(` 39、`request_intvar(` 149、`request_strvar(` 26、`request_choicevar(` 10、`request_idsvar(` 3、`request_uintvar(` 2、`request_textvar(` 1、`abort_if_invalid_id(` 7。`request_boolvar()` の呼び出しは 0）。#1059 で `getv()` / `postv()` に寄せた箇所が多かったため。
- 「リクエストのメソッド」と「値の置き場所」は一致しない。状態を変える操作（`csrfProtectedActions()`、`manager/includes/csrf_token.php`）は POST でしか受けないが、JS の `EvoCsrf.post(url)`（`manager/media/script/csrf.js`）は「id などのパラメータは url のクエリに残し、本文はトークンだけにする」。たとえば公開（`a=61`）は POST で届くが、`id` は `$_GET` に入る。`action` を書かないフォームは今の画面の URL へ送る（`manager/media/script/shell.js` の `EvoShell.submit()`）ので、URL のクエリの値は `$_GET`、欄の値は `$_POST` に入る。受け取り元は、メソッドではなく値の置き場所で決める必要がある。
- 振り分けの番号 `a` は本当に両方から届く。リンクや `EvoShell.go()` はクエリ、いくつかのフォームは hidden の `a` を本文で送る（`manager/actions/permission/access_permissions.dynamic.php` の `a=41`、`manager/actions/tool/bkmanager.static.php`・`import_site.static.php`・`mutate_settings.dynamic.php`、`manager/actions/element/mutate_module_resources.dynamic.php`、`manager/actions/document/move_document.dynamic.php`・`publish_draft.dynamic.php`、文書の編集画面のテンプレートの切り替え、セーフモードの切り替えのフォーム `manager_safe_mode_toggle_form()`）。`manager/index.php` の振り分けは両方を読むまま残す。一方、画面の側でも `request_intvar('a')` で `a` を読み直している（エレメントの 6 画面、ユーザー・Web ユーザー・ロールの画面、`resource_selector.static.php`、`manager.lockout.inc.php`、`log.class.inc.php`）。#1059 の M3b・M5a で「画面の判定は振り分けと同じ読み方にそろえる」と決めた理由（振り分けと画面で値が食い違うと権限の判定が働かない）からすると、画面は読み直さず、振り分けが決めた `manager()->action` を使うのが筋。
- 同じ処理でも、操作によって同じ名前の値が届く場所が違うものがある。アクセス権の処理（`a=41`・`a=92`）は、追加・名前の変更のフォームでは `operation` と値を本文で、削除・関連の解除のボタン（`data-evo-post`）ではクエリで受ける。`operation` は両方、各操作の値は操作ごとに片方になる。
- 文書の編集画面（`a=27`・`a=4`・`a=72`、下書きの `a=131`・`a=132`）は、リンクやリダイレクト（`?a=27&id=…`、`?a=4&pid=…&newtemplate=…`）と、テンプレートやエディターを切り替えたときに自分自身へ送るフォーム（`manager/media/style/common/jscripts.tpl` の `setMutateValue('newtemplate', …); submitMutate()`、hidden の `id`・`pid`・`newtemplate`）の両方で開く。`id`・`pid`・`newtemplate` は本当に両方から届く。保存後の行き先 `after_save` の既定を読む `EvoFormFlow::requestedAfterSave()` も、コメントのとおり GET（保存後の戻り）と POST（文書の再表示）の両方。
- `abort_if_invalid_id($key, $required)`（`manager/includes/helpers.php`）は中で `anyv($key)` を読む。エレメントの画面の `id` を GET に絞ると、確かめる値（両方）と使う値（GET）が食い違う。1.2 系には無い関数（1.2.4J に無い）なので、値を受け取る形に変えてよい。
- ログインの処理で「ログイン状態を保持」の読み方がそろっていない。`input()`（`manager/processors/login.processor.functions.php:152-175`）は `post_strvar('rememberme')` で読むが、ログインの成功の処理（同 553 行）は `request_strvar('rememberme')` で読む。画面（`manager/media/script/login.js`）は本文で送る。自動ログインのトークンの処理（`DocumentParser::token_auth()`）は `get_strvar('auth_token')` と同じリクエストで `request_strvar('rememberme')` を読む。
- 公開側では、`anyv()` と `postv()` で値の無害化が違う。システム設定 `sanitize_gpc`（既定 1）のとき、起動時の `sanitizeVars()`（`DocumentParser::executeParser()` からだけ呼ぶ）は `$_GET`・`$_COOKIE`・`$_REQUEST` を無害化し、公開側の `$_POST` は無害化しない。`anyv()` は無害化した値を返す。公開側の値を `any_` から `post_` に絞ると、`[[` や `<script` を含む値が無害化されずに届くようになる。`get_` に絞るなら変わらない。管理画面では `sanitizeVars()` が呼ばれないので、`anyv()` も無害化せず、絞っても値は変わらない。
- `ManagerAPI::isAllowed($id)`（`manager/includes/extenders/ex_managerapi.php:1007`）は、`$id` が 0 のとき中でリクエストの `pid` を読む。汎用の API がリクエストを読む形で、#1059 で DocAPI について決めた「汎用の API はリクエストを読まない」（#1059 の Decision Log、2026-11-06 の M5a の自己レビュー）に合わない。
- 今の main で、状態を変える操作を GET で受けているものは、調べた範囲では、一覧から意図して外したもの（モジュールの実行 `a=112`、ツリーの開閉・並び順をセッションに覚えること）だけだった。保存・削除などは `csrfProtectedActions()` で GET を 405 にしている。TV の並び順（`a=117`）・プラグインの優先順位（`a=100`）・設定の ajax（`a=118`）の更新も `postv()` で POST のときだけ動く。そのため、この計画で「GET で動いていた操作が受け付けられなくなる」ことは、画面の操作では起きない見込み。利用者から見て変わりうるのは「本来と違う場所に置いた値が読まれなくなる」ことで、外部の拡張がそう送っている場合に限られる。
- `request_intvar()` は 1.2.0J から配布している（`git grep "function request_intvar" release-1.2.0J -- manager/includes/helpers.php`）。ほかの `request_*var()` は #1059 で足したもので、まだ配布していない（1.2.4J に無い）。

## Decision Log

- 2026-11-10 / Claude（planner）: 受け取り元は「値の置き場所」で決める。URL のクエリに載る値は `get_*` / `getv()`、フォームや `fetch` の本文に載る値は `post_*` / `postv()`。リクエストのメソッドでは決めない（POST のリクエストでもクエリの値は `$_GET` に入る。Surprises）。`assets/docs/request-input.md` の「受け取り元の選び方」をこの形に書き直す。
  理由: `EvoCsrf.post(url)` がパラメータをクエリに残す作りなので、メソッドで決めると、状態を変える操作の `id` を `post_` で読んで壊す。
- 2026-11-10 / Claude（planner）: `any_*` / `anyv()` を残してよいのは、次の 5 つに当てはまる値だけにする。判定表の「理由」の欄にはこの記号を書く。
  - K1 入口の振り分けと、どの画面でも読む共通の値: `manager/index.php` の `a`、アクティブユーザーの記録とログの `id`（どの画面のリクエストでも読む）
  - K2 同じ処理が、クエリで届く入口と本文で届く入口の両方を持つもの: 文書の編集画面の `id`・`pid`・`newtemplate`、モジュールの依存関係の画面の `id`、アクセス権の処理の `operation`、インストーラの `action` など。判定表に両方の送り手を書く
  - K3 守りの判定: `isCsrfProtectedAction()` のように「この要求は守る対象か」を決める値。処理が読む場所を必ず含む広さで読む（処理より狭く読むと守りを抜けられる）
  - K4 入口を本体の外（サイトのテンプレート・モジュール・プラグイン）が決めるもの: テンプレートの `[[$_REQUEST]]` のタグ、Ditto の request extender、公開側のスニペットのフォームの値、モジュールの実行（`a=112`）の `id`、「GET・POST の」と書いて公開している `baseTime`
  - K5 API の作りとして、読む操作は GET、書く操作は POST で同じ名前を送るもの: ファイルブラウザの `scope`・`action`・`type`（本文が `post_max_size` を超えたときのために URL にも付けている。`manager/media/browser/evo/js/api.js` の `postUrl()`）
  代わりの案: 「メソッドが GET と POST の両方で来るもの」を残す基準は、上の理由で採らない。基準を書かずに 1 か所ずつ判断する案は、残したものの理由がそろわないので採らない。
- 2026-11-10 / Claude（planner）: 名前の変更は、最後にまとめてではなく、各段階で 1 か所ずつ決める。M1 で `any_*var()` を足し、M2〜M7 では、絞るものは `get_*` / `post_*` に、残すものは `any_*` に、同じ編集で書き換える。M8 で呼び出しが 0 件になった `request_*var()` を消す。
  理由: 1 つの行を 2 回書き換えない（Issue の「先に名前を変えると同じ箇所をもう一度書き換える」と同じ考え）。M2〜M7 の差分を見れば、その段階で残した `any_*` が理由つきで読める。
  代わりの案: 最後にまとめて `request_*` → `any_*` を置換する案は、残すと判断した行を M8 でもう一度触ることになり、判定表との照合も二度になるので採らない。
- 2026-11-10 / Claude（planner）: 画面の側で `a` を読み直している箇所（エレメントの 6 画面、ユーザー・Web ユーザー・ロールの画面、リソースの選択の画面の hidden、`manager.lockout.inc.php`、`log.class.inc.php`）は、`any_intvar('a')` にせず `manager()->action` を使う。`manager()->action` は `manager/index.php` が `request_intvar('a')` で 1 回だけ読んで決めた値（指定されていて正の整数ならその int、正の整数でなければ `''`）で、画面に入るのは `a` が指定されたときだけなので、`(int) manager()->action` は今の `request_intvar('a')` と同じ値になる。
  理由: 振り分けと画面で `a` の読み方が食い違わない形を、読み直しの書き方ではなく 1 つの値で保証できる（SSOT）。`any_` の残りも減る。
  代わりの案: `any_intvar('a')` に改名するだけの案は、同じ値を複数の場所で読む形が残るので採らない。
- 2026-11-10 / Claude（planner）: `abort_if_invalid_id()` は、キーではなく値を受け取る形（`abort_if_invalid_id($value, $required = false)`）にし、受け取り元は呼び出し側が `getv('id')` / `anyv('id')` で選ぶ。M1 では 7 か所の呼び出しを `anyv('id')` にして動きを変えず、M3・M4 で判定表どおりに絞る。
  理由: 確かめる値と使う値を同じ受け取り元にそろえる。関数の役目は確かめることで、取り出すことではない。1.2 系に無い関数なので、互換の手当ては要らない。
  代わりの案: `get_` / `any_` を付けた 2 つの関数にする案は、取り出し口を増やすだけなので採らない。
- 2026-11-10 / Claude（planner）: 文書の編集画面（`mutate_content.dynamic.php`・`mutate_draft.dynamic.php`・`mutate_content/functions.php`・`mutate_content/fields.php`）の `id`・`pid`・`newtemplate` は K2 として `any_intvar()` に改めるだけにし、入口で 1 回だけ読んで変数で渡す作りへの組み替えはしない。
  理由: 受け取り元はすでに 1 つ（両方）にそろっていて、食い違いは起きない。組み替えは関数の引数を変える広い変更になり、この Issue の目的（受け取り元を絞る）から外れる。
- 2026-11-10 / Claude（planner）: 公開側の値は、`get_` に絞るのはよいが、`post_` に絞るのは、無害化が外れても困らないこと（出力やタグの解析に使わない、int や選択肢として読む）を判定表に書けたものだけにする。書けないものは K4 として `any_` に残す。
  理由: 公開側の `$_POST` は `sanitize_gpc` で無害化されない（Surprises）。受け取り元を絞る作業で、無害化の有無まで変えない。
- 2026-11-10 / Claude（planner）: M0 で全呼び出しの判定表を作る。置き場所は `.agent/plans/2026-11-10-request-source-narrowing-inventory.md`（この ExecPlan と同じ所。完了したら一緒に archive へ移す）。1 行は「ファイル・キー・今の書き方」の組（同じファイルの同じキーの呼び出しはまとめ、行番号を並べる）。
  理由: Issue の完了条件「残したものは理由を PR に書く」の根拠を 1 か所に置き、各段階の PR はその表の該当の行を写す。ジョブの一時領域は消えることがあり、レビューする人からも見えない。
  代わりの案: ExecPlan の中に表を置く案は、表が 100 行を超え ExecPlan が読みにくくなるので採らない。（置き場所はメインの判断で変えてよい）
- 2026-11-10 / Claude（planner）: マイルストーンは、#1059 と同じくディレクトリで分ける（M2〜M7）。M2 を最初にするのは、振り分けの `a` と共通の処理の判断が、後の画面の段階の前提になるため。1 つのマイルストーンを 1 つの PR にし、後の段階は前の段階のブランチの上に積む（#1059 と、夜間は未レビューのマージができない運用に合わせる）。
- 2026-11-10 / Claude（planner）: `ManagerAPI::isAllowed()` の `pid` の読み込みは、M0 で呼び出し元を確かめたうえで M3 で呼び出し側へ移す（`isAllowed($id, $pid = 0)` のように受け取る）。呼び出し元で `$id` が 0 になりうるのが文書の編集画面だけなら、そこで `any_intvar('pid')` を渡す。ほかにもあれば、M0 の判定表に書いて同じ形にする。
  理由: 汎用の API はリクエストを読まない（#1059 の決定）。ここで `any_` を 1 つ残すより、呼び出し側の 1 か所にまとまる。
- 2026-11-10 / Claude（メイン）: `request_*var()` の古い名前は、M8 で 7 つとも消す（planner の推奨どおり）。互換のために古い名前を本体に残さない方針と、互換を気にする外部のプラグインは TinyMCE 7 だけ（同梱なので M7 で一緒に直る）という方針に照らして決めた。
  planner の推奨と理由: M8 で 7 つとも消す。`evo()->input_*()` を #1059 で 1.3 で消したのと同じ扱いで、古い作法のための互換を本体に重ねない方針に合う。`request_intvar()` 以外はまだ配布していない。PR とリリースノートに `仕様変更` として置き換え先を書く。
  代わりの案: 1.2.0J から配布している `request_intvar()` だけを 1.4 まで残す（`any_intvar()` を呼び、呼ばれたら `evo()->logEvent()` で非推奨を記録する）。外部のスニペット・プラグインが呼んでいても Fatal error にならないが、別名が 1 つ残る。
- 2026-11-10 / Claude（メイン）: ファイルブラウザ（`manager/media/browser/evo/`）の `folder`・`foldersOnly` は、操作ごとに絞る（planner の推奨どおり）。
  planner の推奨と理由: 操作ごとに、今の JS（`manager/media/browser/evo/js/api.js`）が送る場所に合わせる（読む操作の `apiUrl()` はクエリなので get、書く操作の `postForm()` は本文なので post）。`scope`・`action`・`type` は K5 で any に残す。今の画面からの操作（V1）は変わらない。ファイルブラウザの API は本体の画面専用で、外部から呼ぶ作りではない。
  代わりの案: API の作りとして、`folder` も K5 で any に残す。書く操作にクエリで `folder` を送っても今までどおり動くが、「この値はどこから来るか」がコードから読めないまま残る。
- 2026-11-10 / Claude（メイン）: 着手は 1.3 のリリース後にする（ユーザーさんの判断）。全体で 30〜40 時間ほどの見込みで、M8 で古い名前を消すのは外部の拡張に関わる変更なので、1.4 に向けて入れるほうがリリースノートで案内しやすい。調べた範囲では、GET のまま状態を変える操作や cookie が混ざる問題は無く、急ぐ理由が薄い。この計画は着手まで `.agent/plans/` に置いておき、着手時に main の変化（呼び出しの数・行番号）を確かめ直してから M0 に入る。
- 2026-11-10 / Claude（メイン）: 判定表は、planner の案どおり別ファイル（`.agent/plans/2026-11-10-request-source-narrowing-inventory.md`）に置く。段階ごとのブランチ名も Artifacts の案どおり。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **リクエスト値**: ブラウザが送ってくる値。URL の `?id=5` のようなクエリ（PHP では `$_GET`）と、フォームや `fetch` の本文（`$_POST`）がある。POST のリクエストでも、送り先の URL にクエリがあれば、その値は `$_GET` に入る。
- **生の値のヘルパー**: `getv()`（`$_GET`）・`postv()`（`$_POST`）・`anyv()`（両方をまとめたもの。同じ名前は POST を優先）。`manager/includes/helpers.php`。
- **型つきのヘルパー**: 決まった型の値だけを返し、それ以外は既定値を返す。`get_` / `post_` / `request_` の接頭辞 × `intvar`・`uintvar`・`strvar`・`textvar`・`boolvar`・`choicevar`・`idsvar`。戻り値の規則は `assets/docs/request-input.md` が正本。この計画で `request_` を `any_` に改める。
- **受け取り元**: ヘルパーが値を読む場所（`$_GET`・`$_POST`・両方）。
- **入口（送り手）**: その値を送ってくる側。リンク（`<a href>`、`EvoShell.go()` / `navigate()`）、フォーム（`method` と `action`、hidden の欄、`formaction`）、JS（`fetch` / `Evo.fetch` / `EvoCsrf.post()` / `loadTreeRequest()` / ファイルブラウザの `apiUrl()`・`postForm()`）、リダイレクト（`header('Location: …')`、`EvoFormFlow::afterSaveUrl()` / `formUrl()`）、ほかの処理からの `include`（保存の処理が `header.inc.php` を読むなど）。
- **振り分けの番号 `a`**: 管理画面の `manager/index.php` が、どの画面・処理を読むかを決める番号。`manager()->action` に入る。
- **状態を変える操作**: `csrfProtectedActions()`（`manager/includes/csrf_token.php`）に載った操作。POST でしか受けず（GET は `manager/includes/accesscontrol.inc.php` で 405）、POST はすべて CSRF トークンを確かめる。
- **シェル**: 管理画面の `#mainPane` だけを入れ替える仕組み（`manager/media/script/shell.js`）。リンクは `fetch` の GET、フォームは `method` のとおりに `fetch` で送る。`action` の無いフォームは今の画面の URL（モーダルならモーダルの URL）へ送る。

関係するファイル（行は 2026-11-10 の main）:

- `manager/includes/helpers.php`: 型つきヘルパー（535〜766 行）、`abort_if_invalid_id()`（576）、`getv()` / `postv()`（800〜808）、`anyv()`（841〜851）、`is_post()` / `is_get()`。
- `manager/index.php`: `a` を読んで `manager()->action` を決める（59〜68、152〜159）。振り分けの `switch`（478〜834）。
- `manager/includes/csrf_token.php`: `csrfProtectedActions()`（201〜265）と `isCsrfProtectedAction()`（273〜290、`a=113` の `op` を `anyv()` で読む）。
- `manager/media/script/csrf.js`（`EvoCsrf.post()`）、`manager/media/script/shell.js`（`EvoShell.submit()`、645〜686）。
- `.github/scripts/security-lint.php`: `REQUEST_FUNCS`（237。SQL への埋め込みを見張る関数）と、型が決まっていて安全とみなす関数の一覧（244）に `request_*` の名前が入っている。
- 文書: `assets/docs/request-input.md`（「受け取り元の選び方」の節）、AGENTS.md（「スーパーグローバル変数の禁止」の `$_REQUEST → anyv()` と `request_boolvar()` の例）、`assets/docs/template-system.md`（`[[$_REQUEST]]` のタグは `anyv()` から読む。変えない）。

呼び出しの棚卸し（2026-11-10、`helpers.php` の定義とコメントの行を除く）:

| 段階 | 範囲 | ファイル | 呼び出し |
| --- | --- | --- | --- |
| M2 | `manager/index.php`・`manager/ajax.php`・`manager/includes/`（10）・`manager/frames/`（2）・`manager/actions/header.inc.php` | 15 | 30 |
| M3 | `manager/actions/document/` | 7 | 67 |
| M4 | `manager/actions/element/`（10）・`manager/actions/permission/`（4） | 14 | 61 |
| M5 | `manager/processors/`（document 8・permission 2・db 1・module 1・login 1） | 13 | 40 |
| M6 | `manager/media/browser/evo/` | 3 | 15 |
| M7 | `assets/plugins/`（4）・`assets/snippets/`（7）・`install/`（1） | 12 | 24 |
| 計 | | 64 | 237 |

計画時に送り手を調べた範囲での見込み（M0 で全行を確かめて判定表に移す。「要確認」は送り手を全部は見ていないもの）:

| ファイル | キー | 送り手 | 見込み |
| --- | --- | --- | --- |
| `manager/index.php` | `a` | クエリと、hidden の `a`（Surprises） | any（K1） |
| `manager/index.php` | `f` | ツリー・メニューの再取得はクエリ、セーフモードのフォームは本文（`f=menu`） | any（K2） |
| `manager/index.php` | `ajaxa` | `Evo.fetch('index.php?a=1&ajaxa=16…')`（`mutate_templates.dynamic.php:318`） | get |
| `manager/index.php` | `manager_safe_mode_redirect` | セーフモードのフォームの hidden | post |
| `manager/ajax.php` | `ajaxa` | 同上 | get |
| `manager/includes/accesscontrol.inc.php` | `id` | すべての画面 | any（K1） |
| `manager/includes/accesscontrol-not-mgr.inc.php` | `a` | `is_get()` のときだけ読む | get（`$_POST` は空なので同じ値） |
| `manager/includes/csrf_token.php` | 一覧のパラメータ（`op`） | 守りの判定 | any（K3） |
| `manager/includes/manager.lockout.inc.php` | `a` | 振り分けと同じ値 | `manager()->action` |
| `manager/includes/log.class.inc.php` | `a` / `id` | すべての画面 | `manager()->action` / any（K1） |
| `manager/includes/form_flow.class.inc.php` | `after_save` | 保存後の戻り（クエリ）と文書の再表示（本文） | any（K2） |
| `manager/includes/extenders/ex_managerapi.php` | `pid` | `isAllowed()` の中（Decision Log） | 呼び出し側へ移す |
| `manager/includes/document.parser.class.inc.php` | `rememberme`（`token_auth()`） | 自動ログインの URL | get |
| 同上 | `baseTime` | 公開している仕様（GET・POST） | any（K4） |
| 同上 | `[[$_REQUEST]]` のタグ | サイトのテンプレート | any（K4。変えない） |
| `manager/includes/traits/document.parser.subparser.trait.php` | `err` | `sendRedirect()` が URL に付ける | get |
| 同上 | `pid`（`@INHERIT`） | 文書の編集画面で新規のとき | any（K2） |
| `manager/frames/tree.php` | イベントへ渡す全体・`dt` | ツリーの再取得（GET） | get |
| `manager/frames/nodes.php` | `tree_sortby`・`tree_sortdir` | `loadTreeRequest()`（`fetch` の GET） | get |
| `manager/actions/header.inc.php` | `quickmanager` | QuickManager のリンク（クエリ）と、編集フォームの hidden（本文） | any（K2） |
| `manager/actions/document/mutate_content*`・`mutate_draft.dynamic.php` | `id`・`pid`・`newtemplate` | リンク・リダイレクトと、自分自身へのフォーム | any（K2） |
| `manager/actions/document/move_document.dynamic.php` | `id` / `batch` | リンク（`?a=51&id=`）/ 一覧のフォームの本文（`formaction="index.php?a=51"`） | get / post |
| `manager/actions/document/parent_picker.dynamic.php` | `id` | `EvoShell.openPicker('index.php?a=135&id=…')` | get |
| `manager/actions/document/document_data.static.php` | `id` | リンク・リダイレクト | get |
| エレメントの画面（`mutate_*`） | `a` / `id` | 振り分け / リンク・保存後の戻り | `manager()->action` / get（要確認: 自分自身へ送るフォームが無いこと） |
| `manager/actions/element/mutate_module_resources.dynamic.php` | `id` / `op`・`type`・`newids`・`depid` | リンクと自分自身へのフォーム / フォームの本文 | any（K2） / post |
| `manager/actions/element/resource_selector.static.php` | すべて | `method="get"` のフォームと `window.open()` | get（`a` は `manager()->action`） |
| `manager/actions/element/mutate_template_tv_rank.dynamic.php` | `id` | 表示も送信も `index.php?a=117&id=` のクエリ | get |
| `manager/actions/element/ajax/mutate_templates.php` | `id` | `Evo.fetch` のクエリ | get |
| ユーザー・Web ユーザー・ロールの画面 | `a` / `id` | 振り分け / リンク | `manager()->action` / get（要確認） |
| `manager/processors/document/`（公開・非公開・復元・完全削除・下書きの公開予約の取り消し） | `id` | `EvoCsrf.post('index.php?a=61&id=…')` などのクエリ | get |
| `manager/processors/document/publish_draft_content.processor.php` | `id` | フォームの hidden（`publish_draft.dynamic.php:103`） | post |
| `manager/processors/document/move_document.processor.php` | `id`・`new_parent` | フォームの hidden（`move_document.dynamic.php:55-61`） | post |
| `manager/processors/document/batch_content.processor.php` | `op` | `formaction="index.php?a=121&op=…"` のクエリ | get |
| `manager/processors/permission/access_groups.processor.php`・`web_access_groups.processor.php` | `operation` / 各操作の値 | フォームの本文と `data-evo-post` のクエリ | any（K2） / 操作ごとに get か post |
| `manager/processors/db/optimize_table.processor.php` | `table`・`truncate`・`from` | `data-evo-post` のリンクのクエリ | get |
| `manager/processors/module/execute_module.processor.php` | `id` | 本体はリンク。モジュールのフォームは外部が決める | any（K4） |
| `manager/processors/login.processor.functions.php` | `rememberme` | ログインの画面の本文 | `input('rememberme')` にそろえる（post） |
| `manager/media/browser/evo/api.php` ほか | `scope`・`action`・`type` / `folder`・`foldersOnly` | 読む操作はクエリ、書く操作は本文 | any（K5） / 操作ごとに get か post（Decision Log） |
| `assets/plugins/qm/inc/on_doc_form_save.php` | `quickmanager` | 保存のフォームの hidden | post |
| `assets/plugins/qm/inc/on_doc_form_prerender.php` | `quickmanager`・`pid`・`id`・`qmrefresh` | リンクと編集フォームの hidden | any（K2） |
| `assets/plugins/qm/inc/on_manager_logout.php` | `quickmanager`・`logoutid` | ログアウトのフォームの `action` のクエリ（`on_web_page_prerender.php:146-154`） | get |
| `assets/plugins/managermanager/widgets/mm_inherit/mm_inherit.php` | `pid`・`parent` | 文書の編集画面 | any（K2。`parent` は要確認） |
| `assets/snippets/ditto/` | request extender の全体・`dateFilter` の値 / `jsonp`・`dbg` | サイトのリンクとフォーム / URL | any（K4） / get |
| `assets/snippets/weblogin/` | `uid`・`token`（有効化のリンク）/ `refurl`・`webloginmode`・`txtpwdrem` / `id` | URL / サイトのフォームと URL / 文書の URL | get / any（K4） / get（要確認） |
| `install/index.php` | `action` | フォームの hidden と「最初からやり直す」のリンク（`install/actions/install.php:36`） | any（K2） |

DocumentParser への影響: `executeParser()` の中の `setBaseTime()`（`baseTime`。K4 で残す）と、`parseDocumentSource()` の中の `[[$_REQUEST]]` のタグ（変えない）、`sendRedirect()` の `err`（get に絞る）、TV の `@INHERIT` の `pid`（any に改名）。`prepareResponse()` と `postProcess()` には影響しない。キャッシュ: 公開側のキャッシュのキー（`genQsHash()`）は `$_GET` から作っていて、この計画では変えない。

## Plan of Work

M0 で、64 ファイル・237 か所を 1 行ずつ判定する。判定は「値の置き場所」（Decision Log）で行い、`any` に残すものには K1〜K5 のどれかを書く。あわせて、各画面・各操作を、今の画面が送るのと同じ形のリクエストで main に送った結果を記録する。以後の段階は、この記録と比べて「画面からの操作の結果が変わらない」ことを確かめる。コードは変えない。

M1 で、名前の変更と決まりの土台を作る。`any_*var()` を足し（中身は `request_*var()` と同じ）、`request_*var()` は M8 で消すまで `any_*var()` を呼ぶだけにする。`abort_if_invalid_id()` を値を受け取る形にする。`assets/docs/request-input.md` と AGENTS.md に、受け取り元の選び方（値の置き場所で選ぶ、`any_` を残す 5 つの理由）と名前の対応（`getv`/`get_*`・`postv`/`post_*`・`anyv`/`any_*`、`$_REQUEST` を読みたいときは `anyv()`）を書く。security-lint の関数の一覧に `any_*` を足す（`any_strvar()` を足さないと、SQL への埋め込みが検出から外れる）。動きは変えない。

M2〜M7 で、判定表のとおりにディレクトリごとに書き換える。絞るものは `get_*` / `post_*` / `getv()` / `postv()` に、残すものは `any_*` / `anyv()` に、画面で `a` を読み直しているものは `manager()->action` にする。確かめることは各段階で同じで、画面からの操作（V1）は M0 と同じ結果、値を本来と違う場所に置いた要求（V2）は判定表どおり「無いもの」として扱われ、同じ名前の cookie（V3）は結果を変えない。ついでに直すもの（ログインの `rememberme`、`isAllowed()` の `pid`）は、該当する段階で同じ形にそろえる。

M8 で、呼び出しが 0 件になった `request_*var()` を `helpers.php` から消し、security-lint の一覧からも消す。文書の経過の記述を仕上げ、標準の PHP と PHP 7.4 で全段階を積んだ状態を横断で確かめる。

仕様変更の扱い: 画面の操作で受け付けられなくなるものは無い見込み（Surprises）。変わるのは「本来と違う場所に置いた値が読まれなくなる」ことで、外部の拡張（モジュール・プラグイン・サイトのスクリプト）が処理（M5）・ファイルブラウザの API（M6）・同梱のスニペット（M7）へ値を送っているときに影響しうる。M0 の判定表で、絞る行のうち外部から呼ばれうるものに印を付け、その段階の PR に `仕様変更` を付けて、送り方（クエリか本文か）を本文に書く。`request_*var()` を消す M8 も、`request_intvar()` を 1.2.0J から配布しているので `仕様変更` にし、置き換え先（`any_intvar()`、多くは `get_intvar()` / `post_intvar()`）をリリースノートの文案に書く。画面の操作（V1）が M0 と変わったら、判定の誤りとして判定表を直す（仕様変更として受け入れない）。

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。検証環境は `adaptive-validation-runner` スキルの手順で立てる（`setup-site.js --sample --editor`、`smoke.js` が全 PASS）。以下の `<dc>` は `docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`。管理画面へ curl するときは `Accept-Language` を必ず付ける。`<work>` はリポジトリの外の作業用ディレクトリ（ジョブの一時領域など）。

M0 判定表と修正前の記録

1. 基準を決める。`git log -1 --format=%H origin/main` を Artifacts and Notes に書き、ブランチを最新の main に合わせる。`gh issue view 1059 -R modxcms-jp/evolution-jp --json state` が CLOSED であることを確かめる。
2. 呼び出しを一覧にする。

       grep -rnE '\banyv\(|\brequest_[a-z]+var\(|\babort_if_invalid_id\(' --include=*.php --include=*.tpl --include=*.inc manager assets install | grep -v '/tinymce/' | grep -v '^manager/includes/helpers.php:'

   期待: 2026-11-10 の時点で 64 ファイル・237 か所（コメントの行を除く。Context の表）。数が違えば、main で増減した行を判定表の対象に足す。
3. 送り手を調べる。1 行ごとに、次を grep して、値がクエリと本文のどちらに載るかを決める。
   - 画面の番号（`a=<番号>` と `name="a" value="<番号>"`）と、キーの名前（`name="<キー>"`・`<キー>=`・JS のオブジェクトの `<キー>:`）を `manager assets install` の `*.php`・`*.tpl`・`*.js`・`*.html` で探す。
   - フォームは `method` と `action`（クエリがあればその値は GET）、ボタンの `formaction`、`action` が無ければ今の画面の URL（`shell.js` の `EvoShell.submit()`）を見る。
   - JS は `EvoCsrf.post(`（クエリ）、`data-evo-post`（`href` / `data-href` のクエリ）、`EvoShell.go(` / `navigate(` / `openPicker(` / `openModal(`（クエリ）、`Evo.fetch(` / `fetch(`（`method` と `body`）、`loadTreeRequest(`、ファイルブラウザの `apiUrl(`（クエリ）と `postForm(`（本文と、URL の `apiParams`）。
   - リダイレクトは `header('Location:` と `EvoFormFlow::afterSaveUrl(` / `formUrl(`、`webAlertAndQuit(` の戻り先。
   - ほかの処理からの `include`（保存の処理が `actions/header.inc.php` を読む、`header.inc.php` は POST のリクエストでも動く）。
   - CLI（`manager/includes/cli/commands/`）から通る経路が無いこと。
4. 判定表を書く。`.agent/plans/2026-11-10-request-source-narrowing-inventory.md` に、次の列の表を段階ごとに作る: 番号・ファイル・行・キー・今の書き方・送り手（ファイル:行と、クエリか本文か）・判定（get / post / any / `manager()->action` / 呼び出し側へ移す）・理由（any なら K1〜K5、ほかは空）・外部から呼ばれうるか（仕様変更の印）・済（段階の PR 番号）。Context の「見込み」の表から始め、「要確認」をすべて解く。公開側の行で post に絞るものは、無害化が外れても困らない理由を書く（Decision Log）。
5. シナリオを決める。判定表の行を「画面・操作」でまとめ、1 つの画面・操作を 1 つのシナリオにする（見込みは 60 前後）。シナリオごとに、今の画面が送るのと同じ形のリクエスト（メソッド・クエリ・本文。状態を変える操作は CSRF トークン付き）と、ログインするユーザー（`admin`、権限で分かれる画面は `editor` も）を JSON に書く。置き場所は `<work>/1070-scenarios.json`。
6. 修正前の動きを記録する。main の検証環境で、DB をシナリオの前の状態に戻してから（セットアップ直後に `<dc> exec db mariadb-dump -uuser -ppassword evodb > <work>/1070-fixture.sql` で控え、各シナリオの前に流し込む）、各シナリオを 3 通りで送る。
   - V1: 画面と同じ形。
   - V2: 判定表で get / post に絞る値を、もう一方の場所へ移した形（クエリの値を本文へ、本文の値をクエリへ）。GET の画面の値を本文へ移すときは、POST（CSRF トークン付き）で送る。main では値そのものは今と同じく読まれるが、POST のときだけ通る分岐（`!postv()` の判定など）で V1 と結果が違うことがある。その違いもそのまま記録する。
   - V3: V1 に、シナリオのすべてのキーと同じ名前で別の値の cookie を足した形。
   記録するもの: HTTP の状態・`Location`・本文を正規化したもの（`csrf_token` などの値、時刻、セッション名を伏せる）と、`<form>` の欄の一覧（`.claude/skills/adaptive-validation-runner/scripts/form-snapshot.js` と同じ形）、状態を変える操作は対象の表の行（`<dc> exec app php evo db:query "SELECT …"`）。スクリプトは `<work>/1070-scenarios.js`（`scripts/lib.js` の `login()` を使い、`page.request` で送る。リダイレクトは追わずに `Location` を控える）。結果は `<work>/1070-m0.tsv`（シナリオ・型・状態・Location・本文の要約のハッシュ・DB の行のハッシュ）。
   期待: main では V3 が V1 と同じ結果（`anyv()` が cookie を読まないため）。V3 が違えば Surprises に書く。V2 の記録は、後の段階で「値が無いときの動き」に変わったことを確かめるための比較の元にする。あわせて、絞る値を除いた要求（値が無いとき）も 1 回送って記録し、後の段階の V2 の期待値にする。
7. 判定表の件数（get / post / any / `manager()->action` / 呼び出し側へ移す）と、仕様変更の印の付いた行を、Progress の M0 の行に書く。基準のコミットとシナリオの数を Artifacts and Notes に書く。

M1 `any_*var()` と決まりの土台（動きは変えない）

8. `manager/includes/helpers.php`:
   - `any_intvar` / `any_uintvar` / `any_strvar` / `any_textvar` / `any_boolvar` / `any_choicevar` / `any_idsvar` を足す（`to_*(anyv($key), …)` を呼ぶ形。既定値と引数の並びは `request_*` と同じ）。コメントは「GET と POST の両方から届く値だけに使う（理由は request-input.md の K1〜K5）」。
   - `request_*var()` の中身を、対応する `any_*var()` を呼ぶだけにし、「#1070 の M8 で消す」とコメントを書く。
   - `abort_if_invalid_id($value, $required = false)` にする（`$value === null || '' || '0'` で `$required` が false なら通す、`to_intvar($value)` が 0 ならエラー。今の判定と同じ）。コメントに「受け取り元は呼び出し側で選ぶ」と書く。
9. 呼び出しを書き換える（動きは変えない）: `manager/actions/document/mutate_content/functions.php:490` と `manager/actions/element/mutate_{module,htmlsnippet,templates,plugin,snippet,tmplvars}.dynamic.php` の `abort_if_invalid_id('id', …)` を `abort_if_invalid_id(anyv('id'), …)` にする。
10. `.github/scripts/security-lint.php`: `REQUEST_FUNCS`（237 行）に `any_strvar`・`any_textvar` を、244 行の一覧に `any_intvar`・`any_uintvar` を足す。先頭のコメントの関数の名前もそろえる。
11. `assets/docs/request-input.md`:
    - 「2 種類のヘルパー」と「受け取り元の選び方」の表の `request_` を `any_` にし、「`request_*var()` は #1070 で `any_*var()` に改めた（M8 で消す）」と書く。
    - 「受け取り元の選び方」を書き直す: 値の置き場所で選ぶ（URL のクエリは `get_`、本文は `post_`）。POST のリクエストでもクエリの値は `get_` で読む（`EvoCsrf.post(url)`・`data-evo-post`・`formaction` のクエリ・`action` の無いフォームの今の URL）。`any_` / `anyv()` を残してよいのは K1〜K5 だけ（Decision Log の 5 つを、例のファイルとともに書く）。守りの判定（K3）は処理より狭く読まない。画面の側で振り分けの番号 `a` を読み直さず `manager()->action` を使う。公開側で `post_` に絞ると `sanitize_gpc` の無害化が外れる。
    - 名前の対応の表: `$_GET`→`getv()`/`get_*`、`$_POST`→`postv()`/`post_*`、`$_REQUEST`→`anyv()`/`any_*`（GET と POST だけ、cookie は読まない。cookie は `cookiev()`）。
    - 「置き換えの表」の `request_idsvar('depid')` の例を、判定どおりの名前（`post_idsvar('depid')`）に直す。
    - 「関連」に、この ExecPlan のパスを足す。
12. AGENTS.md の「スーパーグローバル変数の禁止」: `$_REQUEST` の行を「`anyv()`（GET と POST の両方から届く値だけ。多くは `getv()` / `postv()` を選ぶ。cookie は含まない）」にし、型つきの例を `get_intvar()` / `post_strvar()` / `any_boolvar()` にする。選び方の詳細は `assets/docs/request-input.md` を参照、と書く（詳細は書き写さない）。
13. 確かめる。
    - `docker run --rm -v "$PWD":/app -w /app php:7.4-cli php <work>/1070-m1-helpers.php` と、標準の版の `<dc> exec app php <work の写し>` で、`any_*var()` と `request_*var()` が同じ入力（`null`・`''`・`'0'`・`'abc'`・配列・正常な値・GET だけ・POST だけ・両方）で同じ値を返し、`abort_if_invalid_id()` が今と同じ値で止まる・通ることを確かめる（`error_reporting(E_ALL)` で警告も FAIL）。期待: すべて PASS。
    - `php .github/scripts/security-lint.php` が `0 violations`。`any_strvar()` を SQL に埋め込んだ一時ファイルで sql-embed の違反が出ることを確かめ、消す。
    - M0 のシナリオを V1 だけ流し、M0 と同じ結果。`smoke.js` が全 PASS。
    - `doc-maintainer` の手順（`/doc-audit`）で、変えた文書に無効な参照やパスの表記ゆれが無いことを確かめる。

M2〜M7 ディレクトリごとの書き換え（各段階で同じ手順）

14. 判定表のその段階の行を、上から順に書き換える。
    - get / post: `request_intvar('x')` → `get_intvar('x')` など、`anyv('x')` → `getv('x')` / `postv('x')`。
    - any: `request_*` → `any_*`。`anyv()` はそのまま。
    - `manager()->action`: `request_intvar('a')` → `(int) manager()->action`（`switch` や `===` で比べている箇所。hidden の値に出すものも同じ）。
    - 呼び出し側へ移す: `ManagerAPI::isAllowed()`（M3）。
    書き換えた行の「済」に段階の名前を書き、PR の本文に、その段階で any に残した行と理由（K1〜K5）、仕様変更の印の付いた行を表で写す。
15. 段階ごとの注意（判定表に無い作業）:
    - M2: `manager/includes/cli/commands/export-site.php:86` のコメントの `request_intvar('a')` を `manager()->action` に直す。`log.class.inc.php` は、引数で `$action` が渡らないときに `manager()->action` を使う。
    - M3: `ManagerAPI::isAllowed($id, $pid = 0)` にし、`$id` が 0 のときは引数の `$pid` を見る（「pid が送られたかどうか」は `$pid` が 0 でないかで見る。今の `!anyv('pid')` と同じく `'0'`・空は送られなかった扱い）。呼び出し元は判定表のとおりに渡す。文書の編集画面の 4 ファイルは `any_intvar()` への改名が大半なので、grep で 1 行も取りこぼしていないことを確かめる。
    - M4: エレメントの画面の `abort_if_invalid_id(anyv('id'), …)` を判定どおり `getv('id')` にし、同じ画面の `$id` を読む行と同じ受け取り元にそろえる。
    - M5: `login.processor.functions.php:553` を `input('rememberme')` にする（`input()` と同じく本文から読む）。アクセス権の 2 つの処理は、`operation` を `any_choicevar()` で読み、各 `case` の中の値を判定表のとおり get / post で読む。
    - M6: `PickerScope.php` の `type` と `api.php` の `scope`・`action` は K5 で残す。`folder`・`foldersOnly` は操作ごとに、今の JS が送る場所に合わせて get か post に絞る（Decision Log）。本文が `post_max_size` を超えたとき（`api.php` の `too_large` の分岐）に、管理モードの要求がピッカーとして扱われないこと（`scope` を URL から読めること）も V1 に入れる。
    - M7: 公開側の行を post に絞るときは、判定表の「無害化が外れても困らない理由」を確かめてから書き換える。`assets/snippets/weblogin/weblogin.install_base.tpl` はインストール時に DB へ入るスニペットの本文なので、既存のサイトには反映されないことを PR に書く。
16. 確かめる（各段階）。
    - M0 のシナリオのうち、その段階で触れた画面・操作のものを V1・V2・V3 で流し、`<work>/1070-<段階>.tsv` に書く。期待は Validation and Acceptance の表のとおり。前の段階で触れたシナリオも V1 だけ流し直し、変わっていないことを確かめる。
    - 変えたファイルを `docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l <ファイル>` で構文チェックする。
    - `php .github/scripts/security-lint.php` が `0 violations`、`smoke.js` が全 PASS、システムログ（`temp/logs/system/`）に `error` 以上の行が増えていない。
17. コミットして PR を作る（前の段階のブランチを base にする）。PR の本文に判定表のその段階の行を写す。外部から呼ばれうる行を絞った段階は `仕様変更` を付ける。

M8 `request_*var()` を消して仕上げる

18. `grep -rnE '\brequest_[a-z]+var\(' --include=*.php --include=*.tpl --include=*.inc manager assets install` が 0 件であることを確かめ、`helpers.php` から `request_*var()` の 7 つを消す。`.github/scripts/security-lint.php` の一覧から `request_*` を消す。
19. `assets/docs/request-input.md` から「M8 で消す」などの経過の記述を消し、`any_` の説明だけにする。AGENTS.md に `request_` が残っていないことを確かめる。
20. 横断で確かめる。標準の PHP と PHP 7.4（`references/environment.md` の「ほかの PHP の版で検証するとき」のとおり `FROM php:7.4-apache` にしたイメージ）で、M0 の全シナリオを V1・V2・V3 で流す。PR の本文に、`仕様変更` の項目（外部から本来と違う場所に送っていた値が読まれなくなる箇所の一覧と、`request_*var()` の置き換え先）と、リリースノートの文案を書く。

想定のコミット（Conventional Commits、日本語）:

- M0: `docs(plan): #1070 の受け取り元の判定表を足す`
- M1: `feat(helpers): GET と POST の両方を読む型つきヘルパーを any_*var() として足す`、`refactor(helpers): abort_if_invalid_id() が確かめる値を受け取るようにする`、`ci(security-lint): any_*var() を検出の対象に入れる`、`docs(request): 受け取り元を値の置き場所で選ぶ決まりにする`
- M2〜M7: `refactor(<範囲>): リクエスト値を実際の入口に合わせて GET か POST から読む`（範囲は `manager`・`document`・`element`・`processors`・`filepicker`・`assets` など）。ついでの修正は別のコミット（例: `fix(login): ログイン状態の保持を入力の読み方とそろえる`、`refactor(manager): isAllowed() がリクエストを読まないようにする`）
- M8: `refactor(helpers): request_*var() を消し、名前を any_*var() にそろえる`

## Validation and Acceptance

検証環境は `adaptive-validation-runner` スキルの手順で立てる。比べる相手は M0 の記録（main）で、同じ DB の状態（`<work>/1070-fixture.sql`）から流す。

各段階で観察する結果:

| 型 | 送り方 | 期待（M2〜M8） |
| --- | --- | --- |
| V1 | 今の画面と同じ形（リンク・フォーム・JS が送るのと同じメソッド・クエリ・本文） | M0 と同じ状態・`Location`・本文の要約・フォームの欄・DB の行。違えば判定の誤りとして直す |
| V2（any に残した値） | 値をもう一方の場所へ移す | M0 と同じ（両方から読む） |
| V2（get / post に絞った値） | 値をもう一方の場所へ移す | 値が無いときの動き（既定値・「id が無い」のエラー・新規の画面など）になる。M0 で記録した「値を除いた要求」の結果と一致すること |
| V3 | V1 に同じ名前の cookie を足す | V1 と同じ（cookie の値で結果が変わらない） |

V1 には、絞った経路だけでなく、同じ処理を通る今の経路を必ず含める。例: 文書の編集画面はリンクで開く・新規（`a=4&pid=`）・テンプレートの切り替えで自分自身へ送る・保存に失敗して戻る、の 4 通り。アクセス権の処理はフォームの追加・名前の変更と、ボタンの削除・関連の解除の両方。ファイルブラウザはピッカーと管理モード（`scope=manage`）の両方で、読む操作と書く操作。QuickManager は公開側からの編集・新規・ログアウト。

完了とみなす観察結果:

- `grep -rnE '\brequest_[a-z]+var\(' --include=*.php --include=*.tpl --include=*.inc manager assets install .github` が 0 件。
- `grep -rnE '\banyv\(|\bany_[a-z]+var\(' --include=*.php --include=*.tpl --include=*.inc manager assets install`（`helpers.php` の定義を除く）の行が、判定表で any にした行とちょうど一致する。
- M0 の全シナリオが、標準の PHP と PHP 7.4 で上の表のとおり。`smoke.js` が全 PASS。システムログに `error` 以上の行が増えていない。
- `php .github/scripts/security-lint.php` が `0 violations`。
- `assets/docs/request-input.md` に受け取り元の選び方（値の置き場所、K1〜K5）と名前の対応（`getv`/`get_*`・`postv`/`post_*`・`anyv`/`any_*`・`$_REQUEST`）があり、AGENTS.md の「スーパーグローバル変数の禁止」が同じ名前で書かれ、request-input.md を参照している。

## Idempotence and Recovery

各段階は独立した PR にし、判定表の「済」の列で進み具合を残す。中断したら、Progress の未完了の段階と、判定表の「済」が空の行から再開する。M1 から M7 までは `request_*var()` が `any_*var()` の別名として残るので、途中の段階で止まっても動きは壊れない。M8 は、M2〜M7 がすべて済んで grep が 0 件になってから行う。ある段階で V1 が M0 と違ったら、その段階の PR を戻せば元に戻る（前の段階には影響しない）。

M0 の記録（`<work>/1070-*.json`・`*.tsv`・`*.sql`・`*.js`）はジョブの一時領域にあり、消えることがある。消えていたら、Artifacts and Notes の基準のコミットで検証環境を立て、`1070-scenarios.json` から取り直す（シナリオの定義も消えていたら、判定表からもう一度作る）。

## Artifacts and Notes

- Issue: #1070（ラベル `refactor`・`仕様変更`）
- 判定表: `.agent/plans/2026-11-10-request-source-narrowing-inventory.md`（M0 で作る）
- 前段: #1059（`.agent/plans/archive/2026-11-07-typed-request-helpers.md`）、#1073（`anyv()` が cookie を読まないようにした。08bf5f763）
- 参考にした進め方: `.agent/plans/archive/2026-11-09-user-settings-allowlist.md`（M0 で修正前の結果を記録して完全一致で比べる）
- 基準のコミット（計画時）: 6ef1f76d3（origin/main、2026-11-10）。M0 で着手時のコミットに書き換える
- 計画を置いた PR のブランチ: `docs/plan-1070-request-source-narrowing`（Issue に紐づけない。計画だけを置くもの）
- ブランチ: M0・M1 は、着手時に `gh issue develop 1070 --name refactor/1070-request-source-narrowing` で作る（計画時に一度作ったが、着手を 1.3 リリース後に回したので消した）。M2 以降は `refactor/1070-m<番号>-<範囲>`（例: `refactor/1070-m2-entry`）を前の段階のブランチから作り、PR の base も前の段階のブランチにする

## Interfaces and Dependencies

- 足す関数（`manager/includes/helpers.php`）: `any_intvar($key)`・`any_uintvar($key, $default = null)`・`any_strvar($key, $default = '')`・`any_textvar($key, $default = null)`・`any_boolvar($key, $default = false)`・`any_choicevar($key, array $choices, $default)`・`any_idsvar($key)`。戻り値は今の `request_*var()` と同じ。
- 変える関数: `abort_if_invalid_id($value, $required = false)`（キーではなく値を受け取る。1.2 系には無い）、`ManagerAPI::isAllowed($id, $pid = 0)`（M3。`$pid` を引数で受け取り、リクエストを読まない）。
- 消す関数（M8）: `request_intvar`・`request_uintvar`・`request_strvar`・`request_textvar`・`request_boolvar`・`request_choicevar`・`request_idsvar`。`request_intvar()` は 1.2.0J から配布しているので、外部の拡張が呼んでいると Fatal error になる（`仕様変更`。7 つとも消すと決めた。Decision Log）。
- 変えないもの: `anyv()` の中身（GET と POST をまとめ、cookie を読まない。公開側では無害化する）、`getv()` / `postv()`、テンプレートの `[[$_REQUEST]]` のタグ、`csrfProtectedActions()` の一覧。
- 外部の拡張への影響: 処理（`manager/processors/`）・ファイルブラウザの API・同梱のスニペットに、本来と違う場所（クエリのはずを本文、本文のはずをクエリ）で値を送っている外部のコードは、その値が読まれなくなる。各段階の PR の `仕様変更` の欄に、送り方を書く。
- 依存: なし（#1059 は完了済み）。同じファイルを触る作業（`manager/processors/permission/` の #1076 など）と並行するときは、段階の範囲が重ならないようにする。
