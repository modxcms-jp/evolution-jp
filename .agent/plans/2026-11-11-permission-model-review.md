# ExecPlan: 管理画面の権限の判定を 1 か所に寄せ、権限の作りを見直す（Issue #1357 の第 2・3 段階）

## Purpose / Big Picture

管理画面で「この操作をしてよいか」の判定が、画面・メニュー・処理ごとに別々の権限の組み合わせで書かれているため、メニューに出るのに押すと断られる、別の画面では出ない、といった食い違いがある。第 2 段階では、文書グループで済ませた形（判定の正を `ManagerAPI` に置き、すべての入口がそれを使う）を、ロールの権限の判定にも広げる。第 3 段階では、そのうえで権限の作り（権限どうしの依存、`save_role` の扱い、ロール ID 1 の特別扱い、管理側と Web 側の利用者・グループ）を見直すかを決め、決めたものを既存のサイトのロールを壊さずに移す。

目指す形は、次の 3 文で説明できる構造（2026-11-12 に本人と決めた）:

- 何をできるかはロールで決まり、その判定は 1 つの表にある。ボタンもその表で出し分けるので、出たボタンは押せば通る。
- どの文書に触れるかは文書グループで決まる（機能がオフなら全部）。
- 管理者はそのどちらにも縛られない。管理者でない人は、自分が持つ以上の権限を人に渡せない。

使う人から見た変化は、第 2 段階では「メニュー・ボタンに出る操作は、押せば通る。通らない操作は出ない」こと、第 3 段階では「ロールの編集画面で付けた権限の意味が、その画面で読み取れる」こと。既定の設定（文書グループの機能 `use_udperms` が無効）で使うサイトの動きは、変えない側を優先する。

## Progress

- [x] (2026-11-11) 計画の下書き（develop/1.4 ＋ #1394 の変更、2ba7e4b05 で調査。#1394 は #1405 で develop/1.4 にマージ済み）
- [x] (2026-11-12) Decision Log の D1〜D8 を決めた（本人が 4 点、内部の 3 点は Claude）
- [x] (2026-11-12) M0: 修正前の記録（Docker。既定のロール・所属なしの利用者を含む）。16,841 行を取り、流し直して差 0。スクリプトの置き場所と観測された食い違いは Artifacts and Notes
- [x] (2026-11-12) M1: 非公開の印を 1 回の更新で計算し直す（決定不要。#1397 の残り）。PR #1407（ブランチ `fix/1357-m1-private-flags`）。計画の確認を待つため develop/1.4 へのマージは保留
- [x] (2026-11-12) M2: 呼び出し元がそろっている権限の判定を ManagerAPI に寄せる（決定不要。動きは変えない）。ブランチ `refactor/1357-m2-permission-helpers`
- [x] (2026-11-12) M3: 文書の操作（移動・公開・削除・複製・編集・新規）の判定を 1 か所にし、入口をそろえる。ブランチ `refactor/1357-m3-document-operations`。表（`OPERATION_PERMISSIONS`）と `can()` を ManagerAPI に置き、M2 の `ELEMENT_PERMISSIONS` を取り込み、入口を表で出し分け、`DocAPI::can*Doc()` を消した。入口の取り直し（Docker）は、既定のロールが変わらず、カスタムのロールが「M3 の変わる行」の行だけ変わることを確かめた。M0 の記録のスクリプトでの取り直しは未了
- [ ] M4: 文書以外の入口の食い違いをそろえる（モジュール・利用者・ロール・エレメント）
- [ ] M5: サイトのインポートの取り込み先と初期化を確かめる（#1404）
- [ ] M6: 権限の依存
- [ ] M7: `save_role` などアクセスの規則を変えられる権限の扱い
- [ ] M8: ロール ID 1 の特別扱い
- [ ] M9: 管理側と Web 側の利用者・グループ
- [ ] M10: 最終の突き合わせ（PHP 7.4・MySQL 5.7）と文書の仕上げ

## Surprises & Discoveries

計画時（2026-11-11、2ba7e4b05 で調査）に分かったこと。行番号はこの時点のもの。

