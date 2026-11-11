# ExecPlan: 文書グループの絞り込みを受けない「管理者」の条件を 1 か所にそろえる（Issue #1338）

## Purpose / Big Picture

文書グループで「管理側で非公開」にした文書を、だれが見られて操作できるかを、管理画面のどの画面・どの処理でも同じ 1 つの判定で決める。今は場所ごとに「管理者」の条件が違い（`save_role` 権限・`isAdmin()`・ロールの ID が 1）、所属が無い利用者の扱いや「文書グループの権限を使う」が無効のときの扱いも場所ごとに違うため、ツリーに出ないのに開ける、開けると判定されるのに編集画面が空になる、編集画面で変えた TV が保存されない、といった食い違いが起きている。判定を `ManagerAPI` の 1 か所に置き、すべての箇所がそれを使うようにする。絞り込みを受けない「管理者」は「アクセスの規則を自分で変えられる人」（`isAdmin()` または `save_role`）とし、今より見えなくなる利用者を作らない。

## Progress

- [x] (2026-11-10) 計画の作成（main ec7b45146 で調査。Context の表・Surprises・Decision Log）
- [x] (2026-11-10) 決定の見直しを反映（管理者＝`isAdmin()` または `save_role`。判定は `bypassesDocumentGroups()`。Validation の変更後の列を計算し直した）
- [x] (2026-11-10) M0: 修正前の記録（基準 f6e94f10d。V1〜V3 は 9 利用者、V4 は 3 利用者。表との違い A2・A5・C5・C6・D6 を Validation に反映。PHP の警告・JS のエラー 0 件）
- [x] (2026-11-10) M1: `ManagerAPI` に判定を置き、`checkPermissions()`・`isAllowroot()`・`canOpenWithDocumentGroups()` をそれに通す（1118f9f74、自己レビューの低の手当て f58424d75。M0 との差は adminnosave の 112 行だけで、すべて表の B8・B9・B10・C11・D5 の期待どおり、見えなくなる向きの差は 0。V4 は差 0。PHP の警告・JS のエラー 0 件。自己レビューは高なし、中 1（#1318 の保存の確認の変化が表に無かった）を B12・B13 として表に足し M2 で確かめる）
- [x] (2026-11-11) M2: 管理画面の一覧と読み込み（ツリー・子の一覧・検索・親の選択・文書の情報・編集画面の `db_value()`）を同じ判定にする（a448581e1、自己レビューの低の手当て 5eeb11cbf。M1 との差はすべて表の M2 の列どおり、期待に無い差 0。見えなくなる向きは A6 の 4 行だけ。B12・B13 は保存でき、保存のあと開ける。PHP の警告・JS のエラー 0 件。自己レビューは高・中なし）
- [x] (2026-11-11) M3: TV のアクセス（編集画面・保存・QuickManager）と QuickManager の編集ボタンを同じ判定にする（aa0aff040。TV の判定の重複を ManagerAPI の `tmplvarAccessCondition()`・`canAccessTmplvar()` に集めた 3a1a6934e、自己レビューの中 1（別名なしの式が外側の行を参照できない）と低 3 の手当て 40c9b4328。M2 との差は V・Q・QT の行だけで表の M3 の期待どおり、見えなくなる向き 0。C2（webmaster の TV の保存）が直った。TV の欄の並びは変わらず重複 0。PHP の警告・JS のエラー 0 件）
- （2026-11-11）M1〜M3 を PR-A（#1361）にまとめ、下書きにした。1.3 には入れず、1.3 のリリース後に M4 から再開する（ユーザーさんの判断。Issue のコメント）。再開するときは、PR-A のブランチに origin/main を取り込み、M0 の手順 1 と同じく Context の行番号から動いたところを確かめてから M4 に入る
- [x] (2026-11-11) M4: DocumentParser の取得の関数（管理画面側の分岐は同じ判定、公開側は「ロールの ID が 1」を `bypassesDocumentGroups()` に）（7cb9515e6。M3 との差は表の D3・D6〜D9・D11 の行だけで期待どおり、見えなくなる向き 0、ログインしていない訪問者の行は 1 行も変わらない。ツリーのノード名を日付にしたときの修正前からの 500 は、新しく見える文書の分は M4 で消え、`tree_show_protected=1` の開けない文書の分は 2d7bd224f（列の添字の数え間違いで日付がすべて `- - -` になったのを 8597eae0d で直した）で手当てした。PHP の警告・JS のエラー 0 件。自己レビューは問題なし）
- [x] (2026-11-11) M5: ドキュメント・PHP 7.4 での確認・最終の突き合わせ（architecture.md は cb719e9ec・fea7eef01・7cb9515e6、core-issues.md は f00be5d7c・fea7eef01。全体の取り直しは M4 と差 0。PHP 7.4.33 と MySQL 5.7.44（ONLY_FULL_GROUP_BY）で差 0・エラー 0。ツリーの日付の表示は M4 と一致し、M4 で 500 だった組も日付が出る。PR-B の最終の自己レビューは問題なし）

## Surprises & Discoveries

M4 で分かったこと（2026-11-11）:

- 実装の報告に「公開側の `getDocuments()` は所属が無い分岐で `privateweb` を絞らない」とあったが、誤りだった。修正前も修正後も、所属が無い分岐の公開側では `AND privateweb=0` が入る（自己レビューでコードを、検証で HTTP と CLI の両方を確かめた）。ログインしていない訪問者と Web のグループに所属しない Web 利用者は、修正前・修正後とも、どの取得の関数でも Web 側で非公開の文書を受け取らない。
- 「ログイン時もキャッシュを使う」（`disable_cache_at_login=0`、既定は 1）のサイトでは、管理者が公開側を見たときの一覧の部品の出力がページキャッシュに入り、ログインしていない訪問者にも出る。修正前もロールの ID 1 で起きていた（検証で確認）。M4 から、ウェブマスターなど管理者の判定が真の利用者でも起きる。根本の対策は `assets/docs/core-issues.md` の「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」の課題として残す。
- ツリーのノード名を日付（`resource_tree_node_name`）にすると、`nodes.php` が文書ごとに `getDocumentObject()` を呼び、開けない文書で false を読んで 500 になっていた（修正前から）。PR-A で新しく見える文書の分は M4 で消えたが、`tree_show_protected=1` で開けない文書の分は残ったので、ツリーの行の問い合わせで日付の列を取り、`getDocumentObject()` を呼ばない形にした（2d7bd224f・8597eae0d）。行は位置で受ける既存の分解に `$nodeDate` を足し、日付の設定でないときは `array_pad()` で null にする。この形では、日付の設定のときにノードごとに発火していた `OnLoadDocumentObject` と TV の読み込みが無くなる（ツリーが軽くなる。イベント自体はほかの経路で発火する）。開けない文書の日付は、印つきで出しているタイトルと同程度の情報として出す。

M3 で分かったこと（2026-11-11）:

- 計画では TV のアクセスの条件を編集画面・保存・QuickManager の 3 か所にそれぞれ置き換える予定だったが、C2（webmaster の TV が保存されない）の原因そのものが「同じ条件が編集画面と保存の 2 か所にあって食い違った」ことだったので、`ManagerAPI` に `tmplvarAccessCondition($alias)`（SQL の条件の片）と `canAccessTmplvar($tmplvarId)`（1 つの TV）を置き、3 か所がそれを使う形にした（計画の Decision Log の「ManagerAPI に置く判定」に 2 つ加わる）。条件を副問い合わせにしたので、`getTmplvars()`・`get_tmplvars()` の `site_tmplvar_access` の LEFT JOIN は使わなくなり外した（計画の「JOIN は変えない」から外れるが、残すと行を重複させて DISTINCT で消すだけの結合になる）。返る TV と並びが変わらないことは、TV を一時的に足して確かめた。
- 最初の式は、別名を空で渡すと副問い合わせの `id` が `site_tmplvar_access` 自身の id 列を指し、制限した TV が誰にでも通った（今の呼び出しはすべて別名 `tv` で影響なし）。`documentAccessCondition()` と同じく外側の行を参照しない形（`id NOT IN (…) OR id IN (…)`）にした。`tmplvarid` は NOT NULL なので NOT IN の NULL の問題は無い。
- QuickManager の編集ボタンは、文書グループで開けるかだけを見て、許可された親の範囲と `edit_document` を見ない（修正前から）。#1360 に分けた。

M2 で分かったこと（2026-11-11）:

- M1 と M2 の間の一時的な食い違い（B12 の保存のあとの編集画面）は、`db_value()` を同じ判定にして消えた。
- 管理者でない editor（G1）が f2（D2）の下に新規の文書を作ろうとすると、#1318 の `resource_permissions_error` に届く前に、親の確認（`checkParent()`）の `error_no_privileges` で止まる（入口の `a=4&pid=<f2>` はフォーム自体が出ない）。#1318 の確認は、変えてよいグループを持つ利用者（`access_permissions` を持ち G1 に所属する検証用の `accg`）で、D2 だけを付けると止まり、D1 を付けると保存できることで確かめた。
- 文書グループを付けて保存する検証では、グループの無い文書で既定でオンの「すべての文書グループ」（`chkalldocs`）を外さないと、選んだグループが付かない（画面の仕様）。
- 新しい式（`MAX(IF(… IN (SELECT …)),1,0))` と `IN` の副問い合わせ）は MariaDB 11 でしか動かしていない。README の動作要件の MySQL 5.7 でも、M5 で確かめる（手順 28 に足した）。

M1 で分かったこと（2026-11-10）:

- #1318 の保存の確認（`canOpenWithDocumentGroups()`）を「管理者か、集合が届く」にしたので、saveonly（save_role、所属あり）が所属しない非公開のグループを引き継ぐ新規の作成と、adminnosave が非公開のグループを付ける保存が、止まらなくなる（表の B12・B13）。どちらも最終の設計どおり。ただし M1 の時点では、保存のあとに戻る編集画面の読み込み（`db_value()`）がまだ `isAdmin() || 所属が空` で絞るので、saveonly の B12 は保存されたのに編集画面が開けない、という一時的な食い違いがある。M2 で `db_value()` を同じ判定にすると消える。PR-A は M1〜M3 をまとめて出すので、この食い違いは main には入らない。
- `use_udperms` の判定の書き方が 2 通りある（`checkPermissions()` は `!config('use_udperms')`、`restrictsByDocumentGroups()` は `== 1`）。0・1 以外の値（設定の画面では選べない）のときだけ結果が違う。実害は無いので残すリスクとして記録する。

計画時（2026-11-10、main ec7b45146 で調査）に分かったこと:

