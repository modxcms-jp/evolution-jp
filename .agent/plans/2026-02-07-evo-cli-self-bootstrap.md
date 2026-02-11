# ExecPlan: EVO CLI Self-Bootstrap (Phase 0-1)

## Purpose / Big Picture
Evolution CMS JP Edition に最小限の CLI エントリポイントと DB コマンドを追加し、後続の CLI 機能を自分自身で生成できる土台を作る。結果として、管理作業や開発補助が CLI で再現可能になる。

## Progress
- [x] (2026-02-07) 既存コードと初期化フローを確認し、CLI 配置先を `manager/includes/cli/` に決定
- [x] (2026-02-07) ルート `evo` エントリポイントを追加し、CLI ルーティングを実装
- [x] (2026-02-07) CLI ブートストラップを追加し、`DocumentParser` 初期化を有効化
- [x] (2026-02-07) 最小コマンド（`help`/`db:console`/`db:query`/`make:command`）を実装
- [x] (2026-02-07) CLI 補助機能（出力ヘルパー、`cache:clear`/`config:show`/`db:tables`/`db:describe`/`db:count`）を追加
- [x] (2026-02-07) CLI での実行時の安定化（セッション抑止、`mb_internal_encoding` ガード）を実施
- [x] (2026-02-07) Docker 環境で CLI 動作確認（`help`/`db:query`/`make:command`/`db:tables`/`db:describe`/`db:count`/`cache:clear`）
- [x] (2026-02-07) CLI README と ExecPlan を更新
- [x] (2026-02-11) コマンド候補を整理し優先順位を決定: `health:check` > `log:show` > `db:backup` > `log:clear`。`db:restore` は `db:import` と重複するため不要
- [x] (2026-02-11) CLI 出力形式の方針を決定: `--json` は必要になったコマンドから個別対応。共通規約として正常=exit 0/stdout、エラー=exit 1+/stderr
- [x] (2026-02-11) `logEvent` 方針を決定: 副作用コマンドで `evo()->logEvent(0, 1, "...", 'CLI')` を記録。実装は待機
- [ ] (2026-02-11) `health:check` を実装（`ConfigCheck` クラスを CLI から呼び出し）
- [ ] (2026-02-11) `log:show` を実装（`event_log` テーブルを整形表示、HTML 除去を試みる）
- [ ] (2026-02-11) `db:backup` を実装（`db:export` + snapshot パス + 世代管理）
- [ ] (2026-02-11) `log:clear` を実装（`event_log` テーブルの TRUNCATE）
- [ ] (2026-02-11) Docker 環境で新コマンドの動作確認
- [ ] (2026-02-11) CLI README を更新
- [x] (2026-02-07) `db:export` を実装（`mysql_dumper` を利用し `--tables` と `--output` を提供）
- [x] (2026-02-07) Docker で `db:export` の出力を確認（`/tmp/site_content.sql` を生成）
- [x] (2026-02-07) `db:import` を実装し、環境変数 `EVO_CLI_IMPORT=1` を必須化
- [x] (2026-02-07) Docker で安全確認（未指定時に拒否されることを確認）
- [x] (2026-02-07) `db:import` は `system_cache` を除外・`TRUNCATE` し、`system_settings` はインポート後に `site_url`/`base_url`/`filemanager_path`/`rb_base_dir` を復元する
- [x] (2026-02-07) 開発環境で `db:import` 実行確認（`/tmp/site_content.sql` を取り込み）
- [x] (2026-02-07) `db:import` の保全テストを実施（`site_url`/`base_url` を保持、`system_cache` が空であることを確認）
- [x] (2026-02-07) `db:query` で結果セットが返らない場合はエラー扱いにする
- [x] (2026-02-11) `db:export` を `mysqldump` ラッパーに改修（デフォルト動作を変更）
- [x] (2026-02-11) `db:console` と共通の認証情報ヘルパー（`--defaults-extra-file` 一時ファイル生成）を `cli-helpers.php` に抽出
- [x] (2026-02-11) `--driver=php` オプションで既存 `Mysqldumper` クラスへのフォールバックを実装
- [x] (2026-02-11) `mysqldump` が利用不可の場合のエラーメッセージと `--driver=php` への誘導
- [x] (2026-02-11) Docker 環境で `db:export` の動作確認（mysqldump 版 / --driver=php 版 / --tables 版 / stdout 版）
- [x] (2026-02-11) CLI README を更新（`db:export` の新オプション記載）

