# ExecPlan: バックアップ・スナップショット・復元の役割を整理する

## Purpose / Big Picture

データベースの控え（管理画面のバックアップ・スナップショット・復元、CLI の `db:backup`・`db:export`・`db:import`）を、「ダンプの形」「表の一覧」「保存先と世代」「作成と復元の手順」「入口」の 5 つの受け持ちに分け、それぞれを 1 か所に置く。利用者にとっては、管理画面と CLI で同じ控えが同じ手順で作られて戻せるようになり、別のサイトのダンプを戻して「成功」と出る、置き場所の設定が黙って無視される、大きなダンプでメモリが足りなくなる、といった分かりにくい動きが無くなる。

## Progress

- [ ] (2026-11-17) ExecPlan 作成
- [ ] M0 着手前の確認: #1560 のマージを確かめ、Context を今の main に合わせて直す。往復の確認スクリプトを用意する（PR なし）
- [ ] S1 表の一覧と状態を `EvoDbTables` にまとめる（動きを変えない）
- [ ] S2 表を空にする入口を a=54 の 1 つにする（動きを変える）
- [ ] S3 ダンプの書く・読むを `EvoSqlDump` にまとめ、`Mysqldumper` を消す（動きを変えない）
- [ ] S4 保存先と世代を `EvoSnapshotStore` にまとめる（動きを変えない）
- [ ] S5 保存先の黙った切り替えをやめる（動きを変える。`snapshot_path` の扱いは本人の確認を得てから行う）
- [ ] S6 作成・復元の手順を `EvoBackupManager` にまとめ、復元をファイルから流して読む（動きを変えない）
- [ ] S7 復元の前の確かめを足し、作成の結果をそろえる（動きを変える）
- [ ] S8 入口を薄くする: ダウンロードを processor へ移し、失敗の知らせ方をそろえる（動きを変える）
- [ ] S9 言葉をそろえる（動きを変える。本人の確認を得てから行う）
- [ ] 最終確認: PHP 7.4・MySQL 5.7 で往復を確かめる

## Surprises & Discoveries

- 2026-11-17: `snapshot_path` は `install/sql/default_settings.sql` にも設定の画面（`manager/includes/settings_form.definition.inc.php`）にも無い。`evo()->config('snapshot_path', '')` で読むだけで、値を入れる手段は DB を直接書き換えることしか無い（`grep -rn snapshot_path install/ manager/includes/settings_form.definition.inc.php` が 0 件）。
- 2026-11-17: 検証用のアプリのイメージ（`manager/docker/Dockerfile`）には `mysqldump` が入っていない。`db:backup`・`db:import` の既定（`mysqldump`）は検証環境ではそのままでは動かない。mysqldump の出力は DB のコンテナの `mariadb-dump` で作るか、アプリのコンテナに一時的にクライアントを入れて確かめる（Concrete Steps の M0）。
- 2026-11-17: `db:export` の既定は `--routines --triggers` 付きで、出力に `DELIMITER ;;` が入りうる。今の復元（`;` と改行で分ける）はこれを読めない。`db:backup`・`db:import` の控えは `--skip-triggers` なので読める。
- 2026-11-17: 表の名前の正規化（接頭辞を付ける）は `cli_full_table_name()` と `db-export.php` の中の 2 か所に同じ処理がある。表の一覧（`SHOW TABLE STATUS ... LIKE`）は `Mysqldumper::getTableNames()`・`bkmanager.static.php`・`sysinfo.static.php` の 3 か所で、`_` を LIKE のためにエスケープしているのは `getTableNames()` だけ。表の有無は `EvoBackupManager::tableExists()` と `db()->tableExists()` の 2 通り。
- 2026-11-17: a=55（`manager/processors/db/empty_table.processor.php`）は `settings` の権限だけで `manager_log` を空にする。a=54 の空にする処理は `settings` かつ（`logs` か `bk_manager`）。a=55 への入口は管理ログの画面（`logs` が要る）の説明文 `mgrlog_sortinst` のリンクだけ。

## Decision Log