- 既定のロールは、インストール直後で 3 つ（`install/sql/default_settings.sql` を `default_settings_custom.sql` が上書き）: 1 Administrator（管理者）、2 ウェブマスター（すべての権限）、3 投稿担当者。古いサイトから上げたものには 2 Editor・3 Publisher が残ることがある。どれも `save_role` と `isAdmin()`（`access_permissions` と `web_access_permissions` の両方）が「両方真」か「両方偽」で、管理画面の見え方は変わらない。ただし「ロールの ID が 1」とは違い、ウェブマスター（2）も管理者の判定が真なので、ロールの ID で判定している DocumentParser の取得の関数と、TV の保存・QuickManager の TV ボタンでは、ウェブマスターの見え方が変わる（M3・M4）。
- TV の文書グループによるアクセスで、編集画面（`manager/actions/document/mutate_content/functions.php:31-41` の `getTmplvars()`）は `isAdmin()` で絞りを外し、保存（`manager/processors/document/save_resource.processor.php:276-295` の `get_tmplvars()`）は `1='mgrRole'`（ロールの ID が 1）で外す。QuickManager の TV ボタン（`assets/plugins/qm/qm.inc.php:270-300` の `checkTvAccess()`）もロールの ID が 1。M0 で確かめた: ウェブマスターは D2 に限った TV の欄が編集画面に出るが、保存すると文書は保存され、TV の値だけ入らない（C2）。
- QuickManager の編集ボタンの判定（`qm.inc.php:175-212` の `checkAccess()`）は、`privatemgr` も `use_udperms` も見ず、「文書にグループの行があれば、そのどれかに所属していること」を求める。グループの行があるが管理側で非公開でない文書（`privatemgr=0`）は、管理画面では開けるのに、所属しない利用者にはボタンが出ない。TV ボタンは、編集ボタンの判定が真のときだけ出る（`on_web_page_prerender.php:38, 213`）。
- `use_udperms`（文書グループの権限を使う）が 0 のとき、`checkPermissions()`・検索・各処理・#1318 の保存の確認は絞らないが、ツリー（`manager/frames/nodes.php:677-690`）・子の一覧（`resources_list.static.php`）・親の選択（`parent_picker.dynamic.php:277-291`）・文書の情報（`document_data.static.php:38-43`）・編集画面（`db_value()`）は `privatemgr` で絞る。`privatemgr=1` は `use_udperms=0` でも残りうる（グループ管理の画面 `access_permissions.dynamic.php` は無効のときも使え、`access_groups.processor.php:157` が `secureMgrDocument()` で付け直す。有効だった時期に付いた印もそのまま残る）。
- `tree_show_protected`（権限のないリソースの表示）は表示だけの設定。ツリーと子の一覧で、開けない文書を `protectedNode` の印つきで出す（`nodes.php:572-584`、`resources_list.static.php:395-397`）。開く・操作するのは各画面・処理の `checkPermissions()` が止める。子の一覧の「すべてを選択」は `has_access=0` を外す（81-90 行）。検索（`search.static.php:131`）も、有効なら開けない文書を印なしの普通のリンクで出す。親の選択は見ない。
- `checkPermissions()` の先頭の `save_role` は、文書グループだけでなく、ルート（親 0）と「ルートへの複製の禁止」（`udperms_allowroot=0`）の確認も外している。ルート直下に作れるかの `ManagerAPI::isAllowroot()`（`ex_managerapi.php:1139-1142`）も `save_role || udperms_allowroot`。
- ルートの扱いが入口で違う（修正前から。この計画では変えない。#1356）: 新規の作成（`save_resource.processor.php:481` と `manager/frames/tree.php:373` の `isAllowroot()`）は `use_udperms` を見ないので、既定（`use_udperms=0`・`udperms_allowroot=0`）では投稿担当者はルートに作れない。移動（`move_document.processor.php:75`）と親の選択のルートの項目（`parent_picker.dynamic.php:40` の `checkPermissions(0)`）は `use_udperms=0` なら許す。
- DocumentParser の取得の関数は、公開側（`privateweb`）と管理画面側（`privatemgr`）を `isFrontend()` で分け、ロールの ID が 1 のときの扱いが関数ごとに違う。
  - `getDocumentObject()`（`document.parser.class.inc.php:3786-3800`）: 両側で `1=<ロールの ID>` を OR に入れる（ID が 1 なら絞らない）。
  - `getDocuments()`（4212-4262）・キャッシュの読み込みの確認（1679-1700）: 両側で、ID が 1 なら絞らない。
  - `getAllChildren()`（trait 2175-2215、`$where` が無いとき）: 所属（公開側は Web の所属）があるときだけ `1='<ロールの ID>'` を入れる。M0 で確かめた: 所属の無い admin（ロール 1）は、管理画面側でも公開側でも絞られる（D6）。
  - `getPageInfo()`（4335-4370）・`getActiveChildren()`（trait 2230-2275）・`getDocumentChildren()`（trait 2278-2310）: 公開側は ID 1 でも絞る。管理画面側だけ ID 1 で外す。
  - `getDocumentChildren()` の管理画面側は、ID 1 で所属があると、条件が「所属のグループの文書だけ」になる（`$access` が空のまま ` dg.document_group IN (...)` を足すため。2298-2306 行）。管理者が所属を持つと子が減る不具合（修正前から。M0 の D7 で m1 だけになることを確かめた）。
- 公開側は、管理画面にログインしていても `ManagerAPI` を読み込まない（`document.parser.class.inc.php:185-187` は管理画面だけ。読み込むのは `token_auth()` の 1242 行と QuickManager の 41 行）。公開側の処理から `manager()` を呼ぶと null になる。
- 公開側のページキャッシュ: 既定の `disable_cache_at_login=1`（`document.parser.class.inc.php:1380-1383`）で、管理画面にログイン中の公開側はキャッシュを使わない。0 にしているサイトでは、ロールの ID 1 の利用者が見たときに `getDocuments()` などが絞らずに返した文書が、キャッシュする部品の出力ごとページキャッシュに入りうる（修正前から）。M4 で判定を `bypassesDocumentGroups()` にすると、ウェブマスターと独自の管理者のロールにも広がる。
- 文書グループで絞っていない一覧が残る（修正前から。この計画の範囲外。#1354）: 予約の一覧（`manager/actions/report/site_schedule.static.php:31-36, 75-80, 120-125`）、要素の画面の文書の選択（`manager/actions/element/resource_selector.static.php:87-92`）、テンプレートの画面の「このテンプレートを使う文書」（`manager/actions/element/ajax/mutate_templates.php:14-18`）。
- 同梱のスニペット Ditto（`assets/snippets/ditto/classes/ditto.class.inc.php:962, 1085, 1105`）と Wayfinder（`assets/snippets/wayfinder/wayfinder.inc.php:482`）も、ロールの ID が 1 で `privatemgr` の絞りを外す独自の条件を持つ（この計画の範囲外。#1355）。
- ロールの ID が 1 で決めている、文書グループと関係ない箇所: モジュールのメニュー（`manager/frames/menu.php:294`）、ユーザーの一覧（`user_management.static.php:162`）、`config_check.inc.php:373`。この計画では触れない。
- `isAdmin()` は文書グループ以外にも使われている（`git grep -n "isAdmin()"`。バックアップの復元 `restore.processor.php:6`・`bkmanager.static.php:10`、ファイルブラウザーの守る場所 `manager/media/browser/evo/lib/PathGuard.php:28`、モジュールの実行 `execute_module.processor.php:23`・`mutate_module_resources.dynamic.php:120`、ユーザーの削除・保存 `delete_user.processor.php:22`・`save_user.functions.php:278`・`save_user.processor.php:117`、ようこその画面 `welcome.static.php:148, 179`、`config_check.inc.php:34`、文書グループの付け方 `ex_managerapi.php:1188` の `documentGroupAccess()` と編集画面の `access_permissions_docs_collision`（`mutate_content.dynamic.php:109`・`mutate_draft.dynamic.php:112`））。そのため `isAdmin()` の意味は変えず、文書グループの絞り込みの判定を別のメソッドにする（Decision Log）。
- CLI（`manager/includes/cli/commands/`）に文書グループを判定する処理は無い。
- `nodes.php:492` は、ツリーのノード名を日付にする設定のとき `getDocumentObject()` を呼ぶ。`tree_show_protected=1` で開けない文書は false が返る（`'- - -'` か警告。要確認）。M0 の記録はノード名の設定を既定（ページタイトル）のままにしたので、この経路を通っていない。M4 の確認で 1 回見る（Concrete Steps の 25）。
- `privatemgr` の列で絞る条件（`privatemgr=0 OR 所属のグループの文書`）と、#1318 の集合で求める条件（管理側で非公開のグループ＝`membergroup_access` に行があるもの、が無いか所属と重なる）は、`privatemgr` が実際の所属と合っていれば同じ結果になる（`mgrDocgroups` は `membergroup_access` から求めるので、所属のグループはすべて管理側で非公開）。印が合わない経路は #1353（範囲外）。

M0（2026-11-10、基準 f6e94f10d。コードは origin/main 899a6ddea と同じ）で分かったこと:

