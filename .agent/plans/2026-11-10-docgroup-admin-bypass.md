# ExecPlan: 文書グループの絞り込みを受けない「管理者」の条件を 1 か所にそろえる（Issue #1338）

## Purpose / Big Picture

文書グループで「管理側で非公開」にした文書を、だれが見られて操作できるかを、管理画面のどの画面・どの処理でも同じ 1 つの判定で決める。今は場所ごとに「管理者」の条件が違い（`save_role` 権限・`isAdmin()`・ロールの ID が 1）、所属が無い利用者の扱いや「文書グループの権限を使う」が無効のときの扱いも場所ごとに違うため、ツリーに出ないのに開ける、開けると判定されるのに編集画面が空になる、編集画面で変えた TV が保存されない、といった食い違いが起きている。判定を `ManagerAPI` の 1 か所に置き、すべての箇所がそれを使うようにする。

## Progress

- [x] (2026-11-10) 計画の作成（main ec7b45146 で調査。Context の表・Surprises・Decision Log）
- [ ] M0: 着手前の確認と修正前の記録（main の見え方・操作の可否を TSV に残す）
- [ ] M1: `ManagerAPI` に判定を置き、`checkPermissions()`・`isAllowroot()`・`canOpenWithDocumentGroups()` をそれに通す（各処理・編集画面の入口の確認が新しい条件になる）
- [ ] M2: 管理画面の一覧と読み込み（ツリー・子の一覧・検索・親の選択・文書の情報・編集画面の `db_value()`）を同じ判定にする
- [ ] M3: TV のアクセス（編集画面・保存・QuickManager）と QuickManager の編集ボタンを同じ判定にする
- [ ] M4: DocumentParser の取得の関数（管理画面側の分岐は同じ判定、公開側は「ロールの ID が 1」を `isAdmin()` に）
- [ ] M5: ドキュメント・PHP 7.4 での確認・最終の突き合わせ

## Surprises & Discoveries

計画時（2026-11-10、main ec7b45146 で調査）に分かったこと:

- 既定のロールは、インストール直後で 3 つ（`install/sql/default_settings.sql` を `default_settings_custom.sql` が上書き）: 1 Administrator（管理者）、2 ウェブマスター（すべての権限）、3 投稿担当者。古いサイトから上げたものには 2 Editor・3 Publisher が残ることがある。どれも `save_role` と `isAdmin()`（`access_permissions` と `web_access_permissions` の両方）が「両方真」か「両方偽」で、管理画面の見え方は変わらない。ただし「ロールの ID が 1」とは違い、ウェブマスター（2）は `isAdmin()` が真なので、ロールの ID で判定している DocumentParser の取得の関数と、TV の保存・QuickManager の TV ボタンでは、ウェブマスターの見え方が変わる（M3・M4）。
- TV の文書グループによるアクセスで、編集画面（`manager/actions/document/mutate_content/functions.php:31-41` の `getTmplvars()`）は `isAdmin()` で絞りを外し、保存（`manager/processors/document/save_resource.processor.php:276-295` の `get_tmplvars()`）は `1='mgrRole'`（ロールの ID が 1）で外す。ウェブマスターは、所属しない文書グループに限った TV を編集画面で見て変えられるが、保存の処理はその TV を受け取らないので、変えた値が黙って保存されない（要確認。M0 で確かめる）。QuickManager の TV ボタン（`assets/plugins/qm/qm.inc.php:270-300` の `checkTvAccess()`）もロールの ID が 1。
- QuickManager の編集ボタンの判定（`qm.inc.php:175-212` の `checkAccess()`）は、`privatemgr` も `use_udperms` も見ず、「文書にグループの行があれば、そのどれかに所属していること」を求める。Web 側だけのグループが付いた文書（`privatemgr=0`）は、管理画面では開けるのに、所属しない利用者にはボタンが出ない。
- `use_udperms`（文書グループの権限を使う）が 0 のとき、`checkPermissions()`・検索・各処理・#1318 の保存の確認は絞らないが、ツリー（`manager/frames/nodes.php:677-690`）・子の一覧（`resources_list.static.php`）・親の選択（`parent_picker.dynamic.php:277-291`）・文書の情報（`document_data.static.php:38-43`）・編集画面（`db_value()`）は `privatemgr` で絞る。`privatemgr=1` は `use_udperms=0` でも残りうる（グループ管理の画面 `access_permissions.dynamic.php` は無効のときも使え、`access_groups.processor.php:157` が `secureMgrDocument()` で付け直す。有効だった時期に付いた印もそのまま残る）。
- `tree_show_protected`（権限のないリソースの表示）は表示だけの設定。ツリーと子の一覧で、開けない文書を `protectedNode` の印つきで出す（`nodes.php:572-584`、`resources_list.static.php:395-397`）。開く・操作するのは各画面・処理の `checkPermissions()` が止める。子の一覧の「すべてを選択」は `has_access=0` を外す（81-90 行）。検索（`search.static.php:131`）も、有効なら開けない文書を印なしの普通のリンクで出す。親の選択は見ない。
- `checkPermissions()` の先頭の `save_role` は、文書グループだけでなく、ルート（親 0）と「ルートへの複製の禁止」（`udperms_allowroot=0`）の確認も外している。ルート直下に作れるかの `ManagerAPI::isAllowroot()`（`ex_managerapi.php:1139-1142`）も `save_role || udperms_allowroot`。
- ルートの扱いが入口で違う（修正前から。この計画では変えない）: 新規の作成（`save_resource.processor.php:481` と `tree.php:372` の `isAllowroot()`）は `use_udperms` を見ないので、既定（`use_udperms=0`・`udperms_allowroot=0`）では投稿担当者はルートに作れない。移動（`move_document.processor.php:75`）と親の選択のルートの項目（`parent_picker.dynamic.php:40` の `checkPermissions(0)`）は `use_udperms=0` なら許す。
- DocumentParser の取得の関数は、公開側（`privateweb`）と管理画面側（`privatemgr`）を `isFrontend()` で分け、ロールの ID が 1 のときの扱いが関数ごとに違う。
  - `getDocumentObject()`（`document.parser.class.inc.php:3786-3800`）: 両側で `1=<ロールの ID>` を OR に入れる（ID が 1 なら絞らない）。
  - `getDocuments()`（4212-4262）・キャッシュの読み込みの確認（1679-1700）: 両側で、ID が 1 なら絞らない。
  - `getAllChildren()`（trait 2175-2215、`$where` が無いとき）: 所属があるときだけ `1='<ロールの ID>'` を入れる（所属が無い ID 1 は絞られる）。
  - `getPageInfo()`（4335-4370）・`getActiveChildren()`（trait 2230-2275）・`getDocumentChildren()`（trait 2278-2310）: 公開側は ID 1 でも絞る。管理画面側だけ ID 1 で外す。
  - `getDocumentChildren()` の管理画面側は、ID 1 で所属があると、条件が「所属のグループの文書だけ」になる（`$access` が空のまま ` dg.document_group IN (...)` を足すため。2298-2306 行）。管理者が所属を持つと子が減る不具合（修正前から）。
