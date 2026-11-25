# データベースのバックアップ（ダウンロード・スナップショット・復元）

データベースの控えを作る・戻す処理の受け持ちと、ダンプの約束をまとめる。
管理画面のバックアップ管理（a=93）、CLI の `db:backup`・`db:export`・`db:import`、公開の API の `$modx->snapshot()` がここに書いた部品を使う。

## 言葉

- ダンプ: DB の表の構造（`CREATE TABLE`）と中身（`INSERT INTO`）を SQL の文として並べたテキストファイル（`.sql`）
- スナップショット: サーバーの保存先に置いたダンプ。管理画面の「スナップショット」タブ、CLI の `db:backup`、`$modx->snapshot()` が作る
- 復元: ダンプの文を順に実行して、DB をダンプの状態に戻すこと。管理画面のアップロードとスナップショットの一覧、CLI の `db:import` が行う
- 復元の前の控え: 復元で書き換える前に、今の DB を `pre-restore-<自動の名前>.sql` として保存したスナップショット

## 受け持ち

| 受け持ち | クラス（ファイル） | 持つもの |
| --- | --- | --- |
| 表の一覧 | `EvoDbTables`（`manager/includes/db_tables.class.inc.php`） | このサイトの表（接頭辞の付いた表）の一覧・状態・名前の付け方・有無、選んだ名前をこのサイトの表に絞る（`own()`）、空にしてよい表、最適化してよい表 |
| ダンプの形 | `EvoSqlDump`（`manager/includes/sql_dump.class.inc.php`） | ダンプを書く（PHP の書き手）、ダンプを 1 文ずつ読む |
| 保存先と世代 | `EvoSnapshotStore`（`manager/includes/snapshot_store.class.inc.php`） | 保存先の決め方と使えるかの判定、保護の `.htaccess`、名前、一覧、世代の削除、読ませない置き場所 |
| 作成と復元の手順 | `EvoBackupManager`（`manager/includes/backup_manager.class.inc.php`） | スナップショットの作成（`createSnapshot()`）、管理画面のダウンロード用のダンプ（`writeDownload()`・`downloadName()`）、ダンプのファイルからの復元（`restoreFile()`）、復元の前の控え、環境ごとの設定の保持、キャッシュの作り直し |
| 入口 | 画面 `manager/actions/tool/bkmanager.static.php`（a=93）、`manager/processors/backup/` のダウンロード（a=306）・復元（a=305）・スナップショットの作成（a=307）、`manager/includes/cli/commands/db-*.php`、`$modx->snapshot()` | 入力を受けて手順を呼び、結果を知らせる。画面は表示だけで、操作は processor が受ける（POST と CSRF のトークンが要る） |

表の一覧・状態とダンプの読み書きの問い合わせは、`EvoDbTables` と `EvoSqlDump` に置く。復元の文の実行と、環境ごとの設定の読み書き・キャッシュの表を空にする処理は、まだ `EvoBackupManager` が直接 `db()` を呼ぶ（PDO 移行のときは、この 3 つのクラスを見る）。

## ダンプの約束

書く側（`EvoSqlDump::write()`）と読む側（`EvoSqlDump::statements()`）は、次の約束を共有する。

- 値には生の改行を入れない（改行・CR は `db()->escape()` で `\n`・`\r` になる）
- 1 つの `INSERT` が複数の行のデータを持ち、データ 1 行ごとに改行する（``INSERT INTO `t` VALUES`` の行のあと、途中の行は `(...),`、最後の行だけ `(...);`）。1 つの文の大きさは、`mysqldump` の既定の `net_buffer_length`（1,046,528 バイト）と、書いたときの DB の `max_allowed_packet` − 1KB の小さいほうで区切る（1 行だけで超える行は、その行だけの文になる）。書いたときより `max_allowed_packet` が小さい DB へ戻すと、1 つの文が入らないことがある
- 文は「;」で行を終える。読む側は「;」の後に空白とタブだけを挟んで改行が来たところで文を分ける
- 読む側は CRLF と単独の CR を LF にそろえてから分け、文の先頭のコメント（`--`・`#`・`/* */`。`/*! */` は残す）を除く

