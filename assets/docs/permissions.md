# 権限と文書グループ

管理画面の権限（ロール・「管理者」の判定・文書グループ・許可するリソース）の今の仕組みと、判定を置く場所の決まり。コードを読んで確かめた事実だけを書く。作り直しの方針は #1357 で決める。

細かい規則（引数・戻り値・境界の扱い）は各メソッドの docblock を正とし、この文書には複製しない。この文書は、どの判定がどこにあり、何を正とするかの地図にあたる。

## 全体の見取り図

| 層 | 決めること | 正 |
| --- | --- | --- |
| ロール | 管理画面で何の操作ができるか（約 70 個の真偽） | `user_roles` の列、セッション `mgrPermissions` |
| 文書グループ | どの文書を開けるか（管理側）、公開側で見えるか（Web 側） | グループの結びつきの表（`document_groups`・`membergroup_access`・`webgroup_access` など） |
| 許可するリソース | 利用者ごとに、どの文書の下を編集してよいか | 利用者の設定 `allowed_parents` |

3 つは別々の仕組みで、それぞれ自分で判定する。文書を開いて編集するには、ロールの権限・文書グループ・許可するリソースの 3 つを（設定しているものすべて）通る必要がある。ただしロールの権限は 1 か所で文書グループとつながっていて、`access_permissions` と `web_access_permissions` を両方持つか、`save_role` を持つロールは、文書グループの絞り込みを外す（次の「管理者」の判定）。

## 管理ユーザーの権限

### ロール

管理ユーザーは `user_attributes.role` で 1 つのロール（`user_roles` の 1 行）を持つ。ロールの列は次のとおり。

- `id`・`name`・`description`
- 権限の列 69 個（`int(1)`、1 が許可。`save_password` だけ `int(11)`。定義は `install/sql/create_tables.sql`）

権限はロールごとの平らな真偽の並びで、権限どうしの関係（階層・依存）は持たない。ある権限を持っても、関連する別の権限が自動で付くことはない。

ロールの編集画面（`manager/actions/permission/mutate_role.dynamic.php`）は、権限を機能ごとの群に分けて並べる。保存（`manager/processors/permission/save_role.processor.php`）は、送られた値が 1 のものだけを 1、それ以外を 0 にして書く。完全な一覧の正は、この編集画面とテーブルの定義で、この文書には全部を列挙しない。

- 画面では、`frames`・`home`・`logout`・`action_ok`・`error_dialog`・`about`・`credits`・`view_document` の 8 個は、オンで固定されている（外せない）
- `edit_parser`・`save_parser`・`manage_metatags`・`edit_doc_metatags` の 4 個は、編集画面に出ない。どのコードも値を読まない（`edit_parser`・`save_parser` は、ロールの保存が送られない値として 0 に書き直す）

### 主な権限の意味

| 群 | 代表の権限 | 意味 |
| --- | --- | --- |
| 文書 | `view_document` | 文書の編集画面（a=27）・ドラフトの画面（a=131・132）・文書の情報・リソース一覧・ツリーのメニューを開く。画面の表記は「リソースデータの表示」で、ロールの編集画面ではオンで固定 |
| | `new_document`・`edit_document`・`save_document` | 文書の作成・編集・保存。ツリーのメニューなどが `edit_document` を、保存の処理が `save_document` を見る |
| | `move_document`・`publish_document`・`delete_document`・`empty_trash` | 移動・公開の切り替え・削除・ごみ箱を空にする |
| | `view_unpublished`・`view_schedule` | 未公開の文書を見る・予約の一覧 |
| エレメント | `new_template`・`edit_template`・`save_template`・`delete_template` | テンプレート。スニペット・チャンク・プラグイン・モジュールも、それぞれ `new_*`・`edit_*`・`save_*`・`delete_*`（モジュールは実行の `exec_module` も） |
| 利用者 | `new_user`・`edit_user`・`save_user`・`delete_user` | 管理ユーザー。Web ユーザーは `*_web_user` |
| | `access_permissions` | 管理側のグループ管理（ユーザーグループと文書グループの結びつき、所属）。ロールの編集画面の表記は「所属グループ」、メニューは「グループ管理」（画面 a=40 の見出しは「グループ管理(管理操作グループ)」） |
| | `web_access_permissions` | Web 側のグループ管理。ロールの編集画面の表記は「ウェブアクセス許可」、メニューは「グループ管理(ウェブ)」 |
| ロール | `new_role`・`edit_role`・`save_role`・`delete_role` | ロールの作成・表示・保存・削除。`save_role` は特別（後述） |
| 設定・保守 | `settings` | グローバル設定 |
| | `file_manager`・`bk_manager`・`import_static`・`export_static` | ファイル管理・バックアップ・静的 HTML の取り込みと書き出し |
| | `logs`・`view_eventlog`・`delete_eventlog`・`remove_locks` | ログの表示・イベントログ・ロックの解除 |
| その他 | `help`・`change_password`・`save_password`・`empty_cache` | ヘルプ・パスワードの変更・キャッシュの更新 |

