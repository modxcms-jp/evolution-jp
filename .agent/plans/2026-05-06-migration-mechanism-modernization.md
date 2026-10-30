# ExecPlan: マイグレーション機構のモダン化

## Purpose / Big Picture

1.0.0J 以降のすべての版から、今の版へアップグレードできるようにする。アップグレードしたあとの DB は、今の版を新規インストールした DB と同じ形（表・列・索引）になり、利用者が選んだ設定（外した権限、選んだテーマ・エディタなど）は残る。

そのために、アップグレード（DB のスキーマと、設定・データの移行）の処理を、置き場所 1 つ（`manager/includes/migrations/`）、入口 1 つ（マイグレーションのランナー）にまとめる。今は `manager/includes/upgrades/`・`install/sql/fix_settings.php`・`install/instprocessor.php`・`install/functions.php`・`manager/includes/default.config.php` に散らばっている版依存の処理をなくし、「アップデートが済んでいないか」の判定も 1 つ（未適用のマイグレーションがあるか）にそろえる。

到達条件:

- 1.0.0J・1.0.x の途中の版・1.1.0J・1.2.0J〜1.2.3J・今の main の新規インストールのそれぞれの DB から、アップデーター（`install/`）で上げると、スキーマが今の版の新規インストールと一致する（比べ方は Validation）。
- 同じアップグレードをもう一度行っても、DB は何も変わらない。
- 版依存のアップグレード処理は `manager/includes/migrations/` の中だけにあり、`manager/includes/upgrades/` と `install/sql/fix_settings.php` は無い。
- 公開側の 503・管理画面の入口・ダッシュボード・メニューの版の表示・`health:check` が、同じ判定（未適用のマイグレーションの有無）を使う。

## Progress

- [x] (2026-10-26) M1: ランナー（`EvoMigrator`）・補助（`EvoMigrationHelper`）・CLI（`migrate`・`migrate:status`・`make:migration`）を作る（自己レビュー 3 回、tester の確認 4 回。Docker の php:8.4 / mariadb:11 で計画の期待をすべて満たした）
- [x] (2026-10-26) M2: 古い版の DB を作る道具とスキーマの比べ方を、検証スキルに足す（15 タグすべてで build・load が通り、1.0.0J の差が Context の表と 41 件で一致。utf8mb4 版も作れる。自己レビュー 2 回、tester の独立の確認 1 回）
- [x] (2026-10-26) M3: baseline のスキーマの部を作り、`create_tables.sql` に `fix_settings.php` のスキーマの直しを移す（15 タグ・utf8mb4 版・途中の版を通ってきた形・COMPACT の InnoDB の再現・mariadb:10.4 で、今の新規インストールの形との差 0 件。2 回目は baseline の変更系の文 0 本。途中で失敗させても流し直せる。自己レビュー 3 回、tester の独立の確認 2 回）
- [x] (2026-10-26) M4: baseline のデータの部を作り、`default.config.php` の版の分岐を撤去する（1.0.0J・1.0.5J・1.2.3J で計画の期待どおり。利用者の選択を変えたフィクスチャでは変わらない。2 回目は変更系の文 0 本。ログに秘密の値が出ない。自己レビュー 2 回、tester の独立の確認 1 回）
- [x] (2026-10-26) M5: インストーラに組み込み、`manager/includes/upgrades/`・`fix_settings.php`・インストーラの版依存の処理を消す（新規インストールで baseline が記録され、イベント名は旧 `fix_settings.php` と 128 件同一。1.0.0J・1.0.14J.r9・1.2.3J・utf8mb4 の 1.1.0J からアップデーターで差 0 件。長い値・ロックの待ち時間切れで止まり、何も変えない。2 回目は baseline 由来の変更 0。自己レビュー 2 回、tester の独立の確認 1 回）
- [x] (2026-10-26) M6: 「アップデートが済んでいないか」の判定を未適用のマイグレーションの有無にそろえる（本物の新規インストールとアップデート後に、設定を保存せず公開側 200。未適用があれば公開側 503・権限で振り分け・install/ が無ければ置くよう案内。記録の表が壊れても 503 で警告なし。公開側 1 リクエストあたり SELECT 1 本。自己レビュー 1 回、tester の独立の確認 1 回）
- [x] (2026-10-26) M7: 全版の通しの確認と文書（`assets/docs/migrations.md` ほか）（行列の 21 行すべてで MariaDB 11 の差 0 件。PHP 7.4・MariaDB 10.4・MySQL 5.7・8.0 でもアップデーターが成功。記録は `.agent/runs/2026-10-26-migration-validation/README.md`。PR 全体の最終の自己レビューで出た高 1・中 2 を直した）

## Surprises & Discoveries

- 2026-10-14（#585） / 旧 `upgrades.php` は、新しいコードのまま古い DB で管理画面が起動できる前提で、設定ページを開いたときに列を足している。この前提を支えていたのが `exec()` の 1054 などの無視で、#585 で無視を外した。1.0.0J 以降の DB では、システム設定より前に無い列（`site_content.alias_visible`、`user_attributes` / `web_user_attributes` の `street` / `city`）を読んでも止まらないことを確かめた。
- 2026-10-14（#585） / `upd_db_structure.php` で `ADD COLUMN ... AFTER x` の `x` が後から追加される順になっていた。baseline でも、AFTER の基準の列を先に足す。
- 2026-10-26 / 新規インストールでも `install/sql/fix_settings.php` が動く（`install/instprocessor.php` 266〜269 行。新規で失敗していないとき）。そのため、今の新規インストールの最終形は `create_tables.sql` だけでなく、`fix_settings.php` が後から変えた形（`documentgroup_names`・`membergroup_names`・`webgroup_names` の `name` が `varchar(191)`）になっている。ロール ID 1（Administrator）も、新規インストールでは `default_settings.sql` ではなく `fix_settings.php` が入れている。
- 2026-10-26 / 新規インストール直後の `settings_version` は `'0'`（`install/sql/default_settings.sql` 6 行）。前の計画の「`settings_version` があればアップグレード」という判定では、新規インストールもアップグレードと見なしていた。
- 2026-10-26 / `upd_db_structure.php` 202 行は `site_templates.content` を毎回 `mediumtext`（NULL 可）にしている。新規インストールは `mediumtext NOT NULL` なので、設定画面を通ったサイトほど新規インストールと違う形になっていた。
- 2026-10-26 / `upd_db_structure.php` 191〜194 行の `REGEXP '^https?://([-\w\.]+)+(:\d+)?/?'` は、SQL の文字列の中で `\w`・`\d`・`\.` のバックスラッシュが落ちる（MySQL・MariaDB とも、知らないエスケープは文字だけになる）。意図した正規表現として動いていない。
- 2026-10-26 / `upgrades.php` 10 行の `$default_config = include_once(...)` は、`default.config.php` が読み込み済みなので `true` になり、111 行と 129 行で既定値が入らない（explorer が PHP 8.4 で確認）。157〜193 行の `update_config_default_template_method()` は、計算した `$auto_template_logic` をグローバル変数に入れるだけで保存していない。
- 2026-10-26 / タグごとの新規インストールの定義（`git show release-<tag>:<path>` の CREATE TABLE）を MariaDB 11 に読み込んで比べた（planner が一時コンテナで実施）。1.0.6J の新規インストールは `site_plugin_events` の主キーが `(evtid)` だけ、`user_settings` / `web_user_settings` の主キーが `(setting_name)` だけになっている。1.0.14J.r9 の `site_revision` は今と列が違う（`docid`・`unpub_date` があり `elmid` が無い）。1.0.10J のアップグレード用 SQL（`install/sql/upd_fixvalues.sql` 203 行）は `site_templates.doc_encoding` を足しているが、今の定義には無く、今のコードも読まない。1.2.3J の新規インストールでも、`active_users.id` と `documentgroup_names.private_*` は NULL 可。
- 2026-10-26 / MariaDB 11 で、前置き長の付いた索引（1.0.0J の `aliasidx (alias(250))`）がある列を `varchar(245)` に縮めると、索引は列全体の索引に自動で直った。MySQL 5.7 での動きは未確認（MySQL 公式イメージは Docker Engine 29 系で取得できない。`.claude/skills/adaptive-validation-runner/references/environment.md` の「既知の落とし穴」）。
- 2026-10-26 / インストーラを通さずにファイルだけ差し替えたサイトは、設定画面（`a=17`）で `upgrades.php` が動くが、`create_tables.sql`（1.0.14J・1.0.20J で増えた `site_revision`・`system_cache`）、`fix_settings.php`、utf8mb4 への変換、同梱の要素の入れ直しは行われない。インストーラを通したサイトと結果が違う 2 つ目の経路になっていた。
- 2026-10-26 / インストーラは同梱プラグインのイベントの紐付け（`install/processors/prc_insPlugins.inc.php` 106 行）と同梱 TV のテンプレートの割り当て（`install/processors/prc_insTVs.inc.php` 61 行）を消して入れ直す。利用者が付け替えた分は戻る（推測）。版依存の処理ではなく要素の入れ直しの作りなので、この計画の非対象とし、`assets/docs/core-issues.md` に記録した。
- 2026-10-26（M5） / M5 と M6 の間は、アップデートしたあとも `settings_version` が古いままなので、公開側は今の判定（`version_mismatch_guard.php`）で 503 になる（設定を保存すると直る）。M6 で判定を未適用のマイグレーションの有無にそろえて解消する。マイグレーションが途中で失敗したとき、アップデーターはキャッシュを消さない（`system_cache` の無い DB で `clearCache()` が止まるため）。公開側は止まり、管理画面もアップデーターへ送られるので、古いキャッシュで動くことはほぼ無く、成功したときに消える。
- 2026-10-26（M5） / 検証のフィクスチャは `system_settings` が少ない（`site_name` などが無い）ので、上げたあとの管理画面が `Undefined variable $site_name`（`manager/frames/menu.php`）で止まることがある。実際のサイトには行があるので、本体の不具合ではない。管理画面まで確かめる M6・M7 の前に、フィクスチャのデータに `site_name` などの基本の設定を足す（M6 の書き込んでよいファイルに `upgrade-fixture-data.sql` を加える）。
- 2026-10-26（M4） / M6 で `install_version` の書き込みを消すまでは、今の main で新規インストールした DB に baseline を流すと、`install_version` の行を消す DELETE が 1 本流れる（ほかは変えない）。新規インストールでは `markAllApplied()` で baseline を流さないので、実害は無い。M3 の「最終形の DB に流しても何も変えない」の、M6 までの例外。
- 2026-10-26（M3） / MariaDB 11 で、MyISAM のまま utf8mb4 にした 1.0.0J の `*_names.name`（255 文字）の UNIQUE は HASH になっているが、191 に縮める MODIFY で BTREE に作り直される。COMPACT の InnoDB 表は、`ROW_FORMAT=DYNAMIC` への ALTER で全体の `aliasidx` を作れるようになる（1.2.3J の utf8mb4 版で再現して確かめた）。MySQL 5.6・5.7 の実機では確かめていない。
- 2026-10-26（M2） / 1.0.16J までの CREATE TABLE は文字セットを書かないので、そのまま流すとサーバーの既定（utf8mb4）になり、`aliasidx` が `alias(250)` に切られ、長い UNIQUE が HASH と報告される。古いサイトの再現には文字セットを明示する。
- 2026-10-26（M2） / 古い DB を読み込んだ直後は、アップデーターを通すまで管理画面に入れないことがある。1.0.0J・1.0.6J は `/manager/` が HTTP 500（`site_htmlsnippets.published` などが無い）。1.0.14J.r9 以降は、ログインすると次の `GET /manager/` でアップデーター（`/install/`）へ送られる。M5 以降の確認では、アップデーターをログインなしで動かせることが前提になる。
- 2026-10-26（M2） / `user_roles` の欠けた列: 1.0.0J は `move_document`・`remove_locks`・`view_schedule`、1.0.5J・1.0.6J は `move_document`・`view_schedule`、1.0.7J〜1.0.17J は `move_document`、1.0.20J 以降は無し。重複行は 1.0.0J と 1.0.5J のフィクスチャにだけ入る（1.0.6J 以降は主キー・UNIQUE が既にある）。
- 2026-10-26（M1） / `evo()->clearCache()` は `system_cache` を TRUNCATE するので、表の無い DB（1.0.20J より前）では `messageQuit()` から `exit` して処理ごと終わる（未ログインの CLI では `Error` とだけ出て終了コード 0）。ランナーの中でキャッシュを消すと、アップデーターでは `create_tables.sql` より前に止まる。
- 2026-10-26（M1） / マイグレーションのファイルをメソッドの中で `require` すると、ファイルの変数（`$id` など）がランナーの変数を書き換え、返した関数が `$this` に結び付く（php:7.4 で確かめた）。
- 2026-10-26（M1） / 1.0.20J〜1.2.3J の DB には `system_cache` があるので、表の有無で「古い DB か」を判定すると、CLI だけで baseline まで流れてしまう。baseline の記録の有無で判定する。
- 2026-10-26（M1） / MariaDB 11 で、補助の `modifyColumn()`（INT の既定値 `'0'`・VARCHAR の既定値 `''`）・`addIndex()`（UNIQUE・通常・FULLTEXT・非 UNIQUE からの作り直し）・`removeDuplicates()`（NULL を含む組を含む）は、2 回目に何もしない（`SHOW CREATE TABLE` が同じ）ことを確かめた。MySQL と古い MariaDB での差は M3 で確かめる。

## Decision Log

