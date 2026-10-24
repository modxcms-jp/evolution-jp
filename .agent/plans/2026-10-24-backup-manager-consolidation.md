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

## Decision Log

- 2026-10-24 / yamamoto: この ExecPlan は #889（PR #890）を先に入れた main を前提にする。#889 で、バックアップ管理の「SQL を直接実行」（入力欄・`console_mode`・セッションの `textarea`・結果一覧の `last_result`）を廃止し、復元（アップロード・スナップショットとも）を管理者だけにする。管理者の判定は `manager()->isAdmin()`（`access_permissions` と `web_access_permissions` を持つ）。インストーラーは最初の管理者を役割 2（ウェブマスター）で作り、役割 1 と 2 の権限は同じなので、`mgrRole=1` では判定しない。そのため `restore()` は実行結果の行を返さず、復元の失敗の確認はアップロードした SQL ファイルで行う。#889 を ExecPlan に取り込む案は、任意の SQL を流せる入口が実装の完了まで残るので採らなかった。
- 2026-10-24 / yamamoto: CLI の `db:backup` は mysqldump を既定のまま、プレフィックスの付いたテーブルだけを書き出し、`--routines --triggers` を付けない。管理画面は PHP のダンパー（`Mysqldumper`）のまま。どちらで作っても、管理画面の復元と `db:import` で戻せる形にそろえる。代替案の「PHP のダンパーに統一」は、大きな DB で遅くメモリを使う課題（`assets/docs/core-issues.md` の「Mysqldumper クラスの設計上の制約」）が CLI にも広がるので採らない。「CLI のものは一覧から外す」は、戻せないバックアップが残り続けるので採らない。
- 2026-10-24 / yamamoto: 復元（管理画面・`db:import`）の前に、自動で現在の DB を `pre-restore-<日時>-<版>.sql` として保存する。途中で失敗したときに、利用者がこのファイルから戻せる。失敗時に自動で戻すことはしない（戻す処理も失敗しうるため、どのファイルから戻せるかを示すだけにする）。
- 2026-10-24 / yamamoto: 古い世代の削除は、自動で付けた名前（`YYYY-MM-DD-HHMMSS-<版>.sql`）のファイルだけを対象にし、更新日時の古い順に消す。利用者が名前を付けたものと `pre-restore-` のものは消さない。世代数は既定 10、CLI は `--max` で変える。システム設定の項目は増やさない（YAGNI）。
- 2026-10-24 / yamamoto: 自動の名前の日時は `date('Y-m-d-His')` で作る。これまで管理画面は日付の表示形式（`datetime_format`）で名前を作っていたため、形式によって並び順が崩れていた。
- 2026-10-24 / yamamoto: クラス名は `EvoBackupManager`、ファイルは `manager/includes/backup_manager.class.inc.php`。#558 で `Logger` が利用者側の名前とぶつかった経緯から、新しいクラスには `Evo` を付ける（`EvoLogger` と同じ）。短い名前のヘルパー関数は足さない。
- 2026-10-24 / yamamoto: 復元の処理（`Mysqldumper::import_sql()` / `getSettings()` / `restoreSettings()`）は `EvoBackupManager::restore()` へ移し、`Mysqldumper` からは消す（互換の薄い包みも残さない。呼び出し元は本体だけ）。`Mysqldumper::createDump()` は `db:export` が使うので残す。
- 2026-10-24 / yamamoto: 復元後の設定の引き継ぎは、CLI の方式にそろえる。`site_url` / `base_url` / `filemanager_path` / `rb_base_dir` / `fixed_site_url` は、取り込み先にあった値を戻す。取り込み先に値が無く、取り込んだ値にプレースホルダー（`[(base_path)]` など）があるときだけ、今の環境の値に置き換える。mysqldump のダンプにはプレースホルダーが無いため、管理画面の方式（プレースホルダーだけ置き換える）では、別の環境のパスがそのまま残ってしまう。
- 2026-10-24 / yamamoto: 保存先のディレクトリとファイルに `chmod` しない（これまでは `0777` / `0666`）。`mkdir` は `0755`。`.htaccess` は Apache 2.2 と 2.4 の両方で効く書き方にする。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **スナップショット**: サーバー上の保存先に置く DB のバックアップ（`.sql` ファイル）。管理画面の「バックアップ管理」（`a=93`）の「スナップショット」タブで作り、一覧から復元する。ブラウザへダウンロードする「バックアップ」タブとは別。
- **保存先（`snapshot_path`）**: スナップショットを置くディレクトリ。システム設定 `snapshot_path` が `MODX_BASE_PATH`（サイトのルートの実パス）の下を指していればそこ、そうでなければ `temp/backup/`（無くて `assets/backup/` があればそちら）。
- **世代**: 保存先に残すスナップショットの数。超えた分を古い順に消す。
- **プレースホルダー**: PHP のダンパーが `system_settings` を書き出すときに、環境ごとに違う値を置き換える目印。`[(base_path)]` / `[(site_url)]` / `[(base_url)]`。
- **mysqldump**: MySQL・MariaDB に付属する、DB を SQL として書き出すコマンド。CLI だけが使う（管理画面はシェルを使えないサーバーがあるため PHP のダンパーを使う）。

