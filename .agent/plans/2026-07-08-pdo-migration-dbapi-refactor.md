# ExecPlan: PDO移行（DBAPI改修 フェーズ1）

## Purpose / Big Picture

DBアクセス層（`DBAPI` クラス）の内部実装を、mysqli直叩きからPDOベースのオブジェクト指向実装へ置き換える。呼び出し側（`db()->select()` 等、約1,700箇所）が使う**公開メソッドのAPI契約**は完全互換のまま維持し、内部だけをモダン化することで、保守性向上と将来のマルチDB対応（PostgreSQL/SQLite）への土台を作る。公開プロパティ `$conn` は互換対象に含めず、直接参照箇所は本ExecPlan内のStep 3で既存公開メソッド経由へ解消する。当初ロードマップ案にあった「mysqli_*関数の手続き型ラッパー」は根本解決にならないため不採用とし、クラス内部を作り直す方針に切り替えた。

## Progress

- [ ] (2026-07-08) ExecPlan作成
- [ ] (2026-07-08) `DriverInterface`・`PdoMysqlDriver`・`Result` を新規作成する
- [ ] (2026-07-08) `DBAPI.php` を新規作成し、mysqli依存箇所をDriver/Result経由へ置き換える
- [ ] (2026-07-08) インストーラの `$conn` 直接アクセス（2ファイル）を既存公開メソッド経由へ書き換える
- [ ] (2026-07-08) `ex_dbapi.php` の読み込み先を `DBAPI.php` へ変更する
- [ ] (2026-07-08) 動作確認後、`mysqli.inc.php` を削除する
- [ ] (2026-07-08) 判定用の互換メッセージ・チェック（bootstrap.php等4ファイル）を更新する
- [ ] (2026-07-08) `architecture.md` のパス言及を更新する
- [ ] (2026-07-08) Validation and Acceptance の各項目を実施する

## Surprises & Discoveries