- 2026-05-06 / ロールバック（down）は作らない。マイグレーションは上げる方向（up）だけ。
- 2026-05-06 / 既存の処理は `00000000_baseline.php` 1 本にまとめる（分割しない）。
- 2026-05-06 / baseline は `upd_db_structure.php` の写しではなく、`release-1.0.0J:install/setup.sql` と今の定義の差を正として書き起こす。
- 2026-10-14 / DB のエラーは既定ですべて止まる（#585）。やり直しても止まらないこと（冪等性）は、`INFORMATION_SCHEMA`・`SHOW INDEX`・`IF NOT EXISTS` などの存在確認で担保し、省略しない。
- 2026-10-26 / planner / 「正」は今の新規インストールの最終形とする。`create_tables.sql` を正にし、`fix_settings.php` が後から変えていた形（`name` の `varchar(191)`）は `create_tables.sql` に書き、1 か所にする。191 は、utf8mb4 の 1 文字 4 バイトで UNIQUE 索引が 767 バイトに収まる長さ（README の動作要件に MySQL 5.x があるため）。代替案の「`create_tables.sql` の 245 に戻す」は、アップグレード済みのサイト（すでに 191）を広げ直すことになり、古い InnoDB で索引が作れないおそれがあるので採らない。
- 2026-10-26 / planner / 2026-05-06 の「`settings_version` の有無で新規かアップグレードかを決める」は改める。新規インストールでも `settings_version` は `'0'` で存在する。新規かどうかはインストーラが知っている（`sessionv('is_upgradeable')`）ので、新規インストールではインストーラが「すべて適用済み」と記録する（`markAllApplied()`）。インストーラの外（CLI）では、記録の表が無ければ baseline から順に流す。baseline は最終形の DB に流しても何も変えない作りにするので、これで正しい。
- 2026-10-26 / planner / 2026-05-06 の「トリガー統一は次フェーズ」は改める（本人の決定 1）。インストーラに組み込み、上書きインストールでもランナーを動かす。
- 2026-10-26 / planner / 設定画面（`a=17`）のトリガーは残さない。インストーラを通さない経路は、`create_tables.sql`・イベント名の同期・utf8mb4 への変換・要素の入れ直しを含まず、インストーラと違う結果になる 2 つ目の経路だった。ファイルだけ差し替えて `install/` を置いていないサイトは、管理画面の入口でメンテナンスの表示にし、settings 権限のある人には「アップデーター（`install/`）を置いて実行してください」と出す（M6）。開発中に足したマイグレーションは `php evo migrate` で当てられる。代替案の「`a=17` でランナーだけ動かす」は、上の差が残るので採らない。
- 2026-10-26 / planner / 実行の順は、インストーラのアップグレードでは「ランナー → `create_tables.sql` → イベント名の同期 → utf8mb4 への変換」にする。ランナーを先にするのは、(1) 1.0.14J の形の `site_revision` を baseline が消してから `create_tables.sql` が今の形で作れる（`CREATE TABLE IF NOT EXISTS` は古い形の表があると何もしない）、(2) 列の長さを縮める処理（`alias` 245・`name` 191）を utf8mb4 への変換より前に済ませないと、索引の長さの上限（MyISAM 1000 バイト）で変換が失敗する、(3) 新しい表の定義が `create_tables.sql` の 1 か所だけで済む（マイグレーションは表を作らない）ため。マイグレーションの決まりとして「表を作るのは `create_tables.sql` だけ。マイグレーションは既にある表を変えるだけで、表が無ければ何もしない」を `assets/docs/migrations.md` に書く。
- 2026-10-26 / planner / 記録の表は `{prefix}migrations`（既定の接頭辞で `modx_migrations`。Issue の名前と同じ）にする。前の計画の `{prefix}modx_migrations` は `modx_modx_migrations` になるので改める。定義はランナー（`EvoMigrator::ensureTable()`）だけが持ち、`create_tables.sql` には書かない（ランナーは `create_tables.sql` より前に動くため、また、ランナーの記録の表はランナーの持ち物なので）。列は `id varchar(100)` と `applied_at int(20)`（ほかの表と同じく UNIX 時刻）。
- 2026-10-26 / planner / マイグレーションの ID は `YYYYMMDD_HHMMSS_説明`（説明は英小文字・数字・`_`）。Issue の `YYYYMMDD_説明` に時刻を足したのは、同じ日に 2 本足したときの順を決めるため。baseline だけは `00000000_baseline`。ランナーが読むファイルは `^\d{8}(_\d{6})?_[a-z0-9_]+\.php$` に合うものだけ。
- 2026-10-26 / planner / Issue のコメント（2026-10-18、Laravel を参考にした検討）の 4 点を取り入れる: `php evo migrate:status`、`php evo make:migration 説明`、マイグレーション 1 本は `return function (EvoMigrationHelper $m) { ... };` で関数を 1 つ返す、`GET_LOCK()` で同時に 2 回走らせない（30 秒待って取れなければ失敗にする）。Schema Builder は作らない。補助は存在確認と、それに基づく変更だけにする。
- 2026-10-26 / planner / 失敗の扱い: 補助の `$m->exec()` は `db()->query($sql, false)` で実行し、エラーなら SQL とエラーを持った例外を投げる。ランナーは例外を受けて `evo()->logEvent()`（種別 3、コンテキスト配列）に残し、そのマイグレーションを記録せずに止める。インストーラは画面にエラーを出して止まり、CLI は終了コード 1 で終わる。`messageQuit()` で画面ごと止める今の動きより、何が失敗したかを示せる。
- 2026-10-26 / planner / baseline には版の条件を 1 つも置かない。条件はすべて DB とファイルの状態（列があるか、値が古い既定値と同じか、など）で決める。そのため、版の正規化（`upgrades.php` 19〜23 行。`'1.0.5J-r11'` が `1.0.5` より大きくなる、`'1.3.0rc2'` が `1.3.0` より小さくなる、などの怪しさ）は要らなくなるので消す。前の版が分からない（`settings_version` が `'0'`）サイトも同じに扱える。
- 2026-10-26 / planner / 毎回走っていた MODIFY は、baseline の中で `INFORMATION_SCHEMA.COLUMNS` の型・NULL 可否・既定値を最終形と比べ、違うときだけ実行する。2 回目の実行で ALTER が 1 本も出ないようにするため。最終形と同じ列を MODIFY し直すだけの処理（`upd_db_structure.php` の `pagetitle` など、1.0.0J から変わっていない列）は baseline に入れない。
- 2026-10-26 / planner / 列を縮める MODIFY（`site_content.alias` を 245、`*group_names.name` を 191）は、先に長さを確かめ、収まらない行が 1 件でもあれば、baseline の最初で何も変えずに止める（例外に表・列・上限・行の ID の一覧を入れる）。黙って切ると URL（alias）が変わり、飛ばすと最終形にならないまま記録されて二度と直らないため。止まった場合は、利用者が DB の道具か `php evo db:query` で値を短くしてから、アップデーターをやり直す。
- 2026-10-26 / planner / `site_content.type` の推定の UPDATE は残し、`REGEXP` を `LIKE` に置き換える（`content LIKE 'http://%' OR content LIKE 'https://%'`、`alias LIKE '%.xml'` など）。`REGEXP` は前述のとおり意図どおりに動いておらず、MySQL 5.7 と 8 で正規表現の実装も違うため。条件が `type=''` の行だけなので、何度流しても同じ。
- 2026-10-26 / planner / `site_tmplvar_contentvalues` の FULLTEXT 索引 `value_ft_idx` は baseline で作る。今の新規インストールは InnoDB で作っており、MySQL 5.6 以降はすでに前提になっている。コアは使っていない（`MATCH ... AGAINST` はコアに無い）が、外部のスニペットが使う可能性を残し、新規インストールとそろえる。大きな表では作るのに時間がかかるので、かかった秒数をログに残す。代替案の「`create_tables.sql` から外し、既にあるサイトからは消す」は、使っているかもしれない外部の処理を壊すので採らない。
- 2026-10-26 / planner / `site_content.content_ft_idx` と、1.0.0J の重複した索引 `site_content.id` は、あれば消す（最終形に無い）。`site_tmplvar_templates` の `idx_tmplvarid`・`idx_templateid` も、あれば消す（今の処理と同じ）。
- 2026-10-26 / planner / 主キー・UNIQUE を付ける前に、キーの列が同じ重複行を 1 行残して消す（本人の決定 3）。残す行は表ごとに決め、消した件数を `evo()->logEvent()`（種別 2、`['table' => ..., 'key' => [...], 'removed' => 件数]`）に残す。重複の組ごとに「残す行を決める → その組の行を消す → 残す行を入れ直す（または ID で残す）」で行い、一時の表は作らない（`sql_require_primary_key=ON` の MySQL では主キーの無い一時の表を作れないため）。主キーを付け替える ALTER は、外す操作と付ける操作を 1 本にする（`syncPrimaryKey()` と同じ理由）。
- 2026-10-26 / planner / 途中の版だけにあった列・表の扱い: `site_templates.doc_encoding`（1.0.10J のアップグレード用 SQL が足した。今のコードは読まない。中身に使い道が無い）は、あれば消す。1.0.14J の形の `site_revision`（`elmid` が無い）は、今のインストーラと同じく消す（今のコードが読めない形の古い履歴。`create_tables.sql` が今の形で作り直す）。消えた表（`event_log`・`keyword_xref`・`site_content_metatags`・`site_keywords`・`site_metatags`、サンプルの `jot_*`）は消さずに残す（利用者のデータが入っている。今のコードは読まないので害は無い）。スキーマの比較では、これらを「残す表」として別に数える。
- 2026-10-26 / planner / 比べる範囲は、列の型・NULL 可否・既定値・`EXTRA`（AUTO_INCREMENT など）、索引の名前・一意かどうか・種類（FULLTEXT か）・列の並び。列のコメント・列の順・表のコメント・文字セットと照合順序・ストレージエンジンは比べない。文字セットは利用者が選ぶ utf8mb4 への変換（`convert2utf8mb4()`）の役で、エンジン（古いサイトの MyISAM）は変える処理がもともと無く、この計画の非対象とする。baseline の MODIFY には、`create_tables.sql` と同じコメントを付ける（どうせ書き直すので）。
- 2026-10-26 / planner / 利用者の設定を上書きしていた処理は、条件を直して baseline へ移す（本人の決定 2）。個々の扱いは Plan of Work の「データの部」の表のとおり。`upload_maxsize` を `'1048576'` から空にする処理は移さない（1 MB を選んだ利用者と見分けられず、古い既定値のままでも動くため）。
- 2026-10-26 / planner / `default.config.php` 125〜137 行の `settings_version` による分岐（1.0.5J-r11〜1.0.6J-r1 向けに、チャンク「ログイン画面」「ダッシュボード」を非公開にする）は撤去し、baseline にも入れない。今のコードはこの名前のチャンクを読まない（2012 年の 0b24a400f でファイルに移った）ので、非公開にしても意味が無く、テンプレートでこの名前のチャンクを使っている利用者のものを隠すだけになるため。撤去すると `default.config.php` は副作用の無い既定値の配列になり、baseline は `include` で既定値を受け取れる（`include_once` の戻り値の誤りもなくなる）。
- 2026-10-26 / planner / インストーラの版依存の処理は baseline へ移す: `site_revision` の古い形の削除（235〜245 行）、`site_id` の作り直し（379〜391 行）、`site_url` の削除（393〜396 行）、`cleanupNullStrings()`（398〜400 行。要素の入れ直しより前という条件は、ランナーが先に動くので満たす）。`settings_version` の補完（439〜464 行）は、設定画面で `upgrades.php` を動かすためだけのものだったので消す。`convert2utf8mb4()` はインストーラに残す（版に依存せず、利用者がインストーラで選ぶ変換で、設定ファイルの書き換えも伴うため）。
- 2026-10-26 / planner / `fix_settings.php` は消す。スキーマの直しは `create_tables.sql` と baseline へ、1 回だけのデータの直し（#611・#615・#640 の不要な設定の行の削除、ロール ID 1 が無いときの追加）は baseline へ、イベント名の一覧（`system_eventnames` への REPLACE）は新しい `install/sql/system_eventnames.sql` へ移し、新規・アップグレードのどちらでも `create_tables.sql` の後に流す。イベント名は版ごとに増える「同梱のデータ」で、1 回だけの移行ではないため、`create_tables.sql` と同じく毎回そろえる。新規インストールのロール ID 1 は `default_settings.sql` に入れる。
- 2026-10-26 / planner / 「アップデートが済んでいないか」の正は「未適用のマイグレーションがあるか」（`EvoMigrator::hasPending()`）にする。公開側の 503、管理画面の入口、`health:check` はこれだけを見る。`settings_version` は「システム設定を保存した版」として残すが、アップデートの判定には使わない（初回だけ設定画面へ案内する、`'0'` か空かの判定にだけ使う）。版の表示（メニュー・システム情報・バックアップの名前・ダンプの見出し・`health:check`）はコードの版（`$modx_version`）にする。`settings_version` は今後アップグレードで更新されなくなるので、表示に使うと古い版が出るため。
- 2026-10-26 / 本人の決定 / 設定画面（`a=17`）のトリガーは残さない（上の planner の案のとおり）。ファイルだけ差し替えたサイトは、未適用のマイグレーションがあれば、公開側はメンテナンスの表示にする。管理画面では、settings 権限のある人に「アップデーター（`install/`）を置いて実行してください」と案内し、ほかの人にはメンテナンスの表示を出す（M6）。
- 2026-10-26 / 本人の決定 / `install_version` による判定はやめる。アップデート待ちの判定は「未適用のマイグレーションがあるか」（`EvoMigrator::hasPending()`）の 1 つだけにし、管理画面の入口（`manager()->isUpdatePending()`）でも `install_version` を見ない。同梱プラグインのコードなど、DB に入っている同梱物の入れ替えが要る版では、その版のマイグレーションに入れ替えを書く。マイグレーションがあれば未適用として数えられ、アップデーターへ送られる。その結果、マイグレーションを含まない版は、ファイルを差し替えただけでもアップデーターへは送られない（DB が追いついているので、公開側も管理画面も止めない）。
- 2026-10-26 / planner（本人の決定を受けて） / 今回の baseline では、同梱物の入れ替えをしない。今のアップデーターは、ランナーのあとに `prc_ins*`（`install/processors/`。利用者がアップデーターで選んだ同梱の要素を入れ直す）を毎回流すので、baseline で入れ替えると同じことを 2 回することになる。役割は次のように分ける。`prc_ins*` は「利用者が選んだ要素を入れる・入れ直す」（新規とアップグレードのどちらでも、選択の画面に従う）。マイグレーションは「その版で必ず要る入れ替え」（選択に関わらず、1 回だけ）。入れ替えの元は `assets/` の下の `.tpl`（`install/setup.info.php` が読む docblock）で、読み取りの関数 `parse_docblock()` は今は `install/functions.php`（180 行）にある。初めて入れ替えのマイグレーションを書く版で、この関数を `manager/includes/` へ移し、インストーラとマイグレーションの両方から使う（今回は使わないので移さない。YAGNI）。入れ替えの書き方は `prc_insPlugins.inc.php` の作り（同名の有効な行を無効にして控えとして残し、新しい行を入れる）に合わせることを `assets/docs/migrations.md` に書く。
- 2026-10-26 / planner（本人の決定を受けて） / `install_version` の記録もやめる。読むのは `version_mismatch_guard.php` と `ex_managerapi.php` だけで、M6 でどちらも読まなくなるため、残しても使い道が無い。M6 で `install/instprocessor.php` の書き込み（新規 360〜367 行、アップグレード 439〜471 行のうち残っていた 466〜470 行）を消し、baseline のデータの部で、既にある `install_version` の行を消す。M5 の時点では書き込みを残す（M5 と M6 の間も、新規インストールの公開側が止まらないよう、今の判定が `install_version` を読むため）。代替案の「インストーラを最後に通した版として残す」は、表示する場所も無く、判定に使われる誤解のもとになるので採らない。
- 2026-10-26 / 本人の決定 / 縮める列に収まらない値があるときは、何も変えずに止める（上の planner の案のとおり）。該当する行は件数を絞らず、すべて（表・列・上限・行の ID・今の長さ）を、アップデーターの画面（CLI では標準出力）と `evo()->logEvent()`（種別 3、コンテキストに行の一覧）の両方に出す。
- 2026-10-26 / 本人の決定 / `upload_maxsize` を移さないこと、古いプラグイン 2 つを有効なら無効にすること、`emailsender` をロール ID 1 の管理者のうち ID が最も小さい人のアドレスにすること、消えた表を残し `doc_encoding` と古い形の `site_revision` を消すことは、上の planner の案のとおりとする。
- 2026-10-26 / planner / インストーラの中で使う定数とログ: `MODX_CORE_PATH`・`MODX_MANAGER_PATH` は、`install/index.php` が読み込む `document.parser.class.inc.php` から `manager/includes/initialize.inc.php`（30・34 行）で定義されるので、インストーラの中でも使える。ログは AGENTS.md のとおり `evo()->logEvent()` を使う。インストーラの中では `evo()->config` が空のことがあり、そのときは `logEvent()` が `getSettings()` を呼んでキャッシュを作り直す。1.0.0J 以降の DB なら起動時の読み込みで止まらないことは #585 で確かめてあり、`getSettings()` の例外は `logEvent()` の中で受けている。インストーラは最後にキャッシュを消すので、途中で作られたキャッシュは残らない。M5 の検証で、1.0.0J のフィクスチャからアップデーターを通し、`temp/logs/system/` に書かれることを確かめる。
- 2026-10-26 / planner / FCKeditor の判定は、`which_editor` を `FCKeditor` と大文字小文字を区別せずに比べる。FCKeditor のプラグインはこのリポジトリの履歴に無い（`git log --all -- assets/plugins/fckeditor` が空。外部の配布物）ので、登録名をタグから確かめることはできない。v2.1.1 の雛形のファイルがあり、かつ選んでいる値が `FCKeditor` のときだけ戻すので、ほかのエディタを選んでいる利用者には影響しない。
- 2026-10-26 / planner / 公開側の判定は、リクエストごとに `glob()` 1 回と、記録の表への SELECT 1 回になる。今も `settings_version` の SELECT を 1 回しているので、負担は同じ程度とする。記録の表が無い（ランナー導入前の DB）ときは、エラーで止めず（`db()->query($sql, false)` とエラー番号 1146 の確認）、すべて未適用と見なす。
- 2026-10-26 / planner / 古い版のコードは PHP 8.4 で動かせない。1.0.0J〜1.0.15J は `mysql_*` 関数を使い（mysqli は 1.0.16J から）、PHP 7.0 以降には無い。そのため古い版の DB は、そのタグの CREATE TABLE と最小のデータから作るフィクスチャで再現する（M2）。1.2.0J〜1.2.3J は、今の検証環境で実際に入れて上げる手順（`environment.md` の「1.2 系で検証するとき」）も行う。1.0.16J〜1.1.0J を PHP 7.x のイメージで実際に入れるのは、手間に比べて得るものが少ない（スキーマはフィクスチャで同じに作れる）ので行わず、その理由を検証の記録に書く。
- 2026-10-26 / M1 自己レビュー / ランナー（`run()`）はキャッシュを消さず、呼び出し側（CLI の `migrate`、インストーラ）が消す。`evo()->clearCache()` は `system_cache` を TRUNCATE し、表の無い DB（1.0.20J より前）では `messageQuit()` から `exit` して処理ごと終わるため。アップデーターでは `create_tables.sql` より前にランナーを流すので、ランナーの中では消せない。「表があれば消す」の条件で避けると判定が増えるので、呼び出し側に寄せた。
- 2026-10-26 / M1 自己レビュー（2 回目） / CLI の `migrate` は、流す前に `EvoMigrator::requiresInstaller()`（baseline のファイルがあり、まだ記録されていない。記録の表が無い DB も含む）を確かめ、true なら何も流さずに「アップデーター（install/）で上げる」旨を出して終了コード 1 で止まる。古い版からはアップデーターだけで上げる（本人の決定）ので、CLI だけで baseline を流すと、イベント名の同期・utf8mb4 への変換・同梱の要素の入れ直しを通らないまま `hasPending()` が false になり、二度とアップデーターへ案内されなくなるため。いったん `system_cache` の有無で判定したが、1.0.20J〜1.2.3J の DB には表があって CLI で baseline まで流れるので改めた。新規インストール（`markAllApplied()`）とアップデーターを通したサイトは baseline が記録済みなので、開発中に足したマイグレーションは CLI で流せる。baseline のファイルがまだ無い（M3 より前）ときは止めない。`requiresInstaller()` は記録を読めないとき例外を投げる（CLI は終了コード 1。M6 で管理画面の案内に使うときは呼ぶ側で受ける）。M3・M4 の検証で古い DB に流すときは、ガードを通さない `upgrade-fixture.js migrate`（M2）を使う。失敗の文と終了コードは `clearCache()` より前に決める。`migrate:status` も ID の誤りなどの例外を捕まえて終了コード 1 にする。
- 2026-10-26 / PR #933 の Codex のレビュー（P1） / アップデーターは、マイグレーションを流したあと、`create_tables.sql`・イベント名の同期・utf8mb4 への変換・config の書き込み・要素の入れ直しがすべて成功してから、流したものをまとめて記録する（`run(false)` と `recordApplied()`）。すぐに記録すると、あとの手順が失敗しても `hasPending()` が false になり、更新が終わっていないのに公開側の停止が解けるため（以前は最後に書く `install_version` で止めていた）。判定は `hasPending()` の 1 つのまま保つ。代わりに、アップデーターが途中で失敗すると、その回に流したマイグレーションも次のアップデーターで流し直す（マイグレーションは冪等に書く決まりなので壊れない）。新規インストールの `markAllApplied()` も同じ位置に移した（途中で失敗した新規インストールは、公開側が止まり、アップデーターを通し直すと解ける）。流してから記録するまでのあいだはロックを持たない（同時に動いても冪等で、記録は `INSERT IGNORE`）。CLI の `migrate` は今までどおり 1 本ずつ記録する。
- 2026-10-26 / M7 の通しの確認（メインの判断） / MariaDB 10.4・MySQL 5.7・8.0 で utf8mb3 のサイトを上げると、インストーラの `convert2utf8mb4()` が text 系の列の型を 1 段広げ、新規インストールと型が違う列（11 列）が残る。4 バイトの文字で容量が足りなくならないための DB の動きで、型を戻すと値が切れるおそれがあるので、差として受け入れて文書に書く（MariaDB 11 では広がらない）。
- 2026-10-26 / M6 自己レビュー（メインの判断。本人の就寝中のため、あとで確かめてもらう） / ガードのファイル名を `update_guard.php` に変えたので、`version_mismatch_guard.php` の `evo_guard_version_mismatch()` を呼ぶ古いルートの `index.php` が残ったサイトは、公開側が 503 ではなく 500 になる（FTP でのアップロードの途中や、`index.php` に手を入れて上書きしないサイト）。リリース済みの 1.2.0J〜1.2.3J の `index.php` も古いガードを読み込む（M7 の通しの確認で一度「影響しない」と記録したが、確かめた環境に古いガードのファイルが残っていたためで、最後の自己レビューでタグの中身から誤りと分かり、確かめ直した）。上書きでアップロードして古いガードのファイルが残っていれば 500 にはならないが、古い判定（`settings_version` と版の比較）のまま動き、新しい判定は効かない（未適用があっても 503 にならない。逆に `settings_version` が古いままなら、設定を保存するまで 503 が続く）。どちらも `index.php` を差し替えれば解消する。互換のために古いファイル名と関数を残す案もあったが、本人の方針「互換を重ねず進める」に合わせて残さない。代わりに `assets/docs/migrations.md` とリリースノートに「ルートの `index.php` も必ず差し替える」と書く（M7）。出さなくなった版の食い違いの印（`supplementalNav__version-label--mismatch`）のスタイルは、同梱テーマ 6 つから消した（凍結中の RevoStyle には無かった）。docmanager の使われていない `settings_version` の読み込みも消した。
- 2026-10-26 / M3 自己レビュー（メインの判断。本人の就寝中のため、あとで確かめてもらう） / 索引の長さの上限が 767 バイトの InnoDB 表（ROW_FORMAT が COMPACT・REDUNDANT。MySQL 5.6 の頃に作られた表など）を utf8mb4 にしたサイトでは、`alias varchar(245)`（980 バイト）全体の `aliasidx` を作れない。baseline は、前置き長の索引を外す前に作れるかを判定し（`EvoMigrationHelper::fullIndexFits()`。1 文字は、あとでインストーラが utf8mb4 に変換するので 4 バイトで見積もる）、作れなければまず `ROW_FORMAT=DYNAMIC` にしてから判定し直す（`useDynamicRowFormat()`）。それでも作れない環境（`innodb_large_prefix` が無効など）では、前置き長の索引を残して種別 2 のログで知らせ、最終形との差として扱う。止まって流し直せなくなるよりも、差を記録して先へ進むほうを選んだ。1 文字を 4 バイトで見積もるので、COMPACT の InnoDB で utf8mb3 のまま全体の索引（735 バイト）が使えている DB でも、DYNAMIC にできなければ `alias(191)` に短くする（あとでインストーラが utf8mb4 へ変換したときに失敗しないため。変換は利用者が選ぶもので、変換しない利用者は索引を短くされるだけになる）。`DYNAMIC` にできたかは ALTER の成否ではなく、ALTER のあとの `ROW_FORMAT` で判定し、`innodb_file_format` が Antelope なら ALTER を流さない。DYNAMIC の上限は `innodb_page_size` から `min(3072, ページ × 3 / 16)` とする。utf8mb4 で 767 バイトを超える索引列は、今の定義では `site_content.alias` だけ（`*_names.name` は 191 文字で 764 バイト）。`system_cache` の 245 文字の列も 980 バイトになるが、`create_tables.sql` が毎回作り直す表なので baseline の対象外（サーバーの既定の行形式が COMPACT だと `create_tables.sql` の側が失敗する。以前からの問題）。
- 2026-10-26 / M2 自己レビュー（メインの判断。本人の就寝中のため、あとで確かめてもらう） / フィクスチャは utf8mb3 に加えて utf8mb4 でも作れるようにし、M3・M7 の確認に 1.0.0J・1.1.0J・1.2.3J の utf8mb4 版を加える。1.1.0J 以降のインストーラは `convert2utf8mb4()` を流すので、実際のサイトの多くは utf8mb4 に変換済みで、前置き長の付いた索引（1.0.0J の `alias(250)`）など、baseline が向き合う形が utf8mb3 だけでは再現できないため。既定は今までどおり utf8mb3。
- 2026-10-26 / M2 / `install_version` の行は、どのリリースのタグのインストーラも書いていない（main の d98d88609 から）。フィクスチャに入れるのは、baseline がこの行を消すことを確かめるため。
- 2026-10-26 / 本人の決定 / 日本語版は mbstring があることを前提にする。ログに入れる SQL は `mb_strimwidth()` で切り、mbstring の有無で分けない。動作要件への明記と、本体に残る `function_exists('mb_...')` の分岐の整理は #932 で行う（この ExecPlan の範囲外）。
- 2026-10-26 / M1 / マイグレーションのファイルは、変数を持たない `private static function load()` で読み込み、雛形も `static function` にする。ファイルの変数がランナーの変数（記録する ID など）を書き換えたり、関数が `$this` に結び付いたりしないようにするため。
- 2026-10-26 / M1 / ID の上限は `EvoMigrator::MAX_ID_LENGTH`（100。記録の表の列と同じ値）。超える ID があれば流す前に止める。`make:migration` の説明は 84 文字まで。時刻の無い ID は `00000000_baseline` だけを読む（同じ日の時刻つきの ID との並び順が直感と合わないため）。
- 2026-10-26 / M1 / `hasPending()` は、記録の表が 1146 以外のエラーで読めないときも例外を投げずに true（待ちあり）を返し、ログは残さない。確かめられないときは止める側に倒す。公開側で毎回呼ぶので、ログを残すと増えすぎるため。
- 2026-10-26 / M1 / 失敗のログは 1 回だけ。補助は失敗をコンテキストつきの `EvoMigrationException`（`context()`）で投げ、ランナーがそれに ID を足して 1 回だけ記録する。マイグレーション側はログせずに投げるだけにする。例外の文とログに入れる SQL は先頭 200 文字までにし、値を含む SQL（設定の保存、重複の除去）は値を含まない説明だけを出す（SMTP のパスワードなどの設定値や利用者のデータを画面とログに出さないため）。
- 2026-10-26 / M1 / `removeDuplicates()` は重複の組ごとにトランザクションで DELETE と INSERT を行い、失敗したら ROLLBACK する。MyISAM の表では戻せないことをコードに書いた。キーの比べ方は `<=>`（NULL を含む組でも正しく合う）。
- 2026-10-26 / M1 / `modifyColumn()` は、MariaDB が既定値を引用符つきで返す差と、MySQL 8.0.19 以降で整数の表示幅を出さない差を吸収して比べる。`addIndex()` は列の並び・一意かどうか・種類（FULLTEXT など）・前置き長を比べ、違えば作り直す。実機での差は M3 で確かめる。
- 2026-10-26 / planner / マイルストーンは 7 つにし、この ExecPlan で最後まで行う。インストーラ統合と、散らばった版依存の処理を残さないことは範囲に入れる。次の段階（非対象）は Plan of Work の「非対象と次の段階」に書く。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

