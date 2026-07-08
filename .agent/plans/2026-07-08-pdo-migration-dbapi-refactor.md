# ExecPlan: PDO移行（DBAPI改修 フェーズ1）

## Purpose / Big Picture

DBアクセス層（`DBAPI` クラス）の内部実装を、mysqli直叩きからPDOベースのオブジェクト指向実装へ置き換える。呼び出し側（`db()->select()` 等、約1,700箇所）のAPI契約は完全互換のまま維持し、内部だけをモダン化することで、保守性向上と将来のマルチDB対応（PostgreSQL/SQLite）への土台を作る。当初ロードマップ案にあった「mysqli_*関数の手続き型ラッパー」は根本解決にならないため不採用とし、クラス内部を作り直す方針に切り替えた。

## Progress

- [ ] (2026-07-08) ExecPlan作成
- [ ] (2026-07-08) `DriverInterface`・`PdoMysqlDriver`・`Result` を新規作成する
- [ ] (2026-07-08) `DBAPI.php` を新規作成し、mysqli依存箇所をDriver/Result経由へ置き換える
- [ ] (2026-07-08) `ex_dbapi.php` の読み込み先を `DBAPI.php` へ変更する
- [ ] (2026-07-08) 動作確認後、`mysqli.inc.php` を削除する
- [ ] (2026-07-08) 判定用の互換メッセージ・チェック（bootstrap.php等4ファイル）を更新する
- [ ] (2026-07-08) `architecture.md` のパス言及を更新する
- [ ] (2026-07-08) Validation and Acceptance の各項目を実施する

## Surprises & Discoveries

- `mysqli.inc.php` への `include_once` は `manager/includes/extenders/ex_dbapi.php` の1箇所のみ。ファイル名・配置を自由に再設計してよい。
- `db()->` 系メソッドの呼び出しは grep 調査で約1,700箇所あるが、すべて `DBAPI` の公開メソッド経由。外部コードが `mysqli_result` オブジェクトのプロパティ（`num_rows` 等）に直接触れている形跡はない（`getRow()` 等のラッパー経由のみ）。
- `dataSeek()` の外部呼び出しは0件（`DBAPI` 内部でも未使用）。Result ラッパーでのスクロールカーソル対応は本フェーズでは省略してよい。
- `numFields()` / `fieldName()` は `manager/includes/controls/datagrid.class.php` と `document.parser.subparser.trait.php` から使われている（PDOStatement の `columnCount()` / `getColumnMeta()` で代替可能）。
- mysqli に直接依存する外部ファイルは4つあるが、いずれも実処理ではなく判定・表示用の文字列: `manager/includes/cli/bootstrap.php`（`extension_loaded('mysqli')` チェック）、`manager/includes/cli/commands/health-check.php`（同）、`install/instprocessor.php`（`$ph['database_type']='mysqli'` という表示用文字列）、`manager/includes/default.config.php`（文字コード関連のエラーメッセージ文言）。これらはPDO移行後に `pdo_mysql` 拡張の確認へ差し替える必要がある。
- `manager/includes/mysql_dumper.class.inc.php` は `db()` 経由のみで mysqli 非依存。ただし `addslashes` ベースの独自エスケープ等、別の設計課題を抱えている（`assets/docs/core-issues.md` の「Mysqldumperクラスの設計上の制約」に記録済み、関連ロードマップ: 本タスク）。改修するかはフェーズ2以降で判断し、本フェーズのスコープには含めない。
- 独立した `mysql_*()` 関数のポリフィルファイルは存在しなかった。ロードマップ記載の「既存 mysql_ 系互換レイヤーを整理」は、`mysqli.inc.php` の `DBAPI` クラス自体を新実装に置き換えることで満たされると解釈する。

## Decision Log