`save_role` は、ロールの編集画面で任意のロールの権限を書き換えられる。自分のロールも書き換えられるので、持てば実質的にほかのすべての権限を付けられる。画面ではほかの権限と同じ 1 つのチェックに見える（「管理者」の判定で特別扱いする理由は次の節）。

### 既定のロール

インストール直後（`install/sql/default_settings.sql` と `install/sql/default_settings_custom.sql`）のロールは次のとおり。

| id | 名前 | 権限 |
| --- | --- | --- |
| 1 | `Administrator`（管理者） | すべて |
| 2 | ウェブマスター | すべて。ロールを編集できるので、必要に応じて自分を昇格できる。最初に作る管理ユーザーのロール |
| 3 | 投稿担当者 | 文書の作成・編集・保存・移動・公開・削除、未公開の文書を見る、予約、キャッシュの更新、ファイル管理、チャンクの編集・保存、モジュールの実行、ロックの解除など。利用者・ロール・グループ・設定の権限は無い（`access_permissions`・`web_access_permissions`・`save_role` も無い） |

古いサイト（`install/sql/default_settings_custom.sql` を使わなかった、またはその前に作ったサイト）では、id 2 が `Editor`、id 3 が `Publisher` のことがある。`Editor` の権限は投稿担当者と同じ。`Publisher` は、`Editor` に利用者・Web ユーザー・テンプレート・設定・バックアップなどを加えたもので、`access_permissions`・`web_access_permissions`・`save_role` は無い。

アップグレードのとき、`user_roles` に権限の列が足されると、そのときの値は id 1 のロールにだけ 1 で入る（`manager/includes/migrations/00000000_baseline.php`）。id 1 が無ければ作る。

### 権限の読み方（hasPermission）

`hasPermission($key)`（ヘルパー）と `evo()->hasPermission($key)` は、セッションの `mgrPermissions[$key] == 1` を返す。`mgrPermissions` は、ロールの行（`ManagerAPI::getMgrPermissions($role)`。ロールが無ければ空）をそのまま持つ。ログインしていなければ false。`$key` を省くと、セッションの内容を文字列にして返す。

- 管理画面へのログイン時にセットする（`manager/processors/login.processor.functions.php` と、`DocumentParser::token_auth()` のログイン処理）
- 管理画面のリクエストごとに `manager/includes/accesscontrol.inc.php` が `refreshMgrPermissions()` で読み直す。ロールを変えると、ログインし直さなくても次のリクエストから反映される
- 公開側でも、管理画面にログインしているセッションなら同じ値が読める

## 「管理者」の判定

「管理者」という語が指す範囲は、場所によって 2 つある。

| 判定 | 定義 | 使う場所 |
| --- | --- | --- |
| `ManagerAPI::isAdmin()` | `access_permissions` と `web_access_permissions` の両方を持つ | バックアップの復元・ファイルブラウザの保護する場所（`PathGuard`）・モジュールの実行・ユーザーの削除・ロック済みモジュールの編集・ユーザー保存の確認・文書グループを付け外しできる範囲（`documentGroupAccess()`）など。アクセスの規則（ユーザーグループ）を扱える人 |
| `ManagerAPI::bypassesDocumentGroups()` | `isAdmin()` または `save_role` | 文書グループの絞り込みを受けない人。本体（DocumentParser・ManagerAPI）の文書グループの判定が使う |

`save_role` を入れる理由は docblock にある（自分のロールに 2 つの権限を付けて `isAdmin()` になれるため、`save_role` だけのロールを絞っても守りにならず、実質の最上位の権限になる）。