- 公開側は、管理画面にログインしていても `ManagerAPI` を読み込まない（`document.parser.class.inc.php:185-187` は管理画面だけ。読み込むのは `token_auth()` の 1242 行と QuickManager の 41 行）。公開側の処理から `manager()` を呼ぶと null になる。
- 公開側のページキャッシュ: 既定の `disable_cache_at_login=1`（`document.parser.class.inc.php:1380-1383`）で、管理画面にログイン中の公開側はキャッシュを使わない。0 にしているサイトでは、ロールの ID 1 の利用者が見たときに `getDocuments()` などが絞らずに返した文書が、キャッシュする部品の出力ごとページキャッシュに入りうる（修正前から）。M4 で判定を `isAdmin()` にするとウェブマスターにも広がる。
- 文書グループで絞っていない一覧が残る（修正前から。この計画の範囲外）: 予約の一覧（`manager/actions/report/site_schedule.static.php:31-36, 75-80, 120-125`）、要素の画面の文書の選択（`manager/actions/element/resource_selector.static.php:87-92`）、テンプレートの画面の「このテンプレートを使う文書」（`manager/actions/element/ajax/mutate_templates.php:14-18`）。非公開の文書のタイトルが、所属しない利用者にも出る。M1 の後なら `documentAccessCondition()` を足すだけで直せる。
- 同梱のスニペット Ditto（`assets/snippets/ditto/classes/ditto.class.inc.php:962, 1085, 1105`）と Wayfinder（`assets/snippets/wayfinder/wayfinder.inc.php:482`）も、ロールの ID が 1 で `privatemgr` の絞りを外す独自の条件を持つ（この計画の範囲外）。
- ロールの ID が 1 で決めている、文書グループと関係ない箇所: モジュールのメニュー（`manager/frames/menu.php:294`）、ユーザーの一覧（`user_management.static.php:162`）、`config_check.inc.php:373`。この計画では触れない。
- CLI（`manager/includes/cli/commands/`）に文書グループを判定する処理は無い。
- `nodes.php:492` は、ツリーのノード名を日付にする設定のとき `getDocumentObject()` を呼ぶ。`tree_show_protected=1` で開けない文書は false が返る（`'- - -'` か警告。要確認。M0 で記録する）。
- `privatemgr` の列で絞る条件（`privatemgr=0 OR 所属のグループの文書`）と、#1318 の集合で求める条件（管理側で非公開のグループ＝`membergroup_access` に行があるもの、が無いか所属と重なる）は、`privatemgr` が実際の所属と合っていれば同じ結果になる（`mgrDocgroups` は `membergroup_access` から求めるので、所属のグループはすべて管理側で非公開）。印が合わない経路は #1353（範囲外）。

## Decision Log

- 2026-11-10 / ユーザーさんと相談（Issue の最後のコメント）: 絞り込みを外す「管理者」は、閲覧も操作も `isAdmin()`。`checkPermissions()` と各処理の `save_role`、DocumentParser の取得の「ロールの ID が 1」も同じ判定。管理側のグループに 1 つも所属しない利用者は、どこでも公開の文書（`privatemgr=0`）だけ。判定は `ManagerAPI` の 1 か所。`privatemgr` の付け方の食い違いは #1353 に分ける。
- 2026-11-10 / Claude（planner）: `ManagerAPI` に置く判定は次の 5 つ。定義は 1 つ（「管理者」＝`isAdmin()`、「絞る」＝`use_udperms` が 1 で管理者でない、「届く」＝`privatemgr=0` か所属のグループに入っている）で、使う場面に合わせて形だけを変える。
  1. `isAdmin()`（今のまま）: docblock に「文書グループの絞り込みを受けない管理者の定義」と書く。
  2. `userMgrDocumentGroupIds(): int[]`: ログイン中の利用者の所属（セッション `mgrDocgroups`）を、正の整数の配列で返す。各所の `array_filter(array_map('intval', …))` と `implode(',', sessionv('mgrDocgroups'))` をこれにまとめる。
  3. `restrictsByDocumentGroups(): bool`: `use_udperms == 1 && !isAdmin()`。
  4. `documentAccessCondition(string $alias = 'sc'): string`: 絞らないときは空文字。絞るときは SQL の真偽の式（前に `AND` は付けない）。所属が空なら `{a}privatemgr=0`、あれば `({a}privatemgr=0 OR {a}id IN (SELECT document FROM <prefix>document_groups WHERE document_group IN (<所属>)))`。`{a}` は `$alias.`（空なら付けない）。結合（`LEFT JOIN document_groups`）に頼らない副問い合わせの形にし、どの問い合わせにも同じ式を入れられるようにする（ツリーと親の選択が今使っている形）。`$alias` は呼ぶ側の固定の文字列だけを受け、`/^[A-Za-z_][A-Za-z0-9_]*$/` か空でなければ `InvalidArgumentException`。
  5. `canAccessDocument($docId): bool`: 絞らないなら true。絞るなら、その id の文書が 4 の式を満たすか（存在しない文書は false。今の `checkPermissions()` と同じ）。
  あわせて、`canOpenWithDocumentGroups($groupIds)`（#1318）は「絞らない、または集合が届く」にする（2 つの確認を組み合わせた式をやめる。3 つの条件が 1 つになったので組み合わせが要らない）。
  理由: 一覧（SQL の式）・1 文書の可否（真偽）・保存前の集合（#1318）の 3 つの場面があり、それぞれに同じ定義から作った形を渡せば、呼ぶ側に条件を書かずに済む。副問い合わせの形なら、結合の形が違う問い合わせ（ツリー・一覧・検索・DocumentParser）に同じ式を入れられる。
  不採用: (a) `isAdmin()` を各所で直接呼ぶ形のままにする案は、`use_udperms` と所属が空のときの扱いをまた各所に書くことになる。(b) 判定を DocumentParser の trait に置く案は、#1318 と決定に合わせて `ManagerAPI` に置く（`checkPermissions()` は公開の API として残し、中身を `ManagerAPI` に任せる）。(c) 文書グループ専用の新しいクラスを作る案は、既存の `ManagerAPI` に #1318 の判定がすでにあり、この規模で新しい置き場所は作らない。
- 2026-11-10 / Claude（planner）: `use_udperms` が 0 のときは、管理画面のどこでも文書グループで絞らない（`restrictsByDocumentGroups()` が false）。TV のアクセスも同じ。
  理由: `checkPermissions()`・各処理・検索・#1318 はすでにそうで、絞っているのはツリーなどの表示だけ。表示で隠しても、id を指定すれば開けて操作できる。設定の名前と説明（「文書グループの権限を使う」）とも合う。
  影響: `use_udperms=0` で `privatemgr=1` の印が残っているサイトでは、投稿担当者などのツリー・一覧・親の選択に、今は出ていない文書が出る（開くことは今もできる）。リリースノートに書く。
  公開側（`privateweb`）は `use_udperms` を見ないまま変えない（Web 側の会員制の動きで、この Issue の範囲外）。
- 2026-11-10 / Claude（planner）: `tree_show_protected` は「ツリーと子の一覧に、開けない文書を印つきで出す」表示だけの設定として残す。絞り込みを外す条件には入れない（今も操作は外していない）。ツリーと子の一覧は、有効なら WHERE に式を入れず、`has_access` を `MAX(IF(<式>,1,0))` で求める。検索と親の選択は、この設定を見ずに常に絞る。
  理由: 印をつけて出せるのはツリーと子の一覧だけで、検索は開けない文書を普通のリンクで出し、押すとエラーになる。設定の説明も「リソースツリーに表示」。
  代わりの案: 検索も設定に従い、`has_access` を求めて印をつけ、リンクを外す。検索の画面の作りを変えることになり、この Issue の目的（判定をそろえる）から外れるので採らない。
  影響: `tree_show_protected=1` で管理者でない利用者の検索の結果から、開けない文書が消える。リリースノートに書く。