- `hasPermission()` の呼び出しは本体（`manager/`・`install/`・同梱の `assets/`）に 332 行ある。管理画面の入口（`manager/index.php` の `switch`）には、action ごとの権限の表が無く、各画面・処理の先頭が自分で確かめる。
- 文書の移動は、通る経路の条件が入口ごとに 5 通りある。画面 a=51（`manager/actions/document/move_document.dynamic.php:5`）は `move_document`・`save_document`・`publish_document`、処理 a=52（`manager/processors/document/move_document.processor.php:5,9`）は `move_document`・`edit_document` を求め、移動は a=51 → a=52 と続けて通るので、実際に移せるのは 4 つすべてを持つ利用者だけ。入口は、ツリーの右クリック（`manager/frames/tree.php:755-760`）と一覧のボタン（`resources_list.static.php:288`）と文書の情報（`document_data.static.php:156`）が `move`・`save`、一覧の右クリック（`resources_list.static.php:702`）が `move`・`save`・`publish`、一覧の一括の移動（同 21 行）は `move` だけ、編集画面のボタン（`mutate_content/action_buttons.php:32-33`）は `save`・`move`。`DocAPI::canMoveDoc()`（`ex_docapi.php:648`）は `save` だけで、どこからも呼ばれない。
- 公開・非公開の処理（a=61・62・121。`publish_content.processor.php:5`・`unpublish_content.processor.php:5`・`batch_content.processor.php:17`）は `save_document`・`publish_document` を求めるが、ツリーの右クリック（`tree.php:793,806`）・一覧の右クリック（`resources_list.static.php:712,715`）・ツリーの JS の `permpub`（`tree.php:75`）は `publish_document` だけで出す。
- 削除・元に戻す（a=6・63・121 の delete）は `delete_document` だけで通るが、一覧のボタン（`resources_list.static.php:294`）・文書の情報（`document_data.static.php:170`）・編集画面（`DocAPI::canDeleteDoc()`）は `save_document` も求めて出す（出ないのに処理は通る向き）。
- 複製（a=94。`duplicate_content.processor.php:5`）は `new_document`・`save_document`。一覧の右クリック（`resources_list.static.php:705`）だけ `new_document` で出す。
- 編集画面（a=27）の入口の判定（`ManagerAPI::documentEditorDenial()`）は `view_document`（ロールの編集画面でオンに固定）を見て、`edit_document` を見ない。`edit_document` で決まるのは、ツリー・一覧の右クリック・検索のリンク先（`search.static.php:166`）・下書きを作るボタン・移動の処理など、入口の表示が主。一覧のボタン「編集」（`resources_list.static.php:285`）と文書の情報の「編集」（`document_data.static.php:149`）は `save_document` で出す。
- 新規作成の画面（a=4・72。`mutate_content/functions.php:501`）は `new_document`、保存（a=5。`save_resource.processor.php:6,484`）は `save_document` と `new_document`。ダッシュボードの古いアイコン（`welcome.static.php:53` の `NewDocIcon`）は `new_document` または `save_document` で出す（#1394 でそろえたのは `manager/media/style/common/welcome.php` と `manager/frames/menu.php` の側）。
- モジュールの実行（a=112。`execute_module.processor.php:23`）は `isAdmin()` でなければモジュールのグループ（`site_module_access`）で絞るが、メニュー（`manager/frames/menu.php:294`）は「ロール ID が 1 でなければ」絞る。ウェブマスター（ロール 2、`isAdmin()` が真）は、メニューには所属のグループのモジュールしか出ないが、ほかのモジュールも実行できる。ダッシュボード（`welcome.static.php:148`）は `isAdmin()`。
- モジュールのグループの絞り込みは、`use_udperms` が無効でも実行の処理で効く（`execute_module.processor.php` は設定を見ない）。一方、モジュールの編集画面でグループを付け外しする欄は `use_udperms` が有効なときだけ出る（`mutate_module.dynamic.php:402`、保存も `save_module.processor.php:158`）。有効だった時期に付けた結びつきは、無効にすると見えないまま効き続ける。
- Web ユーザーの一覧の右クリック（`web_user_management.static.php:25,31`）は、項目の有効・無効を管理ユーザーの権限（`edit_user`・`delete_user`）で決める。開いた先は `edit_web_user`・`delete_web_user` を見る。
- ロールの管理: メニュー（`menu.php:319`）は `new_role`・`edit_role`・`delete_role` のどれかで出すが、一覧（a=86。`role_management.static.php:6`）は `edit_role` を求める。ユーザーの管理: ダッシュボードの `UserManagerIcon`（`welcome.static.php:108`）は `new_user` または `edit_user`、一覧（a=75）は `edit_user`。Web ユーザーも同じ形（`welcome.static.php:112`）。
- エレメントの管理（a=76）を出す条件が、メニュー（`menu.php:272-275`。テンプレート・スニペット・チャンク・プラグインの `new_*`・`edit_*` のどれか）とダッシュボードの 2 か所（`welcome.static.php:81`・`manager/media/style/common/welcome.php:12`。チャンクを含まない）で違う。
- TV の複製（`duplicate_tmplvars.processor.php:5`）は `edit_template`、テンプレートの複製（`duplicate_template.processor.php:5`）は `new_template`。
- 「`access_permissions` と `web_access_permissions` の両方」（`isAdmin()` と同じ式）を、`isAdmin()` を呼ばずに書いた場所が 2 つある（`mutate_content/functions.php:296`、`assets/modules/docmanager/classes/dm_backend.class.php:350`）。文書グループの「衝突」の知らせ（`access_permissions_docs_collision`）を出す条件は、`mutate_content.dynamic.php:107-113` と `mutate_draft.dynamic.php:110-114` に同じ式で 2 回ある。
- ルートでの新規作成の入口のうち、ツリー（`tree.php:371`）と一覧（`resources_list.static.php:318` の id が 0 のとき）は、`canCreateDocumentUnder(0)` と同じ式（`new_document`・`canPlaceAtRoot()`・`isInAllowedParents(0)`）を書き写していた（#1394 で `canCreateDocumentUnder(0)` に寄せた）。
- 文書の保存（`save_resource.processor.php:452,478,487`）と移動（`move_document.processor.php:47`。#1394 で消えた）は、`checkPermissions()` の前に `use_udperms == 1` を確かめるが、`checkPermissions()` 自身が先頭で `use_udperms` を見る（`document.parser.subparser.trait.php:1133`）。0・1 以外の値（設定の画面では選べない）のときだけ結果が違う。
- 非公開の印（`ManagerAPI::setMgrDocsAsPrivate()`・`setWebDocsAsPrivate()`。`ex_managerapi.php:795-879`）は、「対象の印をすべて 0 にする UPDATE」と「付ける文書を SELECT して 1 にする UPDATE」の 2 回に分けて書く。全件の計算（グループ管理の処理・文書グループの削除・マイグレーション・インストーラの後片付け）では、その間、Web 側で非公開の文書が公開側に `privateweb=0` として出る（管理側も同じく、絞られる利用者のツリーに出る）。DBAPI にトランザクションの API は無い。`setMgrDocsAsPrivate()` は SELECT に `DISTINCT` が無く、`$docid` の扱いが UPDATE（`$docid ?`）と SELECT（`$docid > 0`）で違い、id を `'%s'` で埋め込む。戻り値（印を付けた id のカンマ区切り）を使う呼び出し元は無い（`secureMgrDocument()`・`secureWebDocument()` は返すだけで、呼ぶ側は捨てる）。
- 利用者の保存（`save_user.functions.php:276-291` の `verifyPermission()`）がロールについて確かめるのは、ロール 1 を付けるかどうかだけで、付けるロールが保存する人の権限の範囲に収まるかは見ない。所属のユーザーグループ（`save_user.functions.php:514,651`）と「許可するリソース」も同じ。編集画面の選択肢（`mutate_user/tpl/form/tab_general.php:177-183`）は自分を編集するときに絞るが、保存の処理とはそろっていない（コードを読んだ範囲。M0 で確かめる）。グローバル設定（`settings`）は `use_udperms` そのものを切れる。つまり、`bypassesDocumentGroups()` が `save_role` を特別に扱う理由（アクセスの規則を自分で変えられる権限）は、`save_user` と `settings` にも当てはまる。
- サイトのインポート（`import_site.static.php:150-154`）は、「初期化」（`reset`）にチェックがあると、取り込み先や許可するリソースの範囲に関係なく `site_content` を全件消す。`import_static` の権限だけで通る。

M3 の実装でコードを読んで分かったこと（2026-11-12）:

- 計画の記述とコードが一致した範囲: 移動（a=51 は `move`・`save`・`publish`、a=52 は `move`・`edit`）、公開・非公開（a=61・62・121 は `save`・`publish`）、削除・元に戻す（a=6・63・121 の delete は `delete` だけ）、複製（a=94 は `new`・`save`）、保存（a=5 は `save`、新規のときだけ `new` も）。表の組み合わせはこのとおり。
- ツリーの右クリックの「移動」は、入口の HTML では `move`・`save` で出るが、ツリーの JS（`showPopup()`）が `permpub`（`publish_document` の有無）が 0 のとき `item51` を隠していた。`publish_document` の無いロール（`R_mv`）では、画面上は M0 より前から移動が出ていない。HTML の出力で記録する M0 の記録では「出る」で残っているので、変わる行では「出る→出ない（画面上は元から出ない）」と書く。表で `move` に `publish` を含めたので、この隠す記述は要らなくなり外した。
- 同じ JS の `permpub` が 0 のときの分岐は、`item27`（編集）を「下書きがあれば隠す」処理も持つ（`permpub` が公開・非公開の項目の出し分けと、この下書きの分岐の 2 つの役に使われていた）。`permpub` を `can('document.publish')`（`publish` と `save`）に変えると、`publish_document` を持つが `save_document` を持たないロールが、この分岐に入って「編集」が消える（M3 の検証で見つかった）。そこで、下書きの分岐には元の意味（`publish_document` の有無そのもの）を別の JS 変数 `hasPublishPermission` で渡し、`permpub` は公開・非公開の項目だけに使う。これで下書きの分岐の動きは変わらない。分岐そのものは、下書きの出し分けの別の癖（`publish_document` の有無で編集の出し方が変わる）なので M3 では変えていない。
- 編集画面（a=27）の「移動」「複製」「削除」のボタンは、外側が `canSaveDoc()`（`save_document`）の条件だった。削除は処理が `save_document` を求めないので、外側を外し、操作ごとの条件だけで出すようにした（`R_del` に、編集画面の「削除」が出る。計画の Validation の表は一覧のボタンと文書の情報だけを挙げていた）。
- 新規作成の画面（a=4・72）の保存ボタンも `new_document` だけで出していた（保存 a=5 は `save_document` も求める）。入口の「新規」に含め、`can('document.create')` にそろえた。画面そのもの（`mutate_content/functions.php:497` の確かめ）は処理側なので変えていない。`new_document` だけのロールは、画面を開けるが、保存のボタンは出ない。
- ダッシュボードの古いアイコン（`welcome.static.php` の `NewDocIcon`）は、同梱のテンプレート（welcome.tpl は 4 つのテーマとも `[+iconNewDoc+]` だけを使う）のどれも読まない。入口としてはそろえた（`canCreateDocumentUnder(0)`）が、使われていない。
- 一括の公開（`$canBatchPublish`）と、一覧のボタン「複製」（`DocAPI::canCopyDoc()` が `new`・`save`）、ツリーの「複製」は、元から処理の条件と同じだった。`DocAPI::can*Doc()` は、入口を表に切り替えると同梱のコードのどこからも呼ばれなくなったので、7 つとも消した（`canSaveDraft()` は権限の判定ではなく、定数 1 を返すだけで、これも呼ばれていないが、M3 の対象外として残した）。
- 表を引かずに権限名を直接見る場所を、あえて残した: 下書きの画面とボタン（a=131・132・128、`save_draft_content`・`publish_draft_content` など。`save_document` と `publish_document` の組み合わせが下書き固有）、下書きの作成ボタン（`edit_document`）、編集画面の中の公開の切り替え（`fields.php`・`functions.php:327` の `publish_document`）、QuickManager の TV の編集（`assets/plugins/qm/inc/edit_tv.inc.php` の `save_document`）、親の選択（a=135。`save_document`）。入口というより画面の中の項目か、書き込み範囲の外。
- ツリーの区切り線（`itemSeperator1`・`itemSeperator2`・`itemSeperator3`）は、その群の項目の条件と同じ式にした（以前は `edit`・`new`・`save` のどれかなど、項目と食い違い、項目が 1 つも出ないのに線だけ出る場合があった）。