`isAdmin()` は、文書グループの絞り込みの判定には使わない（docblock にもある）。

ロールの ID が 1 かどうかで文書グループの絞り込みを外す場所は、本体（DocumentParser・ManagerAPI）には無い。同梱の Ditto・Wayfinder の管理画面側の分岐も、`documentAccessCondition()` を使う。

ロールの ID 1 を特別扱いする場所は、文書グループとは別にある。主なものは次のとおり。

- ユーザーの一覧は、ロール 1 でない人にロール 1 のユーザーを出さない（`manager/actions/permission/user_management.static.php`）
- ユーザーの保存・削除は、`isAdmin()` でない人がロール 1 のユーザーを変える・消すのを止める（`manager/processors/permission/save_user.processor.php`・`manager/processors/permission/delete_user.processor.php`）。ユーザー保存の `verifyPermission()`（`manager/processors/permission/save_user.functions.php`）も、ロール 1 を指定するときに `edit_role`・`save_role`・`delete_role`・`new_role` を要求する
- ロールの削除は、ロール 1 を削除させない（`manager/processors/permission/delete_role.processor.php`）
- 設定の確認（`manager/includes/config_check.inc.php`）は、ロール 1 でログインしていると警告を出す
- メニューのモジュール一覧は、ロール 1 でなければ実行できるモジュールだけに絞る（`manager/frames/menu.php`）
- ログインのとき、ユーザー名に `:roleid=N` を付けると、DB のロールが 1 のユーザーはロール N（試したいロールの確認用）として入る（`manager/processors/login.processor.functions.php`）。選んだロールはセッション `mgrForceRole` に残り、ログアウトまで保つ。判定は `ManagerAPI::resolveMgrRole()` で、リクエストごとの読み直し（`refreshMgrPermissions()`）もこれを通す。DB のロールが 1 でなくなったら捨てて DB のロールにする。存在しないロールを指定すると、認証が通った後でログイン失敗にする。`auth_token` でのログインは、残った差し替えを消す
- アップグレード（`manager/includes/migrations/00000000_baseline.php`）は、足した権限の列をロール 1 にだけ 1 で入れ、ロール 1 が無ければ作り、メールの送信元が初期値（you@example.com）のままのときだけ、ロール 1 のユーザーのうち ID が最も小さい人のアドレスにする

## 文書グループ

利用者のグループと文書をグループで結びつけて、文書を開ける人・見える人を絞る仕組み。管理側（管理ユーザー）と Web 側（公開側の Web ユーザー）が別々の表を持つ。

### 表

| 表 | 内容 |
| --- | --- |
| `documentgroup_names` | 文書グループの名前。列 `private_memgroup`・`private_webgroup` は、管理側・Web 側のユーザーグループに結びつけられているかの印（後述） |
| `document_groups` | 文書（`document`）と文書グループ（`document_group`）の結びつき |
| `membergroup_names` | 管理側のユーザーグループの名前 |
| `member_groups` | 管理ユーザー（`member`）とユーザーグループ（`user_group`）の結びつき |
| `membergroup_access` | 管理側のユーザーグループ（`membergroup`）と文書グループ（`documentgroup`）の結びつき（アクセスを許す） |
| `webgroup_names`・`web_groups`・`webgroup_access` | 上の 3 つの Web 側。`web_groups` は Web ユーザー（`webuser`）とユーザーグループ（`webgroup`）の結びつき |
| `site_tmplvar_access` | TV（`tmplvarid`）と文書グループ（`documentgroup`）の結びつき。TV のアクセスを絞る。絞る相手は文書と同じ（`restrictsByDocumentGroups()`）だが、届く条件は「`site_tmplvar_access` に行が無い、または所属のグループの行がある」で、`membergroup_access` の有無は見ない（文書の非公開の規則とは違う） |

アクセスを許す結びつき（`membergroup_access`・`webgroup_access`）が 1 つでもある文書グループは、その側で「非公開」になる。結びつきの無い文書グループは、その側では何も絞らない。

### 設定