- `mysqli.inc.php` への `include_once` は `manager/includes/extenders/ex_dbapi.php` の1箇所のみ。ファイル名・配置を自由に再設計してよい。
- `db()->` 系メソッドの呼び出しは grep 調査で約1,700箇所あるが、大半は `DBAPI` の公開メソッド経由。ただし例外として、インストーラの2ファイルが公開プロパティ `$conn`（生の接続オブジェクト）へ直接アクセスしている（詳細は次項）。`mysqli_result` オブジェクトのプロパティ（`num_rows` 等）への直接アクセスは発見されなかった（`getRow()` 等のラッパー経由のみ）。
- **（レビューで発覚）** `install/connection.servertest.php` 24〜26行目・`install/connection.databasetest.php` 30〜32行目が `if (db()->conn) { db()->conn->close(); db()->conn = null; }` という形で `$conn` に直接アクセスし、既存接続を強制的に破棄してから新しい接続情報でテストしている（コメントに理由の記載あり: `connect()` は `isConnected()` が真だと即座に `true` を返すため、接続テスト画面で新しい入力値を試すには一度明示的に切断する必要がある）。さらに `install/connection.databasetest.php` 110行目は `return @db()->conn->query($query);` で `CREATE DATABASE` 文を生の接続オブジェクトへ直接投げている。当初のgrep調査（`db()->[a-zA-Z_]+\(` パターン）では `db()->conn->close()` のような「プロパティアクセス後にさらにメソッド呼び出し」の形を検出できていなかった。
- `manager/includes/document.parser.class.inc.php` 5475〜5479行目の `DocumentParser::dbConnect()`（「deprecated db functions」とコメントされた非推奨メソッド）も `$this->rs = $this->db->conn;` で `$conn` に直接アクセスしている。ただしこのメソッド自体はリポジトリ全体で呼び出し箇所が0件（`dbConnect(`のgrepで確認済み）。
- `dataSeek()` の外部呼び出しは0件（`DBAPI` 内部でも未使用）だが、公開メソッドである以上フェーズ1の「シグネチャ・返却値互換を変えない」要件の対象。省略はせず、行バッファ方式で互換実装する（Result ラッパー参照）。
- `numFields()` / `fieldName()` は `manager/includes/controls/datagrid.class.php` と `manager/includes/traits/document.parser.subparser.trait.php` から使われている（PDOStatement の `columnCount()` / `getColumnMeta()` で代替可能）。
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
- 2026-07-08（レビュー反映） / フェーズ1では `insert()`/`update()` を `bindValue()` ベースのプレースホルダへ置き換えない。AGENTS.mdの規約上、呼び出し側は `db()->insert(db()->escape($data), $table)` のように**事前にエスケープ済みの値**を渡す前提のため、それを `bindValue()` すると二重エスケープでデータが破損する。プレースホルダ化（呼び出し側の事前エスケープ規約の解消込み）はフェーズ2に完全に切り出す。フェーズ1は既存どおり `driver->escape()` でエスケープした値をSQL文字列へ埋め込む方式を維持する。
- 2026-07-08（レビュー反映） / PDO接続失敗時は `PDO::ATTR_ERRMODE` の設定に関わらず `new PDO(...)` が `PDOException` を送出する（PHPマニュアルに明記された特例）。`connect()` は `try/catch(PDOException)` で例外を捕捉し、既存の「接続失敗時は `false` を返す」フローへ変換する。
- 2026-07-08（レビュー反映） / `dataSeek()` は公開APIの互換対象から除外しない。`Result` はコンストラクタ時点で `fetchAll(PDO::FETCH_BOTH)` により**結果セット全体**を内部配列へバッファし、`fetch*()`/`dataSeek()` は以後この配列とカーソル位置だけで完結させる方式を採用する。
- 2026-07-08（2回目のレビュー反映） / 上記の行バッファ方式について、初期案は「初回シーク時に残り行だけをFETCH_ASSOCでキャッシュ」としていたが、これだと（a）既読済みの行への絶対位置シークが壊れる、（b）`fetchRow()`/`fetchBoth()` に必要な数値添字が欠落する、という2つの不具合があるとの指摘を受けた。修正として、バッファ生成をコンストラクタ時点（＝クエリ実行直後）に前倒しし、`FETCH_BOTH` で数値・連想の両方のキーを保持する設計に変更した。
- 2026-07-08（レビュー反映） / `escape()` の契約は2層で分離する。`DriverInterface::escape(string $value): string` は文字列のみを受け取り、エスケープ済み文字列を返す（`PDO::quote()` の前後クォート除去 + 文字列限定）。`DBAPI::escape()` ファサードは現行の分岐（未接続時 `connect()` 失敗で `false`・`null` は `'NULL'`・配列は再帰的に配列を返す）をそのまま維持し、数値・真偽値など文字列以外の値はここで `string` へ正規化したうえで `$this->driver->escape($value)` を呼ぶ。
- 2026-07-08（レビュー反映） / `PDO::query()`/`PDO::exec()` が失敗（`false`）を返した場合、`PDOStatement` は存在しないため `PDOStatement::errorInfo()` は呼べない。errno判定は接続オブジェクト側の `PDO::errorInfo()[1]` を参照する。
- 2026-07-08（ユーザー指摘で発覚） / 新設する `DBAPI` クラスから公開プロパティ `$conn` を廃止し、内部保持先は `private $driver`（`PdoMysqlDriver` インスタンス）のみとする。理由: `$conn` が生の接続オブジェクトである前提でインストーラの2ファイルが直接操作しており、型を公開したまま残すとPDO化後も「生のPDOインスタンスを外部に渡す」実装を強いられ抽象化が崩れる。`$conn` への直接アクセス箇所（3箇所、Surprises参照）は Concrete Steps で `DBAPI` の既存公開メソッド（`isConnected()`/`disconnect()`/`query()`）を使うよう書き換える。非推奨の `DocumentParser::dbConnect()`（呼び出し箇所0件）は本フェーズの互換対象から明示的に除外する（Interfaces and Dependencies参照）。