- 2026-07-08 / エンジニアと合意: オブジェクト指向のPDO実装を採用（手続き型 mysqli_* ラッパー案は不採用）。理由: ラッパーは対症療法であり、保守負担は変わらない。
- 2026-07-08 / 段階移行方針: フェーズ1=内部PDO化（API完全互換・MySQLのみ）、フェーズ2=生SQL呼び出しのプレースホルダ移行、フェーズ3=PostgreSQL/SQLite等マルチDB対応。本ExecPlanはフェーズ1のみを扱う。
- 2026-07-08 / 結果セットは `Result` ラッパークラスで包む。`PDOStatement` を直接呼び出し側へ渡さないことで、将来ドライバを差し替えても `DBAPI` 側の実装に影響が波及しない。
- 2026-07-08 / `escape()` は `PDO::quote()` の前後クォートを除去して返す（既存の `mysqli::escape_string()` はクォートなし文字列を返すため、呼び出し側は `'...'` を自前で付与している）。
- 2026-07-08 / SQL方言差分（`REPLACE INTO`・`INSERT IGNORE`・`SHOW TABLES`・`DESCRIBE`・`OPTIMIZE TABLE`・識別子のバッククォート等）は `DriverInterface` 越しに扱えるようメソッド化するが、実装するのは `PdoMysqlDriver` のみ。PostgreSQL/SQLite実装はスコープ外（フェーズ3）。
- 2026-07-08 / ファイル構成は `manager/includes/extenders/dbapi/` 配下に `DBAPI.php`（ファサード）・`Result.php`・`drivers/DriverInterface.php`・`drivers/PdoMysqlDriver.php` を新設し、`mysqli.inc.php` は削除する。プロジェクトに PSR-4 オートローダーがないため、`DBAPI.php` 先頭で `require_once` により他ファイルを読み込む。

## Outcomes & Retrospective

## Context and Orientation

**対象ファイル**: `manager/includes/extenders/dbapi/mysqli.inc.php`（`DBAPI` クラス、1,251行）
**ロード元**: `manager/includes/extenders/ex_dbapi.php`（`$this->db = new DBAPI` で1インスタンスを生成し `evo()->db` として全体へ公開）

**用語定義**:
- **DBAPI**: Evolution CMSのDBアクセスを抽象化するファサードクラス。`db()` ヘルパー経由で全コードから呼ばれる。
- **Result**: 1件のクエリ実行結果を表すオブジェクト。本改修で新設するラッパークラスで、内部で `PDOStatement` を保持する。
- **Driver**: 実際のDB接続・クエリ実行・SQL方言差分を担当する実装単位。`DBAPI` は Driver を通じてのみDBへアクセスし、Driverを差し替えれば別DBMSに対応できる設計にする（実装は MySQL のみ）。

**呼び出し規模**（grep調査、`db()->` 経由のみ）: `select` 396、`getRow` 290、`escape` 206、`query` 171、`update` 128、`getValue` 91、`delete` 67、`insert` 58、`getLastError` 32、その他 `isResult`/`getObject`/`getColumn`/`freeResult`/`tableExists`/`makeArray`/`getInsertId`/`isConnected`/`getVersion`/`getAffectedRows`/`connect`/`truncate`/`prop`/`get`/`save`/`numFields`/`insert_ignore`/`getFullTableName`/`exec`/`lastQuery`/`getObjects`/`fieldName`/`fieldExists`/`server_info`/`select_db`/`optimize`/`host_info`/`getRecordCount`/`getLastErrorNo`/`disconnect` が合計約60箇所。**これらすべての公開メソッドのシグネチャと返却値の意味を変えない**ことが本フェーズの必須要件。

**MySQL固有SQL構文の利用状況**（呼び出し側コード全体）: `REPLACE INTO` 12箇所、`INSERT IGNORE` 3箇所、`SHOW TABLES` 5箇所、`DESCRIBE` 4箇所、`OPTIMIZE TABLE` 3箇所、`SHOW FIELDS` 1箇所。これらは将来のマルチDB対応時に方言差分として問題になるため、Driverインターフェースに切り出す対象として認識しておく（実装はMySQLのみ）。

**mysqli依存箇所と `DBAPI` 内での役割**（`connect()`/`escape()`/`exec()` 等で使用）:
- `mysqli_init()` / `real_connect()` / `mysqli_report()` — 接続確立とタイムアウト設定
- `$this->conn->escape_string()` — エスケープ
- `$this->conn->query()` — クエリ実行（戻り値は `mysqli_result` または `bool`）
- `$this->conn->insert_id` / `affected_rows` / `error` / `errno` — 実行結果メタ情報
- `$rs->num_rows` / `fetch_assoc()` / `fetch_row()` / `fetch_object()` / `fetch_array(MYSQLI_BOTH)` / `data_seek()` / `field_count` / `fetch_field_direct($i)->name` — 結果セット操作

