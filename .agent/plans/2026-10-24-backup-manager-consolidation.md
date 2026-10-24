# ExecPlan: DB バックアップの保存先・世代管理・作成・復元を 1 つのクラスにまとめる（Issue #886）

## Purpose / Big Picture

管理画面・CLI・パーサーの API のどこから作っても、同じ場所に同じ形でバックアップが残り、どこからでも戻せるようにする。あわせて、古い世代を消すときに新しいバックアップや利用者が名前を付けたものを消さないこと、復元が途中で失敗しても直前の状態へ戻せることを保証する。

## Progress

- [ ] (2026-10-24) M1: `EvoBackupManager` クラスと、`Mysqldumper` のファイルへの直接書き出し
- [ ] M2: 管理画面（バックアップ管理・スナップショット・復元）を新しいクラスへ置き換える
- [ ] M3: CLI（`db:backup` / `db:import`）とパーサーの API（`$modx->snapshot()`）を置き換える
- [ ] M4: ドキュメントの更新と Docker での通し確認

## Surprises & Discoveries

- 2026-10-24: 管理画面の「SQL を直接実行」の結果は、PHP 8 では表示されていなかった（`array_diff()` で「Array to string conversion」）。PR #884 で直した。この機能自体は #889 で廃止する。
- 2026-10-24: `$modx->snapshot(['filename' => 'x.sql', 'target' => 'c'])` は `target` が効かない。`$filename` を文字列で上書きしてから `$filename['target']` を読んでいる（`manager/includes/traits/document.parser.subparser.trait.php` の `snapshot()`）。
- 2026-10-24: 設定 `snapshot_path` は `manager/includes/default.config.php` に定義が無い隠し設定。値は `system_settings` に直接入れたときだけ効く。
- 2026-10-24: スナップショットのフォームは、`file_name` に日付の表示形式（`toDateFormat()`）で作った名前を最初から入れて送る（`manager/actions/tool/bkmanager.static.php` の 306〜311 行と 330 行。#890 の後）。`snapshot.processor.php` は空でない名前を利用者が付けた名前として扱うので、このままでは自動の名前の判定に合わず、`prune()` で消えない。
- 2026-10-24: 検証環境のアプリのイメージ（`manager/docker/Dockerfile`）には mysqldump も mariadb-client も入っていない。`php evo db:backup` は `cli_export_database()` が「mysqldump command not found」で止まる（`manager/includes/cli/cli-helpers.php`）。
- 2026-10-24: インストーラーは `temp/backup/.htaccess` を Apache 2.2 の書き方（`order deny,allow` / `deny from all`）で作る（`install/actions/summary.php` の 135〜137 行）。`.htaccess` が無いときだけ置く作りでは、既存のサイトに古い中身が残る。
- 2026-10-24: `DocumentParser::snapshot()` の戻り値は、名前に `/` か `\` を含むと `null`（値を返さない `return`）、それ以外は `Mysqldumper::snapshot()` の `file_put_contents()` の戻り値（書いたバイト数か `false`）。
- 2026-10-24: 管理画面の `global $settings_version` は、`manager/index.php` の `extract($modx->config)` で入る `settings_version` の設定値と同じ。CLI（`db-backup.php`）は `evo()->config('settings_version')` を使っている。フロントから呼ばれる `$modx->snapshot()` では、この global は入らない。
- 2026-10-24: `mysql_dumper.class.inc.php` は、先頭で `$modx` がオブジェクトでなければ `exit` する。メソッドの中で `include` すると、`global $modx;` を書かない限り何も出さずに止まる（CLI は `cli_export_database()` で先に `global $modx;` を書いている）。

## Decision Log

- 2026-10-24 / yamamoto: この ExecPlan は #889（PR #890）を先に入れた main を前提にする。#889 で、バックアップ管理の「SQL を直接実行」（入力欄・`console_mode`・セッションの `textarea`・結果一覧の `last_result`）を廃止し、復元（アップロード・スナップショットとも）を管理者だけにする。管理者の判定は `manager()->isAdmin()`（`access_permissions` と `web_access_permissions` を持つ）。インストーラーは最初の管理者を役割 2（ウェブマスター）で作り、役割 1 と 2 の権限は同じなので、`mgrRole=1` では判定しない。そのため `restore()` は実行結果の行を返さず、復元の失敗の確認はアップロードした SQL ファイルで行う。#889 を ExecPlan に取り込む案は、任意の SQL を流せる入口が実装の完了まで残るので採らなかった。
- 2026-10-24 / yamamoto: CLI の `db:backup` は mysqldump を既定のまま、プレフィックスの付いたテーブルだけを書き出し、`--routines` を付けず、`--skip-triggers` を明示する（mysqldump は triggers が既定で有効、routines が既定で無効。mariadb:11 の `mariadb-dump --help` で確認。`--triggers` を外すだけではトリガーが書き出され、`DELIMITER` が入る）。管理画面は PHP のダンパー（`Mysqldumper`）のまま。どちらで作っても、管理画面の復元と `db:import` で戻せる形にそろえる。代替案の「PHP のダンパーに統一」は、大きな DB で遅くメモリを使う課題（`assets/docs/core-issues.md` の「Mysqldumper クラスの設計上の制約」）が CLI にも広がるので採らない。「CLI のものは一覧から外す」は、戻せないバックアップが残り続けるので採らない。
- 2026-10-24 / yamamoto: 復元（管理画面・`db:import`）の前に、自動で現在の DB を `pre-restore-<日時>-<版>.sql` として保存する。途中で失敗したときに、利用者がこのファイルから戻せる。失敗時に自動で戻すことはしない（戻す処理も失敗しうるため、どのファイルから戻せるかを示すだけにする）。復元前のスナップショットが作れなかったときは、復元を止めて DB を書き換えない（戻せる状態を作れないまま書き換えないため。保存先に書けない環境では復元もできなくなる）。CLI の `db:import` の復元前スナップショットは、`db:backup` と同じく mysqldump（`cli_export_database()`）で取る（PHP のダンパーの遅さとメモリを CLI に広げないという下の判断と合わせる）。
- 2026-10-24 / yamamoto: 古い世代の削除は、自動で付けた名前（`YYYY-MM-DD-HHMMSS-<版>.sql`）のファイルだけを対象にし、更新日時の古い順に消す。利用者が名前を付けたものは消さない。世代の上限は管理画面と CLI で共有し、既定 10。CLI の `--max` は、その実行のときだけ上限を変える（次に管理画面で作ると 10 件に戻る）。`pre-restore-` のものは通常の世代とは別に数え、新しい 3 件だけ残す（復元のたびに DB 全体のダンプが増え続けないようにする）。システム設定の項目は増やさない（YAGNI）。
- 2026-10-24 / yamamoto: 自動の名前の日時は `date('Y-m-d-His', request_time() + server_offset_time)` で作る（画面の `toDateFormat()` と同じく `server_offset_time` を足し、ファイル名の時刻と一覧の表示をそろえる）。これまで管理画面は日付の表示形式（`datetime_format`）で名前を作っていたため、形式によって並び順が崩れていた。スナップショットのフォームの `file_name` は初期値を空にし、空なら自動の名前にする。
- 2026-10-24 / yamamoto: 自動の名前の「版」は `evo()->config('settings_version')`（無ければ `unknown`）にする。管理画面の `global $settings_version` は同じ値だが、`$modx->snapshot()` をフロントから呼ぶと入らない。CLI の `db-backup.php` と同じ取り方にそろえる。
- 2026-10-24 / yamamoto: クラス名は `EvoBackupManager`、ファイルは `manager/includes/backup_manager.class.inc.php`。#558 で `Logger` が利用者側の名前とぶつかった経緯から、新しいクラスには `Evo` を付ける（`EvoLogger` と同じ）。短い名前のヘルパー関数は足さない。
- 2026-10-24 / yamamoto: 復元の処理（`Mysqldumper::import_sql()` / `getSettings()` / `restoreSettings()`）は `EvoBackupManager::restore()` へ移し、`Mysqldumper` からは消す（互換の薄い包みも残さない。呼び出し元は本体だけ）。`Mysqldumper::createDump()` は `db:export` が使うので残す。
- 2026-10-24 / yamamoto: 復元後の設定の引き継ぎは、CLI の方式にそろえる。`site_url` / `base_url` / `filemanager_path` / `rb_base_dir` / `fixed_site_url` は、取り込み先にあった値を戻す。取り込み先に値が無く、取り込んだ値にプレースホルダー（`[(base_path)]` など）があるときだけ、今の環境の値に置き換える。mysqldump のダンプにはプレースホルダーが無いため、管理画面の方式（プレースホルダーだけ置き換える）では、別の環境のパスがそのまま残ってしまう。
- 2026-10-24 / yamamoto: 保存先のディレクトリとファイルに `chmod` しない（これまでは `0777` / `0666`）。`mkdir` は `0755`。`.htaccess` は Apache 2.2 と 2.4 の両方で効く書き方にする。`ensureDir()` は、`.htaccess` が無いとき、または中身がインストーラーの古い書き方（`order deny,allow` / `deny from all` だけ）のときに書き直す。利用者が書き換えた中身は残す。インストーラー（`install/actions/summary.php`）も同じ中身を書くように直す。
- 2026-10-24 / yamamoto: `DocumentParser::snapshot()` は外部から呼ばれうる公開の API なので、戻り値の形を変えない。作れたら書いたバイト数、書き出しに失敗したら `false`、名前が通らなければ `null`。`.sql` が無い名前には `.sql` を付ける（`snapshot.processor.php` と同じ）。名前の規則が `isValidFilename()` になるので、これまで作れた日本語や空白を含む名前は `null` になる。
- 2026-10-24 / yamamoto: `restore()` は、取り込みの前に `system_cache` を TRUNCATE し、`system_cache` を対象にする文は飛ばす（今の `db-import.php` と同じ）。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **スナップショット**: サーバー上の保存先に置く DB のバックアップ（`.sql` ファイル）。管理画面の「バックアップ管理」（`a=93`）の「スナップショット」タブで作り、一覧から復元する。ブラウザへダウンロードする「バックアップ」タブとは別。
- **保存先（`snapshot_path`）**: スナップショットを置くディレクトリ。システム設定 `snapshot_path` が `MODX_BASE_PATH`（サイトのルートの実パス）の下を指していればそこ、そうでなければ `temp/backup/`（無くて `assets/backup/` があればそちら）。
- **世代**: 保存先に残すスナップショットの数。超えた分を古い順に消す。
- **プレースホルダー**: PHP のダンパーが `system_settings` を書き出すときに、環境ごとに違う値を置き換える目印。`[(base_path)]` / `[(site_url)]` / `[(base_url)]`。
- **mysqldump**: MySQL・MariaDB に付属する、DB を SQL として書き出すコマンド。CLI だけが使う（管理画面はシェルを使えないサーバーがあるため PHP のダンパーを使う）。

今の処理の場所（行番号は #890 を入れた後の main。`origin/fix/889-drop-sql-console` で確認）:

- `manager/actions/tool/bkmanager.static.php`: 画面。`mode=backup` でダウンロード（`Mysqldumper::createDump()` → `dumpSql()`）。`mode=snapshot`（49〜102 行）は今のフォームから使われない死んだコード。スナップショットのフォームは `file_name` に日付の表示形式で作った名前を最初から入れる（306〜311 行・330 行）。スナップショットの一覧（`glob()` → 更新時刻をキーにした配列）。
- `manager/processors/backup/snapshot.processor.php`（`a=307`）: スナップショットの作成と、ファイル名順での世代の削除（10 世代）。
- `manager/processors/backup/restore.processor.php`（`a=305`）: `mode=restore1` はアップロードした SQL ファイル、`mode=restore2` は一覧のファイル。どちらも `Mysqldumper::import_sql()`。#889 の後は、`bk_manager` の権限があり、かつ `manager()->isAdmin()` のときだけ使える。そうでなければ `setError(3)` で止まる。画面の「リストア」タブと一覧の「このデータに戻す」も、`$canRestore`（isAdmin）のときだけ出る。入力欄から SQL を流す機能は #889 で廃止した。
- `manager/includes/mysql_dumper.class.inc.php`: `Mysqldumper`。`createDump()` は固定名の一時ファイル `MODX_CACHE_PATH . 'bktemp.pageCache.php'` に書き、最後に全体を `file_get_contents()` で読み込んで返す。`import_sql()` は `;` と改行で文を区切り、1 文ずつ `db()->query()`（エラーで `messageQuit` して止まる）。
- `manager/includes/cli/commands/db-backup.php`: 保存先の決定・`.htaccess`・ファイル名・世代の削除（`--max`）。書き出しは `cli_export_database()`（`manager/includes/cli/cli-helpers.php`）。
- `manager/includes/cli/commands/db-import.php`: CLI の復元。`EVO_CLI_IMPORT` が定義されていないと拒否。`system_cache` の文を飛ばし、5 つの設定を取り込み先の値に戻す。
- `manager/includes/traits/document.parser.subparser.trait.php` の `snapshot($filename, $target)`: `$modx->snapshot()`。保存先の判定なし、世代の削除なし。同じファイルの `getBindDenyDirs()` は、`temp/backup`・`assets/backup`・`snapshot_path` をバインディングで読めないディレクトリにしている（保存先の判定を変えても、この一覧が保存先を含むことを保つ）。
- `install/actions/summary.php`: インストール時に `temp/backup/` と、Apache 2.2 の書き方の `.htaccess` を作る。

保存先の決定は、上の 5 か所で判定が少しずつ違う（Issue #886 の表）。

## Plan of Work

`EvoBackupManager` に、保存先・ファイル名・一覧・世代・作成・復元を集める。管理画面・CLI・パーサーの API は、このクラスを呼ぶだけにする。書き出しそのもの（PHP のダンパーと mysqldump）は今の場所に残し、クラスは「どこに・どの名前で・何世代」と「どう戻すか」を受け持つ。こうすると、CLI だけが持つ mysqldump の呼び出し（`cli_export_database()`、資格情報の一時ファイルを含む）を管理画面に持ち込まずに済む。

`Mysqldumper` には、ファイルへ直接書き出す `dumpToFile($path)` を足す。一時ファイルは書き出し先と同じディレクトリに一意の名前で作り、書き終えてから `rename()` する。途中で失敗しても、中途半端な `.sql` が一覧に並ばない。`createDump()` は `dumpToFile()` を一時ファイルへ呼んで読み返す形にし、`db:export --driver=php` の動きを変えない。

M1 でクラスを作り、M2〜M3 で呼び出し側を順に置き換える。各マイルストーンの終わりで、置き換えた入口の動作を Docker で確かめる。

## Concrete Steps

コマンドはリポジトリのルートで実行する。Docker の検証環境は `.claude/skills/adaptive-validation-runner/` の手順で立ち上げる（以下、アプリのコンテナを `<app>`、DB のコンテナを `<db>` と書く。DB は `evodb`、ユーザーは `user` / `password`、テーブルの接頭辞は `modx_`）。

### M1: クラスとファイルへの直接書き出し

編集対象: `manager/includes/backup_manager.class.inc.php`（新規）、`manager/includes/mysql_dumper.class.inc.php`

`EvoBackupManager` の公開メソッド:

    dir(): string            保存先の実パス（末尾 /）。判定は Context の「保存先」の通り。realpath で正規化し、MODX_BASE_PATH の下かを先頭一致で見る
    ensureDir(): bool        無ければ 0755 で作り、.htaccess を置く（無いか、インストーラーの古い中身なら書き直す）。書けなければ false
    autoFilename(string $prefix = ''): string   $prefix . date('Y-m-d-His', request_time() + server_offset_time) . '-' . 版 . '.sql'。版は evo()->config('settings_version') ?: 'unknown'
    isValidFilename(string $name): bool          /\A[0-9A-Za-z][0-9A-Za-z._-]*\.sql\z/（PR #884 の規則に .sql 必須を足したもの）
    path(string $name): string|false             検証して、保存先にあるファイルの実パス。無ければ false
    files(): array           [['name', 'size', 'mtime'], ...] を更新日時の新しい順で
    createSnapshot(string $name = '', bool $contentsOnly = false): string|false   PHP のダンパーで書き出し、成功したら prune()。ファイル名を返す
    prune(int $max = 10): string[]               自動の名前のファイルだけを、更新日時の古い順に $max 件まで消す。あわせて pre-restore- のファイルを新しい 3 件まで消す。消した名前を返す
    restore(string $sql, ?callable $preRestore = null): array   復元前のスナップショットを取り（既定は createSnapshot(autoFilename('pre-restore-'))。CLI は mysqldump で書き出す関数を渡す）、取れなければ何も実行せずに返す。取れたら文を実行し、設定を引き継ぐ。['ok' => bool, 'executed' => int, 'skipped' => int, 'error' => string, 'failed_at' => int, 'pre_restore' => string, 'pre_restore_failed' => bool]

自動の名前の判定は `/\A\d{4}-\d{2}-\d{2}-\d{6}-[0-9A-Za-z._-]+\.sql\z/`。`.htaccess` の中身:

    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>

`restore()` は、復元前のスナップショットが取れなかったら `ok` を `false`、`pre_restore_failed` を `true` にして、文を 1 つも実行せずに返す。取れたら、取り込みの前に `system_cache` を TRUNCATE し、文を `db()->query($sql, false)` で実行し、`false` が返ったら止めて `db()->getLastError()` を `error` に入れる。`system_cache` を対象にする文は飛ばす（`db-import.php` の正規表現をそのまま使う）。実行結果の行は返さない（表示する画面は #889 で廃止）。

`mysql_dumper.class.inc.php` は先頭で `$modx` を確かめて `exit` するので、クラスの中で読み込むときは先に `global $modx;` を書く（`cli_export_database()` と同じ）。

`Mysqldumper` には `dumpToFile($path)` を足し、`createDump()` をそれを使う形にする。`import_sql()` / `getSettings()` / `restoreSettings()` は M2 で呼び出し元を置き換えてから消す。

確認:

    docker exec -w /var/www/html <app> php -r 'define("MODX_API_MODE", true); include "index.php"; require_once MODX_CORE_PATH . "backup_manager.class.inc.php"; $b = new EvoBackupManager(); var_dump($b->dir(), $b->createSnapshot(), $b->files()[0]["name"] ?? null);'

期待: 保存先（`/var/www/html/temp/backup/`）、作ったファイル名（`2026-…-<版>.sql`）、一覧の先頭が同じ名前。`temp/backup/` に一時ファイルが残らない。

### M2: 管理画面

編集対象: `manager/actions/tool/bkmanager.static.php`、`manager/processors/backup/snapshot.processor.php`、`manager/processors/backup/restore.processor.php`、`manager/includes/mysql_dumper.class.inc.php`、`install/actions/summary.php`、言語ファイル 2 つ

1. `bkmanager.static.php` の冒頭で `$modx->config['snapshot_path']` を書き換えるのをやめ、`EvoBackupManager::dir()` を使う。`mode=snapshot` の死んだコードを消す。スナップショットのフォームの `file_name` は初期値を空にする（日付の表示形式で名前を作る 306〜311 行を消す）。空のまま送れば自動の名前になることを、入力欄の近くに書く（言語ファイルに 1 つ追加）。
2. 一覧は `files()` を使う（更新時刻をキーにしないので、同じ時刻のファイルも全部出る）。
3. ダウンロード（`mode=backup`）は、`dumpToFile()` で一時ファイルに書き、成功したときだけヘッダーを送って `readfile()` する。失敗時はヘッダーを送る前にエラーを出す。
4. `snapshot.processor.php` は、入力のファイル名が空なら `autoFilename()`、空でなければ `isValidFilename()` で確かめ（`.sql` が無ければ付ける。PR #884 の動き）、`createSnapshot()` を呼ぶ。保存先・`.htaccess`・世代の処理を消す。
5. `restore.processor.php` は、#889 で入った管理者だけの判定（`bk_manager` かつ `manager()->isAdmin()`）を残したまま、`restore2` で `path()` を使い、どちらのモードも `restore()` を呼ぶ。結果をセッションに入れ、画面で「復元した」か「N 文目で失敗した。復元前の状態は <pre_restore> から戻せる」か「復元前のスナップショットを作れなかったので、復元しなかった」と出す（言語ファイルに 3 つ追加）。
6. `Mysqldumper` から `import_sql()` / `getSettings()` / `restoreSettings()` を消す。
7. `install/actions/summary.php` が書く `temp/backup/.htaccess` の中身を、M1 の `.htaccess` と同じにする。

確認: Docker の管理画面で、ファイル名を空のままスナップショットを 12 回作って一覧が自動の名前の 10 件になり、名前を付けたもの（`keep.sql`）が残ること。`datetime_format` を `dd-mm-YYYY` にしても同じこと。存在しないテーブルへの INSERT を含む `.sql` ファイルを「復元」タブからアップロードし、失敗のメッセージと `pre-restore-` のファイル名が出て、そのファイルの復元で元に戻ること。復元を 4 回して `pre-restore-` のファイルが新しい 3 件だけ残ること。保存先を書き込めなくして（`chmod 0555`）復元すると、「復元しなかった」と出て DB が変わらないこと。

### M3: CLI とパーサーの API

編集対象: `manager/includes/cli/commands/db-backup.php`、`manager/includes/cli/commands/db-import.php`、`manager/includes/cli/cli-helpers.php`、`manager/includes/traits/document.parser.subparser.trait.php`

1. `cli_export_database()` に `$options` を足し、`['routines' => false]` のときは `--routines --triggers` の代わりに `--skip-triggers` を付ける（`--triggers` を外すだけでは、既定で有効なのでトリガーが残る）。`db:export` の既定は変えない。
2. `db-backup.php` は、`dir()` / `ensureDir()` / `autoFilename()` を使い、`cli_export_database($driver, $path, <プレフィックスのテーブル一覧>, true, ['routines' => false])` で書き出してから `prune($max)` を呼ぶ（`--max` の既定は 10。管理画面と同じ上限）。テーブル一覧は `Mysqldumper::getTableNames()`。
3. `db-import.php` は `EVO_CLI_IMPORT` の確認を残し、`restore()` を呼ぶ。復元前のスナップショットは、`autoFilename('pre-restore-')` の名前で `cli_export_database()`（mysqldump、`['routines' => false]`）に書き出す関数を渡して取る。結果を `Executed / Skipped / Pre-restore snapshot` として出し、失敗なら終了コード 1。復元前のスナップショットが取れなければ、何も取り込まずに終了コード 1。
4. `$modx->snapshot()` は `createSnapshot()` を呼ぶ。配列の引数の `target` が効かない不具合を直す。戻り値の形は変えない（Decision Log）。`.sql` が無い名前には付け、`isValidFilename()` を通らなければ `null` を返す。作れたら作ったファイルのバイト数、書き出しに失敗したら `false` を返す。

確認（アプリのイメージに mysqldump が無いので、先に入れる。Dockerfile を変えるかはこの ExecPlan の範囲外）:

    docker exec -u root <app> sh -c 'apt-get update && apt-get install -y mariadb-client'
    docker exec <db> mariadb -uuser -ppassword evodb -e "CREATE TRIGGER modx_bk_test BEFORE UPDATE ON modx_site_content FOR EACH ROW SET NEW.editedon = NEW.editedon"
    docker exec -w /var/www/html <app> php evo db:backup --max=3
    docker exec -w /var/www/html <app> sh -c 'grep -c "DELIMITER" temp/backup/$(ls -t temp/backup | grep -E "^[0-9]" | head -1)'
    docker exec -w /var/www/html -e EVO_CLI_IMPORT=1 <app> php evo db:import temp/backup/<上で作ったファイル>

期待: `db:backup` は 3 世代を超えた自動の名前のファイルだけを消す。トリガーを作ってあっても `DELIMITER` は 0 件（main の `--triggers` 付きでは 1 件以上になる）。確認のあとは `DROP TRIGGER modx_bk_test` で消す。`db:import` は `Pre-restore snapshot: pre-restore-….sql` を出して成功し、同じファイルを管理画面の一覧から復元しても成功する。

### M4: ドキュメントと通し確認

編集対象: `manager/includes/cli/README.md`（`db:backup` / `db:import` の説明）、`assets/docs/site-url.md`（53 行目の `mysql_dumper` を新しいクラス名へ）、`assets/docs/core-issues.md`（「Mysqldumper クラスの設計上の制約」のメモリの項目を解消済みにする）

確認: `/doc-audit` で指摘が無いこと。Validation の 1〜6 を main とこのブランチで流し、表にして PR の本文へ書く。

## Validation and Acceptance

1. 管理画面でファイル名を空のままスナップショットを 12 回作ると、一覧は自動の名前のものが新しい 10 件になる。`datetime_format` を `dd-mm-YYYY` にしても同じ。
2. 利用者が名前を付けたスナップショットは、何回作っても消えない。`pre-restore-` のものは新しい 3 件だけ残る。
3. `php evo db:backup` で作ったファイルを、管理画面の一覧から復元できる（main では、トリガーのある DB で失敗する）。確認の前に、アプリのコンテナへ mariadb-client を入れる（M3 の確認を参照）。
4. 存在しないテーブルへの INSERT を含む `.sql` ファイルを管理画面からアップロードして復元すると、失敗した文の位置と `pre-restore-` のファイル名が表示され、そのファイルを復元すると元の内容に戻る。保存先に書き込めないときは、復元しなかったことが表示され、DB は変わらない。
5. `$modx->snapshot(['filename' => 'c.sql', 'target' => 'c'])` で作ったファイルに、`site_content` などコンテンツのテーブルだけが入っている。
6. 保存先の `.htaccess` が Apache 2.4 の書き方を含み、作ったファイルのパーミッションが `0666` でない。インストーラーが作った古い `.htaccess` も、最初のスナップショットで書き直される。

## Idempotence and Recovery

各マイルストーンはコミットを分ける。途中で止めたら、`git log` で最後に終えたマイルストーンを確かめて、その次から再開する。M1 は新規ファイルと追加メソッドだけなので、既存の動きに影響しない。M2 以降で動きがおかしければ、そのマイルストーンのコミットだけを戻せばよい。

検証で作ったスナップショットは `temp/backup/` に残る。確認が終わったら `docker exec -w /var/www/html <app> sh -c 'rm -f temp/backup/*.sql'` で消す。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/886
- 先行の修正（出力のエスケープとファイル名の検証、マージ済み fb9bba1b7）: https://github.com/modxcms-jp/evolution-jp/pull/884 。復元の `onclick` は `hsc(js_literal($filename))` で埋め込む
- 「SQL を直接実行」の廃止と復元を管理者だけにする変更（#889）: https://github.com/modxcms-jp/evolution-jp/pull/890
- 想定するコミット:
  - `refactor(backup): バックアップの保存先・世代・作成・復元を EvoBackupManager にまとめる`（M1）
  - `refactor(manager): バックアップ管理を EvoBackupManager に置き換える`（M2）
  - `feat(backup): 復元の前に自動でスナップショットを取る`（M2 の 5）
  - `fix(cli): db:backup のバックアップを管理画面から復元できる形にする`（M3）
  - `fix(core): $modx->snapshot() の target が効かないのを直す`（M3 の 4）
  - `docs(backup): バックアップの保存先と世代の扱いを更新する`（M4）

## Interfaces and Dependencies

- 呼び出し元: `manager/actions/tool/bkmanager.static.php`、`manager/processors/backup/*.php`、`manager/includes/cli/commands/db-backup.php` / `db-import.php`、`DocumentParser::snapshot()`（外部のスニペットやプラグインが呼びうる公開の API。引数と戻り値の形は変えない。名前の規則だけが `isValidFilename()` になる）
- `Mysqldumper`: `createDump()` / `getTableNames()` / `setDBtables()` は残す（`db:export --driver=php` が使う）。`import_sql()` / `getSettings()` / `restoreSettings()` は消す
- `evo system-upgrade` コマンド（`.agent/plans/2026-05-06-evo-system-upgrade-command.md`）は、DB のバックアップを `temp/backup/migrate/` に置く設計。`files()` と `prune()` は保存先の直下の `*.sql` だけを見るので、影響しない
- 前提: #889（「SQL を直接実行」の廃止と、復元を管理者だけにする変更）が main に入っていること。着手前に、PR #890 がマージ済みかを確かめる
- 範囲外: `Mysqldumper` のエスケープ方式（PDO 移行で扱う）、nginx で `.htaccess` が効かない件の根本的な対策、検証環境のイメージ（`manager/docker/Dockerfile`）に mysqldump を入れること