- 2026-11-10 / Claude（planner）: ルートの許可（`checkPermissions()` の親 0・ルートへの複製の確認と、`isAllowroot()`）を外す条件も `save_role` から `isAdmin()` にする。`checkPermissions()` の確認の順番（管理者 → 親 0 で `udperms_allowroot` → `use_udperms=0` → id 無し → ルートへの複製 → 文書グループ）は変えない。
  理由: `udperms_allowroot` は文書グループの設定の 1 つ（`settings_form.definition.inc.php:424-428` で `use_udperms=1` のときだけ出す）で、今は `checkPermissions()` の同じ `save_role` で外れている。ここだけ `save_role` に残すと「管理者」が 2 つに戻る。既定のロールは結果が変わらない。
  ルートの入口ごとの `use_udperms` の扱いの違い（Surprises）は直さない（既定のロールの動きが変わるため、別 Issue）。
- 2026-11-10 / Claude（planner）: 公開側でも `manager()` が `ManagerAPI` を返すように、`manager/includes/helpers.php` の `manager()` を「未読み込みなら `evo()->loadExtension('ManagerAPI')` してから返す」にする。DocumentParser の公開側の判定は、`$this->isLoggedIn('mgr') && manager()->isAdmin()` の形で呼び、管理画面にログインしていない閲覧では `ManagerAPI` を読み込まない。
  理由: `checkPermissions()` は公開側（未公開の文書の表示、`document.parser.class.inc.php:637`）からも呼ばれ、QuickManager も公開側で動く。`ManagerAPI` の読み込みは今も `token_auth()` と QuickManager が公開側で行っている。訪問者の毎回の表示で読み込まないよう、ログインの確認を先に置く（`isAdmin()` は `mgrPermissions` が無ければ偽なので、結果は同じ）。
- 2026-11-10 / Claude（planner）: TV の文書グループによるアクセス（`site_tmplvar_access`）の「絞らない」も `restrictsByDocumentGroups()` の否定にそろえる（編集画面 `getTmplvars()`・保存 `get_tmplvars()`・QuickManager の `checkTvAccess()`）。TV の絞り方そのもの（TV に行が無いか、所属のグループの行がある）は変えない。
  理由: 編集画面と保存で条件が違い、ウェブマスターの変更が保存されない食い違い（Surprises）は、この Issue と同じ「管理者の条件が場所で違う」問題。範囲に入れないと M4 の後もここだけロールの ID が残る。
- 2026-11-10 / Claude（planner）: QuickManager の編集ボタンの判定 `checkAccess()` は `manager()->canAccessDocument(docid)` にする（管理者・`use_udperms=0`・Web 側だけのグループの文書でも、管理画面で開ける文書ならボタンを出す）。
- 2026-11-10 / Claude（planner）: DocumentParser の取得の関数（M4）は、管理画面側の分岐（`isFrontend()` が偽）を `manager()->documentAccessCondition()` にし、公開側の分岐は「ロールの ID が 1」を今ある場所だけ `isLoggedIn('mgr') && manager()->isAdmin()` に置き換える。公開側で ID 1 を見ていない関数（`getPageInfo()`・`getActiveChildren()`・`getDocumentChildren()` の公開側）に新しく外す条件は足さない。`getAllChildren()` の「所属があるときだけ外す」は、所属が無くても外す形にする（同じ判定にするため）。
  理由: 公開側の関数ごとの違いをそろえる向き（外す側にそろえるか、外さない側にそろえるか）は、ページキャッシュに入りうるかという別の判断が要る。決定の範囲（ロールの ID 1 を同じ判定にする）にとどめ、そろえる向きは別の課題にする（`assets/docs/core-issues.md` に書いた）。
  M4 はこの Issue に入れると決めた（未決事項の 1）。
- 2026-11-10 / Claude（planner）: 表示の問い合わせの FROM・JOIN・GROUP BY は変えず、条件の式だけを入れ替える。`has_access` は今と同じく `MAX(IF(…,1,0))` で集計の形のままにする。
  理由: 行の重なりや並び順を変えずに、M0 の記録との比較を完全一致で行えるようにする。結合が使われなくなっても、この Issue では外さない。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **文書グループ（ドキュメントグループ）**: 文書をまとめる名前つきの集まり（表 `documentgroup_names`、文書との対応は `document_groups`）。グループ管理の画面（`a=40`）で、管理側のユーザーグループ（`membergroup_names`。利用者の所属は `member_groups`）に結びつける（`membergroup_access`）と、そのグループの文書は「管理側で非公開」になる。Web 側のユーザーグループに結びつける（`webgroup_access`）と「Web 側で非公開」になる。
- **`privatemgr` / `privateweb`**: `site_content` の列。文書が管理側（Web 側）で非公開のグループに 1 つでも入っていると 1。`ManagerAPI::setMgrDocsAsPrivate()`・`setWebDocsAsPrivate()`（`manager/includes/extenders/ex_managerapi.php:749-821`）が付ける。
- **所属（`mgrDocgroups`）**: ログイン中の管理ユーザーが、自分のユーザーグループを通じてアクセスできる文書グループの id の一覧。`ManagerAPI::getMgrDocgroups()` が `membergroup_access` から求め、管理画面のリクエストのたびに `manager/includes/accesscontrol.inc.php:106` がセッションに入れ直す。ここに入るのは管理側で非公開のグループだけ。
- **`use_udperms`**: グローバル設定「文書グループの権限を使う」（既定 0）。**`udperms_allowroot`**: 「ルートの使用許可」（既定 0、`use_udperms=1` のときだけ画面に出る）。**`tree_show_protected`**: 「権限のないリソースの表示」（既定 0）。
- **`isAdmin()`**: `ManagerAPI::isAdmin()`（`ex_managerapi.php:646-657`）。ロールが `access_permissions`（ユーザーグループの権限の管理）と `web_access_permissions`（Web ユーザーグループの権限の管理）の両方を持つ。権限は `evo()->hasPermission()` がセッション `mgrPermissions` から読む（管理画面のリクエストごとに `refreshMgrPermissions()` で読み直す）。
- **`checkPermissions($docid, $duplicateDoc)`**: `manager/includes/traits/document.parser.subparser.trait.php:1112-1169`。文書（または親 0）を管理画面で扱ってよいか。各処理・画面の入口の確認で、外部のプラグインからも呼ばれる公開の API。
- **既定のロール**: Surprises の 1 つ目。検証では、権限を組み替えた独自のロールも作る（Validation）。

今の判定（main ec7b45146）。「外す」は絞り込みをしない条件、「空」は所属が空のとき。