## Outcomes & Retrospective

## Context and Orientation

**対象ファイル**: `manager/includes/extenders/dbapi/mysqli.inc.php`（`DBAPI` クラス、1,251行）
**ロード元**: `manager/includes/extenders/ex_dbapi.php`（`$this->db = new DBAPI` で1インスタンスを生成し `evo()->db` として全体へ公開）

**用語定義**:

- **DBAPI**: Evolution CMSのDBアクセスを抽象化するファサードクラス。`db()` ヘルパー経由で全コードから呼ばれる。
- **Result**: 1件のクエリ実行結果を表すオブジェクト。本改修で新設するラッパークラスで、内部で `PDOStatement` を保持する。
- **Driver**: 実際のDB接続・クエリ実行・SQL方言差分を担当する実装単位。`DBAPI` は Driver を通じてのみDBへアクセスし、Driverを差し替えれば別DBMSに対応できる設計にする（実装は MySQL のみ）。

**呼び出し規模**（grep調査、`db()->` 経由のみ）: `select` 396、`getRow` 290、`escape` 206、`query` 171、`update` 128、`getValue` 91、`delete` 67、`insert` 58、`getLastError` 32、その他 `isResult`/`getObject`/`getColumn`/`freeResult`/`tableExists`/`makeArray`/`getInsertId`/`isConnected`/`getVersion`/`getAffectedRows`/`connect`/`truncate`/`prop`/`get`/`save`/`numFields`/`insert_ignore`/`getFullTableName`/`exec`/`lastQuery`/`getObjects`/`fieldName`/`fieldExists`/`server_info`/`select_db`/`optimize`/`host_info`/`getRecordCount`/`getLastErrorNo`/`disconnect` が合計約60箇所。**これらすべての公開メソッドのシグネチャと返却値の意味を変えない**ことが本フェーズの必須要件。

**公開プロパティ `$conn` への直接アクセス**（メソッド経由ではない例外、`rg -n '\->conn\b' install/ manager/includes/document.parser.class.inc.php -g '*.php'` で確認済み、計3箇所）:

- `install/connection.servertest.php` 24〜26行目
- `install/connection.databasetest.php` 30〜32行目、110行目
- `manager/includes/document.parser.class.inc.php` 5478行目（非推奨 `dbConnect()`、呼び出し箇所0件のため本フェーズの互換対象外）

前2ファイルは新設する `DBAPI` の公開メソッドのみで書き換え可能なため、Concrete Steps で対応する（Step 3）。

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
- `escape(string $value): string`（**文字列のみ**を受け取り、クォートなし文字列を返す。`null` 判定・配列の再帰処理・未接続時のフォールバックに加え、数値・真偽値など文字列以外の値を `string` へ正規化する責務は呼び出し元の `DBAPI::escape()` が担当し、Driverには渡さない）
- `lastInsertId(): string|int`
- `affectedRows(): int`
- `lastError(): string`
- `lastErrorNo(): int`
- `quoteIdentifier(string $name): string`（バッククォート等の識別子クォート、方言差分の吸収点）
- `isConnected(): bool`
- `close(): void`

`PdoMysqlDriver` はこれを `PDO` で実装する。

### 接続確立（`connect()`）の移植