- 検証環境の `setup-site.js` が作る `admin` はロール 2 だった。記録では `admin` をロール 1 に変え、ウェブマスター（ロール 2）の `webmaster` を別に作った。
- A2: editor（V1）の検索（S）と親の選択（P）には c2 が出る（c2 は `privatemgr=0`）。ツリー（T）と子の一覧（L）では、親の f2 が出ないので c2 も出ない（T で `parent=f2` を直接開くと出る）。
- A5: editor（V2）のツリーで、m2・f2 は `protectedNode` で出るが、u2 は印なしで出る。子の一覧では u2 も `protectedNode` つき。`nodes.php` の `getClassName()` が、未公開・削除の文書に `protectedNode` を付けないため（`has_access` は 0）。M2 で `has_access` を置き換えたあとも、この違いは今のまま残る。
- C5・C6: 計画の w（Web 側で非公開）は `privateweb=1` なので、所属しない利用者には未認可のページ（Home、id 1）になり、編集ボタンを確かめられない。グループの行を持つが管理側・Web 側とも非公開でない文書 n（id 25、どのユーザーグループにも結びつけない文書グループ DN）を足した。main では editor0・editor の V1・V3 で n の編集ボタンは出ず、webmaster・adminnosave は出る。
- D6: admin（ロール 1、所属なし）の管理画面側の `getAllChildren(T)` は pub・w・n だけ（所属のある admin+G1 は全部）。ほかの関数は全部。
- D8 の管理画面側: webmaster の管理画面側の取得の関数は pub・w・n だけ（ロール 1 でないので `privatemgr=0` に絞る）。
- B3: saveonly の m2・f2・u2 の文書の情報・編集画面は、入口は通り、読み込みで絞られて `error_no_privileges`（エラー 3）になる。
- saveonly0（`save_role`、所属なし）は、V1 で m1・m2・f2・u2 の文書の情報・編集画面を開ける（`db_value()` が所属の空で絞らない）が、ツリー・子の一覧・検索・親の選択には出ない。QuickManager の編集ボタンも出ない（u2 は本文が出るのにボタンなし）。
- QuickManager の TV ボタンのクラスは `qm-tv`（計画の `qm_tv` は誤り）。QuickManager はプラグイン id 3 で有効だった。プロパティ `tvbuttons` を true にした。
- QuickManager の TV の保存の入口（`index.php?id=X&quickmanagertv=1&tvname=u1338tv`、記録の QT）は、main では admin（ロール 1）だけが FORM。ほかは `Access denied`（w は 403 の未認可のページの上で動く）。
- 移動の処理（`a=52`）は GET だと 405。記録は、画面の `meta[name="csrf-token"]` を付けた POST（`id`・`new_parent`・`csrf_token`）で行った。
- 編集画面（`a=27`）を開くと `active_users` にロックが残り、次の利用者の記録が変わる。記録の前と間に `delete from modx_active_users where action=27` を入れた。
- 公開側: 未認可（w）は 403 で Home（id 1）、未公開（u2）は 404 で id 4 が出る。公開側の `getAllChildren`・`getPageInfo`・`getActiveChildren`・`getDocumentChildren` はロール 1 でも w を含まない。`getDocumentObject` と `getDocuments` だけが admin に w を含める。
- V4（`use_udperms=0` かつ `tree_show_protected=1`）を足した。editor0 のツリーと子の一覧で、m1・m2・f2 が `protectedNode` つきで出る（`use_udperms=0` なので開けない文書は無く、印は見かけだけ）。
- PHP の警告（`docker logs` の `PHP Warning|Notice|Deprecated|Fatal`）・JS のエラーとも 0 件。

## Decision Log

- 2026-11-10 / ユーザーさんと相談（Issue の「着手時の決定」のコメント）: 絞り込みを外す「管理者」は、閲覧も操作も `isAdmin()`。`checkPermissions()` と各処理の `save_role`、DocumentParser の取得の「ロールの ID が 1」も同じ判定。管理側のグループに 1 つも所属しない利用者は、どこでも公開の文書（`privatemgr=0`）だけ。判定は `ManagerAPI` の 1 か所。`privatemgr` の付け方の食い違いは #1353 に分ける。→ 管理者の条件は、次の見直しで変えた。
- 2026-11-10 / ユーザーさんと相談（Issue の最後のコメント「決定の見直し」）: 絞り込みを外す「管理者」を、`isAdmin()` または `save_role` にする。
  理由: `save_role` を持つ利用者は、自分のロールに `access_permissions`・`web_access_permissions` を付けて `isAdmin()` になれる（ロールの保存の処理に、自分のロールを除く制限は無い）ので、`save_role` だけのロールを絞っても守りにならない。「アクセスの規則を自分で変えられる人は管理者」の 1 つの考え方で説明でき、今より見えなくなる利用者がいない（例外は Validation の「見えなくなるもの」）。
  変えないこと: 判定を `ManagerAPI` の 1 か所に置くこと、所属が空なら公開の文書だけ、公開側（M4）も含めること。
  不採用: `isAdmin()` だけ（前の決定）。`save_role` だけのロールが、今は開けて操作できる文書を扱えなくなる（M0 の B2・B4・B5・D4）のに、自分のロールを変えれば戻せるので、利用者の手間が増えるだけで守りにならない。
- 2026-11-10 / Claude（planner）: 見直しの判定は、`isAdmin()` とは別のメソッド `bypassesDocumentGroups(): bool`（`$this->isAdmin() || evo()->hasPermission('save_role')`）にする。`isAdmin()` の意味は変えない。
  理由: `isAdmin()` は、バックアップの復元・ファイルブラウザーの守る場所・モジュールの実行・ユーザーの削除と保存などにも使われている（Surprises）。そこに `save_role` を足すと、文書グループと関係ない権限が広がる。文書グループの判定だけを新しい名前に寄せれば、影響が文書グループの中に閉じる。
  文書グループの付け方（`documentGroupAccess()` の `!$this->isAdmin() && !$yes && $no` と、編集画面の `access_permissions_docs_collision` の表示）は `isAdmin()` のまま変えない。どのグループを付け外しできるかの判定で、`access_permissions`・`web_access_permissions` そのものを見ており、#1300・#1307・#1318 の範囲。
- 2026-11-10 / Claude（planner）: `ManagerAPI` に置く判定は次の 6 つ。定義は 1 つ（「管理者」＝`bypassesDocumentGroups()`、「絞る」＝`use_udperms` が 1 で管理者でない、「届く」＝`privatemgr=0` か所属のグループに入っている）で、使う場面に合わせて形だけを変える。
  1. `bypassesDocumentGroups(): bool`: `isAdmin() || hasPermission('save_role')`。docblock に「文書グループの絞り込みを受けない管理者の定義（#1338）。アクセスの規則（ユーザーグループの権限・ロール）を自分で変えられる人」と書く。`use_udperms` は見ない（公開側の判定にも使うため）。
  2. `userMgrDocumentGroupIds(): int[]`: ログイン中の利用者の所属（セッション `mgrDocgroups`）を、正の整数の配列で返す。各所の `array_filter(array_map('intval', …))` と `implode(',', sessionv('mgrDocgroups'))` をこれにまとめる。
  3. `restrictsByDocumentGroups(): bool`: `use_udperms == 1 && !bypassesDocumentGroups()`。管理画面の判定はすべてこれを通す。
  4. `documentAccessCondition(string $alias = 'sc'): string`: 絞らないときは空文字。絞るときは SQL の真偽の式（前に `AND` は付けない）。所属が空なら `{a}privatemgr=0`、あれば `({a}privatemgr=0 OR {a}id IN (SELECT document FROM <prefix>document_groups WHERE document_group IN (<所属>)))`。`{a}` は `$alias.`（空なら付けない）。結合（`LEFT JOIN document_groups`）に頼らない副問い合わせの形にし、どの問い合わせにも同じ式を入れられるようにする（ツリーと親の選択が今使っている形）。`$alias` は呼ぶ側の固定の文字列だけを受け、`/^[A-Za-z_][A-Za-z0-9_]*$/` か空でなければ `InvalidArgumentException`。
  5. `canAccessDocument($docId): bool`: 絞らないなら true。絞るなら、その id の文書が 4 の式を満たすか（存在しない文書は false。今の `checkPermissions()` と同じ）。
  6. `canOpenWithDocumentGroups($groupIds)`（#1318）: 「絞らない、または集合が届く」にする（2 つの確認を組み合わせた式をやめる。条件が 1 つになったので組み合わせが要らない）。
  `isAdmin()` は変えず、docblock に「文書グループの絞り込みの判定には使わない。`bypassesDocumentGroups()` を使う」と 1 行足す。
  理由: 一覧（SQL の式）・1 文書の可否（真偽）・保存前の集合（#1318）の 3 つの場面があり、それぞれに同じ定義から作った形を渡せば、呼ぶ側に条件を書かずに済む。副問い合わせの形なら、結合の形が違う問い合わせ（ツリー・一覧・検索・DocumentParser）に同じ式を入れられる。
  不採用: (a) `bypassesDocumentGroups()` を各所で直接呼ぶ形は、`use_udperms` と所属が空のときの扱いをまた各所に書くことになる（管理画面の呼ぶ側は 3〜6 だけを使う。1 を直接使うのは `isAllowroot()`・`checkPermissions()` の先頭・公開側の判定）。(b) 判定を DocumentParser の trait に置く案は、#1318 と決定に合わせて `ManagerAPI` に置く（`checkPermissions()` は公開の API として残し、中身を `ManagerAPI` に任せる）。(c) 文書グループ専用の新しいクラスを作る案は、既存の `ManagerAPI` に #1318 の判定がすでにあり、この規模で新しい置き場所は作らない。
- 2026-11-10 / Claude（planner）: `use_udperms` が 0 のときは、管理画面のどこでも文書グループで絞らない（`restrictsByDocumentGroups()` が false）。TV のアクセスも同じ。
  理由: `checkPermissions()`・各処理・検索・#1318 はすでにそうで、絞っているのはツリーなどの表示だけ。表示で隠しても、id を指定すれば開けて操作できる。設定の名前と説明（「文書グループの権限を使う」）とも合う。
  影響: `use_udperms=0` で `privatemgr=1` の印が残っているサイトでは、投稿担当者などのツリー・一覧・親の選択に、今は出ていない文書が出る（開くことは今もできる）。リリースノートに書く。
  公開側（`privateweb`）は `use_udperms` を見ないまま変えない（Web 側の会員制の動きで、この Issue の範囲外）。
- 2026-11-10 / Claude（planner）: `tree_show_protected` は「ツリーと子の一覧に、開けない文書を印つきで出す」表示だけの設定として残す。絞り込みを外す条件には入れない（今も操作は外していない）。ツリーと子の一覧は、有効なら WHERE に式を入れず、`has_access` を `MAX(IF(<式>,1,0))` で求める。検索と親の選択は、この設定を見ずに常に絞る。
  理由: 印をつけて出せるのはツリーと子の一覧だけで、検索は開けない文書を普通のリンクで出し、押すとエラーになる。設定の説明も「リソースツリーに表示」。
  代わりの案: 検索も設定に従い、`has_access` を求めて印をつけ、リンクを外す。検索の画面の作りを変えることになり、この Issue の目的（判定をそろえる）から外れるので採らない。
  影響: `tree_show_protected=1` で管理者でない利用者の検索の結果から、開けない文書が消える（A6）。リリースノートに書く。
- 2026-11-10 / Claude（planner）: ルートの許可（`checkPermissions()` の親 0・ルートへの複製の確認と、`isAllowroot()`）を外す条件も、`save_role` から `bypassesDocumentGroups()` にする。`checkPermissions()` の確認の順番（管理者 → 親 0 で `udperms_allowroot` → `use_udperms=0` → id 無し → ルートへの複製 → 文書グループ）は変えない。
  理由: `udperms_allowroot` は文書グループの設定の 1 つ（`settings_form.definition.inc.php:424-428` で `use_udperms=1` のときだけ出す）で、今は `checkPermissions()` の同じ `save_role` で外れている。見直しで `save_role` を含む判定になったので、`save_role` のロールの動きは変わらず、`isAdmin()` だけのロールが加わる。既定のロールは結果が変わらない。
  ルートの入口ごとの `use_udperms` の扱いの違い（Surprises）は直さない（既定のロールの動きが変わるため、#1356）。
