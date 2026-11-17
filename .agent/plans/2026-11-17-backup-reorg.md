# ExecPlan: バックアップ・スナップショット・復元の役割を整理する

## Purpose / Big Picture

データベースの控え（管理画面のバックアップ・スナップショット・復元、CLI の `db:backup`・`db:export`・`db:import`）を、「ダンプの形」「表の一覧」「保存先と世代」「作成と復元の手順」「入口」の 5 つの受け持ちに分け、それぞれを 1 か所に置く。利用者にとっては、管理画面と CLI で同じ控えが同じ手順で作られて戻せるようになり、別のサイトのダンプを戻して「成功」と出る、置き場所の設定が黙って無視される、大きなダンプでメモリが足りなくなる、といった分かりにくい動きが無くなる。

## Progress

- [x] (2026-11-17) ExecPlan 作成
- [x] (2026-11-17) M0 着手前の確認: #1560 のマージを確かめ、Context を今の main に合わせて直す。往復の確認スクリプトを用意する（PR なし）
- [x] (2026-11-17) S1 表の一覧と状態を `EvoDbTables` にまとめる（動きを変えない。出てよい違いは Decision Log の 3 点）
- [x] (2026-11-17) S2 表を空にする入口を a=54 の 1 つにする（動きを変える）
- [x] (2026-11-17) S3 ダンプの書く・読むを `EvoSqlDump` にまとめ、`Mysqldumper` を消す（動きを変えない。違いはダンプの頭のサイト名の行と、`db:export --driver=php` を標準出力へ書くときに途中で失敗すると途中までが出ること）
- [x] (2026-11-17) S4 保存先と世代を `EvoSnapshotStore` にまとめる（動きを変えない。違いはインストーラーが既存の保護の `.htaccess` を書き換えなくなることだけ）
- [x] (2026-11-18) S5 `snapshot_path` をグローバル設定の画面に出し、保存先の黙った切り替えをやめる（動きを変える）
- [x] (2026-11-18) S6 作成・復元の手順を `EvoBackupManager` にまとめ、復元をファイルから流して読む（動きを変えない）
- [x] (2026-11-18) S6b PHP の書き出しを複数行の INSERT にする（動きを変える）
- [x] (2026-11-18) S7 復元の前の確かめを足し、作成の結果をそろえる（動きを変える）
- [ ] S8 入口を薄くする: ダウンロードを processor へ移し、失敗の知らせ方をそろえる（動きを変える）
- [ ] S9 言葉をそろえ、CLI の `db:backup` を `db:snapshot` に改名する（動きを変える。別の Issue で `develop/1.4` に入れる）
- [ ] 最終確認: PHP 7.4・MySQL 5.7 で往復を確かめる

## Surprises & Discoveries