## Plan of Work

### 全体方針

`DBAPI` クラスの公開メソッド（Context and Orientation記載の約60種）は一切シグネチャを変えない。内部実装だけを、`PdoMysqlDriver`（接続・クエリ実行・エスケープ・方言吸収）と `Result`（結果セット操作）に委譲する形へ置き換える。

### Driverインターフェースの設計

`DriverInterface` は以下を定義する:
- `connect(string $host, string $user, string $pass, ?string $dbase, int $timeout): bool`
- `query(string $sql): Result|false`
- `escape($value): string`（クォートなし文字列を返す。呼び出し側の `'%s'` 形式の埋め込みと互換）
- `lastInsertId(): string|int`
- `affectedRows(): int`
- `lastError(): string`
- `lastErrorNo(): int`
- `quoteIdentifier(string $name): string`（バッククォート等の識別子クォート、方言差分の吸収点）
- `isConnected(): bool`
- `close(): void`

`PdoMysqlDriver` はこれを `PDO` で実装する。

### 接続確立（`connect()`）の移植

- `PDO` のDSNは `mysql:host={host};charset={charset}` 形式（ポート指定がある場合は `;port={port}`）。
- 接続タイムアウトは `PDO::ATTR_TIMEOUT`（`$timeout` が指定された場合のみ設定）。
- 既存コードの `mysqli_report(MYSQLI_REPORT_OFF)` 相当として、PDOは `PDO::ATTR_ERRMODE = PDO::ERRMODE_SILENT` を設定し、エラーは戻り値・`errorInfo()` で判定する（例外を投げさせない）。これにより既存の「接続失敗時は `false` を返してメール通知」という制御フローをそのまま維持できる。
- 接続後の `$this->connection_method . ' ' . $this->charset`（`SET CHARACTER SET utf8` 等）の実行は、PDO接続後に `PDO::exec()` でそのまま流用可能。
- `select_db()` はPDOに直接の等価メソッドがないため、DSNに `dbname` を含めて接続するか、接続後に `USE `db`` を実行する形に置き換える（既存が接続後に `select_db()` を呼ぶ設計のため、後者を踏襲する）。

### エラー処理（`exec()`）の移植

既存は `!in_array($this->conn->errno, [1064, 1054, 1060, 1061, 1091])` の場合のみ `messageQuit()` で停止し、該当errnoは黙って `true` を返す（idempotentなDDL実行のため）。PDOでは `PDO::ATTR_ERRMODE = PDO::ERRMODE_SILENT` 設定時、`PDOStatement::errorInfo()[1]` にドライバ固有のMySQLエラーコードがそのまま入るため、同じ判定ロジックをそのまま移植できる。

### Result ラッパー

`PDOStatement` を保持し、以下を提供する:
- `numRows(): int` — `rowCount()` を返す（MySQL＋バッファードクエリ前提でSELECTのrowCountが正しく機能する。PDO_MYSQLはデフォルトでバッファードクエリのため問題ない。他ドライバでは非対応の場合がある点はフェーズ3で要検討）
- `fetchAssoc()` / `fetchRow()` / `fetchObject()` / `fetchBoth()` — `PDOStatement::fetch()` に `PDO::FETCH_*` 定数を指定
- `columnCount(): int` — `columnCount()`
- `columnName(int $i): string` — `getColumnMeta($i)['name']`
- `dataSeek(int $n)` — 本フェーズでは未使用（外部呼び出し0件）のため、スクロールカーソル対応は実装せず、呼ばれた場合は例外を投げるスタブとする

### `DBAPI` ファサードの実装

`mysqli.inc.php` の各メソッド本体を、`$this->conn->` への直接アクセスから `$this->driver->` 経由の呼び出しへ書き換える。SQL文字列の組み立てロジック（`select()`/`update()`/`insert()`/`delete()`/`_where()`/`replaceFullTableName()` 等）はDB非依存のためほぼそのまま流用する。

### プレースホルダ対応の追加（フェーズ1の範囲内）

既存の配列渡しAPI（`insert($fields, $table)`・`update($fields, $table, $where)`）は、値をSQL文字列に埋め込む代わりに、内部で `PDOStatement::bindValue()` を使うよう置き換える。これにより、配列渡しで使われている呼び出し（1,700箇所のうち相当数）は自動的にプリペアドステートメント化される。一方、`query($sql)` に生SQL文字列を直接渡す171箇所は本フェーズでは変更しない（フェーズ2で対応）。