- 2026-11-17 / yamamoto（Claude が起案）: 1 段階を 1 PR にする（`exec-plan-runner` の `/run-plan` で段階ごとに PR を分ける）。動きを変えない段階（S1・S3・S4・S6）と動きを変える段階（S2・S5・S7・S8・S9）を別の PR にし、動きを変えない PR は「修正前と同じ出力」で、動きを変える PR は「計画どおりの違いだけ」で確かめる。段階ごとのブランチは `origin/main` から `git switch -c` で作り、`gh issue develop` で Issue に紐づけない（紐づいたブランチの PR は `Refs` でもマージで Issue を閉じるため）。S1 だけは `gh issue develop` で作った `refactor/1561-backup-reorg` を使い、最後の段階の PR で `Closes #1561` にする。最後の段階より前に S1 のブランチの PR がマージされて Issue が閉じたときは、Issue を開き直して続ける。
- 2026-11-17 / yamamoto（Claude が起案）: #1560（ダンプの値のエスケープ、スナップショットのログの表の構造、バックアップの失敗の知らせ方、`processors/backup/` の失敗の知らせ方）のマージを待ってから S1 に入る。#1560 は同じファイルを書き換えるため、先に入れると衝突と二重の手直しになる。M0 で #1560 の差分を読み、この計画の Context と各段階の作業から、#1560 で済んだものを外す。
- 2026-11-17 / yamamoto（Claude が起案）: 新しいクラスは、`manager/includes/` の新しい作法（`EvoSiteExporter`・`EvoHtaccess`・`EvoSpecialPages`・`EvoFormFlow`）に合わせ、`Evo` を頭に付けたクラス名と、`<小文字の名前>.class.inc.php` のファイル名にする。ファイルの先頭に `$modx` の有無で `exit` する行を置かない（クラスの定義だけのファイルにし、`global $modx;` を呼び出し側に置かせない）。互いの読み込みは `require_once __DIR__ . '/<ファイル>'` にする。名前は次のとおり。
  - `EvoDbTables`（`manager/includes/db_tables.class.inc.php`）: 接頭辞の付いた表の一覧・状態（行数・大きさ）・名前の正規化・表の有無・空にしてよい表・最適化してよい表
  - `EvoSqlDump`（`manager/includes/sql_dump.class.inc.php`）: ダンプを書く（値のエスケープ・文の並び）と読む（文に分ける）。「値に生の改行を入れない。文は `;` で行を終える」という約束をこのクラスの説明に書き、書く側と読む側が同じ約束を使う
  - `EvoSnapshotStore`（`manager/includes/snapshot_store.class.inc.php`）: 置き場所・保護の `.htaccess`・名前・一覧・世代
  - `EvoBackupManager`（今の `manager/includes/backup_manager.class.inc.php` を残す）: 作成と復元の手順（復元の前の控え、接頭辞の確認、環境ごとの設定の保持、キャッシュの作り直し）。管理画面・CLI・`$modx->snapshot()` はこれを呼ぶ。名前を変えないのは、呼び出し元とほかの計画（`.agent/plans/2026-05-06-evo-system-upgrade-command.md`）が「手順の窓口」としてこの名前を使っているため