- 2026-11-17: `snapshot_path` は `install/sql/default_settings.sql` にも設定の画面（`manager/includes/settings_form.definition.inc.php`）にも無い。`evo()->config('snapshot_path', '')` で読むだけで、値を入れる手段は DB を直接書き換えることしか無い（`grep -rn snapshot_path install/ manager/includes/settings_form.definition.inc.php` が 0 件）。
- 2026-11-17: 検証用のアプリのイメージ（`manager/docker/Dockerfile`）には `mysqldump` が入っていない。`db:backup`・`db:import` の既定（`mysqldump`）は検証環境ではそのままでは動かない。mysqldump の出力は DB のコンテナの `mariadb-dump` で作るか、アプリのコンテナに一時的にクライアントを入れて確かめる（Concrete Steps の M0）。
- 2026-11-17: `db:export` の既定は `--routines --triggers` 付きで、出力に `DELIMITER ;;` が入りうる。今の復元（`;` と改行で分ける）はこれを読めない。`db:backup`・`db:import` の控えは `--skip-triggers` なので読める。
- 2026-11-17: 表の名前の正規化（接頭辞を付ける）は `cli_full_table_name()` と `db-export.php` の中の 2 か所に同じ処理がある。表の一覧（`SHOW TABLE STATUS ... LIKE`）は `Mysqldumper::getTableNames()`・`bkmanager.static.php`・`sysinfo.static.php` の 3 か所で、`_` を LIKE のためにエスケープしているのは `getTableNames()` だけ。表の有無は `EvoBackupManager::tableExists()`・`db()->tableExists()`・`EvoMigrationHelper::tableExists()`（INFORMATION_SCHEMA）の 3 通り。
- 2026-11-17: a=55（`manager/processors/db/empty_table.processor.php`）は `settings` の権限だけで `manager_log` を空にする。a=54 の空にする処理は `settings` かつ（`logs` か `bk_manager`）。a=55 への入口は管理ログの画面（`logs` が要る）の説明文 `mgrlog_sortinst` のリンクだけ。
- 2026-11-17（M0）: #1560 は PR #1562 で main に入った（77a59e271）。ダンプの値は `db()->escape()` で書かれ、CR が `\r` として残るようになったので、復元の分け方（`;` の後の改行）に掛かる生の改行は値の中に出ない。スナップショットのログの表は `CREATE TABLE IF NOT EXISTS` だけになり、管理画面と processor の失敗の知らせ方も決まった。計画の S3・S7・S8 から、済んだ作業を外し、決まった形を引き継ぐ書き方に直した。
- 2026-11-17（M0）: `sysinfo.static.php`（a=53）は `SHOW TABLE STATUS` の `Rows` をそのまま出し、`bkmanager.static.php`（a=93）は空にしてよい表だけ `COUNT(*)` に差し替える。同じ表の行数が 2 つの画面で違いうる（InnoDB の `Rows` は見積もり）。
- 2026-11-17（M0）: `mysqldump` には `CREATE TABLE IF NOT EXISTS` を書かせる選択肢が無い。S7 の `db:backup` のログの表は、2 回目の出力を書き換えて PHP の書き手と同じ形にする。
- 2026-11-17（S1）: `rotate_log()` の `automatic_optimize` も、接頭辞で絞らない 5 か所目の表の一覧だった（同じ DB のすべての表を、名前を囲まずに `OPTIMIZE` していた）。
- 2026-11-17（S1）: 確かめの結果。修正前を記録し、同じ DB（`baseline.sql`）に戻して修正後を記録して diff を取った。`db:tables`・`db:count`・`db:describe`・`db:export`（php・mysqldump）・`db:backup`（php・mysqldump）・`db:import`（両方の出力）と復元後の `CHECKSUM TABLE`・a=54 の 8 通りが同じ。違いは a=93・a=53 の `modxXfake` と、`recent_update` の時刻だけ。a=53 のログの表の行数は、フィクスチャで `ANALYZE TABLE` をしたため見積もりと正確な数が一致し、差は出なかった。自動最適化は修正後 36 表（このサイトの表だけ）。`EvoDbTables` の単体と `db:count`・`db:describe`・`db:export --driver=php` は `php:7.4-cli` でも動いた。
- 2026-11-17（S2）: a=55 は CSRF の一覧（`csrf_token.php`）に入っていたので、GET で開くと 405 になり、POST だけが空にしていた。修正後は GET も POST も「まだ実装されていない機能」の画面になる（`index.php` の `default`）。
- 2026-11-17（S2）: 管理ログの画面（a=13）の「empty」のリンクは、`logs` を持つ利用者すべてに出る。`settings` を持たない利用者（`logs` だけのロール）が押すと、修正前（a=55）も修正後（a=54）も「この操作を行なう権限がありません」になり、ログは消えない。リンクを権限で出し分けるのは S2 の範囲の外として残した。
- 2026-11-17（S2）: 確かめの結果。既定のロール（ウェブマスター・投稿担当者）と、`settings` だけ・`logs` だけ・`settings`+`logs`・`settings`+`bk_manager` の 4 つのロールの利用者で、a=13 のリンクを押す・a=55 を POST と GET で開く・a=54 に `from=logging` で POST する、を修正前後で比べた。違いは計画どおりの 3 点だけ: リンクの行き先が a=54 になる、a=55 がどの利用者でも「まだ実装されていない機能」になる（修正前は `settings` を持つ利用者が POST で空にできた。`settings` だけのロールもここで空にできなくなる）、a=54 の `from=logging` の戻り先が a=13 になる（修正前は戻り先が無くダッシュボード）。`settings`+`bk_manager` で `logs` の無い利用者が `from=logging` で直接 POST すると、空にしたあと a=13 に戻って権限のエラーになる（画面にリンクの無い操作なので残した）。`grep -rn "a=55\|empty_table" manager/ install/ assets/docs/` は 0 件、`php:7.4-cli` の `php -l` と security-lint は通過。
- 2026-11-17（S2。#1566 への Codex の指摘）: S1 で入れた `EvoDbTables::qualify()` は「接頭辞で始まれば、もう付いている」とみなしていた。接頭辞が `manager_` のサイトでは `qualify('manager_log')` が `manager_log` を返し、管理ログの画面のリンク（S2 で足した）が a=54 の `truncatable()` で断られて権限エラーになる。同じ取り違えは、修正前（main 10d3bfbed）の CLI にもあった: 接頭辞 `manager_` で `db:count manager_log` は無い表を数えようとしてエラー、`db:export --tables=manager_log,site_content --driver=php` は `manager_log` を黙って書かず、mysqldump では「Couldn't find table」で終わる。下線の無い接頭辞 `sys` では `db:count system_settings` がエラー。S1 の前の `cli_full_table_name()` と `db-export.php` も同じ推し量り方だった。
- 2026-11-17（S2）: 呼び出し元の洗い出し。接頭辞を除いた名前を本体が決め打ちで渡すもの: `truncatable()`（`event_log`・`manager_log`）、`EvoBackupManager` の `system_settings`・`system_cache`、管理ログの画面のリンク（`manager_log`）。利用者が入力するもの（完全な名前と接頭辞を除いた名前のどちらもありうる）: CLI の `db:count`・`db:describe`・`db:export --tables=`。完全な名前を受けるもの: `exists()`（`EvoBackupManager`・a=54）、`isOwn()`（a=54 の最適化）。
- 2026-11-17（S2）: 確かめの結果。接頭辞 `modx_`・`manager_`・`sys` のサイト（`modx_` の表を写して作り、`config.inc.php` の接頭辞を切り替えた）で、`db:count`・`db:describe`・`db:export`（php・mysqldump）が接頭辞の付いた名前と付いていない名前のどちらでもこのサイトの表を指し、無い表はエラーで終わる。管理ログの画面のリンクは `<接頭辞>manager_log` を指して空にでき、a=54・a=93 の空にするリンクも同じ。`db:backup --driver=php` → `db:import` は 3 つとも「Import completed」で警告なし（`system_settings` を取り違えない）。S2 の利用者 6 人の確かめは、この直しの後も結果が同じ。
- 2026-11-17（S3）: `Mysqldumper` のコンストラクタは `addDropCommand(false)` のあとで `_isDroptables = true` にしていたので、DROP TABLE を書くのが既定だった（スナップショット・`db:export --driver=php`・`db:backup --driver=php`・復元の前の控え）。書かないのは、管理画面のダウンロードで「DROP 文を生成」を外したときだけ。`EvoSqlDump::write()` は既定で書かず、呼び出し側が `drop` を渡す（最初の実装でこれを落とし、往復の確かめで「Table ... already exists」になって気付いた）。
- 2026-11-17（S3）: 確かめの結果。修正前を記録し、同じ DB（`baseline-s3.sql`。値に CRLF・単独の CR・LF・`'`・`"`・`\`・NUL・`;` と改行・`; ` とタブと改行・`;` と CRLF・`-- `・`#` で始まる行・日本語・絵文字を含む文書）に戻して修正後を記録し、diff を取った。ダンプは CLI（`db:export --driver=php` のファイルと標準出力、`db:backup` の php と mysqldump）・管理画面（ダウンロード（DROP あり）・スナップショット・コンテンツのみのスナップショット・アップロードの復元の前の控え）・DB のコンテナの `mariadb-dump`。違いは時刻（`recent_update`・ログイン時刻）だけ（サイト名の行は比べる前に除いた）。それぞれを `db:import` で戻した結果（実行した文・飛ばした文の数）と `CHECKSUM TABLE`、管理画面のアップロード（ダウンロード・`mariadb-dump`・`db:export --driver=php`・`db:backup` の mysqldump の出力）とスナップショットの一覧からの復元の結果も、修正前と同じ。接頭辞 `manager_`・`sys` のサイトでも同じ往復が通り、ダンプにはそのサイトの表だけが入る。文の分け方は、修正前の `preg_split` の分け方と 15 通りの入力（実際のダンプ 3 つを含む）で番号と文が一致した（PHP 8.4・7.4）。`db:export --driver=php` の出力は PHP 7.4 と 8.4 で同じ。管理画面の往復で `user_attributes` の CHECKSUM が元と違うのは、ダンプを取る前のログインで最終ログイン時刻が変わるためで、修正前も同じ。
- 2026-11-17（S3。自己レビュー）: `writeFile()` は `tempnam()` の一時ファイルを rename するので、できるファイルの権限は 0600 になる（スナップショットと管理画面のダウンロードは修正前も同じ）。修正前の `db:export --driver=php --output=` は `file_put_contents()` でその場に書いていた（権限は umask のとおり、既存のファイルの権限・所有者・リンクの先はそのまま。`db:backup --driver=php` も同じ経路）。CLI と Web が別のユーザーで動くサイトで、CLI で作ったスナップショットを管理画面から読めなくならないよう、CLI の `--output` は書き終えた一時ファイルを `copy()` で写す形にして、修正前と同じにした。
- 2026-11-17（S4）: `snapshot_path` に相対パス（例 `temp/snap2/`）を入れると、`realpath()` が作業ディレクトリから解くので、CLI（`/var/www/html` から動く）は `temp/snap2/` に書き、管理画面（`manager/` から動く）は `temp/backup/` を見る。修正前から同じで、S4 では変えない。S5 で値を `[(base_path)]` の書き方で保存し、読むときにサイトのフォルダから解くようにすれば無くなる。
- 2026-11-17（S4）: ファイルブラウザー（`manager/media/browser/evo/lib/PathGuard.php`）も `temp/backup`・`assets/backup` を決め打ちで読ませない一覧に持つ。`snapshot_path` は入っていない。S5 で `snapshot_path` を画面から変えられるようにするときに、`EvoSnapshotStore::denyDirs()` を使うかを決める。
- 2026-11-17（S4）: インストーラーは、`temp/backup/.htaccess` を毎回上書きしていた。`EvoSnapshotStore::protect()` は、無いか古い書き方（`Order deny,allow` と `Deny from all` だけ）のときだけ書くので、利用者が書き換えた保護の `.htaccess` はアップデートで戻らなくなる。新規インストールでは今と同じファイルができる（確かめた）。
- 2026-11-17（S4）: 確かめの結果。修正前を記録し、修正後と突き合わせた。保存先の決まり方（`snapshot_path` が無い・サイトの下・サイトの外・存在しない・サイトのルート・相対パス・`assets/backup` だけが在る）ごとに、`getBindDenyDirs()`・`db:backup --driver=php` の書き先と最後の行・a=93 の保存先の表示・a=307 で作られる場所、世代（自動の名前 12・名前付き 2・復元の前の控え 5 を置いて `db:backup --max=10`・`--max=3`・管理画面のスナップショット・管理画面の復元）で消えるファイルと残るファイル、古い書き方の `.htaccess` の書き換え、`$modx->snapshot()` の戻り値（`probe-a`・`probe-b.SQL`・`probe-c.sql`・`a/b`・`bad name`・空）が、すべて同じ。新規インストールで `temp/backup/.htaccess` が修正前と同じ中身でできる（ディレクトリは修正前と同じ 0777）。グローバル設定の保存で、無い `temp/backup/` が作られる。
- 2026-11-18（S5）: 確かめの結果（修正前は main 172f7faeb を同じ環境で記録）。自己レビューのあとの追加: 保存で ` temp/sp `（前後の空白）は `[(base_path)]temp/sp/`、`manager/`・`content/`・`assets/templates/x/` は「使えません」で保存されず、`manager/`・`content/` に `.htaccess` は作られない。書き込めない保存先（root の 555）が今の値のままでも、ほかの設定は保存できる。`problem()` の判定は PHP 8.4 と php:7.4-cli の両方で 19 通りを確かめた（日本語の名前・`C:/foo/`・`..`・サイトの親を含む）。グローバル設定（a=17）の「ファイル」タブの最後の小見出し「スナップショット」に欄が出て、全体の読み込みとシェルの遷移の両方で既定 `[(base_path)]temp/backup/` を表示し、a=12・a=74 には出ない。保存: `[(base_path)]temp/snap2/`・`<サイト>/temp/newdir`（無い）・`temp/rel`（相対）は `[(base_path)]…/` で保存されて作られ、保護の `.htaccess` も置かれる。空は既定値で保存される。`/tmp/outside/`・サイトのルート・`temp/../../etc/` は「使えません」と知らせ、入力を保ったまま a=17 に戻り、DB は変わらない。DB に直接 `/tmp/outside/`・`<サイト>/temp/../../tmp/outside/` を入れると、a=93 のスナップショットのタブに理由が出て、a=307（作成）・a=305（一覧からの復元）・`$modx->snapshot()`（`false`）・`db:backup`・`db:import`（終了コード 1、何も取り込まない）がすべて断り、システムログに 1 件ずつ残る（修正前は黙って `temp/backup/` に作り、取り込みも行った）。書き込めない保存先（root の 555）も同じく断り、理由は「作れない・書き込めない」で出る。`assets/backup/` だけが在るサイトでは `temp/backup/` に作られ、`assets/backup/` の控えは一覧に出ない（修正前は `assets/backup/` に作った）。`snapshot_path` が `temp/snap2/` のとき、バインドとファイルブラウザー（管理者でない利用者）の守る場所に `temp/snap2/` が入る（修正前は入らなかった）。
- 2026-11-18（S6）: 確かめの結果（修正前は main 4ad093602 を同じ環境で記録）。S3 と同じ往復（CLI の `db:export --driver=php`（ファイル・標準出力）・`db:backup`（php・mysqldump）・`mariadb-dump`・管理画面のダウンロード・スナップショット・コンテンツのみを、`db:import` と管理画面のアップロード・スナップショットの一覧で戻す）で、実行した文・飛ばした文の数、`CHECKSUM TABLE`、ダンプの中身（時刻を除く）が修正前と同じ。DB への問い合わせの回数は増えていない（復元は同じ文を同じ数だけ実行する。作成は、自己レビューで `db:backup` の表の一覧の問い合わせが 1 回増えていると分かり、空の確かめを書き手の中へ移して修正前と同じ 1 回にした）。途中で失敗するダンプ（69 番目の文が不正）は、`db:import` と管理画面のどちらも修正前と同じく 69 番目で止まり、復元の前の控えの名前を知らせる。空のファイルは修正前と同じく断る。`$modx->snapshot()` は `abc`・`abc2.SQL`・空で大きさを返し、`a/b` は `null`（修正前と同じ）。世代の削除と `db:backup` の件数の行（S4 の記録）も同じ。大きなダンプ: 本文が合わせて 229MB の文書 200 件を `db:backup`（mysqldump）で書いた 256MB のダンプを `php -d memory_limit=128M evo.php db:import` で戻すと、修正前は `Allowed memory size of 134217728 bytes exhausted` で何も取り込まずに止まり、修正後は通って最大のメモリが 12MB（計画どおりの違い）。php:7.4-cli でも、PHP の書き手のダンプを `restoreFile()` で戻す往復が通り（実行 468・飛ばし 2、復元の前の控えあり）、`CHECKSUM TABLE` が元と同じ。
- 2026-11-18（S6。自己レビュー）: 復元するファイルを、控えを作ったあとの世代の整理（`prune()`）が先に消しうる（控えが上限まである状態で一番古い控えを戻すとき）。修正前は中身を先にメモリへ読んでいたので戻せた。`restoreFile()` は控えを作る前にファイルを開くようにした（開いたファイルは消されても読める）。開けないときは何もせずに `read_failed` を返し、画面と CLI は修正前と同じ文言（`restore_invalid_file`・`restore_upload_failed`・「failed to read SQL file」）で知らせる。
- 2026-11-18（S6）: PHP の書き手（`EvoSqlDump::write()`）は、表ごとの `SELECT *` の結果を DBAPI がまとめて受け取るので、大きな表ではメモリが要る（上の 229MB の DB で `db:export --driver=php` が 256MB の上限を超えた）。管理画面のスナップショットと復元の前の控えも同じ書き手なので、大きなサイトでは作れないことがある。修正前から同じで、S6 の範囲（復元）の外。
- 2026-11-18（S6）: PHP の書き手は 1 行ごとに 1 つの `INSERT` を書くので、復元の文の数が行の数と同じになる（`mysqldump` は複数行の `INSERT`）。DB サーバーが離れていると、復元が行の数に比べて遅くなる。S6b で扱う（本人の決定）。
- 2026-11-18（S6b）: 確かめの結果（修正前は main b8d24e587 を同じ環境で記録）。テスト用の表を足した: 空の表、1 行だけの表、値が `;` で終わる・`)`・`,`・`),(`・`');` と改行・`);`・`),`・日本語と絵文字・CR を含む表、2 万行の表。往復（S3 と同じ組み合わせ。CLI の `--driver=php` と管理画面のダウンロード・スナップショット・コンテンツのみを、`db:import`・管理画面のアップロード・スナップショットの一覧で戻す）は、すべて `CHECKSUM TABLE` が元と同じ（管理画面の往復の `user_attributes` はログイン時刻の違いで、修正前も同じ）。実行した文の数は、`db:export --driver=php` の復元で 20,496 から 178 に、コンテンツのみのスナップショットで 168 から 37 に減った（mysqldump の出力は 513 のまま）。修正前の形（1 行ずつの `INSERT`）のダンプ 3 つも、修正後の `db:import` で戻せて `CHECKSUM TABLE` が同じ。上限で分かれる場合: `max_allowed_packet` が 16MB（上限 1,046,528 バイト）と 64KB（上限 64,512 バイト）の両方で、2 行でちょうど上限になる文、上限を超える 1 行だけの文、その前後の 1 行の文に分かれ、往復の `CHECKSUM TABLE` が同じ。復元の問い合わせの回数と時間（`restoreFile()`、復元の前の控えを除く、3 回の平均）: 修正前 20,529 回・約 11.7 秒、修正後 211 回・約 0.38 秒（遅延を入れた DB は、検証環境のコンテナに `tc` を使う権限が無いので測っていない。問い合わせの回数がほぼ 100 分の 1 になったので、離れた DB ではその分だけ速くなる見込み）。16MB の DB で書いた上限ちょうどの文を、`max_allowed_packet` が 1MB の DB へ戻す往復も通った（自己レビューの指摘で上限を 1MB から 1,046,528 バイトに直したあと）。php:7.4-cli でも、上限で分かれる場合と、PHP の書き手のダンプを `restoreFile()` で戻す往復が通った。
- 2026-11-18（S7）: 確かめの結果（修正前は main 2fba5e352 を同じ環境で記録。DB は確かめごとに作り直した）。接頭辞 `other_` に書き換えた PHP の書き出しと mysqldump の出力、このサイトの表に接頭辞の無い表 `other_table` を混ぜた mysqldump の出力、トリガーを入れた DB の `db:export`（既定。`DELIMITER` の行が 2 つ）を、`db:import`・管理画面のアップロード・スナップショットの一覧で戻した。修正前は、接頭辞の違うダンプが「成功」になって `other_` の表が 40 個増え（復元の前の控えも作られる）、トリガーのダンプは途中の文で失敗した。修正後は、どれも何も書き換えずに断り（表の数・`CHECKSUM TABLE`・保存先のファイルが前と同じ。復元の前の控えも作られない）、接頭辞の違うダンプはダンプの接頭辞（`other_`。推し量れないときは `?`）・このサイトの接頭辞・外れた表（5 つまで）を、`DELIMITER` のダンプは `mysql` コマンドで戻すことを知らせる。CLI は終了コード 1。このサイトの接頭辞だけのダンプは今どおり戻る。`db:backup`（mysqldump・php）のスナップショットでは、`modx_manager_log` が `CREATE TABLE IF NOT EXISTS` だけになり（`DROP TABLE` と `INSERT` が無い）、戻しても戻した先の管理ログの行が残る（往復の `CHECKSUM TABLE` は同じ）。`$modx->snapshot()` は `a/b`・`bad name` で `false`（修正前は `null`）。S3 と同じ往復は、`db:backup` の 2 つ（ログの表のデータが無くなった分だけ文の数が変わる）のほかは修正前と同じ。php:7.4-cli でも、2 つの断りと PHP の書き出しの往復が通った。
- 2026-11-18（S7。自己レビューのあと）: 接頭辞の重なりを見分ける本体の表の名前に `user_attributes` を入れると、このサイトの `modx_web_user_attributes` を外れた表とみなし、正しいダンプまで断った（往復の確かめで分かった）。ほかの本体の表の名前の終わりと重ならないもの（`install/sql/create_tables.sql` の 35 表で確かめた 4 つ）だけにした。直したあと、断る確かめ・正しいダンプの往復・`db:backup` の往復を流し直し、前と同じ結果になった。
- 2026-11-18（S7）: 下読み（`EvoSqlDump::inspect()`）はダンプを最後まで 1 回読むので、復元ではファイルを 2 回読む（DB への問い合わせは増えない）。表の名前の判定（`EvoSqlDump::tableOf()`）は、復元の中で `system_cache` への文を飛ばす判定と同じものを使う。