- `PDO` への接続文字列（**DSN**: Data Source Name。接続先ホスト・文字コードなどをひとつの文字列にまとめたもので、`new PDO($dsn, ...)` の第1引数に渡す）は `mysql:host={host};charset={charset}` 形式（ポート指定がある場合は `;port={port}`）。
- 接続タイムアウトは `PDO::ATTR_TIMEOUT`（`$timeout` が指定された場合のみ設定）。
- 既存コードの `mysqli_report(MYSQLI_REPORT_OFF)` 相当として、接続後のクエリ実行は `PDO::ATTR_ERRMODE = PDO::ERRMODE_SILENT` を設定し、エラーは戻り値・`errorInfo()` で判定する（例外を投げさせない）。**ただし接続確立自体（`new PDO(...)`）は `ATTR_ERRMODE` の設定に関わらず失敗時に必ず `PDOException` を送出する**（PHPマニュアルに明記された特例）。そのため `new PDO(...)` は `try { ... } catch (PDOException $e) { return false; }` で包み、既存の「接続失敗時は `false` を返してメール通知」という制御フローへ変換する。
- 接続後の `$this->connection_method . ' ' . $this->charset`（`SET CHARACTER SET utf8` 等）の実行は、PDO接続後に `PDO::exec()` でそのまま流用可能。
- `select_db()` はPDOに直接の等価メソッドがないため、DSNに `dbname` を含めて接続するか、接続後に `USE` 文（識別子は `quoteIdentifier()` でクォート）を実行する形に置き換える（既存が接続後に `select_db()` を呼ぶ設計のため、後者を踏襲する）。

### エラー処理（`exec()`）の移植

既存は `!in_array($this->conn->errno, [1064, 1054, 1060, 1061, 1091])` の場合のみ `messageQuit()` で停止し、該当errnoは黙って `true` を返す（**冪等**＝同じDDLを何度実行しても結果が変わらないこと。例えば「既に存在するカラムをADD COLUMNしようとしてエラーになっても処理を継続する」ことで、同じマイグレーションを繰り返し実行しても安全にする）。PDOでは `PDO::query($sql)` が失敗すると `false` を返し、この時点では `PDOStatement` が存在しないため `PDOStatement::errorInfo()` は呼び出せない。errno判定は**接続オブジェクト側の `PDO::errorInfo()[1]`**（`$this->pdo->errorInfo()[1]`）を参照する。クエリが成功した場合は `PDOStatement::errorInfo()` でも同じ値が取れるが、失敗時の判定には接続オブジェクト側を使うことで統一する。

### Result ラッパー

`PDOStatement` を保持し、以下を提供する:

- コンストラクタで即座に `fetchAll(PDO::FETCH_BOTH)`（数値添字・連想キーの両方を持つ行の配列。mysqliの `fetch_array(MYSQLI_BOTH)` と同じ形）を実行し、**結果セットの全行を内部配列としてバッファする**。以後の `fetchAssoc()`/`fetchRow()`/`fetchObject()`/`fetchBoth()`/`dataSeek()` は生の `PDOStatement` へは触れず、すべてこの内部配列とカーソル位置（初期値0）だけで完結させる。PDO_MYSQLはデフォルトでバッファードクエリ（結果セットをMySQLクライアント側に読み切ってから返す方式）のため、`fetchAll()` を1回追加で行っても新たな通信は発生しない
- `numRows(): int` — 内部配列の要素数を返す（`rowCount()` には依存しない。他ドライバでの挙動差はフェーズ3で要検討）
- `fetchAssoc()` — カーソル位置の行から連想キー部分のみを取り出して返し、カーソルを1つ進める
- `fetchRow()` — 同様に数値添字部分のみを取り出す
- `fetchBoth()` — カーソル位置の行（数値・連想の両方を含む）をそのまま返す
- `fetchObject()` — カーソル位置の行の連想キー部分を `(object)` キャストして返す
- `columnCount(): int` — `PDOStatement::columnCount()`（メタ情報のため `fetchAll()` 後も呼び出し可能）
- `columnName(int $i): string` — `PDOStatement::getColumnMeta($i)['name']`
- `dataSeek(int $n): bool` — 内部配列のカーソル位置を `$n` に移動する。既読・未読を問わず全行が内部配列に揃っているため、絶対位置への移動も正しく機能する。範囲外の `$n` を指定した場合は `false` を返す（mysqliの`data_seek()`と同じ契約）

### `DBAPI` ファサードの実装