- 2026-11-17 / yamamoto（Claude が起案）: PDO の新しい DB API（ロードマップの「PDO移行」、#677。AGENTS.md の #453 の取り決め）は待たず、今の `db()` で進める。PDO 移行のフェーズ 1 は `db()->escape()` などの公開メソッドの約束を変えないので、この計画の書き方はそのまま動く。そのかわり、DB に問い合わせる箇所を `EvoDbTables` と `EvoSqlDump` の 2 つに閉じ込め、PDO 移行のフェーズ 2（プレースホルダ）で直す場所をこの 2 つにする。
- 2026-11-17 / yamamoto（Claude が起案）: 消すクラス・関数・入口に、古い名前の別名を残さない（互換を重ねない方針）。`Mysqldumper`・`mysql_dumper.class.inc.php`（S3）、`EvoBackupManager::truncatableTables()`・`dir()` などの保存先のメソッド（S1・S4）、a=55（S2）、`cli_full_table_name()`（S1）は、移した段階で消す。外の拡張が `Mysqldumper` を読んでいた場合は Fatal error で気付ける（黙って動きが変わらない）。PR の本文の「備考」に消した名前を挙げる。
- 2026-11-17 / yamamoto（Claude が起案）: a=55 を消し、管理ログの画面のリンクを a=54 の `truncate=<接頭辞>manager_log&from=logging` に向ける（S2）。権限は a=54 の確かめ方（`settings` かつ `logs` か `bk_manager`）にそろえる。リンクのある画面は `logs` が要るので、画面から操作する利用者の動きは変わらない。変わるのは、`settings` だけを持ち `logs` も `bk_manager` も持たない利用者が a=55 を直接呼んだときだけで、これは画面に入口の無い操作なので、本人の確認は要らないと判断した。
- 2026-11-17 / yamamoto（Claude が起案）: 表の一覧の LIKE の `_` のエスケープは、S1 でそろえる（`getTableNames()` の書き方に合わせる）。違いが出るのは、接頭辞の `_` を任意の 1 文字とみなしたときに当たる別の表がある DB だけで、そのときに他人の表が一覧に混ざる今の動きは不具合とみなす。S1 で出てよい違いはこれだけとする。
- 2026-11-17 / yamamoto（Claude が起案）: 大きなダンプのメモリは、S6 で復元を「ファイルを 1 行ずつ読み、`;` で終わる行まで溜めて 1 文として渡す」形（PHP のジェネレーター。PHP 7.4 で使える）にして減らす。分け方の規則（`;` の後に空白とタブだけを挟んで改行）は今の `preg_split('@;[ \t]*\n@', ...)` と同じにし、CR の扱い（CRLF・単独の CR を LF にそろえてから分ける）も行ごとに同じにする。アップロードの復元も一時ファイルから流して読む（`file_get_contents()` しない）。書く側は今も 1MB ごとに書き出しているので変えない。`createDump()`（文字列で返す）は S3 で消し、`db:export --driver=php` の標準出力へは `php://output` のストリームに書く。
- 2026-11-17 / yamamoto（Claude が起案）: 別の接頭辞のダンプは、S7 で復元の前に断る。読む側が文を流しながら `CREATE TABLE`・`INSERT INTO`・`DROP TABLE` などの対象の表の名前を集めるのでは遅い（書き換えた後に分かる）ので、復元の前に一度ファイルを流して読み（S6 の読む側で、実行せずに）、対象の表がすべて今の接頭辞で始まるかを確かめる。1 つでも外れる表があれば、控えも取らず、何も書き換えずに「接頭辞が違う（ダンプの接頭辞: `<見つかった接頭辞>`、このサイト: `<接頭辞>`）」と知らせる。接頭辞を読み替えて取り込む機能は作らない（YAGNI。必要になったら別の Issue）。`DELIMITER` を含むダンプ（`db:export` の既定の出力など）も同じ下読みで見つけ、「トリガーやルーチンを含むダンプは戻せない。`db:backup` か、`--skip-triggers` を付けた mysqldump の出力を使う」と知らせる。
- 2026-11-17 / yamamoto（Claude が起案）: `$modx->snapshot()`（`manager/includes/traits/document.parser.subparser.trait.php`。公開の API。本体からの呼び出しは無いが、外のスニペットが使う前提で残す）は、S6 で `EvoBackupManager` の作成の手順を呼ぶだけにする。名前の `.sql` の付け方は `snapshot.processor.php` と同じ処理の写しなので、`EvoSnapshotStore::normalizeName()` に 1 つにし、両方から呼ぶ。戻り値は S7 で「成功は作ったファイルの大きさ（int）、失敗は名前の不正も含めてすべて `false`」にそろえる（今は名前が不正だと `null`）。
- 2026-11-17 / yamamoto（Claude が起案）: CLI の `db:backup` は S7 で、管理画面のスナップショットと同じ中身（ログの表は構造だけ、データは入れない）にする。同じ置き場所の同じ世代に数えるもので、作った入口によって中身が違うのは分かりにくいため。`mysqldump` で書くときは、ログの表を `--ignore-table` で外した 1 回目と、ログの表だけを `--no-data` で書く 2 回目を同じ一時ファイルに続けて書く。書き手（PHP か mysqldump か）は `EvoBackupManager` の作成の手順に引数で渡す。
- 2026-11-17 / yamamoto（Claude が起案）: S8 のダウンロードの processor は、空いている a=306 に置く（`manager/processors/backup/download.processor.php`）。a=93 は画面だけにする。a=306 は POST だけを受け、`manager/includes/csrf_token.php` の一覧と `manager/includes/actionlist.inc.php` に足す。
- 2026-11-17 / yamamoto（Claude が起案）: 本人の確認が要る点（S5・S9 に入る前に、選択肢を並べて確かめる。runner はここで止まる）。
  - 言葉と名前（S9）。今は、管理画面のタブ「バックアップ」が手元へのダウンロード、「スナップショット」がサーバーへの保存で、CLI の `db:backup` の中身はスナップショット。
    - 案 A（推奨）: 「バックアップ」を控え全体の呼び名にし、手元へ持ち帰るものを「ダウンロード」、サーバーに置くものを「スナップショット」と呼ぶ。画面のタブ「バックアップ」を「ダウンロード」に、ボタン「選択したテーブルをバックアップ・ダウンロード」を「選択したテーブルをダウンロード」に変える。CLI の `db:backup` は `db:snapshot` に改名し、`db:backup` の別名は残さない。改名は cron などで `db:backup` を呼んでいるサイトの控えを止めるので、S9 は main ではなく次の版（1.4、`develop/1.4`）に入れ、リリースノートに書く。`db:backup` を呼ぶと「unknown command」で終了コードが 0 以外になり、cron のメールなどで気付ける。
    - 案 B: 画面の言葉だけ案 A のとおりに変え、CLI の `db:backup` は名前を変えずに「スナップショットを作るコマンド」と README に書く。cron は止まらないが、言葉は CLI だけずれたまま残る。
    - 案 C: 名前は何も変えず、`assets/docs/backup.md` に言葉の定義だけ書く。
  - `snapshot_path`（S5）。今は設定の画面にも既定値にも無く、DB を書き換えた人だけが使える設定で、無い・サイトの外のときは黙って `temp/backup/` を使う。
    - 案 A（推奨）: グローバル設定の画面に出し（`manager/includes/settings_form.definition.inc.php`）、保存するときにサイトのフォルダの下か確かめて、外なら保存しない。実行時に値が不正なら、`temp/backup/` に切り替えず、作成・復元を止めて「保存先 `<値>` が使えない」と知らせる（`evo()->logEvent()` にも残す）。
    - 案 B: 設定をやめ、置き場所を `temp/backup/` に固定する。値が入っているサイトでは、アップデーターのマイグレーションで設定の行を消し、そのフォルダの `.sql` は動かさない（管理画面に一度だけ「前の保存先 `<値>` にある控えは一覧に出ない」と出す）。
    - 案 C: 画面には出さず、実行時の不正だけを知らせる形にする（隠れた設定のまま）。
  - 古い置き場所 `assets/backup`（S5）は、本人の確認なしに分岐を消す。インストーラー・アップデーターの `install/actions/summary.php` が `temp/backup/` を作るので、アップデーターを通ったサイトではこの分岐はもう通らない。ただし `assets/backup` に残った控えがバインドで読まれないよう、`EvoSnapshotStore::denyDirs()` の一覧には残す。

## Outcomes & Retrospective

## Context and Orientation