## Surprises & Discoveries
- (2026-02-11) 既存の `Mysqldumper` クラスは `addslashes` ベースのエスケープ、トランザクション整合性なし、最終的に全体を `file_get_contents` でメモリに読み込む設計。大規模 DB やバイナリデータでは信頼性に懸念があり、`mysqldump` をデフォルトにする方針を決定。
- (2026-02-11) MariaDB クライアントの `mysqldump` は MySQL 8 サーバーに接続時に TLS 自己署名証明書エラーが発生。SSL 無効化オプションも MariaDB は `ssl=0`、MySQL は `ssl-mode=DISABLED` と異なる。ヘルパーで `mysql --version` の出力からクライアント種別を判定して対応。また `--no-tablespaces` が PROCESS 権限なしの環境で必要。

## Decision Log
2026-02-07: コマンド名は `evo` を採用する。短く入力しやすく、既存のブランド名を保持できるため。代替案は `evolution` と `evocli`。
2026-02-07: CLI 本体は公開領域の URL 競合を避けるため `manager/includes/` 配下に配置する。`evo` エントリポイントはルート配置を許容する。
2026-02-11: `db:export` のデフォルトを `mysqldump` ラッパーに変更する。理由: ストリーミング出力で省メモリ、`--single-transaction` による一貫性、ネイティブエスケープの信頼性。既存 `Mysqldumper` は `--driver=php` で引き続き利用可能とする。
2026-02-11: 次期コマンド優先順位を `health:check` > `log:show` > `db:backup` > `log:clear` に決定。`db:restore` は `db:import` と重複するため見送り。`--json` 出力は全面導入せず必要なコマンドから個別対応。`logEvent` 記録は副作用コマンドに限定し `title='CLI'` で統一、実装は待機。

## Outcomes & Retrospective
実装後に記載

## Context and Orientation
CLI エントリポイントは未実装。ロードマップでは `cli.php` の整備とコマンドルーティングが Phase 1 に位置付けられている。`AGENTS.md` に従い、グローバルアクセスは `evo()` / `db()` / `manager()` を経由し、スーパーグローバルは禁止。CLI では `$_SERVER` が最小限なので `serverv()` などのラッパーは前提にせず、必要な情報は CLI 専用の引数や設定から取得する。CLI ブートストラップで `$_SERVER` の必要最小限を補完する場合は、互換目的で `serverv()` を使ってもよい。既存のフロントエンドは `DocumentParser` 中心だが、CLI はフロントルーティングとは独立して初期化を行う必要がある。関連ドキュメントは `assets/docs/architecture.md` と `assets/docs/events-and-plugins.md` を参照する。

用語:
CLI はコマンドラインから実行する小さなプログラム群。ブートストラップは最小限の初期化処理。コマンドルーティングは入力されたコマンド名を対応する処理に振り分ける仕組み。DocumentParser はフロントエンドのリクエスト解析と出力を担う中心クラス。

## Plan of Work
最小の CLI ディレクトリ構成を作り、`cli/evo` から `cli/bootstrap.php` を読み込む。`bootstrap.php` では必要最小限の設定読み込みと `evo()` / `db()` 初期化を行い、コマンドは `cli/commands/*.php` の手続き型で実装する。最初は `help`、`db:console`、`db:query`、`make:command` を用意し、以降は `make:command` で拡張できる状態にする。

## Concrete Steps
1. 既存コードから初期化パターンを確認する。`index.php` と `manager/includes/document.parser.class.inc.php` の読み込み順と必要定数を把握し、CLI 用の最小初期化セットを整理する。
2. `manager/includes/cli/` を作成し、`manager/includes/cli/bootstrap.php`、`manager/includes/cli/README.md`、`manager/includes/cli/commands/help.php`、`manager/includes/cli/commands/db-console.php`、`manager/includes/cli/commands/db-query.php`、`manager/includes/cli/commands/make-command.php` を追加する。`evo` エントリポイントはリポジトリルートに配置する。