`mysqldump` の出力（`--skip-triggers` を付けた、`DELIMITER` を含まないもの）も、この約束で読める。
書き手は 2 つある。管理画面はいつも PHP の書き手を使う。CLI の `db:backup`・`db:export` は既定で `mysqldump`、`--driver=php` で PHP の書き手を使い、`db:import` の復元の前の控えはいつも `mysqldump` で書く。復元はどちらの出力も読む。
`db:backup` は、管理画面のスナップショットと同じ中身にする（ログの表は構造だけ）。`mysqldump` では、ログの表を外した 1 回目と、ログの表だけを `--no-data --skip-add-drop-table` で書く 2 回目を続け、2 回目の `CREATE TABLE` を `CREATE TABLE IF NOT EXISTS` にする（`--ignore-table-data` は MariaDB と新しい MySQL にしか無いので使わない）。

復元はダンプのファイルを 1 行ずつ読み、文ごとに実行する（ファイル全体をメモリに読まない）。

## 保存先

- グローバル設定の「ファイル」タブの「スナップショットの保存先」（`snapshot_path`）。既定は `[(base_path)]temp/backup/`
- 値の解き方は `EvoSnapshotStore::normalizeValue()` 1 つ（相対パスはサイトのフォルダから。CLI と管理画面で同じ場所になる）
- 使えない値（サイトの外、作れない・書き込めない、スナップショット専用でないフォルダ、管理画面などサイトの置き場所の中や親）のときは、別の場所に切り替えずに作成も復元も止め、システムログに残す
- 以前の置き場所 `assets/backup/` は保存先にしない。残った控えを読ませないよう、守る場所の一覧（`EvoSnapshotStore::guardedDirs()`）には入れる

## 復元の前に断るもの

復元は、書き換える前にダンプを最後まで下読みし（`EvoSqlDump::inspect()`）、次のダンプは復元の前の控えも作らず、何も書き換えずに断る。管理画面（アップロード・スナップショットの一覧）と CLI の `db:import` は同じ判定で、同じことを知らせる。

- このサイトの表でない表を含むダンプ（別のサイトのダンプなど）。このサイトの接頭辞で始まらない表と、本体の表の名前（`system_settings` など）で終わるのに「このサイトの接頭辞 + 本体の表の名前」でない表（`modx_` のサイトへの `modx_old_` のダンプなど）を見る。ダンプの接頭辞とこのサイトの接頭辞を示す。接頭辞を置き換えて取り込む機能は無い
- 同じ DB に、接頭辞が重なる 2 つのサイト（`modx_` と `modx_old_` など）があると、`modx_` のサイトの書き出しには `modx_old_` の表も入るので、そのサイトは自分のスナップショットも戻せない（断る）。接頭辞は大文字小文字を問わず比べる
- 表の名前を拾う文は `INSERT`・`REPLACE`・`CREATE TABLE`・`DROP TABLE`・`TRUNCATE`・`LOCK TABLES`・`ALTER TABLE`（`EvoSqlDump::tableOf()`）。ビューやルーチンの定義の中の表は見ない
- このサイトの DB 以外の DB を指すダンプ（`USE`・`CREATE DATABASE`・`` `db`.`table` `` の形の表。`EvoSqlDump::databaseOf()`）。`DROP DATABASE`・`ALTER DATABASE` は DB 名にかかわらず断る。このサイトの DB 名と同じ名前（`mysqldump --databases` でこのサイトの DB だけを書き出したダンプ）は、別の DB へ向かわないので許す。DB 名は大文字小文字を区別して比べる。見るのは、文の先頭が `USE`・`CREATE`/`DROP`/`ALTER` `DATABASE`（`SCHEMA`）・表を対象にする文（上の一覧）の DB 名で、文字列やコメントの中は見ない（`/*!NNNNN ... */` の形は見る）。DB 名の違うサーバーへ移すときは、`USE` などを含まない書き出し（`db:export`・スナップショット）を使う
- `DELIMITER` を含むダンプ（トリガー・ストアドプロシージャー。`db:export` の既定の出力など）。`mysql` コマンドで戻すよう知らせる

## 復元で残すもの

- 環境ごとの設定（`site_url`・`base_url`・`filemanager_path`・`rb_base_dir`・`fixed_site_url`）は、取り込み先の値を残す（`assets/docs/site-url.md`）
- `system_cache` の表への文は飛ばし、最後にキャッシュを作り直す
- 途中の文で失敗したときは、`UNLOCK TABLES` を送ってから（`mysqldump` 形式の `LOCK TABLES` が残ると、ほかの表を更新できない）設定だけ戻し、復元の前の控えの名前を知らせる