## Decision Log

- 2026-11-17 / yamamoto（Claude が起案）: 1 段階を 1 PR にする（`exec-plan-runner` の `/run-plan` で段階ごとに PR を分ける）。動きを変えない段階（S1・S3・S4・S6）と動きを変える段階（S2・S5・S7・S8・S9）を別の PR にし、動きを変えない PR は「修正前と同じ出力」で、動きを変える PR は「計画どおりの違いだけ」で確かめる。段階ごとのブランチは `origin/main` から `git switch -c` で作り、`gh issue develop` で Issue に紐づけない（紐づいたブランチの PR は `Refs` でもマージで Issue を閉じるため）。S1 だけは `gh issue develop` で作った `refactor/1561-backup-reorg` を使い、main に入れる最後の段階（S8）の PR で `Closes #1561` にする。S8 より前に S1 のブランチの PR がマージされて Issue が閉じたときは、Issue を開き直して続ける。
- 2026-11-17 / yamamoto（Claude が起案）: #1560（ダンプの値のエスケープ、スナップショットのログの表の構造、バックアップの失敗の知らせ方、`processors/backup/` の失敗の知らせ方）のマージを待ってから S1 に入る。#1560 は同じファイルを書き換えるため、先に入れると衝突と二重の手直しになる。M0 で #1560 の差分を読み、この計画の Context と各段階の作業から、#1560 で済んだものを外す。
- 2026-11-17 / yamamoto（Claude が起案）: 新しいクラスは、`manager/includes/` の新しい作法（`EvoSiteExporter`・`EvoHtaccess`・`EvoSpecialPages`・`EvoFormFlow`）に合わせ、`Evo` を頭に付けたクラス名と、`<小文字の名前>.class.inc.php` のファイル名にする。ファイルの先頭に `$modx` の有無で `exit` する行を置かない（クラスの定義だけのファイルにし、`global $modx;` を呼び出し側に置かせない）。互いの読み込みは `require_once __DIR__ . '/<ファイル>'` にする。名前は次のとおり。
  - `EvoDbTables`（`manager/includes/db_tables.class.inc.php`）: 接頭辞の付いた表の一覧・状態（行数・大きさ）・名前の正規化・表の有無・空にしてよい表・最適化してよい表
  - `EvoSqlDump`（`manager/includes/sql_dump.class.inc.php`）: ダンプを書く（値のエスケープ・文の並び）と読む（文に分ける）。「値に生の改行を入れない。文は `;` で行を終える」という約束をこのクラスの説明に書き、書く側と読む側が同じ約束を使う
  - `EvoSnapshotStore`（`manager/includes/snapshot_store.class.inc.php`）: 置き場所・保護の `.htaccess`・名前・一覧・世代
  - `EvoBackupManager`（今の `manager/includes/backup_manager.class.inc.php` を残す）: 作成と復元の手順（復元の前の控え、接頭辞の確認、環境ごとの設定の保持、キャッシュの作り直し）。管理画面・CLI・`$modx->snapshot()` はこれを呼ぶ。名前を変えないのは、呼び出し元とほかの計画（`.agent/plans/2026-05-06-evo-system-upgrade-command.md`）が「手順の窓口」としてこの名前を使っているため