用語:

- **マイグレーション**: DB を 1 段階だけ新しい形にする処理。1 本が PHP ファイル 1 つで、SQL だけでなく設定値の書き換えなどの PHP の処理も書ける。適用したものは記録の表に 1 行ずつ残り、2 回は流れない。
- **ランナー**: まだ適用していないマイグレーションを、ファイル名の順に流して記録する仕組み（`EvoMigrator`）。
- **baseline**: ランナーを入れる前の DB を、今の最終形にそろえる最初のマイグレーション（`00000000_baseline.php`）。今の `upgrades.php`・`upd_db_structure.php`・`fix_settings.php` などの役を引き継ぐ。
- **最終形**: 今の版を新規インストールした直後の DB の形（`install/sql/create_tables.sql` と、ランナーの記録の表）。
- **冪等**: 何度実行しても、1 回目のあとは何も変わらないこと。
- **アップデーター**: アップグレードとして動くときのインストーラ（`install/`）。`manager/includes/config.inc.php` があり、主要な表がそろっていればアップグレードになる（`install/functions.php` の `isUpGradeable()`）。実行には管理画面へのログインが要る。
- **フィクスチャ**: 検証のために作る、古い版の DB の中身（SQL ファイル）。

今の版は `1.3.0rc2`（`manager/includes/version.inc.php` 2 行）。main は b686bb12c。