| 箇所 | ファイル:行 | 外す | 空 | use_udperms=0 |
| --- | --- | --- | --- | --- |
| a `checkPermissions()` | trait 1112-1169 | `save_role`（ルートも） | `privatemgr=0` だけ | 絞らない |
| b 編集画面の入口 | `mutate_content/functions.php:474-520` | a と `isAllowed()` | a | a |
| c 編集画面の読み込み `db_value()` | 同 563-590 | `isAdmin()` | 絞らない | 絞る |
| d 下書き・編集の画面 | `mutate_content.dynamic.php:16-29`、`mutate_draft.dynamic.php:16-28` | b・c を呼ぶ | | |
| e 文書の情報（a=3） | `document_data.static.php:23-51` | a のあと `isAdmin()` | 絞らない | 絞る |
| f ツリー | `manager/frames/nodes.php:92-108, 673-701` | `isAdmin()`・`tree_show_protected` | `privatemgr=0` | 絞る |
| g 子の一覧（a=120） | `resources_list.static.php:13, 52-90, 125-176, 670-685` | `isAdmin()`・`tree_show_protected` | `privatemgr=0` | 絞る |
| h 検索（a=71） | `manager/actions/main/search.static.php:129-142` | `isAdmin()`・`tree_show_protected` | `privatemgr=0` | 絞らない |
| i 親の選択（a=135） | `parent_picker.dynamic.php:40, 238, 277-291, 312` | `isAdmin()` | `privatemgr=0` | 絞る |
| j 各処理 | `manager/processors/document/` の move・duplicate・remove・undelete・delete_draft・publish_draft・unpublish_draft・save_draft・`document_state.functions.php`（a=121 の一括も）、`ex_managerapi.php:994-1004` | a | a | a |
| k 保存 | `save_resource.processor.php:446-505` | a、ルートは `isAllowroot()` | a | 絞らない |
| l 保存後に開けるか（#1318） | `ex_managerapi.php:1367-1380` | b と c の組み合わせ | | 絞らない |
| m TV（編集画面） | `mutate_content/functions.php:2-45` | `isAdmin()` | TV の行が無いものだけ | 絞る |
| m' TV（保存） | `save_resource.processor.php:267-295` | ロール ID 1 | 同上 | 絞る |
| n QuickManager | `assets/plugins/qm/qm.inc.php:175-212, 270-300` | `isAdmin()`（TV はロール ID 1） | 行のある文書は不可 | 絞る |
| o 公開側の取得 | `document.parser.class.inc.php:637, 1679-1700, 3786-3800, 4212-4262, 4335-4370`、trait 2175-2310 | ロール ID 1（関数ごとに違う。Surprises） | | |
| p ルートに作れるか | `ex_managerapi.php:1139-1142`、`tree.php:372` | `save_role` | | 見ない |

DocumentParser の処理の流れ（`assets/docs/architecture.md`）への影響: `prepareResponse()`（未公開の文書の表示の確認 637 行、キャッシュの読み込みの確認 1679-1700 行、`getDocumentObject()`）が M1・M4 で変わる。`executeParser()`・`parseDocumentSource()`・`postProcess()` は変えない（部品から呼ばれる `getDocuments()` などの結果は M4 で変わる）。キャッシュ: サイトのキャッシュ・ページキャッシュの作り方と消す条件は変えない。ページキャッシュに入りうる結果の注意は Surprises。

## Plan of Work

判定を先に 1 か所へ置き（M1）、呼ぶ側を画面の群ごとに移す（M2〜M4）。M1 だけで、`checkPermissions()` を通るすべての処理と画面の入口が新しい条件になり、編集画面の 2 つの確認（b と c）の食い違いも消える（c の方が広い利用者がいなくなる）。M2 以降は、表示の条件を同じ式に置き換え、`use_udperms=0` の扱いをそろえる。

`ManagerAPI` に足すもの（Decision Log の 2 つ目）の形:

    public function userMgrDocumentGroupIds(): array
        sessionv('mgrDocgroups', []) を (array) にし、intval、0 以下を除き、重複を除いて返す
    public function restrictsByDocumentGroups(): bool
        evo()->config('use_udperms') == 1 && !$this->isAdmin()
    public function documentAccessCondition(string $alias = 'sc'): string
        絞らない → ''
        $a = $alias === '' ? '' : "{$alias}."（形の確認は Decision Log）
        所属が空 → "{$a}privatemgr=0"
        それ以外 → sprintf('(%sprivatemgr=0 OR %sid IN (SELECT document FROM %s WHERE document_group IN (%s)))',
                   $a, $a, db()->getFullTableName('document_groups'), implode(',', 所属))
    public function canAccessDocument($docId): bool
        絞らない → true
        (int) にした id で COUNT(*) FROM site_content sc WHERE sc.id=<id> AND <式('sc')> が 1 か
    public function canOpenWithDocumentGroups($groupIds)
        絞らない → true
        $private = mgrPrivateDocumentGroupIds($groupIds)、所属と重なるか、$private が空なら true

`checkPermissions()` は、先頭の `save_role` を `manager()->isAdmin()` にし、最後の問い合わせ（1149-1168 行）を `manager()->canAccessDocument($docid)` にする。ほかの分岐と順番は変えない。`isAllowroot()` は `$this->isAdmin() || (bool) evo()->config('udperms_allowroot')`。

画面の置き換えの形（M2）:

    $cond = manager()->documentAccessCondition('sc');
    WHERE: $cond === '' なら足さない。足すときは "AND {$cond}"（配列の WHERE は今の書き方に合わせる）
    ツリーと子の一覧は、さらに config('tree_show_protected') が真なら WHERE に足さない
    has_access: $cond === '' ? '1 AS has_access' : "MAX(IF({$cond}, 1, 0)) AS has_access"

`isAdmin()` で分けていた `has_access` の分岐（`nodes.php:102-106`、`resources_list.static.php:65-71, 131-133, 174-176`）は `$cond === ''` で分ける（`use_udperms=0` でも 1 になる）。

想定コミット（マイルストーンごとに 1 つ）と PR:

- M1 `fix(manager): 文書グループの絞り込みを受けない管理者の判定を ManagerAPI に置く`
- M2 `fix(manager): ツリー・一覧・検索・親の選択・文書の情報・編集画面を文書グループの同じ判定で絞る`
- M3 `fix(manager): TV と QuickManager の文書グループの判定を管理画面とそろえる`
- M4 `fix(core): 文書の取得の関数の管理者の判定を ManagerAPI にそろえる`
- M5 `docs: 文書グループの判定の置き場所を書く`

PR は 2 つに分ける。PR-A（M1〜M3 と、それに関わる M5 のドキュメント。`Refs #1338`）は管理画面だけで閉じる。PR-B（M4。`Closes #1338`）は公開側の見え方が変わるので、PR-A を base にして積む（M4 をこの Issue に入れることは未決事項の 1 で決めた）。M0 の記録は両方の確認に使う。

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。検証環境は `adaptive-validation-runner` スキルの手順で立てる（`setup-site.js --sample --editor`、`smoke.js` が全 PASS）。以下の `<dc>` は `docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`（名前やアドレスを変えて起動したときは `references/environment.md` のとおり環境変数を先頭に付ける）。管理画面へ curl するときは `Accept-Language` を必ず付ける。記録のスクリプトと出力は、リポジトリの外の作業用の一時領域（以下 `<tmp>`）に置く。

M0 着手前の確認と修正前の記録