## Decision Log

- 2026-11-11 / 計画（Claude）/ 決定（実装の選び方。動きは変わらないので本人の判断を待たない。違う案がよければ M1 の前に言ってもらう）: 非公開の印は、1 回の UPDATE で 0 と 1 を同時に書く（`SET privateweb = CASE WHEN id IN (<結びつきから求めた文書の副問い合わせ>) THEN 1 ELSE 0 END`。範囲は、全件なら「今 1 の文書、または付ける文書」、1 件ならその id）。根拠: 1 つの文は InnoDB でも MyISAM（古いサイトに残る。`core-issues.md`）でも、読む側から途中の状態が見えない。副問い合わせは `document_groups` と `webgroup_access`（`membergroup_access`）だけを読み、更新する `site_content` を読まないので、MySQL の「更新する表を副問い合わせで読めない」制限に当たらない。代替案: (イ) 順序を入れ替える（先に 1 を付け、次に付けない文書を 0 に）。間は残るが、公開の文書が一時的に非公開になる向き（安全側）になる。1 つの文で済むなら選ぶ理由が無い。(ロ) トランザクションで囲む。DBAPI に API が無く、MyISAM では効かない。PDO の新 API（#453）の後なら候補。戻り値（印を付けた id）は使う呼び出し元が無いが、外部のコードのために、付けた id を別に SELECT して今と同じ形で返す（要確認: 同梱以外の呼び出しを想定するか本人に確かめる。不要なら戻り値をやめる）。

- 2026-11-12 / M1（Claude）/ 決定（実装の改め）: 1 文の UPDATE は、`id IN (副問い合わせ)` ではなく、結びつきの一覧（`document_groups` ⋈ アクセスの表の `SELECT DISTINCT dg.document`）への LEFT JOIN にした（`SET col = IF(x.document IS NULL, 0, 1)`。全件は `WHERE col = 1 OR x.document IS NOT NULL`、1 件は `WHERE sc.id = <id>` で、派生表の中も `dg.document = <id>` に絞る）。根拠: MySQL 5.7 で `IN (副問い合わせ)` が文書 1 行ごとの副問い合わせ（DEPENDENT SUBQUERY）になり、文書約 5,000 件で一度 4 秒かかって同時の書き込みが止まった（再現は不安定）。JOIN の形は 5.7・8.0・MariaDB 11 のどれでも行ごとの副問い合わせにならず、全件の計算は MariaDB で修正前 59 ms → 19 ms。`db()->update()` は JOIN 付きの UPDATE を想定していないので、`db()->query()` に `replaceFullTableName()` で展開した文を渡す。戻り値は今と同じ形で残した（本人への確認は未了。外部のコードを壊さない側）。

- 2026-11-11 / 計画（Claude）/ 未決 D1: 文書以外の権限の判定を、ManagerAPI でどんな形で持つか。
  - (a) 操作ごとのメソッド（`canMoveDocuments()`・`canManageRoles()` など）。今の `canCreateDocumentUnder()`・`canOpenDocumentEditor()` と同じ形。利点: 呼ぶ側が読みやすく、範囲・文書グループの判定と同じメソッドで混ぜられる。欠点: 操作が 30 前後あり、ManagerAPI（約 1,850 行）がさらに膨らむ。権限の組み合わせが各メソッドの本文に散り、第 3 段階の依存の表と別に持つことになる。
  - (b) 操作 → 必要な権限の表（ManagerAPI の定数）と、それを引く 1 つのメソッド（`can('document.move')` のような形）。範囲・文書グループが要る操作だけ、(a) の形のメソッドを足し、その中で表を引く。利点: 権限の組み合わせの正が 1 つの表になり、第 3 段階で依存（D3）や「強い権限」（D4）の情報を同じ表に足せる。ロールの編集画面が表を読んで「この権限で使える操作」を出すこともできる。欠点: 操作の名前（文字列）を呼ぶ側が持つので、綴りの誤りが実行時まで分からない（未知の名前は例外にして防ぐ）。
  - (c) 権限の判定だけの新しいクラス（例: `manager/includes/permission_policy.class.php`）に (b) の表を置き、ManagerAPI は委ねるだけにする。利点: ManagerAPI が膨らまない。欠点: Issue と `permissions.md` の「判定の正は ManagerAPI」から外れ、判定の置き場所が 2 つになる（文書グループの判定は ManagerAPI に残る）。
  - 影響: どれでも、決めるのは置き場所と呼び方で、`use_udperms` が無効のサイトの動きには関わらない。推奨: (b)。理由は、第 2 段階の目的が「組み合わせの正を 1 つにする」ことで、第 3 段階の依存の表も同じ表に育てられるため。M2 は (a) の形（意味の名前のメソッド）で進め、(b) を選んだら M3 でその中身を表を引く形に変える（M2 のメソッド名はそのまま使える）。

- 2026-11-11 / 計画（Claude）/ 未決 D2: 入口（メニュー・ボタン・右クリック・ダッシュボード）と処理で権限の組み合わせが違うとき、どちらにそろえるか（Surprises の食い違い。M3・M4 の中身）。
  - (a) 処理の今の条件（通る経路が複数あるときは、すべての処理の条件を合わせたもの）を正にして、入口を合わせる。処理は変えない。利点: 誰が何をできるかは変わらない。変わるのは、押しても断られる項目が出なくなる・通るのに出ていなかった項目が出ることだけ。欠点: 処理の今の条件に意味の通らないもの（移動に `publish_document`・`edit_document` が要る、削除に `save_document` が要らない）がそのまま正になる。編集画面だけは処理の条件が `view_document`（外せない）なので、入口は今の `edit_document` を正とする例外にする。
  - (b) 操作ごとに意味の通る組み合わせを決め直し、処理も入口も合わせる（案: 移動＝`move`・`save`、公開・非公開＝`publish`・`save`、削除・元に戻す＝`delete`・`save`、複製＝`new`・`save`、編集画面を開く＝`edit_document`、新規＝`new`・`save`）。利点: 1 回で意味がそろう。欠点: カスタムのロールで、できることが増える利用者（移動に `publish`・`edit` が要らなくなる）と減る利用者（削除・元に戻すに `save` が要る、編集画面に `edit_document` が要る）が出る。
  - (c) 第 2 段階は (a) にとどめ、意味の決め直しは第 3 段階の依存の表（D3）で行う。
  - 影響: ロールの権限は `use_udperms` と別の仕組みなので、どれを選んでも文書グループの機能の有効・無効で差は無い。既定のロール（1 管理者・2 ウェブマスター・3 投稿担当者、古いサイトの Editor・Publisher）は、文書の権限を全部持つか（1・2）、文書の操作の権限をすべて持つ（3・Editor・Publisher）ので、(a)〜(c) のどれでも見え方は変わらない。変わるのはカスタムのロールだけ。推奨: (c)。理由は、第 2 段階を「できることを変えない」範囲に保ち、できることが変わる判断を、依存と移し方（D3・D6）と一度に決めるため。
  - 補足（M4 の 1 件）: モジュールのグループの絞り込みは、`use_udperms` が無効でも実行の処理で効く。(a) なら実行の処理を正として、無効のときも絞る今の動きを保ち、編集画面の欄は「無効でも、既に結びつきがあるモジュールには出す」にそろえる案を推奨する（無効のサイトで実行できるモジュールを増やさない）。