今、版依存のアップグレード処理がある場所と、この計画での扱い:

| 場所 | 中身 | 扱い |
| --- | --- | --- |
| `manager/includes/upgrades/upgrades.php` | 版の条件付きの設定・データの直し（毎回のものを含む） | 条件を直して baseline へ。ファイルは消す（M4・M5） |
| `manager/includes/upgrades/upd_db_structure.php` | 列の追加、毎回の MODIFY、主キーの付け替え（`syncPrimaryKey()`）、`type` の推定 | 最終形との差として baseline へ。ファイルは消す（M3・M5） |
| `install/sql/fix_settings.php` | 権限の列の追加、`name` 191、NOT NULL 化、`error_reporting`、不要な設定の行の削除、ロール ID 1、イベント名 | スキーマは `create_tables.sql` と baseline、1 回だけの直しは baseline、ロール ID 1 は `default_settings.sql` と baseline、イベント名は `install/sql/system_eventnames.sql`。ファイルは消す（M3〜M5） |
| `install/instprocessor.php` 235〜245 行 | 古い形の `site_revision` を消す | baseline へ（M3・M5） |
| `install/instprocessor.php` 379〜400 行 | `site_id` の作り直し、`site_url` の削除、`cleanupNullStrings()` | baseline へ（M4・M5） |
| `install/instprocessor.php` 439〜464 行 | `settings_version` の補完 | 消す（M5） |
| `install/instprocessor.php` 360〜367 行・466〜470 行 | `install_version` の記録 | 消す（M6）。既にある行は baseline が消す（M4） |
| `install/functions.php` 345〜369 行 | `cleanupNullStrings()` | baseline へ移して消す（M4・M5） |
| `install/functions.php` 956 行 | `convert2utf8mb4()` | インストーラに残す（版に依存しない、利用者が選ぶ変換） |
| `manager/includes/default.config.php` 125〜137 行 | `settings_version` による分岐 | 撤去（M4） |
| `manager/actions/tool/mutate_settings.dynamic.php` 23〜25 行 | 設定画面のトリガー | 消す（M5）。94 行の案内は「一度も保存していない」ときだけにする（M6） |
| `manager/includes/version_mismatch_guard.php` | 公開側の 503（`settings_version`・`install_version` で判定） | `manager/includes/update_guard.php` に名前を変え、未適用のマイグレーションで判定（M6） |
| `manager/includes/extenders/ex_managerapi.php` 32〜44 行 | `isUpdatePending()`（`install_version`） | 未適用のマイグレーションがあるか（`EvoMigrator::hasPending()` を返すだけ）（M6） |
| `manager/index.php` 95〜117 行 | 管理画面の入口でアップデーターへ送る | `install/` が無いときの表示を足す（M6） |
| `manager/actions/main/welcome.static.php` 10〜19 行 | 版が違えば設定画面へ | 一度も保存していない（`'0'` か空）ときだけ設定画面へ（M6） |
| `manager/frames/menu.php` 339〜357 行 | `settings_version` を表示し、違えば印を付ける | コードの版を表示（M6） |
| `manager/processors/save_settings.processor.php` 180 行 | `settings_version` を保存 | 変えない（「保存した版」として残す） |
| `manager/includes/cli/commands/health-check.php` 31 行 | `settings_version` を表示 | コードの版と、未適用のマイグレーションの件数（M6） |
| `manager/actions/report/sysinfo.static.php` 41・111 行、`manager/includes/mysql_dumper.class.inc.php` 157 行、`manager/includes/backup_manager.class.inc.php` 73 行、`manager/actions/tool/bkmanager.static.php` 57 行 | `settings_version` を版として表示・名前に使う | コードの版にする（M6） |

アップデーターの今の流れ（`install/instprocessor.php`）: 231〜245 行で古い `site_revision` を消し、253 行で `create_tables.sql`（`CREATE TABLE IF NOT EXISTS` だけ。540 行で `system_cache` は消して作り直す）、新規なら 255〜262 行で既定の設定、266〜269 行で `fix_settings.php`、271〜274 行で utf8mb4 への変換、353〜401 行で新規とアップグレードで分かれた設定の処理、403〜409 行で同梱の要素の入れ直し、439〜471 行で `settings_version` と `install_version`、488 行でキャッシュの削除。そのあと管理者は `manager/actions/main/welcome.static.php` で設定画面（`a=17`）へ送られ、`mutate_settings.dynamic.php` 23 行で `upgrades.php` が動いていた。

DocumentParser への影響: 公開側の判定は `index.php` 108〜111 行（`evo()->isFrontend()` のとき、`executeParser()` より前）で行う。M6 で中身が変わるが、呼ぶ位置は変えない。`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` には影響しない。

キャッシュ: ランナーはキャッシュを消さない。消すのは呼び出し側で、CLI の `migrate` は 1 本でも適用したか失敗したときに `evo()->clearCache()` を呼び、インストーラは最後に消す（488 行）。

1.0.0J の新規インストールの定義（`git show release-1.0.0J:install/setup.sql` の 1〜548 行）と今の最終形の差（2026-10-26、MariaDB 11 に読み込んで `INFORMATION_SCHEMA` で比べた結果。今の最終形は `create_tables.sql` に `name` の 191 を足したもの）:

列の追加:

| 表 | 列 | 最終形 |
| --- | --- | --- |
| `site_content` | `alias_visible` | `int(2) NOT NULL DEFAULT '1'`（末尾） |
| `site_htmlsnippets` | `published` / `pub_date` / `unpub_date` | `int(1) NOT NULL DEFAULT '1'` AFTER `description` / `int(20) NOT NULL DEFAULT '0'` AFTER `published` / `int(20) NOT NULL DEFAULT '0'` AFTER `pub_date` |
| `site_plugins` | `error_reporting` | `varchar(8) NOT NULL DEFAULT 'inherit'` AFTER `properties` |
| `site_snippets` | `error_reporting` | `varchar(8) NOT NULL DEFAULT 'inherit'` AFTER `properties` |
| `site_templates` | `parent` | `int(10) NOT NULL DEFAULT '0'` AFTER `content` |
| `user_attributes` | `street` / `city` | `varchar(255) NOT NULL DEFAULT ''` AFTER `country` / AFTER `street` |
| `web_user_attributes` | `street` / `city` | 同上 |
| `user_roles` | `move_document` / `remove_locks` / `view_schedule` | `int(1) NOT NULL DEFAULT '0'` AFTER `save_document` / AFTER `export_static` / AFTER `remove_locks` |

1.0.x の途中の版では、ほかに `user_roles` の `edit_chunk`・`new_chunk`・`save_chunk`・`delete_chunk`・`empty_trash`・`view_unpublished`・`import_static`・`export_static`・`publish_document` が無いことがある（`upd_db_structure.php` と `fix_settings.php` が足していた）。`site_plugin_events.priority`、`site_tmplvar_templates.rank`、`site_content` の `publishedon`・`publishedby`・`link_attributes` は 1.0.0J にあるが、存在確認つきで足す処理は残す（1.0.0J より前から上げてきたサイトに欠けていることがあるため。アップデーターは `priority` が無い DB を受け付けない）。

列の変更:

| 表 | 列 | 1.0.0J | 最終形 |
| --- | --- | --- | --- |
| `active_users` | `id` | `int(10) NULL DEFAULT NULL` | `int(10) NOT NULL DEFAULT '0'`（NULL を 0 にしてから） |
| `active_users` | `ip` | `varchar(20)` | `varchar(50) NOT NULL DEFAULT ''` |
| `documentgroup_names` | `name` | `varchar(255)` | `varchar(191) NOT NULL DEFAULT ''`（長さを確かめてから） |
| `documentgroup_names` | `private_memgroup` / `private_webgroup` | `tinyint(4) NULL DEFAULT 0` | `tinyint(4) NOT NULL DEFAULT '0'`（NULL を 0 にしてから） |
| `membergroup_names` | `name` | `varchar(255)` | `varchar(191) NOT NULL DEFAULT ''` |
| `webgroup_names` | `name` | `varchar(255)` | `varchar(191) NOT NULL DEFAULT ''` |
| `site_content` | `alias` | `varchar(255) NULL DEFAULT ''` | `varchar(245) NULL DEFAULT ''`（長さを確かめてから） |
| `site_content` | `template` | `DEFAULT '1'` | `int(10) NOT NULL DEFAULT '0'` |
| `site_templates` | `content` | `mediumtext NULL` | `mediumtext NOT NULL`（NULL を空にしてから） |
| `site_tmplvar_contentvalues` | `value` | `text` | `mediumtext` |
| `user_attributes` | `comment` | `varchar(255) NOT NULL DEFAULT ''` | `text NULL` |
| `web_user_attributes` | `comment` | `varchar(255) NOT NULL DEFAULT ''` | `text NULL` |
| `web_user_attributes` | `country` | `varchar(5)` | `varchar(25) NOT NULL DEFAULT ''` |

索引:

| 表 | 1.0.0J | 最終形 |
| --- | --- | --- |
| `site_content` | `KEY id (id)`、`FULLTEXT content_ft_idx`、`typeidx` 無し | `id` と `content_ft_idx` は無し、`KEY typeidx (type)` |
| `site_plugin_events` | 主キー無し（1.0.6J は `(evtid)`） | `PRIMARY KEY (pluginid, evtid)` |
| `site_tmplvar_contentvalues` | FULLTEXT 無し | `FULLTEXT KEY value_ft_idx (value)` |
| `system_settings` | `UNIQUE KEY setting_name` | `PRIMARY KEY (setting_name)`（UNIQUE は外す） |
| `user_settings` | 主キー無し（1.0.6J は `(setting_name)`） | `PRIMARY KEY (user, setting_name)`、`KEY setting_name`、`KEY user` |
| `web_user_settings` | 主キー無し（1.0.6J は `(setting_name)`） | `PRIMARY KEY (webuser, setting_name)`、`KEY setting_name`、`KEY webuserid` |
| `member_groups` | UNIQUE 無し | `UNIQUE KEY ix_group_member (user_group, member)` |
| `web_groups` | UNIQUE 無し | `UNIQUE KEY ix_group_user (webgroup, webuser)` |
| `site_tmplvar_templates` | `PRIMARY KEY (tmplvarid, templateid)` | 同じ（それより古いサイトの `idx_tmplvarid`・`idx_templateid` は消す） |

表: `site_revision`（1.0.14J から）・`system_cache`（1.0.20J から）・`{prefix}migrations` は 1.0.0J に無い。前の 2 つは `create_tables.sql`、最後はランナーが作る。

## Plan of Work

### ランナーと補助（M1）

`manager/includes/migrator.class.inc.php` に `EvoMigrator`、`manager/includes/migration_helper.class.inc.php` に `EvoMigrationHelper` を置く（既存の `backup_manager.class.inc.php` の `EvoBackupManager` と同じ並び）。マイグレーションのファイルは `manager/includes/migrations/` に置き、ランナーのコードは置かない（読むファイルの規則を単純にするため）。ディレクトリが無ければ、マイグレーションは 0 本として扱う。

`EvoMigrator` の役割は、ファイルの一覧、適用済みの一覧、未適用の一覧、未適用を流して記録すること、すべてを適用済みとして記録すること、記録の表を作ることだけ。マイグレーション 1 本は `return function (EvoMigrationHelper $m) { ... };` で、ランナーは `require` した関数に補助を渡して呼ぶ。CLI の `migrate` では、1 本が例外なく終わったら、すぐにその 1 行を記録する（途中で止まっても、次はその続きから）。アップデーターでは `run(false)` で流すだけにし、アップデーターの必須の処理がすべて成功したあとで `recordApplied()` でまとめて記録する（PR #933 の Codex のレビュー。Decision Log）。ロックは `GET_LOCK()`（名前は DB 名と接頭辞から作る）で取り、`RELEASE_LOCK()` は `finally` で必ず行う。

`EvoMigrationHelper` は、存在確認（表・列・索引・主キー）、それに基づく変更（列を足す、最終形と違えば MODIFY、主キーをそろえる、UNIQUE を付ける、重複行を消す）、システム設定の読み書き（`system_settings` を `db()` で直接読み書きする）、ログ（`evo()->logEvent(0, 種別, 文, 'Migration', コンテキスト配列)`）を持つ。システム設定を `evo()->config` や `evo()->regOption()` で扱わないのは、インストーラ・CLI・管理画面のどこから呼んでも同じに動かすため（`regOption()` は `getSettings()` を呼び、インストーラの中では前提が違う）。DB に渡す値は、SQL を組み立てる直前で `db()->escape()` か `(int)` にする。表と列の名前は baseline に書いた固定の文字列だけを渡し、リクエストの値は渡さない。