| 設定 | 意味 |
| --- | --- |
| `use_udperms` | グループ管理機能を使う。無効（既定）のとき、管理画面の文書グループの絞り込みはなく、グループ管理の画面はメニューに出ない。Web 側（a=91）は直接開いても入れず、管理側（a=40）は開けるが無効の旨を出す。文書の保存も印を計算し直さない |
| `udperms_allowroot` | 管理者でない利用者が、ルート直下に文書を置いてよいか（`isAllowroot()`）。`use_udperms` が有効なときだけ効く（設定画面にも有効なときだけ出る）。無効なら誰でもルートに置ける（`canPlaceAtRoot()`。`new_document` の権限と許可するリソースの範囲は別に見る） |
| `tree_show_protected` | 開けない文書を、ツリーと子の一覧に印つきで出すか。表示だけの設定で、アクセスの判定には使わない。親の選択・検索・取得の関数は、この設定を見ずに絞る |
| `allow_mgr2web` | 公開側で、管理画面にログイン中の利用者を Web ユーザーでもあるとして扱う。具体的には、`DocumentParser::getUserDocGroups()` が公開側の所属に、管理側の所属（`mgrDocgroups`）を足す |

設定の画面は、グローバル設定の「セキュリティ」の項（`manager/includes/settings_form.definition.inc.php`）。

### 非公開の印

文書（`site_content`）には、`privatemgr`（管理側で非公開）と `privateweb`（Web 側で非公開）の列がある。1 は「その文書が付く文書グループのどれかに、その側のユーザーグループへのアクセスの結びつきがある」ことを表す。

印は、結びつきから計算した結果を持っておく列で、正は結びつき（`document_groups` と `membergroup_access`・`webgroup_access`）。管理画面の絞り込み（`documentAccessCondition()`）は `privatemgr` の列を、公開側の取得の関数は `privateweb` の列を読む。

印を計算するのは、次の 2 つのメソッド。

- `ManagerAPI::setMgrDocsAsPrivate($docid = '')`: `privatemgr`
- `ManagerAPI::setWebDocsAsPrivate($docid = '')`: `privateweb`

どちらも、`$docid` を渡すとその文書の分だけ、空だと全文書を計算し直す（空のときは全件に及ぶので、id を渡し忘れない）。呼ぶのは、グループ管理の処理（`manager/processors/permission/access_groups.processor.php`・`manager/processors/permission/web_access_groups.processor.php`。全件）、文書の保存（`manager/processors/document/save_resource.processor.php`。`use_udperms` が有効のときだけ）、QuickManager の保存（`assets/plugins/qm/inc/on_doc_form_save.php`。その文書）、ddAutoFolders（`assets/plugins/managermanager/widgets/ddautofolders/ddautofolders.php`。その文書）、マイグレーション `20261111_120000_delete_orphan_document_group_rows`（全件）、インストーラの後片付け（`install/instprocessor.php`。新規インストールとアップグレードの両方、全件）、`DocAPI::create()`（`manager/includes/extenders/ex_docapi.php`。`refreshDocumentPrivateFlags()` 経由。文書グループを付けたとき、`use_udperms` に関係なく）。

その文書の 2 つの印を 1 度に計算し直すのは `ManagerAPI::refreshDocumentPrivateFlags($docId)`（`DocAPI::create()`・ddAutoFolders・DocManager の文書グループの付け外し（`assets/modules/docmanager/classes/dm_backend.class.php`）が使う。0 以下の id では何もしない）。

`documentgroup_names.private_memgroup`・`private_webgroup` も、同じく結びつきから計算した印で、グループ管理の処理が、結びつきを変えたとき（ユーザーグループと文書グループの結びつきの追加・削除、ユーザーグループの削除）に書き直す。Web 側のグループ管理は、このとき `privateweb` を計算し直したあとページキャッシュも消す（ページキャッシュは文書の `privateweb` ごと残り、公開側の読み込みの確認がそれを使うため）。管理側は `privatemgr` を公開側の判定に使わないので消さない（テンプレートで `[*privatemgr*]` を出す場合は、次にキャッシュが消えるまで古い値が出る）。文書グループを付け外しできるか（`documentGroupAccess()`）が読む。文書グループの削除は、`ManagerAPI::deleteDocumentGroup($groupId)` に 1 か所にある（管理側・Web 側のグループ管理のどちらからも呼ぶ）。文書グループの id を持つ 5 つの表（`document_groups`・`documentgroup_names`・`membergroup_access`・`site_tmplvar_access`・`webgroup_access`）の行を消し、そのグループに文書が付いていたときは、印を全件で計算し直してページキャッシュも消す。以前の版の削除で残った行は、マイグレーション `20261111_120000_delete_orphan_document_group_rows` が消す。