- 2026-11-10 / Claude（planner）: 公開側でも `manager()` が `ManagerAPI` を返すように、`manager/includes/helpers.php` の `manager()` を「未読み込みなら `evo()->loadExtension('ManagerAPI')` してから返す」にする。DocumentParser の公開側の判定は、`$this->isLoggedIn('mgr') && manager()->bypassesDocumentGroups()` の形で呼び、管理画面にログインしていない閲覧では `ManagerAPI` を読み込まない。
  理由: `checkPermissions()` は公開側（未公開の文書の表示、`document.parser.class.inc.php:637`）からも呼ばれ、QuickManager も公開側で動く。`ManagerAPI` の読み込みは今も `token_auth()` と QuickManager が公開側で行っている。訪問者の毎回の表示で読み込まないよう、ログインの確認を先に置く（権限は `mgrPermissions` が無ければ偽なので、結果は同じ）。公開側の権限は、最後に管理画面を開いたときのセッションの値（`isAdmin()` の今の使い方と同じ）。
- 2026-11-10 / Claude（planner）: TV の文書グループによるアクセス（`site_tmplvar_access`）の「絞らない」も `restrictsByDocumentGroups()` の否定にそろえる（編集画面 `getTmplvars()`・保存 `get_tmplvars()`・QuickManager の `checkTvAccess()`）。TV の絞り方そのもの（TV に行が無いか、所属のグループの行がある）は変えない。
  理由: 編集画面と保存で条件が違い、ウェブマスターの変更が保存されない食い違い（Surprises。M0 の C2 で確定）は、この Issue と同じ「管理者の条件が場所で違う」問題。範囲に入れないと M4 の後もここだけロールの ID が残る。
- 2026-11-10 / Claude（planner）: QuickManager の編集ボタンの判定 `checkAccess()` は `manager()->canAccessDocument(docid)` にする（管理者・`use_udperms=0`・グループの行はあるが管理側で非公開でない文書でも、管理画面で開ける文書ならボタンを出す）。
- 2026-11-10 / Claude（planner）: DocumentParser の取得の関数（M4）は、管理画面側の分岐（`isFrontend()` が偽）を `manager()->documentAccessCondition()` にし、公開側の分岐は「ロールの ID が 1」を今ある場所だけ `managerBypassesDocumentGroups()`（DocumentParser に足す公開側の入口。Concrete Steps の 22）に置き換える。公開側で ID 1 を見ていない関数（`getPageInfo()`・`getActiveChildren()`・`getDocumentChildren()` の公開側）に新しく外す条件は足さない。`getAllChildren()` の「所属があるときだけ外す」は、所属が無くても外す形にする（同じ判定にするため。admin の管理画面側・公開側の `getAllChildren()` も変わる。D6・D8）。
  理由: 公開側の関数ごとの違いをそろえる向き（外す側にそろえるか、外さない側にそろえるか）は、ページキャッシュに入りうるかという別の判断が要る。決定の範囲（ロールの ID 1 を同じ判定にする）にとどめ、そろえる向きは別の課題にする（`assets/docs/core-issues.md` の「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」）。
  M4 はこの Issue に入れると決めた（未決事項の 1）。
- 2026-11-10 / Claude（planner）: 表示の問い合わせの FROM・JOIN・GROUP BY は変えず、条件の式だけを入れ替える。`has_access` は今と同じく `MAX(IF(…,1,0))` で集計の形のままにする。
  理由: 行の重なりや並び順を変えずに、M0 の記録との比較を完全一致で行えるようにする。結合が使われなくなっても、この Issue では外さない。
- 2026-11-10 / Claude（planner）: ロールの ID が 1 でも、そのロールから `save_role`・`access_permissions`・`web_access_permissions` を外したサイトでは、TV の保存・QuickManager の TV ボタン・DocumentParser の取得の関数で、ID 1 の利用者の絞り込みが外れなくなる。既定のロール 1 はすべての権限を持つので、既定では起きない。権限で決める方針（ロールの ID で決めない）を優先し、救う分岐は置かない。リリースノートに書く。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **文書グループ（ドキュメントグループ）**: 文書をまとめる名前つきの集まり（表 `documentgroup_names`、文書との対応は `document_groups`）。グループ管理の画面（`a=40`）で、管理側のユーザーグループ（`membergroup_names`。利用者の所属は `member_groups`）に結びつける（`membergroup_access`）と、そのグループの文書は「管理側で非公開」になる。Web 側のユーザーグループに結びつける（`webgroup_access`）と「Web 側で非公開」になる。どちらにも結びつけないグループに入れても非公開にはならない（M0 の文書 n）。
- **`privatemgr` / `privateweb`**: `site_content` の列。文書が管理側（Web 側）で非公開のグループに 1 つでも入っていると 1。`ManagerAPI::setMgrDocsAsPrivate()`・`setWebDocsAsPrivate()`（`manager/includes/extenders/ex_managerapi.php:749-821`）が付ける。
- **所属（`mgrDocgroups`）**: ログイン中の管理ユーザーが、自分のユーザーグループを通じてアクセスできる文書グループの id の一覧。`ManagerAPI::getMgrDocgroups()` が `membergroup_access` から求め、管理画面のリクエストのたびに `manager/includes/accesscontrol.inc.php:106` がセッションに入れ直す。ここに入るのは管理側で非公開のグループだけ。
- **`use_udperms`**: グローバル設定「文書グループの権限を使う」（既定 0）。**`udperms_allowroot`**: 「ルートの使用許可」（既定 0、`use_udperms=1` のときだけ画面に出る）。**`tree_show_protected`**: 「権限のないリソースの表示」（既定 0）。
- **`isAdmin()`**: `ManagerAPI::isAdmin()`（`ex_managerapi.php:646-657`）。ロールが `access_permissions`（ユーザーグループの権限の管理）と `web_access_permissions`（Web ユーザーグループの権限の管理）の両方を持つ。権限は `evo()->hasPermission()` がセッション `mgrPermissions` から読む（管理画面のリクエストごとに `refreshMgrPermissions()` で読み直す）。文書グループ以外の用途もある（Surprises）。
- **管理者（この計画での意味）**: 文書グループの絞り込みを受けない利用者。`isAdmin()` が真か、ロールが `save_role`（ロールの保存）を持つ。M1 で `ManagerAPI::bypassesDocumentGroups()` として置く。
- **`checkPermissions($docid, $duplicateDoc)`**: `manager/includes/traits/document.parser.subparser.trait.php:1112-1169`。文書（または親 0）を管理画面で扱ってよいか。各処理・画面の入口の確認で、外部のプラグインからも呼ばれる公開の API。
- **既定のロール**: Surprises の 1 つ目。検証では、権限を組み替えた独自のロールも作る（Validation）。

今の判定（main ec7b45146。f6e94f10d でも、`tree.php` の行が 372 → 373 になったほかは同じ）。「外す」は絞り込みをしない条件、「空」は所属が空のとき。

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
| p ルートに作れるか | `ex_managerapi.php:1139-1142`、`tree.php:373` | `save_role` | | 見ない |

変更後は、a・c・e〜n の「外す」がすべて `bypassesDocumentGroups()`（管理画面の判定は `restrictsByDocumentGroups()` を通すので、`use_udperms=0` でも外す）、p は `bypassesDocumentGroups() || udperms_allowroot`、o は管理画面側が `documentAccessCondition()`、公開側が `managerBypassesDocumentGroups()` になる。

DocumentParser の処理の流れ（`assets/docs/architecture.md`）への影響: `prepareResponse()`（未公開の文書の表示の確認 637 行、キャッシュの読み込みの確認 1679-1700 行、`getDocumentObject()`）が M1・M4 で変わる。`executeParser()`・`parseDocumentSource()`・`postProcess()` は変えない（部品から呼ばれる `getDocuments()` などの結果は M4 で変わる）。キャッシュ: サイトのキャッシュ・ページキャッシュの作り方と消す条件は変えない。ページキャッシュに入りうる結果の注意は Surprises。

## Plan of Work

判定を先に 1 か所へ置き（M1）、呼ぶ側を画面の群ごとに移す（M2〜M4）。M1 で、`checkPermissions()` を通るすべての処理と画面の入口が新しい条件になる（`save_role` のロールは今と同じ、`isAdmin()` だけのロールが加わる）。M2 以降は、表示の条件を同じ式に置き換え、`use_udperms=0` の扱いをそろえる。編集画面の 2 つの確認（b と c）の食い違いは M2 で消える。

`ManagerAPI` に足すもの（Decision Log の 6 つ）の形:

    public function bypassesDocumentGroups(): bool
        $this->isAdmin() || evo()->hasPermission('save_role')
    public function userMgrDocumentGroupIds(): array
        sessionv('mgrDocgroups', []) を (array) にし、intval、0 以下を除き、重複を除いて返す
    public function restrictsByDocumentGroups(): bool
        evo()->config('use_udperms') == 1 && !$this->bypassesDocumentGroups()
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
    public function isAllowroot()
        $this->bypassesDocumentGroups() || (bool) evo()->config('udperms_allowroot')

`checkPermissions()` は、先頭の `evo()->hasPermission('save_role')`（1124 行）を `manager()->bypassesDocumentGroups()` にし、最後の問い合わせ（1149-1168 行）を `manager()->canAccessDocument($docid)` にする。ほかの分岐と順番は変えない（`use_udperms=0` の分岐は残す。`canAccessDocument()` も同じ結果になるが、ルートへの複製の確認より前で返す今の順番を保つ）。

画面の置き換えの形（M2）:

    $cond = manager()->documentAccessCondition('sc');
    WHERE: $cond === '' なら足さない。足すときは "AND {$cond}"（配列の WHERE は今の書き方に合わせる）
    ツリーと子の一覧は、さらに config('tree_show_protected') が真なら WHERE に足さない
    has_access: $cond === '' ? '1 AS has_access' : "MAX(IF({$cond}, 1, 0)) AS has_access"

`isAdmin()` で分けていた `has_access` の分岐（`nodes.php:102-106`、`resources_list.static.php:65-71, 131-133, 174-176`）は `$cond === ''` で分ける（`use_udperms=0` でも 1 になる）。

TV と QuickManager（M3）は `restrictsByDocumentGroups()` の否定で外し、所属は `userMgrDocumentGroupIds()` から読む。DocumentParser（M4）は、管理画面側が `documentAccessCondition()`、公開側が `managerBypassesDocumentGroups()`（`isLoggedIn('mgr') && manager()->bypassesDocumentGroups()`）。