## Concrete Steps

### Step 1: ファイル構成を新設

新規作成:
- `manager/includes/extenders/dbapi/drivers/DriverInterface.php` — インターフェース定義（Plan of Work記載のメソッド一式）
- `manager/includes/extenders/dbapi/drivers/PdoMysqlDriver.php` — `DriverInterface` の実装（接続・クエリ実行・エスケープ・識別子クォート・エラー情報取得）
- `manager/includes/extenders/dbapi/Result.php` — `PDOStatement` を保持するラッパークラス

期待される観測結果: 3ファイルとも構文エラーなく作成される（`php -l <file>` でOK）。

### Step 2: `DBAPI.php` を新規作成し `mysqli.inc.php` を置き換え

新規作成: `manager/includes/extenders/dbapi/DBAPI.php`

- ファイル先頭で `require_once __DIR__ . '/drivers/DriverInterface.php';` `require_once __DIR__ . '/drivers/PdoMysqlDriver.php';` `require_once __DIR__ . '/Result.php';`
- `mysqli.inc.php` の `DBAPI` クラス本体をコピーし、`$this->conn`（`mysqli` インスタンス）への直接参照をすべて `$this->driver`（`PdoMysqlDriver` インスタンス）経由に置き換える
- コンストラクタ・`connect()`・`escape()`・`exec()`・`select_db()`・`disconnect()`・`getInsertId()`・`getAffectedRows()`・`lastError()`・`getLastErrorNo()`・`getVersion()`・`host_info()`・`freeResult()`・`fieldName()`・`numFields()`・`dataSeek()`・`isConnected()` の実装を Plan of Work の方針に沿って書き換える
- それ以外のSQL文字列組み立て系メソッド（`select`/`update`/`insert`/`delete`/`_insert`/`_where`/`replaceFullTableName`/`getFullTableName`/`getRow`/`getRows`/`getColumn`/`getColumnNames`/`getValue`/`makeArray`/`getObject`/`getObjectSql`/`getObjects`/`getXML`/`getTableMetaData`/`prepareDate`/`getHTMLGrid`/`optimize`/`truncate`/`importSql`/`tableExists`/`table_exists`/`fieldExists`/`field_exists`/`_getFieldsStringFromArray`/`_getFromStringFromArray`/`rawQuery`/`prop`/`get`/`set`/`count`/`getRecordCount`/`lastQuery`/`isResult`/`server_info`/`selectDb`/`save`）はロジックを変えず、`$rs->fetch_assoc()` 等の直接呼び出し箇所だけを `Result` のメソッド呼び出しへ置き換える

期待される観測結果: `php -l manager/includes/extenders/dbapi/DBAPI.php` が構文エラーなし。

### Step 3: `ex_dbapi.php` の読み込み先を変更

編集対象: `manager/includes/extenders/ex_dbapi.php` 2行目

変更前: `include_once __DIR__ . '/dbapi/mysqli.inc.php';`
変更後: `include_once __DIR__ . '/dbapi/DBAPI.php';`

### Step 4: 旧ファイルを削除

削除対象: `manager/includes/extenders/dbapi/mysqli.inc.php`

Step 1〜3の動作確認が完了してから削除する（Idempotence and Recoveryも参照）。

### Step 5: 判定用の互換メッセージ・チェックを更新

編集対象:
- `manager/includes/cli/bootstrap.php` — `extension_loaded('mysqli')` を `extension_loaded('pdo_mysql')` に変更する
- `manager/includes/cli/commands/health-check.php` — `check('PHP mysqli extension', extension_loaded('mysqli'))` を `check('PHP pdo_mysql extension', extension_loaded('pdo_mysql'))` に変更する
- `install/instprocessor.php` 282行目 — `$ph['database_type'] = 'mysqli';` を `$ph['database_type'] = 'pdo_mysql';` に変更する。この値は生成される `config.inc.php` に書き込まれるのみで実行時に読み取られる箇所がない（grep調査で確認済み）ため、実態を反映する文字列変更のみで影響はない
- `manager/includes/default.config.php` 118〜120行目 — `mysqli_set_charset`/`mysql_set_charset` の存在チェックを `extension_loaded('pdo_mysql')` に変更する。警告文言中の「mysqli.incのescape関数の処理を書き換えてください。mb_convert_encodingの処理を行なっている行が2行ありますので」は現行の `escape()` 実装に該当箇所がなく既に実態と合っていないため、この一文を削除し「対応が必要な場合は、サーバ環境のUTF-8エンコードの扱いを整備してください。」に置き換える