`mysqli.inc.php` の各メソッド本体を、`$this->conn->` への直接アクセスから `$this->driver->` 経由の呼び出しへ書き換える。SQL文字列の組み立てロジック（`select()`/`update()`/`insert()`/`delete()`/`_where()`/`replaceFullTableName()` 等）はDB非依存のためほぼそのまま流用する。

`escape()` は現行の分岐構造（未接続時 `connect()` 失敗で `false`・`null` は `'NULL'`・配列は各要素を再帰的に `escape()` して配列のまま返す）を**そのまま維持**し、単一スカラー値に到達した最後の分岐だけを `$this->conn->escape_string($s)` から `$this->driver->escape($s)` へ置き換える。

### プレースホルダ移行はフェーズ1の対象外

AGENTS.mdの規約（`db()->insert(db()->escape($data), $table)` / `db()->update(db()->escape($data), $table, $where)`）により、呼び出し側は**事前にエスケープ済みの値**を `insert()`/`update()` に渡すことが前提になっている。この前提のまま内部を `PDOStatement::bindValue()` に置き換えると、エスケープ済み文字列（例: バックスラッシュでエスケープ済みの `it\'s`）がそのままバインドされ、二重エスケープされた値がDBに保存されてしまう（例: 実際の値ではなく `it\'s` という文字列がそのまま保存される）。

したがって、フェーズ1では `insert()`/`update()` の内部実装を `bindValue()` 化しない。`$this->driver->escape()` でエスケープした値をSQL文字列へ埋め込む既存方式を維持し、生成されるSQL文字列とその実行結果がmysqli版と完全に一致することを優先する。プレースホルダ移行（呼び出し側の事前エスケープ規約の解消を含む）はフェーズ2で扱う独立したテーマとする。

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

### Step 3: インストーラの `$conn` 直接アクセスを解消

新設する `DBAPI` は公開プロパティ `$conn` を持たない（Decision Log参照）ため、Step 4（`ex_dbapi.php` の読み込み先切り替え）より前に本Stepを完了しておく必要がある。対象は Context and Orientation に記載した3箇所のうち、呼び出し実績のある2ファイル（`DocumentParser::dbConnect()` は呼び出し箇所0件のため対象外）。

編集対象: `install/connection.servertest.php` 24〜26行目

変更前:

    if (db()->conn) {
        db()->conn->close();
        db()->conn = null;
    }

変更後:

    if (db()->isConnected()) {
        db()->disconnect();
    }

編集対象: `install/connection.databasetest.php` 30〜32行目・110行目

30〜32行目は上記と同じ変更を行う。110行目は以下のとおり変更する。

変更前: `return @db()->conn->query($query);`
変更後: `return db()->query($query, false);`（`query($sql, $watchError = true)` は既存の公開メソッドで、第2引数を `false` にすることで従来の `@`（エラー抑制）と同じく `messageQuit()` を呼ばずに `false` を返す挙動を維持する）

期待される観測結果: `rg -n '\->conn\b' install/ -g '*.php'` が0件になる。`php -l install/connection.servertest.php install/connection.databasetest.php` が構文エラーなし。

### Step 4: `ex_dbapi.php` の読み込み先を変更

編集対象: `manager/includes/extenders/ex_dbapi.php` 2行目

変更前: `include_once __DIR__ . '/dbapi/mysqli.inc.php';`
変更後: `include_once __DIR__ . '/dbapi/DBAPI.php';`

期待される観測結果: `docker compose exec <app-service> php evo db:tables` が引き続きテーブル一覧を返す（新しいロード先で接続が確立できている証拠）。ブラウザでインストーラのDB接続テスト画面（`connection.servertest.php`/`connection.databasetest.php` を呼ぶ画面）を開き、Step 3で書き換えた2ファイルがエラーなく動作することも確認する。

### Step 5: 旧ファイルを削除

削除対象: `manager/includes/extenders/dbapi/mysqli.inc.php`

Step 1〜4の動作確認が完了してから削除する（Idempotence and Recoveryも参照）。