今の処理の場所（行番号は 2026-10-24 の main）:

- `manager/actions/tool/bkmanager.static.php`: 画面。`mode=backup` でダウンロード（`Mysqldumper::createDump()` → `dumpSql()`）。`mode=snapshot`（47〜100 行）は今のフォームから使われない死んだコード。スナップショットの一覧（`glob()` → 更新時刻をキーにした配列）。
- `manager/processors/backup/snapshot.processor.php`（`a=307`）: スナップショットの作成と、ファイル名順での世代の削除（10 世代）。
- `manager/processors/backup/restore.processor.php`（`a=305`）: `mode=restore1` はアップロードした SQL ファイル、`mode=restore2` は一覧のファイル。どちらも `Mysqldumper::import_sql()`。#889 の後は、`bk_manager` の権限があり、かつ `manager()->isAdmin()` のときだけ使える。そうでなければ `setError(3)` で止まる。画面の「リストア」タブと一覧の「このデータに戻す」も、`$canRestore`（isAdmin）のときだけ出る。入力欄から SQL を流す機能は #889 で廃止した。
- `manager/includes/mysql_dumper.class.inc.php`: `Mysqldumper`。`createDump()` は固定名の一時ファイル `MODX_CACHE_PATH . 'bktemp.pageCache.php'` に書き、最後に全体を `file_get_contents()` で読み込んで返す。`import_sql()` は `;` と改行で文を区切り、1 文ずつ `db()->query()`（エラーで `messageQuit` して止まる）。
- `manager/includes/cli/commands/db-backup.php`: 保存先の決定・`.htaccess`・ファイル名・世代の削除（`--max`）。書き出しは `cli_export_database()`（`manager/includes/cli/cli-helpers.php`）。
- `manager/includes/cli/commands/db-import.php`: CLI の復元。`EVO_CLI_IMPORT` が定義されていないと拒否。`system_cache` の文を飛ばし、5 つの設定を取り込み先の値に戻す。
- `manager/includes/traits/document.parser.subparser.trait.php` の `snapshot($filename, $target)`: `$modx->snapshot()`。保存先の判定なし、世代の削除なし。

保存先の決定は、上の 5 か所で判定が少しずつ違う（Issue #886 の表）。

## Plan of Work

`EvoBackupManager` に、保存先・ファイル名・一覧・世代・作成・復元を集める。管理画面・CLI・パーサーの API は、このクラスを呼ぶだけにする。書き出しそのもの（PHP のダンパーと mysqldump）は今の場所に残し、クラスは「どこに・どの名前で・何世代」と「どう戻すか」を受け持つ。こうすると、CLI だけが持つ mysqldump の呼び出し（`cli_export_database()`、資格情報の一時ファイルを含む）を管理画面に持ち込まずに済む。

`Mysqldumper` には、ファイルへ直接書き出す `dumpToFile($path)` を足す。一時ファイルは書き出し先と同じディレクトリに一意の名前で作り、書き終えてから `rename()` する。途中で失敗しても、中途半端な `.sql` が一覧に並ばない。`createDump()` は `dumpToFile()` を一時ファイルへ呼んで読み返す形にし、`db:export --driver=php` の動きを変えない。

M1 でクラスを作り、M2〜M3 で呼び出し側を順に置き換える。各マイルストーンの終わりで、置き換えた入口の動作を Docker で確かめる。

## Concrete Steps

コマンドはリポジトリのルートで実行する。Docker の検証環境は `.claude/skills/adaptive-validation-runner/` の手順で立ち上げる（以下、アプリのコンテナを `<app>` と書く）。

### M1: クラスとファイルへの直接書き出し

編集対象: `manager/includes/backup_manager.class.inc.php`（新規）、`manager/includes/mysql_dumper.class.inc.php`