言葉の定義（この計画の中での使い方）:
- ダンプ: DB の表の構造（`CREATE TABLE`）と中身（`INSERT INTO`）を SQL の文として並べたテキストファイル（`.sql`）。
- スナップショット: サーバーの保存先（既定は `temp/backup/`）に置いたダンプ。管理画面の「スナップショット」タブと CLI の `db:backup` が作る。
- ダウンロード: 管理画面の「バックアップ」タブで、選んだ表のダンプを手元に保存すること。
- 復元: ダンプの文を順に実行して、DB をダンプの状態に戻すこと。管理画面の「リストア」タブ（アップロード）とスナップショットの一覧（「このデータに戻す」）、CLI の `db:import` が行う。
- 接頭辞: このサイトの表の名前の頭に付く文字列（例 `modx_`）。`db()->table_prefix`。同じ DB に複数のサイトの表が入るときに区別する。
- 世代: 自動の名前（`YYYY-MM-DD-HHMMSS-<版>.sql`）のスナップショットを新しい順に何件残すか（既定 10 件。`pre-restore-` は別に 3 件）。
- 環境ごとの設定: `site_url`・`base_url`・`filemanager_path`・`rb_base_dir`・`fixed_site_url`。復元しても、取り込み先の値を残す。
- 往復: ダンプを作り、それを復元して、元の DB と同じ中身に戻るかを比べること。

今の受け持ち（main 78b61bfec 時点。M0 で #1560 のマージ後の main に合わせて直す）:
- `manager/includes/mysql_dumper.class.inc.php`: `Mysqldumper`。PHP でダンプを書く。先頭の `if (!isset($modx) || !is_object($modx)) exit;` のため、メソッドの中から読み込む `EvoBackupManager::createSnapshot()`・`db-backup.php`・`db-import.php`・`cli_export_database()` が `global $modx;` を置いている。`getTableNames()` が表の一覧。`dumpToFile()` が `_dbtables` を `array_flip()` で書き換える、全部の表に `SHOW CREATE TABLE` を打つ、`object2Array()` が使われていない、ヘッダーに `site_name` を入れる。
- `manager/includes/backup_manager.class.inc.php`: `EvoBackupManager`。保存先（`dir()`・`ensureDir()`・`HTACCESS`）・名前（`autoFilename()`・`isValidFilename()`・`path()`）・一覧（`files()`）・世代（`prune()`）・作成（`createSnapshot()`）・復元（`restore($sql, $preRestore)`。文字列で受けて `preg_split()` で分ける）・環境ごとの設定の保持・空にしてよい表（`truncatableTables()`）。
- `manager/actions/tool/bkmanager.static.php`（a=93）: 画面と、ダウンロード（`mode=backup` の POST を非表示の iframe に送る）の処理。表の一覧を `SHOW TABLE STATUS` で自分で問い合わせる。
- `manager/processors/backup/restore.processor.php`（a=305）・`snapshot.processor.php`（a=307）: 復元とスナップショットの作成。
- `manager/includes/cli/cli-helpers.php`: `cli_export_database()`（`mysqldump` を呼ぶか `Mysqldumper::createDump()`）・`cli_export_database_file()`（一時ファイルに書いて rename）・`cli_full_table_name()`（接頭辞を付けて `` `db`.`table` `` にする。`db:count`・`db:describe` が使う）。
- `manager/includes/cli/commands/db-backup.php`・`db-export.php`・`db-import.php`: CLI。`db-backup.php` は世代の数え方を自分で書き、`db-export.php` は接頭辞の付け方を自分で書く。コマンド名 `a:b` は `evo.php` で `commands/a-b.php` に読み替える。
- `manager/includes/traits/document.parser.subparser.trait.php`: `snapshot()`（`$modx->snapshot()`）と `getBindDenyDirs()`（バインドで読ませない置き場所。`EvoBackupManager::dir()` に加え `temp/backup`・`assets/backup`・`snapshot_path` を自分で並べる）。
- `manager/actions/report/sysinfo.static.php`（a=53）: 4 か所目の表の一覧と、最適化のリンク（a=54）。
- `manager/processors/db/optimize_table.processor.php`（a=54）: 最適化と、ログの表を空にする。`manager/processors/db/empty_table.processor.php`（a=55）: `manager_log` を空にする別の入口。
- `install/actions/summary.php`: `temp/backup/.htaccess` を、保存先の判定を通さずに決め打ちで置く。
- つなぎ: `manager/index.php`（a=93・305・307・54・55 のルーティング）、`manager/includes/csrf_token.php`（POST の要る操作の一覧）、`manager/includes/actionlist.inc.php`、`manager/frames/menu.php`・`manager/actions/main/welcome.static.php`（メニューとダッシュボード）、`manager/actions/permission/mutate_role.dynamic.php`・`manager/processors/permission/save_role.processor.php`（権限 `bk_manager`）、`manager/actions/report/logging.static.php` と言語ファイルの `mgrlog_sortinst`（a=55 へのリンク）、言語ファイルの `bkmgr_`・`backup`・`bk_manager`・`database_table_*`。
- 文書: `manager/includes/cli/README.md`（`db:*`）、`assets/docs/core-issues.md` の「Mysqldumper クラスの設計上の制約」、`assets/docs/site-url.md`（復元が `fixed_site_url` を残す）、`.agent/plans/2026-05-06-evo-system-upgrade-command.md`（`EvoBackupManager` と `cli_export_database_file()` を使う計画）。

