# ExecPlan: DBAPI の null の扱いを正す（Issue #540）

## Purpose / Big Picture

利用者が入力欄に「NULL」や「null」とだけ書いた値を、文字列としてそのまま保存できるようにする。あわせて、プログラムが「この列は NULL にしたい」と指示するときは PHP の `null` だけを使う、という一つの規則に揃え、`insert()` と `update()` で結果が食い違う状態をなくす。

## Progress

- [ ] (2026-10-12) M1: 呼び出し側の棚卸しと分類（Artifacts の分類表を埋める）
- [ ] (2026-10-12) M2: 呼び出し側の先行修正（今の DBAPI のままでも結果が変わらない修正だけを先に入れる）
- [ ] (2026-10-12) M3: DBAPI 本体の修正（`escape()` / `update()` / `_insert()`）
- [ ] (2026-10-12) M4: Docker 環境での動作確認（新規インストール・アップデート・管理画面の保存・QuickManager）

## Surprises & Discoveries

- 2026-10-12: `update()` が文字列の「null」を SQL の NULL にする条件は、MySQLi 版 DBAPI を入れたコミット 664b26b3f（2015-09-23、1.0.15J で初リリース）からある。1.0.14J.r9 までの mysql 版 `update()` は、値を常に引用符で囲んでいた。
- 2026-10-12: `escape()` が `null` を文字列 `'NULL'` で返すのは、6ee358b80（2025-01-31）から。PHP 8.1 以降で `escape_string(null)` が出す非推奨警告への対策とみられる。`update()` では古い文字列判定と組み合わさって NULL になるが、`insert()` では文字列「NULL」が保存される、という食い違いはここから生じた。
- 2026-10-12: `where($field, $op, $value)` は `$value === null` のとき 2 引数の形（`$op` を値として扱う）と解釈する（`manager/includes/extenders/ex_dbapi.php:38`）。修正後は `where('x', '=', db()->escape(null))` が `` `x` = "=" `` になる。今は `` `x` = "NULL" `` で、どちらにしても意図した条件ではない。M1 で、この形に `null` が届く経路が無いかを確かめる。
- 2026-10-12: 列の定義を揃える `upd_db_structure.php` は、インストーラではなく、アップデート後に管理画面の「グローバル設定」を開いたときに実行される（`mutate_settings.dynamic.php:22`）。検証手順にこの操作を含める必要がある。

## Decision Log