### baseline のスキーマの部（M3）

`manager/includes/migrations/00000000_baseline.php` の前半。上の Context の差の表を、次の順で行う。すべて存在確認か、最終形との比較をしてから実行する。

1. 長さの確認（何も変えない）: `site_content.alias` が 245 文字を超える行、`documentgroup_names`・`membergroup_names`・`webgroup_names` の `name` が 191 文字を超える行があれば、該当する行をすべて（表・列・上限・行の ID・今の文字数）`evo()->logEvent()`（種別 3、コンテキストに行の一覧）に残し、同じ一覧を入れた例外を投げて止める。ランナーは例外の文をそのまま返し、アップデーターの画面と CLI の出力に出す（本人の決定）。
2. 古い表・列の片付け: `site_revision` に `elmid` が無ければ表を消す。`site_templates.doc_encoding` があれば消す。
3. 列の追加: Context の表と、途中の版で欠けることのある `user_roles` の列（`fix_settings.php` 36〜47 行と同じ定義と AFTER）・`site_plugin_events.priority`・`site_tmplvar_templates.rank`・`site_content` の `publishedon`・`publishedby`・`link_attributes`。`user_roles` の列を足したときは、その列をロール ID 1 だけ 1 にする（データの部の表の 1 行目。足した列の一覧は補助が返す）。
4. NULL の片付けと列の変更: `active_users.id`・`documentgroup_names.private_*` の NULL を 0 に、`site_templates.content` の NULL を空にしてから、Context の「列の変更」の表を、型・NULL 可否・既定値が違うときだけ MODIFY する（コメントは `create_tables.sql` と同じにする）。
5. 重複行の除去と、主キー・UNIQUE・索引: 下の表の順。
6. FULLTEXT: `value_ft_idx` が無ければ作り、かかった秒数をログに残す。`content_ft_idx` と `site_content` の `id` 索引があれば消す。`typeidx` が無ければ作る。
7. `type` の推定: `type=''` の行だけ、`LIKE` で `reference`（`content` が `http://` か `https://` で始まる）、`text/xml`（`alias` が `.rss` か `.xml` で終わる）、`text/javascript`（`.js`）、`text/css`（`.css`）、残りを `document` / `text/html` にする（`upd_db_structure.php` 191〜195 行と同じ順）。

| 表 | 付けるキー | 重複したとき残す行 | 付け方 |
| --- | --- | --- | --- |
| `site_plugin_events` | `PRIMARY KEY (pluginid, evtid)` | `priority` が最も小さい行 | 主キーの付け替え（外すと付けるを 1 本の ALTER） |
| `site_tmplvar_templates` | `PRIMARY KEY (tmplvarid, templateid)` | `rank` が最も小さい行 | `idx_tmplvarid`・`idx_templateid` を消してから付け替え |
| `system_settings` | `PRIMARY KEY (setting_name)` | 今のコードが読んだときに効いている値（`WHERE` で選んだ結果の最後の行。`ORDER BY` を付けずに読み、最後の値で上書きしていく今の読み方と同じ） | `UNIQUE setting_name` を外す操作と 1 本の ALTER |
| `user_settings` | `PRIMARY KEY (user, setting_name)` と `KEY setting_name`・`KEY user` | 同上（`manager/includes/user_settings.inc.php` 13 行の読み方） | 付け替え。索引は無ければ足す |
| `web_user_settings` | `PRIMARY KEY (webuser, setting_name)` と `KEY setting_name`・`KEY webuserid` | 同上 | 同上 |
| `member_groups` | `UNIQUE KEY ix_group_member (user_group, member)` | `id` が最も小さい行 | UNIQUE を足す |
| `web_groups` | `UNIQUE KEY ix_group_user (webgroup, webuser)` | `id` が最も小さい行 | UNIQUE を足す |

`create_tables.sql` は、`documentgroup_names`・`membergroup_names`・`webgroup_names` の `name` を `varchar(191) NOT NULL DEFAULT ''` にする（M3）。これで `fix_settings.php` 49〜65 行のスキーマの直しは不要になる（M5 で消す）。

### baseline のデータの部（M4）

`00000000_baseline.php` の後半。どれも版の条件は置かず、下の「直した条件」が成り立つときだけ変える。変えたときはログ（種別 1、コンテキストに設定名・前の値・新しい値）を残す。

| 元の処理（場所） | 元の条件 | baseline に | 直した条件と中身 |
| --- | --- | --- | --- |
| `update_tbl_user_roles()`（`upgrades.php` 195〜215 行）と `addRolePermissionColumn()`（`fix_settings.php` 27〜47 行） | 前の版が 1.0.20 未満なら、`save_role=1` の全ロールの 12 列を 1 に | 入れる | 列を足したときだけ、その列をロール ID 1 だけ 1 にする（本人の決定 2）。既にある列は触らない |
| ロール ID 1 の追加（`fix_settings.php` 83〜164 行） | 毎回、無ければ入れる | 入れる | ロール ID 1 が無いときだけ、今と同じ値で入れる（ID 1 はコードが特別に扱う。`manager/processors/permission/delete_user.processor.php` 26 行など）。新規インストールは `default_settings.sql` に移す |
| `disableOldCarbonTheme()`（`upgrades.php` 98〜115 行） | 毎回、テーマが古い作りなら既定に | 入れる | 選んでいるテーマ（`system_settings.manager_theme`）のフォルダが無い、`style.php` が無い、`manager/` フォルダか `sysalert_style.php` がある（古い作り）、または `MODxCarbon` で `images/icons/32x` が無いときだけ、`default.config.php` の既定（`RevoClassic`）に戻す。空の値も既定に戻す。同梱のテーマ（RevoStyle を含む）はどれも `style.php` があり、戻されない。テーマ名は `basename($theme) === $theme` で `..` を含まないことだけを確かめ（`.` や空白を含む自作のテーマも判定する）、合わなければ変えずに種別 2 のログ。戻す先の既定のテーマに `style.php` が無ければ、書き込まずに種別 2 のログだけ（M4 の自己レビュー） |
| `disableOldFckEditor()`（`upgrades.php` 117〜132 行） | 毎回、古い FCKeditor の雛形があれば既定に | 入れる | 選んでいるエディタ（`which_editor`）が大文字小文字を区別せずに `FCKeditor` で、`assets/plugins/fckeditor/plugin.fckeditor.tpl` が v2.1.1 のときだけ既定（`TinyMCE7`）に戻す（Decision Log） |
| `removeBundledThemeCopies()`（`upgrades.php` 63〜96 行） | 毎回 | 入れる | 今と同じ（同梱テーマの、`common/` にまとめたファイルの古い写しを消す。消せなければ種別 2 で知らせる）。配布から外したファイルなので、利用者の選択ではない |
| `update_config_default_template_method()`（`upgrades.php` 157〜193 行） | 前の版が 1.0.6 未満なら、`Inherit Parent Template` を無条件に無効化（計算した値は保存していない） | 入れる | 有効な `Inherit Parent Template` があり、`auto_template_logic` の行が無いときだけ、プラグインのプロパティ `inheritTemplate` が `From First Sibling` なら `sibling`、それ以外は `parent` を保存し、プラグインを無効にする（1.0.5J のインストーラの意図。`git show release-1.0.5J:install/instprocessor.php` 145〜166 行）。行があれば、プラグインも設定も触らない |
| `update_config_custom_contenttype()`（`upgrades.php` 140〜155 行） | 前の版が 1.0.6 未満 | 入れる | 保存された値が古い既定の 2 つの一覧のどちらかと同じときだけ、今の既定にする。行が無いときは書かない（`getSettings()` が `default.config.php` の既定を使うので、書くと値がサイトに固定され、今後の既定の変更が届かなくなる。M4 の自己レビュー）。空の値（利用者が空にしたかもしれない）は変えない |
| `disableLegacyPlugins()`（`upgrades.php` 134〜138 行） | 前の版が 1.0.7 未満 | 入れる | `Bindings機能の有効無効`・`Bottom Button Bar` が有効なら無効にする（1.0.7J で役目を終えたプラグインで、今のコードと組み合わせる理由が無い）。無効にした ID をログに残す |
| `delete_actionphp()`（`upgrades.php` 239〜252 行） | 1.0.4 < 前の版 < 1.0.7 | 入れる | サイトの直下の `action.php` が、2012 年の脆弱な版（`$_GET['include']` と `MODX_API_MODE` を含み、`if(strpos($path,MODX_MANAGER_PATH)!==0)` を含まない）のときだけ消す。利用者が置いた別の `action.php` は消さない |
| `updateTopMenu()`（`upgrades.php` 254〜268 行） | 毎回 | 入れる | 値が古い既定（`home,preview,refresh_site,search,add_resource,add_weblink`）と同じときだけ、今の既定にする |
| `validate_referer` の削除（`upgrades.php` 222 行） | 前の版が 1.0.5 未満 | 入れる | 行があれば消す（CSRF トークンに置き換わり、今のコードは読まない） |
| `upload_maxsize` を空に（`upgrades.php` 224〜226 行） | 前の版が 1.0.5 未満で `'1048576'` | 入れない | 1 MB を選んだ利用者と見分けられず、古い既定のままでも動く |
| `emailsender` の置き換え（`upgrades.php` 227〜229 行） | 前の版が 1.0.5 未満で `you@example.com` | 入れる | 値が `you@example.com` のときだけ、ロール ID 1 の管理者のうち ID が最も小さい人のメールアドレスにする（セッションに頼らず、CLI でも同じ結果にするため）。見つからなければ変えずに種別 2 で知らせる |
| `use_udperms` の設定（`upgrades.php` 231〜236 行） | 前の版が 1.0.5 未満なら、文書グループの有無で上書き | 入れる | `use_udperms` の行が無いときだけ、`document_groups` に行があれば `1`、無ければ `0` を保存する。行があれば利用者の選択なので触らない |
| 不要な設定の行の削除（`fix_settings.php` 73〜81 行。#611・#615・#640） | 毎回 | 入れる | 今と同じ（`user_settings` の `csrf_token`・`save_action`・`blockedcheck`・`tinymce7_template_*`、`system_settings` の `csrf_token` を消す）。設定ではない値が入っていたもの |
| `site_id` の作り直し（`instprocessor.php` 379〜391 行） | アップグレードのたび | 入れる | 値が無いか、配布物の既定値（`MzGeQ2faT4Dw06+U49x3`）のときだけ作り直す |
| `site_url` の削除（`instprocessor.php` 393〜396 行） | アップグレードのたび | 入れる | 行があれば消す（今の「サイトのURL」は `fixed_site_url`） |
| `install_version` の行（`instprocessor.php` 360〜367・466〜470 行が書いていた） | — | 入れる | 行があれば消す（判定に使わなくなり、読む所が無い。本人の決定 2 を受けた planner の判断） |
| 同梱物（プラグインなどのコード）の入れ替え | — | 入れない | 同じアップデーターの中で、ランナーのあとに `prc_ins*` が利用者の選択どおり入れ直すため（Decision Log）。これからの版で必ず要る入れ替えは、その版のマイグレーションに書く |
| `cleanupNullStrings()`（`install/functions.php` 345〜369 行） | アップグレードのたび | 入れる | 今と同じ（文字列 `'NULL'` を空に、`&NULL=undefined;;` をプロパティから除く）。BINARY で比べ、何度流しても同じ |
| チャンクの非公開（`default.config.php` 125〜137 行） | `settings_version` が 1.0.5J-r11〜1.0.6J-r1 | 入れない（撤去） | Decision Log のとおり |

既定値は `$defaults = include MODX_CORE_PATH . 'default.config.php';` で受け取る（`include_once` は使わない）。M4 で `default.config.php` の分岐を撤去する。

### インストーラへの組み込み（M5）

`install/instprocessor.php` の流れを次にする（行は今の位置）。

1. 231〜245 行の `site_revision` の削除を消す（baseline へ移した）。
2. 251 行の `$tablesBeforeInstall` の控えのあと、アップグレードなら `create_tables.sql` の前で `EvoMigrator::run()` を呼ぶ。失敗したら、画面にマイグレーションの ID とエラーを出し（新しい言語の文字列 `migration_failed`）、`$errors` を足し、`log_install_event()` に残して、この先（`create_tables.sql` 以降）に進まずに終わる（DB をそれ以上変えない）。成功したら、適用した ID の一覧を `log_install_event()` に残す。
3. 253 行の `create_tables.sql` のあと、新規なら既定の設定（今と同じ）、どちらでも `system_eventnames.sql` を流す。
4. 264〜269 行の `fix_settings.php` の読み込みを消す。新規インストールでは、要素の入れ直しなどアップデーターの必須の処理がすべて成功したあとで `EvoMigrator::markAllApplied()` を呼ぶ（baseline などを流さず、適用済みとだけ記録する。本人の決定 1）。アップグレードでは同じ位置で `recordApplied()` を呼ぶ。新規で失敗したときの表の片付け（291〜311 行）は、記録の表も消す対象になる（控えより後に作るため）。
5. 271〜274 行の utf8mb4 への変換は残す。
6. 378〜401 行（アップグレードの `site_id`・`site_url`・`cleanupNullStrings()`）を消す。新規の `site_id`・`install_version`・`fixed_site_url`（353〜377 行）は残す（`install_version` は M6 で消す）。
7. 439〜464 行の `settings_version` の補完を消す。466〜470 行の `install_version` の記録は M5 では残し、M6 で消す（Decision Log）。

インストーラの中で、ランナーと補助を読み込むには `require_once MODX_CORE_PATH . 'migrator.class.inc.php';` を使う（`MODX_CORE_PATH` はインストーラでも定義済み。Decision Log）。ログは `evo()->logEvent()` で書き、M5 の検証で `temp/logs/system/` に書かれることを確かめる。書かれないときは止まって報告する（別の書き方を作らない）。マイグレーションが失敗したときは、ランナーが返した文（長さの確認で止まったときは該当する行の一覧）を `hsc()` してアップデーターの画面に出す。

M5 では、`manager/includes/upgrades/`（2 ファイル）と `install/sql/fix_settings.php` を消し、`mutate_settings.dynamic.php` 23〜25 行のトリガーを消す。