インストーラー（`install/`）からは `EvoBackupManager::HTACCESS` だけを使い、ダンプ・復元は使わない（`grep -rn "Mysqldumper\|EvoBackupManager\|cli_export_database" install/` で確かめた）。

DocumentParser への影響: `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` のどれにも影響しない。触るのは、要求ごとの処理の外で呼ばれる `snapshot()` と、バインドの読み込みで使う `getBindDenyDirs()` だけ。キャッシュへの影響は、復元の後の `evo()->clearCache()`（今と同じ）だけ。

## Plan of Work

下の層（表の一覧 → ダンプの形 → 保存先）から先に 1 か所に寄せ、その上に手順を置き、最後に入口と言葉を変える。下の層を先にするのは、上の層（手順・入口）を寄せるときに、移した先がもう 1 つに決まっているようにするため。各段階は「移すだけで動きを変えない PR」と「動きを変える PR」を分け、動きを変えない PR は修正前と同じ出力になることで確かめる。

S1（表の一覧と状態。動きを変えない）: `EvoDbTables` を作り、`names()`（接頭辞の表の名前。LIKE の `_`・`%` をエスケープ）、`status()`（`SHOW TABLE STATUS` の行。空にしてよい表は `COUNT(*)` の正確な行数に差し替える）、`qualify($name)`（接頭辞が無ければ付け、名前の文字を確かめる。`db:count`・`db:describe` 向けに `` `db`.`table` `` を返す `qualifiedWithDb()` も持つ）、`exists($name)`、`truncatable()`、`isOwn($name)`（最適化してよい表か）を置く。`Mysqldumper::getTableNames()`、`bkmanager.static.php` と `sysinfo.static.php` の問い合わせ、`EvoBackupManager::truncatableTables()`・`tableExists()`、`optimize_table.processor.php` の判定、`cli_full_table_name()`、`db-export.php` の接頭辞の付け方、`db-backup.php`・`db-import.php` の表の一覧を、これに置き換える。`bkmanager.static.php` の使われていない `_dbtables` のチェックも消す（いつも空なので、出力は変わらない）。

S2（表を空にする入口。動きを変える）: a=55 を消し、`logging.static.php` のリンクと言語ファイルの `mgrlog_sortinst` の `href` を a=54 へ向け、`optimize_table.processor.php` の戻り先に `logging`（a=13）を足す。`manager/index.php`・`csrf_token.php`・`actionlist.inc.php` から 55 を消し、`empty_table.processor.php` を消す。

S3（ダンプの形。動きを変えない）: `EvoSqlDump` を作る。書く側は `Mysqldumper::dumpToFile()` を移し、`write($stream, array $tables, array $options)`（options: `drop`・`structureOnly`（構造だけ書く表の一覧。スナップショットのログの表）・`contentsOnly`）と、一時ファイルに書いてから rename する `writeFile($path, ...)` にする。表の一覧は `EvoDbTables` から受け、`SHOW CREATE TABLE` は書き出す表にだけ打つ。`system_settings` の値の置き換え（`[(base_path)]` など）は書く側に残す。ヘッダーの `site_name` の行はやめる（コメントだけの違い）。読む側は `statements($stream)`（ジェネレーター）で、今の `EvoBackupManager::restore()` の分け方と先頭のコメントの除き方（`stripLeadingComments()`）を移す。S3 では `restore()` は今のとおり文字列を受け、`php://memory` に書いてから `statements()` に渡す（流して読むのは S6）。`Mysqldumper` と `mysql_dumper.class.inc.php` を消し、呼び出し元の `global $modx;` も消す。`cli_export_database()` の `php` の書き手は `EvoSqlDump` を使い、標準出力へは `php://output` に書く。

S4（保存先と世代。動きを変えない）: `EvoSnapshotStore` を作り、`dir()`・`ensureDir()`・`HTACCESS`・`autoFilename()`・`isValidFilename()`・`normalizeName()`（`.sql` の付け方。`snapshot.processor.php` と `snapshot()` の写しをまとめる）・`path()`・`files()`・`prune()`・`counts($max)`（自動の名前とそれ以外の件数。`db-backup.php` の数え方を移す）・`denyDirs()`（バインドで読ませない置き場所の一覧。`getBindDenyDirs()` の並べ方を移す）・`static protect($dir)`（保護の `.htaccess` を置く。DB の設定を読めないインストーラーから呼ぶ）を置く。`EvoBackupManager` は保存先のメソッドを消し、`store()` で `EvoSnapshotStore` を返す。`getBindDenyDirs()` は `denyDirs()` を呼び、`summary.php` は `EvoSnapshotStore::protect()` を呼ぶ。

S5（保存先の黙った切り替えをやめる。動きを変える。本人の確認の後）: Decision Log の `snapshot_path` の決まった案を入れる。`assets/backup` への分岐を消す（`denyDirs()` には残す）。

S6（作成と復元の手順。動きを変えない）: `EvoBackupManager` を手順だけにする。`createSnapshot($name, $options)`（書き手を options の `driver` で受ける。管理画面は `php`、CLI は `mysqldump` が既定）、`restoreFile($path, $preRestore)`（`EvoSqlDump::statements()` でファイルから流して読む）、復元の前の控え、環境ごとの設定の保持、キャッシュの作り直し。`restore.processor.php` はアップロードの一時ファイルとスナップショットのパスを `restoreFile()` に渡す。`db-backup.php`・`db-import.php` は `createSnapshot()`・`restoreFile()` を呼ぶ。`$modx->snapshot()` は `normalizeName()` と `createSnapshot()` を呼ぶだけにする。`assets/docs/backup.md` を作り、5 つの受け持ちとダンプの約束を書き、AGENTS.md のドキュメントマップに足す。`assets/docs/core-issues.md` の「Mysqldumper クラスの設計上の制約」を解決済みにする。