- 2026-10-12 / yamamoto: `escape(null)` は `null` を返す。配列の中の `null` も `null` のまま返す。Issue #540 のコメントで不採用にした案 2（配列のときだけ `null` を残す）は、単体と配列とで結果が変わるのが問題だった。今回の案は「`null` は常に `null`」の一つの規則なので、DBAPI を単純に保つ方針と矛盾しない。
- 2026-10-12 / yamamoto: `update()` と `_insert()`（`insert()` / `insert_ignore()` / `replace()` の共通処理）は、PHP の `null` だけを引用符なしの `NULL` にする。文字列の「null」は、大文字小文字を問わず、ただの文字列として引用符で囲む。
- 2026-10-12 / yamamoto: `null` が NOT NULL の列に届く呼び出し側は、DBAPI に補正を足さず、呼び出し側で空文字や既定値にしてから渡す。インストーラでは既存の `nullToEmpty()`（`install/functions.php`）を使う。管理画面側は、値を作る箇所（`postv()` の既定値など）で `null` にならないようにし、汎用ヘルパーは増やさない。
- 2026-10-12 / yamamoto: qm の `setLocked()` はロック解除時に `active_users.id` を `null` ではなく `0` にする。コアの管理画面（`manager/includes/accesscontrol.inc.php:81`）は編集対象が無いとき `id` を `0` にしており、ロック判定も `action=27 AND id=…` のように action と id の組で行っていて NULL かどうかを見ていない。qm だけが「文字列 `'NULL'` を渡すと `update()` が NULL にする」という DBAPI の癖に頼って NULL で表現していたので、コアの表現に揃える。代替案の `null` への置き換えは、癖に頼るのはやめられるが、qm だけ表現が違う状態が残るので採らない。
- 2026-10-12 / yamamoto: 「NULL ではなく `0` が自然な値」の列は、列の定義を `NOT NULL DEFAULT '0'` にし、アップグレード時に既存の NULL を `0` に書き換える。対象は `active_users.id`（コアは「対象なし」を `0` で書き、ロック判定も NULL を見ていない。NULL は qm の `setLocked()` が入れたものだけ）と、`documentgroup_names.private_memgroup` / `private_webgroup`（0/1 のフラグで、書き込みは常に真偽式。`access_groups.processor.php:160`、`web_access_groups.processor.php:151`）。どちらも NULL と `0` の意味が同じなので、書き換えても情報は失われない。NULL を許したままにすると、今回の修正後に `null` を渡すコードがあれば黙って NULL が入り、値の表現が二通りに割れる。
- 2026-10-12 / yamamoto: 逆向きの「`0` を NULL に戻す」正規化は行わない。`install/sql/create_tables.sql` で NULL を許す列 27 個のうち、数値の列は上の 3 列と `manager_log.itemid`（varchar、既定値 `'0'`）だけで、いずれも `0` が正しい値である。残りはテキスト系の列で、`0` が誤って入る経路は無い。文字列「NULL」は、インストーラ由来の列を #539 の `cleanupNullStrings()` で片付け済み。管理画面由来のものは、利用者が入力した「NULL」と見分けられないので書き換えない。`update()` が利用者の入力「null」を NULL にしてしまった値は、元の文字列を判別できず復元できない。NULL のままでも PHP 側では空文字とほぼ同じに扱われるので、そのままにする。テキスト系の列を `NOT NULL` にするかは、今回の範囲外とする。
- 2026-10-12 / yamamoto: PDO 移行（ロードマップ「PDO移行」、#453）のフェーズ 2（値のバインド）を待たずに直す。理由は、利用者の入力が保存できない不具合が今あるため。フェーズ 2 でも「PHP の `null` だけが NULL」という規則はそのまま引き継げる。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- **DBAPI**: このCMSのデータベース操作クラス。`db()` で取得する。実体は `manager/includes/extenders/dbapi/mysqli.inc.php` の `DBAPI` クラスだけ（他の実装は無い）。
- **`escape()`**: SQL に埋め込む文字列の特殊文字を無害にするメソッド。配列を渡すと、要素ごとに処理した配列を返す。
- **SQL の NULL と文字列「NULL」**: SQL の NULL は「値が無い」ことを表し、引用符なしで `NULL` と書く。引用符で囲んだ `'NULL'` は 4 文字の文字列で、別物。
- **NOT NULL の列**: SQL の NULL を入れられない列。1 行ずつの INSERT で明示的に NULL を入れると、STRICT モードでなくてもエラーになる。テーブル定義は `install/sql/create_tables.sql` にある。
- **QuickManager（qm）**: サイトの表側からリソースを編集するプラグイン（`assets/plugins/qm/`）。

現在のコード（行番号は 616c9ded5 時点）:

- `manager/includes/extenders/dbapi/mysqli.inc.php` の `escape()`（226 行付近）: `$s === null` なら `'NULL'` を返す。
- 同 `update()`（402 行付近）: `$value === null || strtolower($value) === 'null'` なら引用符なしの `NULL`、それ以外は `'%s'` で囲む。
- 同 `_insert()`（478 行付近）: 配列の値をまとめて `implode("','", array_values($fields))` で連結し、`VALUES('...')` に入れる。生の `null` は空文字、`escape()` 済みの `null` は文字列「NULL」が保存される。INSERT … SELECT の経路と、文字列で `$fields` を渡す経路は値を扱わないので、今回の変更の対象外。
- `install/functions.php:293` の `nullToEmpty()`: #539 / #543 で導入。インストーラの要素登録で、`null` を空文字にしてから `escape()` に渡す。291 行目のコメントは今の `escape()` の挙動を説明しているので、M3 で書き換える。
- `manager/includes/helpers.php:450` の `postv($key, $default = null)`: POST に項目が無いと、既定値の `null` を返す。管理画面の保存処理で `null` が `insert()` に届く主な経路は、これ。
- `assets/plugins/qm/qm.inc.php` の `setLocked()`（429 行付近）: ロック解除時に `$fields['id'] = 'NULL'` を `update()` に渡し、`active_users.id` を SQL の NULL にしている。`active_users` は管理画面にログイン中のユーザーと、いま開いている画面（`action`）・対象 ID（`id`）を記録するテーブルで、`id` は NULL 可の int。ユーザー ID は `internalKey` で、`id` はユーザー ID ではなく、開いている画面の対象（リソースや要素）の ID である。コアは対象が無いとき `0` を入れる（リソースの `parent = 0` が「親なし」を表すのと同じく、このCMSでは ID の `0` を「なし」の意味で使う）。
- `manager/includes/mysql_dumper.class.inc.php` と `assets/snippets/weblogin/weblogin.inc.php` は、`'NULL'` を自前の SQL に直接埋め込んでいる。DBAPI の `insert()` / `update()` / `escape()` を通らないので対象外。
- `manager/includes/upgrades/upd_db_structure.php`: 列の定義をアップグレード時に揃える処理。インストーラでは実行されない。アップデート後に管理画面の「グローバル設定」を開いたとき、`settings_version` と本体のバージョンが違えば `manager/actions/tool/mutate_settings.dynamic.php:22` から `upgrades.php` 経由で毎回実行される。そのため、中の処理は何度実行しても同じ結果になるように書く。インストーラのアップデート時に実行されるのは `install/instprocessor.php` の `cleanupNullStrings()` など。
- テストスイートは無い。検証は `compose.yml` の Docker 環境（`app` サービス、DB は `evodb` の MariaDB 11）で行う。CLI は `php evo <command>`（`manager/includes/cli/commands/`）。

## Plan of Work

DBAPI を正しく直すと、これまで誤った挙動に頼っていた呼び出し側が表に出る。そこで、先に呼び出し側を棚卸しして直し、最後に DBAPI 本体を切り替える順で進める。

M2 で入れる呼び出し側の修正は、今の DBAPI でも修正後の DBAPI でも結果が同じになるものに限る。例外は 2 つある。1 つは qm の `setLocked()` で、ロック解除時の `id` が NULL から `0` に変わる。ただ、ロック判定は action と id の組で行っていて NULL か `0` かを区別していないので、画面の挙動は変わらない。もう 1 つは列の定義の変更で、`active_users.id` などが NOT NULL になる。今の qm は `'NULL'` を渡して NULL を書くので、NOT NULL にした後はエラーになる。qm の修正（手順 4）と列の定義の変更（手順 5）は、必ず同じリリースに入れる。こうしておけば、M3 の DBAPI の修正は単独で切り戻せる。

壊れる可能性がある呼び出し側は 3 種類ある。

1. **`insert()` 系に `null` が届く箇所**: これまでは空文字か文字列「NULL」で保存されていたが、修正後は SQL の NULL が入る。NOT NULL の列ならエラーになる。NULL を許す列なら、保存されるのが空文字から NULL に変わる。実コードで約 60 か所（一覧は Artifacts）。値の作り方を読み、`null` になりうるかを判定する。
2. **`escape()` の結果を引用符なしで SQL に埋め込む箇所**: `null` のとき `id=` のような構文エラーになる。2026-10-12 の grep では見つかっていないが、M1 で再確認する。
3. **文字列「NULL」で SQL の NULL を指示している箇所**: 修正後は文字列が保存されるか、int の列ではエラーになる。2026-10-12 時点では qm の `setLocked()` の 1 か所だけ。

`escape()` の結果を `update()` に渡す箇所は、修正前後ともに `null` が NULL になるので、対応は要らない。`escape()` の結果を引用符の内側に埋め込む箇所は、`null` のとき文字列「NULL」が空文字に変わる。検索条件ではむしろ正しくなる方向なので、これも対応は要らない。