M5 と M6 の間は、公開側の判定がまだ `settings_version` を見るので、アップグレード後に設定を保存するまで公開側は 503 のまま（今と同じ）。M6 で解ける。

### 判定をそろえる（M6）

| 場所 | 役割 | 変更後に見るもの |
| --- | --- | --- |
| `manager/includes/update_guard.php`（`version_mismatch_guard.php` の名前を変える。`index.php` 109 行も） | 公開側を 503 にする | `EvoMigrator::hasPending()` だけ |
| `manager()->isUpdatePending()`（`ex_managerapi.php`） | 管理画面の入口で止める | `EvoMigrator::hasPending()` だけ（`install_version` は見ない。本人の決定） |
| `manager/index.php` 97〜117 行 | 止めたときの行き先 | settings 権限があり `install/index.php` があればアップデーターへ。`install/` が無ければ、settings 権限のある人には「アップデーター（`install/`）を置いて実行してください」（新しい言語の文字列 `update_upload_installer_message`。見出しは今の `update_in_progress_title`）、ほかの人には今のメンテナンスの表示（本人の決定） |
| `install/instprocessor.php` 360〜367・466〜470 行 | `install_version` の記録 | 消す（Decision Log） |
| `welcome.static.php` 10〜19 行 | 初回の設定の案内 | `settings_version` が `'0'` か空のときだけ設定画面へ |
| `mutate_settings.dynamic.php` 94 行 | 設定画面の案内文 | 同上 |
| `menu.php` 339〜357 行 | 版の表示 | `$modx_version`（DB を読まない。食い違いの印は、入口で止めるので不要になり消す） |
| `health-check.php` 31 行 | CLI の確認 | `CMS version` は `$modx_version`。`Database migrations` を足し、未適用が 0 件なら OK、あれば件数と ID（最大 5 件）で NG |
| `sysinfo.static.php` 41・111 行、`mysql_dumper.class.inc.php` 157 行、`backup_manager.class.inc.php` 73 行、`bkmanager.static.php` 57 行 | 版の表示・名前 | `$modx_version`（`manager/includes/version.inc.php` を読む） |

`.claude/skills/adaptive-validation-runner/references/environment.md` の「インストール直後は `settings_version` が `0` のまま」の項も、新しい動き（公開側は未適用のマイグレーションで判定、初回だけ設定画面へ案内）に書き直す。

### 文書（M7）

`assets/docs/migrations.md` を新しく作り、アップグレードの流れ（アップデーターの順）、マイグレーションの書き方（ID の付け方、`return function (EvoMigrationHelper $m)`、存在確認を省かない、表を作るのは `create_tables.sql` だけ、`create_tables.sql` を変えたら同じ変更のマイグレーションも足す、版の条件を置かない、利用者の選択を上書きしない、DB にある同梱物の入れ替えが要る版はその版のマイグレーションに書く・書き方は `prc_insPlugins.inc.php` の作りに合わせる）、アップデート待ちの判定が未適用のマイグレーションだけであること（マイグレーションを含まない版はアップデーターへ送られない）、CLI（`migrate`・`migrate:status`・`make:migration`）、検証のしかた（M2 の道具）を書く。`AGENTS.md` のドキュメントマップに 1 行足し、`assets/docs/release-process.md` のリリース前の確認に「新しいマイグレーションを古い版の DB から確かめた」と「DB にある同梱物（プラグインのコードなど）を変えた版は、入れ替えのマイグレーションを足した」を足す。

### 非対象と次の段階

- テーブルのエンジン（古いサイトの MyISAM）を InnoDB にそろえること。
- 消えた表（`event_log` など）とサンプルの `jot_*` を片付けること。
- 1.0.14J の形の `site_revision` の中身を残すこと（今と同じく消す）。
- インストーラの要素の入れ直しで、同梱プラグインのイベントの紐付けと同梱 TV のテンプレートの割り当てが戻ること（`assets/docs/core-issues.md` に記録）。
- 1.0.0J より古い DB（アップデーターが受け付けない。`install/functions.php` の `isUnsupportedOldDb()`）。
- CLI だけでアップグレードを済ませること（ファイルの差し替え・`create_tables.sql`・イベント名の同期を含む）。#668（`evo system-upgrade`）で、この計画のランナーを呼ぶ形で行う。
- `parse_docblock()` を `manager/includes/` へ移し、同梱物を入れ替えるマイグレーションから使えるようにすること。初めてそういうマイグレーションが要る版で行う（Decision Log）。
- `settings_version` を別の名前の設定に置き換えること。

## Concrete Steps

検証は Docker の検証環境で行う（`.claude/skills/adaptive-validation-runner/references/environment.md`）。並走するセッションがあるので、名前とアドレスを分ける。以下では `EVO_VALIDATE_NAME=evo-validate-667 EVO_BIND_IP=127.0.0.6` とし、コンテナは `evo-validate-667-app`・`evo-validate-667-db`、作業用ディレクトリ（リポジトリの外）を `<work>` と書く。CLI はホストから `docker exec -w /var/www/html evo-validate-667-app php evo <コマンド>` で実行する（`docker compose exec app php evo ...` と同じ）。確認のコマンドの名前は `manager/includes/cli/commands/` のファイル名の `-` を `:` にしたもの（`health-check.php` は `health:check`）。変えた PHP には毎回 `docker exec evo-validate-667-app php -l <ファイル>` を流す。

各マイルストーンの書き込んでよいファイルは、下に挙げたものだけ。挙げていないファイルを変える必要が出たら、止まって報告する。

### M1: ランナー・補助・CLI

書き込んでよいファイル:

- `manager/includes/migrator.class.inc.php`（新規）
- `manager/includes/migration_helper.class.inc.php`（新規）
- `manager/includes/cli/commands/migrate.php`・`migrate-status.php`・`make-migration.php`（新規）
- `manager/includes/cli/README.md`

作業: Interfaces and Dependencies の API のとおり作る。`make:migration <説明>` は `manager/includes/migrations/<YYYYMMDD_HHMMSS>_<説明>.php` に雛形（`return function (EvoMigrationHelper $m) { };` と、存在確認を省かない旨のコメント）を作る。説明が `^[a-z0-9_]+$` に合わなければ終了コード 1。`migrate` は適用した ID を 1 行ずつ出し、未適用が無ければ `Nothing to migrate.`、失敗したら ID とエラーを出して終了コード 1。`migrate:status` は `applied <ID> <日時>` と `pending <ID>` を並べ、最後に件数を出す。

確かめ方:

    EVO_VALIDATE_NAME=evo-validate-667 EVO_BIND_IP=127.0.0.6 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build
    EVO_VALIDATE_NAME=evo-validate-667 EVO_BIND_IP=127.0.0.6 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor
    docker exec -w /var/www/html evo-validate-667-app php evo migrate:status

期待: 記録の表が無いことと `0 applied, 0 pending` が出る（ディレクトリにまだファイルが無い）。続けて、tester が一時のマイグレーション `manager/includes/migrations/20991231_000000_cli_check.php`（`$m->exec('SELECT 1');` だけ）を置き、`migrate:status` で `pending 20991231_000000_cli_check`、`migrate` で `Applied 20991231_000000_cli_check`、もう一度 `migrate` で `Nothing to migrate.`、`db:query "SELECT * FROM modx_migrations"` で 1 行。わざと失敗する一時のマイグレーション（`$m->exec('SELECT * FROM no_such_table');`）では終了コード 1、記録は増えず、`log:search "no_such_table" --level=error` に 1 件。別のプロセスでロックを持ったまま（`docker exec evo-validate-667-db mariadb -uuser -ppassword evodb -e "SELECT GET_LOCK('<ランナーのロック名>', 0), SLEEP(40)"` を裏で流す）`migrate` を流すと、30 秒待ってロックを取れない旨で終了コード 1。`make:migration add_x` でファイルができ、`make:migration Add-X` は終了コード 1。一時のファイルは最後に消し、`migrations` の表の行も消す。

推奨コミット: `feat(migration): マイグレーションのランナーと CLI を追加`

### M2: 古い版の DB を作る道具とスキーマの比べ方

書き込んでよいファイル:

- `.claude/skills/adaptive-validation-runner/scripts/upgrade-fixture.js`（新規）
- `.claude/skills/adaptive-validation-runner/scripts/upgrade-fixture-data.sql`（新規）
- `.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`（DB のイメージを `${EVO_DB_IMAGE:-mariadb:11}` で変えられるようにする）
- `.claude/skills/adaptive-validation-runner/references/upgrade-fixtures.md`（新規）
- `.claude/skills/adaptive-validation-runner/SKILL.md`（参照先に 1 行足す）

作業: `upgrade-fixture.js` は `lib.js` の docker の補助を使い、次の副コマンドを持つ。

- `build <tag>`: `git show release-<tag>:<path>` でそのタグの CREATE TABLE の定義を取り出す（1.0.0J〜1.0.6J は `install/setup.sql` の CREATE TABLE、1.0.7J〜1.0.16J は `install/sql/both_createtables.sql`、1.0.17J 以降は `install/sql/create_tables.sql`。`git grep -l "CREATE TABLE" release-<tag> -- install` で確かめた）。`{PREFIX}` を `modx_` に、`TYPE=MyISAM` を `ENGINE=MyISAM` に、`{TABLE_OPTION}` を `DEFAULT CHARSET=utf8mb3` にし（古いサイトの文字セットを再現する。`{TABLE_OPTION}` の無い 1.0.16J までは `ENGINE=` の直後に足す）、`upgrade-fixture-data.sql` を足して `<work>/fixture-<tag>.sql` に書く。`--charset utf8mb4` を付けると、utf8mb4（`utf8mb4_general_ci`。`convert2utf8mb4()` と同じ）で作り、`<work>/fixture-<tag>-utf8mb4.sql` に書く（utf8mb4 へ変換済みのサイトの再現）。データの `settings_version` はそのタグの版にする。
- `load <file>`: 検証用の DB の `modx_` の表をすべて消してから、ファイルを流し込む。管理者（`manager_users` の ID 1）のパスワードの値は、流し込む前の DB（`setup-site.js` で入れた今の版）のものに置き換え、`admin` / `Passw0rd!2026` でログインできるようにする。
- `migrate`: アップデーターと同じく、CLI のガード（`requiresInstaller()`）を通さずに作業ツリーのランナーを流す。アプリのコンテナで CLI の起動処理（`manager/includes/cli/bootstrap.php`）を読み込んでから `(new EvoMigrator())->run()` を呼び、適用した ID を 1 行ずつ、失敗したら ID とエラーを出して終了コード 1 にする。キャッシュは消さない（`system_cache` の無い DB で止まらないように。M3・M4 では次の `create-tables` のあとで消す必要も無い）。M3・M4 で、M5 より前にアップデーターの順を手で再現するため。M5 からはアップデーターが行う。
- `create-tables`: 作業ツリーの `install/sql/create_tables.sql` を、インストーラと同じ置き換えで流す（M3・M4 で、アップデーターの順を手で再現するため。M5 からはアップデーターが行う）。
- `snapshot <out>`: `INFORMATION_SCHEMA.COLUMNS`（型・NULL 可否・既定値・`EXTRA`）と `STATISTICS`（索引の名前・一意・種類・列の並び）を、`modx_` の表について 1 行 1 項目の TSV に書く（比べない項目は Decision Log のとおり）。
- `diff <ref> <out>`: 2 つの TSV を比べ、違う行と、`<ref>` に無い表（残す表）を分けて出す。違いが無ければ終了コード 0。

`upgrade-fixture-data.sql` は、1.0.0J から今まである列だけを使い、次を入れる: `settings_version`・`manager_theme`（`MODxCarbon`）・`which_editor`（`TinyMCE7`）・`site_id`（配布の既定値）・`site_url`・`emailsender`（`you@example.com`）・`custom_contenttype`（古い既定）・`topmenu_site`（古い既定）・`validate_referer`・`upload_maxsize`（`1048576`）・`csrf_token`・`install_version`（main を入れたサイトにある行の再現。どのリリースのタグのインストーラも書かない。main の d98d88609 から）、管理者（ロール 1）と、`empty_trash=0` のロール 1・`save_role=1` で一部の権限が 0 のロール 2、`type=''` の文書（`http://example.com/` の中身、`feed.xml` の alias）、alias が文字列 `NULL` の文書、`content` が NULL のテンプレート、有効な `Inherit Parent Template`（`&inheritTemplate=...;list;From Parent,From First Sibling;From First Sibling`）と `Bottom Button Bar`、プロパティに `&NULL=undefined;;` を含むスニペット、`site_plugin_events`・`member_groups`・`web_groups`・`user_settings`（同じ利用者・名前で値の違う 2 行）・`web_user_settings` の重複行、`user_settings` の `csrf_token`。どの表の重複が何件かを `upgrade-fixture.md` に書く。

`upgrade-fixtures.md` には、使い方、タグとファイルの対応、古い版のコードを動かさない理由（Decision Log の PHP の版の話）、比べない項目、`EVO_DB_IMAGE=mariadb:10.4` などで古い DB の版を試す方法と、MySQL 公式イメージは Docker Engine 29 系で取れないことを書く。

確かめ方（2 行目以降の `...upgrade-fixture.js` は、1 行目と同じ環境変数と `node` のパスを付けて実行する）:

    EVO_VALIDATE_NAME=evo-validate-667 EVO_BIND_IP=127.0.0.6 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/upgrade-fixture.js snapshot <work>/ref-main.tsv
    ...upgrade-fixture.js build 1.0.0J
    ...upgrade-fixture.js load <work>/fixture-1.0.0J.sql
    ...upgrade-fixture.js snapshot <work>/v100.tsv
    ...upgrade-fixture.js diff <work>/ref-main.tsv <work>/v100.tsv