S7（復元の前の確かめと作成の結果。動きを変える）: 復元の前の下読み（接頭辞と `DELIMITER`）、`snapshot()` の戻り値、`db:backup` のログの表の扱いを Decision Log のとおりに変える。

S8（入口を薄くする。動きを変える）: ダウンロードを a=306 の processor に移す。`processors/backup/` の 3 つと CLI のコマンドは、`EvoBackupManager` を呼んで結果を知らせるだけにする。画面の残り（`#fff` を決め打ちした `<style>`、`selectAll()` の古い JS、`postv('mode')`、ダウンロードのファイル名を引用符で囲む）を片付ける。#1560 で済んだものは M0 で外す。失敗の知らせ方は、#1560 で決まった形（画面の `EvoDialog`、processor はセッションの結果を画面に出す）にそろえる。

S9（言葉。動きを変える。本人の確認の後）: Decision Log の決まった案のとおりに、言語ファイル・CLI のコマンド名・README・`assets/docs/backup.md` を変える。

## Concrete Steps

各段階の共通の進め方（`/run-plan` の「1 件ごとの繰り返し」に従う）:

1. 段階のブランチを作る（S1 は `refactor/1561-backup-reorg`。S2 以降は前の段階の PR のマージ後に `git switch -c refactor/1561-backup-<段階名> origin/main`）。
2. 修正前の記録: `git worktree add --detach <一時パス> origin/main`（使えないときは `git archive origin/main` を作業用ディレクトリへ展開）で修正前のツリーを用意し、別の `EVO_VALIDATE_NAME` の検証環境で M0 の確認スクリプトを流して結果を残す。
3. 実装し、変えた PHP に `php -l` を通す（検証用イメージの `php` で）: `docker exec <名前>-app php -l /var/www/html/<ファイル>`。
4. 修正後の検証環境で同じ確認スクリプトを流し、修正前と比べる。動きを変えない段階は差が無いこと、動きを変える段階は計画どおりの差だけであることを確かめる。