想定コミット（マイルストーンごとに 1 つ）と PR:

- M1 `fix(manager): 文書グループの絞り込みを受けない管理者の判定を ManagerAPI に置く`
- M2 `fix(manager): ツリー・一覧・検索・親の選択・文書の情報・編集画面を文書グループの同じ判定で絞る`
- M3 `fix(manager): TV と QuickManager の文書グループの判定を管理画面とそろえる`
- M4 `fix(core): 文書の取得の関数の管理者の判定を ManagerAPI にそろえる`
- M5 `docs: 文書グループの判定の置き場所を書く`

PR は 2 つに分ける。PR-A（M1〜M3 と、それに関わる M5 のドキュメント。`Refs #1338`）は管理画面だけで閉じる。PR-B（M4。`Closes #1338`）は公開側の見え方が変わるので、PR-A を base にして積む（M4 をこの Issue に入れることは未決事項の 1 で決めた）。M0 の記録は両方の確認に使う。

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。検証環境は `adaptive-validation-runner` スキルの手順で立てる（`setup-site.js --sample --editor`、`smoke.js` が全 PASS）。以下の `<dc>` は `docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`（名前やアドレスを変えて起動したときは `references/environment.md` のとおり環境変数を先頭に付ける）。管理画面へ curl するときは `Accept-Language` を必ず付ける。記録のスクリプトと出力は、リポジトリの外の作業用の一時領域 `<tmp>`（M0 では `/home/yamamoto/.claude/jobs/62dfc916/tmp/v1338m0/`。Artifacts and Notes）に置く。

M0 着手前の確認と修正前の記録（2026-11-10 完了。次に取り直すときの手順として残す）

1. 基準のコミットは f6e94f10d（ブランチ `fix/1338-m1-admin-side`。コードは origin/main 899a6ddea と同じ。ec7b45146 からの差は `tree.php` の表示の変更だけで、Context の行番号は `tree.php:372` → 373 のほか動いていない）。
2. 検証環境を立てる: `<tmp>/up.sh`（`<tmp>/env.sh` が `EVO_VALIDATE_NAME=evo-validate-1338`・`EVO_BIND_IP=127.0.0.38`・`EVO_MAILPIT_PORT=8288` と Playwright の `NODE_PATH` を付けて、`<dc> up -d --build`・`setup-site.js --sample --editor`・`smoke.js` を流す）。DB へは `<tmp>/q.sh`（`docker exec -i evo-validate-1338-db mariadb …`、SQL は標準入力）。
3. 検証のデータ: `<tmp>/1338-fixture.sql` のあとに `<tmp>/1338-fixture-n.sql` を流す。作った id は `<tmp>/1338-ids.txt`（Artifacts and Notes に写した）。テンプレートは既定（id 2）、パスワードは admin と同じ。
   - 管理側のユーザーグループ G1・G2、Web のグループ WG。文書グループ D1（G1）・D2（G2）・DW（WG だけ）・DN（どこにも結びつけない。`1338-fixture-n.sql`）。
   - 公開の親フォルダ T（グループなし、`u1338-T`）と、その子: `u1338-pub`（なし）、`u1338-m1`（D1）、`u1338-m2`（D2）、`u1338-w`（DW）、フォルダ `u1338-f2`（D2）とその子 `u1338-c2`（なし）、未公開の `u1338-u2`（D2）、`u1338-n`（DN）。本文はすべて `<p>u1338 body</p><!-- qm-tv u1338tv -->`。`SELECT id,pagetitle,privatemgr,privateweb FROM modx_site_content WHERE pagetitle LIKE 'u1338-%'` で、m1・m2・f2・u2 が `privatemgr=1`、w が `privatemgr=0,privateweb=1`、ほか（n を含む）は 0。
   - TV `u1338tv`（テキスト）を既定のテンプレートに付け、`site_tmplvar_access` で D2 にだけ結びつける。
   - ロール（投稿担当者 ID 3 の行を複製し、3 つの権限だけを変える）: R_S（`save_role=1`）、R_P（`access_permissions=1`・`web_access_permissions=1`、`save_role=0`）、R_A（`access_permissions=1` だけ）、R_W（`web_access_permissions=1` だけ）。
   - 利用者: `admin`（`setup-site.js` はロール 2 で作るので、ロール 1 に変える）、`webmaster`（ロール 2、所属なし。新しく作る）、`editor`（ロール 3、G1）、`editor0`（ロール 3、所属なし）、`saveonly`（R_S、G1）、`saveonly0`（R_S、所属なし）、`adminnosave`（R_P、所属なし）、`acc`（R_A、所属なし）、`web`（R_W、所属なし）。記録の M では、admin を一時的に G1 に入れた `admin+G1` も取る（取ったあと戻す）。
   - 取得の関数の確かめ用: モジュール `u1338probe`（`a=112&id=<mod>`）と、公開側の文書 `u1338-probe`（キャッシュしない、テンプレートなし、本文は `[!u1338probe!]`）。T の子の id ごとに `getDocumentObject('id',…)` が返るか、`getPageInfo(…,0)`、`getActiveChildren(T)`・`getDocumentChildren(T,1)`・`getDocumentChildren(T,0)`・`getAllChildren(T)`・`getDocuments(<T の子の id>, null)` の id の一覧を出す。
   - QuickManager（プラグイン id 3、有効）のプロパティ `tvbuttons` を true にする（既定 false、`qm.inc.php:33, 51`）。TV ボタンのクラスは `qm-tv`。
4. 記録: `<tmp>/run-all.sh <タグ>`。中で、`delete from modx_active_users where action=27; delete from modx_site_tmplvar_contentvalues where tmplvarid=<tv>;` を流してから、`<tmp>/1338-m0.js`（Playwright。`lib.js` の `login()` を使う）を V1〜V3 は 9 利用者、V4 は admin・editor・editor0 で動かし、`<tmp>/1338-<タグ>.tsv`・`<tmp>/1338-<タグ>-v4.tsv` と、`docker logs evo-validate-1338-app` の PHP の警告の件数 `<tmp>/logs-after-<タグ>-count.txt` を書く。設定の組は `REPLACE INTO modx_system_settings` で切り替え、キャッシュを消す: V1（`use_udperms=1`、`tree_show_protected=0`、`udperms_allowroot=0`）・V2（V1 で `tree_show_protected=1`）・V3（`use_udperms=0`、印はそのまま）・V4（`use_udperms=0`、`tree_show_protected=1`）。利用者ごとにログインし直し、編集画面を開くたびに `active_users` のロックを消す。1 行は「設定・利用者・場所・対象・結果」の TSV。
   - T（ツリー）: `index.php?a=1&f=nodes&indent=1&parent=<T|f2|0>&expandAll=1` のノードの id と `protectedNode` の有無。
   - L（子の一覧）: `a=120&id=<T>` の行の id と `protectedNode`、「すべてを選択」の id の一覧。
   - S（検索）: `a=71&submitok=1&pagetitle=u1338` の結果の id。
   - P（親の選択）: `a=135&ajax=search&q=u1338` の JSON の id、`a=135` の画面にルートの項目があるか。
   - I（文書の情報）・E（編集画面）: `a=3&id=X`・`a=27&id=X` が開けるか（`OPEN`／`FORM id=X`、`denied:<言語キー>`、`denied:error3`）。対象は T・T の子すべて・c2・n。
   - X（操作の確認）: `a=52` に POST（`id`・`new_parent`・`csrf_token`。GET は 405）。同じ親への移動は、確認を通ると「同じ親」の知らせで止まる。あわせて pub のルートへの移動（通ると `MOVED`。記録の中で T に戻す）。
   - R（ルート）: ツリーの「ルートに新規作成」のボタン（`tree.php:373`）があるか。
   - V（TV）: `a=27&id=<pub>` に TV の欄があるか。admin・webmaster・editor では、欄（あれば）に値を入れて保存し、文書と `site_tmplvar_contentvalues` に入ったかを記録してから値を消す。
   - Q（QuickManager）: 公開側の T の子のページで、編集ボタン（`li.qmEdit`）の数と行き先、TV ボタン（`span.qm-tv`）の数と行き先、本文が出たか。
   - QT（QuickManager の TV の保存の入口）: `index.php?id=X&quickmanagertv=1&tvname=u1338tv` の応答（FORM か `Access denied` か）。
   - F（公開側）: `index.php?id=<w|u2|n>` を管理画面にログインしたまま開いたときの応答（ステータス・本文・タイトル）。`anon`（ログインなし）も取る。
   - M（取得の関数）: `M-mgr`（モジュール）と `M-front`（`u1338-probe` の文書）。
   - 結果の読み方: `python3 -I <tmp>/pivot.py <tsv> [場所,…] [設定,…]`（同じ結果の利用者をまとめて出す）、`python3 -I <tmp>/cmpset.py <tsv> V1 V2 [場所]`（同じ記録の中で設定の組を比べる）。マイルストーンどうしの比較は `sort` した 2 つの TSV の `diff` で行う（`cmprun.py` は n の行を除いて比べる M0 の 1 回目用で、C5・C6 を見落とすので使わない）。
   期待（2026-11-10 に確かめた）: Validation and Acceptance の表の「main」の列と一致する。PHP の警告・JS のエラーは 0 件。

M1 判定を `ManagerAPI` に置く

5. `manager/includes/helpers.php` の `manager()` を、`evo()->manager` が無ければ `evo()->loadExtension('ManagerAPI')` してから返す形にする（コメントに「公開側でも、管理画面にログインした利用者の判定（文書グループ・QuickManager）に使う」と書く）。
6. `manager/includes/extenders/ex_managerapi.php`:
   - `isAdmin()` の docblock に、文書グループの絞り込みの判定には使わず `bypassesDocumentGroups()` を使うこと（#1338）を書く。本文は変えない。
   - Plan of Work の `bypassesDocumentGroups()`・`userMgrDocumentGroupIds()`・`restrictsByDocumentGroups()`・`documentAccessCondition()`・`canAccessDocument()` を、`canOpenWithDocumentGroups()` の前に足す。`bypassesDocumentGroups()` の docblock に定義と理由（アクセスの規則を自分で変えられる人。`save_role` があれば自分のロールに 2 つの権限を付けられる）と、`use_udperms` を見ないこと（管理画面は `restrictsByDocumentGroups()` を通す）を書く。`documentAccessCondition()` の docblock に、`privatemgr` の列の式と #1318 の集合の式が同じ定義であること（Surprises の最後）、印の食い違いは #1353 であることを書く。
   - `canOpenWithDocumentGroups()` の本文と docblock を Plan of Work の形にする（「2 つの確認をそのまま式にした」「#1338 で 1 か所にまとめる」の記述を消す）。
   - `isAllowroot()` を `$this->bypassesDocumentGroups() || (bool) evo()->config('udperms_allowroot')` にし、コメントに理由（文書グループの設定の 1 つで、同じ管理者の判定を使う）を書く。