既にデータベースに保存されている文字列「NULL」は、#539 でインストーラ由来の主な列を片付け済み。今回の修正で新しく文字列「NULL」が生まれることは無い。アップグレード時の正規化は、NULL と `0` の意味が同じ 3 列（`active_users.id` と `documentgroup_names` の 2 フラグ）だけを `0` に揃え、列を `NOT NULL DEFAULT '0'` にする。それ以外の列は書き換えない。理由は Decision Log に記す。

## Concrete Steps

M1 棚卸し（作業ディレクトリはリポジトリルート。シェルの `grep` が ugrep の別名になっている環境では `command grep` を使う）

1. `insert()` 系の呼び出しを列挙する。
    command grep -rnE -- "(db\(\)|modx->db|this->db)->(insert|insert_ignore|replace|save)\(" --include=*.php . | command grep -vE "^\./(vendor|\.claude)/"
   期待結果: 約 60 行。各行について、値の配列の作り方を読み、`null` が入りうるか（`postv()` の既定値が `null`、`$row` の値が DB 由来の NULL、関数の戻り値が `null` など）を判定する。入りうる場合は、対象の列が NOT NULL かを `install/sql/create_tables.sql` で確かめる。結果を Artifacts の分類表に「要修正（NOT NULL）／要確認（NULL 可、空文字→NULL に変わる）／対応不要」で記録する。
2. 文字列「NULL」で NULL を指示している箇所を探す。
    command grep -rniE "=>\s*['\"]null['\"]|\]\s*=\s*['\"]null['\"]" --include=*.php . | command grep -vE "^\./(vendor|\.claude)/"
   期待結果: `assets/plugins/qm/qm.inc.php` の 1 件だけ。増えていれば分類表に加える。
3. `escape()` の結果を引用符なしで埋め込む箇所と、`where()` の 3 引数の形に `escape()` を渡す箇所を探す。
    command grep -rnE "escape\(" --include=*.php . | command grep -vE "^\./(vendor|\.claude)/" > /tmp/esc.txt
    command grep -E "[^'\\\\]\"\s*\.\s*(db\(\)|\\\$[a-zA-Z_>-]+)->escape\(|implode\([^)]*escape|where\('[^']+',\s*'[^']+',\s*db\(\)->escape" /tmp/esc.txt
   期待結果: 引用符なしの連結は 0 件（`LIKE '%" . escape . "%'` は引用符の内側なので除く）。`where()` の 3 引数の形はヒットした行ごとに、値が `null` になりうるかを判定する。

M2 呼び出し側の先行修正

4. `assets/plugins/qm/qm.inc.php` の `setLocked()` で、`$fields['id'] = 'NULL'` を `0` に替える（コアの `accesscontrol.inc.php` と同じ表現）。
5. 列の定義を揃える。`install/sql/create_tables.sql` の `active_users.id` を `int(10) NOT NULL DEFAULT '0'`、`documentgroup_names.private_memgroup` / `private_webgroup` を `tinyint(4) NOT NULL DEFAULT '0'` にする。`manager/includes/upgrades/upd_db_structure.php` の `active_users.ip` の ALTER（239 行付近）の近くに、列ごとに「`UPDATE … SET 列=0 WHERE 列 IS NULL`」と「`ALTER TABLE … MODIFY COLUMN …`」をこの順で加える。UPDATE を先にしないと、NULL が残った行で ALTER が失敗する。
6. M1 で「要修正」とした箇所を、値を作る箇所で空文字や既定値にする。インストーラでは `nullToEmpty()` を使う。
7. 変更したファイルごとに構文を確認する。
    docker compose exec app php -l <変更したファイル>
   期待結果: `No syntax errors detected`。

M3 DBAPI 本体の修正（`manager/includes/extenders/dbapi/mysqli.inc.php`）