M0（着手前の確認。PR なし）:
- `gh pr list --search "1560" --state merged` で #1560 のマージを確かめる。まだなら止まる。`git log --oneline origin/main -- manager/includes/mysql_dumper.class.inc.php manager/includes/backup_manager.class.inc.php manager/actions/tool/bkmanager.static.php manager/processors/backup/` で入った変更を読み、この計画の Context・Plan of Work（S3・S8）から済んだものを外して、この ExecPlan を更新してコミットする。
- 確認スクリプトを作業用ディレクトリ（リポジトリの外）に作る。中身は次のとおり（検証環境のアプリのコンテナで `php` で動かす）。
  - `fixture`: 接頭辞の表に、CRLF・単独の CR・LF・`'`・`"`・`\`・NUL・`;` と改行の並び・日本語・絵文字を含む値の文書を `db()->insert(db()->escape(...))` で入れる。`manager_log` に数行入れる。接頭辞の無い表 `other_table` と、接頭辞の `_` を別の文字に変えた名前の表（例 `modxXfake`）を作る。
  - `checksum`: 接頭辞の表ごとに `CHECKSUM TABLE` と `SHOW CREATE TABLE` を出す（往復の前後で同じかを比べる）。
  - 表の一覧: `php evo.php db:tables` と、管理画面 a=93・a=53 の表の行の名前（`curl` で取り、`class="table-name"` の中身を抜く。管理画面の `curl` には `Accept-Language` を付ける）。
- mysqldump の出力: DB のコンテナで `docker exec <名前>-db mariadb-dump -uuser -ppassword --skip-triggers evodb <表...> > <作業用>/mysqldump.sql`。MySQL 5.7 の最終確認では `mysql:5.7` のコンテナの `mysqldump` を使う。CLI の `mysqldump` の書き手を確かめるときは、アプリのコンテナに一時的に `apt-get install -y default-mysql-client`（`mariadb-client` でもよい）を入れる（コンテナを作り直せば消える）。

S1 の確かめ: 修正前後で、`db:tables`・a=93・a=53 の表の名前と行数の列、`php evo.php db:count manager_log`・`db:describe site_content`・`db:export --tables=site_content --driver=php --output=/tmp/a.sql`（`-- Generation Time` の行を除いて）が同じ。違ってよいのは `modxXfake` が a=93・a=53 に出なくなることだけ。a=54 で `manager_log` を空にする・`site_content` を最適化する・`other_table` を渡すと権限エラー、が修正前と同じ。期待: `diff` が上の 1 点だけ。

S2 の確かめ: 管理ログの画面（a=13）の「empty」のリンクで `manager_log` が空になり、管理ログの画面に戻る。`index.php?a=55` は「不明な操作」になる（修正前は空にした）。`settings` だけを持つロールの利用者が a=54 の `truncate` を POST すると権限エラー。`grep -rn "a=55\|empty_table" manager/` が 0 件。

S3 の確かめ（往復）: `fixture` を入れた DB で、管理画面のダウンロード（`DROP 文を生成` あり）・スナップショット・`db:export --driver=php`・`db:backup --driver=php` のそれぞれの出力を、修正前と修正後で `-- Generation Time`・`--  Server version` などのヘッダーのコメントを除いて `diff` し、違いがヘッダーの `site_name` の行だけであることを確かめる。それぞれを復元（管理画面のアップロードと `EVO_CLI_IMPORT=1 php evo.php db:import <ファイル>`）して `checksum` が元と同じ。mysqldump の出力も復元でき、`checksum` が同じ。`grep -rn "Mysqldumper\|mysql_dumper" manager/ install/` が 0 件。

S4 の確かめ: 修正前後で、スナップショットの一覧・自動の名前・`prune()` で消える世代（自動の名前を 12 件、名前付きを 2 件、`pre-restore-` を 5 件置いて `db:backup --driver=php --max=10` を流す）・`db:backup` の最後の行（`auto-named N/M, other K`）・`getBindDenyDirs()` の一覧（`docker exec <名前>-app php -r` で bootstrap して出す）が同じ。新規インストールで `temp/backup/.htaccess` ができる。

S5 の確かめ（案 A の場合）: `snapshot_path` に `temp/backup2`（サイトの下）を入れるとそこに作られる。サイトの外（`/tmp`）を設定の画面で保存しようとすると保存されず、DB に直接入れた場合は、スナップショットの作成が「保存先が使えない」と知らせて何も作らない（修正前は `temp/backup/` に作った）。`temp/backup` を消して `assets/backup` だけを置くと、`temp/backup/` が作られてそこに保存される。`assets/backup` は `denyDirs()` に出る。

S6 の確かめ: S3 と同じ往復を、管理画面・CLI の両方で行い、修正前と `checksum`・復元の結果の表示（実行した文の数・飛ばした数）が同じ。大きなダンプ: 本文 1MB の文書を 200 件入れた DB のダンプ（約 200MB）を `php -d memory_limit=128M evo.php db:import` で戻すと、修正前は `Allowed memory size` で止まり、修正後は通る（これは計画どおりの違い）。`$modx->snapshot('abc')` が `abc.sql` を作り、大きさを返す（修正前と同じ）。

S7 の確かめ: 接頭辞 `other_` で作ったダンプ（別のサイトのダンプ）を管理画面と `db:import` で戻すと、「接頭辞が違う」と出て、`pre-restore-` の控えも作られず、`checksum` が変わらない（修正前は「成功」と出て `other_` の表が増えた）。`db:export`（既定の `--routines --triggers`）の出力にトリガーを含めて戻すと「トリガーやルーチンを含むダンプは戻せない」と出る。`$modx->snapshot('a/b')` が `false`（修正前は `null`）。`db:backup`（mysqldump）で作ったスナップショットに `manager_log` の `CREATE TABLE` があり、`INSERT INTO` が無い（管理画面のスナップショットと同じ）。

S8 の確かめ: 管理画面で、表を選ばずにダウンロード・成功（ファイル名がそろい、中身が S3 と同じ）・失敗（`MODX_CACHE_PATH` を書き込めなくする）、スナップショットの作成の成功・失敗（保存先を書き込めなくする）・名前の不正、復元のアップロードの失敗（上限を超えるファイル）・スナップショットの名前の不正、のそれぞれで画面に出るものを、修正前と比べる（#1560 で決めた知らせ方にそろっている）。画面はシェルの遷移と全体の読み込み（F5）の両方で開く。`index.php?a=93` に `mode=backup` を POST しても何もダウンロードされない。

S9 の確かめ: 決まった案のとおりに画面の文言・CLI のコマンド名が変わり、`grep -rn "db:backup" manager/ assets/docs/` が案に合う。

最終確認: S9（または最後の段階）の後、`php:7.4-apache` のイメージ（`.claude/skills/adaptive-validation-runner/references/environment.md` の「ほかの PHP の版で検証するとき」）と `mysql:5.7` の DB で、S3 の往復と S7 の接頭辞の確かめを流す。

想定するコミットの単位（各段階の PR の中）:
- S1: `refactor(backup): 表の一覧と状態を EvoDbTables にまとめる`
- S2: `refactor(backup): ログの表を空にする入口を a=54 にまとめる`
- S3: `refactor(backup): ダンプの書き出しと読み込みを EvoSqlDump にまとめる`
- S4: `refactor(backup): スナップショットの保存先と世代を EvoSnapshotStore にまとめる`
- S5: `fix(backup): 保存先が使えないときに別の場所へ切り替えず知らせる`
- S6: `refactor(backup): 作成と復元の手順をまとめ、復元をファイルから流して読む`
- S7: `fix(backup): 接頭辞の違うダンプとトリガーを含むダンプの復元を断る`
- S8: `refactor(backup): ダウンロードを processor へ移し、入口を薄くする`
- S9: `feat(backup): ダウンロードとスナップショットの言葉をそろえる`（案による）
- 各段階の ExecPlan の記録: `docs(plan): バックアップの整理の S<n> の結果を記録する`

## Validation and Acceptance

すべての段階を終えたとき、次がそろっている。
- 管理画面のダウンロード・スナップショット・`db:backup`（PHP と mysqldump の両方）・`db:export --driver=php`・mysqldump の出力のどれを、管理画面のアップロード・スナップショットの一覧・`db:import` のどれで戻しても、`CHECKSUM TABLE` が元と同じ（CRLF・単独の CR・NUL・`'`・`\`・日本語を含む値も元のとおり）。PHP 8.4（標準）と PHP 7.4・MySQL 5.7 の両方で。
- 別の接頭辞のダンプ、トリガーを含むダンプを戻そうとすると、DB を書き換えずに理由が出る。
- 表の一覧（管理画面のバックアップ・システム情報・CLI）・保存先と世代（管理画面・CLI・`$modx->snapshot()`・バインドの読み込み・インストーラー）・作成と復元の手順が、それぞれ 1 つのクラスから来ている（`grep -rn "SHOW TABLE STATUS" manager/` が `db_tables.class.inc.php` だけ、`grep -rn "assets/backup\|config('snapshot_path'" manager/includes/ manager/processors/ manager/actions/` が `snapshot_store.class.inc.php` だけ）。
- 保存先が使えないとき、別の場所へ黙って保存しない。
- 大きなダンプ（上の S6 の条件）を `memory_limit=128M` で戻せる。
- 言葉は本人が選んだ案のとおり。