期待される観測結果: `grep -rn "mysqli.inc.php" --include="*.php" .` が0件になる。削除後も `docker compose exec <app-service> php evo db:tables` が引き続き成功する。

### Step 6: 判定用の互換メッセージ・チェックを更新

編集対象:

- `manager/includes/cli/bootstrap.php` — `extension_loaded('mysqli')` を `extension_loaded('pdo_mysql')` に変更する
- `manager/includes/cli/commands/health-check.php` — `check('PHP mysqli extension', extension_loaded('mysqli'))` を `check('PHP pdo_mysql extension', extension_loaded('pdo_mysql'))` に変更する
- `install/instprocessor.php` 282行目 — `$ph['database_type'] = 'mysqli';` を `$ph['database_type'] = 'pdo_mysql';` に変更する。この値は生成される `config.inc.php` に書き込まれるのみで実行時に読み取られる箇所がない（grep調査で確認済み）ため、実態を反映する文字列変更のみで影響はない
- `manager/includes/default.config.php` 118〜120行目 — `mysqli_set_charset`/`mysql_set_charset` の存在チェックを `extension_loaded('pdo_mysql')` に変更する。警告文言中の「mysqli.incのescape関数の処理を書き換えてください。mb_convert_encodingの処理を行なっている行が2行ありますので」は現行の `escape()` 実装に該当箇所がなく既に実態と合っていないため、この一文を削除し「対応が必要な場合は、サーバ環境のUTF-8エンコードの扱いを整備してください。」に置き換える

これらは実処理に影響しないため、Step 1〜5完了後に更新する。

期待される観測結果: `rg -n "extension_loaded\\('mysqli'\\)|database_type'\\s*=\\s*'mysqli'|mysqli_set_charset" manager/includes/cli install/instprocessor.php install/ -g '*.php'` が0件になる（`mysqli.inc.php` 自体は既に削除済みのため対象外）。`docker compose exec <app-service> php evo health:check` が `PHP pdo_mysql extension` 項目を表示し、正常完了する。

### Step 7: ドキュメントのパス言及を更新

編集対象: `assets/docs/architecture.md` 24行目

`architecture.md` 24行目は現在 `DBAPI`（`manager/includes/extenders/dbapi/mysqli.inc.php` など）が遅延ロードされ、と記述されている。この一文中のファイルパス部分のみを `manager/includes/extenders/dbapi/mysqli.inc.php` から `manager/includes/extenders/dbapi/DBAPI.php` に更新する（前後の文章は変更しない）。コミット前に `/doc-audit` でこのファイルを含む変更対象を確認する（AGENTS.mdの運用ルールに従う）。

期待される観測結果: `grep -n "mysqli.inc.php" assets/docs/architecture.md` が0件になり、代わりに `grep -n "DBAPI.php" assets/docs/architecture.md` が1件ヒットする。

## Validation and Acceptance

いずれもDockerコンテナ内（`docker compose exec <app-service> ...`）で確認する。

1. **CLIによる疎通確認**: `php evo db:tables` がテーブル一覧を正常に返す（接続・`SHOW TABLES` 相当の実行が機能している証拠）
2. **CRUD確認**: `php evo db:query "SELECT * FROM {prefix}system_settings LIMIT 5"` が結果を返す。`php evo db:count site_content` が件数を返す
3. **管理画面での動作確認**: ブラウザで管理画面にログインし、リソース一覧表示（`select`/`getRow`/`count` 経由）、リソースの新規作成・更新・削除（`insert`/`update`/`delete` 経由）が従来どおり動作する
4. **エラー抑制の互換確認**: 既に存在するカラムへの `ADD COLUMN` を含むSQL（errno 1060相当）を実行し、`messageQuit()` で停止せず処理が継続することを確認する（マイグレーション機構やbaseline実行時の冪等性に影響するため重要）
5. **`getObject`/`getObjects`/`makeArray`/`getXML`/`getHTMLGrid` の確認**: 管理画面のデータグリッド表示（`datagrid.class.php` 経由、`numFields`/`fieldName` を使用）が崩れずに表示される
6. **健全性チェック**: `php evo health:check` がエラーなく完了する
7. **インストーラの動作確認**: インストーラのDB接続テスト画面（サーバ接続テスト・DB接続テスト）を実行し、`connection.servertest.php`/`connection.databasetest.php` がエラーなく応答する。既存DBが存在する状態で再テストしても（`isConnected()`→`disconnect()`経由の再接続が機能し）正常に完了する