期待: 違いの一覧が、Context の「1.0.0J と今の最終形の差」の表と同じ項目になる（この時点の最終形は `name` が 191。新規インストールで `fix_settings.php` が動くため）。`migrate` は、一時のマイグレーション（`$m->exec('SELECT 1');` だけ）を置いた 1.0.0J のフィクスチャで `Applied` を出して終了コード 0、`system_cache` の無い DB でも止まらない（一時のファイルと記録の行は最後に消す）。`build` は 1.0.0J・1.0.5J・1.0.6J・1.0.7J・1.0.10J・1.0.14J.r9・1.0.16J・1.0.17J・1.0.20J・1.0.26J・1.1.0J・1.2.0J・1.2.1J・1.2.2J・1.2.3J のすべてで、`load` がエラー無しで終わる。終わったら `setup-site.js --reinstall` で今の版に戻す。

推奨コミット: `test(validation): 古い版の DB を作る道具とスキーマの比較を追加`

### M3: baseline のスキーマの部

書き込んでよいファイル:

- `manager/includes/migrations/00000000_baseline.php`（新規）
- `manager/includes/migration_helper.class.inc.php`（足りない補助の追加）
- `install/sql/create_tables.sql`

作業: 実装の前に、途中の版だけにあった列・表が Decision Log の一覧のほかに無いかを確かめる。

    git -C <worktree> grep -n -i "ADD COLUMN\|ADD INDEX\|ADD UNIQUE\|ADD PRIMARY\|CREATE TABLE\|DROP COLUMN\|DROP TABLE\|CHANGE \`" release-1.0.0J release-1.0.5J release-1.0.6J release-1.0.7J release-1.0.8J release-1.0.9J release-1.0.10J release-1.0.14J.r9 release-1.0.15J release-1.0.16J release-1.0.17J release-1.0.18J release-1.0.19J release-1.0.20J release-1.0.21J release-1.0.22J release-1.0.23J release-1.0.24J release-1.0.25J release-1.0.26J release-1.1.0J release-1.2.0J release-1.2.1J release-1.2.2J release-1.2.3J -- install manager/includes/upgrades

期待: 今の最終形に無いもので、Decision Log に無いもの（列・表・索引）が見つからない。見つかったら、扱いを決めずに止まって報告する（planner が Decision Log に足す）。

続けて、Plan of Work の「baseline のスキーマの部」の 1〜7 を書き、`create_tables.sql` の 3 つの `name` を 191 にする。

確かめ方（アップデーターの順を手で再現する。`load` のあと `upgrade-fixture.js migrate`、`create-tables`。CLI の `php evo migrate` は baseline が未適用の DB では止まるので、止まることの確認にだけ使う）:

    ...upgrade-fixture.js load <work>/fixture-1.0.0J.sql
    docker exec -w /var/www/html evo-validate-667-app php evo migrate
    ...upgrade-fixture.js migrate
    ...upgrade-fixture.js create-tables
    ...upgrade-fixture.js snapshot <work>/after-1.0.0J.tsv
    ...upgrade-fixture.js diff <work>/ref.tsv <work>/after-1.0.0J.tsv
    docker exec -w /var/www/html evo-validate-667-app php evo log:search "Migration" --limit=20

`<work>/ref.tsv` は、M3 の作業ツリーで `setup-site.js --reinstall` した直後の `snapshot`（この時点では、新規インストールは `fix_settings.php` をまだ通るので、`name` は 191 のまま。`create_tables.sql` の変更後も同じになること自体が確認の 1 つ）。

期待: 最初の `php evo migrate` は、baseline が未適用なので何も流さずに、アップデーターで上げる旨を出して終了コード 1（記録の表も作られない）。`diff` の違いは 0 件で、残す表（1.0.0J なら `event_log`・`keyword_xref`・`site_content_metatags`・`site_keywords`・`site_metatags` とサンプルの表）だけが出る。ログに、重複行を消した件数が表ごとに出る（M2 で書いた件数と同じ）。`user_settings` で残った値が、フィクスチャで後に入れた行の値と同じ。M2 の 15 個のタグのすべてで同じ結果になる。

2 回目: `db:query "DELETE FROM modx_migrations"` で記録を消し、DB の一般ログを有効にして（`docker exec evo-validate-667-db mariadb -uroot -ppassword -e "SET GLOBAL general_log=1; SET GLOBAL log_output='TABLE'"`）もう一度 `upgrade-fixture.js migrate` を流す。期待: `mysql.general_log` に `ALTER`・`UPDATE`・`DELETE`・`INSERT`（記録の 1 行を除く）が 1 本も無い。今の main の新規インストール（`setup-site.js --reinstall` 直後、記録の表が無い）に `upgrade-fixture.js migrate` を流しても同じ（baseline は最終形を変えない）。終わったら一般ログを戻す。

長さの確認: 1.0.0J のフィクスチャに alias が 250 文字の文書を 25 件と、`name` が 200 文字の文書グループを 1 件足して `upgrade-fixture.js migrate` を流す。期待: 終了コード 1。出力に、該当する 26 行がすべて（`site_content.alias`・上限 `245`・ID・`250`、`documentgroup_names.name`・上限 `191`・ID・`200`）出る。`log:search "245" --level=error` で同じ一覧がコンテキストに入った 1 件が見つかる。`snapshot` が流す前と同じ（何も変わっていない）。

utf8mb4 へ変換済みのサイト: 1.0.0J・1.1.0J・1.2.3J を `build --charset utf8mb4` で作り、同じ確認を行う（1.0.0J の utf8mb4 版には、前置き長の付いた `aliasidx (alias(250))` が出る。`alias` を 245 に縮めたあと、最終形の索引に直ること）。

古い DB の版: `EVO_DB_IMAGE=mariadb:10.4` で環境を作り直し、1.0.0J と 1.2.3J のフィクスチャで同じ確認を行う（取れない・動かないときは理由を記録する）。

推奨コミット: `feat(migration): baseline でスキーマを新規インストールの形にそろえる`

### M4: baseline のデータの部

書き込んでよいファイル:

- `manager/includes/migrations/00000000_baseline.php`
- `manager/includes/migration_helper.class.inc.php`（設定の読み書きの補助が足りなければ）
- `manager/includes/default.config.php`（125〜137 行の撤去）

作業: Plan of Work の「データの部」の表のとおり書く。

確かめ方: 1.0.0J と 1.0.5J のフィクスチャで `load` → `upgrade-fixture.js migrate` → `create-tables` のあと、`db:query` で次を確かめる。

    docker exec -w /var/www/html evo-validate-667-app php evo db:query "SELECT setting_name, setting_value FROM modx_system_settings WHERE setting_name IN ('manager_theme','which_editor','auto_template_logic','custom_contenttype','topmenu_site','emailsender','upload_maxsize','use_udperms','site_id','site_url','validate_referer','csrf_token')"
    docker exec -w /var/www/html evo-validate-667-app php evo db:query "SELECT id, save_role, empty_trash, view_schedule, move_document, remove_locks FROM modx_user_roles"
    docker exec -w /var/www/html evo-validate-667-app php evo db:query "SELECT name, disabled FROM modx_site_plugins"

期待: `manager_theme` は `RevoClassic`（フィクスチャの `MODxCarbon` は無いので戻る）。`which_editor` は `TinyMCE7` のまま。`auto_template_logic` は `sibling` で、`Inherit Parent Template` と `Bottom Button Bar` は `disabled=1`。`custom_contenttype`・`topmenu_site` は今の既定。`emailsender` は管理者のアドレス。`upload_maxsize` は `1048576` のまま。`use_udperms` はフィクスチャの値のまま。`site_id` は既定値から変わり、`site_url`・`validate_referer`・`csrf_token`・`install_version`（フィクスチャに入れておく）の行は無い。`site_plugins` の行数と `plugincode` は流す前と同じ（baseline は同梱物を入れ替えない）。ロール 1 の `empty_trash` は 0 のまま（1.0.0J に列があった）。1.0.5J で足りなかった列（`view_schedule` など。M2 で確かめた欠け）はロール 1 だけ 1、ロール 2 は 0。文字列 `NULL` の alias とプロパティの `&NULL=undefined;;` は消えている。

利用者の選択が残ること: フィクスチャを変えて（`manager_theme` を `SoftSlate`、`which_editor` を `none`、`auto_template_logic` の行を `parent` で入れる、`topmenu_site` を利用者が並べ替えた値にする）流すと、どれも変わらない。

ファイル: tester が `manager/media/style/RubberWhite/welcome.php`（空のファイル）と、2012 年の脆弱な版の `action.php`（`git show 6aca49d69:action.php`）をサイトの直下に置いて流すと、どちらも消え、ログに残る。中身の違う `action.php`（`<?php echo 1;`）は消えない。置いたファイルは最後に消す。

2 回目: 記録を消して `upgrade-fixture.js migrate` で流し直すと、`system_settings`・`user_roles`・`site_plugins` を `db:export --tables=...` で書き出した内容が流す前と同じ。

推奨コミット: `feat(migration): baseline に設定とデータの移行を入れる`

### M5: インストーラへの組み込みと撤去

書き込んでよいファイル（install/ の PHP はメインがマージ前に並走セッションへ照会する）:

- `install/instprocessor.php`
- `install/functions.php`（`cleanupNullStrings()` を消す）
- `install/langs/english.inc.php`・`install/langs/japanese-utf8.inc.php`（`migration_failed`）
- `install/sql/fix_settings.php`（削除）
- `install/sql/system_eventnames.sql`（新規。`fix_settings.php` 166 行の REPLACE をそのまま移す）
- `install/sql/default_settings.sql`（ロール ID 1 の行を足す。値は `fix_settings.php` 83〜157 行と同じ）
- `manager/includes/upgrades/upgrades.php`・`upd_db_structure.php`（削除。ディレクトリも無くなる）
- `manager/actions/tool/mutate_settings.dynamic.php`（23〜25 行の削除）

M4 から引き継いで確かめること: アップデーターの中で baseline の `evo()->parseProperties()`（`auto_template_logic` の判定）が動くこと。テーマの古い写しや `action.php` を Web サーバーの権限で消せないときに、種別 2 のログが `temp/logs/system/` に残ること。

作業: Plan of Work の「インストーラへの組み込み」のとおり。あわせて、ランナーを呼ぶ前に `ignore_user_abort(true)` と `set_time_limit(0)` を呼ぶ（baseline の MODIFY や FULLTEXT の追加は表を作り直すので、大きなサイトでは数分かかり、Web サーバーやプロキシの時間切れで要求が切られることがある。冪等なので流し直せば進むが、利用者には失敗に見える。M3 の自己レビュー）。

確かめ方:

新規インストール:

    EVO_VALIDATE_NAME=evo-validate-667 EVO_BIND_IP=127.0.0.6 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor --reinstall
    docker exec -w /var/www/html evo-validate-667-app php evo migrate:status
    docker exec -w /var/www/html evo-validate-667-app php evo db:count system_eventnames
    docker exec -w /var/www/html evo-validate-667-app php evo db:query "SELECT id, name FROM modx_user_roles WHERE id=1"

期待: `applied 00000000_baseline` と `0 pending`。イベント名の件数は M4 の作業ツリーで入れたときと同じ。ロール 1 がある。`snapshot` を M3 の `ref.tsv` と比べて違いが 0 件（`fix_settings.php` が無くても同じ形になる）。この `snapshot` を以後の正（`<work>/ref-final.tsv`）にする。

フィクスチャからのアップグレード: `load <work>/fixture-1.0.0J.sql` のあと、Playwright でアップデーターを最後まで進める（`admin` でログイン → `install/` → アップグレード）。期待: 結果の画面にエラーが無い。`migrate:status` が `applied 00000000_baseline`。`snapshot` を `ref-final.tsv` と比べて違いが 0 件（残す表だけ）。インストールのログ（`temp/logs/install/`）に適用した ID がある。`temp/logs/system/` に重複行の件数のログがある（インストーラの中から `evo()->logEvent()` が書けている）。もう一度アップデーターを通すと、DB は変わらない（M3 と同じく一般ログで確かめる）。

失敗: M3 の「長さの確認」と同じ 26 行を入れたフィクスチャでアップデーターを通すと、画面にマイグレーションの ID と、該当する 26 行がすべて（表・列・上限・ID・文字数）出て、`create_tables.sql` 以降に進まない（`snapshot` が流す前と同じ）。インストールのログと `temp/logs/system/` にも同じ一覧が残る。

撤去の確認:

    git -C <worktree> grep -n "upgrades/\|fix_settings\|cleanupNullStrings" -- manager install

期待: 何も出ない。

推奨コミット: `refactor(install): アップデーターからマイグレーションを流し、散らばった更新処理を消す`

### M6: 判定をそろえる

M5 から引き継いで確かめること（M5 の自己レビュー）:

- `install_version` の書き込みを消したあと、今の main で新規インストールした DB に baseline を流しても何も変えない（M4 の Surprises の、M6 までの例外が解消される）。
- `isUpdatePending()` を `hasPending()` に替えたあと、マイグレーションが失敗した（記録されていない）サイトで、settings 権限のある人はアップデーターへ、ほかの人はメンテナンスの表示になる。
- アップデーターがマイグレーションの失敗で止まったあと（失敗の画面のあと `session_destroy()` される）、もう一度ログインしてアップデーターを開き直し、値を直してから通すと最後まで進む。

書き込んでよいファイル:

- `.claude/skills/adaptive-validation-runner/scripts/upgrade-fixture-data.sql`（`site_name` など、上げたあとの管理画面が開くのに要る基本の設定を足す。M5 の Surprises）
- `manager/includes/version_mismatch_guard.php` → `manager/includes/update_guard.php`（名前の変更と中身）
- `install/instprocessor.php`（`install_version` の記録を消す。install/ の PHP なので、メインがマージ前に並走セッションへ照会する）
- `index.php`（109 行の読み込み先）
- `manager/includes/extenders/ex_managerapi.php`
- `manager/index.php`
- `manager/includes/lang/english.inc.php`・`manager/includes/lang/japanese-utf8.inc.php`
- `manager/actions/main/welcome.static.php`
- `manager/actions/tool/mutate_settings.dynamic.php`
- `manager/frames/menu.php`
- `manager/includes/cli/commands/health-check.php`
- `manager/actions/report/sysinfo.static.php`・`manager/includes/mysql_dumper.class.inc.php`・`manager/includes/backup_manager.class.inc.php`・`manager/actions/tool/bkmanager.static.php`
- `.claude/skills/adaptive-validation-runner/references/environment.md`

作業: Plan of Work の「判定をそろえる」の表のとおり。