7. `manager/includes/traits/document.parser.subparser.trait.php` の `checkPermissions()`: 1124 行の `evo()->hasPermission('save_role')` を `manager()->bypassesDocumentGroups()` にし、1149-1168 行（所属から条件を組んで数える部分）を `return manager()->canAccessDocument($docid);` にする。親の問い合わせとルートへの複製の確認（1142-1147）は残す。関数の先頭に、文書グループの判定の正は `ManagerAPI` であることをコメントで書く。
8. 構文の確認:
       <dc> exec app php -l manager/includes/extenders/ex_managerapi.php
       docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l manager/includes/extenders/ex_managerapi.php
   変えたファイルごとに行う。期待: `No syntax errors detected`。
9. `<tmp>/run-all.sh m1` で取り直し、`1338-m0.tsv` と `1338-m1.tsv`（V4 は `-v4`）を `sort` して `diff` で比べる。期待: 差は、Validation の表で「M1」と書いた行だけ。`git grep -n "hasPermission('save_role')" -- manager/includes/traits manager/includes/extenders` が `bypassesDocumentGroups()` の 1 行だけ。

M2 管理画面の一覧と読み込み

10. `manager/frames/nodes.php`: `getNodes()` の 92-106 行の `$mgr_docgroups`・`$in_docgrp`・`isAdmin()` の分岐を、Plan of Work の `$cond` と `has_access` にする。`tree_access_condition()` を「`tree_show_protected` が真か `$cond` が空なら ''、それ以外は `' AND ' . $cond`」にし、docblock を直す。`hasChildren()` はそのまま `tree_access_condition()` を使う。`getClassName()` の、未公開・削除の文書に `protectedNode` を付けない動き（Surprises の A5）は変えない。
11. `manager/actions/document/resources_list.static.php`: 52-71 行と 129-139 行の `$docgrp`・`$in_docgrp`・`isAdmin()` の分岐を、10 と同じ形にする（`tree_show_protected` で WHERE に足さないのも同じ）。174-176 行の `isAdmin()` で `has_access=1` にする分岐は、`$cond === ''` のときにする。81-82 行のコメントの「管理者でない利用者」を「絞る利用者」に直す。
12. `manager/actions/main/search.static.php` の 129-142 行を、`$cond = manager()->documentAccessCondition('');`（表に別名が無い問い合わせなので空）にし、空でなければ `$where[] = '(' . $cond . ')'` にする。`tree_show_protected` を見ないことと理由（Decision Log）をコメントに書く。
13. `manager/actions/document/parent_picker.dynamic.php` の `parent_picker_access_where()`（273-291 行）を、`$cond` が空なら ''、それ以外は `' AND ' . $cond` にする。
14. `manager/actions/document/document_data.static.php` の 37-43 行を、`$cond` が空でなければ `'AND ' . $cond` を `$where` に足す形にする。
15. `manager/actions/document/mutate_content/functions.php`: `db_value($id, $docgrp)` の第 2 引数をやめ、`documentAccessCondition('sc')` で絞る（`(manager()->isAdmin() || !$docgrp)` の分岐を消す）。`mutate_content.dynamic.php:28`・`mutate_draft.dynamic.php:28` の呼び出しを直す。`getDocgrp()` は M3 で TV に使うので、ここでは残す。
16. 構文の確認（手順 8 と同じ）。`git grep -n "mgrDocgroups" -- manager/frames manager/actions/document manager/actions/main` の結果が、`mutate_content/functions.php` の `getDocgrp()` だけになる。`git grep -n "isAdmin()" -- manager/frames/nodes.php manager/actions/document/resources_list.static.php manager/actions/main/search.static.php manager/actions/document/parent_picker.dynamic.php manager/actions/document/document_data.static.php` が出力なし。
17. `<tmp>/run-all.sh m2` で取り直し、M1 の記録と比べる。期待: 差は表の「M2」の行だけ。

M3 TV と QuickManager

18. `manager/actions/document/mutate_content/functions.php` の `getTmplvars($docid, $template_id, $docgrp)`: 38-42 行の `manager()->isAdmin() ? 1 : 0` を `manager()->restrictsByDocumentGroups() ? 0 : 1` にし、第 3 引数をやめて `manager()->userMgrDocumentGroupIds()` から作る。`getDocgrp()` と、呼び出し（`mutate_content.dynamic.php:21, 49, 69`、`mutate_draft.dynamic.php:21, 51`、`functions.php:157`、`fields.php:315` のコメント）を直し、`getDocgrp()` を消す（ほかで使っていないことを `git grep -n "getDocgrp" -- manager assets` で確かめる）。
19. `manager/processors/document/save_resource.processor.php` の `get_tmplvars()`（274-295 行）: `sessionv('mgrRole')` と `sessionv('mgrDocgroups')` を、18 と同じ形にする。
20. `assets/plugins/qm/qm.inc.php`: `checkAccess()`（175-212 行）を、`documentIdentifier` が空なら false、それ以外は `manager()->canAccessDocument(evo()->documentIdentifier)` にする。`checkTvAccess()`（270-300 行）の `sessionv('mgrRole') == 1` を `!manager()->restrictsByDocumentGroups()` にし、所属は `manager()->userMgrDocumentGroupIds()` から読む。`assets/plugins/qm/inc/on_web_page_prerender.php:214-217` の `$this->docGroup` の作り方も `userMgrDocumentGroupIds()` にする（使っていなければ消す。`git grep -n "docGroup" -- assets/plugins/qm` で確かめる）。
21. 構文の確認。`git grep -n "mgrRole" -- manager/actions/document manager/processors/document assets/plugins/qm/qm.inc.php` が、文書グループと関係ない行（あれば）だけになることを確かめる。
22. `<tmp>/run-all.sh m3` で取り直し、M2 と比べる。期待: 差は表の「M3」の行だけ。ここまでで PR-A を作る（本文に Validation の表の M1〜M3 の行と、リリースノートの文案の管理画面の部分を写す）。

M4 DocumentParser の取得の関数

23. `manager/includes/document.parser.class.inc.php` に、公開側の判定の入口として public の `managerBypassesDocumentGroups(): bool`（`$this->isLoggedIn('mgr') && manager()->bypassesDocumentGroups()`。docblock に「定義は ManagerAPI::bypassesDocumentGroups()。ログインしていない閲覧では ManagerAPI を読み込まない。公開側の privateweb の判定に使い、use_udperms は見ない」）を足す。trait の関数は `$modx->managerBypassesDocumentGroups()` で呼ぶ（trait が `$modx` 経由で書かれているため public にする）。
24. 管理画面側の分岐を `manager()->documentAccessCondition()` にし、公開側の「ロールの ID が 1」を 23 にする:
    - `getDocumentObject()`（3786-3800）: 公開側は今の `privateweb=0`・所属の OR に、`managerBypassesDocumentGroups()` なら条件を付けない形。管理画面側は `$cond` が空なら条件なし、あれば `$cond`。`1=<ロールの ID>` をやめる。
    - `getPageInfo()`（4335-4370）: 管理画面側の `1='<ロールの ID>' OR sc.privatemgr='0'` と所属の OR を `$cond` にする。公開側は変えない。
    - `getDocuments()`（4212-4262）: 2 つの分岐の `mgrRole != 1` を、公開側は `!managerBypassesDocumentGroups()`、管理画面側は `$cond`（所属が無い分岐は別名なしの `documentAccessCondition('')`）にする。
    - キャッシュの読み込みの確認（1679-1700）: `$this->session('mgrRole') != 1` を `!$this->managerBypassesDocumentGroups()` にする。
    - trait の `getAllChildren()`（2196-2215）: 公開側は `privateweb=0`・所属の OR に、`managerBypassesDocumentGroups()` なら条件なし（所属が無くても外す）。管理画面側は `$cond`。
    - trait の `getActiveChildren()`（2257-2267）・`getDocumentChildren()`（2296-2306）: 管理画面側の `$_SESSION['mgrRole'] != 1` の分岐を `$cond` にする（`getDocumentChildren()` の、管理者で所属があると子が減る不具合もこれで消える）。公開側は変えない。
25. 構文の確認。`git grep -n "mgrRole" -- manager/includes/document.parser.class.inc.php manager/includes/traits` が、`token_auth()` の 1241 行（ログインの処理）だけになる。あわせて、グローバル設定のツリーのノード名を日付（`createdon` など）にし、V2 の editor でツリーを開いて、開けない文書のノード名が `'- - -'` になり、PHP の警告が出ないことを確かめる（Surprises の `nodes.php:492`。main と同じなら可）。
26. `<tmp>/run-all.sh m4` で取り直し、M3 と比べる。期待: 差は表の「M4」の行だけ。PR-B を作る。

M5 ドキュメントと最終確認

27. `assets/docs/architecture.md` の「データアクセスと拡張」に、文書グループの判定の置き場所を 3〜5 行で書く（正は `ManagerAPI` の `bypassesDocumentGroups()`（管理者の定義）・`restrictsByDocumentGroups()`・`documentAccessCondition()`・`canAccessDocument()`・`canOpenWithDocumentGroups()`、`checkPermissions()` はこれを使う公開の API、`tree_show_protected` は表示だけ、公開側の `privateweb` の扱いは DocumentParser の `managerBypassesDocumentGroups()`）。詳しい規則は docblock を正にし、ドキュメントに複製しない。`assets/docs/core-issues.md` の「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」の「関連ロードマップ」の「`isAdmin()` に置き換えるだけ」を「`bypassesDocumentGroups()` に置き換えるだけ」に直す。`doc-audit` の手順で、変えたファイルのパスの表記と参照を確かめる。
28. PHP 7.4 の検証環境（`references/environment.md` の PHP 7.4 の節）で、V1 の admin・saveonly0・adminnosave・editor の T・I・E・X・F・M を取り、PHP 8 側の M4 の記録と同じになることを確かめる。あわせて、DB を `mysql:5.7` の公式イメージにした検証環境でも、V1 の editor・admin の T・L・S・P・I・E を取り、MariaDB 側の記録と同じになることを確かめる（ツリーと子の一覧の `MAX(IF(… IN (SELECT …)),1,0))` と `GROUP BY` の組み合わせ、`ONLY_FULL_GROUP_BY` で通ること）。
29. `<tmp>/run-all.sh m5` で全体を取り直し、M4 と同じであること。ブラウザのコンソールと `log:tail` にエラーが無いこと。