### 管理側の所属（mgrDocgroups）

管理ユーザーが所属するユーザーグループにアクセスを許す文書グループの id を、セッション `mgrDocgroups` に持つ（`ManagerAPI::getMgrDocgroups()`。`member_groups` と `membergroup_access` を結んで作る）。

- ログイン時にセットし、`action` が 1（ツリーなどの一部の読み込み）以外の管理画面のリクエストごとに `manager/includes/accesscontrol.inc.php` が読み直す。所属を変えると、次のリクエストから反映される
- 判定は `ManagerAPI::userMgrDocumentGroupIds()` を通して読む。重複と 0 以下を除いた整数の配列で返す

### 公開側（Web 側）の絞り込み

公開側では、`privateweb` と Web 側の所属（セッション `webDocgroups`。Web ログインで作る。`allow_mgr2web` が有効なら管理側の所属も足す）で絞る。`DocumentParser::getUserDocGroups()` が所属を返す。

- 所属があるときは `privateweb=0` または所属のグループに入っている文書を返す。所属が無いときは `privateweb=0` の文書だけ
- 管理画面にログインしていて `managerBypassesDocumentGroups()` が真の利用者に絞り込みを外すかは、関数ごとに違う。`getDocumentObject()`・`getDocuments()`・`getAllChildren()`・ページキャッシュの読み込みの確認は外し、`getPageInfo()`・`getActiveChildren()`・`getDocumentChildren()` は外さない（`core-issues.md` の「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」）
- ページキャッシュの文書オブジェクトには文書グループ（`__MODxDocGroups__`）を入れ、キャッシュを読み込むときに `privateweb` と所属を確かめる

## 判定の置き場所

文書グループの判定の正は ManagerAPI（`manager/includes/extenders/ex_managerapi.php`）。**文書グループで絞る問い合わせや確認は、自前で条件を書かず、次の判定を使う。** 画面・処理・プラグインごとに条件を書き写さない（食い違いが、権限の抜けや無駄な拒否になる）。

### ManagerAPI の判定

| メソッド | 返すもの |
| --- | --- |
| `bypassesDocumentGroups()` | 文書グループの絞り込みを受けない管理者か（`isAdmin()` または `save_role`）。`use_udperms` は見ない（公開側の判定にも使う） |
| `restrictsByDocumentGroups()` | 管理画面で文書を絞るか（`use_udperms` が有効で、`bypassesDocumentGroups()` でない） |
| `documentAccessCondition($alias)` | 絞るときの SQL の条件式（前に `AND` を付けない。絞らないなら空文字）。副問い合わせの形で、どの問い合わせにも入れられる |
| `canAccessDocument($docId)` | 1 つの文書を開けるか |
| `canOpenWithDocumentGroups($groupIds)` | 保存前の文書グループの集合が付いた文書を開けるか（保存で文書グループを変えたあとに開けなくなる状態を作らない） |
| `tmplvarAccessCondition($alias)` | TV（テンプレート変数）の SQL の条件式。絞るかどうかは文書と同じ（`restrictsByDocumentGroups()`）。届く条件は `site_tmplvar_access` の行で決まり、文書の非公開の規則とは別 |
| `canAccessTmplvar($tmplvarId)` | 1 つの TV を扱えるか |
| `canOpenDocumentEditor($docId)` | 文書の編集画面（a=27）を開けるか。許可するリソースの範囲（`isInAllowedParents()`）・`view_document` の権限・文書グループ（`canAccessDocument()`）の順に見る。QuickManager のボタンの表示と TV の保存が使う。開けない理由を返す `documentEditorDenial($docId)`（`invalid_id`・`out_of_range`・`no_permission`・`document_group` のどれか、開けるなら空文字）が正で、編集画面の入口（a=27・131・132。理由ごとにエラーの出し方を変える）も同じ判定を使う |
| `canCreateDocumentUnder($parentId)` | 親の下に新規作成の画面を開けるか。`new_document`・範囲・親の文書グループを見る。ルート（親が 0 以下）は `canPlaceAtRoot()` と `isInAllowedParents(0)` の両方。QuickManager の追加ボタンが使う |
| `deleteDocumentGroup($groupId)` | 文書グループの削除（印の節） |
| `isAllowroot()` | `udperms_allowroot` の設定を管理者の判定込みで返す（`bypassesDocumentGroups()` または `udperms_allowroot`）。`use_udperms` は見ないので、ルートに置けるかの判定には使わず `canPlaceAtRoot()` を使う |
| `canPlaceAtRoot()` | 文書をルート（親が 0）に置いてよいか（`use_udperms` が無効、または `isAllowroot()`）。許可するリソースの範囲（`isInAllowedParents(0)`）は混ぜない別の判定 |
| `isInAllowedParents($id)` ほか | 許可するリソースの範囲の判定（次の節） |