1. `git fetch origin` のあと、PR-A のブランチ `fix/1338-m1-admin-side` を最新の main に合わせる（ブランチの割り当ては Artifacts and Notes）。`git diff --stat ec7b45146 origin/main -- manager/includes manager/actions/document manager/frames/nodes.php manager/actions/main/search.static.php manager/processors/document assets/plugins/qm` で、Context の行番号から動いたところを確かめる。
2. 検証のデータを作る（`<tmp>/1338-fixture.sql` に書き、`lib.js` の `sql()` かコンテナの `mariadb` で流す。id は控える）。テンプレートは既定のテンプレート、パスワードは管理者と同じ。
   - 管理側のユーザーグループ G1・G2。文書グループ D1（G1 に結びつける）・D2（G2 に結びつける）・DW（Web のグループ WG にだけ結びつける）。
   - 公開の親フォルダ T（グループなし、`pagetitle` は `u1338-T`）と、その子: `u1338-pub`（グループなし）、`u1338-m1`（D1）、`u1338-m2`（D2）、`u1338-w`（DW）、フォルダ `u1338-f2`（D2）とその子 `u1338-c2`（グループなし）、未公開の `u1338-u2`（D2）。グループを付けたあと、管理画面のグループ管理の保存か `secureMgrDocument()` と同じ規則で `privatemgr`・`privateweb` を付ける（`SELECT id,pagetitle,privatemgr,privateweb FROM modx_site_content WHERE pagetitle LIKE 'u1338-%'` で m1・m2・f2・u2 が `privatemgr=1`、w が `privatemgr=0,privateweb=1`、ほかは 0 であること）。
   - TV `u1338tv`（テキスト）を既定のテンプレートに付け、`site_tmplvar_access` で D2 にだけ結びつける。
   - ロール（投稿担当者 ID 3 の行を複製し、次の 3 つの権限だけを変える）: R_S（`save_role=1`）、R_P（`access_permissions=1`・`web_access_permissions=1`、`save_role=0`）、R_A（`access_permissions=1` だけ）、R_W（`web_access_permissions=1` だけ）。
   - 利用者: `admin`（ロール 1）、`webmaster`（ロール 2、所属なし）、`editor`（ロール 3、G1。`setup-site.js --editor` が作る）、`editor0`（ロール 3、所属なし）、`saveonly`（R_S、G1）、`saveonly0`（R_S、所属なし）、`adminnosave`（R_P、所属なし）、`acc`（R_A、所属なし）、`web`（R_W、所属なし）。
   - 取得の関数の確かめ用（M4）: モジュール `u1338probe`（管理画面で動く。`a=112&id=<id>`）と、公開側の文書 `u1338-probe`（キャッシュしない、本文は呼び出しをキャッシュしない形 `[!u1338probe!]` のスニペット）。どちらも、T の子の id ごとに `getDocumentObject('id',…)` が返るか、`getPageInfo(…,0)`、`getActiveChildren(T)`・`getDocumentChildren(T,1)`・`getDocumentChildren(T,0)`・`getAllChildren(T)`・`getDocuments(<T の子の id>, null)` の id の一覧を JSON で出す。
   - QuickManager が有効かを確かめる（`SELECT name,disabled,properties FROM modx_site_plugins WHERE name LIKE '%QuickManager%'`）。無効なら有効にし、TV ボタンを出す設定（プロパティ `tvbuttons=true`。既定は `false`、`qm.inc.php:33, 51`）にして、文書の本文に TV ボタンの印 `<!-- qm_tv u1338tv -->` を入れる。変えたことを Artifacts and Notes に書く。QuickManager の `checkAccess()` は、ツールバーの編集ボタン（`on_web_page_prerender.php:38-43` の `qmEdit`）だけでなく、公開側からの TV の保存（`inc/edit_tv.inc.php:5`）の可否も決める。
3. 記録のスクリプト `<tmp>/1338-m0.js`（`lib.js` の `login()`・`sql()` を使う Playwright）を作る。設定の組 V1（`use_udperms=1`、`tree_show_protected=0`、`udperms_allowroot=0`）・V2（V1 で `tree_show_protected=1`）・V3（`use_udperms=0`、印はそのまま）を `REPLACE INTO modx_system_settings` で切り替え、キャッシュを消してから、利用者ごとにログインし直して次を記録する。1 行は「設定・利用者・場所・対象・結果」の TSV（`<tmp>/1338-m0.tsv`）。
   - T（ツリー）: `index.php?a=1&f=nodes&indent=1&parent=<T>&expandAll=1` と `parent=0` の HTML から、出たノードの id と `protectedNode` の有無。
   - L（子の一覧）: `a=120&id=<T>` の行の id、`protectedNode` の有無、「すべてを選択」の id の一覧（data 属性）。
   - S（検索）: `a=71&submitok=1&pagetitle=u1338` の結果の id。
   - P（親の選択）: `a=135&ajax=search&q=u1338` の JSON の id と `disabled`、`a=135` の画面にルートの項目があるか。
   - I（文書の情報）・E（編集画面）: `a=3&id=X`・`a=27&id=X` が開けるか（フォームに `id` が X で出るか、権限のエラーか、空の画面か）。対象は T の子すべて。
   - X（操作の確認。DB を変えない）: `a=52&id=X&new_parent=<T>`（同じ親への移動は、確認を通ると「同じ親」の知らせで止まり、通らないと権限のエラー）。あわせて `a=52&id=<u1338-pub>&new_parent=0`（ルートへの移動）の結果。
   - R（ルート）: ツリーの「ルートに新規作成」のボタン（`tree.php:372`）があるか。
   - V（TV）: `a=27&id=<u1338-pub>` に `tv<u1338tv の id>` の欄があるか。admin・webmaster・editor では、その欄（あれば）に `m0-<利用者>` を入れて保存し、`site_tmplvar_contentvalues` に入ったかを記録してから値を消す。
   - Q（QuickManager）: 公開側の T の子のページを開き、ツールバーの編集ボタン（`li.qmEdit`）が出るか、`u1338tv` の TV ボタン（`span.qm_tv`）が出るか。
   - F（公開側）: `index.php?id=<u1338-w>`（Web 側で非公開）と `index.php?id=<u1338-u2>`（未公開）を、管理画面にログインしたまま開いたときの応答（本文が出る・未認可のページ・エラーのページ）。
   - M（取得の関数）: `u1338probe` のモジュールと `u1338-probe` の文書の JSON。
   - W（警告）: 記録の最後に `<dc> exec app php evo log:tail --lines=50` の PHP の警告・エラーの件数。
   基準のコミットを Artifacts and Notes に書く。期待: Validation and Acceptance の表の「main」の列と一致する。違えば Surprises に書き、表を直してから M1 に進む。

M1 判定を `ManagerAPI` に置く

4. `manager/includes/helpers.php` の `manager()` を、`evo()->manager` が無ければ `evo()->loadExtension('ManagerAPI')` してから返す形にする（コメントに「公開側でも、管理画面にログインした利用者の判定（文書グループ・QuickManager）に使う」と書く）。
5. `manager/includes/extenders/ex_managerapi.php`:
   - `isAdmin()` の docblock に、文書グループの絞り込みを受けない管理者の定義であること（#1338）と、使う場所は `restrictsByDocumentGroups()` を通すことを書く。
   - Plan of Work の 4 つのメソッドを、`canOpenWithDocumentGroups()` の前に足す。docblock に、`privatemgr` の列の式と #1318 の集合の式が同じ定義であること（Surprises の最後）、印の食い違いは #1353 であることを書く。
   - `canOpenWithDocumentGroups()` の本文と docblock を Plan of Work の形にする（「2 つの確認をそのまま式にした」「#1338 で 1 か所にまとめる」の記述を消す）。
   - `isAllowroot()` を `$this->isAdmin() || (bool) evo()->config('udperms_allowroot')` にし、コメントに理由（文書グループの設定の 1 つで、同じ管理者の判定を使う）を書く。