- 2026-11-17 / yamamoto（Claude が起案）: PDO の新しい DB API（ロードマップの「PDO移行」、#677。AGENTS.md の #453 の取り決め）は待たず、今の `db()` で進める。PDO 移行のフェーズ 1 は `db()->escape()` などの公開メソッドの約束を変えないので、この計画の書き方はそのまま動く。そのかわり、DB に問い合わせる箇所を `EvoDbTables` と `EvoSqlDump` の 2 つに閉じ込め、PDO 移行のフェーズ 2（プレースホルダ）で直す場所をこの 2 つにする。
- 2026-11-17 / yamamoto（Claude が起案）: 消すクラス・関数・入口に、古い名前の別名を残さない（互換を重ねない方針）。`Mysqldumper`・`mysql_dumper.class.inc.php`（S3）、`EvoBackupManager::truncatableTables()`・`dir()` などの保存先のメソッド（S1・S4）、a=55（S2）、`cli_full_table_name()`（S1）は、移した段階で消す。外の拡張が `Mysqldumper` を読んでいた場合は Fatal error で気付ける（黙って動きが変わらない）。PR の本文の「備考」に消した名前を挙げる。
- 2026-11-17 / yamamoto（Claude が起案）: a=55 を消し、管理ログの画面のリンクを a=54 の `truncate=<接頭辞>manager_log&from=logging` に向ける（S2）。権限は a=54 の確かめ方（`settings` かつ `logs` か `bk_manager`）にそろえる。リンクのある画面は `logs` が要るので、画面から操作する利用者の動きは変わらない。変わるのは、`settings` だけを持ち `logs` も `bk_manager` も持たない利用者が a=55 を直接呼んだときだけで、これは画面に入口の無い操作なので、本人の確認は要らないと判断した。
- 2026-11-17 / yamamoto（Claude が起案）: 表の一覧の LIKE の `_` のエスケープは、S1 でそろえる（`getTableNames()` の書き方に合わせる）。違いが出るのは、接頭辞の `_` を任意の 1 文字とみなしたときに当たる別の表がある DB だけで、そのときに他人の表が一覧に混ざる今の動きは不具合とみなす。S1 で出てよい違いは、これと下の a=53 のログの表の行数（M0 で追加）と `rotate_log()` の自動最適化（S1 で追加）の 3 点とする。
- 2026-11-17 / yamamoto（Claude が起案）: 大きなダンプのメモリは、S6 で復元を「ファイルを 1 行ずつ読み、`;` で終わる行まで溜めて 1 文として渡す」形（PHP のジェネレーター。PHP 7.4 で使える）にして減らす。分け方の規則（`;` の後に空白とタブだけを挟んで改行）は今の `preg_split('@;[ \t]*\n@', ...)` と同じにし、CR の扱い（CRLF・単独の CR を LF にそろえてから分ける）も行ごとに同じにする。アップロードの復元も一時ファイルから流して読む（`file_get_contents()` しない）。書く側は今も 1MB ごとに書き出しているので変えない。`createDump()`（文字列で返す）は S3 で消し、`db:export --driver=php` の標準出力へは `php://output` のストリームに書く。
- 2026-11-17 / yamamoto（Claude が起案）: 別の接頭辞のダンプは、S7 で復元の前に断る。読む側が文を流しながら `CREATE TABLE`・`INSERT INTO`・`DROP TABLE` などの対象の表の名前を集めるのでは遅い（書き換えた後に分かる）ので、復元の前に一度ファイルを流して読み（S6 の読む側で、実行せずに）、対象の表がすべて今の接頭辞で始まるかを確かめる。1 つでも外れる表があれば、控えも取らず、何も書き換えずに「接頭辞が違う（ダンプの接頭辞: `<見つかった接頭辞>`、このサイト: `<接頭辞>`）」と知らせる。接頭辞を読み替えて取り込む機能は作らない（YAGNI。必要になったら別の Issue）。`DELIMITER` を含むダンプ（`db:export` の既定の出力など）も同じ下読みで見つけ、「トリガーやルーチンを含むダンプは戻せない。`mysql` コマンドで戻す」と知らせる（下の 2026-11-18 の本人の決定）。
- 2026-11-17 / yamamoto（Claude が起案）: `$modx->snapshot()`（`manager/includes/traits/document.parser.subparser.trait.php`。公開の API。本体からの呼び出しは無いが、外のスニペットが使う前提で残す）は、S6 で `EvoBackupManager` の作成の手順を呼ぶだけにする。名前の `.sql` の付け方は `snapshot.processor.php` と同じ処理の写しなので、`EvoSnapshotStore::normalizeName()` に 1 つにし、両方から呼ぶ。戻り値は S7 で「成功は作ったファイルの大きさ（int）、失敗は名前の不正も含めてすべて `false`」にそろえる（今は名前が不正だと `null`）。
- 2026-11-17 / yamamoto（Claude が起案）: CLI の `db:backup` は S7 で、管理画面のスナップショットと同じ中身（ログの表は構造だけ、データは入れない）にする。同じ置き場所の同じ世代に数えるもので、作った入口によって中身が違うのは分かりにくいため。`mysqldump` で書くときは、ログの表を `--ignore-table` で外した 1 回目と、ログの表だけを `--no-data --skip-add-drop-table` で書く 2 回目を同じ一時ファイルに続けて書き、2 回目の `CREATE TABLE ` を `CREATE TABLE IF NOT EXISTS ` に書き換える（PHP の書き手と同じ形。#1560）。`--ignore-table-data` は MariaDB と新しい MySQL にしか無いので使わない（使えない環境での分岐を作らず、MySQL 5.7 の mysqldump でも同じ手順で書く）。書き手（PHP か mysqldump か）は `EvoBackupManager` の作成の手順に引数で渡す。
- 2026-11-17 / yamamoto（Claude が起案）: S8 のダウンロードの processor は、空いている a=306 に置く（`manager/processors/backup/download.processor.php`）。a=93 は画面だけにする。a=306 は POST だけを受け、`manager/includes/csrf_token.php` の一覧と `manager/includes/actionlist.inc.php` に足す。
- 2026-11-17 / 本人の決定: 言葉と CLI のコマンド名（S9）。「バックアップ」を控え全体の呼び名にし、手元へ持ち帰るものを「ダウンロード」、サーバーに置くものを「スナップショット」と呼ぶ。画面のタブ「バックアップ」を「ダウンロード」に、ボタン「選択したテーブルをバックアップ・ダウンロード」を「選択したテーブルをダウンロード」に変える。CLI の `db:backup` は `db:snapshot` に改名し、`db:backup` の別名は残さない。改名は cron などで `db:backup` を呼んでいるサイトの控えを止めるので、S9 は main ではなく次の版（1.4、`develop/1.4`）に入れ、リリースノートに書く（`db:backup` を呼ぶと「unknown command」で終了コードが 0 以外になり、cron のメールなどで気付ける）。退けた案: 画面の言葉だけ変えて `db:backup` の名前を残す（CLI だけ言葉がずれたまま残る）、名前を変えず文書に定義だけ書く。
- 2026-11-17 / 本人の決定: `snapshot_path`（S5）。グローバル設定の画面に出し、保存するときにサイトのフォルダの下か確かめて、外なら保存しない。実行時に値が使えない（サイトの外・作れない・書き込めない）ときは、`temp/backup/` に切り替えず、作成も復元も止めて「保存先 `<値>` が使えない」と知らせ、`evo()->logEvent()` にも残す。設定のキーは `snapshot_path` のまま。退けた案: 設定をやめて `temp/backup/` に固定する、画面に出さず実行時の不正だけ知らせる。
- 2026-11-17 / yamamoto（Claude が起案）: `snapshot_path` の画面での置き方は、`assets/docs/global-settings.md` の作法に従って次のとおりにする。
  - 置き場所: 「ファイル」タブ（`settingsTabFiles`）の最後に、小見出し「スナップショット」（言語キー `settings_section_snapshot`。日本語と英語の言語ファイルの両方に足す）を足し、その表に 1 行置く。ファイルの置き場所の設定（`rb_base_dir`・`export_dir`）が並ぶタブで、今ある小見出し（ファイルピッカー・ファイルマネージャー・アップロード・HTMLエクスポート）のどれにも合わないため、小見出しを足す。システム全体の設定なので `scope` は既定（`['system']`）のままにし、ユーザー編集画面には出さない。
  - 行: `export_dir` にならい、`'type' => 'path'`、`'defaultPath' => EvoSettingsForm::BASE_PATH_TOKEN . 'temp/backup/'`、見出しと説明は `snapshot_path_title`・`snapshot_path_message`（両方の言語ファイルに足す。説明には「サイトのフォルダの下だけ。外の場所は保存できない」と「名前を変えても、前の場所の控えは動かさない」を書く）。
  - 既定値: `manager/includes/default.config.php` に `'snapshot_path' => '[(base_path)]temp/backup/'` を足す（既定値の正本はここ。`install/sql/` とアップデーターでは行を足さない）。値は `export_dir` と同じく `[(base_path)]` の書き方で保存する（`save_settings.processor.php` の `formv()` の対象に `snapshot_path` を足し、空で送られたら既定値に戻す）。DB に絶対パスで入っている既存の値も、そのまま読める。
  - 保存のときの確かめ: `save_settings.processor.php` の `warnings()` に、`EvoSnapshotStore::checkPath($value)`（サイトのフォルダの下で、サイトのルートそのものではないか。無ければ作れるか）を足す。だめなら言語ファイルの文言で知らせ、今の作法どおり入力を保ったまま a=17 に戻す。`setPermission()` が `temp/backup` を決め打ちで作っている処理は、`EvoSnapshotStore::ensureDir()` に置き換える（保存先の判定を store の外に残さない）。
  - 文書: S5 の PR で、`assets/docs/global-settings.md` の「設定画面のタブ構成」の表のファイルのタブの小見出しに「スナップショット」を足す。
- 2026-11-17 / yamamoto（Claude が起案）: 古い置き場所 `assets/backup`（S5）は分岐を消す。インストーラー・アップデーターの `install/actions/summary.php` が `temp/backup/` を作るので、アップデーターを通ったサイトではこの分岐はもう通らない。ただし `assets/backup` に残った控えがバインドで読まれないよう、`EvoSnapshotStore::denyDirs()` の一覧には残す。
- 2026-11-17 / 本人の決定: 管理画面では `mysqldump` を使わない。管理画面のダウンロード・スナップショット・復元の前の控えは、いつも PHP の書き手（S3 からは `EvoSqlDump`）で書く。CLI（`db:backup`・`db:export`・`db:import` の復元の前の控え）は今どおり既定で `mysqldump`、`--driver=php` で PHP の書き手。書き出しは 2 通りのまま残るので、復元（管理画面のアップロード・スナップショットの一覧・`db:import`）は両方の出力を読めることを、S3・S6・S7 の往復の確かめで両方の出力について確かめる。
- 2026-11-17 / yamamoto（Claude が起案。M0 で追加）: `EvoDbTables::status()` は、空にしてよい表の行数を `COUNT(*)` の正確な数に差し替えた行を返し、a=93 と a=53 の両方がこれを使う。a=53 は今は `SHOW TABLE STATUS` の `Rows`（InnoDB では見積もり）をそのまま出しているので、ログの表（`manager_log`・`event_log`）の行数だけ、見積もりから正確な数に変わりうる。同じ表の行数が画面によって違う今の動きを不具合とみなし、S1 で出てよい違いに加える（上の LIKE のエスケープ、下の `rotate_log()` と合わせて 3 点）。表の有無（`EvoDbTables::exists()`。接頭辞の付いた完全な名前を受ける。下の S1 の項）も `names()` と同じく LIKE の `_`・`%` をエスケープして確かめる（`EvoBackupManager::tableExists()` と復元の中の `system_cache` の確かめが置き換わる。違いが出るのは接頭辞の `_` を任意の 1 文字とみなしたときに当たる別の表がある DB だけ）。
- 2026-11-17 / yamamoto（Claude が起案。S1 で追加）: `rotate_log()`（`manager/includes/traits/document.parser.subparser.trait.php`。管理のログ `log.class.inc.php` から呼ばれる）の `automatic_optimize` は、`SHOW TABLE STATUS FROM <DB>` で同じ DB のすべての表（ほかのサイトの表も）を最適化していた。`EvoDbTables::names()` の表だけを最適化するようにし、S1 で出てよい違いに加える（3 点目）。ほかのサイトの表にロックをかけていた今の動きは不具合とみなす。完了条件の「`SHOW TABLE STATUS` は `db_tables.class.inc.php` だけ」のためにも要る。DocumentParser の `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` には影響しない。
- 2026-11-17 / yamamoto（Claude が起案。S1 で追加）: `EvoDbTables::exists()` は接頭辞の付いた完全な名前だけを受ける（`qualify()` を通さない）。「接頭辞で始まれば付いているとみなす」推測は CLI に入力された名前（`qualify()`）にだけ使う。下線の無い接頭辞（例 `sys`）で `system_settings` を完全な名前と取り違えないため。表の有無の 3 通り（上の Surprises）を 1 つにするのは、この計画では行わない（PDO 移行か別の Issue）。
- 2026-11-17 / yamamoto（Claude が起案。S2 で追加。#1566 への Codex の指摘から）: 表の名前に接頭辞が付いているかを、名前の頭から推し量らない。S1 で入れた `qualify()`・`qualifiedWithDb()` を消し、次の 2 つに分ける。
  - `EvoDbTables::name($short)`: 接頭辞を除いた名前（本体が決め打ちで持つ名前）に、必ず接頭辞を付ける。`truncatable()`・`EvoBackupManager`・管理ログの画面のリンクが使う。
  - `EvoDbTables::resolve($input)`: 利用者が入力した名前を、このサイトに在る表（`names()`）で決める。入力のとおりの名前の表が在ればそれを、無ければ接頭辞を付けた名前の表が在ればそれを返し、どちらも無ければ `''`。CLI の `db:count`・`db:describe`・`db:export --tables=` が使い、`''` なら「no such table with the prefix <接頭辞>: <名前>」で終える（修正前は、無い表を渡すと SQL や mysqldump のエラーで終わるか、PHP の書き手では黙って飛ばしていた）。両方の名前の表が在る（例: 接頭辞 `manager_` で、`manager_log` という名前のこのサイトの表と `manager_manager_log` が両方在る）ときは、入力のとおりの名前を選ぶ。もう一方は接頭辞の付いた名前で指せる。
  - S1 の段階で直すべきものだったが、S1 はマージ済みなので S2 の PR で直す。