完了の扱い: 最後の段階の PR がマージされたら、「ExecPlan の実装と検証が完了した」と告げ、ロードマップの更新とこの ExecPlan のアーカイブの前に本人の確認を取る（`.agent/PLANS.md` の「完了処理プロトコル」。`exec-plan-runner` の `/finish-task` で行う）。

## Idempotence and Recovery

各段階は別のブランチと PR なので、途中で止まったら、その段階のブランチを捨てて `origin/main` から作り直せばよい（前の段階はマージ済みで影響しない）。確認スクリプトは作業用ディレクトリにあり、修正前の結果はいつでも `origin/main` の detached のツリーで取り直せる。検証環境は `EVO_VALIDATE_NAME=<名前> docker compose ... down -v --rmi local` で消し、追跡外の `manager/includes/config.inc.php` も消す。復元の確かめで DB を壊したときは、`down -v` で DB のボリュームごと作り直し、`setup-site.js` で入れ直す。S5・S9 で本人の答えを待つ間は、その段階に入らずに止まる。S5 は S4 だけに依存するので、答えがまだ無いときは S5 を飛ばして S6 以降を先に進め、答えが来たら S5 をそのときの main から作ってよい。

## Artifacts and Notes

- Issue: #1561（対象）、#1560（依存。不具合の修正）
- ブランチ: `refactor/1561-backup-reorg`（`gh issue develop 1561` で作成。S1 に使う。S2 以降の段階のブランチと PR は、作ったらここに書き足す）
- PR: 未作成（`/run-plan` が S1 で作る）
- 段階ごとに PR を分ける（1 段階を 1 PR）。S1〜S8 は `Refs #1561`、最後の段階の PR で `Closes #1561`。S9 を案 A で次の版に回すときは、S8 の PR を `Closes #1561` にし、S9 は別の Issue を起こして `develop/1.4` に向ける。

## Interfaces and Dependencies

- 依存: #1560 のマージ（M0 で確かめる）。
- 作るクラスの公開メソッド（呼び出し元はこの計画のファイルだけ）:
  - `EvoDbTables`: `names(): array`、`status(): array`、`qualify(string $name): string`（不正なら `''`）、`qualifiedWithDb(string $name): string`、`exists(string $name): bool`、`truncatable(): array`、`isOwn(string $name): bool`
  - `EvoSqlDump`: `write($stream, array $tables, array $options = []): bool`、`writeFile(string $path, array $tables, array $options = []): bool`、`statements($stream): Generator`（1 文ずつ、文の番号と本文を返す）、`tablesIn($stream): array`（下読み。S7）
  - `EvoSnapshotStore`: `dir()`、`ensureDir()`、`normalizeName()`、`isValidFilename()`、`autoFilename($prefix = '')`、`path($name)`、`files()`、`prune($max)`、`counts($max)`、`denyDirs()`、`static protect($dir)`、定数 `HTACCESS`・`KEEP`・`PRE_RESTORE_KEEP`・`AUTO_NAME_PATTERN`・`PRE_RESTORE_PREFIX`
  - `EvoBackupManager`: `store()`、`createSnapshot($name = '', array $options = [])`、`restoreFile($path, $preRestore = null)`（戻り値の配列は今の `restore()` と同じキー）
- CLI の `mysqldump` を呼ぶ処理（`cli_export_database()`・`cli_export_database_file()`・`cli_mysql_defaults_file()`）は CLI だけのものなので `cli-helpers.php` に残し、`php` の書き手を `EvoSqlDump` に任せる。
- ほかの計画: `.agent/plans/2026-05-06-evo-system-upgrade-command.md` は `EvoBackupManager` の `ensureDir()`・`dir()`・`autoFilename()`・`HTACCESS` と `cli_export_database_file()` を使う前提で書かれている。S4・S6 の PR で、その計画の Interfaces and Dependencies と Decision Log の名前を新しい置き場所（`EvoSnapshotStore`）に書き直す。
- PDO 移行（#677）: この計画の後に入るとき、DB への問い合わせは `EvoDbTables` と `EvoSqlDump` だけを見ればよい。