6. `manager/includes/traits/document.parser.subparser.trait.php` の `checkPermissions()`: 1124 行の `evo()->hasPermission('save_role')` を `manager()->isAdmin()` にし、1149-1168 行（所属から条件を組んで数える部分）を `return manager()->canAccessDocument($docid);` にする。親の問い合わせとルートへの複製の確認（1142-1147）は残す。関数の先頭に、文書グループの判定の正は `ManagerAPI` であることをコメントで書く。
7. 構文の確認:
       <dc> exec app php -l manager/includes/extenders/ex_managerapi.php
       docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l manager/includes/extenders/ex_managerapi.php
   変えたファイルごとに行う。期待: `No syntax errors detected`。
8. M0 のスクリプトで取り直し（`<tmp>/1338-m1.tsv`）、`diff` で M0 と比べる。期待: 差は、Validation の表で「M1」と書いた行だけ。`git grep -n "hasPermission('save_role')" -- manager/includes/traits manager/includes/extenders` が出力なし。

M2 管理画面の一覧と読み込み

9. `manager/frames/nodes.php`: `getNodes()` の 92-106 行の `$mgr_docgroups`・`$in_docgrp`・`isAdmin()` の分岐を、Plan of Work の `$cond` と `has_access` にする。`tree_access_condition()` を「`tree_show_protected` が真か `$cond` が空なら ''、それ以外は `' AND ' . $cond`」にし、docblock を直す。`hasChildren()` はそのまま `tree_access_condition()` を使う。
10. `manager/actions/document/resources_list.static.php`: 52-71 行と 129-139 行の `$docgrp`・`$in_docgrp`・`isAdmin()` の分岐を、9 と同じ形にする（`tree_show_protected` で WHERE に足さないのも同じ）。174-176 行の `isAdmin()` で `has_access=1` にする分岐は、`$cond === ''` のときにする。81-82 行のコメントの「管理者でない利用者」を「絞る利用者」に直す。
11. `manager/actions/main/search.static.php` の 129-142 行を、`$cond = manager()->documentAccessCondition('');`（表に別名が無い問い合わせなので空）にし、空でなければ `$where[] = '(' . $cond . ')'` にする。`tree_show_protected` を見ないことと理由（Decision Log）をコメントに書く。
12. `manager/actions/document/parent_picker.dynamic.php` の `parent_picker_access_where()`（273-291 行）を、`$cond` が空なら ''、それ以外は `' AND ' . $cond` にする。
13. `manager/actions/document/document_data.static.php` の 37-43 行を、`$cond` が空でなければ `'AND ' . $cond` を `$where` に足す形にする。
14. `manager/actions/document/mutate_content/functions.php`: `db_value($id, $docgrp)` の第 2 引数をやめ、`documentAccessCondition('sc')` で絞る（`(manager()->isAdmin() || !$docgrp)` の分岐を消す）。`mutate_content.dynamic.php:28`・`mutate_draft.dynamic.php:28` の呼び出しを直す。`getDocgrp()` は M3 で TV に使うので、ここでは残す。
15. 構文の確認（手順 7 と同じ）。`git grep -n "mgrDocgroups" -- manager/frames manager/actions/document manager/actions/main` の結果が、`mutate_content/functions.php` の `getDocgrp()` だけになる。
16. 取り直し（`1338-m2.tsv`）、M1 の記録と比べる。期待: 差は表の「M2」の行だけ。

M3 TV と QuickManager

17. `manager/actions/document/mutate_content/functions.php` の `getTmplvars($docid, $template_id, $docgrp)`: 38-42 行の `manager()->isAdmin() ? 1 : 0` を `manager()->restrictsByDocumentGroups() ? 0 : 1` にし、第 3 引数をやめて `manager()->userMgrDocumentGroupIds()` から作る。`getDocgrp()` と、呼び出し（`mutate_content.dynamic.php:21, 49, 69`、`mutate_draft.dynamic.php:21, 51`、`functions.php:157`、`fields.php:315` のコメント）を直し、`getDocgrp()` を消す（ほかで使っていないことを `git grep -n "getDocgrp" -- manager assets` で確かめる）。
18. `manager/processors/document/save_resource.processor.php` の `get_tmplvars()`（274-295 行）: `sessionv('mgrRole')` と `sessionv('mgrDocgroups')` を、17 と同じ形にする。
19. `assets/plugins/qm/qm.inc.php`: `checkAccess()`（175-212 行）を、`documentIdentifier` が空なら false、それ以外は `manager()->canAccessDocument(evo()->documentIdentifier)` にする。`checkTvAccess()`（270-300 行）の `sessionv('mgrRole') == 1` を `!manager()->restrictsByDocumentGroups()` にし、所属は `manager()->userMgrDocumentGroupIds()` から読む。`assets/plugins/qm/inc/on_web_page_prerender.php:214-217` の `$this->docGroup` の作り方も `userMgrDocumentGroupIds()` にする（使っていなければ消す。`git grep -n "docGroup" -- assets/plugins/qm` で確かめる）。
20. 構文の確認。`git grep -n "mgrRole" -- manager/actions/document manager/processors/document assets/plugins/qm/qm.inc.php` が、`save_resource.processor.php` の文書グループと関係ない行（`sessionv('mgrRole')` を使う別の処理があれば）だけになることを確かめる。
21. 取り直し（`1338-m3.tsv`）、M2 と比べる。期待: 差は表の「M3」の行だけ。ここまでで PR-A を作る（本文に Validation の表の M1〜M3 の行と、リリースノートの文案の管理画面の部分を写す）。

M4 DocumentParser の取得の関数

22. `manager/includes/document.parser.class.inc.php` に、公開側の判定の入口として public の `isManagerAdmin(): bool`（`(bool) $this->isLoggedIn('mgr') && manager()->isAdmin()`。docblock に「定義は ManagerAPI::isAdmin()。ログインしていない閲覧では ManagerAPI を読み込まない」）を足す。trait の関数は `$modx->isManagerAdmin()` で呼ぶ（trait が `$modx` 経由で書かれているため public にする）。
23. 管理画面側の分岐を `manager()->documentAccessCondition()` にする:
    - `getDocumentObject()`（3786-3800）: 公開側は今の `privateweb=0`・所属の OR に、`isManagerAdmin()` なら条件を付けない形。管理画面側は `$cond` が空なら条件なし、あれば `$cond`。`1=<ロールの ID>` をやめる。
    - `getPageInfo()`（4335-4370）: 管理画面側の `1='<ロールの ID>' OR sc.privatemgr='0'` と所属の OR を `$cond` にする。公開側は変えない。
    - `getDocuments()`（4212-4262）: 2 つの分岐の `mgrRole != 1` を、公開側は `!isManagerAdmin()`、管理画面側は `$cond`（所属が無い分岐は別名なしの `documentAccessCondition('')`）にする。
    - キャッシュの読み込みの確認（1679-1700）: `$this->session('mgrRole') != 1` を `!$this->isManagerAdmin()` にする。
    - trait の `getAllChildren()`（2196-2215）: 公開側は `privateweb=0`・所属の OR に、`isManagerAdmin()` なら条件なし。管理画面側は `$cond`。
    - trait の `getActiveChildren()`（2257-2267）・`getDocumentChildren()`（2296-2306）: 管理画面側の `$_SESSION['mgrRole'] != 1` の分岐を `$cond` にする（`getDocumentChildren()` の、管理者で所属があると子が減る不具合もこれで消える）。公開側は変えない。