### DocumentParser の判定

| メソッド | 返すもの |
| --- | --- |
| `checkPermissions($docid, $duplicateDoc)` | 文書グループの面で扱ってよいか。管理者・`use_udperms` が無効・ルートの扱いを先に決め、それ以外は `canAccessDocument()`。モジュール・プラグインからも呼べる公開の API |
| `managerBypassesDocumentGroups()` | 公開側で、管理画面にログイン中の利用者が絞り込みを受けないか（`isLoggedIn('mgr')` かつ `bypassesDocumentGroups()`）。ログインしていなければ ManagerAPI を読み込まない。`use_udperms` は見ない（`privateweb` の判定に使う） |
| `getDocumentObject()`・`getDocuments()`・`getPageInfo()`・`getAllChildren()`・`getActiveChildren()`・`getDocumentChildren()` | 取得の関数。管理画面側（`isFrontend()` が偽）の分岐は `documentAccessCondition()`、公開側の分岐は `privateweb` と `managerBypassesDocumentGroups()` を使う。公開側で絞り込みを外す関数は、関数ごとに違う（課題の節） |

ページキャッシュの読み込みの確認（`manager/includes/document.parser.class.inc.php`）も、公開側の分岐と同じく `managerBypassesDocumentGroups()` を使う。

### ルートに置けるか

文書を変える処理（保存・移動・下書き・複製）と、ツリー・文書一覧・親の選択・QuickManager の表示は、ルート（親が 0）に置けるかを `canPlaceAtRoot()` で決める。判定を見ない入口が残る（#1394）。文書グループへのアクセス（`checkPermissions()`）は、ルートには使わない。許可するリソースの範囲は別の判定で、ルートでは入口ごとに `isInAllowedParents(0)` も重ねて確かめる。

| 入口 | 判定 |
| --- | --- |
| ツリーの「ルートに新規作成」（`manager/frames/tree.php`）・文書一覧のルートの作成ボタン（`resources_list.static.php`）・親の選択のルートの項目（`parent_picker.dynamic.php`） | `new_document`（親の選択は不要）・`canPlaceAtRoot()`・`isInAllowedParents(0)` |
| 新規作成の保存・保存での親の変更（`save_resource.processor.php`） | 新規は、親がルートなら常に `canPlaceAtRoot()`、それ以外の親は `checkPermissions()`。更新は、文書そのものを開けるかを先に確かめ、親が変わるときだけ、親がルートなら `canPlaceAtRoot()`、それ以外の親は `checkPermissions()` |
| 移動（`move_document.processor.php`） | `move_document.processor.php` は `parentChangeDeniedMessage()` を呼ばず、同じ判定をその場に書いている。範囲（`isInAllowedParents()`）を先に見て、そのあと親が変わるときに、親がルートなら `canPlaceAtRoot()`、それ以外の親は `checkPermissions()` |
| 下書きの保存・公開（`save_draft_content.processor.php`・`publish_draft_content.processor.php`・`parentChangeDeniedMessage()`） | 親が変わるときに、循環と存在、親がルートなら `canPlaceAtRoot()`（それ以外の親は `checkPermissions()`）、`isInAllowedParents()` の順 |
| 複製（`duplicate_content.processor.php`・`checkPermissions($docid, true)`） | 複製元の親がルートなら `canPlaceAtRoot()` |
| QuickManager の追加ボタン（`canCreateDocumentUnder()`） | ルートは `canPlaceAtRoot()` と `isInAllowedParents(0)` |