- 2026-11-17 / yamamoto（Claude が起案。S3 で追加）: 「コンテンツのみ」のスナップショットに入れる表と、スナップショットで構造だけを入れるログの表の一覧は、スナップショットの手順の決まりなので `EvoBackupManager`（`contentTables()`・`logTables()`）に置き、`EvoSqlDump::write()` には表の一覧と `structureOnly` で渡す。`write()` の options に `contentsOnly` は持たせない（書く側はどの表をどう書くかだけを知る）。復元は、先頭のコメントを除いた文を実行する（修正前はコメントの付いたまま実行していた。DB にとって同じ文）。
- 2026-11-17 / yamamoto（Claude が起案。S4 で追加）: 既定の保存先 `temp/backup/` と以前の置き場所 `assets/backup/` は `EvoSnapshotStore::DEFAULT_DIR`・`LEGACY_DIR` に持ち、DB の設定を読めないインストーラーとグローバル設定の保存は `EvoSnapshotStore::defaultDir()` と `protect()` を使う（`ensureDir()` は設定を読むので使わない）。グローバル設定の保存は、修正前と同じく `temp/backup/` を作るだけにする（`ensureDir()` に置き換えるのは、保存先が設定で決まるようになる S5）。名前の `.sql` の付け方は `EvoSnapshotStore::normalizeName()` にまとめ、`snapshot.processor.php` と `$modx->snapshot()` が使う。
- 2026-11-18 / yamamoto（Claude が起案。S5 で追加）: `snapshot_path` の相対パス（S4 の Surprises）は、サイトのフォルダからの場所として解く（CLI と管理画面で同じ場所になる）。保存のときは `[(base_path)]` を付けた形で保存する。`EvoSnapshotStore::dir()` は値をそのまま解いて返し、使えるかは `problem()` が決める（サイトの外・サイトのルート・`..` で外へ出るものは `OUTSIDE`、無くて作れない・書き込めないものは `UNWRITABLE`）。作成・復元・`db:backup`・`db:import`・`$modx->snapshot()` は `ensureDir()` を通して断り、断ったことは `ensureDir()` が `evo()->logEvent()` に残す（呼び出し元ごとに書かない）。a=93 の画面は `OUTSIDE`・`SHARED` のときに理由を常に出し、一覧も出さない（`UNWRITABLE` は作成・復元を試みたときに今の文言で出す）。グローバル設定の保存は、`warnings()` で `problem()` を確かめ（変えた値はすべて確かめる。今の値のままなら `OUTSIDE`・`SHARED` のときだけ止め、書き込めないだけなら関係のない設定の保存は止めない。自己レビューの指摘で直した）、`setPermission()` は `temp/backup/` の決め打ちをやめて、使える値のときだけ `ensureDir()` を呼ぶ（新しい保存先を作り、保護の `.htaccess` を置く。ディレクトリの権限は `mkd()` の 0777 から `ensureDir()` の 0755 になる）。
- 2026-11-18 / yamamoto（Claude が起案。S5 で追加）: ファイルブラウザー（`PathGuard::protectedPathsFor()`）の守る場所は、`EvoSnapshotStore::guardedDirs()`（今の保存先・既定の保存先・以前の置き場所）から得る。バインドの `denyDirs()` も同じ一覧から作る。
- 2026-11-18 / yamamoto（Claude が起案。S5 で追加）: `assets/backup/` に控えが残っているサイトへの知らせは足さない。アップデーターを通ったサイトは `temp/backup/` ができていて、もともと `assets/backup/` は保存先になっていない（一覧にも出ていなかった）。分岐が効いていたのは `temp/backup/` を消したサイトだけで、そのサイトでも次の作成で `temp/backup/` ができる。`assets/backup/` は読ませない場所として残すので、控えが外から読まれることはない。PR の「変更内容」に書く。
- 2026-11-18 / yamamoto（Claude が起案。S5 の自己レビューから追加）: 本人の決定の「サイトのフォルダの下」のうち、保護の `.htaccess`（`Require all denied`）を置くと表のサイトや管理画面が見えなくなる場所は、保存先にさせない（`SHARED`）。対象は、スナップショット以外のファイルがあって、読ませない `.htaccess`（`<Files>`・`<FilesMatch>`・`<If>`・`<Location>`・`<Directory>` などのブロックの外に `Require all denied` か `Deny from all` を含むもの。古い書き方も含む）も無いフォルダ（`manager/`・`assets/` など。置いてよいのは `.sql`・`.htaccess`・`index.html` と書き出しの途中のファイルだけ）と、`manager/`・`assets/templates/`・`rb_base_dir` の中とその親。拒否の無い `.htaccess` だけがあるフォルダも、守れないので断る（PR #1576 への Codex の指摘）。保存のときにも、DB に直接入れたときの実行時にも断る（画面の欄から 1 回打ち間違えると管理画面が 403 になり、FTP でしか戻せなくなるため）。本人の決定（外なら保存しない）を狭めるのではなく、「サイトのフォルダの下でも、壊すと戻せない場所」を足したものと判断した。値の整え方（前後の空白・空・相対パス・`[(base_path)]`・末尾の `/`）は `EvoSnapshotStore::normalizeValue()` 1 つにし、保存と読むときが同じ規則を使う。絶対パスかは OS ごとに決め（Linux では `C:/…` は相対）、`basename()` は使わない（PHP 7.4 の C ロケールで日本語の名前を落とすため）。守る場所の一覧（`guardedDirs()`）には、`OUTSIDE`・`SHARED` の値を入れない（サイトの親や `content/` ごと守ってしまうため）。Plan of Work の S5 の「`dir()` は使えないときに例外か `false`」は、`dir()` は値を解いて返し、使えるかは `problem()` が決める形にした。
- 2026-11-18 / 本人の決定: エクスポートの出力先との重なりは S5 では扱わない。
- 2026-11-18 / yamamoto（Claude が起案。S6 で追加）: `EvoBackupManager::createSnapshot($name, array $options)` の書き手は、計画の `driver` の名前ではなく `writer`（callable($path, array $tables): bool）で受ける。`mysqldump` を呼ぶ処理は CLI だけのもの（`cli-helpers.php`）なので、`EvoBackupManager` から CLI の関数を呼ばないため。`db:backup` は、今と同じく `cli_export_database_file()` を書き手として渡す（`--driver=php` でもログの表のデータを入れる今の中身のまま。管理画面と同じ中身にするのは S7）。世代の数は `keep` で受け、消した名前は `pruned()` で得る（`db:backup` の「Removed old backup」の行のため）。復元は `restore($sql)` を消して `restoreFile($path, $preRestore)` にした（別名は残さない）。`assets/docs/core-issues.md` の節は、トランザクションの整合性が残るので、解決済みの見出しにはせず、メモリの項だけを解消にした。
- 2026-11-18 / 本人の決定: PHP の書き出しは、複数行の `INSERT`（バルクインサート）にできるならそれがよい。S6 の後に S6b として足す（S6 の PR のマージの後、本人の指示で始める）。
- 2026-11-18 / 本人の判断（S6b の形）: 複数行の `INSERT` の中で、1 行のデータごとに改行する。理由: DB への問い合わせは 1 つの文で 1 回のまま（速さ）、ダンプの差分は 1 行 1 データで取れる（diff の取りやすさ）。読む側は「`;` で終わる行で文を区切る」ままで、途中の行は `),` で終わるので、変えずに 1 つの文として読める。
- 2026-11-18 / yamamoto（Claude が起案。S6b の区切り）: 1 つの `INSERT` の大きさは、`mysqldump` の既定の `net_buffer_length`（1,046,528 バイト。1MB − 2KB で、`max_allowed_packet` が 1MB の DB へ戻しても入る）に合わせ、それを超えたら次の文にする（1 行だけで超える行は、その 1 行で 1 つの文）。書く DB の `max_allowed_packet` が小さいときに超えないよう、上限は「`min(1,046,528, max_allowed_packet の値 − 余白 1KB)`」にする（自己レビューで 1MB ちょうどでは 1MB の DB へ戻せないと分かり直した）（書くときに `SELECT @@max_allowed_packet` を 1 回だけ問い合わせる）。1 行がそれだけで `max_allowed_packet` を超える行は、修正前もその行を戻せなかったので、扱いは変えない。
- 2026-11-18 / 本人の決定: S7 で、接頭辞が今のサイトと違うダンプは、何も書き換えずに断り、ダンプの接頭辞とこのサイトの接頭辞を示して理由を知らせる（置き換えて取り込む機能は作らない）。
- 2026-11-18 / 本人の決定: S7 で、`DELIMITER`（トリガー・ストアドプロシージャー）を含むダンプは、復元で何も書き換えずに断り、理由（`mysql` コマンドで戻す）を知らせる。`db:export` の既定（`--routines --triggers`）は変えない。
- 2026-11-18 / yamamoto（Claude が起案。S7 の自己レビューから）: 接頭辞の判定は「このサイトの接頭辞で始まるか」に加えて、本体の表の名前（`system_settings`・`site_content`・`manager_users`・`site_templates`。ほかの本体の表の名前の終わりと重ならないもの。`user_attributes` は `web_user_attributes` と重なるので入れない）で終わる表が「このサイトの接頭辞 + その名前」かを見る（`modx_` のサイトへの `modx_old_` のダンプと、接頭辞が空のサイトへの `modx_` のダンプを断るため）。接頭辞は大文字小文字を問わず比べ、表の名前は backtick で囲んだものは丸ごと取る（接頭辞に `-` などを含むサイトが自分のダンプを戻せるように）。同じ DB に接頭辞が重なる 2 つのサイトがあると、書き出しの表の一覧（`LIKE` の接頭辞）と復元の判定（本体の表の名前）がずれて、自分のスナップショットも断る（修正前は相手のサイトの表を上書きしていたので、断るほうが安全。判定を `EvoDbTables` にまとめるのは後の課題）。大文字小文字だけ違う接頭辞（`lower_case_table_names=0` の DB で `MODX_` のダンプ）は見分けない。どちらも `assets/docs/backup.md` に書いた。`db:import` の復元の前の控え（`mysqldump`。ログの表のデータを含む）は、管理画面の控え（ログの表は構造だけ）と中身が違うまま残す（S7 は `db:backup` だけをそろえる範囲。控えから戻すと管理ログが取り込み前まで戻る程度で、軽い）。

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