これらは実処理に影響しないため、Step 1〜4完了後に更新する。

### Step 6: ドキュメントのパス言及を更新

編集対象: `assets/docs/architecture.md` 24行目

`` `DBAPI`（`manager/includes/extenders/dbapi/mysqli.inc.php` など）が遅延ロードされ `` の記述を、Step 4で削除した `mysqli.inc.php` から新設した `DBAPI.php` へのパスに更新する。コミット前に `/doc-audit` でこのファイルを含む変更対象を確認する（AGENTS.mdの運用ルールに従う）。

## Validation and Acceptance

いずれもDockerコンテナ内（`docker compose exec <app-service> ...`）で確認する。

1. **CLIによる疎通確認**: `php evo db:tables` がテーブル一覧を正常に返す（接続・`SHOW TABLES` 相当の実行が機能している証拠）
2. **CRUD確認**: `php evo db:query "SELECT * FROM {prefix}system_settings LIMIT 5"` が結果を返す。`php evo db:count site_content` が件数を返す
3. **管理画面での動作確認**: ブラウザで管理画面にログインし、リソース一覧表示（`select`/`getRow`/`count` 経由）、リソースの新規作成・更新・削除（`insert`/`update`/`delete` 経由）が従来どおり動作する
4. **エラー抑制の互換確認**: 既に存在するカラムへの `ADD COLUMN` を含むSQL（errno 1060相当）を実行し、`messageQuit()` で停止せず処理が継続することを確認する（マイグレーション機構やbaseline実行時の冪等性に影響するため重要）
5. **`getObject`/`getObjects`/`makeArray`/`getXML`/`getHTMLGrid` の確認**: 管理画面のデータグリッド表示（`datagrid.class.php` 経由、`numFields`/`fieldName` を使用）が崩れずに表示される
6. **健全性チェック**: `php evo health-check` がエラーなく完了する

## Idempotence and Recovery

- Step 1〜3は新規ファイル追加と1行の読み込み先変更のみのため、途中で中断しても `mysqli.inc.php` が残っていれば `ex_dbapi.php` を元に戻すだけで旧実装に復帰できる
- Step 4（旧ファイル削除）はStep 1〜3の動作確認が完了するまで実行しない
- 本番相当の検証は必ずDBのバックアップ（`php evo db-backup` 等の既存コマンド）を取得してから行う

## Artifacts and Notes

- `manager/includes/extenders/ex_dbapi.php` — DBAPIのロード・グローバル設定箇所
- `manager/includes/controls/datagrid.class.php` — `numFields`/`fieldName` の利用箇所
- `manager/includes/traits/document.parser.subparser.trait.php` — `numFields` の利用箇所
- `manager/includes/mysql_dumper.class.inc.php` — `db()` 経由でDBAPIに依存するが本フェーズのスコープ外（`assets/docs/core-issues.md` 参照）
- `assets/docs/core-issues.md` — 「Mysqldumperクラスの設計上の制約」の関連ロードマップとして本タスクが記録済み

## Interfaces and Dependencies

- **依存**: PHP `pdo_mysql` 拡張（`mysqli` 拡張からの切り替え。Docker環境のPHPイメージに含まれているか事前確認が必要）
- **依存**: `evo()` グローバルヘルパー（`messageQuit`/`sendmail`/`dumpSQLCode`/`getMicroTime` 等、`DBAPI` 内部から使用。変更なし）
- **影響範囲**: `db()` ヘルパーを経由する全コード（約1,700箇所）。ただし公開API契約を維持するため、呼び出し側コードの変更は不要
- **将来の拡張**: `DriverInterface` を実装する `PdoPgsqlDriver` / `PdoSqliteDriver` を追加すれば、`DBAPI` 側のコード変更なしにマルチDB対応が可能になる設計（実装はフェーズ3、別ExecPlanで扱う）