- 2026-11-11 / 計画（Claude）/ 未決 D3: 権限の階層・依存を持つか。
  - (a) 依存の表を持ち、ロールの保存で正規化する（例: `save_document` を付けると `edit_document` も付く、`edit_document` を外すと `save_document` も外れる）。DB の値は常に表に合う。ロールの編集画面は依存を見せる（JS で連動）。利点: 画面に見える値と実際の判定が一致する。欠点: 既存のロールで依存に合わないものを、アップグレードでどう扱うか決める必要がある（D6）。
  - (b) 依存の表を持ち、判定のときに解釈する（`save_document` を持てば `edit_document` を持つとみなす）。DB の値は今のまま。利点: マイグレーションが要らない。欠点: ロールの編集画面のチェックと、実際にできることが食い違う（外したはずの権限が効く）。アップグレードしただけで、カスタムのロールのできることが黙って増える。
  - (c) 平らなまま（依存を持たない）。ロールの編集画面で、依存に合わない組み合わせを保存するときに知らせるだけ。利点: 動きは変わらない。欠点: 「意味の通らない組み合わせ」が残り続け、D2 の (b) の組み合わせを表で表せない。
  - 影響: `use_udperms` と無関係。既定のロールは依存に合う（すべて、または文書の操作をすべて持つ）ので、(a)・(b) でも値は変わらない見込み（M0 で確かめる）。推奨: (a)。依存の向きは「書き換える権限（`save_*`・`delete_*`・`publish_document`・`move_document`）は、その対象を開く権限（`edit_*`）を要る」を基本にし、表は D1 の (b) の表に足す。

- 2026-11-11 / 計画（Claude）/ 未決 D4: `save_role` と、同じくアクセスの規則を自分で変えられる権限の扱い。今は `bypassesDocumentGroups()` が「`isAdmin()` または `save_role`」で、`save_role` を持つと文書グループの判定をすべて通る（管理画面の絞り込み・`checkPermissions()`・ルートに置けるか・公開側の `privateweb` の判定）。ただし Surprises のとおり、`save_user`（付けるロール・所属を保存する人の権限の範囲で確かめない）と `settings`（`use_udperms` を切れる）も、アクセスの規則を変えられる点で同じ。
  - (a) 今の定義を保ち、ロールの編集画面で「アクセスの規則を変えられる権限」（`save_role`・`save_user`・`access_permissions`・`web_access_permissions`・`settings`）に、持つと実質すべての権限を得られる旨を出す。利点: 動きは変わらない。欠点: `save_user` と `settings` を持つ利用者が文書グループで絞られる一方、`save_role` だけ特別、という説明しにくい形が残る。
  - (b) 権限を渡す操作を「自分が持つ権限の範囲まで」に制限する（ロールの保存は、自分のロールが持たない権限を付けられない・自分のロールは書き換えられない。利用者の保存は、自分のロールが持たない権限を含むロールを付けられない・自分のロールは変えられない）。自分の制限を外す道が `settings` だけになるので、`bypassesDocumentGroups()` を `isAdmin()` だけに戻せる。利点: 「管理者」の定義が 1 つの権限の組（`isAdmin()`）になり、`save_role` の特別扱いが消える。欠点: `save_role` を持ち `isAdmin()` でないカスタムのロールは、文書グループで絞られるようになり（`use_udperms` が有効なサイトの管理画面と、管理画面にログイン中の公開側の `privateweb`）、ほかのロールに自分より強い権限を付けられなくなる。`settings` の扱いは別に残る（設定の画面で `use_udperms` を `isAdmin()` だけが変えられるようにする、などを (b) に含めるか決める）。
  - (c) ロールに「管理者」の印（列 1 つ）を新設し、`isAdmin()`・`bypassesDocumentGroups()`・ロール ID 1 の特別扱い（D7）をすべてこの印で決める。アクセスの規則を変えられる権限は、この印のロールだけが持てる（保存で正規化）。利用者の保存でのロールの付与は (b) と同じ制限。利点: 「管理者」の意味がロールの編集画面で 1 つのチェックとして見える。欠点: 列の追加とマイグレーションが要り、既存のロールのどれに印を付けるか（D6）を決める必要がある。変更が最も大きい。
  - 影響: `use_udperms` が無効のサイトでは、管理画面の文書の絞り込みはもともと無いので、(b)・(c) で変わるのは、ロール・利用者の保存で付けられるものと、公開側で `privateweb=1` の文書（無効のサイトでは、有効だった時期の印か `DocAPI::create()` で付いたものだけ）の見え方。既定のロールは、1・2 が全部の権限、3 が `save_role`・`save_user`・`settings` を持たないので、(a)〜(c) のどれでも変わらない。推奨: (b)。理由は、`save_role` だけを特別にする理由を、権限を渡す操作の範囲をそろえることで消せるため。`settings` での `use_udperms` の切り替えは、(b) に含め、`isAdmin()` に限る案を推奨する。

- 2026-11-11 / 計画（Claude）/ 未決 D5: 管理側と Web 側の利用者・グループを統合するか。
  - (a) 統合しない（2 系統のまま）。1.4 では、文書と判定の置き場所（Web 側も ManagerAPI・DocumentParser の判定を使う）だけ整える。`allow_mgr2web` は今の意味のまま文書に書く。利点: 外部のスニペット（WebLogin など、`web_users`・`web_groups` を読むもの）を壊さない。欠点: 2 系統の説明と保守が続く。
  - (b) ユーザーグループだけ 1 系統にする（グループごとに「管理側で効く」「Web 側で効く」の印。利用者の表は別のまま）。`privatemgr`・`privateweb` の 2 つの印と、`membergroup_access`・`webgroup_access` の 2 つの表をまとめられる。利点: 文書グループの結びつきの画面が 1 つになる。欠点: 表の作り替えとマイグレーションが大きく、Web 側の表を直接読む外部のコードが動かなくなる。
  - (c) 利用者も 1 つの表にする（「管理画面にログインできるか」の印）。利点: 仕組みが 1 つになる。欠点: ログイン・セッション・WebLogin・パスワードの扱いまで作り直しになり、権限の見直しの範囲を大きく超える。
  - 影響: (a) は `use_udperms` の有効・無効のどちらでも動きは変わらない。(b)・(c) は Web 側のグループを使っていない無効のサイトでも、表の作り替え（マイグレーション）を通る。推奨: (a)。(b) をやるなら、この計画から外して別の Issue と ExecPlan にする。

- 2026-11-11 / 計画（Claude）/ 未決 D6: 既存のロールを、アップグレードでどう移すか（D3・D4 で決めた規則を、既存のサイトに当てる方法）。
  - (a) DB の値は変えず、新しい規則で判定する。規則に合わないロールは、設定の確認（`config_check.inc.php`）とアップグレードのログで知らせる。利点: マイグレーションが要らない。欠点: アップグレードしただけで、カスタムのロールのできることが変わる（D3 の (a) なら保存し直したときに初めて値が変わる）。
  - (b) マイグレーションで、今と同じことができる値に書き換える（依存で要る権限を明示的に付ける、など）。利点: アップグレードの直後に、できることが変わらない。欠点: 「今と同じ」を保つために、今は効いていない権限を付けることになる場合がある（例: D2 の (b) で削除に `save_document` を求めるとき、削除できていたロールに `save_document` を付けると、保存もできるようになる）。D4 の (b) の「文書グループを通る」を保つ方法は無い（`access_permissions` などを付けると、グループ管理まで開ける）。
  - (c) マイグレーションで新しい規則に合う値に直し（依存に合わない組み合わせは、権限を足さずに外す側で直す）、変えたロールと権限の一覧をアップグレードのログと設定の確認に残す。利点: 権限が増える向きの変更を自動で行わない（安全側）。欠点: できなくなる利用者が出る。
  - 影響: 既定のロールはどの案でも値が変わらない見込み（M0 で確かめる）。推奨: (c)。権限を自動で足すと、サイトの運用者が意図しない操作を許すことになるため、足さずに外し、何を外したかを必ず知らせる。外したことでできなくなる操作は、ログの一覧を見て運用者が戻せるようにする。

- 2026-11-11 / 計画（Claude）/ 未決 D7: ロール ID 1 の特別扱い（`permissions.md` の「管理者」の判定の節）。
  - (a) すべて `isAdmin()`（D4 の (c) なら管理者の印）に置き換える。利点: 「管理者」の判定が 1 つになる。欠点: ロール 1 を消せる・管理者でない利用者が見える、など今の守りが変わる（ロール 1 を削除させない、`isAdmin()` でない人にロール 1 のユーザーを変えさせない、は残す必要がある）。
  - (b) ロール 1 を「守るロール」として残し、扱いを 1 つのメソッド（例: `ManagerAPI::isProtectedRole($roleId)`）に集める。守る内容は、削除させない・管理者でない利用者に付け外しさせない・管理者でない利用者の一覧に出さない。モジュールのメニューなど「管理者なら全部」の判定は `isAdmin()` にそろえる。ロール切り替え（`:roleid=N`）は今のまま。利用者の一覧で隠す条件を、今の「ロール ID が 1 でなければ」から「`isAdmin()` でなければ」にする（ウェブマスターに、既に編集・削除できるロール 1 のユーザーが一覧に出るようになる）。利点: 今の守りを保ったまま、判定の書き方が 1 つになる。欠点: ID に頼る形は残る。
  - (c) ロールに「守る」の列を足し、ID に頼らない。利点: 運用者が守るロールを選べる。欠点: 列の追加とマイグレーションが要る。D4 の (c) と同時でないと二度手間。
  - 影響: `use_udperms` と無関係。推奨: (b)。D4 で (c) を選ぶなら (c)。