今の受け持ち（main 77a59e271 時点。#1560 のマージ後）:
- `manager/includes/mysql_dumper.class.inc.php`: `Mysqldumper`。PHP でダンプを書く。先頭の `if (!isset($modx) || !is_object($modx)) exit;` のため、メソッドの中から読み込む `EvoBackupManager::createSnapshot()`・`db-backup.php`・`db-import.php`・`cli_export_database()` が `global $modx;` を置いている。`getTableNames()` が表の一覧。`dumpToFile()` が `_dbtables` を `array_flip()` で書き換える、全部の表に `SHOW CREATE TABLE` を打つ、`object2Array()` が使われていない、ヘッダーに `site_name` を入れる。値は #1560 で `db()->escape()` で書くようになった（改行・CR は `\n`・`\r` になり、1 件の `INSERT` が 1 行に収まる。CR は LF に変えずに残す）。スナップショット（`mode = 'snapshot'`）のログの表は、`DROP TABLE` もデータも書かず、`CREATE TABLE IF NOT EXISTS` だけを書く（#1560）。
- `manager/includes/backup_manager.class.inc.php`: `EvoBackupManager`。保存先（`dir()`・`ensureDir()`・`HTACCESS`）・名前（`autoFilename()`・`isValidFilename()`・`path()`）・一覧（`files()`）・世代（`prune()`）・作成（`createSnapshot()`）・復元（`restore($sql, $preRestore)`。文字列で受けて `preg_split()` で分ける）・環境ごとの設定の保持・空にしてよい表（`truncatableTables()`）。
- `manager/actions/tool/bkmanager.static.php`（a=93）: 画面と、ダウンロード（`mode=backup` の POST を非表示の iframe に送る）の処理。表の一覧を `SHOW TABLE STATUS` で自分で問い合わせる。ダウンロードの失敗は、iframe に `#evoBackupError` を返し、親の画面が読み込みで拾って `EvoDialog.alert()` で知らせる（#1560）。表を選ばずに押したときは送る前に `EvoDialog` で知らせ、アップロードの復元は `upload_max_filesize`・`post_max_size` を超えるファイルを送る前に断る（#1560）。
- `manager/processors/backup/restore.processor.php`（a=305）・`snapshot.processor.php`（a=307）: 復元とスナップショットの作成。失敗はどちらも `sessionv('*result_msg', <理由>)` に入れて a=93 に戻し、画面が言語ファイルの文言で出す（`restore_no_file`・`restore_too_large`・`restore_upload_failed`・`restore_invalid_file`・`snapshot_invalid_file_name`・`snapshot_mkdir_failed`・`snapshot_failed`。#1560）。復元はまだファイル全体を `file_get_contents()` で読む。
- `manager/includes/cli/cli-helpers.php`: `cli_export_database()`（`mysqldump` を呼ぶか `Mysqldumper::createDump()`）・`cli_export_database_file()`（一時ファイルに書いて rename）・`cli_full_table_name()`（接頭辞を付けて `` `db`.`table` `` にする。`db:count`・`db:describe` が使う）。
- `manager/includes/cli/commands/db-backup.php`・`db-export.php`・`db-import.php`: CLI。`db-backup.php` は世代の数え方を自分で書き、`db-export.php` は接頭辞の付け方を自分で書く。コマンド名 `a:b` は `evo.php` で `commands/a-b.php` に読み替える。
- `manager/includes/traits/document.parser.subparser.trait.php`: `snapshot()`（`$modx->snapshot()`）と `getBindDenyDirs()`（バインドで読ませない置き場所。`EvoBackupManager::dir()` に加え `temp/backup`・`assets/backup`・`snapshot_path` を自分で並べる）。
- `manager/actions/report/sysinfo.static.php`（a=53）: 4 か所目の表の一覧と、最適化のリンク（a=54）。
- `manager/processors/db/optimize_table.processor.php`（a=54）: 最適化と、ログの表を空にする。`manager/processors/db/empty_table.processor.php`（a=55）: `manager_log` を空にする別の入口。
- `install/actions/summary.php`: `temp/backup/.htaccess` を、保存先の判定を通さずに決め打ちで置く。
- つなぎ: `manager/index.php`（a=93・305・307・54・55 のルーティング）、`manager/includes/csrf_token.php`（POST の要る操作の一覧）、`manager/includes/actionlist.inc.php`、`manager/frames/menu.php`・`manager/actions/main/welcome.static.php`（メニューとダッシュボード）、`manager/actions/permission/mutate_role.dynamic.php`・`manager/processors/permission/save_role.processor.php`（権限 `bk_manager`）、`manager/actions/report/logging.static.php` と言語ファイルの `mgrlog_sortinst`（a=55 へのリンク）、言語ファイルの `bkmgr_`・`backup`・`bk_manager`・`database_table_*`。
- 文書: `manager/includes/cli/README.md`（`db:*`）、`assets/docs/core-issues.md` の「PHP のダンプの書き出し（EvoSqlDump）の設計上の制約」（S3 で見出しを変えた）、`assets/docs/site-url.md`（復元が `fixed_site_url` を残す）、`.agent/plans/2026-05-06-evo-system-upgrade-command.md`（`EvoBackupManager` と `cli_export_database_file()` を使う計画）。

インストーラー（`install/`）からは `EvoBackupManager::HTACCESS` だけを使い、ダンプ・復元は使わない（`grep -rn "Mysqldumper\|EvoBackupManager\|cli_export_database" install/` で確かめた）。

DocumentParser への影響: `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` のどれにも影響しない。触るのは、要求ごとの処理の外で呼ばれる `snapshot()` と、バインドの読み込みで使う `getBindDenyDirs()` だけ。キャッシュへの影響は、復元の後の `evo()->clearCache()`（今と同じ）だけ。

## Plan of Work

下の層（表の一覧 → ダンプの形 → 保存先）から先に 1 か所に寄せ、その上に手順を置き、最後に入口と言葉を変える。下の層を先にするのは、上の層（手順・入口）を寄せるときに、移した先がもう 1 つに決まっているようにするため。各段階は「移すだけで動きを変えない PR」と「動きを変える PR」を分け、動きを変えない PR は修正前と同じ出力になることで確かめる。

S1（表の一覧と状態。動きを変えない）: `EvoDbTables` を作り、`names()`（接頭辞の表の名前。LIKE の `_`・`%` をエスケープ）、`status()`（`SHOW TABLE STATUS` の行。空にしてよい表は `COUNT(*)` の正確な行数に差し替える）、`qualify($name)`（接頭辞が無ければ付け、名前の文字を確かめる。`db:count`・`db:describe` 向けに `` `db`.`table` `` を返す `qualifiedWithDb()` も持つ。S2 で `name()`・`resolve()`・`resolveWithDb()` に分けた。Decision Log を参照）、`exists($table)`（完全な名前）、`truncatable()`、`isOwn($name)`（最適化してよい表か）を置く。`Mysqldumper::getTableNames()`、`bkmanager.static.php` と `sysinfo.static.php` の問い合わせ、`EvoBackupManager::truncatableTables()`・`tableExists()`、`optimize_table.processor.php` の判定、`cli_full_table_name()`、`db-export.php` の接頭辞の付け方、`db-backup.php`・`db-import.php` の表の一覧、`rotate_log()` の自動最適化の表の一覧を、これに置き換える。`bkmanager.static.php` の使われていない `_dbtables` のチェックも消す（いつも空なので、出力は変わらない）。

S2（表を空にする入口。動きを変える）: a=55 を消し、`logging.static.php` のリンクと言語ファイルの `mgrlog_sortinst` の `href` を a=54 へ向け、`optimize_table.processor.php` の戻り先に `logging`（a=13）を足す。`manager/index.php`・`csrf_token.php`・`actionlist.inc.php` から 55 を消し、`empty_table.processor.php` を消す。

S3（ダンプの形。動きを変えない）: `EvoSqlDump` を作る。書く側は `Mysqldumper::dumpToFile()` を移し、`write($stream, array $tables, array $options)`（options: `drop`・`structureOnly`（構造だけ書く表の一覧。スナップショットのログの表）。S3 で `contentsOnly` は持たせないことにした。Decision Log の S3 の項）と、一時ファイルに書いてから rename する `writeFile($path, ...)` にする。表の一覧は `EvoDbTables` から受け、`SHOW CREATE TABLE` は書き出す表にだけ打つ。`system_settings` の値の置き換え（`[(base_path)]` など）と、値の書き方（`db()->escape()`。#1560）は書く側に残す。`structureOnly` の表は、#1560 の形（`DROP TABLE` を書かず `CREATE TABLE IF NOT EXISTS` だけ）で書く。ヘッダーの `site_name` の行はやめる（コメントだけの違い）。読む側は `statements($stream)`（ジェネレーター）で、今の `EvoBackupManager::restore()` の分け方と先頭のコメントの除き方（`stripLeadingComments()`）を移す。S3 では `restore()` は今のとおり文字列を受け、`php://memory` に書いてから `statements()` に渡す（流して読むのは S6）。`Mysqldumper` と `mysql_dumper.class.inc.php` を消し、呼び出し元の `global $modx;` も消す。`cli_export_database()` の `php` の書き手は `EvoSqlDump` を使い、標準出力へは `php://output` に書く。

S4（保存先と世代。動きを変えない）: `EvoSnapshotStore` を作り、`dir()`・`ensureDir()`・`HTACCESS`・`autoFilename()`・`isValidFilename()`・`normalizeName()`（`.sql` の付け方。`snapshot.processor.php` と `snapshot()` の写しをまとめる）・`path()`・`files()`・`prune()`・`counts()`（自動の名前とそれ以外の件数。`db-backup.php` の数え方を移す）・`denyDirs()`（バインドで読ませない置き場所の一覧。`getBindDenyDirs()` の並べ方を移す）・`static protect($dir)`（保護の `.htaccess` を置く。DB の設定を読めないインストーラーから呼ぶ）を置く。`EvoBackupManager` は保存先のメソッドを消し、`store()` で `EvoSnapshotStore` を返す。`getBindDenyDirs()` は `denyDirs()` を呼び、`summary.php` は `EvoSnapshotStore::protect()` を呼ぶ。