24. 構文の確認。`git grep -n "mgrRole" -- manager/includes/document.parser.class.inc.php manager/includes/traits` が、`token_auth()` の 1241 行（ログインの処理）だけになる。
25. 取り直し（`1338-m4.tsv`）、M3 と比べる。期待: 差は表の「M4」の行だけ。PR-B を作る。

M5 ドキュメントと最終確認

26. `assets/docs/architecture.md` の「データアクセスと拡張」に、文書グループの判定の置き場所を 3〜5 行で書く（正は `ManagerAPI` の `restrictsByDocumentGroups()`・`documentAccessCondition()`・`canAccessDocument()`・`canOpenWithDocumentGroups()`、管理者は `isAdmin()`、`checkPermissions()` はこれを使う公開の API、`tree_show_protected` は表示だけ、公開側の `privateweb` の扱いは DocumentParser）。詳しい規則は docblock を正にし、ドキュメントに複製しない。`doc-audit` の手順で、変えたファイルのパスの表記と参照を確かめる。
27. PHP 7.4 の検証環境（`references/environment.md` の PHP 7.4 の節）で、V1 の admin・saveonly0・adminnosave・editor の T・I・E・X・F・M を取り、PHP 8 側の M4 の記録と同じになることを確かめる。
28. 全体を取り直し（`1338-m5.tsv`）、M4 と同じであること。ブラウザのコンソールと `log:tail` にエラーが無いこと。

## Validation and Acceptance

Docker の検証環境で、M0 の手順 2 のデータを使い、M0 の手順 3 の記録を main と各マイルストーンで比べる。「同じ」は main の記録と一致すること。「変わる」は、その列のマイルストーンから表の右の結果になり、それ以外の行は main と同じであること。

既定のロール（管理画面）:

| id | 利用者・設定 | 場所 | main | 変更後 |
| --- | --- | --- | --- | --- |
| A1 | admin・webmaster、V1〜V3 | T・L・S・P・I・E・X・R | すべて出て、すべて開けて操作できる | 同じ |
| A2 | editor（G1）、V1 | T・L・S・P | pub・m1・w・T が出る。m2・f2・c2・u2 は出ない | 同じ |
| A3 | editor、V1 | I・E・X | m1・pub・w は可。m2・f2・u2 は権限のエラー | 同じ |
| A4 | editor0（所属なし）、V1 | T・L・S・P・I・E・X | `privatemgr=0` の文書だけ出て、開けて操作できる | 同じ |
| A5 | editor、V2 | T・L | m2・f2 が `protectedNode` で出る。L の「すべてを選択」に入らない | 同じ |
| A6 | editor、V2 | S | m2・f2・u2 も結果に出る（開くとエラー） | 出ない（M2。Decision Log） |
| A7 | editor、V3 | T・L・P | m2・f2・u2 が出ない | 出る（M2） |
| A8 | editor、V3 | I・E | m2 は I・E とも権限のエラー（`checkPermissions()` は通るが、読み込みで絞られる） | 開ける（M2） |
| A9 | editor0、V3 | T・L・P | m1・m2 などが出ない（I・E は開ける） | 出る（M2） |
| A10 | editor・editor0、V1・V3 | R | ルートに作るボタンなし | 同じ |

独自のロール（管理画面）:

| id | 利用者・設定 | 場所 | main | 変更後 |
| --- | --- | --- | --- | --- |
| B1 | saveonly（G1）、V1 | T・L・S・P | editor と同じ | 同じ |
| B2 | saveonly、V1 | X（m2） | 通る（同じ親の知らせ） | 権限のエラー（M1） |
| B3 | saveonly、V1 | I・E（m2） | 権限のエラー（入口は通り、読み込みで絞られる。文言なしのエラー 3） | 権限のエラー（M1 で入口で止まり、文言が `access_permission_denied` になる） |
| B4 | saveonly0（所属なし）、V1 | I・E（m1・m2） | 開ける（読み込みが絞らない） | 権限のエラー（M1） |
| B5 | saveonly・saveonly0、V1 | R・P のルート・X のルートへの移動 | ボタンあり・項目あり・通る | なし・なし・権限のエラー（M1） |
| B6 | adminnosave（isAdmin、save_role なし）、V1 | T・L・S・P | すべて出る | 同じ |
| B7 | adminnosave、V1 | I・E・X（m1・m2・f2・u2） | 権限のエラー | 開けて操作できる（M1） |
| B8 | adminnosave、V1 | R・P のルート・X のルートへの移動 | なし・なし・権限のエラー | あり・あり・通る（M1） |
| B9 | acc・web（片方だけ）、V1 | すべて | editor0 と同じ | 同じ |

TV・QuickManager:

| id | 利用者・設定 | 場所 | main | 変更後 |
| --- | --- | --- | --- | --- |
| C1 | admin、V1 | V | 欄があり、保存される | 同じ |
| C2 | webmaster、V1 | V | 欄があり、保存されない（要確認。Surprises） | 保存される（M3） |
| C3 | editor、V1 | V | 欄なし | 同じ |
| C4 | editor、V3 | V | 欄なし | 欄あり（M3） |
| C5 | editor0、V1 | Q（w） | 編集ボタンなし | あり（M3） |
| C6 | editor、V3 | Q（m2） | なし | あり（M3） |
| C7 | webmaster、V1 | Q の TV ボタン | なし | あり（M3） |
| C8 | admin、editor、V1 | Q（pub・m1・m2） | admin はすべて、editor は pub・m1 | 同じ |

公開側と取得の関数:

| id | 利用者・設定 | 場所 | main | 変更後 |
| --- | --- | --- | --- | --- |
| D1 | ログインなし | F・M の公開側 | w は未認可のページ、u2 はエラーのページ。M は w を含まない | 同じ |
| D2 | admin、V1 | F（w） | 本文が出る | 同じ |
| D3 | webmaster・adminnosave、V1 | F（w） | 未認可のページ | 本文が出る（M4） |
| D4 | saveonly0、V1 | F（u2） | 本文が出る（`checkPermissions()` を `save_role` で通る） | エラーのページ（M1） |
| D5 | adminnosave、V1 | F（u2） | エラーのページ | 本文が出る（M1） |
| D6 | admin、V1 | M の管理画面側 | 記録（admin に所属が無いので全部） | 同じ |
| D7 | admin を G1 に入れる、V1 | M の管理画面側 `getDocumentChildren` | m1 だけ（不具合。Surprises） | 全部（M4） |
| D8 | webmaster、V1 | M の公開側 `getDocumentObject`・`getDocuments`・`getAllChildren` | w を含まない | 含む（M4） |
| D9 | editor、V3 | M の管理画面側 | m2 などを含まない | 含む（M4） |

あわせて確かめること:

- #1318 の保存の確認: V1 で adminnosave が pub の文書グループを D2 だけにして保存すると通り、保存後に a=27 で開ける（main は `resource_permissions_error` で止まる。M1）。editor が m1 の本文だけを変えて保存すると通る（main と同じ）。グループの欄を変えられるのは `documentGroupAccess()` が許す利用者だけで、saveonly・saveonly0・acc は D2 を選べない（main と同じ。画面の欄で確かめる）。
- QuickManager の公開側からの TV の保存（`quickmanagertv=1`）: C5・C6 の利用者・文書で、`save_document` があれば保存の画面が開く（main は `Error: Access denied.`。M3）。C7 の webmaster は D2 に限った TV も保存できる（M3）。
- `checkPermissions()` を使う各処理（複製 a=94・削除 a=6・復元 a=63・公開の一括 a=121・下書きの保存など）は、B2・B4・B7 と同じ向きに変わる。代表として、使い捨ての文書で saveonly0 と adminnosave の複製を 1 回ずつ行う。
- 記録の W（PHP の警告・エラー）が各マイルストーンで 0 件（main で出ているものは同じ件数）。ブラウザのコンソールにエラーが無い。
- 静的な確認: 手順 8・15・20・24 の grep。変えたすべての PHP ファイルで、`<dc> exec app php -l` と `php:7.4-cli` の `php -l` が `No syntax errors detected`。

## Idempotence and Recovery

M1〜M4 はコードだけの変更で、DB の形・設定・データは変えない。どのマイルストーンのコミットも revert で戻せる（M2〜M4 は M1 のメソッドを使うので、戻すときは後ろから）。M1 だけを残して止めても、判定は 1 か所で、画面は今の条件のまま動く（一覧の見え方が編集画面の入口より広い・狭いことは残る）。検証のデータは `u1338-` で始まる名前で作るので、`site_content`・`documentgroup_names`・`membergroup_names`・`user_roles`・`manager_users` などから名前で消せる。設定の組は `REPLACE INTO modx_system_settings` で戻し、キャッシュを消す。M0 の記録とスクリプトは git の管理外の一時領域にあり、消えていたら Artifacts and Notes の基準のコミットを立てて取り直す。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1338（最後のコメントが着手時の決定）
- ブランチ（メインが決めた）: `fix/1338-docgroup-bypass-condition` は `gh issue develop` で #1338 に紐づけ済み。紐づいたブランチの PR は本文が `Refs` でもマージで Issue を閉じるので、このブランチは最後の PR-B（M4。`Closes #1338`）に使う。この計画のコミットと M0〜M3（PR-A。`Refs #1338`）は、紐づけないブランチ `fix/1338-m1-admin-side` を origin/main から作って載せる。PR-B は、PR-A のブランチを紐づいたブランチに取り込んでから M4 を積み、base を PR-A のブランチにする。PR-A のマージ後に base を main に切り替える。
- 関連: #1318（PR #1349、マージ済み。`canOpenWithDocumentGroups()` と保存の確認）、#1353（`privatemgr` の付け方の食い違い。範囲外）、#1300・#1307（文書グループの付与の規則）。
- 修正前の記録: 基準のコミットと置き場所（`<tmp>/1338-m0.tsv`、スクリプト `1338-m0.js`、データ `1338-fixture.sql`）を M0 で書く。
- 想定コミット単位は Plan of Work の 5 つ。

## Interfaces and Dependencies

リリースノートに書くこと（文案）:

- 文書グループで「管理側で非公開」にした文書の扱いで、すべての文書を見て操作できる「管理者」を、ロールに「ユーザーグループの権限の管理」と「Web ユーザーグループの権限の管理」の両方があるもの、に統一した。これまでは場所によって、「ロールの保存」の権限があるもの、ロールの ID が 1 のもの、と違っていた。
- 既定のロール（Administrator・ウェブマスター・投稿担当者、古いサイトの Editor・Publisher）の管理画面での見え方と操作は変わらない。独自のロールでは次が変わる。
  - 「ロールの保存」の権限だけを持つロールは、所属しないグループの非公開の文書を開く・移動・削除・公開・複製などができなくなる。ルート直下への作成と移動も「ルートの使用許可」に従う。
  - 2 つの権限の管理を両方持ち、「ロールの保存」を持たないロールは、すべての文書を開いて操作でき、ルート直下にも作れるようになる。
- 管理側の文書グループに 1 つも所属しない利用者は、どの画面でも、管理側で非公開でない文書だけを扱える（これまで、文書の情報と編集画面では非公開の文書も読めることがあった）。
- 「文書グループの権限を使う」が「いいえ」のときは、ツリー・子の一覧・親の選択・文書の情報・編集画面・TV も文書グループで絞らない（これまで、以前に付いた非公開の印が残っていると、これらの画面にだけ出なかった。id を指定すれば開けていた）。
- 「権限のないリソースの表示」は、ツリーと子の一覧に開けない文書を印つきで出すだけの設定になる。検索の結果には出さない（これまでは出て、開くとエラーになっていた）。
- 文書グループに限った TV: ウェブマスターが、所属しないグループに限った TV を編集画面で変えたとき、保存されるようになった（これまでは画面に出るのに保存されなかった）。QuickManager の TV ボタンも同じ条件で出る。
- QuickManager の編集ボタンは、管理画面で開ける文書なら出る（Web 側だけのグループが付いた文書や、「文書グループの権限を使う」が「いいえ」のときも出る）。
- 公開側（PR-B）: 管理画面にログインしたまま公開側を見るとき、Web 側で非公開の文書を見られる利用者を、ロールの ID が 1 から上の「管理者」に変えた。既定のロールでは、ウェブマスターも見られるようになる。「ログイン時のキャッシュ」を無効（キャッシュを使う）にしているサイトでは、管理者が見たときの一覧の部品の出力がページキャッシュに入ることがある（これまでも ID 1 で同じ）。
- 開発者向け: `ManagerAPI` に `restrictsByDocumentGroups()`・`documentAccessCondition()`・`canAccessDocument()`・`userMgrDocumentGroupIds()` を足した。文書グループで絞る問い合わせは、これらを使う。`manager()` は公開側でも `ManagerAPI` を読み込んで返す。`checkPermissions()` の引数と戻り値は変えていない。`db_value()`・`getTmplvars()`（編集画面の関数）の引数が減り、`getDocgrp()` は無くなった。

変えないもの: DB の形、`privatemgr`・`privateweb` の付け方（#1353）、`isAllowed()`・`isInAllowedParents()`（許可するリソースの範囲）、文書グループの付け方（`documentGroupAccess()` 以下、#1300・#1307・#1318）、公開側の `privateweb` の絞り方（ロールの ID の置き換えを除く）、ルートの入口ごとの `use_udperms` の扱い、プラグインのイベント。

依存: #1318 は main にマージ済み（ec7b45146）。#1353 とは同じ `ex_managerapi.php` に触れるが、別のメソッド（`setMgrDocsAsPrivate()` を使う側）なので、どちらが先でもよい（後の方が衝突を手で直す）。

## 未決事項

なし。2026-11-10 に次のとおり決めた。

1. M4（公開側を含む DocumentParser の取得の関数）は、この Issue に入れる（ユーザーさんの判断。planner の推奨 (a)）。決定どおり「ロールの ID が 1」を残さない。ウェブマスターの公開側の見え方が変わる（D3・D8）ので、PR-B に分け、リリースノートで知らせる。
2. 範囲外とした食い違いは、別の Issue にした（メイン）。
   - 文書グループで絞っていない一覧（予約の一覧・エレメントの画面の文書の選択・テンプレートの「使う文書」）: #1354。M1 の後に `documentAccessCondition()` で直す
   - Ditto・Wayfinder の独自の条件: #1355。M1 の後に直す
   - ルートの入口ごとの `use_udperms` の扱い: #1356。既定のロールの動きが変わるので、着手時に相談して決める
   - privatemgr の付け方の食い違い（DocAPI・ddtools）: #1353（計画の前に起票済み）