- 2026-11-11 / 計画（Claude）/ 未決 D8: サイトのインポート（#1404）。
  - 取り込み先の親: (a) Issue の案どおり、ルート以外の親を `parentChangeDeniedMessage([], $parent)` と同じ判定（文書グループ・許可するリソースの範囲・存在）で確かめ、だめなら取り込まずにエラーを出す。画面の親の選択（ツリーから選ぶ操作）は、選べない親でも選べたまま（処理で断る）。(b) (a) に加え、画面の親の選択でも断る（JS でツリーの選択を受けるので、選んだときに確かめる問い合わせが要る）。
  - 「初期化」（`reset`。`site_content` の全件の削除）: (a) 許可するリソースの範囲の設定が空（制限なし）で、文書グループで絞られない（`restrictsByDocumentGroups()` が偽）利用者だけに許す。画面でも、許さない利用者にはチェックを出さない。(b) 今のまま（`import_static` だけ）。
  - 影響: `use_udperms` が無効のときは `checkPermissions()` が常に真なので、変わるのは「許可するリソース」を設定した利用者だけ。既定のロールで `import_static` を持つのは 1・2（と古いサイトの Publisher）で、範囲を設定していなければ変わらない。推奨: 親は (a)、初期化は (a)。どちらも、範囲や文書グループで絞った利用者に `import_static` を与えたサイトで、これまで通った取り込みが断られる（意図どおりの変更）。

- 2026-11-12 / 本人 / 決定（D1〜D8）: 利用者から見て動きが変わる 4 点を本人が決め、動きが変わらない内部の 3 点（D1・D5・D7）は Claude が推奨のとおりに決めた。
  - D1: (b)。操作から必要な権限を引く表を ManagerAPI に置き、それを引く 1 つのメソッドで判定する（内部の論点）
  - D2: (c)。第 2 段階は、処理の今の条件を正にして、入口（ボタン・メニュー・右クリック）を合わせる。誰が何をできるかは変えない。権限の意味の決め直しは第 3 段階で、依存とロールの移し方とあわせて行う（本人）
  - D3: (a)。権限の依存の表を持ち、ロールの保存で値をそろえ、ロールの編集画面でも連動させる（本人）
  - D4: (b)。ロール・利用者を保存する人は、自分が持たない権限を人に渡せず、自分のロールは変えられない。そのうえで、文書グループの絞り込みを全部通るのは `isAdmin()` だけにする。設定の画面での `use_udperms` の切り替えも `isAdmin()` だけにする（本人）
  - D5: (a)。管理側と Web 側の利用者・グループは統合しない（内部の論点。統合するなら別の計画）
  - D6: (c)。アップグレードで、依存に合わないロールは権限を足さずに外す側で直し、外したロールと権限をアップグレードのログと設定の確認に出す（本人）
  - D7: (b)。ロール ID 1 は「守るロール」として残し、扱いを 1 つのメソッドに集める。「管理者なら全部」の判定は `isAdmin()` にそろえる（内部の論点）
  - D8: 取り込み先は (a)、初期化は (a)。取り込み先の親を、移動・保存と同じく許可するリソースと文書グループで確かめる。初期化（全件の削除）は、範囲の制限が無く文書グループでも絞られない利用者だけに許す（本人）
- 2026-11-12 / 本人 / 決定: 作り直さず、今の骨組み（ロール＝何の操作をできるか、文書グループ＝どの文書に触れるか）を残して整える。権限そのもの（約 70 個）を操作単位に整理し直す案は採らない（全サイトのロールの移し替えと、権限名を見るプラグインへの影響が大きいため）。目指す形は Purpose の 3 文。
  - これで M3〜M9 は決定待ちではなくなった。進める順は Plan of Work のとおり（M0 の記録 → M2 → M3・M4 → M5 → 第 3 段階）

- 2026-11-12 / M3（Claude）/ 決定（実装。動きの決め直しは含まない）: D1 の (b) の表は、ManagerAPI の private 定数 `OPERATION_PERMISSIONS`（操作の名前 → `['all' => [...]]`・`['any' => [...]]`）と、公開の `can(string $operation): bool`（知らない名前は `InvalidArgumentException`）にした。名前は `<対象>.<操作>`（`document.move` など）。入口が引く名前は、入口が実際に使う操作の単位だけを置く（編集 `document.edit`、保存 `document.save`、新規 `document.create`、複製 `document.duplicate`、移動 `document.move`、公開・非公開 `document.publish`、削除・元に戻す `document.delete`、エレメントの `element.<種類>.manage`・`.save`）。削除と元に戻すは処理の条件が同じなので 1 つにまとめた（分けると表が 2 か所になる。意味が分かれたら分ける）。M2 のメソッド（`canManageElement()`・`canOpenElementManager()`・`canSaveAnyElement()`）は、名前も戻り値も例外の文言も変えず、`ELEMENT_TYPES`（種類の一覧）と表を引く形にした（`ELEMENT_PERMISSIONS` は消した）。`DocAPI::can*Doc()` は、入口を表に切り替えると呼ぶ所が無くなったので、委ねる形にせず消した。`canCreateDocumentUnder()` は `can('document.create')` を見るので、メニュー・ダッシュボード・ツリー・一覧のルートの入口と QuickManager の追加ボタンは、D2 の (c) のとおり `new_document` と `save_document` の両方で出る。編集画面（a=27）を開く入口は `edit_document`（`document.edit`）のまま。

## Outcomes & Retrospective

## Context and Orientation

この計画で使う言葉:

- ロール: 管理ユーザーが 1 つ持つ、約 70 個の「できる・できない」の並び（`user_roles` の 1 行）。`hasPermission('xxx')` は、ログイン中の利用者のロールの `xxx` が 1 かを返す（`manager/includes/document.parser.class.inc.php:4156`、ヘルパーは `manager/includes/helpers.php:40`）。
- 入口: 操作を始める画面の部品（メニュー・ボタン・右クリックの項目・ダッシュボードのアイコン）。処理: 実際に変える部分（`manager/processors/`）と、画面を開く部分の先頭の確かめ（`manager/actions/` の先頭）。
- 文書グループ・許可するリソース・`use_udperms`・`isAdmin()`・`bypassesDocumentGroups()`: `assets/docs/permissions.md` が正。この計画では説明を繰り返さない。

正本は `assets/docs/permissions.md`（今の仕組み・判定の置き場所・残る課題の表）。第 1 段階（#1377）でこの文書を作った。各マイルストーンは、変えた判定をこの文書に反映する（SSOT）。

主なファイル:

- `manager/includes/extenders/ex_managerapi.php`: 判定の正（文書グループ・範囲・ルート・非公開の印）。この計画で権限の判定を足す場所
- `manager/includes/extenders/ex_docapi.php:625-671`: `DocAPI::canSaveDoc()` などの文書の操作の権限（`evo()->doc`）。`canSaveDoc()`・`canCreateDoc()`・`canDeleteDoc()`・`canCopyDoc()` は使われ、`canMoveDoc()`・`canPublishDoc()`・`canEditDoc()` はどこからも呼ばれない。`canPublishDoc()` は `new_document` を見るなど名前と中身が合わない
- 入口: `manager/frames/menu.php`・`manager/frames/tree.php`（右クリックの `item*()`）・`manager/actions/document/resources_list.static.php`（ボタン・右クリック・一括）・`manager/actions/document/document_data.static.php`・`manager/actions/document/mutate_content/action_buttons.php`・`manager/actions/main/welcome.static.php`・`manager/media/style/common/welcome.php`・`manager/actions/element/resources.static.php`・`manager/actions/permission/*_management.static.php`
- 処理: `manager/processors/document/*.php`・`manager/actions/document/move_document.dynamic.php`・`manager/processors/module/execute_module.processor.php`・`manager/processors/permission/save_user.*`・`manager/processors/permission/save_role.processor.php`
- 非公開の印: `ex_managerapi.php:777-879`。呼び出し元は `permissions.md` の「非公開の印」の節
- サイトのインポート: `manager/actions/tool/import_site.static.php`