## 許可するリソース（allowed_parents）

利用者ごとの設定 `allowed_parents` に、編集を許可する親の文書の id（空白・カンマ・`|` 区切り）を書く。ユーザー編集画面の「許可するリソース」で、空なら制限しない。0 を含めるとサイト全体を許可する。

文書がこの範囲の中かの基準は 1 つで、`ManagerAPI::isInAllowedParents($id)` が正。許可した親の文書そのものか、その子孫（深さの制限なし、削除済みも含む）なら範囲の中。設定が空、または 0 を含むときは制限なし（0 を含まないとき、ルートは範囲の外）。設定があるのに正しい ID が 1 つも無いときは、すべて範囲の外。範囲を使う判定は、すべてこの基準にそろえてある。

| メソッド | 返すもの | 使う場所 |
| --- | --- | --- |
| `isInAllowedParents($id)` | 1 つの文書が範囲の中か。祖先を `getAncestorIds()` で DB の親からたどる | 文書の編集画面の入口（`documentEditorDenial()` 経由）・`canOpenDocumentEditor()`・`canCreateDocumentUnder()`・文書の情報（`manager/actions/document/document_data.static.php`）・リソース一覧のボタン・戻り先・パンくず（`manager/actions/document/resources_list.static.php`）・文書を変える処理（保存・移動・複製・公開の切り替え・削除・元に戻す・ドラフトの保存と公開など。`manager/processors/document/` 以下）・移動先の親の確認（`parentChangeDeniedMessage()`） |
| `allowedDocsCondition($alias)` | 範囲の中の文書に絞る SQL の条件式（前に `AND` を付けない。制限なしなら空文字）。許可した親から子孫の ID を集めて `IN` で絞る | ツリーの子の問い合わせ（`manager/frames/nodes.php`）・親の選択 |
| `getUserAllowedDocs()` | 範囲の中の文書の ID の配列。戻り値は 3 通りで、制限なしは `[]`、正しい ID が 1 つも無いときは `''`、それ以外は ID の配列（リクエスト内でキャッシュ） | `allowedDocsCondition()`・`isContainAllowed()` |
| `isContainAllowed($id)` | ツリー・一覧に出してよいか。範囲の中、または範囲の頂点へたどる途中の祖先なら true。行ごとには問い合わせない（最初の 1 回だけ `getUserAllowedDocs()` と `allowedParentAncestorIds()` が読んでキャッシュする） | ツリーとリソース一覧の行 |
| `allowedParentAncestorIds()` | 許可した親それぞれの祖先の ID（ツリーに出す・初期に開くために使う） | ツリーの初期表示（`manager/frames/tree.php`）・`isContainAllowed()` |
| `isAllowed($id)` | `isInAllowedParents()` と同じ判定。`$id` が 0 のときはリクエストの `pid` を見る（`pid` が無ければ true）。外部のコードが使うことがあるので残している | 新しいコードは `isInAllowedParents()` を使う |

新規作成の画面（a=4・72）は、作成先の親の範囲を保存の処理が確かめる。`id` を付けた要求は、その文書を読むので、入口でその文書の範囲を `isInAllowedParents()` で確かめる。

## 分かっている課題

未解決の課題。判定を ManagerAPI に集める方向で扱うものが多い。

| Issue | 内容 |
| --- | --- |
| #1394 | ルートに置く入口のうち、`canPlaceAtRoot()` を見ないものが残る（メインメニュー・ダッシュボードの新規作成、保存後の「続けて新規」、移動の画面でツリーのルートを選ぶ操作、サイトのインポート）。移動の処理の切り分けを `parentChangeDeniedMessage()` に寄せることも含む |
| `core-issues.md` | 公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う（「公開側の文書の取得で、Web 側の非公開の扱いが関数ごとに違う」の項） |
| #1357 | 権限の作り全体の見直し。2 段階目は判定をほかの権限にも寄せる、3 段階目は権限の階層・依存、`save_role` の扱い、管理側と Web 側の統合など作りの見直しを決める（既存のロールのアップグレードでの移し方も含める） |

## 関連ドキュメント

- `architecture.md`: 処理フローと、`DocumentParser` の位置づけ
- `core-issues.md`: コア課題（公開側の取得の関数ごとの違い）