8. `escape()`: `null` のとき `null` を返す。docblock の `@return` に `null` を加える。配列の再帰はそのまま（要素の `null` も `null` のまま返る）。
9. `update()`: 条件を `$value === null` だけにする。`strtolower($value) === 'null'` は削除する。
10. `_insert()`: 値の連結を `implode` から値ごとの組み立てに替える。`null` は引用符なしの `NULL`、それ以外は今と同じく文字列にして `'%s'` で囲む（整数の `0` は `'0'`、`false` は `''` になり、今の `implode` と同じ結果）。
11. `install/functions.php` の `nullToEmpty()` の説明コメントを、「修正後の `insert()` は `null` を SQL の NULL にするので、NOT NULL の列に入れる値は空文字にしておく」という趣旨に書き換える。

## Validation and Acceptance

Docker 環境（`docker compose up -d`）で、次がすべて満たされれば完了とする。

1. 管理画面でテンプレート変数を新規作成し、キャプションに「NULL」とだけ入力して保存する（`insert()` の経路）。続けて同じテンプレート変数を開き、そのまま保存する（`update()` の経路）。どちらもエラーにならずに保存され、再度開くと「NULL」と表示される。DB でも文字列であることを確かめる。
    docker compose exec app php evo db:query "SELECT caption, caption IS NULL FROM modx_site_tmplvars WHERE caption='NULL'"
   期待結果: `caption` が `NULL`（文字列）、`caption IS NULL` が `0`。テーブル接頭辞は環境に合わせる。
2. リソースのタイトルに「null」と入力して新規作成と更新の両方を行い、どちらも文字列「null」として保存される。
3. 新規インストールを行い、エラーなく完了する。説明行の無いチャンクの `description` が空文字（文字列「NULL」でも SQL の NULL でもない）になっている。
    docker compose exec app php evo db:query "SELECT name FROM modx_site_htmlsnippets WHERE description='NULL' OR description IS NULL"
   期待結果: 0 行。
4. 既存サイトからのアップデートを行い、エラーなく完了する。アップデート前に `active_users.id` が NULL の行を用意しておく（QuickManager で TV を保存すると、今のコードでは NULL になる）。アップデート後に管理画面の「グローバル設定」を開くと `upd_db_structure.php` が実行され、`id` が `0` になり、列が NOT NULL になっている。
    docker compose exec app php evo db:describe active_users
   期待結果: `id` の Null が `NO`、Default が `0`。`documentgroup_names` の 2 列も同じ。「グローバル設定」を保存せずにもう一度開いても（`upd_db_structure.php` がもう一度実行されても）エラーにならない。
5. QuickManager でリソースを表側から開き、TV を保存して閉じる。エラーにならず、`active_users` の該当行の `id` が `0`、`action` が `2` になる。続けて別のユーザーで管理画面にログインして同じリソースを開き、「他のユーザーが編集中」の表示が出ない。
6. M1 で「要修正」「要確認」とした箇所の画面操作（要素・ユーザーの新規作成と保存、リソースの複製など）を行い、PHP のエラーログと DB エラーが出ない。
    docker compose exec app php evo log:tail
   期待結果: 該当操作の時刻に DB エラーが無い。

## Idempotence and Recovery

M1 は読み取りだけなので、何度でもやり直せる。M2 の各修正は、今の DBAPI でも結果が変わらないものに限っているので、途中で止めてもサイトの挙動は変わらない。M2 の列定義の変更は、UPDATE も ALTER も何度流しても同じ結果になる（NULL が無ければ UPDATE は 0 行、同じ定義への ALTER は変化なし）。M3 で変わるのは `mysqli.inc.php` の処理と `install/functions.php` のコメントだけなので、問題が出たら M3 のコミットだけを revert すれば元の挙動に戻る。検証で DB を壊した場合に備えて、検証前に `docker compose exec app php evo db:backup` でバックアップを取る。戻すときは `docker compose exec -e EVO_CLI_IMPORT=1 app php evo db:import <バックアップの SQL ファイル>` を使う（`EVO_CLI_IMPORT=1` が無いと取り込みを拒否する）。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/540
- 関連 PR: #539（要素の登録で文字列「NULL」を保存しない）、#543（チャンクのインストールで説明が「NULL」にならないようにする）
- 想定コミット単位:
    fix(qm): ロック解除で active_users.id をコアと同じ 0 にする
    fix(upgrade): NULL と 0 が同じ意味の列を NOT NULL DEFAULT 0 に揃える
    fix: insert で NOT NULL の列に null が届かないようにする（M1 の結果に応じて画面ごとに分けてよい）
    fix(dbapi): PHP の null だけを SQL の NULL として扱う