DocumentParser への影響: M1 は `privateweb` の値を書く処理だけで、読む側（`prepareResponse()` の中の文書の取得とページキャッシュの読み込みの確認）は変えない。M7 で D4 の (b)・(c) を選ぶと、`managerBypassesDocumentGroups()`（同じく `prepareResponse()` の文書の取得・キャッシュの読み込みの確認と、取得の関数）の結果が変わる。ほかのマイルストーンは管理画面だけで、`executeParser()`・`parseDocumentSource()`・`postProcess()` には触れない。

キャッシュ: M1 は印の値そのものは変えないので、ページキャッシュの消し方は今のまま（Web 側のグループ管理と文書グループの削除が消す）。M7 で公開側の見え方が変わる場合は、`disable_cache_at_login`（既定 1）が 0 のサイトで管理者の出力がページキャッシュに入る既知の課題（`core-issues.md`「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」）を広げないことを確かめる。

前提: 対象のマイルストーンは 1.4。PR の base は `develop/1.4`。#1394（ルートでの新規作成の入口）は #1405 でマージ済みで、ツリー（`tree.php`）と文書一覧（`resources_list.static.php`）のルートの入口は `canCreateDocumentUnder(0)` に寄せ終わった。ルートに置けて範囲にもルートが入るかの判定は `ManagerAPI::canUseRootAsParent()` に 1 つにした。

非対象: 公開側の取得の関数ごとの `privateweb` の扱いの違い（`core-issues.md` の課題。D4 の結果を受けて別に扱う）。ファイルブラウザーの守る場所（`PathGuard`）の権限の表。権限の数を減らす・名前を変えること（D3 で必要になったら別に決める）。

## Plan of Work

進め方は「記録 → 動きを変えない寄せ集め → 決めたことに沿ってそろえる → 作りの見直し」。1 マイルストーン＝1 PR（base は `develop/1.4`）。決定待ちのマイルストーンは、その決定が出るまで着手しない。M1・M2 は決定を待たずに進められる。

第 2 段階:

- M0（PR なし）: 既定のロールとカスタムのロール、所属のある・ない利用者で、入口と処理の結果を記録する。以後のマイルストーンは、この記録との差が「期待した行だけ」であることで確かめる。
- M1（決定不要）: 非公開の印の計算を 1 回の UPDATE にする（Decision Log の最初の項）。`$docid` は `(int)` にそろえ、0 以下は全件（今の意味）、`setMgrDocsAsPrivate()` にも `DISTINCT` 相当を入れる。印の値は今と同じで、計算の途中の状態が見えなくなるだけ。
- M2（決定不要。動きは変えない）: 呼び出し元の条件が一致している判定だけを、意味の名前の ManagerAPI のメソッドに寄せる。対象は、`isAdmin()` の書き写し 2 か所、文書グループの衝突の知らせの条件 2 か所、エレメントの種類ごとの「管理を開けるか」（`new_X` または `edit_X`。`resources.static.php` のタブと右クリック、メニュー）と「どれかを保存できるか」（カテゴリの削除と `resources.static.php:264-268`）、`checkPermissions()` の前の重ねた `use_udperms == 1`（設定が 0・1 のとき結果が同じことを確かめてから外す）。食い違っている呼び出し元（ダッシュボードのエレメントのアイコンなど）は M2 では触らず、M4 に回す。
- M3（D1・D2）: 文書の操作の権限の組み合わせを 1 か所にし（D1 の形）、入口と処理を D2 で決めた向きにそろえる。`DocAPI` の `can*Doc()` は、ManagerAPI に委ねる形にするか、使われていないものを消す（外部のコードが `evo()->doc->can*Doc()` を呼ぶ可能性は低いが、消すなら PR に書く）。
- M4（D1・D2）: 文書以外の入口をそろえる。モジュール（メニューの判定を実行の処理と同じ `isAdmin()` とグループの判定に。D2 の補足）、Web ユーザーの一覧の右クリック、ロール・ユーザー・Web ユーザーの管理の入口、ダッシュボードのエレメント、TV の複製。
- M5（D8）: サイトのインポートの取り込み先の親と初期化。

第 3 段階:

- M6（D3・D6）: 依存の表、ロールの保存での正規化、ロールの編集画面での連動、既存のロールのマイグレーション（D6 の方法）。
- M7（D4・D6）: 権限を渡す操作の制限（ロールの保存・利用者の保存）、`bypassesDocumentGroups()` の定義の見直し、`settings` での `use_udperms` の切り替えの扱い、マイグレーション。
- M8（D7）: ロール ID 1 の扱いを 1 つのメソッド（または列）に集める。
- M9（D5）: (a) なら `permissions.md` の Web 側の節と `allow_mgr2web` の説明を整えるだけ。(b)・(c) なら別の ExecPlan に分ける旨を記録して閉じる。
- M10: PHP 7.4・MySQL 5.7 での確認と、`permissions.md`・`architecture.md`・`core-issues.md` の仕上げ。

マイグレーションを伴うマイルストーン（M6・M7・D4 (c)・D7 (c)）は、`assets/docs/migrations.md` に従い、`php evo make:migration` で作る。各 PR は、変えた判定を `permissions.md` の該当の節と「分かっている課題」の表に反映する。

## Concrete Steps

作業ディレクトリは、各マイルストーンのブランチの作業ツリー（段階ごとのブランチは、Issue に紐づけずに `git switch -c <ブランチ名> origin/develop/1.4` で作る。紐づけたブランチの PR はマージで Issue を閉じるため。#1404 は M5 で `gh issue develop 1404 --base develop/1.4`）。コンテナの操作はホストから `docker compose -p evo-validate exec app ...`（`adaptive-validation-runner` スキルの `references/environment.md`）。

M0: 修正前の記録

1. 検証環境を `adaptive-validation-runner` の手順で `develop/1.4` に立て、`scripts/smoke.js` が全 PASS することを確かめる。
2. ロールを用意する。既定の 1・2・3 に加え、カスタムのロールを管理画面（a=38）で作る: `R_mv`（3 から `publish_document` を外す）、`R_pub`（`publish_document` だけ）、`R_del`（`delete_document` だけ）、`R_new`（`new_document` だけ）、`R_noedit`（3 から `edit_document` を外す）、`R_saverole`（3 に `edit_role`・`save_role`）、`R_saveuser`（3 に `edit_user`・`save_user`）、`R_chunk`（`new_chunk`・`edit_chunk`・`save_chunk`）、`R_webuser`（`edit_web_user`・`delete_web_user`）、`R_roledel`（`delete_role` だけ）、`R_mod`（`exec_module` だけ）、`R_import`（3 に `import_static`）。ロールの編集画面でオンに固定の 8 個は全ロールで 1。
3. 利用者を用意する: `admin`（ロール 1）、`webmaster`（2、所属なし）、`editor`（3、管理側のユーザーグループ G1 に所属）、`editor0`（3、所属なし）、カスタムのロールごとに 1 人（所属なし。`R_saverole`・`R_saveuser`・`R_mod` は G1 所属の人も）。`R_import` の利用者は「許可するリソース」をフォルダ F に絞った人も作る。
4. データ: フォルダ F とその子、文書グループ D1（G1 に結びつける）・D2（G1 以外のグループ G2 に結びつける）、D1・D2 をそれぞれ付けた文書、モジュール X1（グループの制限なし）・X2（G1 に制限）・X3（所属者の無いグループ G3 に制限）。設定は V0（`use_udperms=0`、既定）と V1（`use_udperms=1`）の 2 通り。
5. 記録のスクリプトを作業用ディレクトリ（リポジトリの外）に置き、`scripts/lib.js` のログインのヘルパーで各利用者×V0・V1 について次を取って TSV に残す。入口: メニュー（a=1&f=menu）の項目、ツリーの右クリックの項目（`tree.php` の出力）、一覧 a=120（F と 0）のボタン・右クリック・一括のボタン、文書の情報 a=3 のボタン、編集画面 a=27 のボタン、ダッシュボード a=2 のアイコン、エレメントの管理 a=76 のタブ、ユーザー a=75・Web ユーザー a=99・ロール a=86・モジュール a=106 の一覧の表示と右クリック。処理: a=51・52・61・62・6・63・94・121（各 op）・112（M1〜M3）の応答（成功・エラー 3・`access_permission_denied` など）。処理を試した文書は、毎回 DB のダンプから戻す（`php evo db:export` / `db:import`）。
6. あわせて、Surprises の要確認を確かめる: 利用者の保存の処理が、付けるロールを保存する人の権限の範囲で確かめるか（M0 では記録だけ。そろえるのは M7）。