`EvoBackupManager` の公開メソッド:

    dir(): string            保存先の実パス（末尾 /）。判定は Context の「保存先」の通り。realpath で正規化し、MODX_BASE_PATH の下かを先頭一致で見る
    ensureDir(): bool        無ければ 0755 で作り、.htaccess を置く。書けなければ false
    autoFilename(string $prefix = ''): string   $prefix . date('Y-m-d-His') . '-' . 版 . '.sql'
    isValidFilename(string $name): bool          /\A[0-9A-Za-z][0-9A-Za-z._-]*\.sql\z/（PR #884 の規則に .sql 必須を足したもの）
    path(string $name): string|false             検証して、保存先にあるファイルの実パス。無ければ false
    files(): array           [['name', 'size', 'mtime'], ...] を更新日時の新しい順で
    createSnapshot(string $name = '', bool $contentsOnly = false): string|false   PHP のダンパーで書き出し、成功したら prune()。ファイル名を返す
    prune(int $max = 10): string[]               自動の名前のファイルだけを、更新日時の古い順に消す。消した名前を返す
    restore(string $sql): array                  直前に createSnapshot(autoFilename('pre-restore-')) を取り、文を実行し、設定を引き継ぐ。['ok' => bool, 'executed' => int, 'skipped' => int, 'error' => string, 'failed_at' => int, 'pre_restore' => string]

自動の名前の判定は `/\A\d{4}-\d{2}-\d{2}-\d{6}-[0-9A-Za-z._-]+\.sql\z/`。`.htaccess` の中身:

    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>

`restore()` は、文を `db()->query($sql, false)` で実行し、`false` が返ったら止めて `db()->getLastError()` を `error` に入れる。`system_cache` を対象にする文は飛ばす（`db-import.php` の正規表現をそのまま使う）。実行結果の行は返さない（表示する画面は #889 で廃止）。

`Mysqldumper` には `dumpToFile($path)` を足し、`createDump()` をそれを使う形にする。`import_sql()` / `getSettings()` / `restoreSettings()` は M2 で呼び出し元を置き換えてから消す。

確認:

    docker exec -w /var/www/html <app> php -r 'define("MODX_API_MODE", true); include "index.php"; require_once MODX_CORE_PATH . "backup_manager.class.inc.php"; $b = new EvoBackupManager(); var_dump($b->dir(), $b->createSnapshot(), $b->files()[0]["name"] ?? null);'

期待: 保存先（`/var/www/html/temp/backup/`）、作ったファイル名（`2026-…-<版>.sql`）、一覧の先頭が同じ名前。`temp/backup/` に一時ファイルが残らない。

### M2: 管理画面

編集対象: `manager/actions/tool/bkmanager.static.php`、`manager/processors/backup/snapshot.processor.php`、`manager/processors/backup/restore.processor.php`、`manager/includes/mysql_dumper.class.inc.php`、言語ファイル 2 つ

1. `bkmanager.static.php` の冒頭で `$modx->config['snapshot_path']` を書き換えるのをやめ、`EvoBackupManager::dir()` を使う。`mode=snapshot` の死んだコードを消す。
2. 一覧は `files()` を使う（更新時刻をキーにしないので、同じ時刻のファイルも全部出る）。
3. ダウンロード（`mode=backup`）は、`dumpToFile()` で一時ファイルに書き、成功したときだけヘッダーを送って `readfile()` する。失敗時はヘッダーを送る前にエラーを出す。
4. `snapshot.processor.php` は、入力のファイル名を `isValidFilename()` で確かめ（`.sql` が無ければ付ける。PR #884 の動き）、`createSnapshot()` を呼ぶ。保存先・`.htaccess`・世代の処理を消す。
5. `restore.processor.php` は、#889 で入った管理者だけの判定（`bk_manager` かつ `manager()->isAdmin()`）を残したまま、`restore2` で `path()` を使い、どちらのモードも `restore()` を呼ぶ。結果をセッションに入れ、画面で「復元した」か「N 文目で失敗した。復元前の状態は <pre_restore> から戻せる」と出す（言語ファイルに 2 つ追加）。
6. `Mysqldumper` から `import_sql()` / `getSettings()` / `restoreSettings()` を消す。

確認: Docker の管理画面で、スナップショットを 12 回作って一覧が 10 件になり、名前を付けたもの（`keep.sql`）と `pre-restore-` のものが残ること。存在しないテーブルへの INSERT を含む `.sql` ファイルを「復元」タブからアップロードし、失敗のメッセージと `pre-restore-` のファイル名が出て、そのファイルの復元で元に戻ること。