## Idempotence and Recovery

- Step 1〜2は新規ファイル追加のみのため、途中で中断しても既存の `mysqli.inc.php` の読み込みには影響しない
- Step 3（インストーラの `$conn` 直接アクセス解消）はStep 4より前に完了させる。Step 3のみを先行して個別にコミットしても、`isConnected()`/`disconnect()`/`query()` は現行の `mysqli.inc.php` にも存在するため単独で動作し、後方互換を壊さない
- Step 4（`ex_dbapi.php` の読み込み先変更）は1行の変更のみのため、途中で中断しても `mysqli.inc.php` が残っていれば元に戻すだけで旧実装に復帰できる
- Step 5（旧ファイル削除）はStep 1〜4の動作確認が完了するまで実行しない
- 本番相当の検証は必ずDBのバックアップ（`php evo db:backup` 等の既存コマンド）を取得してから行う

## Artifacts and Notes

- `manager/includes/extenders/ex_dbapi.php` — DBAPIのロード・グローバル設定箇所
- `manager/includes/controls/datagrid.class.php` — `numFields`/`fieldName` の利用箇所
- `manager/includes/traits/document.parser.subparser.trait.php` — `numFields` の利用箇所
- `install/connection.servertest.php` / `install/connection.databasetest.php` — インストーラのDB接続テスト画面。`$conn` への直接アクセス箇所（Step 3で修正）
- `manager/includes/document.parser.class.inc.php` 5475〜5479行目 — 非推奨 `DocumentParser::dbConnect()`（呼び出し箇所0件、本フェーズの互換対象外）
- `manager/includes/mysql_dumper.class.inc.php` — `db()` 経由でDBAPIに依存するが本フェーズのスコープ外（`assets/docs/core-issues.md` 参照）
- `assets/docs/core-issues.md` — 「Mysqldumperクラスの設計上の制約」の関連ロードマップとして本タスクが記録済み

## Interfaces and Dependencies

- **依存**: PHP `pdo_mysql` 拡張（`mysqli` 拡張からの切り替え。Docker環境のPHPイメージに含まれているか事前確認が必要）
- **依存**: `evo()` グローバルヘルパー（`messageQuit`/`sendmail`/`dumpSQLCode`/`getMicroTime` 等、`DBAPI` 内部から使用。変更なし）
- **影響範囲**: `db()` ヘルパーを経由する全コード（約1,700箇所）。ただし公開API契約を維持するため、呼び出し側コードの変更は不要
- **影響範囲（インストーラ）**: `install/connection.servertest.php`・`install/connection.databasetest.php` は `$conn` 直接アクセスをやめ既存公開メソッドのみを使う形に書き換える（Step 3）。それ以外のインストーラファイル（`install/index.php`・`install/instprocessor.php` 等）は `db()` ヘルパー経由のみで変更不要
- **互換対象外**: `manager/includes/document.parser.class.inc.php` の非推奨 `DocumentParser::dbConnect()`（呼び出し箇所0件）。将来この関数が呼ばれた場合、`$this->rs` には `$conn` ではなく内部Driverインスタンスが入る点が既存挙動と異なる
- **将来の拡張**: `DriverInterface` を実装する `PdoPgsqlDriver` / `PdoSqliteDriver` を追加すれば、`DBAPI` 側のコード変更なしにマルチDB対応が可能になる設計（実装はフェーズ3、別ExecPlanで扱う）