M1 分類表（2026-10-12 時点の `insert()` 系の呼び出しの控え。正は手順 1 の grep の結果とし、判定は M1 で埋める）

- `escape()` 済みの配列を渡している箇所: `manager/includes/extenders/ex_docapi.php:38`、`manager/processors/module/save_module.processor.php:103, 219`、`manager/processors/chunk/save_htmlsnippet.processor.php:109`、`manager/processors/tmplvars/save_tmplvars.processor.php:261`
- インストーラ（`nullToEmpty()` 適用済みを含む）: `install/functions.php:372`、`install/processors/prc_insSnippets.inc.php:35`、`prc_insChunks.inc.php:47, 82`、`prc_insTVs.inc.php:34, 75`、`prc_insModules.inc.php:39`、`prc_insTemplates.inc.php:41`、`prc_insPlugins.inc.php:64, 78`
- 管理画面の保存処理: `manager/processors/` 配下の template / snippet / plugin / tmplvars / permission（save_user・save_web_user・save_role・access_groups・web_access_groups）/ document（save_resource・duplicate_content）/ pm / module
- コア: `manager/includes/document.parser.class.inc.php:408, 1483, 1488, 6192`、`traits/document.parser.subparser.trait.php:2698`、`cache_sync.class.php:229`、`log.class.inc.php:50`、`mysql_dumper.class.inc.php:351`、`extenders/ex_docapi.php:57, 262`、`ex_revision.php:134`、`ex_managerapi.php:296`、`ex_documentapi.php:568, 607`、`manager/actions/tool/import_site.static.php:236, 260, 312`
- 同梱プラグイン・モジュール: `assets/plugins/fmp/fmp.class.inc.php:237`、`assets/plugins/qm/qm.inc.php:490`、`assets/plugins/managermanager/widgets/ddautofolders/modx.ddtools.class.php:46, 52`、`assets/modules/docmanager/classes/dm_backend.class.php:270`

## Interfaces and Dependencies

- 公開メソッドの戻り値が変わる: `DBAPI::escape(null)` が `'NULL'` から `null` になる。サードパーティのスニペットやプラグインが `escape()` の結果を引用符なしで SQL に埋め込んでいる場合、`null` のとき構文エラーになりうる。リリースノートに記載する。
- `DBAPI::update()` が文字列の「null」を SQL の NULL にしなくなる。文字列「NULL」で NULL を指示しているサードパーティのコードは、`null` を渡すよう直す必要がある。リリースノートに記載する。
- `DBAPI::insert()` / `insert_ignore()` / `replace()` が、PHP の `null` を空文字ではなく SQL の NULL として保存するようになる。NOT NULL の列に `null` を渡しているサードパーティのコードはエラーになる。リリースノートに記載する。
- `active_users.id` と `documentgroup_names.private_memgroup` / `private_webgroup` が NOT NULL になる。これらに `null` を書き込むサードパーティのコードはエラーになるので、`0` を渡すよう直す必要がある。リリースノートに記載する。
- ロードマップの「マイグレーション機構」（`.agent/plans/2026-05-06-migration-mechanism-modernization.md`、未着手）が先に入った場合は、手順 5 の列の定義の変更を `upd_db_structure.php` ではなく、そのマイグレーションファイルとして書く。
- ロードマップの「PDO移行」（#453）のフェーズ 2 と関係する。今回の規則（PHP の `null` だけが NULL）は、値のバインドに移っても変わらない。