```bash
chmod +x evo
```
3. `evo` で `EVO_CLI` と `EVO_CLI_START` を定義し、`manager/includes/cli/bootstrap.php` を読み込む。`$argv` からコマンド名と引数を取り、`:` を `-` に変換してファイルをロードする。
4. `bootstrap.php` で必要な設定ファイルを読み込み、`evo()` / `db()` を利用可能にする。スーパーグローバルは使用せず、`serverv()` などのラッパーを使う。
5. `db-console.php` は MySQL クライアントを安全に起動する。パスワード露出を避けるため、`--defaults-file` 用の一時ファイルを作り、終了時に削除する。
6. `db-query.php` はクエリを受け取り、`db()` を用いて実行し、結果を整形して表示する。空クエリ時は Usage を返す。
7. `make-command.php` は `command:name` を `command-name.php` に変換し、雛形を生成する。
8. `manager/includes/cli/README.md` に使い方の例を追記する。CLI を有効にする前提条件と注意点も明記する。
9. `db:console` と `db:export` で共通利用する認証情報ヘルパー関数を `bootstrap.php` または専用ファイルに抽出する。一時ファイル生成・`chmod 0600`・`register_shutdown_function` による削除をまとめる。
10. `db-export.php` を改修し、デフォルトで `mysqldump` コマンドを実行する。認証はステップ 9 のヘルパーを利用し、`--single-transaction --routines --triggers` をデフォルトオプションとする。出力は `--output` 指定時はファイルへ、未指定時は stdout へ流す。
11. `--driver=php` オプション指定時は従来の `Mysqldumper` クラスを呼び出す（既存ロジックを維持）。
12. `mysqldump` が見つからない・実行失敗した場合はエラーメッセージで `--driver=php` の存在を案内する。
13. `db-console.php` をリファクタリングし、ステップ 9 のヘルパーを利用するように変更する。
14. CLI README に `db:export` の新しいオプション（`--driver=php`）を追記する。

## Validation and Acceptance
以下のコマンドを実行し、期待結果が得られること。

```bash
php evo help
```

期待: 既存コマンド一覧が表示される。

```bash
php evo db:query "SELECT 1"
```

期待: `1` を含む結果が表示される。

```bash
php evo make:command cache:clear
```

期待: `manager/includes/cli/commands/cache-clear.php` が生成され、作成メッセージが出る。

```bash
php evo db:console
```

期待: MySQL クライアントが起動し、終了後に一時ファイルが削除される。

```bash
php evo db:export --output=/tmp/test_dump.sql
```

期待: `mysqldump` 経由で `/tmp/test_dump.sql` が生成される。ファイル先頭に `-- MySQL dump` 等の mysqldump ヘッダが含まれる。

```bash
php evo db:export --tables=site_content --output=/tmp/test_table.sql
```

期待: `site_content` テーブルのみがエクスポートされる。

```bash
php evo db:export --driver=php --output=/tmp/test_php.sql
```

期待: 既存 `Mysqldumper` クラス経由でエクスポートされる。ヘッダに `Database Dump` の文字列が含まれる。

```bash
# mysqldump が利用できない環境で
php evo db:export
```

期待: エラーメッセージに `--driver=php` の案内が表示される。

## Idempotence and Recovery
作成したファイルは再実行で上書きしない設計にする。`make:command` は既存ファイルがある場合に中断する。失敗時は `manager/includes/cli/` 配下と `evo` を削除すれば元の状態に戻せる。

## Artifacts and Notes
関連ファイル: `evo`, `manager/includes/cli/bootstrap.php`, `manager/includes/cli/commands/*.php`, `manager/includes/cli/README.md`  
関連ドキュメント: `assets/docs/architecture.md`, `assets/docs/events-and-plugins.md`, `assets/docs/roadmap.md`, `AGENTS.md`

## Interfaces and Dependencies
外部依存は PHP CLI と MySQL クライアント。`db:export` のデフォルト動作には `mysqldump` コマンドが必要（`--driver=php` で回避可能）。DB 接続情報は既存設定を利用し、認証情報は `--defaults-extra-file` 経由の一時ファイルで安全に渡す。イベント発火や `DocumentParser` との連携が必要になった場合は `evo()->invokeEvent()` を使用する。