## Validation and Acceptance

Docker の検証環境で、M0 の手順 3 のデータを使い、手順 4 の記録を main と各マイルストーンで比べる。「同じ」は main の記録と一致すること。「変わる」は、その列のマイルストーンから表の右の結果になり、それ以外の行は main と同じであること。表の文書の名前は `u1338-` を省いたもの。「全部」は T の子のうち、その場所・関数が出しうるもの（`getActiveChildren`・`getDocumentChildren(T,1)` は未公開の u2 を含まない）。利用者のまとまり: 管理者（変更後）＝admin・webmaster・adminnosave・saveonly・saveonly0、管理者でない＝editor・editor0・acc・web。

既定のロール（管理画面）:

| id | 利用者・設定 | 場所 | main（M0 の実測） | 変更後 |
| --- | --- | --- | --- | --- |
| A1 | admin・webmaster、V1〜V3 | T・L・S・P・I・E・X・R | すべて出て、すべて開けて操作できる | 同じ |
| A2 | editor（G1）、V1 | T・L・S・P | pub・m1・w・n（ルートでは T も）が出る。m2・f2・u2 は出ない。c2 は T・L では出ない（親の f2 が出ない）が、S・P では出る | 同じ |
| A3 | editor、V1 | I・E・X | pub・m1・w・c2・n・T は可。m2・f2・u2 は `access_permission_denied` | 同じ |
| A4 | editor0（所属なし）、V1 | T・L・S・P・I・E・X | `privatemgr=0` の文書（pub・w・n・c2・T）だけ出て、開けて操作できる | 同じ |
| A5 | editor、V2 | T・L | T: m2・f2 が `protectedNode`、u2 は印なしで出る。L: m2・f2・u2 が `protectedNode`。L の「すべてを選択」は pub・m1・w・n | 同じ |
| A6 | editor・editor0、V2 | S | 全部出る（開けない文書も。開くとエラー） | V1 と同じ（M2。Decision Log） |
| A7 | editor、V3 | T・L・P | m2・f2・u2 が出ない。L の「すべてを選択」は 4 件 | admin と同じ（M2） |
| A8 | editor、V3 | I・E | m2・f2・u2 は `error3`（`checkPermissions()` は通るが、読み込みで絞られる） | 開ける（M2） |
| A9 | editor0、V3 | T・L・P | m1・m2・f2・u2 が出ない（I・E は開ける） | admin と同じ（M2） |
| A10 | editor・editor0、V1・V3 | R・P のルート | R はなし。P のルートは V1 なし・V3 あり | 同じ |
| A11 | editor・editor0、V4 | T・L | T・L に m1（editor0 だけ）・m2・f2（L は u2 も）が `protectedNode` で出る。「すべてを選択」は editor 4 件・editor0 3 件 | 印なしで全部、「すべてを選択」も 7 件（M2） |
| A12 | editor・editor0、V4 | P・I・E | P は V1 と同じ。editor の I・E の m2・f2・u2 は `error3` | P は全部、I・E は開ける（M2） |

独自のロール（管理画面）:

| id | 利用者・設定 | 場所 | main（M0 の実測） | 変更後 |
| --- | --- | --- | --- | --- |
| B1 | saveonly（G1）、V1〜V3 | T・L・S・P | editor と同じ（V2 は印つき） | admin と同じ（M2） |
| B2 | saveonly、V1 | X（m2・f2・u2） | 通る（同じ親の知らせ） | 同じ |
| B3 | saveonly、V1〜V3 | I・E（m2・f2・u2） | `error3`（入口は通り、読み込みで絞られる） | 開ける（M2） |
| B4 | saveonly0（所属なし）、V1〜V3 | T・L・S・P | editor0 と同じ（V2 は印つき） | admin と同じ（M2） |
| B5 | saveonly0、V1 | I・E・X（m1・m2・f2・u2） | 開けて操作できる | 同じ |
| B6 | saveonly・saveonly0、V1 | R・P のルート・X のルートへの移動 | あり・あり・`MOVED` | 同じ |
| B7 | adminnosave（isAdmin、save_role なし）、V1〜V3 | T・L・S・P | 全部出る | 同じ |
| B8 | adminnosave、V1・V2 | I・E・X（m1・m2・f2・u2） | `access_permission_denied` | 開けて操作できる（M1） |
| B9 | adminnosave、V1・V2 | R・P のルート・X のルートへの移動 | なし・なし・`access_permission_parent_denied` | あり・あり・`MOVED`（M1） |
| B10 | adminnosave、V3 | R | なし | あり（M1） |
| B11 | acc・web（片方だけ）、V1〜V3 | すべて | editor0 と同じ | editor0 と同じ（V2 の S は A6、V3 は A9 と同じく M2 で変わる） |
| B12 | saveonly（G1）、V1 | f2（D2）の下への新規の作成（#1318 の保存の確認。D2 を引き継ぐ） | `resource_permissions_error` で止まる | 保存される（M1）。保存のあとに戻る編集画面は M1 では `error_no_privileges`（`db_value()` が古い条件のため）、M2 から開ける |
| B13 | adminnosave（所属なし）、V1 | T の下の文書に D2 を付けて保存（#1318 の保存の確認） | `resource_permissions_error` で止まる | 保存される（M1）。保存のあと開ける |

B12・B13 は M0 の記録のスクリプトに入っていない（文書グループの集合が変わる保存）ので、M2 の検証で個別に確かめる（M1 の自己レビューの中 1）。

TV・QuickManager:

| id | 利用者・設定 | 場所 | main（M0 の実測） | 変更後 |
| --- | --- | --- | --- | --- |
| C1 | admin、V1 | V | 欄があり、保存される | 同じ |
| C2 | webmaster、V1・V3 | V | 欄があり、保存すると文書は保存され、TV の値だけ入らない | 保存される（M3） |
| C3 | editor、V1 | V | 欄なし | 同じ |
| C4 | 管理者でない利用者、V3・V4 | V | 欄なし | 欄がある。editor は保存される（M3） |
| C5 | editor・editor0・saveonly・saveonly0・acc・web、V1 | Q の編集ボタン（n） | なし | あり（M3） |
| C6 | 管理者でない利用者、V3・V4 | Q の編集ボタン（m2・f2・n・u2。editor0・acc・web は m1 も）、TV ボタン・QT（w 以外） | 編集ボタンなし、TV ボタンなし、QT は `Access denied` | 編集ボタン・TV ボタンあり、QT は FORM（M3。投稿担当者は `save_document` を持つ） |
| C7 | webmaster・adminnosave、V1 | Q の TV ボタン（w 以外） | なし | あり（M3） |
| C8 | saveonly・saveonly0、V1 | Q の編集ボタン・TV ボタン（m2・f2・u2。saveonly0 は m1・n も） | 編集ボタンなし（saveonly の m1 はあり）・TV ボタンなし | どちらもあり（M3） |
| C9 | webmaster・adminnosave・saveonly・saveonly0、V1 | QT（pub・m1・m2・n） | `Access denied` | FORM（M3） |
| C10 | admin・editor・editor0、V1 | Q・QT（n は C5） | admin は全部。editor は pub・m1・c2、editor0 は pub・c2 の編集ボタン（w・u2 は 403・404 のページの編集ボタン）。TV ボタンと QT は admin だけ | 同じ |
| C11 | adminnosave、V1 | Q（u2） | 404 のページ（id 4）の編集ボタン | u2 の編集ボタン（M1。D5）、TV ボタンも（M3） |
| C12 | saveonly・saveonly0、V1・V3 | V | 欄なし | 欄あり（M3） |

公開側と取得の関数:

| id | 利用者・設定 | 場所 | main（M0 の実測） | 変更後 |
| --- | --- | --- | --- | --- |
| D1 | ログインなし（anon） | F・M-front | w は 403（Home）、u2 は 404（id 4）、n は本文。M-front は w を含まない | 同じ |
| D2 | admin、V1 | F（w） | 本文が出る | 同じ |
| D3 | webmaster・adminnosave・saveonly・saveonly0、V1・V3 | F（w）・Q（w）・QT（w） | 403（Home）。Q は Home の編集ボタン、QT は 403 の `Access denied` | 本文が出て、w の編集ボタン・TV ボタン、QT は 200 の FORM（M4） |
| D4 | saveonly・saveonly0、V1 | F（u2） | 本文が出る（`checkPermissions()` を `save_role` で通る） | 同じ |
| D5 | adminnosave、V1 | F（u2） | 404（id 4） | 本文が出る（M1） |
| D6 | admin、V1 | M-mgr | `getAllChildren(T)` だけ pub・w・n、ほかは全部 | 全部（M4） |
| D7 | admin+G1、V1 | M-mgr の `getDocumentChildren` | `(T,1)` は m1 だけ、`(T,0)` は無し（不具合。Surprises） | `(T,1)` は全部、`(T,0)` は u2（M4） |
| D8 | webmaster・adminnosave・saveonly0（saveonly は m1 も）、V1・V3 | M-mgr | pub・w・n。`getDocumentChildren(T,0)` は無し | 全部（M4） |
| D9 | admin・admin+G1・webmaster・adminnosave・saveonly・saveonly0、V1 | M-front の `getDocumentObject`・`getDocuments`・`getAllChildren` | admin・admin+G1 は `getDocumentObject`・`getDocuments` だけ w を含む。ほかは w を含まない | どれも w を含む（M4） |
| D10 | 全員、V1 | M-front の `getPageInfo`・`getActiveChildren`・`getDocumentChildren` | w を含まない | 同じ |
| D11 | editor・editor0・acc・web、V3（editor・editor0 は V4 も） | M-mgr | editor は pub・m1・w・n、ほかは pub・w・n | 全部（M4） |

あわせて確かめること:

- #1318 の保存の確認: V1 で adminnosave が pub の文書グループを D2 だけにして保存すると通り、保存後に a=27 で開ける（main は `resource_permissions_error` で止まる。M1）。editor が m1 の本文だけを変えて保存すると通る（main と同じ）。グループの欄を変えられるのは `documentGroupAccess()` が許す利用者だけで、saveonly・saveonly0・acc は D2 を選べない（main と同じ。画面の欄で確かめる）。
- `checkPermissions()` を使う各処理（複製 a=94・削除 a=6・復元 a=63・公開の一括 a=121・下書きの保存など）は、B5・B8 と同じ向き（saveonly0 は今と同じ、adminnosave は通るようになる）。代表として、使い捨ての文書で saveonly0 と adminnosave の複製を 1 回ずつ行う。
- 見えなくなるもの: 記録の全体で、変更後に main より出る文書が減る・開けなくなる行は、A6（V2 の検索から開けない文書のリンクが消える）だけであること。`sort` した TSV の `diff` の `<` の行を見て確かめる。
- 記録の W（PHP の警告・エラーの件数）が各マイルストーンで 0 件。ブラウザのコンソールにエラーが無い。
- 静的な確認: 手順 9・16・21・25 の grep。変えたすべての PHP ファイルで、`<dc> exec app php -l` と `php:7.4-cli` の `php -l` が `No syntax errors detected`。