S5（保存先の黙った切り替えをやめる。動きを変える）: Decision Log の本人の決定と画面での置き方のとおりに、`snapshot_path` をグローバル設定の「ファイル」タブに出し、保存のときに確かめ、実行時に使えなければ止めて知らせる（`EvoSnapshotStore::dir()` は使えないときに例外か `false` を返し、`EvoBackupManager` の作成・復元、`db:backup`・`db:import`、管理画面のスナップショットのタブがそれを受けて知らせる）。`assets/backup` への分岐を消す（`denyDirs()` には残す）。編集するのは `manager/includes/snapshot_store.class.inc.php`・`manager/includes/backup_manager.class.inc.php`・`manager/includes/default.config.php`・`manager/includes/settings_form.definition.inc.php`・`manager/processors/save_settings.processor.php`・`manager/includes/lang/japanese-utf8.inc.php`・`manager/includes/lang/english.inc.php`・入口（`manager/actions/tool/bkmanager.static.php`・`manager/processors/backup/`・`manager/includes/cli/commands/db-backup.php`・`db-import.php`）・`assets/docs/global-settings.md`・`manager/includes/cli/README.md`。

S6（作成と復元の手順。動きを変えない）: `EvoBackupManager` を手順だけにする。`createSnapshot($name, $options)`（書き手を options の `driver` で受ける。管理画面は `php`、CLI は `mysqldump` が既定）、`restoreFile($path, $preRestore)`（`EvoSqlDump::statements()` でファイルから流して読む）、復元の前の控え、環境ごとの設定の保持、キャッシュの作り直し。`restore.processor.php` はアップロードの一時ファイルとスナップショットのパスを `restoreFile()` に渡す。`db-backup.php`・`db-import.php` は `createSnapshot()`・`restoreFile()` を呼ぶ。`$modx->snapshot()` は `normalizeName()` と `createSnapshot()` を呼ぶだけにする。`assets/docs/backup.md` を作り、5 つの受け持ちとダンプの約束を書き、AGENTS.md のドキュメントマップに足す。`assets/docs/core-issues.md` の「PHP のダンプの書き出し（EvoSqlDump）の設計上の制約」（S3 で見出しを変えた）を解決済みにする。

S6b（PHP の書き出しを複数行の INSERT にする。動きを変える）: `EvoSqlDump::write()` の書く側で、表ごとに複数の行を 1 つの `INSERT` にまとめ、1 行のデータごとに改行する（``INSERT INTO `t` VALUES`` の行のあとに、途中の行は `(...),`、最後の行だけ `(...);`）。値に生の改行を入れない約束はそのまま（値の中の改行は `\n` にエスケープされる）。区切りは Decision Log の S6b の項のとおり。読む側（`statements()`）は変えない。`assets/docs/backup.md` のダンプの約束の「1 件の `INSERT` が 1 行に収まる」を、「1 つの `INSERT` が複数の行のデータを持ち、データ 1 行ごとに改行する。文の終わりは最後の行の `;`」に置き換える。PR に `仕様変更` のラベルを付ける（ダンプの形が変わる）。

S7（復元の前の確かめと作成の結果。動きを変える）: 復元の前の下読み（接頭辞と `DELIMITER`）、`snapshot()` の戻り値、`db:backup` のログの表の扱いを Decision Log のとおりに変える。

S8（入口を薄くする。動きを変える）: ダウンロードを a=306 の processor に移す。`processors/backup/` の 3 つと CLI のコマンドは、`EvoBackupManager` を呼んで結果を知らせるだけにする。画面の残り（`#fff` を決め打ちした `<style>`、`selectAll()` の古い JS、`postv('mode')`、ダウンロードのファイル名を引用符で囲む）を片付ける。失敗の知らせ方は #1560 で決まった形（ダウンロードは親の画面の `EvoDialog`、processor は `result_msg` を a=93 で出す）をそのまま使い、a=306 に移したダウンロードでも同じ形にする（a=306 も非表示の iframe に `#evoBackupError` を返す）。

S9（言葉。動きを変える。1.4 向け）: S8 の後に、マイルストーン「1.4」を付けた別の Issue を起こし（#1561 は S8 で閉じる）、`exec-plan-runner` の「次の版のまとめ用ブランチ」のとおり `develop/1.4` に向ける。Decision Log の本人の決定のとおりに、言語ファイル（`backup`・`database_table_clickbackup`・`bkmgr_restore_msg` の「「バックアップ」で取得した」など、画面の「バックアップ」が手元へのダウンロードを指す文言）、`manager/includes/cli/commands/db-backup.php` を `db-snapshot.php` に改名（`db-backup.php` は残さない）、`manager/includes/cli/README.md`・`assets/docs/backup.md`・`.agent/plans/2026-05-06-evo-system-upgrade-command.md` の `db:backup` を変える。リリースノートに載せるため、PR の本文に「`db:backup` は `db:snapshot` に改名。cron などの呼び出しを書き換える」と書く。

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

S1 の確かめ: 修正前後で、`db:tables`・a=93・a=53 の表の名前と行数の列（a=53 のログの表の行数は、Decision Log のとおり見積もりから正確な数に変わってよい）、`php evo.php db:count manager_log`・`db:describe site_content`・`db:export --tables=site_content --driver=php --output=/tmp/a.sql`（`-- Generation Time` の行を除いて）が同じ。違ってよいのは `modxXfake` が a=93・a=53 に出なくなることだけ。a=54 で `manager_log` を空にする・`site_content` を最適化する・`other_table` を渡すと権限エラー、が修正前と同じ。自動最適化（`automatic_optimize=1` で `rotate_log()`）の `OPTIMIZE` が、このサイトの表だけになる。期待: `diff` が上の 3 点（`modxXfake`・a=53 のログの表の行数・自動最適化の対象）と時刻（`recent_update`）だけ。

S2 の確かめ: 管理ログの画面（a=13）の「empty」のリンクで `manager_log` が空になり、管理ログの画面に戻る。`index.php?a=55` は「まだ実装されていない機能」の画面（`index.php` の `default`）になる（修正前は空にした）。`settings` だけを持つロールの利用者が a=54 の `truncate` を POST すると権限エラー。`grep -rn "a=55\|empty_table" manager/` が 0 件。

S3 の確かめ（往復）: `fixture` を入れた DB で、管理画面のダウンロード（`DROP 文を生成` あり）・スナップショット・`db:export --driver=php`・`db:backup --driver=php` のそれぞれの出力を、修正前と修正後で `-- Generation Time`・`--  Server version` などのヘッダーのコメントを除いて `diff` し、違いがヘッダーの `site_name` の行だけであることを確かめる。それぞれを復元（管理画面のアップロードと `EVO_CLI_IMPORT=1 php evo.php db:import <ファイル>`）して `checksum` が元と同じ。mysqldump の出力も復元でき、`checksum` が同じ。`grep -rn "Mysqldumper\|mysql_dumper" manager/ install/` が 0 件。

S4 の確かめ: 修正前後で、スナップショットの一覧・自動の名前・`prune()` で消える世代（自動の名前を 12 件、名前付きを 2 件、`pre-restore-` を 5 件置いて `db:backup --driver=php --max=10` を流す）・`db:backup` の最後の行（`auto-named N/M, other K`）・`getBindDenyDirs()` の一覧（`docker exec <名前>-app php -r` で bootstrap して出す）が同じ。新規インストールで `temp/backup/.htaccess` ができる。

S5 の確かめ: グローバル設定（a=17）の「ファイル」タブの最後に小見出し「スナップショット」と「既定 `[(base_path)]temp/backup/`」の欄が出る（シェルの遷移と F5 の両方で開く。ユーザー編集画面 a=12 とプロフィール a=74 には出ない）。何も変えずに保存すると DB の `snapshot_path` が `[(base_path)]temp/backup/` になり、スナップショットの置き場所は修正前と同じ。`[(base_path)]temp/backup2/` にして保存すると、そこにスナップショットが作られ、一覧もそこから出る。`/tmp/` を保存しようとすると、知らせが出て入力を保ったまま a=17 に戻り、DB の値は変わらない。DB に直接 `/tmp/` を入れると、管理画面のスナップショットの作成・復元と `db:backup`・`db:import` が「保存先が使えない」と知らせて何もせず（修正前は `temp/backup/` に作った）、システムログに残る（`php evo.php log:search "snapshot" --limit=5`）。`temp/backup` を消して `assets/backup` だけを置くと、`temp/backup/` が作られてそこに保存される。`assets/backup` は `denyDirs()` に出る。

S6 の確かめ: S3 と同じ往復を、管理画面・CLI の両方で行い、修正前と `checksum`・復元の結果の表示（実行した文の数・飛ばした数）が同じ。大きなダンプ: 本文 1MB の文書を 200 件入れた DB のダンプ（約 200MB）を `php -d memory_limit=128M evo.php db:import` で戻すと、修正前は `Allowed memory size` で止まり、修正後は通る（これは計画どおりの違い）。`$modx->snapshot('abc')` が `abc.sql` を作り、大きさを返す（修正前と同じ）。

S6b の確かめ: 修正前（S6 の後の main）と修正後で、管理画面（ダウンロード・スナップショット・コンテンツのみ）と CLI の `--driver=php`（`db:export`・`db:backup`）のダンプを、`db:import` と管理画面のアップロード・スナップショットの一覧で戻し、`CHECKSUM TABLE` が元と同じ（特別な文字を含む値も）。修正後のダンプの `INSERT` の文の数が大きく減る（表ごとの行の数から、1MB ごとの数へ）。修正前の形（1 行ずつの `INSERT`）のダンプも、修正後に戻せる。値が `;` で終わる行、`)`・`,`・`'),(` を含む値、空の表、1 行だけの表、上限ちょうどで文が分かれる表を往復に入れ、読む側の文の数（表ごとの文の数の合計）と `CHECKSUM TABLE` を確かめる。大きな行（1MB を超える 1 行）と、`max_allowed_packet` を小さくした DB（例 `SET GLOBAL max_allowed_packet=2097152`）でも往復が通る。離れた DB を模して、DB のコンテナとの間に遅延を入れ（`tc qdisc add dev eth0 root netem delay 20ms`。使えなければ toxiproxy、それも無理なら、問い合わせの回数（`SHOW SESSION STATUS LIKE 'Questions'`）と復元の時間の比較）、復元の時間が修正前より大きく減ることを測る。

S7 の確かめ: 接頭辞 `other_` で作ったダンプ（別のサイトのダンプ）を管理画面と `db:import` で戻すと、「接頭辞が違う」と出て、`pre-restore-` の控えも作られず、`checksum` が変わらない（修正前は「成功」と出て `other_` の表が増えた）。`db:export`（既定の `--routines --triggers`）の出力にトリガーを含めて戻すと「トリガーやルーチンを含むダンプは戻せない。`mysql` コマンドで戻す」と出る。`$modx->snapshot('a/b')` が `false`（修正前は `null`）。`db:backup`（mysqldump）で作ったスナップショットに `manager_log` の `CREATE TABLE` があり、`INSERT INTO` が無い（管理画面のスナップショットと同じ）。