期待: Surprises の食い違いが、表のとおりに観測される（例: `R_mv` はツリーと一覧のボタンに「移動」が出て、押すと a=51 がエラー 3。`R_pub` は右クリックに「公開」が出て、a=61 がエラー 3。`R_del` は右クリックの「削除」で a=6 が通り、一覧のボタンには「削除」が出ない）。既定のロールの利用者は、どの入口でも出た項目が通る。

M1: 非公開の印

1. 編集: `ex_managerapi.php` の `setMgrDocsAsPrivate()`・`setWebDocsAsPrivate()`。結びつきの一覧への LEFT JOIN の 1 文の UPDATE にする（Decision Log の 2026-11-12 の項）。`$docid` は `(int)` にし、0 以下は全件。戻り値は Decision Log のとおり。
2. 確認のコマンド（前後で同じになること）:
   docker compose -p evo-validate exec app php evo db:query "SELECT id, privatemgr, privateweb FROM evo_site_content ORDER BY id"
   を、グループ管理での結びつきの追加・削除、文書グループの削除、文書の保存（V1）、QuickManager の保存、マイグレーション `20261111_120000_delete_orphan_document_group_rows` の再実行のそれぞれの後に取り、M0 の同じ操作の後の結果と一致すること（表のプレフィックスは環境に合わせる）。
3. 間が無くなったことの確認: 文書を約 2 万件にして（`php evo` かSQL で複製）、片方のシェルで全件の計算（Web 側のグループ管理で結びつきを付け外し）を繰り返し、もう片方で `SELECT COUNT(*) … WHERE privateweb=1` を回し続ける。修正前は計算の途中で件数が 0 に落ちる回が出て、修正後は一度も落ちない。人工的に処理を止めず、件数を増やして遅くした実際の条件で見る（スキルの Workflow 4 の 5）。
4. MariaDB の general log で、1 回の計算の UPDATE が印ごとに 1 文であることを確かめる。

M2: そろっている判定を寄せる

1. 編集: `ex_managerapi.php` に意味の名前のメソッドを足す（例: `canManageElement($type)`・`canSaveAnyElement()`・`documentGroupCollision()`。名前は実装時に決め、docblock に使う場所を書く）。呼び出し元: `mutate_content/functions.php:296`・`dm_backend.class.php:350` を `isAdmin()` に、`mutate_content.dynamic.php:107-113`・`mutate_draft.dynamic.php:110-114` を衝突のメソッドに（今の式が使う `$permissions_yes`・`$permissions_no` のグローバルが、`documentGroupAccess()` の `yes`・`no` と同じ値であることを先に確かめる。要確認）、`resources.static.php` のタブと右クリックの組み合わせと `menu.php:272-275` をエレメントのメソッドに、`delete_category.processor.php:5-11` と `resources.static.php:263-269` を保存のメソッドに、`save_resource.processor.php:452,478,487` の重ねた `use_udperms` を外す（移動の処理の分は #1394 で `parentChangeDeniedMessage()` に寄せて消えた）。
2. 確認: M0 の記録を取り直し、M0 と差が 0 であること（V0・V1、全利用者）。
3. `permissions.md` の「判定の置き場所」に足したメソッドを書く。

M3〜M5: 決定が出たら、Decision Log の結論に合わせて手順を書き足す。共通の手順は、(1) 決めた組み合わせを Validation の表に「変わる行」として書く、(2) 実装、(3) M0 の記録を取り直し、M0 との差が表の「変わる行」だけであること、(4) `permissions.md` の反映。M3 の対象の入口と処理は Surprises の文書の操作の 6 項目、M4 は文書以外の 5 項目、M5 は `import_site.static.php` の `run()`（親の確認は、#1394 で足したルートの判定（`canUseRootAsParent()`）の分岐の隣に置き、初期化の削除より前に止める）。M3 の手順には次の 2 つも含める: M2 で足した `ELEMENT_PERMISSIONS`（エレメントの種類 → new・edit・save の権限名）を、操作から必要な権限を引く表に取り込む。表は「どれか 1 つ」（OR）と「全部」（AND）の両方を表せるようにする（エレメントの管理は new または edit の OR、文書の移動は move・save・publish などの AND）。

M6〜M9: 決定が出たら同じ形で書き足す。マイグレーションを作るときは `docker compose -p evo-validate exec app php evo make:migration <名前>` で作り、`php evo migrate` と `php evo migrate:status` で 1 回だけ当たること、もう一度実行しても何も変えないことを確かめる。アップグレードは `scripts/upgrade-fixture.js` で、古いサイトのロール（Editor・Publisher）を含むデータから上げて確かめる。

M10: PHP 7.4（`references/environment.md` の PHP 7.4 の節）と、DB を `mysql:5.7` の公式イメージにした環境で、M0 の記録の V0・V1 を admin・webmaster・editor・editor0・`R_mv`・`R_saverole` について取り直し、PHP 8 側の最後の記録と一致すること。M1 の UPDATE（結びつきへの JOIN）が MySQL 5.7 で通ること（M1 で 5.7・8.0 は確認済み）。

推奨のコミット単位（各 PR の中）: 判定のメソッドを足すコミット（`refactor(manager): 〇〇の判定を ManagerAPI に寄せる`）、呼び出し元を置き換えるコミット、`permissions.md` のコミット（`docs(permissions): 〇〇の判定の置き場所を書く`）。M1 は `fix(manager): 非公開の印を 1 回の更新で計算し直す`。

## Validation and Acceptance

M0 の記録と比べる。「同じ」は M0 と一致すること。各マイルストーンは、表に書いた行だけが変わり、それ以外は M0 と同じであること。V0（`use_udperms=0`、既定）の行は、ロールの権限の食い違いをそろえる行を除いて、すべて M0 と同じでなければならない（オフの動きを変えない）。PHP の警告・JS のエラーは 0 件（`php evo log:search "" --level=error`、ブラウザのコンソール）。

| マイルストーン | 確かめること | 期待 |
| --- | --- | --- |
| M1 | 各操作の後の `privatemgr`・`privateweb` の値 | M0 と同じ |
| M1 | 約 2 万件での全件の計算の途中の `privateweb=1` の件数 | 一度も 0 に落ちない（M0 では落ちる回がある） |
| M2 | M0 の記録のすべての行（V0・V1、全利用者） | M0 と同じ（差 0） |
| M3（D2 が (a)・(c) の場合） | 各ロール・入口の出し分け | 下の「M3 の変わる行」の行だけが変わる |
| M3（同） | 各利用者の処理 a=51・52・61・62・6・63・94・121・5 の応答 | M0 と同じ（処理は変えない） |
| M3（同） | 既定のロール（admin・webmaster・editor・editor0）のすべての行 | M0 と同じ |
| M4（D2 が (a)・(c) の場合） | webmaster（所属なし）のメニューのモジュール | X1〜X3 がすべて出る（M0 は X1・X3 だけ。X3 は所属者の無いグループなので出る。実行は M0 から X1〜X3 とも通る） |
| M4（同） | `R_mod`（所属なし）と G1 に所属する `R_mod` の利用者の、メニュー・ダッシュボードのモジュールと a=112 の応答 | 出るモジュールと実行できるモジュールが一致する（X3 は M0 ではメニューに出て実行で断られる。M4 では出ない） |
| M4（同） | `R_webuser` の Web ユーザーの一覧の右クリックの「編集」「削除」 | 有効（M0 は無効） |
| M4（同） | `R_roledel` のメニューの「ロールの管理」 | 出ない（M0 は出て、開くとエラー 3） |
| M4（同） | `R_chunk` のダッシュボードのエレメントのアイコン | 出る（M0 は出ない。メニューには出る） |
| M5（D8 が推奨の場合） | `R_import`（範囲を F に絞った人）の F の外への取り込み | `access_permission_parent_denied` で止まり、文書は増えない |
| M5（同） | 同じ人の初期化 | チェックが出ず、POST しても `site_content` が消えない |
| M5（同） | admin・webmaster の取り込みと初期化 | M0 と同じ |
| M6〜M9 | 決定後に書き足す | 既定のロールの行は M0 と同じ。マイグレーションで変えたロールは、ログと設定の確認に一覧が出る |
| M10 | PHP 7.4・MySQL 5.7 での取り直し | PHP 8 側の最後の記録と同じ、エラー 0 |