## Idempotence and Recovery

M1〜M4 はコードだけの変更で、DB の形・設定・データは変えない。どのマイルストーンのコミットも revert で戻せる（M2〜M4 は M1 のメソッドを使うので、戻すときは後ろから）。M1 だけを残して止めても、判定は 1 か所で、画面は今の条件のまま動く（一覧の見え方が編集画面の入口より狭いことは残る）。検証のデータは `u1338-` で始まる名前で作るので、`site_content`・`documentgroup_names`・`membergroup_names`・`user_roles`・`manager_users` などから名前で消せる。設定の組は `REPLACE INTO modx_system_settings` で戻し、キャッシュを消す。記録の途中で止まったら、`<tmp>/run-all.sh` をはじめから流し直す（先頭でロックと TV の値を消す）。M0 の記録とスクリプトは git の管理外の一時領域にあり、消えていたら Artifacts and Notes の基準のコミットを立て、手順 2〜4 で取り直す。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1338（「着手時の決定」と、最後のコメント「決定の見直し」）
- ブランチ（メインが決めた）: `fix/1338-docgroup-bypass-condition` は `gh issue develop` で #1338 に紐づけ済み。紐づいたブランチの PR は本文が `Refs` でもマージで Issue を閉じるので、このブランチは最後の PR-B（M4。`Closes #1338`）に使う。この計画のコミットと M0〜M3（PR-A。`Refs #1338`）は、紐づけないブランチ `fix/1338-m1-admin-side` を origin/main から作って載せる。PR-B は、PR-A のブランチを紐づいたブランチに取り込んでから M4 を積み、base を PR-A のブランチにする。PR-A のマージ後に base を main に切り替える。
- PR-A: #1361（`fix/1338-m1-admin-side`。2026-11-11 に作成し、1.3 のリリース後まで下書き）。PR-B と同じリリースに入れる（PR-A だけでは DocumentParser の取得の関数が古い判定のままで、新しく見える文書の値を取れない場面がある）。
- M0〜M3 の記録: ジョブの一時領域 `/home/yamamoto/.claude/jobs/62dfc916/tmp/v1338m0/`（消えることがある。消えていたら M0 の手順で取り直す）。
- 関連: #1318（PR #1349、マージ済み。`canOpenWithDocumentGroups()` と保存の確認）、#1353（`privatemgr` の付け方の食い違い。範囲外）、#1300・#1307（文書グループの付与の規則）。
- 修正前の記録（M0）: 基準のコミット f6e94f10d（コードは origin/main 899a6ddea と同じ）。置き場所 `/home/yamamoto/.claude/jobs/62dfc916/tmp/v1338m0/`: 記録 `1338-m0.tsv`（V1〜V3）・`1338-m0-v4.tsv`（V4）、警告の件数 `logs-after-m0-count.txt`（0）、データ `1338-fixture.sql`・`1338-fixture-n.sql`、id の一覧 `1338-ids.txt`、記録のスクリプト `1338-m0.js`、全体の取り直し `run-all.sh <タグ>`、環境 `env.sh`・`up.sh`・`q.sh`、読み方の補助 `pivot.py`・`cmpset.py`（`cmprun.py` は M0 の 1 回目用。使わない）。検証環境の名前は `evo-validate-1338`（`127.0.0.38`）。
- M0 で作った id（`1338-ids.txt`）: 文書 T=16・pub=17・m1=18・m2=19・w=20・f2=21・c2=22・u2=23・probe=24・n=25。モジュール u1338probe=2、TV u1338tv=2。管理側のユーザーグループ G1=1・G2=2、Web のグループ WG=1。文書グループ D1=1・D2=2・DW=3・DN（`1338-ids.txt` に無い。`1338-fixture-n.sql` の最後の SELECT が出す。4 件目なので 4 のはずだが、使う前に `SELECT id FROM modx_documentgroup_names WHERE name='u1338-DN'` で確かめる）。ロール R_S=4・R_P=5・R_A=6・R_W=7。
- M0 で変えた設定: `admin` のロールを 1 に、QuickManager（プラグイン id 3）の `tvbuttons` を true に。
- 想定コミット単位は Plan of Work の 5 つ。

## Interfaces and Dependencies

リリースノートに書くこと（文案）:

- 文書グループで「管理側で非公開」にした文書の扱いで、すべての文書を見て操作できる「管理者」を、ロールに「ロールの保存」がある、または「ユーザーグループの権限の管理」と「Web ユーザーグループの権限の管理」の両方がある利用者、に統一した（アクセスの規則を自分で変えられる人）。これまでは場所によって、「ロールの保存」がある、2 つの権限の管理が両方ある、ロールの ID が 1、と違っていた。
- 既定のロール（Administrator・ウェブマスター・投稿担当者、古いサイトの Editor・Publisher）の管理画面での見え方と操作は変わらない。独自のロールでは次が変わる。今より見える・扱える文書が減るロールは無い。
  - 「ロールの保存」だけを持つロールは、これまでも開いて操作できた文書が、ツリー・子の一覧・検索・親の選択・文書の情報・編集画面・TV・QuickManager にも出るようになる。
  - 2 つの権限の管理を両方持ち、「ロールの保存」を持たないロールは、すべての文書を開いて操作でき、ルート直下にも作れるようになる。
- 「文書グループの権限を使う」が「いいえ」のときは、ツリー・子の一覧・親の選択・文書の情報・編集画面・TV・QuickManager も文書グループで絞らない（これまで、以前に付いた非公開の印が残っていると、これらの画面にだけ出なかった。id を指定すれば開けていた）。
- 「権限のないリソースの表示」は、ツリーと子の一覧に開けない文書を印つきで出すだけの設定になる。検索の結果には出さない（これまでは出て、開くとエラーになっていた）。
- 文書グループに限った TV: ウェブマスターなどの管理者が、所属しないグループに限った TV を編集画面で変えたとき、保存されるようになった（これまでは画面に出るのに、TV の値だけ保存されなかった）。QuickManager の TV ボタンと TV の編集も同じ条件で出る。
- QuickManager の編集ボタンは、管理画面で開ける文書なら出る（管理側で非公開でない文書グループに入った文書や、「文書グループの権限を使う」が「いいえ」のときも出る）。
- 公開側（PR-B）: 管理画面にログインしたまま公開側を見るとき、Web 側で非公開の文書を見られる利用者を、ロールの ID が 1 から上の「管理者」に変えた。既定のロールでは、ウェブマスターも見られるようになる。「ログイン時のキャッシュ」を無効（キャッシュを使う）にしているサイトでは、管理者が見たときの一覧の部品の出力がページキャッシュに入ることがある（これまでも ID 1 で同じ）。
- ロールの ID が 1 のロールから「ロールの保存」と 2 つの権限の管理を外しているサイトでは、そのロールの利用者は TV の保存・QuickManager の TV ボタン・文書の取得の関数で、ほかの利用者と同じく文書グループで絞られるようになる（ロールの ID では決めなくなった）。
- 開発者向け: `ManagerAPI` に `bypassesDocumentGroups()`・`restrictsByDocumentGroups()`・`documentAccessCondition()`・`canAccessDocument()`・`userMgrDocumentGroupIds()`・`tmplvarAccessCondition()`・`canAccessTmplvar()` を、DocumentParser に `managerBypassesDocumentGroups()` を足した。文書グループで絞る問い合わせは、これらを使う。`isAdmin()` の意味は変えていない。`manager()` は公開側でも `ManagerAPI` を読み込んで返す。`checkPermissions()` の引数と戻り値は変えていない。`db_value()`・`getTmplvars()`（編集画面の関数）の引数が減り、`getDocgrp()` は無くなった。

変えないもの: DB の形、`privatemgr`・`privateweb` の付け方（#1353）、`isAdmin()` の意味と文書グループ以外の使い道、`isAllowed()`・`isInAllowedParents()`（許可するリソースの範囲）、文書グループの付け方（`documentGroupAccess()` 以下、#1300・#1307・#1318）、公開側の `privateweb` の絞り方（ロールの ID の置き換えを除く）、ルートの入口ごとの `use_udperms` の扱い（#1356）、プラグインのイベント。

依存: #1318 は main にマージ済み（ec7b45146）。#1353 とは同じ `ex_managerapi.php` に触れるが、別のメソッド（`setMgrDocsAsPrivate()` を使う側）なので、どちらが先でもよい（後の方が衝突を手で直す）。

## 未決事項

なし。2026-11-10 に次のとおり決めた。

1. M4（公開側を含む DocumentParser の取得の関数）は、この Issue に入れる（ユーザーさんの判断。planner の推奨 (a)）。決定どおり「ロールの ID が 1」を残さない。ウェブマスターなどの公開側の見え方が変わる（D3・D9）ので、PR-B に分け、リリースノートで知らせる。
2. 範囲外とした食い違いは、別の Issue にした（メイン）。
   - 文書グループで絞っていない一覧（予約の一覧・エレメントの画面の文書の選択・テンプレートの「使う文書」）: #1354。M1 の後に `documentAccessCondition()` で直す
   - Ditto・Wayfinder の独自の条件: #1355。M1 の後に直す
   - ルートの入口ごとの `use_udperms` の扱い: #1356。既定のロールの動きが変わるので、着手時に相談して決める
   - privatemgr の付け方の食い違い（DocAPI・ddtools）: #1353（計画の前に起票済み）
3. 管理者の条件は「`isAdmin()` または `save_role`」（Issue の最後のコメント）。判定は `bypassesDocumentGroups()` に置き、`isAdmin()` は変えない。
4. メソッドの名前は `bypassesDocumentGroups()`（ManagerAPI）と `managerBypassesDocumentGroups()`（DocumentParser）にする（メイン）。`isAdmin()` は文書グループ以外の用途（バックアップの復元・PathGuard・モジュールの実行など）にも使うので、意味を変えず別のメソッドにする。
5. ロール ID 1 から `save_role` と 2 つの権限の管理を外したサイト（既定では起きない）は救わず、リリースノートに書く（メイン）。互換のための分岐を本体に足さない方針による。