S8 の確かめ: 管理画面で、表を選ばずにダウンロード・成功（ファイル名がそろい、中身が S3 と同じ）・失敗（`MODX_CACHE_PATH` を書き込めなくする）、スナップショットの作成の成功・失敗（保存先を書き込めなくする）・名前の不正、復元のアップロードの失敗（上限を超えるファイル）・スナップショットの名前の不正、のそれぞれで画面に出るものを、修正前と比べる（#1560 で決めた知らせ方にそろっている）。画面はシェルの遷移と全体の読み込み（F5）の両方で開く。`index.php?a=93` に `mode=backup` を POST しても何もダウンロードされない。

S9 の確かめ（`develop/1.4` の上で）: a=93 のタブとボタンが「ダウンロード」「選択したテーブルをダウンロード」になり、「スナップショット」のタブはそのまま。`php evo.php db:snapshot` で修正前の `db:backup` と同じスナップショットができる。`php evo.php db:backup` は不明なコマンドとして終了コードが 0 以外で終わる（`echo $?`）。`grep -rn "db:backup\|db-backup" manager/ assets/docs/ .agent/plans/` が 0 件（アーカイブを除く）。

最終確認: S8 の後（main で）と S9 の後（`develop/1.4` で）、`php:7.4-apache` のイメージ（`.claude/skills/adaptive-validation-runner/references/environment.md` の「ほかの PHP の版で検証するとき」）と `mysql:5.7` の DB で、S3 の往復と S7 の接頭辞の確かめを流す。

想定するコミットの単位（各段階の PR の中）:
- S1: `refactor(backup): 表の一覧と状態を EvoDbTables にまとめる`
- S2: `refactor(backup): ログの表を空にする入口を a=54 にまとめる`
- S3: `refactor(backup): ダンプの書き出しと読み込みを EvoSqlDump にまとめる`
- S4: `refactor(backup): スナップショットの保存先と世代を EvoSnapshotStore にまとめる`
- S5: `feat(settings): スナップショットの保存先をグローバル設定に出す` と `fix(backup): 保存先が使えないときに別の場所へ切り替えず知らせる`
- S6: `refactor(backup): 作成と復元の手順をまとめ、復元をファイルから流して読む`
- S6b: `feat(backup): PHP の書き出しを複数行の INSERT にする`
- S7: `fix(backup): 接頭辞の違うダンプとトリガーを含むダンプの復元を断る`
- S8: `refactor(backup): ダウンロードを processor へ移し、入口を薄くする`
- S9: `feat(backup): ダウンロードとスナップショットの言葉をそろえ、db:backup を db:snapshot に改名する`
- 各段階の ExecPlan の記録: `docs(plan): バックアップの整理の S<n> の結果を記録する`

## Validation and Acceptance

すべての段階を終えたとき、次がそろっている。
- 管理画面のダウンロード・スナップショット・`db:backup`（PHP と mysqldump の両方）・`db:export --driver=php`・mysqldump の出力のどれを、管理画面のアップロード・スナップショットの一覧・`db:import` のどれで戻しても、`CHECKSUM TABLE` が元と同じ（CRLF・単独の CR・NUL・`'`・`\`・日本語を含む値も元のとおり）。PHP 8.4（標準）と PHP 7.4・MySQL 5.7 の両方で。
- 別の接頭辞のダンプ、トリガーを含むダンプを戻そうとすると、DB を書き換えずに理由が出る。
- 表の一覧（管理画面のバックアップ・システム情報・CLI）・保存先と世代（管理画面・CLI・`$modx->snapshot()`・バインドの読み込み・インストーラー）・作成と復元の手順が、それぞれ 1 つのクラスから来ている（`grep -rn "SHOW TABLE STATUS" manager/` が `db_tables.class.inc.php` だけ、`grep -rn "assets/backup\|config('snapshot_path'" manager/includes/ manager/processors/ manager/actions/` が `snapshot_store.class.inc.php` だけ）。
- 保存先が使えないとき、別の場所へ黙って保存しない。
- 大きなダンプ（上の S6 の条件）を `memory_limit=128M` で戻せる。
- `snapshot_path` をグローバル設定の「ファイル」タブで変えられ、サイトの外は保存できない。
- 言葉: 画面の「バックアップ」タブが「ダウンロード」になり、CLI は `db:snapshot` だけになる（`develop/1.4` で）。

完了の扱い: 最後の段階の PR がマージされたら、「ExecPlan の実装と検証が完了した」と告げ、ロードマップの更新とこの ExecPlan のアーカイブの前に本人の確認を取る（`.agent/PLANS.md` の「完了処理プロトコル」。`exec-plan-runner` の `/finish-task` で行う）。

## Idempotence and Recovery

各段階は別のブランチと PR なので、途中で止まったら、その段階のブランチを捨てて `origin/main` から作り直せばよい（前の段階はマージ済みで影響しない）。確認スクリプトは作業用ディレクトリにあり、修正前の結果はいつでも `origin/main` の detached のツリーで取り直せる。検証環境は `EVO_VALIDATE_NAME=<名前> docker compose ... down -v --rmi local` で消し、追跡外の `manager/includes/config.inc.php` も消す。復元の確かめで DB を壊したときは、`down -v` で DB のボリュームごと作り直し、`setup-site.js` で入れ直す。S9 は `develop/1.4` に入れるので、S8 までが main にマージされ、`develop/1.4` に main を取り込んでから始める。

## Artifacts and Notes

- Issue: #1561（対象）、#1560（依存。不具合の修正）
- ブランチ: `refactor/1561-backup-reorg`（`gh issue develop 1561` で作成。S1 に使う。S2 以降の段階のブランチと PR は、作ったらここに書き足す）
- PR: S1 は #1563（`refactor/1561-backup-reorg`。Issue に紐づいたブランチなので、マージで #1561 が閉じる。閉じたら開き直して S2 へ進む）。マージ済み（main 10d3bfbed）
- S2: ブランチ `refactor/1561-s2-truncate-entry`（`origin/main` から作成。Issue に紐づけない）、PR #1566。マージ済み（main 675e39f96）
- S3: ブランチ `refactor/1561-s3-sql-dump`（`origin/main` から作成。Issue に紐づけない）、PR #1569。マージ済み（main 455f91f4b）
- S4: ブランチ `refactor/1561-s4-snapshot-store`（`origin/main` から作成。Issue に紐づけない）、PR #1574。マージ済み（main 172f7faeb）
- S5: ブランチ `refactor/1561-s5-snapshot-path`（`origin/main` から作成。Issue に紐づけない）、PR #1576。マージ済み（main 4ad093602）
- S6: ブランチ `refactor/1561-s6-backup-flow`（`origin/main` から作成。Issue に紐づけない）、PR #1578。マージ済み（main b8d24e587）
- S6b: ブランチ `refactor/1561-s6b-multirow-insert`（`origin/main` から作成。Issue に紐づけない）、PR #1581。マージ済み（main 2fba5e352）
- S7: ブランチ `refactor/1561-s7-restore-guard`（`origin/main` から作成。Issue に紐づけない）
- 段階ごとに PR を分ける（1 段階を 1 PR）。S1〜S7 は `Refs #1561`、S8 の PR で `Closes #1561`。S9 は 1.4 に回すので、マイルストーン「1.4」の別の Issue を起こして `develop/1.4` に向け、その Issue をこの計画の対象に書き足す。

## Interfaces and Dependencies

- 依存: #1560 のマージ（M0 で確かめる）。
- 作るクラスの公開メソッド（呼び出し元はこの計画のファイルだけ）:
  - `EvoDbTables`: `names(): array`、`status(): array`、`name(string $short): string`（接頭辞を除いた名前に必ず接頭辞を付ける。不正なら `''`）、`resolve(string $input): string`（利用者の入力を、このサイトに在る表の名前にする。無ければ `''`）、`resolveWithDb(string $input): string`、`exists(string $table): bool`（接頭辞の付いた完全な名前）、`truncatable(): array`、`isOwn(string $name): bool`
  - `EvoSqlDump`: `write($stream, array $tables, array $options = []): bool`、`writeFile(string $path, array $tables, array $options = []): bool`、`statements($stream): Generator`（1 文ずつ、文の番号と、先頭のコメントを除いた文を返す。番号は空の文やコメントだけの文も数える）。`write()` の options は `drop`・`structureOnly` の 2 つ（「コンテンツのみ」の表の絞り込みは呼び出し側で行う。Decision Log の S3 の項）、`tableOf(string $statement): string` と `inspect($stream): array`（下読み。S7。対象の表の名前と `DELIMITER` を含むか）
  - `EvoSnapshotStore`: `dir()`、`ensureDir()`、`normalizeName()`、`isValidFilename()`、`autoFilename($prefix = '')`、`path($name)`、`files()`、`prune($max)`、`counts()`（引数なし。自動の名前とそれ以外の件数を返し、上限は呼び出し側が出す）、`static defaultDir()`、`denyDirs()`、`static protect($dir)`、`__construct($configured = null)`（S5。保存の前の値を渡して確かめる）、`problem()`（S5。`''`・`OUTSIDE`・`SHARED`・`UNWRITABLE`。保存のときの確かめと、実行時に断る理由。計画の `checkPath()` をこの形にした）、`static normalizeValue($value)`（S5。保存と読むときの値の規則）、`guardedDirs()`（S5。ファイルブラウザーとバインドの守る場所）、定数 `HTACCESS`・`KEEP`・`PRE_RESTORE_KEEP`・`AUTO_NAME_PATTERN`・`PRE_RESTORE_PREFIX`
  - `EvoBackupManager`: `store()`、`createSnapshot($name = '', array $options = [])`、`restoreFile($path, $preRestore = null)`（戻り値の配列は今の `restore()` と同じキー）
- CLI の `mysqldump` を呼ぶ処理（`cli_export_database()`・`cli_export_database_file()`・`cli_mysql_defaults_file()`）は CLI だけのものなので `cli-helpers.php` に残し、`php` の書き手を `EvoSqlDump` に任せる。
- ほかの計画: `.agent/plans/2026-05-06-evo-system-upgrade-command.md` は `EvoBackupManager` の `ensureDir()`・`dir()`・`autoFilename()`・`HTACCESS` と `cli_export_database_file()` を使う前提で書かれている。S4・S6 の PR で、その計画の Interfaces and Dependencies と Decision Log の名前を新しい置き場所（`EvoSnapshotStore`）に書き直す。
- PDO 移行（#677）: この計画の後に入るとき、DB への問い合わせは `EvoDbTables`・`EvoSqlDump` と、`EvoBackupManager` の復元の文の実行・環境ごとの設定の読み書き・キャッシュの表を空にする処理を見ればよい（S6 の時点。`assets/docs/backup.md`）。