### M3 の変わる行

実装（表のコミット 89f2462b0 以降）に合わせて確定した。権限の判定はロールの権限だけで決まり、`use_udperms` に関係しないので、V0・V1 のどちらでも同じ（V1 では、文書グループ・範囲の面で元から出ない行は元から出ない）。利用者は M0 のロール名。記録は、入口の HTML から項目を取り出す方法（M0 の記録と同じ）。Docker で、変更前（cd9ffc54c）と変更後を同じ DB で取り、既定のロール（admin＝ロール 2・editor＝ロール 3）の全項目が同じで、カスタムのロール 5 つの差がこの表と一致することを確かめた。

| 利用者 | 入口 | M0（変更前） | M3 |
| --- | --- | --- | --- |
| `R_mv`（`publish_document` なし） | ツリーの右クリック「移動」（a=51） | 出る（HTML。画面上は JS の `permpub` で元から隠れる） | 出ない |
| `R_mv` | 一覧のボタン「移動」・一括の「移動」・文書の情報「移動」・編集画面（a=27）の「移動」 | 出る（押すと a=51 がエラー 3） | 出ない |
| `R_pub` | ツリーの右クリック「公開」「非公開」・ツリーの `permpub`・区切り線 | 出る・1 | 出ない・0 |
| `R_pub` | 一覧の右クリック「公開」「非公開」 | 出る（押すとエラー 3） | 出ない |
| `R_del` | 一覧のボタン「削除」・文書の情報「削除」・編集画面（a=27）の「削除」 | 出ない（右クリックからは通る） | 出る |
| `R_new`（`new_document` だけ） | ツリーの右クリック「新規（a=4）」「ウェブリンク（a=72）」・ツリーの新規ボタン | 出る（保存で断られる） | 出ない |
| `R_new` | メニュー「リソースを投稿」「ウェブリンクを作成」・ダッシュボードの新規アイコン | 出る | 出ない |
| `R_new` | 一覧の作成ボタン（子の一覧・ルート） | 出る | 出ない |
| `R_new` | 一覧の右クリック「作成」「複製」「ウェブリンク」 | 出る（複製は押すとエラー 3） | 出ない |
| `R_new` | 新規作成画面（a=4・72）の保存ボタン | 出る（押すと a=5 が断る） | 出ない（画面そのものは開ける） |
| `R_noedit`（`edit_document` なし） | ツリーの右クリック「移動」 | 出る（押すと a=52 がエラー 3） | 出ない |
| `R_noedit` | 一覧のボタン「編集」「移動」・一括の「移動」・一覧の右クリック「移動」・文書の情報「編集」「移動」・編集画面（a=27）の「移動」 | 出る | 出ない |

上記以外（編集画面の保存・複製・削除、ツリーと一覧の編集・複製・削除・公開・非公開、既定のロールのすべて）は変わらない。`R_saverole`・`R_saveuser`・`R_import` は文書の権限が投稿担当者と同じ、`R_chunk`・`R_webuser`・`R_roledel`・`R_mod` は文書の権限が無いので、論理上は変わらない（この確認では取っていない）。QuickManager の追加ボタンも `canCreateDocumentUnder()` を通るので、`R_new` では出なくなる（公開側の画面で、この確認では取っていない）。

## Idempotence and Recovery

M0 の記録のスクリプトとデータ（DB のダンプ）は作業用ディレクトリに残し、各マイルストーンで同じものを使う。処理を試すたびに `php evo db:import` で M0 の状態に戻すので、何度でもやり直せる。中断したら、Progress の最後の完了項目のブランチを `develop/1.4` に追いつかせ、M0 の記録を取り直して差が前回と同じことを確かめてから続ける。マイグレーションは、もう一度実行しても値を変えない形で作り（既に正規化されたロールは触らない）、`php evo migrate:status` で 1 回だけ記録されることを確かめる。

## Artifacts and Notes

- Issue: #1357（この計画）、#1404（M5）、#1397 の自己レビューの残り（M1）、#1394（#1405 でマージ済み。ルートの入口は済んだ）
- 正本: `assets/docs/permissions.md`。前の計画: `.agent/plans/2026-11-10-docgroup-admin-bypass.md`（文書グループの判定を ManagerAPI に寄せた #1338。記録と比べる確かめ方の見本）
- 計画時に `assets/docs/core-issues.md` に足す候補（この下書きでは書いていない）: 利用者の保存が、付けるロールを保存する人の権限の範囲で確かめないこと（M7 で扱う）、モジュールのグループの絞り込みが `use_udperms` の無効でも見えないまま効くこと（M4 で扱う）
- ブランチ名は着手時に作り、ここに書く。段階ごとのブランチは Issue に紐づけず `origin/develop/1.4` から作る
- M1: ブランチ `fix/1357-m1-private-flags`、PR #1407
- M2: ブランチ `refactor/1357-m2-permission-helpers`
- M3: ブランチ `refactor/1357-m3-document-operations`。表は `ManagerAPI::OPERATION_PERMISSIONS`、判定は `can()`。確認は、権限をスタブにした単体確認（php:7.4-cli。表の全項目で、`all` は 1 つ欠けると偽、`any` はどれか 1 つで真、未知の名前は例外、M2 のメソッドの動きは同じ）と、Docker（名前 `evo-m3check`）での入口の記録の前後比較（既定のロール 2 つとカスタムのロール 5 つ、ツリー・メニュー・一覧・文書の情報・編集画面・新規作成画面・ダッシュボード）
- M0 の記録: 16,841 行（利用者×V0・V1 の入口と処理）。スクリプトと出力はリポジトリの外のジョブの一時ディレクトリにある（リポジトリには置かない。取り直すときは Concrete Steps の M0 の 5 で作る）。観測された食い違いは Surprises のとおり。新しく見つかった食い違い: モジュールの実行のグループの判定が働いていなかった（#1408）、エレメントの管理（a=76）はエレメントの権限が無くても開く、新規作成の権限だけのロールは入口がすべて出るのに保存で断られる。

## Interfaces and Dependencies

- `ManagerAPI`（`manager()`）: 足すメソッドは公開のメソッドとし、モジュール・プラグインからも呼べる。既存の `isAdmin()`・`bypassesDocumentGroups()`・`canCreateDocumentUnder()` などの意味は、M7（D4）まで変えない。
- `DocAPI`（`evo()->doc`）: `can*Doc()` を M3 で整理する。同梱では `action_buttons.php` と `resources_list.static.php` だけが呼ぶ。
- `DocumentParser::checkPermissions()`・`managerBypassesDocumentGroups()`: M7 まで変えない。
- 同梱のモジュール・プラグイン: DocManager（`dm_backend.class.php`。M2）、QuickManager（`assets/plugins/qm/`。M1 の印の計算、M7 の管理者の判定）、ManagerManager（`mm.inc.php:86` がロール ID を読む。M8 で確かめる）、WebLogin（`assets/snippets/weblogin/`。M9）。
- DB: M1 は結びつきへの JOIN の UPDATE（MariaDB 11・MySQL 5.7・8.0、MyISAM で確かめた。5.5 は未確認で M10 で扱う）。M6・M7（・D4 (c)・D7 (c)）はマイグレーション。
- 依存するタスク: 「文書グループの絞り込みを受けない管理者の判定をそろえる」（#1338 ほか。develop/1.4 に入っている）、#1394（#1405 でマージ済み）。
