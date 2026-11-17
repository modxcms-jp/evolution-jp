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
| 表の一覧 | `EvoDbTables`（`manager/includes/db_tables.class.inc.php`） | このサイトの表（接頭辞の付いた表）の一覧・状態・名前の付け方・有無、空にしてよい表、最適化してよい表 |
| ダンプの形 | `EvoSqlDump`（`manager/includes/sql_dump.class.inc.php`） | ダンプを書く（PHP の書き手）、ダンプを 1 文ずつ読む |
| 保存先と世代 | `EvoSnapshotStore`（`manager/includes/snapshot_store.class.inc.php`） | 保存先の決め方と使えるかの判定、保護の `.htaccess`、名前、一覧、世代の削除、読ませない置き場所 |
| 作成と復元の手順 | `EvoBackupManager`（`manager/includes/backup_manager.class.inc.php`） | スナップショットの作成（`createSnapshot()`）、ダンプのファイルからの復元（`restoreFile()`）、復元の前の控え、環境ごとの設定の保持、キャッシュの作り直し |
| 入口 | `manager/actions/tool/bkmanager.static.php`・`manager/processors/backup/`・`manager/includes/cli/commands/db-*.php`・`$modx->snapshot()` | 入力を受けて手順を呼び、結果を知らせる |

DB に問い合わせるのは `EvoDbTables` と `EvoSqlDump` だけにする（PDO 移行で直す場所をこの 2 つに閉じ込めるため）。

## ダンプの約束

書く側（`EvoSqlDump::write()`）と読む側（`EvoSqlDump::statements()`）は、次の約束を共有する。

- 値には生の改行を入れない（改行・CR は `db()->escape()` で `\n`・`\r` になり、1 件の `INSERT` が 1 行に収まる）
- 文は「;」で行を終える。読む側は「;」の後に空白とタブだけを挟んで改行が来たところで文を分ける
- 読む側は CRLF と単独の CR を LF にそろえてから分け、文の先頭のコメント（`--`・`#`・`/* */`。`/*! */` は残す）を除く

`mysqldump` の出力（`--skip-triggers` を付けた、`DELIMITER` を含まないもの）も、この約束で読める。
書き手は 2 つある。管理画面はいつも PHP の書き手を使い、CLI は既定で `mysqldump`、`--driver=php` で PHP の書き手を使う。復元はどちらの出力も読む。

復元はダンプのファイルを 1 行ずつ読み、文ごとに実行する（ファイル全体をメモリに読まない）。

## 保存先

- グローバル設定の「ファイル」タブの「スナップショットの保存先」（`snapshot_path`）。既定は `[(base_path)]temp/backup/`
- 値の解き方は `EvoSnapshotStore::normalizeValue()` 1 つ（相対パスはサイトのフォルダから。CLI と管理画面で同じ場所になる）
- 使えない値（サイトの外、作れない・書き込めない、スナップショット専用でないフォルダ、管理画面などサイトの置き場所の中や親）のときは、別の場所に切り替えずに作成も復元も止め、システムログに残す
- 以前の置き場所 `assets/backup/` は保存先にしない。残った控えを読ませないよう、守る場所の一覧（`EvoSnapshotStore::guardedDirs()`）には入れる

## 復元で残すもの

- 環境ごとの設定（`site_url`・`base_url`・`filemanager_path`・`rb_base_dir`・`fixed_site_url`）は、取り込み先の値を残す（`assets/docs/site-url.md`）
- `system_cache` の表への文は飛ばし、最後にキャッシュを作り直す
- 途中の文で失敗したときは、設定だけ戻し、復元の前の控えの名前を知らせる