確かめ方（curl は `Accept-Language` を付ける）:

    curl -s -o /dev/null -w '%{http_code}\n' -H 'Accept-Language: ja' http://127.0.0.6/
    docker exec -w /var/www/html evo-validate-667-app php evo health:check

期待: 新規インストール直後（設定を保存していない）で 200、`health:check` に `CMS version 1.3.0rc2` と `Database migrations OK`。一時のマイグレーションを置くと、公開側が 503、`health:check` が `Database migrations` NG と ID、管理画面にログインすると settings 権限の `admin` はアップデーターへ、`editor` はメンテナンスの表示。`install/` を一時的に別名にすると、`admin` には「アップデーターを置いて実行してください」の表示。`php evo migrate` のあとは公開側が 200 に戻る。新規インストール直後は、ログインするとダッシュボードではなく設定画面（`a=17`）に案内され、保存したあとは案内されない。1.2.3J のフィクスチャからアップデーターで上げたあとは、設定を保存しなくても公開側が 200 で、ダッシュボードは設定画面へ飛ばない。メニューの版の表示が `1.3.0rc2`。

判定が 1 つだけであること: 未適用のマイグレーションが無い状態で、`db:query "REPLACE INTO modx_system_settings VALUES ('install_version','1.2.3J')"` のように古い版の行を入れても、`settings_version` を古い版にしても、公開側は 200 のまま、`admin` はアップデーターへ送られずダッシュボードが開く（マイグレーションを含まない版をファイルだけ差し替えた場合の再現）。新規インストールとアップグレードのあと、`db:query "SELECT * FROM modx_system_settings WHERE setting_name='install_version'"` が `(no rows)`。次のコマンドで、判定に使う所が残っていないことを確かめる。

    git -C <worktree> grep -n "install_version" -- manager install index.php
    git -C <worktree> grep -n "settings_version" -- manager install index.php

期待: `install_version` は baseline の削除だけ。`settings_version` は、保存（`save_settings.processor.php` と、設定画面のフォームの hidden の値 `mutate_settings.dynamic.php` 91 行）と、一度も保存していないかの判定（`welcome.static.php`・`mutate_settings.dynamic.php`）と、`default_settings.sql` の初期値だけ。一時のファイルと別名は最後に戻す。

推奨コミット: `refactor(manager): アップデートの判定を未適用のマイグレーションにそろえる`

### M7: 全版の通しの確認と文書

書き込んでよいファイル:

- `assets/docs/migrations.md`（新規）
- `AGENTS.md`（ドキュメントマップに 1 行）
- `assets/docs/release-process.md`（確認の項目に 1 行）

作業: 文書を書き、Validation and Acceptance の行列をすべて通す。`/doc-audit` を流して問題を直す。

M6 から引き継いで `assets/docs/migrations.md` に書くこと（M6 の自己レビュー）:

- アップデートのときは、`manager/` だけでなくルートの `index.php` も必ず差し替える。消した `version_mismatch_guard.php` を読み込む `index.php`（リリース済みの 1.2.0J〜1.2.3J と、未リリースの main の `index.php`）が残り、古い `version_mismatch_guard.php` のファイルも無いと、公開側が 500 になる（上書きでアップロードして古いガードのファイルが残っていれば、古い判定のまま動く）（互換のファイルは残さない。本人の方針「互換を重ねず進める」）。`release-process.md` のリリースノートの項にも同じ注意を足す。
- 判定は未適用のマイグレーションの有無だけ。ただし `index.php` の、版が同じなら DB を見ずにページのキャッシュを返す早い返し方は、キャッシュの互換のための判定で、未適用があってもアップデートの前に作られたページが返ることがある。
- 未適用のあいだは、settings 権限のある人だけがアップデーターへ送られる。権限のある人がいないサイトでは、DB でロールに `settings` の権限を戻してから、ログインしてアップデーターを通す。
- MariaDB 11 以外（MariaDB 10.4・MySQL 5.7・8.0）では、インストーラの utf8mb4 への変換（`convert2utf8mb4()`。版によらない既存の処理）が text 系の列の型を 1 段広げる（`text` → `mediumtext`、`mediumtext` → `longtext`）ので、utf8mb3 から上げたサイトは新規インストールと型が違う列が残る。4 バイトの文字で容量が足りなくならないための DB の動きで、戻すと値が切れるおそれがあるので戻さない。読み書きには支障が無い。

推奨コミット: `docs(migration): マイグレーションの書き方とアップグレードの流れを書く`

## Validation and Acceptance

最終形（`<work>/ref-final.tsv`）は、M5 以降の作業ツリーで新規インストールした直後の `snapshot`。比べ方は M2 の `upgrade-fixture.js diff`（`INFORMATION_SCHEMA` の比較。比べない項目は Decision Log）。

アップグレードの行列（どれもアップデーターで上げ、上げたあとの `snapshot` を `ref-final.tsv` と比べて違いが 0 件。出てよいのは「残す表」だけ）:

| 元の DB | 作り方 |
| --- | --- |
| 1.0.0J | フィクスチャ（`release-1.0.0J:install/setup.sql` + 最小のデータ） |
| 1.0.5J・1.0.6J・1.0.7J・1.0.10J・1.0.14J.r9・1.0.16J・1.0.17J・1.0.20J・1.0.26J | フィクスチャ（そのタグの CREATE TABLE + 最小のデータ） |
| 1.0.0J に `site_templates.doc_encoding` と 1.0.14J.r9 の `site_revision` を足したもの | フィクスチャ（途中の版を通ってきたサイトの再現） |
| 1.1.0J | フィクスチャ |
| 1.0.0J・1.1.0J・1.2.3J の utf8mb4 版 | フィクスチャ（`build --charset utf8mb4`。utf8mb4 へ変換済みのサイトの再現） |
| 1.2.0J・1.2.1J・1.2.2J・1.2.3J | フィクスチャと、`environment.md` の「1.2 系で検証するとき」で実際に入れたサイト（1.2.3J は必須、ほかはフィクスチャでよい） |
| 今の main（b686bb12c）の新規インストール | main の detached の作業ツリーで入れた DB を、この作業ツリーのコードでアップデーター |

それぞれで次も確かめる:

- もう一度アップデーターを通すと、DB は変わらない（一般ログに、記録の 1 行のほかの変更が無い）。
- 重複行を含むフィクスチャで、残った行が Plan of Work の表の規則どおりで、消した件数がログに出る。
- 利用者が変えた設定（外した権限、選んだテーマ・エディタ、`auto_template_logic`、並べ替えた `topmenu_site`）が残る。
- アップグレードのあと、`smoke.js` が通る（1.2.3J の実サイトと 1.0.0J のフィクスチャ）。

PHP の版: main（1.3 系）は PHP 7.4 以上が対象（1.4 で 8 以上にする予定。本人の方針）。新しいコードは `php -l` だけでなく実際に 7.4 で動かす。標準の環境（php:8.4）での確認に加え、PHP 7.4 の公式イメージ（`php:7.4-apache` に mysqli を足したもの、または `php:7.4-cli`）で、1.0.0J と 1.2.3J のフィクスチャからアップデーターを通し、`php evo migrate`・`migrate:status`・`make:migration` を動かす。動かせない部分があれば理由を記録する。

動かせない版の決まり: 古い版のコードを動かさずにフィクスチャで代えた版、試せなかった DB の版（MySQL 5.7・8.0 は Docker Engine 29 系で公式イメージを取れない）は、理由を `.agent/runs/` の検証の記録と PR の本文に書く。「動かせないので未確認」だけで済ませない。

新しいマイグレーションの追加（運用の確認）: `make:migration check_flow` でファイルを作り、`$m->addColumn('site_content', 'x_check', ...)` を書いてアップデーターを通すと 1 回だけ適用され、`migrate:status` に出る。確かめたらファイルと列を消す。

## Idempotence and Recovery

- CLI の `migrate` は 1 本ずつ記録するので、途中で止まったら、次はそのマイグレーションから流し直す。アップデーターは最後にまとめて記録するので、あとの手順が失敗すると、その回に流したマイグレーションも記録されず、次のアップデーターで全部流し直す（baseline の途中で止まったときや、アップデーターが途中で失敗したときは、アップデーターでやり直す）。DDL は途中で戻らないので、baseline の各手順は存在確認か最終形との比較をしてから実行し、途中まで済んだ状態から流しても最後まで進む。
- baseline が長さの確認で止まったときは、DB は何も変わっていない。値を短くしてからやり直す。
- 同時に 2 回走らないよう、ランナーはロックを取る。取れなければ何もせずに失敗する。
- 検証環境は `setup-site.js --reinstall` で今の版の新規インストールに戻せる。フィクスチャは `upgrade-fixture.js build` で何度でも作り直せる。
- 作業中のマイルストーンを中断したときは、この ExecPlan の Progress と、ブランチの最後のコミットから再開する。

## Artifacts and Notes

- 関連 Issue: #932（動作要件への mbstring の明記と、`function_exists('mb_...')` の分岐の整理。この ExecPlan の範囲外）

- ブランチ: `refactor/667-migrations`（ワークツリー `.claude/worktrees/issue-667`）。PR: #933（下書き。M1 の時点で作成）。
- Issue: https://github.com/modxcms-jp/evolution-jp/issues/667（2026-10-18 のコメントに Laravel を参考にした検討）
- 古い版の定義: `git show release-1.0.0J:install/setup.sql`、`git show release-1.0.7J:install/sql/both_createtables.sql`、`git show release-1.0.17J:install/sql/create_tables.sql` など（タグとファイルの対応は M2）。
- 関連する完了済みの計画: `.agent/plans/archive/2026-10-17-upgrade-data-safety.md`（#654〜#659。ロール ID 1 の扱い、主キーの付け替え、1.2.2J からの通しの確認の手順と落とし穴）。
- 推奨コミットは各マイルストーンの末尾。PR は 1 つにまとめる。

## Interfaces and Dependencies

`EvoMigrator`（`manager/includes/migrator.class.inc.php`）:

    public function all(): array          // ファイルの ID（名前の順）
    public function applied(): array      // ID => applied_at（記録の表が無ければ空）
    public function pending(): array      // 未適用の ID（名前の順）
    public function hasPending(): bool    // アップデート待ちの唯一の判定（公開側・管理画面の入口・health:check）。例外を投げない
    public function run(bool $record = true): array // ['applied' => [...], 'failed' => ID か null, 'error' => 文か null]。false なら流すだけで記録しない（アップデーター）
    public function recordApplied(array $ids): void // まとめて記録する（INSERT IGNORE。重ねて呼んでも壊れない）
    public function requiresInstaller(): bool // baseline のファイルがあり、まだ記録されていなければ true（記録の表が無い DB を含む）。記録を読めなければ例外。CLI の migrate のガード
    public function markAllApplied(): void // 新規インストールだけで使う
    public function ensureTable(): void
    public const MAX_ID_LENGTH = 100;     // ID の上限（記録の表の列と同じ）
    public const BASELINE_ID = '00000000_baseline';

ロックの名前は `'evo_migrations_' . md5(<DB 名> . <接頭辞>)`（47 文字。`GET_LOCK()` の上限 64 文字に収まる）。待つのは 30 秒。記録の表の定義は `CREATE TABLE IF NOT EXISTS {prefix}migrations (id varchar(100) NOT NULL, applied_at int(20) NOT NULL DEFAULT '0', PRIMARY KEY (id))`（文字セットとエンジンは DB の既定に任せる。ほかの表と同じく utf8mb4 への変換の対象になる）。

`EvoMigrationHelper`（`manager/includes/migration_helper.class.inc.php`。マイグレーションの関数に渡す）: `table($name)`、`exec($sql, $summary = null)`（失敗で `EvoMigrationException`。`$summary` を渡すと SQL の代わりにそれを出す）、`rows($sql, $summary = null)`（行の配列）、`tableExists`・`columnExists`・`column`（`INFORMATION_SCHEMA` の 1 行）・`index($table, $index)`（`columns`・`unique`・`type`・`prefix`・`sub_part`）・`indexExists`・`indexColumns`・`primaryKey`、`addColumn($table, $column, $definition): bool`（足したら true）、`modifyColumn($table, $column, $definition, $type, $nullable, $default): bool`（違ったときだけ）、`syncPrimaryKey($table, $columns, $dropIndexes = [])`、`addIndex($table, $name, $columns, $kind)`、`addPrefixIndex($table, $name, $column, $length): bool`（同じ列の前置き長の索引が `$length` 以下で既にあれば何もしない）、`fullIndexFits($table, $column, $chars): array`（全体の索引を作れるか。`fits`・`bytes`・`limit`・`char_bytes`・`engine`・`row_format`・`reason`）、`useDynamicRowFormat($table): bool`（InnoDB の COMPACT・REDUNDANT だけ `ROW_FORMAT=DYNAMIC` にし、変わったことを確かめて true）、`dropIndexIfExists`・`dropColumnIfExists`・`dropTableIfExists`、`removeDuplicates($table, $keyColumns, $keepRule): int`、`setting($name, $default = null)`・`hasSetting($name)`・`saveSetting($name, $value)`・`deleteSetting($name)`、`log($message, array $context = [], $type = 1)`。

`EvoMigrationException`（同じファイル）: `__construct($message, array $context = [], ?Throwable $previous = null)`、`context(): array`。ランナーは失敗のログのコンテキストにこれを入れる。

依存:

- `db()`（`query($sql, false)`・`getLastErrorNo()`・`getLastError()`・`select`・`update`・`insert`・`delete`・`getValue`・`getRow`・`escape`・`config['table_prefix']`）。値は実行の直前で `db()->escape()` か `(int)`。
- `evo()->logEvent()`（コンテキスト配列）。`evo()->clearCache()` はランナーでは呼ばず、呼び出し側（CLI の `migrate`、インストーラ）で呼ぶ。
- インストーラ: `install/instprocessor.php` がランナーを呼ぶ。`install/sqlParser.class.php`（`create_tables.sql`・`system_eventnames.sql` を流す）は変えない。
- CLI: `evo`（`manager/includes/cli/bootstrap.php`）。コマンドは `manager/includes/cli/commands/` のファイル名で決まる。
- 次の計画: #668（`evo system-upgrade`）はこのランナーを呼ぶ。#677（PDO 移行）は補助の `exec()` の中を置き換える。