### M3: CLI とパーサーの API

編集対象: `manager/includes/cli/commands/db-backup.php`、`manager/includes/cli/commands/db-import.php`、`manager/includes/cli/cli-helpers.php`、`manager/includes/traits/document.parser.subparser.trait.php`

1. `cli_export_database()` に `$options` を足し、`['routines' => false]` のときは `--routines --triggers` を付けない。`db:export` の既定は変えない。
2. `db-backup.php` は、`dir()` / `ensureDir()` / `autoFilename()` を使い、`cli_export_database($driver, $path, <プレフィックスのテーブル一覧>, true, ['routines' => false])` で書き出してから `prune($max)` を呼ぶ。テーブル一覧は `Mysqldumper::getTableNames()`。
3. `db-import.php` は `EVO_CLI_IMPORT` の確認を残し、`restore()` を呼ぶ。結果を `Executed / Skipped / Pre-restore snapshot` として出し、失敗なら終了コード 1。
4. `$modx->snapshot()` は `createSnapshot()` を呼ぶ。配列の引数の `target` が効かない不具合を直す。名前が `isValidFilename()` を通らなければ `false` を返す。

確認:

    docker exec -w /var/www/html <app> php evo db:backup --max=3
    docker exec -w /var/www/html <app> sh -c 'grep -c "DELIMITER" temp/backup/$(ls -t temp/backup | grep -E "^[0-9]" | head -1)'
    docker exec -w /var/www/html -e EVO_CLI_IMPORT=1 <app> php evo db:import temp/backup/<上で作ったファイル>

期待: `db:backup` は 3 世代を超えた自動の名前のファイルだけを消す。`DELIMITER` は 0 件。`db:import` は `Pre-restore snapshot: pre-restore-….sql` を出して成功し、同じファイルを管理画面の一覧から復元しても成功する。

### M4: ドキュメントと通し確認

編集対象: `manager/includes/cli/README.md`（`db:backup` / `db:import` の説明）、`assets/docs/site-url.md`（53 行目の `mysql_dumper` を新しいクラス名へ）、`assets/docs/core-issues.md`（「Mysqldumper クラスの設計上の制約」のメモリの項目を解消済みにする）

確認: `/doc-audit` で指摘が無いこと。Validation の 1〜6 を main とこのブランチで流し、表にして PR の本文へ書く。

## Validation and Acceptance

1. 管理画面でスナップショットを 12 回作ると、一覧は自動の名前のものが新しい 10 件になる。`datetime_format` を `dd-mm-YYYY` にしても同じ。
2. 利用者が名前を付けたスナップショットと `pre-restore-` のものは、何回作っても消えない。
3. `php evo db:backup` で作ったファイルを、管理画面の一覧から復元できる（main では、トリガーのある DB で失敗する）。
4. 存在しないテーブルへの INSERT を含む `.sql` ファイルを管理画面からアップロードして復元すると、失敗した文の位置と `pre-restore-` のファイル名が表示され、そのファイルを復元すると元の内容に戻る。
5. `$modx->snapshot(['filename' => 'c.sql', 'target' => 'c'])` で作ったファイルに、`site_content` などコンテンツのテーブルだけが入っている。
6. 保存先の `.htaccess` が Apache 2.4 の書き方を含み、作ったファイルのパーミッションが `0666` でない。

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

- 呼び出し元: `manager/actions/tool/bkmanager.static.php`、`manager/processors/backup/*.php`、`manager/includes/cli/commands/db-backup.php` / `db-import.php`、`DocumentParser::snapshot()`（外部のスニペットやプラグインが呼びうる公開の API。引数と戻り値は変えない）
- `Mysqldumper`: `createDump()` / `getTableNames()` / `setDBtables()` は残す（`db:export --driver=php` が使う）。`import_sql()` / `getSettings()` / `restoreSettings()` は消す
- `evo system-upgrade` コマンド（`.agent/plans/2026-05-06-evo-system-upgrade-command.md`）は、DB のバックアップを `temp/backup/migrate/` に置く設計。`files()` と `prune()` は保存先の直下の `*.sql` だけを見るので、影響しない
- 前提: #889（「SQL を直接実行」の廃止と、復元を管理者だけにする変更）が main に入っていること。着手前に、PR #890 がマージ済みかを確かめる- 範囲外: `Mysqldumper` のエスケープ方式（PDO 移行で扱う）、nginx で `.htaccess` が効かない件の根本的な対策
